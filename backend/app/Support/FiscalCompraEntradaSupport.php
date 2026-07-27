<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class FiscalCompraEntradaSupport
{
    public const STATUS_FISCAL_LISTA = ['pendente', 'com_alerta', 'validada', 'processada'];

    public const STATUS_NF = ['rascunho', 'importada', 'validada', 'processada', 'cancelada'];

    public const STATUS_CREDITO = ['nao_analisado', 'potencial', 'nao_aproveitavel', 'aproveitavel', 'aproveitado', 'estornado'];

    public const TIPOS_TRIBUTO = ['icms', 'icms_st', 'pis', 'cofins', 'ipi', 'cbs', 'ibs'];

    public static function resolverEmpresaIdLista(object $lista): ?int
    {
        $empresaId = isset($lista->empresa_id) ? (int) $lista->empresa_id : 0;
        if ($empresaId > 0) {
            return $empresaId;
        }
        $unidadeId = isset($lista->unidade_id) ? (int) $lista->unidade_id : 0;
        if ($unidadeId <= 0 || ! Schema::hasTable('unidades') || ! Schema::hasColumn('unidades', 'empresa_id')) {
            return null;
        }
        $fromUnidade = DB::table('unidades')->where('id', $unidadeId)->value('empresa_id');

        return $fromUnidade ? (int) $fromUnidade : null;
    }

    public static function unidadePertenceEmpresa(?int $unidadeId, ?int $empresaId): bool
    {
        if (! $unidadeId || $unidadeId <= 0) {
            return true;
        }
        if (! $empresaId || $empresaId <= 0) {
            return true;
        }
        if (! Schema::hasTable('unidades') || ! Schema::hasColumn('unidades', 'empresa_id')) {
            return true;
        }
        $uEmp = DB::table('unidades')->where('id', $unidadeId)->value('empresa_id');
        if ($uEmp === null || (int) $uEmp === 0) {
            return true;
        }

        return (int) $uEmp === (int) $empresaId;
    }

    public static function validarEmpresaUnidade(?int $empresaId, ?int $unidadeId): ?string
    {
        if ($empresaId && $unidadeId && ! self::unidadePertenceEmpresa($unidadeId, $empresaId)) {
            return 'Unidade incompatível com a empresa compradora.';
        }

        return null;
    }

    public static function chaveNfDuplicada(int $empresaId, string $chave, ?int $ignoreNotaId = null): bool
    {
        if ($empresaId <= 0 || trim($chave) === '' || ! Schema::hasTable('notas_fiscais_entrada')) {
            return false;
        }
        $q = DB::table('notas_fiscais_entrada')
            ->where('empresa_id', $empresaId)
            ->where('chave_acesso', $chave)
            ->whereNotIn('status', ['cancelada']);
        if ($ignoreNotaId) {
            $q->where('id', '!=', $ignoreNotaId);
        }

        return $q->exists();
    }

    /** @return array<string, mixed> */
    public static function snapshotCadastroProduto(?object $produto): array
    {
        if (! $produto) {
            return [];
        }

        return [
            'tipo_fiscal' => $produto->tipo_fiscal ?? null,
            'perfil_tributario_id' => $produto->perfil_tributario_id ?? null,
            'ncm' => $produto->ncm ?? null,
            'cest' => $produto->cest ?? null,
            'cst_icms' => $produto->cst_icms ?? null,
            'csosn' => $produto->csosn ?? null,
            'cfop_entrada_padrao' => $produto->cfop_entrada_padrao ?? null,
            'origem_mercadoria' => $produto->origem_mercadoria ?? null,
        ];
    }

    /** @return list<array{campo: string, cadastro: mixed, nota: mixed, mensagem: string}> */
    public static function divergenciasItem(object $produto, array $itemNota): array
    {
        $checks = [
            ['campo' => 'ncm', 'cad' => FiscalCadastroSupport::normalizarNcm($produto->ncm ?? null), 'nota' => FiscalCadastroSupport::normalizarNcm($itemNota['ncm'] ?? null)],
            ['campo' => 'cest', 'cad' => FiscalCadastroSupport::normalizarCest($produto->cest ?? null), 'nota' => FiscalCadastroSupport::normalizarCest($itemNota['cest'] ?? null)],
            ['campo' => 'cst_icms', 'cad' => FiscalCadastroSupport::normalizarCst($produto->cst_icms ?? null), 'nota' => FiscalCadastroSupport::normalizarCst($itemNota['cst_icms'] ?? null)],
            ['campo' => 'csosn', 'cad' => FiscalCadastroSupport::normalizarCsosn($produto->csosn ?? null), 'nota' => FiscalCadastroSupport::normalizarCsosn($itemNota['csosn'] ?? null)],
            ['campo' => 'cfop', 'cad' => FiscalCadastroSupport::normalizarCfop($produto->cfop_entrada_padrao ?? null), 'nota' => FiscalCadastroSupport::normalizarCfop($itemNota['cfop'] ?? null)],
            ['campo' => 'origem_mercadoria', 'cad' => trim((string) ($produto->origem_mercadoria ?? '')), 'nota' => trim((string) ($itemNota['origem_mercadoria'] ?? ''))],
        ];
        $out = [];
        foreach ($checks as $c) {
            if ($c['cad'] === null || $c['cad'] === '' || $c['nota'] === null || $c['nota'] === '') {
                continue;
            }
            if ((string) $c['cad'] !== (string) $c['nota']) {
                $out[] = [
                    'campo' => $c['campo'],
                    'cadastro' => $c['cad'],
                    'nota' => $c['nota'],
                    'mensagem' => 'Divergência fiscal detectada',
                ];
            }
        }

        return $out;
    }

    public static function recalcularStatusFiscalLista(int $listaId): void
    {
        if (! Schema::hasTable('listas_compras') || ! Schema::hasColumn('listas_compras', 'status_fiscal')) {
            return;
        }
        $lista = DB::table('listas_compras')->where('id', $listaId)->first();
        if (! $lista) {
            return;
        }
        if ($lista->estoque_lancado_em ?? null) {
            DB::table('listas_compras')->where('id', $listaId)->update(['status_fiscal' => 'processada']);

            return;
        }
        $temAlerta = false;
        if (Schema::hasTable('itens_notas_fiscais_entrada') && Schema::hasTable('notas_fiscais_entrada')) {
            $notaId = DB::table('notas_fiscais_entrada')->where('lista_compra_id', $listaId)->value('id');
            if ($notaId) {
                $itens = DB::table('itens_notas_fiscais_entrada')->where('nota_fiscal_entrada_id', $notaId)->get();
                foreach ($itens as $it) {
                    $alertas = json_decode($it->alertas_fiscais ?? '[]', true);
                    if (is_array($alertas) && count($alertas) > 0) {
                        $temAlerta = true;
                        break;
                    }
                }
            }
        }
        $status = $temAlerta ? 'com_alerta' : 'pendente';
        $nota = Schema::hasTable('notas_fiscais_entrada')
            ? DB::table('notas_fiscais_entrada')->where('lista_compra_id', $listaId)->first()
            : null;
        if ($nota && in_array($nota->status ?? '', ['validada', 'importada'], true)) {
            $status = $temAlerta ? 'com_alerta' : 'validada';
        }
        DB::table('listas_compras')->where('id', $listaId)->update(['status_fiscal' => $status]);
    }

    /** @param array<int, array{produto_id: int, lote_id: ?int, movimentacao_id: int}> $entradas */
    public static function posLancamentoEstoque(int $listaId, object $lista, ?int $empresaId, array $entradas): void
    {
        if (! Schema::hasTable('notas_fiscais_entrada')) {
            return;
        }
        $nota = DB::table('notas_fiscais_entrada')->where('lista_compra_id', $listaId)->first();
        if (! $nota) {
            self::recalcularStatusFiscalLista($listaId);

            return;
        }
        $mapProdutoEntrada = [];
        foreach ($entradas as $e) {
            $mapProdutoEntrada[(int) $e['produto_id']] = $e;
        }
        $itensNf = DB::table('itens_notas_fiscais_entrada')->where('nota_fiscal_entrada_id', $nota->id)->get();
        foreach ($itensNf as $itemNf) {
            $pid = (int) ($itemNf->produto_id ?? 0);
            if ($pid <= 0 || ! isset($mapProdutoEntrada[$pid])) {
                continue;
            }
            $entrada = $mapProdutoEntrada[$pid];
            $loteId = $entrada['lote_id'] ?? null;
            $upd = ['lote_id' => $loteId];
            if (Schema::hasColumn('itens_notas_fiscais_entrada', 'lista_item_id') && empty($itemNf->lista_item_id)) {
                $upd['lista_item_id'] = DB::table('listas_itens')
                    ->where('lista_id', $listaId)
                    ->where('produto_id', $pid)
                    ->value('id');
            }
            DB::table('itens_notas_fiscais_entrada')->where('id', $itemNf->id)->update($upd);
            self::gerarCreditosPotenciaisItem((int) $nota->id, (int) $itemNf->id, $empresaId, $pid, $loteId, (array) json_decode(json_encode($itemNf), true));
        }
        DB::table('notas_fiscais_entrada')->where('id', $nota->id)->update([
            'status' => 'processada',
            'data_entrada' => $nota->data_entrada ?? now(),
        ]);
        self::recalcularStatusFiscalLista($listaId);
    }

    /** @param array<string, mixed> $itemRow */
    public static function gerarCreditosPotenciaisItem(int $notaId, int $itemNotaId, ?int $empresaId, int $produtoId, ?int $loteId, array $itemRow): void
    {
        if (! Schema::hasTable('creditos_fiscais_entrada')) {
            return;
        }
        DB::table('creditos_fiscais_entrada')
            ->where('item_nota_fiscal_entrada_id', $itemNotaId)
            ->delete();
        $map = [
            'icms' => (float) ($itemRow['valor_icms'] ?? 0),
            'icms_st' => (float) ($itemRow['valor_icms_st'] ?? 0),
            'pis' => (float) ($itemRow['valor_pis'] ?? 0),
            'cofins' => (float) ($itemRow['valor_cofins'] ?? 0),
            'ipi' => (float) ($itemRow['valor_ipi'] ?? 0),
            'cbs' => (float) ($itemRow['valor_cbs'] ?? 0),
            'ibs' => (float) ($itemRow['valor_ibs'] ?? 0),
        ];
        foreach ($map as $tipo => $valor) {
            if ($valor <= 0) {
                continue;
            }
            DB::table('creditos_fiscais_entrada')->insert([
                'empresa_id' => $empresaId,
                'nota_fiscal_entrada_id' => $notaId,
                'item_nota_fiscal_entrada_id' => $itemNotaId,
                'produto_id' => $produtoId,
                'lote_id' => $loteId,
                'tipo_tributo' => $tipo,
                'valor_destacado' => $valor,
                'valor_potencial' => $valor,
                'status' => 'potencial',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /** @return array<string, float> */
    public static function totaisTributosNota(int $notaId): array
    {
        $tot = array_fill_keys(self::TIPOS_TRIBUTO, 0.0);
        if (! Schema::hasTable('itens_notas_fiscais_entrada')) {
            return $tot;
        }
        $rows = DB::table('itens_notas_fiscais_entrada')->where('nota_fiscal_entrada_id', $notaId)->get();
        foreach ($rows as $r) {
            $tot['icms'] += (float) ($r->valor_icms ?? 0);
            $tot['icms_st'] += (float) ($r->valor_icms_st ?? 0);
            $tot['pis'] += (float) ($r->valor_pis ?? 0);
            $tot['cofins'] += (float) ($r->valor_cofins ?? 0);
            $tot['ipi'] += (float) ($r->valor_ipi ?? 0);
            $tot['cbs'] += (float) ($r->valor_cbs ?? 0);
            $tot['ibs'] += (float) ($r->valor_ibs ?? 0);
        }

        return $tot;
    }

    /** @return array<string, float> */
    public static function totaisCreditosPotenciaisNota(int $notaId): array
    {
        $tot = array_fill_keys(self::TIPOS_TRIBUTO, 0.0);
        if (! Schema::hasTable('creditos_fiscais_entrada')) {
            return $tot;
        }
        $rows = DB::table('creditos_fiscais_entrada')
            ->where('nota_fiscal_entrada_id', $notaId)
            ->whereIn('status', ['nao_analisado', 'potencial'])
            ->get();
        foreach ($rows as $r) {
            $t = $r->tipo_tributo ?? '';
            if (isset($tot[$t])) {
                $tot[$t] += (float) ($r->valor_potencial ?? 0);
            }
        }

        return $tot;
    }
}
