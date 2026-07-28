<?php

use App\Services\Fiscal\FiscalEmissaoService;
use App\Support\VendaFiscalSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

Route::get('/fiscal/vendas/meta', function () {
    return response()->json([
        'modulo_ativo' => VendaFiscalSupport::moduloAtivo(),
        'status_venda' => VendaFiscalSupport::STATUS_VENDA,
        'status_documento' => VendaFiscalSupport::STATUS_DOCUMENTO,
    ]);
});

Route::post('/fiscal/vendas/validar-item', function (Request $request) {
    $data = $request->validate([
        'unidade_id' => 'required|integer',
        'empresa_id' => 'nullable|integer',
        'produto_id' => 'required|integer',
        'quantidade' => 'required|numeric|min:0.001',
        'lote_id' => 'nullable|integer',
    ]);
    $empresaId = isset($data['empresa_id']) ? (int) $data['empresa_id'] : VendaFiscalSupport::resolverEmpresaUnidade((int) $data['unidade_id']);
    $val = VendaFiscalSupport::validarPropriedadeFiscal(
        $empresaId,
        (int) $data['unidade_id'],
        (int) $data['produto_id'],
        (float) $data['quantidade'],
        isset($data['lote_id']) ? (int) $data['lote_id'] : null
    );
    if (! ($val['ok'] ?? false)) {
        $uid = (int) $request->header('X-Usuario-Id', 0);
        VendaFiscalSupport::registrarBloqueio(
            $uid ?: null,
            $empresaId,
            $val['empresa_estoque_id'] ?? null,
            (int) $data['unidade_id'],
            (int) $data['produto_id'],
            (float) $data['quantidade'],
            $val['message'] ?? ''
        );

        return response()->json(['ok' => false, 'message' => $val['message']], 422);
    }

    return response()->json(['ok' => true]);
});

Route::post('/fiscal/vendas', function (Request $request) {
    $usuarioId = (int) $request->header('X-Usuario-Id', $request->input('usuario_id', 0));
    if ($usuarioId <= 0) {
        return response()->json(['error' => 'Usuário não autenticado'], 401);
    }
    try {
        $payload = $request->validate([
            'unidade_id' => 'required|integer|exists:unidades,id',
            'empresa_id' => 'nullable|integer',
            'forma_pagamento' => 'nullable|string|max:40',
            'numero_documento' => 'nullable|string|max:60',
            'chave_acesso' => 'nullable|string|max:44',
            'status_documento' => 'nullable|string|max:24',
            'pdv_terminal' => 'nullable|string|max:64',
            'observacao' => 'nullable|string',
            'itens' => 'required|array|min:1',
            'itens.*.produto_id' => 'required|integer|exists:produtos,id',
            'itens.*.quantidade' => 'required|numeric|min:0.001',
            'itens.*.preco_unitario' => 'required|numeric|min:0',
            'itens.*.desconto' => 'nullable|numeric|min:0',
        ]);
        $result = VendaFiscalSupport::finalizarVenda($payload, $usuarioId);
        $semEmissao = filter_var($request->input('sem_emissao', false), FILTER_VALIDATE_BOOLEAN);
        $result = FiscalEmissaoService::anexarEmissaoAoResultado($result, ! $semEmissao);

        return response()->json($result, 201);
    } catch (\Throwable $e) {
        return response()->json(['error' => $e->getMessage(), 'message' => $e->getMessage()], 422);
    }
});

Route::get('/fiscal/vendas', function (Request $request) {
    if (! VendaFiscalSupport::moduloAtivo()) {
        return response()->json([]);
    }
    $q = DB::table('vendas')
        ->leftJoin('unidades', 'vendas.unidade_id', '=', 'unidades.id')
        ->select('vendas.*', 'unidades.nome as unidade_nome');
    if ($request->filled('empresa_id')) {
        $q->where('vendas.empresa_id', (int) $request->empresa_id);
    }
    if ($request->filled('data_ini')) {
        $q->where('vendas.data_venda', '>=', $request->data_ini);
    }
    if ($request->filled('data_fim')) {
        $q->where('vendas.data_venda', '<=', $request->data_fim . ' 23:59:59');
    }

    return response()->json($q->orderByDesc('vendas.id')->limit(100)->get());
});

Route::get('/fiscal/vendas/{id}', function ($id) {
    $v = DB::table('vendas')->where('id', (int) $id)->first();
    if (! $v) {
        return response()->json(['error' => 'Não encontrada'], 404);
    }
    $itens = DB::table('venda_itens')->where('venda_id', (int) $id)->get();
    $tributos = Schema::hasTable('tributos_venda')
        ? DB::table('tributos_venda')->where('venda_id', (int) $id)->get()
        : [];
    $evento = Schema::hasTable('eventos_fiscais')
        ? DB::table('eventos_fiscais')->where('venda_id', (int) $id)->first()
        : null;

    return response()->json(compact('v', 'itens', 'tributos', 'evento'));
});

Route::get('/fiscal/painel/vendas', function (Request $request) {
    if (! Schema::hasTable('vendas')) {
        return response()->json(['vendas' => [], 'totais' => []]);
    }
    $q = DB::table('vendas')->where('status', 'finalizada');
    if ($request->filled('empresa_id')) {
        $q->where('empresa_id', (int) $request->empresa_id);
    }
    if ($request->filled('data_ini')) {
        $q->where('data_venda', '>=', $request->data_ini);
    }
    if ($request->filled('data_fim')) {
        $q->where('data_venda', '<=', $request->data_fim . ' 23:59:59');
    }
    $rows = $q->orderByDesc('id')->limit(200)->get();

    return response()->json([
        'vendas' => $rows,
        'totais' => [
            'receita_liquida' => $rows->sum(fn ($r) => (float) $r->valor_liquido),
            'custo' => $rows->sum(fn ($r) => (float) ($r->custo_total ?? 0)),
            'quantidade' => $rows->count(),
        ],
    ]);
});

Route::get('/fiscal/relatorio/vendas-sem-origem', function () {
    if (! Schema::hasTable('venda_itens')) {
        return response()->json(['itens' => []]);
    }
    $itens = DB::table('venda_itens')
        ->leftJoin('vendas', 'venda_itens.venda_id', '=', 'vendas.id')
        ->leftJoin('produtos', 'venda_itens.produto_id', '=', 'produtos.id')
        ->whereNull('venda_itens.lote_id')
        ->select('venda_itens.*', 'vendas.data_venda', 'produtos.nome as produto_nome')
        ->orderByDesc('venda_itens.id')
        ->limit(100)
        ->get();

    return response()->json(['itens' => $itens]);
});
