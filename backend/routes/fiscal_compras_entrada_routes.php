<?php

/**
 * Módulo 2 — Compras / Entrada fiscal (camada sobre listas_compras).
 */

use App\Support\FiscalCadastroSupport;
use App\Support\FiscalCompraEntradaSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$fiscalCompraCors = fn () => response()->json([])
    ->header('Access-Control-Allow-Origin', '*')
    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, OPTIONS')
    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');

$fiscalCompraAuth = function (Request $req) {
    $uid = $req->header('X-Usuario-Id');

    return $uid ? DB::table('usuarios')->where('id', $uid)->where('ativo', 1)->first() : null;
};

$fiscalCompraPodeVer = function ($u) {
    if (! $u) {
        return false;
    }
    $p = strtoupper(trim((string) ($u->perfil ?? '')));

    return in_array($p, ['ADMIN', 'ADMINISTRADOR', 'GERENTE', 'ESTOQUISTA'], true);
};

$fiscalCompraPodeEditar = function ($u) {
    if (! $u) {
        return false;
    }
    $p = strtoupper(trim((string) ($u->perfil ?? '')));

    return in_array($p, ['ADMIN', 'ADMINISTRADOR', 'GERENTE'], true);
};

$fiscalCompraJson = fn ($data, int $code = 200) => response()->json($data, $code)
    ->header('Access-Control-Allow-Origin', '*');

$mapNota = function ($nota) {
    if (! $nota) {
        return null;
    }
    $id = (int) $nota->id;
    $tributos = FiscalCompraEntradaSupport::totaisTributosNota($id);
    $creditos = FiscalCompraEntradaSupport::totaisCreditosPotenciaisNota($id);

    return [
        'id' => $id,
        'empresa_id' => $nota->empresa_id ? (int) $nota->empresa_id : null,
        'unidade_id' => $nota->unidade_id ? (int) $nota->unidade_id : null,
        'fornecedor_id' => $nota->fornecedor_id ? (int) $nota->fornecedor_id : null,
        'lista_compra_id' => $nota->lista_compra_id ? (int) $nota->lista_compra_id : null,
        'modelo_documento' => $nota->modelo_documento,
        'serie' => $nota->serie,
        'numero' => $nota->numero,
        'chave_acesso' => $nota->chave_acesso,
        'data_emissao' => $nota->data_emissao,
        'data_entrada' => $nota->data_entrada,
        'valor_produtos' => $nota->valor_produtos,
        'valor_frete' => $nota->valor_frete,
        'valor_seguro' => $nota->valor_seguro,
        'valor_desconto' => $nota->valor_desconto,
        'valor_outras_despesas' => $nota->valor_outras_despesas,
        'valor_total' => $nota->valor_total,
        'status' => $nota->status,
        'observacoes' => $nota->observacoes,
        'tributos_destacados' => $tributos,
        'creditos_potenciais' => $creditos,
    ];
};

$bundleListaFiscal = function (int $listaId) use ($mapNota) {
    $lista = DB::table('listas_compras')->where('id', $listaId)->first();
    if (! $lista) {
        return null;
    }
    $empresaId = FiscalCompraEntradaSupport::resolverEmpresaIdLista($lista);
    $empresa = $empresaId && Schema::hasTable('empresas')
        ? DB::table('empresas')->where('id', $empresaId)->first()
        : null;
    $nota = Schema::hasTable('notas_fiscais_entrada')
        ? DB::table('notas_fiscais_entrada')->where('lista_compra_id', $listaId)->first()
        : null;
    $itens = [];
    if ($nota) {
        $itensQuery = DB::table('itens_notas_fiscais_entrada as i')
            ->where('i.nota_fiscal_entrada_id', $nota->id);
        if (Schema::hasTable('produtos')) {
            $itensQuery->leftJoin('produtos as p', 'p.id', '=', 'i.produto_id')
                ->select('i.*', 'p.nome as produto_nome');
        } else {
            $itensQuery->select('i.*');
        }
        $itens = $itensQuery->orderBy('i.id')
            ->get()
            ->map(function ($row) {
                $alertas = json_decode($row->alertas_fiscais ?? '[]', true);

                return [
                    'id' => (int) $row->id,
                    'produto_id' => $row->produto_id ? (int) $row->produto_id : null,
                    'produto_nome' => $row->produto_nome,
                    'lista_item_id' => $row->lista_item_id ? (int) $row->lista_item_id : null,
                    'ncm' => $row->ncm,
                    'cest' => $row->cest,
                    'cfop' => $row->cfop,
                    'cst_icms' => $row->cst_icms,
                    'csosn' => $row->csosn,
                    'origem_mercadoria' => $row->origem_mercadoria,
                    'quantidade' => $row->quantidade,
                    'unidade_medida' => $row->unidade_medida,
                    'valor_unitario' => $row->valor_unitario,
                    'valor_produto' => $row->valor_produto,
                    'valor_total_item' => $row->valor_total_item,
                    'valor_icms' => $row->valor_icms,
                    'valor_pis' => $row->valor_pis,
                    'valor_cofins' => $row->valor_cofins,
                    'valor_ipi' => $row->valor_ipi,
                    'valor_icms_st' => $row->valor_icms_st,
                    'cadastro_fiscal_snapshot' => json_decode($row->cadastro_fiscal_snapshot ?? '{}', true),
                    'alertas_fiscais' => is_array($alertas) ? $alertas : [],
                ];
            })
            ->values()
            ->all();
    }

    return [
        'lista_id' => $listaId,
        'empresa_id' => $lista->empresa_id ? (int) $lista->empresa_id : null,
        'empresa_resolvida_id' => $empresaId,
        'status_fiscal' => $lista->status_fiscal ?? 'pendente',
        'empresa' => $empresa ? [
            'id' => (int) $empresa->id,
            'razao_social' => $empresa->razao_social ?? null,
            'cnpj' => $empresa->cnpj ?? null,
            'regime_tributario' => $empresa->regime_tributario ?? null,
            'inscricao_estadual' => $empresa->inscricao_estadual ?? null,
        ] : null,
        'nota' => $mapNota($nota),
        'itens_nota' => $itens,
    ];
};

foreach ([
    '/fiscal/compras/listas/{listaId}',
    '/fiscal/compras/listas/{listaId}/nota',
    '/fiscal/compras/relatorio-entradas',
    '/fiscal/compras/creditos-potenciais',
] as $path) {
    Route::options($path, $fiscalCompraCors);
}

Route::get('/fiscal/compras/listas/{listaId}', function (Request $req, $listaId) use ($fiscalCompraAuth, $fiscalCompraPodeVer, $fiscalCompraJson, $bundleListaFiscal) {
    $u = $fiscalCompraAuth($req);
    if (! $fiscalCompraPodeVer($u)) {
        return $fiscalCompraJson(['error' => 'Não autorizado'], 401);
    }
    $bundle = $bundleListaFiscal((int) $listaId);
    if (! $bundle) {
        return $fiscalCompraJson(['error' => 'Lista não encontrada'], 404);
    }

    return $fiscalCompraJson($bundle);
});

Route::put('/fiscal/compras/listas/{listaId}', function (Request $req, $listaId) use ($fiscalCompraAuth, $fiscalCompraPodeEditar, $fiscalCompraJson, $bundleListaFiscal) {
    $u = $fiscalCompraAuth($req);
    if (! $fiscalCompraPodeEditar($u)) {
        return $fiscalCompraJson(['error' => 'Sem permissão'], 403);
    }
    $lista = DB::table('listas_compras')->where('id', (int) $listaId)->first();
    if (! $lista) {
        return $fiscalCompraJson(['error' => 'Lista não encontrada'], 404);
    }
    $data = $req->validate([
        'empresa_id' => 'nullable|integer',
    ]);
    $empresaId = isset($data['empresa_id']) ? (int) $data['empresa_id'] : null;
    if ($empresaId && Schema::hasTable('empresas') && ! DB::table('empresas')->where('id', $empresaId)->exists()) {
        return $fiscalCompraJson(['error' => 'Empresa não encontrada'], 400);
    }
    $err = FiscalCompraEntradaSupport::validarEmpresaUnidade($empresaId, (int) ($lista->unidade_id ?? 0));
    if ($err) {
        return $fiscalCompraJson(['error' => $err], 400);
    }
    if (Schema::hasColumn('listas_compras', 'empresa_id')) {
        DB::table('listas_compras')->where('id', (int) $listaId)->update([
            'empresa_id' => $empresaId ?: null,
        ]);
    }
    FiscalCompraEntradaSupport::recalcularStatusFiscalLista((int) $listaId);

    return $fiscalCompraJson($bundleListaFiscal((int) $listaId));
});

Route::put('/fiscal/compras/listas/{listaId}/nota', function (Request $req, $listaId) use ($fiscalCompraAuth, $fiscalCompraPodeEditar, $fiscalCompraJson, $bundleListaFiscal, $mapNota) {
    $u = $fiscalCompraAuth($req);
    if (! $fiscalCompraPodeEditar($u)) {
        return $fiscalCompraJson(['error' => 'Sem permissão'], 403);
    }
    if (! Schema::hasTable('notas_fiscais_entrada')) {
        return $fiscalCompraJson(['error' => 'Módulo fiscal não migrado'], 503);
    }
    $lista = DB::table('listas_compras')->where('id', (int) $listaId)->first();
    if (! $lista) {
        return $fiscalCompraJson(['error' => 'Lista não encontrada'], 404);
    }
    $payload = $req->all();
    $empresaId = FiscalCompraEntradaSupport::resolverEmpresaIdLista($lista);
    if (isset($payload['empresa_id'])) {
        $empresaId = (int) $payload['empresa_id'];
    }
    $unidadeId = (int) ($lista->unidade_id ?? 0);
    $err = FiscalCompraEntradaSupport::validarEmpresaUnidade($empresaId, $unidadeId);
    if ($err) {
        return $fiscalCompraJson(['error' => $err], 400);
    }
    $chave = preg_replace('/\D+/', '', (string) ($payload['chave_acesso'] ?? ''));
    if (strlen($chave) === 44 && $empresaId && FiscalCompraEntradaSupport::chaveNfDuplicada($empresaId, $chave, null)) {
        return $fiscalCompraJson(['error' => 'Nota fiscal já cadastrada ou processada para este CNPJ.'], 409);
    }

    DB::beginTransaction();
    try {
        $nota = DB::table('notas_fiscais_entrada')->where('lista_compra_id', (int) $listaId)->first();
        $notaData = [
            'empresa_id' => $empresaId,
            'unidade_id' => $unidadeId ?: null,
            'fornecedor_id' => isset($payload['fornecedor_id']) ? (int) $payload['fornecedor_id'] : null,
            'lista_compra_id' => (int) $listaId,
            'modelo_documento' => $payload['modelo_documento'] ?? '55',
            'serie' => $payload['serie'] ?? null,
            'numero' => $payload['numero'] ?? null,
            'chave_acesso' => strlen($chave) === 44 ? $chave : ($payload['chave_acesso'] ?? null),
            'data_emissao' => $payload['data_emissao'] ?? null,
            'data_entrada' => $payload['data_entrada'] ?? null,
            'valor_produtos' => $payload['valor_produtos'] ?? null,
            'valor_frete' => $payload['valor_frete'] ?? null,
            'valor_seguro' => $payload['valor_seguro'] ?? null,
            'valor_desconto' => $payload['valor_desconto'] ?? null,
            'valor_outras_despesas' => $payload['valor_outras_despesas'] ?? null,
            'valor_total' => $payload['valor_total'] ?? null,
            'status' => in_array($payload['status'] ?? 'rascunho', FiscalCompraEntradaSupport::STATUS_NF, true)
                ? $payload['status']
                : 'rascunho',
            'observacoes' => $payload['observacoes'] ?? null,
            'updated_at' => now(),
        ];
        if ($nota) {
            if ($chave && $empresaId && FiscalCompraEntradaSupport::chaveNfDuplicada($empresaId, $chave, (int) $nota->id)) {
                DB::rollBack();

                return $fiscalCompraJson(['error' => 'Nota fiscal já cadastrada ou processada para este CNPJ.'], 409);
            }
            DB::table('notas_fiscais_entrada')->where('id', $nota->id)->update($notaData);
            $notaId = (int) $nota->id;
        } else {
            $notaData['created_at'] = now();
            $notaId = (int) DB::table('notas_fiscais_entrada')->insertGetId($notaData);
        }

        $itensIn = is_array($payload['itens'] ?? null) ? $payload['itens'] : [];
        DB::table('itens_notas_fiscais_entrada')->where('nota_fiscal_entrada_id', $notaId)->delete();
        foreach ($itensIn as $raw) {
            if (! is_array($raw)) {
                continue;
            }
            $produtoId = isset($raw['produto_id']) ? (int) $raw['produto_id'] : null;
            $produto = ($produtoId && Schema::hasTable('produtos'))
                ? DB::table('produtos')->where('id', $produtoId)->first()
                : null;
            $alertas = $produto ? FiscalCompraEntradaSupport::divergenciasItem($produto, $raw) : [];
            if ($produto && empty($raw['ncm']) && empty($raw['cfop'])) {
                if (empty($produto->ncm) && empty($produto->tipo_fiscal)) {
                    $alertas[] = ['campo' => 'cadastro', 'mensagem' => 'Produto com cadastro fiscal incompleto'];
                }
            }
            DB::table('itens_notas_fiscais_entrada')->insert([
                'nota_fiscal_entrada_id' => $notaId,
                'produto_id' => $produtoId,
                'lista_item_id' => isset($raw['lista_item_id']) ? (int) $raw['lista_item_id'] : null,
                'ncm' => $raw['ncm'] ?? null,
                'cest' => $raw['cest'] ?? null,
                'cfop' => $raw['cfop'] ?? null,
                'cst_icms' => $raw['cst_icms'] ?? null,
                'csosn' => $raw['csosn'] ?? null,
                'origem_mercadoria' => $raw['origem_mercadoria'] ?? null,
                'quantidade' => $raw['quantidade'] ?? 0,
                'unidade_medida' => $raw['unidade_medida'] ?? null,
                'valor_unitario' => $raw['valor_unitario'] ?? null,
                'valor_produto' => $raw['valor_produto'] ?? null,
                'valor_desconto' => $raw['valor_desconto'] ?? null,
                'valor_total_item' => $raw['valor_total_item'] ?? null,
                'valor_icms' => $raw['valor_icms'] ?? null,
                'valor_pis' => $raw['valor_pis'] ?? null,
                'valor_cofins' => $raw['valor_cofins'] ?? null,
                'valor_ipi' => $raw['valor_ipi'] ?? null,
                'valor_icms_st' => $raw['valor_icms_st'] ?? null,
                'cadastro_fiscal_snapshot' => json_encode(FiscalCompraEntradaSupport::snapshotCadastroProduto($produto)),
                'alertas_fiscais' => json_encode($alertas),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasColumn('listas_compras', 'empresa_id') && $empresaId) {
            DB::table('listas_compras')->where('id', (int) $listaId)->update(['empresa_id' => $empresaId]);
        }
        FiscalCompraEntradaSupport::recalcularStatusFiscalLista((int) $listaId);
        DB::commit();
    } catch (\Throwable $e) {
        DB::rollBack();

        return $fiscalCompraJson(['error' => 'Falha ao salvar NF: '.$e->getMessage()], 500);
    }

    return $fiscalCompraJson($bundleListaFiscal((int) $listaId));
});

Route::get('/fiscal/compras/relatorio-entradas', function (Request $req) use ($fiscalCompraAuth, $fiscalCompraPodeVer, $fiscalCompraJson) {
    $u = $fiscalCompraAuth($req);
    if (! $fiscalCompraPodeVer($u)) {
        return $fiscalCompraJson(['error' => 'Não autorizado'], 401);
    }
    if (! Schema::hasTable('notas_fiscais_entrada')) {
        return $fiscalCompraJson(['rows' => []]);
    }
    $q = DB::table('itens_notas_fiscais_entrada as i')
        ->join('notas_fiscais_entrada as n', 'n.id', '=', 'i.nota_fiscal_entrada_id')
        ->leftJoin('empresas as e', 'e.id', '=', 'n.empresa_id')
        ->select(
            'i.*',
            'n.numero as nf_numero',
            'n.chave_acesso',
            'n.data_emissao',
            'n.empresa_id',
            'e.razao_social as empresa_nome',
            'e.cnpj as empresa_cnpj'
        );
    if (Schema::hasTable('produtos')) {
        $q->leftJoin('produtos as p', 'p.id', '=', 'i.produto_id')->addSelect('p.nome as produto_nome');
    }
    $q->orderByDesc('n.id');
    if ($req->query('empresa_id')) {
        $q->where('n.empresa_id', (int) $req->query('empresa_id'));
    }
    if ($req->query('de')) {
        $q->where('n.data_emissao', '>=', $req->query('de'));
    }
    if ($req->query('ate')) {
        $q->where('n.data_emissao', '<=', $req->query('ate'));
    }

    return $fiscalCompraJson(['rows' => $q->limit(500)->get()]);
});

Route::get('/fiscal/compras/creditos-potenciais', function (Request $req) use ($fiscalCompraAuth, $fiscalCompraPodeVer, $fiscalCompraJson) {
    $u = $fiscalCompraAuth($req);
    if (! $fiscalCompraPodeVer($u)) {
        return $fiscalCompraJson(['error' => 'Não autorizado'], 401);
    }
    if (! Schema::hasTable('creditos_fiscais_entrada')) {
        return $fiscalCompraJson(['rows' => []]);
    }
    $q = DB::table('creditos_fiscais_entrada as c')
        ->leftJoin('notas_fiscais_entrada as n', 'n.id', '=', 'c.nota_fiscal_entrada_id')
        ->leftJoin('empresas as e', 'e.id', '=', 'c.empresa_id')
        ->select('c.*', 'n.numero as nf_numero', 'e.razao_social as empresa_nome');
    if (Schema::hasTable('produtos')) {
        $q->leftJoin('produtos as p', 'p.id', '=', 'c.produto_id')->addSelect('p.nome as produto_nome');
    }
    $q->orderByDesc('c.id');
    if ($req->query('empresa_id')) {
        $q->where('c.empresa_id', (int) $req->query('empresa_id'));
    }

    return $fiscalCompraJson(['rows' => $q->limit(500)->get()]);
});
