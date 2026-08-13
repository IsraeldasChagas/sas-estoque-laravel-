<?php

namespace App\Services\Fiscal;

use App\Models\FiscalEmissaoConfig;
use App\Support\FiscalEmissaoConfigSupport;
use App\Support\FiscalMovimentacaoSupport;
use App\Support\FocusNfeTransferenciaPayloadBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** NF-e 55 opcional na transferência de estoque entre CNPJs (Focus). */
final class FiscalNfeTransferenciaService
{
    public static function querEmitir(array $payload): bool
    {
        if (array_key_exists('emitir_nfe', $payload)) {
            return filter_var($payload['emitir_nfe'], FILTER_VALIDATE_BOOLEAN);
        }

        return false;
    }

    /** Valida cadastro/config ANTES de mover o estoque. */
    public static function validarAntesDeTransferir(int $deUnidadeId, int $paraUnidadeId, int $produtoId): ?string
    {
        try {
            self::contexto($deUnidadeId, $paraUnidadeId, $produtoId);
        } catch (\InvalidArgumentException $e) {
            return $e->getMessage();
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function emitirParaMovimentacao(int $movimentacaoId): array
    {
        $mov = DB::table('movimentacoes')->where('id', $movimentacaoId)->first();
        if (! $mov) {
            return ['emitida' => false, 'mensagem' => 'Movimentação não encontrada.'];
        }
        $chaveExistente = preg_replace('/\D+/', '', (string) ($mov->chave_acesso_documento ?? ''));
        if (is_string($chaveExistente) && strlen($chaveExistente) === 44) {
            return [
                'emitida' => true,
                'skipped' => true,
                'chave' => $chaveExistente,
                'mensagem' => 'NF-e já vinculada a esta transferência.',
            ];
        }

        $de = (int) ($mov->de_unidade_id ?? 0);
        $para = (int) ($mov->para_unidade_id ?? 0);
        $produtoId = (int) ($mov->produto_id ?? 0);
        try {
            $ctx = self::contexto($de, $para, $produtoId);
        } catch (\InvalidArgumentException $e) {
            return ['emitida' => false, 'mensagem' => $e->getMessage()];
        }

        $qtd = (float) ($mov->qtd ?? 0);
        $custo = (float) ($mov->custo_unitario ?? 0);
        try {
            $payload = FocusNfeTransferenciaPayloadBuilder::build(
                $ctx['origem'],
                $ctx['destino'],
                $ctx['produto'],
                $ctx['config'],
                $qtd,
                $custo,
                $movimentacaoId
            );
        } catch (\InvalidArgumentException $e) {
            return ['emitida' => false, 'mensagem' => $e->getMessage()];
        }

        $ref = 'sas-trf-'.$movimentacaoId.'-'.time();
        $payload['_ref'] = $ref;
        $baseUrl = $ctx['config']->api_url ?: FiscalEmissaoConfigSupport::focusBaseUrl((string) $ctx['config']->environment);
        $client = new FocusNfeClient($baseUrl, (string) $ctx['config']->api_token);
        $emitter = new FocusNfeDocumentEmitter($client);
        $out = $emitter->emitirNfe((int) $ctx['origem']->id, $payload);

        if (Schema::hasTable('fiscal_emissao_logs')) {
            DB::table('fiscal_emissao_logs')->insert([
                'venda_id' => null,
                'empresa_id' => (int) $ctx['origem']->id,
                'provider' => 'focus_nfe',
                'ref' => $ref,
                'status' => $out['status'] ?? ($out['success'] ?? false ? 'autorizado' : 'erro'),
                'mensagem' => mb_substr((string) ($out['error'] ?? $out['mensagem'] ?? ''), 0, 65000),
                'resposta_json' => json_encode($out),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (! ($out['success'] ?? false)) {
            return [
                'emitida' => false,
                'ref' => $ref,
                'mensagem' => (string) ($out['error'] ?? 'Falha ao emitir NF-e de transferência na Focus/SEFAZ.'),
            ];
        }

        $chave = preg_replace('/\D+/', '', (string) ($out['chave'] ?? ''));
        if (is_string($chave) && strlen($chave) > 44) {
            $chave = substr($chave, -44);
        }
        $update = [
            'numero_documento' => isset($out['numero']) ? (string) $out['numero'] : null,
            'chave_acesso_documento' => $chave ?: null,
            'modelo_documento' => '55',
            'status_documental' => ($chave && strlen($chave) === 44) ? 'vinculado' : 'pendente',
        ];
        if (Schema::hasColumn('movimentacoes', 'emissao_ref')) {
            $update['emissao_ref'] = $ref;
        }
        DB::table('movimentacoes')->where('id', $movimentacaoId)->update($update);

        if ($ctx['config']->numero_proximo_nfe) {
            $num = (int) ($out['numero'] ?? 0);
            $ctx['config']->numero_proximo_nfe = max((int) $ctx['config']->numero_proximo_nfe, $num + 1);
            $ctx['config']->save();
        }

        return [
            'emitida' => true,
            'status' => $out['status'] ?? 'autorizado',
            'chave' => $chave ?: null,
            'numero' => $out['numero'] ?? null,
            'serie' => $out['serie'] ?? null,
            'ref' => $ref,
            'mensagem' => 'NF-e de transferência autorizada (origem → destino).',
        ];
    }

    /**
     * @return array{origem: object, destino: object, produto: object, config: FiscalEmissaoConfig}
     */
    private static function contexto(int $deUnidadeId, int $paraUnidadeId, int $produtoId): array
    {
        $empOrigId = FiscalMovimentacaoSupport::resolverEmpresaUnidade($deUnidadeId);
        $empDestId = FiscalMovimentacaoSupport::resolverEmpresaUnidade($paraUnidadeId);
        if (! $empOrigId || ! $empDestId) {
            throw new \InvalidArgumentException('Ligue cada unidade ao CNPJ (Empresas) antes de emitir NF-e de transferência.');
        }
        if ($empOrigId === $empDestId) {
            throw new \InvalidArgumentException('NF-e de transferência só se aplica entre CNPJs diferentes.');
        }
        $origem = DB::table('empresas')->where('id', $empOrigId)->first();
        $destino = DB::table('empresas')->where('id', $empDestId)->first();
        if (! $origem || ! $destino) {
            throw new \InvalidArgumentException('Empresa de origem ou destino não cadastrada.');
        }
        $produto = DB::table('produtos')->where('id', $produtoId)->first();
        if (! $produto) {
            throw new \InvalidArgumentException('Produto não encontrado.');
        }
        $config = FiscalEmissaoConfig::query()->where('empresa_id', $empOrigId)->first();
        if (! $config || empty($config->api_token)) {
            throw new \InvalidArgumentException('Configure o token Focus da empresa de ORIGEM em Emissão NF-e / NFC-e.');
        }
        if (($config->provider ?? '') !== 'focus_nfe') {
            throw new \InvalidArgumentException('NF-e de transferência usa Focus NFe na empresa de origem.');
        }

        return compact('origem', 'destino', 'produto', 'config');
    }
}
