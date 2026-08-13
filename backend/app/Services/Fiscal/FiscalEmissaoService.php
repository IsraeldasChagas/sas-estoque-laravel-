<?php

namespace App\Services\Fiscal;

use App\Models\FiscalEmissaoConfig;
use App\Support\FiscalEmissaoConfigSupport;
use App\Support\FocusEmpresaCscSync;
use App\Support\FocusNfcePayloadBuilder;
use App\Support\VendaFiscalSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class FiscalEmissaoService
{
    /**
     * Após venda finalizada, tenta emitir NFC-e Focus quando configurado.
     *
     * @return array<string, mixed>
     */
    public static function emitirNfceParaVenda(int $vendaId, bool $forcar = false): array
    {
        if (! Schema::hasTable('vendas') || ! Schema::hasTable('fiscal_emissao_configs')) {
            return self::skip('Módulo de emissão não migrado.');
        }

        $venda = DB::table('vendas')->where('id', $vendaId)->first();
        if (! $venda) {
            return self::skip('Venda não encontrada.');
        }

        $stDoc = strtolower(trim((string) ($venda->status_documento ?? '')));
        // Já autorizada: nunca reemite (mesmo com forçar), para não gerar duplicidade.
        if (in_array($stDoc, ['autorizado', 'autorizada'], true)) {
            return [
                'emitida' => true,
                'skipped' => true,
                'motivo_skip' => 'NFC-e já autorizada para esta venda.',
                'venda_id' => $vendaId,
                'chave' => $venda->chave_acesso ?? null,
                'documentos' => FiscalDocumentoService::rotasRelativas($vendaId),
            ];
        }

        // Recuperação: autorização ficou só no log (ex.: 500 ao gravar chave).
        if ($forcar) {
            $cfgTmp = null;
            $empresaIdTmp = (int) ($venda->empresa_id ?? 0);
            if ($empresaIdTmp > 0) {
                $cfgTmp = FiscalEmissaoConfig::query()->where('empresa_id', $empresaIdTmp)->first();
            }
            if ($cfgTmp) {
                $recuperada = self::recuperarAutorizacaoDoLog($vendaId, $cfgTmp);
                if ($recuperada !== null) {
                    return $recuperada;
                }
            }
        }

        $empresaId = (int) ($venda->empresa_id ?? 0);
        if ($empresaId <= 0) {
            $empresaId = (int) (VendaFiscalSupport::resolverEmpresaUnidade((int) $venda->unidade_id) ?? 0);
        }
        if ($empresaId <= 0) {
            return self::skip('Venda sem empresa/CNPJ vinculado.');
        }

        $config = FiscalEmissaoConfig::query()->where('empresa_id', $empresaId)->first();
        if (! $config) {
            return self::skip('Configure emissão Focus em Configurações → Emissão NF-e / NFC-e.');
        }
        if (! $forcar && ! $config->is_active) {
            return self::skip('Emissão não está ativa para este CNPJ.');
        }
        if (! $config->emitir_nfce_pdv) {
            return self::skip('NFC-e no PDV desabilitada na configuração.');
        }
        if ($config->provider !== 'focus_nfe') {
            return self::skip('Provedor ' . $config->provider . ' ainda não implementado para emissão automática.');
        }
        if (empty($config->api_token)) {
            return self::skip('Token Focus não configurado.');
        }

        $empresa = DB::table('empresas')->where('id', $empresaId)->first();
        if (! $empresa) {
            return self::fail($vendaId, $empresaId, null, 'Empresa emitente não cadastrada.');
        }

        $empArr = [
            'cnpj' => $empresa->cnpj,
            'regime_tributario' => $empresa->regime_tributario,
            'inscricao_estadual' => $empresa->inscricao_estadual,
            'uf' => $empresa->uf,
        ];
        $prontidao = FiscalEmissaoConfigSupport::avaliarProntidao($config, $empArr);
        if (! $forcar && ! ($prontidao['pronto'] ?? false)) {
            return self::skip('Checklist de emissão incompleto — abra Emissão NF-e / NFC-e e valide.');
        }

        $itens = DB::table('venda_itens')->where('venda_id', $vendaId)->orderBy('id')->get()->all();
        if ($itens === []) {
            return self::fail($vendaId, $empresaId, $config, 'Venda sem itens.');
        }

        $produtoIds = array_map(fn ($i) => (int) $i->produto_id, $itens);
        $produtos = DB::table('produtos')->whereIn('id', $produtoIds)->get()->keyBy('id')->all();

        try {
            $payload = FocusNfcePayloadBuilder::build($venda, $itens, $empresa, $config, $produtos);
        } catch (\InvalidArgumentException $e) {
            return self::fail($vendaId, $empresaId, $config, $e->getMessage());
        }

        // Focus usa CSC no cadastro da empresa (/v2/empresas). Tentamos sincronizar;
        // se o token não puder gerenciar empresas (401), seguimos a emissão — o CSC
        // precisa já estar no painel Focus (DETALHES).
        $syncCsc = FocusEmpresaCscSync::sincronizar($config, $empresa);
        if (! ($syncCsc['ok'] ?? false) && empty($syncCsc['skipped']) && empty($syncCsc['auth_error'])) {
            return self::fail(
                $vendaId,
                $empresaId,
                $config,
                'CSC não sincronizado na Focus: '.($syncCsc['motivo'] ?? 'falha desconhecida')
                    .' — confira ID/Token CSC no SAS e no painel Focus (Empresas → DETALHES).'
            );
        }

        $ref = 'sas-v' . $vendaId . '-' . time();
        $payload['_ref'] = $ref;

        $baseUrl = $config->api_url ?: FiscalEmissaoConfigSupport::focusBaseUrl((string) $config->environment);
        $client = new FocusNfeClient($baseUrl, (string) $config->api_token);
        $emitter = new FocusNfeDocumentEmitter($client);
        $out = $emitter->emitirNfce($empresaId, $payload);

        if (! ($out['success'] ?? false)) {
            $err = (string) ($out['error'] ?? '');
            if (stripos($err, 'CSC') !== false || stripos($err, 'Id Token') !== false) {
                $hint = ! empty($syncCsc['auth_error'])
                    ? ' Sync automático falhou (token sem permissão de empresas). No Focus: Tokens → Token principal produção no SAS, OU Empesas → DETALHES → preencher CSC/Id Token produção.'
                    : ' Preencha CSC/Id Token em Focus → Empresas → DETALHES (produção), ou salve de novo no SAS com Token principal.';
                $out['error'] = $err.' |'.$hint;
            }
        }

        self::registrarLog($vendaId, $empresaId, $ref, $out);

        if ($out['success'] ?? false) {
            try {
                self::persistirAutorizacao($vendaId, $config, $ref, $out);
            } catch (\Throwable $e) {
                // Nota já autorizada na Focus — não pode “sumir” no SAS.
                report($e);
                try {
                    self::persistirAutorizacao($vendaId, $config, $ref, $out, true);
                } catch (\Throwable $e2) {
                    report($e2);
                }

                return [
                    'emitida' => true,
                    'skipped' => false,
                    'status' => 'autorizado',
                    'chave' => self::normalizarChaveAcesso($out['chave'] ?? null),
                    'numero' => $out['numero'] ?? null,
                    'serie' => $out['serie'] ?? null,
                    'url_danfe' => $out['danfe_url'] ?? null,
                    'url_xml' => $out['xml'] ?? null,
                    'ref' => $ref,
                    'venda_id' => $vendaId,
                    'documentos' => FiscalDocumentoService::rotasRelativas($vendaId),
                    'mensagem' => 'NFC-e autorizada na Focus. Houve aviso ao gravar no SAS: '.$e->getMessage(),
                ];
            }

            self::atualizarEventoVenda($vendaId);

            return [
                'emitida' => true,
                'skipped' => false,
                'status' => 'autorizado',
                'chave' => self::normalizarChaveAcesso($out['chave'] ?? null),
                'numero' => $out['numero'] ?? null,
                'serie' => $out['serie'] ?? null,
                'url_danfe' => $out['danfe_url'] ?? null,
                'url_xml' => $out['xml'] ?? null,
                'ref' => $ref,
                'venda_id' => $vendaId,
                'documentos' => FiscalDocumentoService::rotasRelativas($vendaId),
                'mensagem' => 'NFC-e autorizada. PDF e XML disponíveis no sistema.',
            ];
        }

        // Se esta venda já teve autorização anterior (ex.: 500 após sucesso), recupera em vez de insistir.
        $recuperada = self::recuperarAutorizacaoDoLog($vendaId, $config);
        if ($recuperada !== null) {
            return $recuperada;
        }

        $err = (string) ($out['error'] ?? 'Falha na emissão Focus.');

        // Duplicidade: SEFAZ já usou esse número — avança o próximo no SAS para não travar.
        if (stripos($err, 'Duplicidade') !== false || stripos($err, 'duplicidade') !== false) {
            $usado = self::extrairNumeroDaChaveNfce($err);
            $atual = (int) ($config->numero_proximo_nfce ?? 0);
            $proximo = max($atual + 1, ($usado ?? $atual) + 1, 2);
            $config->numero_proximo_nfce = $proximo;
            $config->save();
            $err .= " | Numeração avançada automaticamente para o próximo nº {$proximo}. Clique em Emitir nota de novo.";
        }

        DB::table('vendas')->where('id', $vendaId)->update([
            'emissao_ref' => $ref,
            'status_documento' => 'rejeitado',
            'emissao_mensagem' => mb_substr($err, 0, 2000),
            'updated_at' => now(),
        ]);

        return [
            'emitida' => false,
            'skipped' => false,
            'status' => $out['status'] ?? 'rejeitado',
            'ref' => $ref,
            'mensagem' => $err,
            'venda_id' => $vendaId,
        ];
    }

    /**
     * @param  array<string, mixed>  $out
     */
    private static function persistirAutorizacao(int $vendaId, FiscalEmissaoConfig $config, string $ref, array $out, bool $minimo = false): void
    {
        $chave = self::normalizarChaveAcesso($out['chave'] ?? null);
        $update = [
            'chave_acesso' => $chave,
            'numero_documento' => isset($out['numero']) ? (string) $out['numero'] : null,
            'serie_documento' => isset($out['serie']) ? (string) $out['serie'] : null,
            'emissao_ref' => $ref,
            'status_documento' => 'autorizado',
            'emissao_mensagem' => 'NFC-e autorizada via Focus NFe.',
            'updated_at' => now(),
        ];
        if (! $minimo) {
            $update['url_danfe'] = $out['danfe_url'] ?? null;
            if (Schema::hasColumn('vendas', 'url_xml')) {
                $update['url_xml'] = $out['xml'] ?? null;
            }
        }
        DB::table('vendas')->where('id', $vendaId)->update($update);

        if ($config->numero_proximo_nfce) {
            $num = (int) ($out['numero'] ?? 0);
            $proximo = max((int) $config->numero_proximo_nfce, $num + 1);
            $config->numero_proximo_nfce = $proximo;
            $config->status_emissao = 'ready';
            $config->save();
        }
    }

    /** Remove prefixo NFe/NFCe — coluna chave_acesso tem 44 dígitos. */
    private static function normalizarChaveAcesso(mixed $chave): ?string
    {
        if ($chave === null) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', (string) $chave);
        if ($digits === null || $digits === '') {
            return null;
        }
        if (strlen($digits) > 44) {
            $digits = substr($digits, -44);
        }

        return $digits;
    }

    /** @return array<string, mixed>|null */
    private static function recuperarAutorizacaoDoLog(int $vendaId, FiscalEmissaoConfig $config): ?array
    {
        if (! Schema::hasTable('fiscal_emissao_logs')) {
            return null;
        }
        $log = DB::table('fiscal_emissao_logs')
            ->where('venda_id', $vendaId)
            ->where('status', 'autorizado')
            ->orderByDesc('id')
            ->first();
        if (! $log) {
            return null;
        }
        $out = json_decode((string) ($log->resposta_json ?? ''), true);
        if (! is_array($out) || empty($out['success'])) {
            return null;
        }
        $ref = (string) ($log->ref ?? $out['ref'] ?? '');
        try {
            self::persistirAutorizacao($vendaId, $config, $ref, $out);
            self::atualizarEventoVenda($vendaId);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }

        return [
            'emitida' => true,
            'skipped' => false,
            'recuperada' => true,
            'status' => 'autorizado',
            'chave' => self::normalizarChaveAcesso($out['chave'] ?? null),
            'numero' => $out['numero'] ?? null,
            'serie' => $out['serie'] ?? null,
            'url_danfe' => $out['danfe_url'] ?? null,
            'url_xml' => $out['xml'] ?? null,
            'ref' => $ref,
            'venda_id' => $vendaId,
            'documentos' => FiscalDocumentoService::rotasRelativas($vendaId),
            'mensagem' => 'NFC-e já estava autorizada na Focus — vínculo recuperado no SAS.',
        ];
    }

    /** Extrai nNF (9 dígitos) de uma chave NFC-e citada na mensagem SEFAZ. */
    private static function extrairNumeroDaChaveNfce(string $mensagem): ?int
    {
        if (! preg_match('/chNFe[:\s]*([0-9]{44})/i', $mensagem, $m)) {
            return null;
        }
        $chave = $m[1];
        // cUF(2)+AAMM(4)+CNPJ(14)+mod(2)+serie(3)+nNF(9)+…
        $nNf = substr($chave, 25, 9);

        return ctype_digit($nNf) ? (int) $nNf : null;
    }

    /** @param array<string, mixed> $resultadoVenda */
    public static function anexarEmissaoAoResultado(array $resultadoVenda, bool $tentarEmissao = true): array
    {
        if (empty($resultadoVenda['venda_id'])) {
            return $resultadoVenda;
        }
        if (! $tentarEmissao) {
            $resultadoVenda['emissao'] = self::skip('Venda registrada sem emissão de NFC-e.');

            return $resultadoVenda;
        }
        $resultadoVenda['emissao'] = self::emitirNfceParaVenda((int) $resultadoVenda['venda_id']);

        return $resultadoVenda;
    }

    /**
     * @param  array<string, mixed>  $payload  pode conter emitir_nota (bool)
     */
    public static function deveEmitirParaPayload(int $unidadeId, array $payload): bool
    {
        $empresaId = (int) (VendaFiscalSupport::resolverEmpresaUnidade($unidadeId) ?? 0);
        $config = $empresaId > 0
            ? FiscalEmissaoConfig::query()->where('empresa_id', $empresaId)->first()
            : null;
        $explicit = null;
        if (array_key_exists('emitir_nota', $payload)) {
            $explicit = filter_var($payload['emitir_nota'], FILTER_VALIDATE_BOOLEAN);
        } elseif (array_key_exists('sem_emissao', $payload)) {
            $explicit = ! filter_var($payload['sem_emissao'], FILTER_VALIDATE_BOOLEAN);
        }

        return FiscalEmissaoConfigSupport::deveEmitirNoPdv($config, $explicit);
    }

    /** @return array<string, mixed> */
    private static function skip(string $motivo): array
    {
        return [
            'emitida' => false,
            'skipped' => true,
            'motivo_skip' => $motivo,
        ];
    }

    /** @return array<string, mixed> */
    private static function fail(int $vendaId, int $empresaId, ?FiscalEmissaoConfig $config, string $msg): array
    {
        DB::table('vendas')->where('id', $vendaId)->update([
            'status_documento' => 'rejeitado',
            'emissao_mensagem' => mb_substr($msg, 0, 2000),
            'updated_at' => now(),
        ]);

        return [
            'emitida' => false,
            'skipped' => false,
            'mensagem' => $msg,
            'venda_id' => $vendaId,
        ];
    }

    /** @param array<string, mixed> $out */
    private static function registrarLog(int $vendaId, int $empresaId, string $ref, array $out): void
    {
        if (! Schema::hasTable('fiscal_emissao_logs')) {
            return;
        }
        DB::table('fiscal_emissao_logs')->insert([
            'venda_id' => $vendaId,
            'empresa_id' => $empresaId,
            'provider' => 'focus_nfe',
            'ref' => $ref,
            'status' => $out['status'] ?? ($out['success'] ?? false ? 'autorizado' : 'erro'),
            'mensagem' => mb_substr((string) ($out['error'] ?? $out['mensagem'] ?? ''), 0, 65000),
            'resposta_json' => json_encode($out),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private static function atualizarEventoVenda(int $vendaId): void
    {
        if (! Schema::hasTable('eventos_fiscais')) {
            return;
        }
        DB::table('eventos_fiscais')
            ->where('venda_id', $vendaId)
            ->update([
                'status' => 'documento_emitido',
                'updated_at' => now(),
            ]);
    }
}
