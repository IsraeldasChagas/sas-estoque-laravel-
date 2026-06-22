<?php

namespace App\Services;

use App\Models\Boleto;
use App\Models\Imposto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quando um boleto de imposto é pago, espelha a saída no fluxo de caixa (financeiro_lancamentos).
 * Usa origem_tipo/origem_id para evitar duplicar lançamentos.
 */
class BoletoFluxoCaixaService
{
    public const ORIGEM_TIPO = 'boleto';

    public static function sincronizarDeBoleto(Boleto $boleto): void
    {
        if (! Schema::hasTable('financeiro_lancamentos')) {
            return;
        }

        $boletoId = (int) $boleto->id;

        if (! self::deveSincronizar($boleto)) {
            self::cancelarLancamento($boletoId);

            return;
        }

        if ($boleto->status === 'PAGO') {
            self::upsertLancamentoPago($boleto);
        } else {
            self::cancelarLancamento($boletoId);
        }
    }

    public static function aoExcluirBoleto(int $boletoId): void
    {
        self::cancelarLancamento($boletoId);
    }

    private static function deveSincronizar(Boleto $boleto): bool
    {
        if ($boleto->imposto_id) {
            return true;
        }

        return strtoupper(trim((string) ($boleto->categoria ?? ''))) === 'IMPOSTOS';
    }

    private static function upsertLancamentoPago(Boleto $boleto): void
    {
        $existente = DB::table('financeiro_lancamentos')
            ->where('origem_tipo', self::ORIGEM_TIPO)
            ->where('origem_id', $boleto->id)
            ->whereNull('deleted_at')
            ->first();

        $valorPago = (float) ($boleto->valor_pago ?? 0);
        $juros = (float) ($boleto->juros_multa ?? 0);
        $valor = $valorPago > 0
            ? round($valorPago + $juros, 2)
            : round((float) $boleto->valor + $juros, 2);

        $dataPagamento = $boleto->data_pagamento
            ? $boleto->data_pagamento->format('Y-m-d')
            : now()->format('Y-m-d');

        $dataCompetencia = $dataPagamento;
        if ($boleto->imposto_id) {
            $imposto = Imposto::find($boleto->imposto_id);
            if ($imposto?->competencia && preg_match('/^\d{4}-\d{2}/', $imposto->competencia)) {
                $dataCompetencia = substr($imposto->competencia, 0, 7).'-01';
            }
        }

        [$descricao, $observacao] = self::montarTextos($boleto);

        $payload = [
            'unidade_id' => $boleto->unidade_id,
            'categoria_id' => self::categoriaImpostosId(),
            'centro_custo_id' => null,
            'tipo' => 'saida',
            'valor' => $valor,
            'descricao' => $descricao,
            'forma_pagamento' => null,
            'data_competencia' => $dataCompetencia,
            'data_pagamento' => $dataPagamento,
            'status' => 'realizado',
            'observacao' => $observacao,
            'origem_tipo' => self::ORIGEM_TIPO,
            'origem_id' => $boleto->id,
            'updated_at' => now(),
        ];

        if ($existente) {
            DB::table('financeiro_lancamentos')->where('id', $existente->id)->update($payload);

            return;
        }

        $payload['criado_por'] = $boleto->usuario_id;
        $payload['created_at'] = now();
        DB::table('financeiro_lancamentos')->insert($payload);
    }

    /** @return array{0: string, 1: string} */
    private static function montarTextos(Boleto $boleto): array
    {
        $descricao = trim((string) ($boleto->descricao ?: $boleto->fornecedor ?: 'Pagamento de imposto'));
        $obs = 'Lançamento automático do boleto #'.$boleto->id;

        if ($boleto->imposto_id) {
            $imposto = Imposto::find($boleto->imposto_id);
            if ($imposto) {
                $tipo = trim((string) $imposto->tipo_imposto);
                if ($tipo !== '') {
                    $descricao = $tipo.' — '.$descricao;
                }
                $obs .= ' (imposto #'.$imposto->id;
                if ($imposto->competencia) {
                    $obs .= ', competência '.$imposto->competencia;
                }
                $obs .= ')';
            }
        }

        if ($boleto->numero_boleto) {
            $obs .= '. Guia/doc: '.$boleto->numero_boleto;
        }

        return [mb_substr($descricao, 0, 500), $obs];
    }

    private static function categoriaImpostosId(): ?int
    {
        if (! Schema::hasTable('financeiro_categorias')) {
            return null;
        }

        $id = DB::table('financeiro_categorias')
            ->where('ativo', true)
            ->where('tipo', 'saida')
            ->whereRaw('LOWER(nome) = ?', ['impostos'])
            ->value('id');

        if ($id) {
            return (int) $id;
        }

        $id = DB::table('financeiro_categorias')
            ->where('ativo', true)
            ->where('tipo', 'saida')
            ->where('nome', 'like', '%Imposto%')
            ->orderBy('ordem')
            ->value('id');

        return $id ? (int) $id : null;
    }

    private static function cancelarLancamento(int $boletoId): void
    {
        if (! Schema::hasTable('financeiro_lancamentos')) {
            return;
        }

        DB::table('financeiro_lancamentos')
            ->where('origem_tipo', self::ORIGEM_TIPO)
            ->where('origem_id', $boletoId)
            ->whereNull('deleted_at')
            ->update([
                'status' => 'cancelado',
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
