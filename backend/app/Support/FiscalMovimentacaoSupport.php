<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class FiscalMovimentacaoSupport
{
    public const TIPOS_MOVIMENTACAO = [
        'transferencia_interna',
        'operacao_entre_cnpjs',
        'producao',
        'consumo_interno',
        'perda',
        'avaria',
        'vencimento',
        'extravio',
        'furto',
    ];

    public const TIPOS_EVENTO = [
        'transferencia_interna',
        'operacao_entre_empresas',
        'consumo_producao',
        'producao',
        'venda',
        'consumo_interno',
        'perda',
        'avaria',
        'vencimento',
        'extravio',
        'furto',
    ];

    public const STATUS_EVENTO = ['pendente_analise', 'sem_impacto', 'impacto_potencial', 'validado', 'processado', 'cancelado'];

    public const STATUS_DOCUMENTAL = ['pendente', 'vinculado', 'validado', 'cancelado'];

    public const MOTIVOS_SAIDA = ['PRODUCAO', 'CONSUMO', 'PERDA', 'AVARIA', 'VENCIMENTO', 'EXTRAVIO', 'FURTO', 'TRANSFERENCIA'];

    public static function moduloAtivo(): bool
    {
        return Schema::hasTable('eventos_fiscais')
            && Schema::hasTable('movimentacoes')
            && Schema::hasColumn('movimentacoes', 'tipo_movimentacao');
    }

    public static function resolverEmpresaUnidade(?int $unidadeId): ?int
    {
        if (! $unidadeId || $unidadeId <= 0) {
            return null;
        }
        if (! Schema::hasTable('unidades') || ! Schema::hasColumn('unidades', 'empresa_id')) {
            return null;
        }
        $emp = DB::table('unidades')->where('id', $unidadeId)->value('empresa_id');

        return $emp ? (int) $emp : null;
    }

    public static function mapMotivoToTipoMovimentacao(string $motivo, ?int $empresaOrigem, ?int $empresaDestino): string
    {
        $m = strtoupper(trim($motivo));
        if ($m === 'TRANSFERENCIA') {
            if ($empresaOrigem && $empresaDestino && $empresaOrigem !== $empresaDestino) {
                return 'operacao_entre_cnpjs';
            }

            return 'transferencia_interna';
        }

        return match ($m) {
            'PRODUCAO' => 'producao',
            'CONSUMO' => 'consumo_interno',
            'AVARIA' => 'avaria',
            'VENCIMENTO' => 'vencimento',
            'EXTRAVIO' => 'extravio',
            'FURTO' => 'furto',
            'PERDA' => 'perda',
            default => 'consumo_interno',
        };
    }

    public static function mapTipoMovimentacaoToEvento(string $tipoMov): string
    {
        return match ($tipoMov) {
            'operacao_entre_cnpjs' => 'operacao_entre_empresas',
            'producao' => 'consumo_producao',
            default => $tipoMov,
        };
    }

    public static function statusEventoInicial(string $tipoMov): string
    {
        if (in_array($tipoMov, ['transferencia_interna', 'producao'], true)) {
            return 'sem_impacto';
        }
        if (in_array($tipoMov, ['perda', 'avaria', 'vencimento', 'extravio', 'furto', 'operacao_entre_cnpjs'], true)) {
            return 'impacto_potencial';
        }

        return 'pendente_analise';
    }

    public static function motivoExigeJustificativa(string $motivo): bool
    {
        return in_array(strtoupper(trim($motivo)), ['CONSUMO', 'PERDA', 'AVARIA', 'EXTRAVIO', 'FURTO'], true);
    }

    /** @param array<string, mixed> $payload */
    public static function validarPayloadSaida(array $payload, bool $isTransferencia, ?int $deUnidadeId, ?int $paraUnidadeId): ?string
    {
        if (! self::moduloAtivo()) {
            return null;
        }

        $motivo = strtoupper(trim((string) ($payload['motivo'] ?? '')));
        $detalhe = trim((string) ($payload['motivo_detalhe'] ?? ''));

        if (self::motivoExigeJustificativa($motivo) && strlen($detalhe) < 5) {
            return 'Informe uma justificativa (mínimo 5 caracteres) para este tipo de saída.';
        }

        if (! $isTransferencia) {
            return null;
        }

        $empOrig = self::resolverEmpresaUnidade($deUnidadeId);
        $empDest = self::resolverEmpresaUnidade($paraUnidadeId);
        if (! $empOrig || ! $empDest || $empOrig === $empDest) {
            return null;
        }

        $numDoc = trim((string) ($payload['numero_documento'] ?? ''));
        $chave = preg_replace('/\D/', '', (string) ($payload['chave_acesso_documento'] ?? ''));
        $querEmitir = filter_var($payload['emitir_nfe'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($querEmitir) {
            return null;
        }
        if ($numDoc === '' && strlen($chave) < 44 && strlen($detalhe) < 10) {
            return 'Operação entre CNPJs diferentes: informe número do documento, chave de acesso (44 dígitos) ou justificativa detalhada (mín. 10 caracteres).';
        }

        return null;
    }

    /** @param array<string, mixed> $payload */
    public static function buildCamposMovimentacao(
        array $payload,
        bool $isTransferencia,
        int $deUnidadeId,
        ?int $paraUnidadeId,
        float $custoUnitario,
        float $quantidade
    ): array {
        if (! self::moduloAtivo()) {
            return [];
        }

        $empOrig = self::resolverEmpresaUnidade($deUnidadeId);
        $empDest = $isTransferencia ? self::resolverEmpresaUnidade($paraUnidadeId) : null;
        $motivo = strtoupper(trim((string) ($payload['motivo'] ?? '')));
        $tipoMov = self::mapMotivoToTipoMovimentacao($motivo, $empOrig, $empDest);

        $numDoc = trim((string) ($payload['numero_documento'] ?? ''));
        $chave = preg_replace('/\D/', '', (string) ($payload['chave_acesso_documento'] ?? ''));
        $modelo = trim((string) ($payload['modelo_documento'] ?? ''));

        $statusDoc = null;
        if ($tipoMov === 'operacao_entre_cnpjs') {
            if ($numDoc !== '' || strlen($chave) === 44) {
                $statusDoc = 'vinculado';
            } else {
                $statusDoc = 'pendente';
            }
        }

        $custoTotal = round($custoUnitario * $quantidade, 4);

        return array_filter([
            'tipo_movimentacao' => $tipoMov,
            'empresa_origem_id' => $empOrig,
            'empresa_destino_id' => $empDest,
            'status_movimentacao' => 'processada',
            'status_documental' => $statusDoc,
            'numero_documento' => $numDoc !== '' ? $numDoc : null,
            'chave_acesso_documento' => strlen($chave) === 44 ? $chave : null,
            'modelo_documento' => $modelo !== '' ? $modelo : null,
            'motivo_detalhe' => trim((string) ($payload['motivo_detalhe'] ?? '')) ?: null,
            'setor_destino' => trim((string) ($payload['setor_destino'] ?? '')) ?: null,
            'numero_ocorrencia' => trim((string) ($payload['numero_ocorrencia'] ?? '')) ?: null,
            'custo_total' => $custoTotal,
            'empresa_id' => $empOrig,
        ], static fn ($v) => $v !== null);
    }

    public static function criarEventoFiscal(
        int $movimentacaoId,
        int $produtoId,
        ?int $loteId,
        ?int $unidadeId,
        string $tipoMovimentacao,
        float $valorBase,
        ?string $observacao = null
    ): ?int {
        if (! self::moduloAtivo()) {
            return null;
        }

        $empresaId = self::resolverEmpresaUnidade($unidadeId);
        $tipoEvento = self::mapTipoMovimentacaoToEvento($tipoMovimentacao);
        $status = self::statusEventoInicial($tipoMovimentacao);

        return (int) DB::table('eventos_fiscais')->insertGetId([
            'empresa_id' => $empresaId,
            'unidade_id' => $unidadeId,
            'movimentacao_id' => $movimentacaoId,
            'produto_id' => $produtoId,
            'lote_id' => $loteId,
            'tipo_evento' => $tipoEvento,
            'origem_evento' => 'movimentacao_estoque',
            'status' => $status,
            'data_evento' => now(),
            'valor_base' => round($valorBase, 4),
            'valor_estimado' => null,
            'observacao' => $observacao,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public static function posRegistrarSaida(int $movimentacaoId, object $movRow): void
    {
        if (! self::moduloAtivo()) {
            return;
        }

        $tipoMov = $movRow->tipo_movimentacao ?? null;
        if (! $tipoMov) {
            return;
        }

        if (DB::table('eventos_fiscais')->where('movimentacao_id', $movimentacaoId)->exists()) {
            return;
        }

        $valorBase = (float) ($movRow->custo_total ?? 0);
        if ($valorBase <= 0) {
            $valorBase = (float) ($movRow->custo_unitario ?? 0) * (float) ($movRow->qtd ?? 0);
        }

        self::criarEventoFiscal(
            $movimentacaoId,
            (int) $movRow->produto_id,
            isset($movRow->lote_id) ? (int) $movRow->lote_id : null,
            isset($movRow->de_unidade_id) ? (int) $movRow->de_unidade_id : null,
            (string) $tipoMov,
            $valorBase,
            $movRow->motivo_detalhe ?? $movRow->observacao ?? null
        );
    }

    public static function cancelarEventosPorMovimentacao(int $movimentacaoId): void
    {
        if (! Schema::hasTable('eventos_fiscais')) {
            return;
        }
        DB::table('eventos_fiscais')
            ->where('movimentacao_id', $movimentacaoId)
            ->whereNotIn('status', ['cancelado'])
            ->update([
                'status' => 'cancelado',
                'updated_at' => now(),
            ]);
    }

    /** @return array<string, string> */
    public static function labelsTipoMovimentacao(): array
    {
        return [
            'transferencia_interna' => 'Transferência interna',
            'operacao_entre_cnpjs' => 'Operação entre CNPJs',
            'producao' => 'Produção',
            'consumo_interno' => 'Consumo interno',
            'perda' => 'Perda',
            'avaria' => 'Avaria',
            'vencimento' => 'Vencimento',
            'extravio' => 'Extravio',
            'furto' => 'Furto',
        ];
    }
}
