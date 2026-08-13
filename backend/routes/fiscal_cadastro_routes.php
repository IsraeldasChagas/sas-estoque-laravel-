<?php

/**
 * Módulo 1 — Cadastro Fiscal (empresas, perfis tributários).
 * Sem cálculo de imposto ou movimentação fiscal.
 */

use App\Support\AuditLog;
use App\Support\FiscalCadastroSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

$fiscalCors = fn () => response()->json([])
    ->header('Access-Control-Allow-Origin', '*')
    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');

$fiscalAuth = function (Request $req) {
    $uid = $req->header('X-Usuario-Id');

    return $uid ? DB::table('usuarios')->where('id', $uid)->where('ativo', 1)->first() : null;
};

$fiscalPodeVer = function ($u) {
    if (! $u) {
        return false;
    }
    $p = strtoupper(trim((string) ($u->perfil ?? '')));

    return in_array($p, ['ADMIN', 'ADMINISTRADOR', 'GERENTE'], true);
};

$fiscalPodeEditar = function ($u) {
    if (! $u) {
        return false;
    }
    $p = strtoupper(trim((string) ($u->perfil ?? '')));

    return in_array($p, ['ADMIN', 'ADMINISTRADOR'], true);
};

$fiscalJson = fn ($data, int $code = 200) => response()->json($data, $code)
    ->header('Access-Control-Allow-Origin', '*');

$fiscalRowGet = static function ($row, string $key, $default = null) {
    if ($row === null) {
        return $default;
    }
    if (is_array($row)) {
        return $row[$key] ?? $default;
    }

    return property_exists($row, $key) ? $row->{$key} : $default;
};

$mapEmpresa = static function ($row) use ($fiscalRowGet) {
    if (! $row) {
        return null;
    }

    return [
        'id' => (int) $fiscalRowGet($row, 'id', 0),
        'razao_social' => $fiscalRowGet($row, 'razao_social'),
        'nome_fantasia' => $fiscalRowGet($row, 'nome_fantasia'),
        'cnpj' => $fiscalRowGet($row, 'cnpj'),
        'inscricao_estadual' => $fiscalRowGet($row, 'inscricao_estadual'),
        'inscricao_municipal' => $fiscalRowGet($row, 'inscricao_municipal'),
        'regime_tributario' => $fiscalRowGet($row, 'regime_tributario'),
        'crt' => $fiscalRowGet($row, 'crt'),
        'uf' => $fiscalRowGet($row, 'uf'),
        'municipio' => $fiscalRowGet($row, 'municipio'),
        'logradouro' => $fiscalRowGet($row, 'logradouro'),
        'numero' => $fiscalRowGet($row, 'numero'),
        'bairro' => $fiscalRowGet($row, 'bairro'),
        'cep' => $fiscalRowGet($row, 'cep'),
        'codigo_municipio' => $fiscalRowGet($row, 'codigo_municipio'),
        'ativo' => (bool) ($fiscalRowGet($row, 'ativo', true)),
        'created_at' => $fiscalRowGet($row, 'created_at'),
        'updated_at' => $fiscalRowGet($row, 'updated_at'),
    ];
};

$mapPerfil = static function ($row) use ($fiscalRowGet) {
    if (! $row) {
        return null;
    }

    return [
        'id' => (int) $fiscalRowGet($row, 'id', 0),
        'nome' => $fiscalRowGet($row, 'nome'),
        'descricao' => $fiscalRowGet($row, 'descricao'),
        'tipo_fiscal_padrao' => $fiscalRowGet($row, 'tipo_fiscal_padrao'),
        'ncm_padrao' => $fiscalRowGet($row, 'ncm_padrao'),
        'cest_padrao' => $fiscalRowGet($row, 'cest_padrao'),
        'cst_icms' => $fiscalRowGet($row, 'cst_icms'),
        'csosn' => $fiscalRowGet($row, 'csosn'),
        'cfop_entrada_padrao' => $fiscalRowGet($row, 'cfop_entrada_padrao'),
        'cfop_saida_padrao' => $fiscalRowGet($row, 'cfop_saida_padrao'),
        'tratamento_icms' => $fiscalRowGet($row, 'tratamento_icms'),
        'tratamento_pis' => $fiscalRowGet($row, 'tratamento_pis'),
        'tratamento_cofins' => $fiscalRowGet($row, 'tratamento_cofins'),
        'tratamento_ipi' => $fiscalRowGet($row, 'tratamento_ipi'),
        'tratamento_cbs' => $fiscalRowGet($row, 'tratamento_cbs'),
        'tratamento_ibs' => $fiscalRowGet($row, 'tratamento_ibs'),
        'monofasico' => (bool) ($fiscalRowGet($row, 'monofasico', false)),
        'substituicao_tributaria' => (bool) ($fiscalRowGet($row, 'substituicao_tributaria', false)),
        'gera_credito_icms' => (bool) ($fiscalRowGet($row, 'gera_credito_icms', false)),
        'gera_credito_pis' => (bool) ($fiscalRowGet($row, 'gera_credito_pis', false)),
        'gera_credito_cofins' => (bool) ($fiscalRowGet($row, 'gera_credito_cofins', false)),
        'ativo' => (bool) ($fiscalRowGet($row, 'ativo', true)),
        'observacoes' => $fiscalRowGet($row, 'observacoes'),
        'created_at' => $fiscalRowGet($row, 'created_at'),
        'updated_at' => $fiscalRowGet($row, 'updated_at'),
    ];
};

foreach ([
    '/fiscal/empresas',
    '/fiscal/empresas/{id}',
    '/fiscal/perfis-tributarios',
    '/fiscal/perfis-tributarios/{id}',
    '/fiscal/perfis-tributarios/{id}/sugestao-produto',
    '/fiscal/meta',
] as $p) {
    Route::options($p, $fiscalCors);
}

Route::get('/fiscal/meta', function () use ($fiscalJson) {
    return $fiscalJson([
        'tipos_fiscais' => FiscalCadastroSupport::TIPOS_FISCAIS,
        'regimes_tributarios' => FiscalCadastroSupport::REGIMES_TRIBUTARIOS,
        'origens_mercadoria' => FiscalCadastroSupport::ORIGENS_MERCADORIA,
    ]);
});

Route::get('/fiscal/empresas', function (Request $request) use ($fiscalAuth, $fiscalPodeVer, $fiscalJson, $mapEmpresa) {
    try {
        $u = $fiscalAuth($request);
        if (! $fiscalPodeVer($u)) {
            return $fiscalJson(['error' => 'Não autorizado'], 401);
        }
        if (! Schema::hasTable('empresas')) {
            return $fiscalJson(['error' => 'Módulo fiscal não instalado. Execute php artisan migrate.', 'empresas' => []], 503);
        }
        $rows = DB::table('empresas')->orderBy('razao_social')->get();
        $out = [];
        foreach ($rows as $row) {
            $out[] = $mapEmpresa($row);
        }

        return $fiscalJson($out);
    } catch (\Throwable $e) {
        report($e);

        return $fiscalJson(['error' => 'Falha ao listar empresas', 'message' => $e->getMessage()], 500);
    }
});

Route::post('/fiscal/empresas', function (Request $request) use ($fiscalAuth, $fiscalPodeEditar, $fiscalJson, $mapEmpresa) {
    $u = $fiscalAuth($request);
    if (! $fiscalPodeEditar($u)) {
        return $fiscalJson(['error' => 'Não autorizado'], 403);
    }
    if (! Schema::hasTable('empresas')) {
        return $fiscalJson(['error' => 'Módulo fiscal não instalado. Execute php artisan migrate.'], 503);
    }
    $data = $request->validate([
        'razao_social' => 'required|string|max:255',
        'nome_fantasia' => 'nullable|string|max:255',
        'cnpj' => 'nullable|string|max:20',
        'inscricao_estadual' => 'nullable|string|max:30',
        'inscricao_municipal' => 'nullable|string|max:30',
        'regime_tributario' => ['nullable', 'string', Rule::in(FiscalCadastroSupport::REGIMES_TRIBUTARIOS)],
        'crt' => 'nullable|string|max:2',
        'uf' => 'nullable|string|size:2',
        'municipio' => 'nullable|string|max:120',
        'logradouro' => 'nullable|string|max:255',
        'numero' => 'nullable|string|max:20',
        'bairro' => 'nullable|string|max:120',
        'cep' => 'nullable|string|max:10',
        'codigo_municipio' => 'nullable|string|max:7',
        'ativo' => 'nullable|boolean',
    ]);
    $cnpj = FiscalCadastroSupport::normalizarCnpj($data['cnpj'] ?? null);
    if (($data['cnpj'] ?? '') !== '' && $cnpj === null) {
        return $fiscalJson(['error' => 'CNPJ inválido', 'message' => 'CNPJ deve conter 14 dígitos.'], 422);
    }
    $now = now();
    $insert = [
        'razao_social' => $data['razao_social'],
        'nome_fantasia' => $data['nome_fantasia'] ?? null,
        'cnpj' => $cnpj,
        'inscricao_estadual' => $data['inscricao_estadual'] ?? null,
        'inscricao_municipal' => $data['inscricao_municipal'] ?? null,
        'regime_tributario' => $data['regime_tributario'] ?? null,
        'crt' => $data['crt'] ?? null,
        'uf' => isset($data['uf']) ? strtoupper($data['uf']) : null,
        'municipio' => $data['municipio'] ?? null,
        'ativo' => array_key_exists('ativo', $data) ? (int) (bool) $data['ativo'] : 1,
        'created_at' => $now,
        'updated_at' => $now,
    ];
    foreach (['logradouro', 'numero', 'bairro'] as $k) {
        if (Schema::hasColumn('empresas', $k)) {
            $insert[$k] = $data[$k] ?? null;
        }
    }
    if (Schema::hasColumn('empresas', 'cep')) {
        $cepDigits = isset($data['cep']) ? preg_replace('/\D+/', '', (string) $data['cep']) : null;
        $insert['cep'] = $cepDigits !== '' ? $cepDigits : null;
    }
    if (Schema::hasColumn('empresas', 'codigo_municipio')) {
        $insert['codigo_municipio'] = $data['codigo_municipio'] ?? null;
    }
    $id = DB::table('empresas')->insertGetId($insert);
    AuditLog::registrar((int) $u->id, 'criar', 'empresa_fiscal', $id, 'Empresa fiscal criada', null, $request);

    return $fiscalJson($mapEmpresa(DB::table('empresas')->where('id', $id)->first()), 201);
});

Route::put('/fiscal/empresas/{id}', function (Request $request, $id) use ($fiscalAuth, $fiscalPodeEditar, $fiscalJson, $mapEmpresa) {
    $u = $fiscalAuth($request);
    if (! $fiscalPodeEditar($u)) {
        return $fiscalJson(['error' => 'Não autorizado'], 403);
    }
    $id = (int) $id;
    $existing = DB::table('empresas')->where('id', $id)->first();
    if (! $existing) {
        return $fiscalJson(['error' => 'Empresa não encontrada'], 404);
    }
    $data = $request->validate([
        'razao_social' => 'sometimes|required|string|max:255',
        'nome_fantasia' => 'nullable|string|max:255',
        'cnpj' => 'nullable|string|max:20',
        'inscricao_estadual' => 'nullable|string|max:30',
        'inscricao_municipal' => 'nullable|string|max:30',
        'regime_tributario' => ['nullable', 'string', Rule::in(FiscalCadastroSupport::REGIMES_TRIBUTARIOS)],
        'crt' => 'nullable|string|max:2',
        'uf' => 'nullable|string|size:2',
        'municipio' => 'nullable|string|max:120',
        'logradouro' => 'nullable|string|max:255',
        'numero' => 'nullable|string|max:20',
        'bairro' => 'nullable|string|max:120',
        'cep' => 'nullable|string|max:10',
        'codigo_municipio' => 'nullable|string|max:7',
        'ativo' => 'nullable|boolean',
    ]);
    $update = ['updated_at' => now()];
    foreach (['razao_social', 'nome_fantasia', 'inscricao_estadual', 'inscricao_municipal', 'regime_tributario', 'crt', 'municipio', 'logradouro', 'numero', 'bairro', 'codigo_municipio'] as $k) {
        if (array_key_exists($k, $data) && ($k === 'razao_social' || Schema::hasColumn('empresas', $k) || in_array($k, ['nome_fantasia', 'inscricao_estadual', 'inscricao_municipal', 'regime_tributario', 'crt', 'municipio'], true))) {
            if (in_array($k, ['logradouro', 'numero', 'bairro', 'codigo_municipio'], true) && ! Schema::hasColumn('empresas', $k)) {
                continue;
            }
            $update[$k] = $data[$k];
        }
    }
    if (array_key_exists('cep', $data) && Schema::hasColumn('empresas', 'cep')) {
        $cepDigits = preg_replace('/\D+/', '', (string) ($data['cep'] ?? ''));
        $update['cep'] = $cepDigits !== '' ? $cepDigits : null;
    }
    if (array_key_exists('uf', $data)) {
        $update['uf'] = $data['uf'] ? strtoupper($data['uf']) : null;
    }
    if (array_key_exists('cnpj', $data)) {
        $cnpj = FiscalCadastroSupport::normalizarCnpj($data['cnpj']);
        if (($data['cnpj'] ?? '') !== '' && $cnpj === null) {
            return $fiscalJson(['error' => 'CNPJ inválido'], 422);
        }
        $update['cnpj'] = $cnpj;
    }
    if (array_key_exists('ativo', $data)) {
        $update['ativo'] = (int) (bool) $data['ativo'];
    }
    DB::table('empresas')->where('id', $id)->update($update);
    AuditLog::registrar((int) $u->id, 'atualizar', 'empresa_fiscal', $id, 'Empresa fiscal atualizada', null, $request);

    return $fiscalJson($mapEmpresa(DB::table('empresas')->where('id', $id)->first()));
});

Route::get('/fiscal/perfis-tributarios', function (Request $request) use ($fiscalAuth, $fiscalPodeVer, $fiscalJson, $mapPerfil) {
    $u = $fiscalAuth($request);
    if (! $fiscalPodeVer($u)) {
        return $fiscalJson(['error' => 'Não autorizado'], 401);
    }
    if (! Schema::hasTable('perfis_tributarios')) {
        return $fiscalJson([]);
    }
    $q = DB::table('perfis_tributarios');
    if ($request->query('ativos') === '1') {
        $q->where('ativo', 1);
    }
    $rows = $q->orderBy('nome')->get();
    $out = [];
    foreach ($rows as $row) {
        $out[] = $mapPerfil($row);
    }

    return $fiscalJson($out);
});

Route::post('/fiscal/perfis-tributarios', function (Request $request) use ($fiscalAuth, $fiscalPodeEditar, $fiscalJson, $mapPerfil) {
    $u = $fiscalAuth($request);
    if (! $fiscalPodeEditar($u)) {
        return $fiscalJson(['error' => 'Não autorizado'], 403);
    }
    if (! Schema::hasTable('perfis_tributarios')) {
        return $fiscalJson(['error' => 'Módulo fiscal não instalado. Execute php artisan migrate.'], 503);
    }
    $data = $request->validate([
        'nome' => 'required|string|max:150',
        'descricao' => 'nullable|string',
        'tipo_fiscal_padrao' => ['nullable', 'string', Rule::in(FiscalCadastroSupport::TIPOS_FISCAIS)],
        'ncm_padrao' => 'nullable|string|max:12',
        'cest_padrao' => 'nullable|string|max:10',
        'cst_icms' => 'nullable|string|max:5',
        'csosn' => 'nullable|string|max:6',
        'cfop_entrada_padrao' => 'nullable|string|max:6',
        'cfop_saida_padrao' => 'nullable|string|max:6',
        'tratamento_icms' => 'nullable|string|max:80',
        'tratamento_pis' => 'nullable|string|max:80',
        'tratamento_cofins' => 'nullable|string|max:80',
        'tratamento_ipi' => 'nullable|string|max:80',
        'tratamento_cbs' => 'nullable|string|max:80',
        'tratamento_ibs' => 'nullable|string|max:80',
        'monofasico' => 'nullable|boolean',
        'substituicao_tributaria' => 'nullable|boolean',
        'gera_credito_icms' => 'nullable|boolean',
        'gera_credito_pis' => 'nullable|boolean',
        'gera_credito_cofins' => 'nullable|boolean',
        'ativo' => 'nullable|boolean',
        'observacoes' => 'nullable|string',
    ]);
    $now = now();
    $row = [
        'nome' => $data['nome'],
        'descricao' => $data['descricao'] ?? null,
        'tipo_fiscal_padrao' => $data['tipo_fiscal_padrao'] ?? null,
        'ncm_padrao' => FiscalCadastroSupport::normalizarNcm($data['ncm_padrao'] ?? null),
        'cest_padrao' => FiscalCadastroSupport::normalizarCest($data['cest_padrao'] ?? null),
        'cst_icms' => FiscalCadastroSupport::normalizarCst($data['cst_icms'] ?? null),
        'csosn' => FiscalCadastroSupport::normalizarCsosn($data['csosn'] ?? null),
        'cfop_entrada_padrao' => FiscalCadastroSupport::normalizarCfop($data['cfop_entrada_padrao'] ?? null),
        'cfop_saida_padrao' => FiscalCadastroSupport::normalizarCfop($data['cfop_saida_padrao'] ?? null),
        'tratamento_icms' => $data['tratamento_icms'] ?? null,
        'tratamento_pis' => $data['tratamento_pis'] ?? null,
        'tratamento_cofins' => $data['tratamento_cofins'] ?? null,
        'tratamento_ipi' => $data['tratamento_ipi'] ?? null,
        'tratamento_cbs' => $data['tratamento_cbs'] ?? null,
        'tratamento_ibs' => $data['tratamento_ibs'] ?? null,
        'monofasico' => (int) (bool) ($data['monofasico'] ?? false),
        'substituicao_tributaria' => (int) (bool) ($data['substituicao_tributaria'] ?? false),
        'gera_credito_icms' => (int) (bool) ($data['gera_credito_icms'] ?? false),
        'gera_credito_pis' => (int) (bool) ($data['gera_credito_pis'] ?? false),
        'gera_credito_cofins' => (int) (bool) ($data['gera_credito_cofins'] ?? false),
        'ativo' => (int) (bool) ($data['ativo'] ?? true),
        'observacoes' => $data['observacoes'] ?? null,
        'created_at' => $now,
        'updated_at' => $now,
    ];
    $id = DB::table('perfis_tributarios')->insertGetId($row);
    AuditLog::registrar((int) $u->id, 'criar', 'perfil_tributario', $id, 'Perfil tributário criado', null, $request);

    return $fiscalJson($mapPerfil(DB::table('perfis_tributarios')->where('id', $id)->first()), 201);
});

Route::put('/fiscal/perfis-tributarios/{id}', function (Request $request, $id) use ($fiscalAuth, $fiscalPodeEditar, $fiscalJson, $mapPerfil) {
    $u = $fiscalAuth($request);
    if (! $fiscalPodeEditar($u)) {
        return $fiscalJson(['error' => 'Não autorizado'], 403);
    }
    $id = (int) $id;
    if (! DB::table('perfis_tributarios')->where('id', $id)->exists()) {
        return $fiscalJson(['error' => 'Perfil não encontrado'], 404);
    }
    $data = $request->validate([
        'nome' => 'sometimes|required|string|max:150',
        'descricao' => 'nullable|string',
        'tipo_fiscal_padrao' => ['nullable', 'string', Rule::in(FiscalCadastroSupport::TIPOS_FISCAIS)],
        'ncm_padrao' => 'nullable|string|max:12',
        'cest_padrao' => 'nullable|string|max:10',
        'cst_icms' => 'nullable|string|max:5',
        'csosn' => 'nullable|string|max:6',
        'cfop_entrada_padrao' => 'nullable|string|max:6',
        'cfop_saida_padrao' => 'nullable|string|max:6',
        'tratamento_icms' => 'nullable|string|max:80',
        'tratamento_pis' => 'nullable|string|max:80',
        'tratamento_cofins' => 'nullable|string|max:80',
        'tratamento_ipi' => 'nullable|string|max:80',
        'tratamento_cbs' => 'nullable|string|max:80',
        'tratamento_ibs' => 'nullable|string|max:80',
        'monofasico' => 'nullable|boolean',
        'substituicao_tributaria' => 'nullable|boolean',
        'gera_credito_icms' => 'nullable|boolean',
        'gera_credito_pis' => 'nullable|boolean',
        'gera_credito_cofins' => 'nullable|boolean',
        'ativo' => 'nullable|boolean',
        'observacoes' => 'nullable|string',
    ]);
    $update = ['updated_at' => now()];
    foreach ([
        'nome', 'descricao', 'tipo_fiscal_padrao', 'tratamento_icms', 'tratamento_pis', 'tratamento_cofins',
        'tratamento_ipi', 'tratamento_cbs', 'tratamento_ibs', 'observacoes',
    ] as $k) {
        if (array_key_exists($k, $data)) {
            $update[$k] = $data[$k];
        }
    }
    if (array_key_exists('ncm_padrao', $data)) {
        $update['ncm_padrao'] = FiscalCadastroSupport::normalizarNcm($data['ncm_padrao']);
    }
    if (array_key_exists('cest_padrao', $data)) {
        $update['cest_padrao'] = FiscalCadastroSupport::normalizarCest($data['cest_padrao']);
    }
    if (array_key_exists('cst_icms', $data)) {
        $update['cst_icms'] = FiscalCadastroSupport::normalizarCst($data['cst_icms']);
    }
    if (array_key_exists('csosn', $data)) {
        $update['csosn'] = FiscalCadastroSupport::normalizarCsosn($data['csosn']);
    }
    if (array_key_exists('cfop_entrada_padrao', $data)) {
        $update['cfop_entrada_padrao'] = FiscalCadastroSupport::normalizarCfop($data['cfop_entrada_padrao']);
    }
    if (array_key_exists('cfop_saida_padrao', $data)) {
        $update['cfop_saida_padrao'] = FiscalCadastroSupport::normalizarCfop($data['cfop_saida_padrao']);
    }
    foreach (['monofasico', 'substituicao_tributaria', 'gera_credito_icms', 'gera_credito_pis', 'gera_credito_cofins', 'ativo'] as $b) {
        if (array_key_exists($b, $data)) {
            $update[$b] = (int) (bool) $data[$b];
        }
    }
    DB::table('perfis_tributarios')->where('id', $id)->update($update);
    AuditLog::registrar((int) $u->id, 'atualizar', 'perfil_tributario', $id, 'Perfil tributário atualizado', null, $request);

    return $fiscalJson($mapPerfil(DB::table('perfis_tributarios')->where('id', $id)->first()));
});

Route::get('/fiscal/perfis-tributarios/{id}/sugestao-produto', function (Request $request, $id) use ($fiscalAuth, $fiscalPodeVer, $fiscalJson) {
    $u = $fiscalAuth($request);
    if (! $fiscalPodeVer($u)) {
        return $fiscalJson(['error' => 'Não autorizado'], 401);
    }
    $row = DB::table('perfis_tributarios')->where('id', (int) $id)->first();
    if (! $row) {
        return $fiscalJson(['error' => 'Perfil não encontrado'], 404);
    }

    return $fiscalJson(['sugestao' => FiscalCadastroSupport::camposSugeridosDoPerfil($row)]);
});
