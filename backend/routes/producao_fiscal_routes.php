<?php

use App\Support\FiscalMovimentacaoSupport;
use App\Support\ProducaoFiscalSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

Route::get('/fiscal/producao/meta', function () {
    return response()->json([
        'modulo_ativo' => ProducaoFiscalSupport::moduloAtivo(),
        'status' => ProducaoFiscalSupport::STATUS,
    ]);
});

Route::patch('/fichas-tecnicas/{id}/producao', function (Request $request, $id) {
    if (! Schema::hasTable('fichas_tecnicas')) {
        return response()->json(['error' => 'Indisponível'], 503);
    }
    $ficha = DB::table('fichas_tecnicas')->where('id', (int) $id)->first();
    if (! $ficha) {
        return response()->json(['error' => 'Ficha não encontrada'], 404);
    }
    $data = $request->validate([
        'empresa_id' => 'nullable|integer',
        'produto_final_id' => 'nullable|integer|exists:produtos,id',
        'rendimento_quantidade' => 'nullable|numeric|min:0.001',
        'rendimento_unidade' => 'nullable|string|max:20',
        'observacao' => 'nullable|string|max:2000',
    ]);
    $up = ['updated_at' => now()];
    foreach (['empresa_id', 'produto_final_id', 'rendimento_quantidade', 'rendimento_unidade', 'observacao'] as $col) {
        if (Schema::hasColumn('fichas_tecnicas', $col) && array_key_exists($col, $data)) {
            $up[$col] = $data[$col];
        }
    }
    if (Schema::hasColumn('fichas_tecnicas', 'versao')) {
        $up['versao'] = (int) ($ficha->versao ?? 1);
    }
    DB::table('fichas_tecnicas')->where('id', (int) $id)->update($up);
    ProducaoFiscalSupport::sincronizarItensFicha((int) $id);

    return response()->json(['ok' => true]);
});

Route::get('/fiscal/producoes', function (Request $request) {
    if (! ProducaoFiscalSupport::moduloAtivo()) {
        return response()->json([]);
    }
    $q = DB::table('producoes')
        ->leftJoin('produtos', 'producoes.produto_final_id', '=', 'produtos.id')
        ->leftJoin('unidades', 'producoes.unidade_id', '=', 'unidades.id')
        ->select('producoes.*', 'produtos.nome as produto_final_nome', 'unidades.nome as unidade_nome');

    if ($request->filled('status')) {
        $q->where('producoes.status', $request->status);
    }
    if ($request->filled('empresa_id')) {
        $q->where('producoes.empresa_id', (int) $request->empresa_id);
    }

    return response()->json($q->orderByDesc('producoes.id')->limit(100)->get());
});

Route::post('/fiscal/producoes', function (Request $request) {
    $usuarioId = (int) $request->header('X-Usuario-Id', $request->input('usuario_id', 0));
    if ($usuarioId <= 0) {
        return response()->json(['error' => 'Usuário não autenticado'], 401);
    }
    try {
        $data = $request->validate([
            'ficha_tecnica_id' => 'required|integer',
            'unidade_id' => 'required|integer|exists:unidades,id',
            'empresa_id' => 'nullable|integer',
            'produto_final_id' => 'nullable|integer|exists:produtos,id',
            'quantidade_planejada' => 'required|numeric|min:0.001',
            'quantidade_produzida' => 'nullable|numeric|min:0.001',
            'observacao' => 'nullable|string',
        ]);
        $id = ProducaoFiscalSupport::criarProducao($data, $usuarioId);

        return response()->json(['id' => $id, 'message' => 'Produção criada'], 201);
    } catch (\Throwable $e) {
        return response()->json(['error' => $e->getMessage(), 'message' => $e->getMessage()], 422);
    }
});

Route::get('/fiscal/producoes/{id}', function ($id) {
    if (! ProducaoFiscalSupport::moduloAtivo()) {
        return response()->json(['error' => 'Indisponível'], 503);
    }
    $p = DB::table('producoes')->where('id', (int) $id)->first();
    if (! $p) {
        return response()->json(['error' => 'Não encontrada'], 404);
    }
    $insumos = DB::table('producao_insumos')
        ->leftJoin('produtos', 'producao_insumos.produto_id', '=', 'produtos.id')
        ->where('producao_id', (int) $id)
        ->select('producao_insumos.*', 'produtos.nome as produto_nome')
        ->get();
    $lotes = DB::table('producao_lotes')->where('producao_id', (int) $id)->get();
    $evento = Schema::hasTable('eventos_fiscais')
        ? DB::table('eventos_fiscais')->where('producao_id', (int) $id)->whereNotIn('status', ['cancelado'])->first()
        : null;

    return response()->json([
        'producao' => $p,
        'insumos' => $insumos,
        'lotes_consumidos' => $lotes,
        'evento_fiscal' => $evento,
    ]);
});

Route::post('/fiscal/producoes/{id}/simular', function (Request $request, $id) {
    try {
        $p = DB::table('producoes')->where('id', (int) $id)->first();
        if (! $p) {
            return response()->json(['error' => 'Não encontrada'], 404);
        }
        $reais = $request->input('quantidades_reais', []);
        $map = is_array($reais) ? array_map('floatval', $reais) : [];

        return response()->json(ProducaoFiscalSupport::simular((int) $id, (int) $p->unidade_id, $p->empresa_id ? (int) $p->empresa_id : null, $map));
    } catch (\Throwable $e) {
        return response()->json(['error' => $e->getMessage()], 422);
    }
});

Route::post('/fiscal/producoes/{id}/finalizar', function (Request $request, $id) {
    $usuarioId = (int) $request->header('X-Usuario-Id', $request->input('usuario_id', 0));
    if ($usuarioId <= 0) {
        return response()->json(['error' => 'Usuário não autenticado'], 401);
    }
    try {
        $reais = $request->input('quantidades_reais', []);
        $map = is_array($reais) ? array_map('floatval', $reais) : null;
        $custoAd = (float) $request->input('custo_adicional', 0);
        $result = ProducaoFiscalSupport::finalizar((int) $id, $usuarioId, $map, $custoAd);

        return response()->json($result);
    } catch (\Throwable $e) {
        return response()->json(['error' => $e->getMessage(), 'message' => $e->getMessage()], 422);
    }
});

Route::get('/fiscal/producoes/{id}/rastreabilidade', function ($id) {
    $p = DB::table('producoes')->where('id', (int) $id)->first();
    if (! $p) {
        return response()->json(['error' => 'Não encontrada'], 404);
    }
    $cadeia = [];
    $lotes = DB::table('producao_lotes')->where('producao_id', (int) $id)->get();
    foreach ($lotes as $pl) {
        $lote = $pl->lote_id ? DB::table('lotes')->where('id', $pl->lote_id)->first() : null;
        $nfId = $lote->nota_fiscal_entrada_id ?? null;
        $nf = $nfId && Schema::hasTable('notas_fiscais_entrada')
            ? DB::table('notas_fiscais_entrada')->where('id', $nfId)->first()
            : null;
        $cadeia[] = [
            'codigo_lote' => $pl->codigo_lote,
            'quantidade' => $pl->quantidade_consumida,
            'nota_fiscal_entrada_id' => $nfId,
            'chave_nf' => $nf->chave_acesso ?? null,
            'lista_compra_id' => $lote->lista_compra_id ?? null,
        ];
    }

    return response()->json(['producao_id' => (int) $id, 'insumos_lotes' => $cadeia]);
});
