<?php

namespace App\Services\Fiscal;

use App\Models\FiscalEmissaoConfig;
use App\Support\FiscalEmissaoConfigSupport;
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

        $ref = 'sas-v' . $vendaId . '-' . time();
        $payload['_ref'] = $ref;

        $baseUrl = $config->api_url ?: FiscalEmissaoConfigSupport::focusBaseUrl((string) $config->environment);
        $client = new FocusNfeClient($baseUrl, (string) $config->api_token);
        $emitter = new FocusNfeDocumentEmitter($client);
        $out = $emitter->emitirNfce($empresaId, $payload);

        self::registrarLog($vendaId, $empresaId, $ref, $out);

        if ($out['success'] ?? false) {
            DB::table('vendas')->where('id', $vendaId)->update([
                'chave_acesso' => $out['chave'] ?? null,
                'numero_documento' => $out['numero'] ?? null,
                'serie_documento' => $out['serie'] ?? null,
                'url_danfe' => $out['danfe_url'] ?? null,
                'emissao_ref' => $ref,
                'status_documento' => 'autorizado',
                'emissao_mensagem' => 'NFC-e autorizada via Focus NFe.',
                'updated_at' => now(),
            ]);

            if ($config->numero_proximo_nfce) {
                $config->numero_proximo_nfce = (int) $config->numero_proximo_nfce + 1;
                $config->status_emissao = 'ready';
                $config->save();
            }

            self::atualizarEventoVenda($vendaId);

            return [
                'emitida' => true,
                'skipped' => false,
                'status' => 'autorizado',
                'chave' => $out['chave'] ?? null,
                'numero' => $out['numero'] ?? null,
                'serie' => $out['serie'] ?? null,
                'url_danfe' => $out['danfe_url'] ?? null,
                'ref' => $ref,
                'mensagem' => 'NFC-e autorizada.',
            ];
        }

        $err = (string) ($out['error'] ?? 'Falha na emissão Focus.');
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

    /** @param array<string, mixed> $resultadoVenda */
    public static function anexarEmissaoAoResultado(array $resultadoVenda, bool $tentarEmissao = true): array
    {
        if (! $tentarEmissao || empty($resultadoVenda['venda_id'])) {
            return $resultadoVenda;
        }
        $resultadoVenda['emissao'] = self::emitirNfceParaVenda((int) $resultadoVenda['venda_id']);

        return $resultadoVenda;
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
