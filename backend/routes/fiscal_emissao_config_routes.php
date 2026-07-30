<?php

/**
 * Configuração e emissão NFC-e (Focus NFe) integrada ao PDV.
 */

use App\Models\FiscalEmissaoConfig;
use App\Services\Fiscal\FiscalDocumentoService;
use App\Services\Integrations\HttpIntegrationClient;
use App\Support\FiscalEmissaoConfigSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

$fiscalEmCors = fn () => response()->json([])
    ->header('Access-Control-Allow-Origin', '*')
    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, OPTIONS')
    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');

$fiscalEmAuth = function (Request $req) {
    $uid = $req->header('X-Usuario-Id');

    return $uid ? DB::table('usuarios')->where('id', $uid)->where('ativo', 1)->first() : null;
};

$fiscalEmPodeVer = function ($u) {
    if (! $u) {
        return false;
    }
    $p = strtoupper(trim((string) ($u->perfil ?? '')));

    return in_array($p, ['ADMIN', 'ADMINISTRADOR', 'GERENTE'], true);
};

$fiscalEmPodeEditar = function ($u) {
    if (! $u) {
        return false;
    }
    $p = strtoupper(trim((string) ($u->perfil ?? '')));

    return in_array($p, ['ADMIN', 'ADMINISTRADOR'], true);
};

$fiscalEmJson = fn ($data, int $code = 200) => response()->json($data, $code)
    ->header('Access-Control-Allow-Origin', '*');

$mapEmpresaRow = static function ($row) {
    if (! $row) {
        return null;
    }

    return [
        'id' => (int) $row->id,
        'razao_social' => $row->razao_social ?? null,
        'nome_fantasia' => $row->nome_fantasia ?? null,
        'cnpj' => $row->cnpj ?? null,
        'inscricao_estadual' => $row->inscricao_estadual ?? null,
        'regime_tributario' => $row->regime_tributario ?? null,
        'uf' => $row->uf ?? null,
        'ativo' => (bool) ($row->ativo ?? true),
    ];
};

foreach ([
    '/fiscal/emissao/meta',
    '/fiscal/emissao/resumo',
    '/fiscal/emissao/config/{empresaId}',
    '/fiscal/emissao/config/{empresaId}/validar',
    '/fiscal/emissao/config/{empresaId}/testar',
    '/fiscal/emissao/vendas/{vendaId}/nfce',
    '/fiscal/emissao/vendas/{vendaId}/documentos',
    '/fiscal/emissao/vendas/{vendaId}/danfe.pdf',
    '/fiscal/emissao/vendas/{vendaId}/xml',
] as $p) {
    Route::options($p, $fiscalEmCors);
}

$fiscalEmFileResponse = static function (array $bin, string $disposition) {
    return response($bin['body'], 200, [
        'Content-Type' => $bin['content_type'],
        'Content-Disposition' => $disposition.'; filename="'.$bin['filename'].'"',
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Expose-Headers' => 'Content-Disposition, Content-Type',
    ]);
};

Route::get('/fiscal/emissao/meta', function () use ($fiscalEmJson) {
    return $fiscalEmJson([
        'providers' => FiscalEmissaoConfigSupport::PROVIDERS,
        'environments' => FiscalEmissaoConfigSupport::ENVIRONMENTS,
        'focus_urls' => FiscalEmissaoConfigSupport::FOCUS_API_URL,
        'fase_emissao' => 'focus_nfce_pdv',
        'mensagem' => 'Com emissão ativa, vendas PDV disparam NFC-e Focus automaticamente após a baixa de estoque.',
    ]);
});

Route::get('/fiscal/emissao/resumo', function (Request $request) use ($fiscalEmAuth, $fiscalEmPodeVer, $fiscalEmJson, $mapEmpresaRow) {
    $u = $fiscalEmAuth($request);
    if (! $fiscalEmPodeVer($u)) {
        return $fiscalEmJson(['error' => 'Não autorizado'], 401);
    }
    if (! Schema::hasTable('empresas')) {
        return $fiscalEmJson(['error' => 'Cadastre empresas fiscais primeiro.', 'empresas' => []], 503);
    }
    if (! Schema::hasTable('fiscal_emissao_configs')) {
        return $fiscalEmJson(['error' => 'Execute php artisan migrate (fiscal_emissao_configs).', 'empresas' => []], 503);
    }

    $empresas = DB::table('empresas')->where('ativo', 1)->orderBy('razao_social')->get();
    $configs = FiscalEmissaoConfig::query()->get()->keyBy('empresa_id');
    $out = [];
    foreach ($empresas as $row) {
        $emp = $mapEmpresaRow($row);
        $cfg = $configs->get((int) $row->id);
        $prontidao = FiscalEmissaoConfigSupport::avaliarProntidao($cfg, $emp);
        $out[] = [
            'empresa' => $emp,
            'config' => $cfg ? $cfg->paraPainel() : null,
            'prontidao' => $prontidao,
            'status_label' => FiscalEmissaoConfigSupport::labelStatus($prontidao['status']),
        ];
    }

    return $fiscalEmJson([
        'empresas' => $out,
        'pode_editar' => strtoupper(trim((string) ($u->perfil ?? ''))) === 'ADMIN'
            || strtoupper(trim((string) ($u->perfil ?? ''))) === 'ADMINISTRADOR',
    ]);
});

Route::get('/fiscal/emissao/config/{empresaId}', function (Request $request, $empresaId) use ($fiscalEmAuth, $fiscalEmPodeVer, $fiscalEmJson, $mapEmpresaRow) {
    $u = $fiscalEmAuth($request);
    if (! $fiscalEmPodeVer($u)) {
        return $fiscalEmJson(['error' => 'Não autorizado'], 401);
    }
    $empresaId = (int) $empresaId;
    $row = DB::table('empresas')->where('id', $empresaId)->first();
    if (! $row) {
        return $fiscalEmJson(['error' => 'Empresa não encontrada'], 404);
    }
    $emp = $mapEmpresaRow($row);
    $cfg = FiscalEmissaoConfig::query()->where('empresa_id', $empresaId)->first();
    $prontidao = FiscalEmissaoConfigSupport::avaliarProntidao($cfg, $emp);

    return $fiscalEmJson([
        'empresa' => $emp,
        'config' => $cfg ? $cfg->paraPainel() : null,
        'prontidao' => $prontidao,
        'defaults' => [
            'provider' => 'focus_nfe',
            'environment' => 'homologation',
            'emitir_nfce_pdv' => true,
            'emitir_nfe_pedido' => false,
            'is_active' => false,
        ],
    ]);
});

Route::put('/fiscal/emissao/config/{empresaId}', function (Request $request, $empresaId) use ($fiscalEmAuth, $fiscalEmPodeEditar, $fiscalEmJson, $mapEmpresaRow) {
    $u = $fiscalEmAuth($request);
    if (! $fiscalEmPodeEditar($u)) {
        return $fiscalEmJson(['error' => 'Somente administrador pode alterar.'], 403);
    }
    if (! Schema::hasTable('fiscal_emissao_configs')) {
        return $fiscalEmJson(['error' => 'Execute php artisan migrate.'], 503);
    }
    $empresaId = (int) $empresaId;
    if (! DB::table('empresas')->where('id', $empresaId)->exists()) {
        return $fiscalEmJson(['error' => 'Empresa não encontrada'], 404);
    }

    $data = $request->validate([
        'provider' => ['required', 'string', Rule::in(array_keys(FiscalEmissaoConfigSupport::PROVIDERS))],
        'environment' => ['required', 'in:homologation,production'],
        'api_url' => 'nullable|string|max:500',
        'api_token' => 'nullable|string|max:4000',
        'certificado_pfx_base64' => 'nullable|string|max:500000',
        'certificado_senha' => 'nullable|string|max:500',
        'csc_id' => 'nullable|string|max:20',
        'csc_token' => 'nullable|string|max:500',
        'serie_nfce' => 'nullable|integer|min:1|max:999',
        'serie_nfe' => 'nullable|integer|min:1|max:999',
        'numero_proximo_nfce' => 'nullable|integer|min:1',
        'numero_proximo_nfe' => 'nullable|integer|min:1',
        'emitir_nfce_pdv' => 'nullable|boolean',
        'emitir_nfe_pedido' => 'nullable|boolean',
        'is_active' => 'nullable|boolean',
        'observacoes' => 'nullable|string|max:2000',
    ]);

    $cfg = FiscalEmissaoConfig::query()->firstOrNew(['empresa_id' => $empresaId]);

    $cfg->provider = $data['provider'];
    $cfg->environment = $data['environment'];
    $apiUrl = isset($data['api_url']) ? trim((string) $data['api_url']) : '';
    if ($apiUrl === '' && $data['provider'] === 'focus_nfe') {
        $apiUrl = FiscalEmissaoConfigSupport::focusBaseUrl($data['environment']);
    }
    $cfg->api_url = $apiUrl !== '' ? $apiUrl : null;

    if (! empty($data['api_token']) && ! HttpIntegrationClient::isMaskedSecret($data['api_token'])) {
        $cfg->api_token = $data['api_token'];
    }
    if (! empty($data['certificado_pfx_base64'])) {
        $cfg->certificado_pfx = $data['certificado_pfx_base64'];
    }
    if (! empty($data['certificado_senha']) && ! HttpIntegrationClient::isMaskedSecret($data['certificado_senha'])) {
        $cfg->certificado_senha = $data['certificado_senha'];
    }
    if (array_key_exists('csc_id', $data)) {
        $cfg->csc_id = $data['csc_id'];
    }
    if (! empty($data['csc_token']) && ! HttpIntegrationClient::isMaskedSecret($data['csc_token'])) {
        $cfg->csc_token = $data['csc_token'];
    }

    foreach (['serie_nfce', 'serie_nfe', 'numero_proximo_nfce', 'numero_proximo_nfe'] as $k) {
        if (array_key_exists($k, $data)) {
            $cfg->{$k} = $data[$k];
        }
    }

    if (array_key_exists('emitir_nfce_pdv', $data)) {
        $cfg->emitir_nfce_pdv = (bool) $data['emitir_nfce_pdv'];
    }
    if (array_key_exists('emitir_nfe_pedido', $data)) {
        $cfg->emitir_nfe_pedido = (bool) $data['emitir_nfe_pedido'];
    }
    if (array_key_exists('is_active', $data)) {
        $cfg->is_active = (bool) $data['is_active'];
    }
    if (array_key_exists('observacoes', $data)) {
        $cfg->observacoes = $data['observacoes'];
    }

    $emp = $mapEmpresaRow(DB::table('empresas')->where('id', $empresaId)->first());
    $prontidao = FiscalEmissaoConfigSupport::avaliarProntidao($cfg, $emp);
    $cfg->status_emissao = $prontidao['status'];
    $cfg->last_validated_at = now();
    $cfg->last_validation_message = $prontidao['pronto']
        ? 'Configuração completa para a próxima fase de emissão.'
        : 'Revise os itens pendentes no checklist.';

    $cfg->save();

    return $fiscalEmJson([
        'config' => $cfg->fresh()->paraPainel(),
        'prontidao' => $prontidao,
    ]);
});

Route::post('/fiscal/emissao/config/{empresaId}/validar', function (Request $request, $empresaId) use ($fiscalEmAuth, $fiscalEmPodeVer, $fiscalEmJson, $mapEmpresaRow) {
    $u = $fiscalEmAuth($request);
    if (! $fiscalEmPodeVer($u)) {
        return $fiscalEmJson(['error' => 'Não autorizado'], 401);
    }
    $empresaId = (int) $empresaId;
    $row = DB::table('empresas')->where('id', $empresaId)->first();
    if (! $row) {
        return $fiscalEmJson(['error' => 'Empresa não encontrada'], 404);
    }
    $emp = $mapEmpresaRow($row);
    $cfg = FiscalEmissaoConfig::query()->where('empresa_id', $empresaId)->first();
    $prontidao = FiscalEmissaoConfigSupport::avaliarProntidao($cfg, $emp);
    if ($cfg) {
        $cfg->status_emissao = $prontidao['status'];
        $cfg->last_validated_at = now();
        $cfg->last_validation_message = $prontidao['pronto']
            ? 'Checklist OK — aguardando motor de emissão.'
            : 'Existem pendências no checklist.';
        $cfg->save();
    }

    return $fiscalEmJson(['prontidao' => $prontidao, 'config' => $cfg?->fresh()->paraPainel()]);
});

Route::post('/fiscal/emissao/config/{empresaId}/testar', function (Request $request, $empresaId) use ($fiscalEmAuth, $fiscalEmPodeEditar, $fiscalEmJson) {
    $u = $fiscalEmAuth($request);
    if (! $fiscalEmPodeEditar($u)) {
        return $fiscalEmJson(['error' => 'Somente administrador.'], 403);
    }
    $empresaId = (int) $empresaId;
    $cfg = FiscalEmissaoConfig::query()->where('empresa_id', $empresaId)->first();
    if (! $cfg) {
        return $fiscalEmJson(['error' => 'Salve a configuração antes de testar.'], 422);
    }

    $provider = (string) $cfg->provider;
    $ok = false;
    $message = '';

    if (in_array($provider, ['focus_nfe', 'nfe_io', 'tecnospeed'], true)) {
        if (empty($cfg->api_token)) {
            $message = 'Informe o token da API.';
        } elseif ($provider === 'focus_nfe') {
            $base = rtrim((string) ($cfg->api_url ?: 'https://homologacao.focusnfe.com.br'), '/');
            $ok = true;
            $message = 'Credenciais armazenadas. Teste HTTP ao emissor será feito na fase de emissão (Focus: '.$base.').';
        } else {
            $ok = ! empty($cfg->api_url) && ! empty($cfg->api_token);
            $message = $ok
                ? 'Token e URL presentes — pronto para integrar o emissor.'
                : 'Informe URL e token do provedor.';
        }
    } elseif ($provider === 'certificado_a1') {
        $ok = ! empty($cfg->certificado_pfx) && ! empty($cfg->certificado_senha);
        $message = $ok
            ? 'Certificado salvo (criptografado). Emissão direta ainda não implementada.'
            : 'Envie o .pfx e a senha.';
    } else {
        $message = 'Use Focus NFe ou NFe.io para emissão nesta fase.';
    }

    $cfg->last_validated_at = now();
    $cfg->last_validation_message = $message;
    $cfg->status_emissao = $ok ? 'pending' : 'error';
    $cfg->save();

    return $fiscalEmJson([
        'success' => $ok,
        'message' => $message,
        'environment' => $cfg->environment,
        'provider' => $provider,
    ]);
});

Route::post('/fiscal/emissao/vendas/{vendaId}/nfce', function (Request $request, $vendaId) use ($fiscalEmPodeEditar, $fiscalEmAuth, $fiscalEmJson) {
    $u = $fiscalEmAuth($request);
    if (! $fiscalEmPodeEditar($u)) {
        return $fiscalEmJson(['error' => 'Somente administrador.'], 403);
    }
    $result = \App\Services\Fiscal\FiscalEmissaoService::emitirNfceParaVenda((int) $vendaId, true);

    return $fiscalEmJson($result);
});

Route::get('/fiscal/emissao/vendas/{vendaId}/documentos', function (Request $request, $vendaId) use ($fiscalEmAuth, $fiscalEmPodeVer, $fiscalEmJson) {
    $u = $fiscalEmAuth($request);
    if (! $fiscalEmPodeVer($u)) {
        return $fiscalEmJson(['error' => 'Não autorizado'], 401);
    }
    try {
        return $fiscalEmJson(FiscalDocumentoService::info((int) $vendaId));
    } catch (\Throwable $e) {
        return $fiscalEmJson(['error' => $e->getMessage()], 422);
    }
});

Route::get('/fiscal/emissao/vendas/{vendaId}/danfe.pdf', function (Request $request, $vendaId) use ($fiscalEmAuth, $fiscalEmPodeVer, $fiscalEmJson, $fiscalEmFileResponse) {
    $u = $fiscalEmAuth($request);
    if (! $fiscalEmPodeVer($u)) {
        return $fiscalEmJson(['error' => 'Não autorizado'], 401);
    }
    try {
        $bin = FiscalDocumentoService::obterPdf((int) $vendaId);

        return $fiscalEmFileResponse($bin, 'inline');
    } catch (\Throwable $e) {
        return $fiscalEmJson(['error' => $e->getMessage()], 422);
    }
});

Route::get('/fiscal/emissao/vendas/{vendaId}/xml', function (Request $request, $vendaId) use ($fiscalEmAuth, $fiscalEmPodeVer, $fiscalEmJson, $fiscalEmFileResponse) {
    $u = $fiscalEmAuth($request);
    if (! $fiscalEmPodeVer($u)) {
        return $fiscalEmJson(['error' => 'Não autorizado'], 401);
    }
    try {
        $bin = FiscalDocumentoService::obterXml((int) $vendaId);

        return $fiscalEmFileResponse($bin, 'attachment');
    } catch (\Throwable $e) {
        return $fiscalEmJson(['error' => $e->getMessage()], 422);
    }
});
