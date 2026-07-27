<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class FiscalConsolidacaoSupport
{
    /** @param array{empresa_id?: int, data_ini?: string, data_fim?: string} $f */
    public static function visaoGeral(array $f): array
    {
        $entradas = self::totalEntradas($f);
        $saidas = self::totalSaidas($f);
        $creditos = self::totalCreditos($f);
        $estornos = self::totalEstornosPotenciais($f);
        $tributosVenda = self::totalTributosVenda($f);
        $estoque = self::valorEstoque($f);
        $pendencias = self::pendencias($f);

        return [
            'cards' => [
                'entradas' => $entradas,
                'saidas' => $saidas,
                'receita' => $saidas,
                'creditos_potenciais' => $creditos['potencial'],
                'creditos_validados' => $creditos['validados'],
                'estornos_potenciais' => $estornos,
                'tributos_estimados_recolher' => max(0, $tributosVenda - $creditos['potencial']),
                'valor_estoque' => $estoque,
                'tributacao_potencial_estoque' => self::tributacaoPotencialEstoque($f),
                'pendencias_count' => count($pendencias),
            ],
            'pendencias' => array_slice($pendencias, 0, 20),
            'disclaimer' => 'Estimativas gerenciais — não substituem apuração oficial.',
        ];
    }

    /** @param array<string, mixed> $f */
    public static function listarEntradas(array $f, int $limit = 100): array
    {
        if (! Schema::hasTable('notas_fiscais_entrada')) {
            return [];
        }
        $q = DB::table('notas_fiscais_entrada')
            ->leftJoin('listas_compras', 'notas_fiscais_entrada.lista_compra_id', '=', 'listas_compras.id')
            ->select('notas_fiscais_entrada.*', 'listas_compras.unidade_id');
        self::applyEmpresaPeriodo($q, $f, 'notas_fiscais_entrada.empresa_id', 'notas_fiscais_entrada.data_entrada');

        return $q->orderByDesc('notas_fiscais_entrada.id')->limit($limit)->get()->all();
    }

    /** @param array<string, mixed> $f */
    public static function listarSaidas(array $f, int $limit = 100): array
    {
        if (! Schema::hasTable('vendas')) {
            return [];
        }
        $q = DB::table('vendas')->select('vendas.*');
        self::applyEmpresaPeriodo($q, $f, 'vendas.empresa_id', 'vendas.data_venda');

        return $q->orderByDesc('vendas.id')->limit($limit)->get()->all();
    }

    /** @param array<string, mixed> $f */
    public static function listarCreditos(array $f, int $limit = 200): array
    {
        if (! Schema::hasTable('creditos_fiscais_entrada')) {
            return [];
        }
        $q = DB::table('creditos_fiscais_entrada')->select('creditos_fiscais_entrada.*');
        if (! empty($f['empresa_id'])) {
            $q->where('empresa_id', (int) $f['empresa_id']);
        }

        return $q->orderByDesc('id')->limit($limit)->get()->all();
    }

    /** @param array<string, mixed> $f */
    public static function listarEstornos(array $f, int $limit = 100): array
    {
        if (Schema::hasTable('estornos_fiscais')) {
            $q = DB::table('estornos_fiscais');
            if (! empty($f['empresa_id'])) {
                $q->where('empresa_id', (int) $f['empresa_id']);
            }

            return $q->orderByDesc('id')->limit($limit)->get()->all();
        }

        return self::gerarEstornosFromEventos($f, $limit);
    }

    /** @param array<string, mixed> $f */
    public static function porCnpj(array $f): array
    {
        if (! Schema::hasTable('empresas')) {
            return [];
        }
        $empresas = DB::table('empresas')->where('ativo', 1)->get();
        $out = [];
        foreach ($empresas as $e) {
            $ctx = array_merge($f, ['empresa_id' => (int) $e->id]);
            $vg = self::visaoGeral($ctx);
            $out[] = [
                'empresa_id' => (int) $e->id,
                'razao_social' => $e->razao_social,
                'cnpj' => $e->cnpj,
                'regime_tributario' => $e->regime_tributario,
                'resumo' => $vg['cards'],
            ];
        }

        return $out;
    }

    /** @param array<string, mixed> $f */
    public static function estoquePotencial(array $f, int $limit = 150): array
    {
        if (! Schema::hasTable('stock_lotes')) {
            return [];
        }
        $q = DB::table('stock_lotes')
            ->join('produtos', 'stock_lotes.produto_id', '=', 'produtos.id')
            ->join('unidades', 'stock_lotes.unidade_id', '=', 'unidades.id')
            ->where('stock_lotes.quantidade', '>', 0)
            ->select(
                'stock_lotes.*',
                'produtos.nome as produto_nome',
                'produtos.tipo_fiscal',
                'produtos.perfil_tributario_id',
                'unidades.nome as unidade_nome',
                'unidades.empresa_id'
            );
        if (! empty($f['empresa_id']) && Schema::hasColumn('unidades', 'empresa_id')) {
            $q->where('unidades.empresa_id', (int) $f['empresa_id']);
        }
        $rows = $q->limit($limit)->get();
        $regime = null;
        if (! empty($f['empresa_id'])) {
            $regime = DB::table('empresas')->where('id', (int) $f['empresa_id'])->value('regime_tributario');
        }

        return $rows->map(function ($r) use ($regime) {
            $valor = (float) $r->quantidade * (float) ($r->custo_unitario ?? 0);
            $regra = RegraFiscalSupport::regraAplicavel('icms', $regime, 'venda');
            $tribPot = $regra ? RegraFiscalSupport::calcularEstimativa($regra, $valor) : 0;

            return [
                'produto_id' => $r->produto_id,
                'produto_nome' => $r->produto_nome,
                'unidade_nome' => $r->unidade_nome,
                'empresa_id' => $r->empresa_id,
                'codigo_lote' => $r->codigo_lote,
                'quantidade' => (float) $r->quantidade,
                'custo_unitario' => (float) ($r->custo_unitario ?? 0),
                'valor_estoque' => round($valor, 2),
                'tributacao_potencial_estimada' => $tribPot,
                'tipo_fiscal' => $r->tipo_fiscal,
            ];
        })->all();
    }

    /** @param array<string, mixed> $f */
    public static function pendencias(array $f): array
    {
        $p = [];
        if (Schema::hasTable('produtos')) {
            $semNcm = DB::table('produtos')->where('ativo', 1)->where(function ($q) {
                $q->whereNull('ncm')->orWhere('ncm', '');
            })->limit(5)->pluck('nome');
            foreach ($semNcm as $nome) {
                $p[] = ['tipo' => 'produto_sem_ncm', 'mensagem' => "Produto sem NCM: {$nome}"];
            }
        }
        if (Schema::hasTable('creditos_fiscais_entrada')) {
            $na = DB::table('creditos_fiscais_entrada')->where('status', 'nao_analisado')->count();
            if ($na > 0) {
                $p[] = ['tipo' => 'credito_nao_analisado', 'mensagem' => "{$na} crédito(s) não analisado(s)"];
            }
        }
        if (Schema::hasTable('eventos_fiscais')) {
            $ev = DB::table('eventos_fiscais')->where('status', 'pendente_analise')->count();
            if ($ev > 0) {
                $p[] = ['tipo' => 'evento_pendente', 'mensagem' => "{$ev} evento(s) fiscal(is) pendente(s)"];
            }
        }
        if (Schema::hasTable('vendas')) {
            $vd = DB::table('vendas')->where('status_documento', 'pendente')->count();
            if ($vd > 0) {
                $p[] = ['tipo' => 'documento_pendente', 'mensagem' => "{$vd} venda(s) com documento pendente"];
            }
        }

        return $p;
    }

    /** @param array<string, mixed> $f */
    protected static function totalEntradas(array $f): float
    {
        if (! Schema::hasTable('notas_fiscais_entrada')) {
            return 0.0;
        }
        $q = DB::table('notas_fiscais_entrada');
        self::applyEmpresaPeriodo($q, $f, 'empresa_id', 'data_entrada');

        return (float) $q->sum('valor_total');
    }

    /** @param array<string, mixed> $f */
    protected static function totalSaidas(array $f): float
    {
        if (! Schema::hasTable('vendas')) {
            return 0.0;
        }
        $q = DB::table('vendas')->where('status', 'finalizada');
        self::applyEmpresaPeriodo($q, $f, 'empresa_id', 'data_venda');

        return (float) $q->sum('valor_liquido');
    }

    /** @return array{potencial: float, validados: float} */
    protected static function totalCreditos(array $f): array
    {
        if (! Schema::hasTable('creditos_fiscais_entrada')) {
            return ['potencial' => 0.0, 'validados' => 0.0];
        }
        $q = DB::table('creditos_fiscais_entrada');
        if (! empty($f['empresa_id'])) {
            $q->where('empresa_id', (int) $f['empresa_id']);
        }
        $pot = (float) (clone $q)->sum('valor_potencial');
        $val = (float) (clone $q)->whereIn('status', ['aproveitavel', 'aproveitado', 'validado'])->sum('valor_potencial');

        return ['potencial' => $pot, 'validados' => $val];
    }

    /** @param array<string, mixed> $f */
    protected static function totalEstornosPotenciais(array $f): float
    {
        if (Schema::hasTable('estornos_fiscais')) {
            $q = DB::table('estornos_fiscais')->whereIn('status', ['potencial', 'nao_analisado']);
            if (! empty($f['empresa_id'])) {
                $q->where('empresa_id', (int) $f['empresa_id']);
            }

            return (float) $q->sum('valor_potencial');
        }

        return 0.0;
    }

    /** @param array<string, mixed> $f */
    protected static function totalTributosVenda(array $f): float
    {
        if (! Schema::hasTable('tributos_venda')) {
            return 0.0;
        }
        $q = DB::table('tributos_venda');
        if (! empty($f['empresa_id'])) {
            $q->where('empresa_id', (int) $f['empresa_id']);
        }

        return (float) $q->sum('valor');
    }

    /** @param array<string, mixed> $f */
    protected static function valorEstoque(array $f): float
    {
        if (! Schema::hasTable('stock_lotes')) {
            return 0.0;
        }
        $q = DB::table('stock_lotes')->where('quantidade', '>', 0);
        if (! empty($f['empresa_id']) && Schema::hasTable('unidades') && Schema::hasColumn('unidades', 'empresa_id')) {
            $q->whereIn('unidade_id', DB::table('unidades')->where('empresa_id', (int) $f['empresa_id'])->pluck('id'));
        }
        $rows = $q->get(['quantidade', 'custo_unitario']);

        return $rows->sum(fn ($r) => (float) $r->quantidade * (float) ($r->custo_unitario ?? 0));
    }

    /** @param array<string, mixed> $f */
    protected static function tributacaoPotencialEstoque(array $f): float
    {
        $itens = self::estoquePotencial($f, 500);

        return array_sum(array_column($itens, 'tributacao_potencial_estimada'));
    }

    /** @param array<string, mixed> $f */
    protected static function gerarEstornosFromEventos(array $f, int $limit): array
    {
        if (! Schema::hasTable('eventos_fiscais')) {
            return [];
        }
        $tipos = ['perda', 'avaria', 'vencimento', 'extravio', 'furto'];
        $q = DB::table('eventos_fiscais')->whereIn('tipo_evento', $tipos);
        if (! empty($f['empresa_id'])) {
            $q->where('empresa_id', (int) $f['empresa_id']);
        }

        return $q->orderByDesc('id')->limit($limit)->get()->map(fn ($e) => [
            'id' => 'ev-' . $e->id,
            'empresa_id' => $e->empresa_id,
            'tipo_evento' => $e->tipo_evento,
            'status' => 'potencial',
            'valor_potencial' => $e->valor_base,
            'observacao' => $e->observacao,
        ])->all();
    }

    protected static function applyEmpresaPeriodo($q, array $f, string $colEmpresa, string $colData): void
    {
        if (! empty($f['empresa_id'])) {
            $q->where($colEmpresa, (int) $f['empresa_id']);
        }
        if (! empty($f['data_ini'])) {
            $q->where($colData, '>=', $f['data_ini']);
        }
        if (! empty($f['data_fim'])) {
            $q->where($colData, '<=', $f['data_fim'] . ' 23:59:59');
        }
    }
}
