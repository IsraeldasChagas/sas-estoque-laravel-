<?php

use App\Support\PdvComercialSupport;
use App\Support\VendaFiscalSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

Route::get('/pdv/meta', function () {
    return response()->json([
        'modulo_comandas' => PdvComercialSupport::moduloAtivo(),
        'modulo_venda_fiscal' => VendaFiscalSupport::moduloAtivo(),
    ]);
});

Route::get('/pdv/produtos', function (Request $request) {
    $request->validate(['unidade_id' => 'required|integer']);
    $search = $request->input('search');

    return response()->json(
        PdvComercialSupport::listarProdutosPdv((int) $request->unidade_id, is_string($search) ? trim($search) : null)
    );
});

Route::get('/pdv/salao', function (Request $request) {
    $request->validate(['unidade_id' => 'required|integer']);
    if (! PdvComercialSupport::moduloAtivo()) {
        return response()->json(['error' => 'Módulo PDV não migrado'], 422);
    }

    return response()->json(
        PdvComercialSupport::mapaSalao((int) $request->unidade_id, $request->input('data'))
    );
});

Route::get('/pdv/comandas/abertas', function (Request $request) {
    $request->validate(['unidade_id' => 'required|integer']);

    return response()->json(PdvComercialSupport::comandasAbertas((int) $request->unidade_id));
});

Route::patch('/pdv/comandas/{id}', function (Request $request, int $id) {
    $data = $request->validate([
        'pessoas' => 'nullable|integer|min:1',
        'desconto' => 'nullable|numeric|min:0',
        'acrescimo' => 'nullable|numeric|min:0',
        'status' => 'nullable|string|in:aguardando_pagamento,aberta',
    ]);
    try {
        return response()->json(PdvComercialSupport::atualizarComanda($id, $data));
    } catch (\Throwable $e) {
        return response()->json(['error' => $e->getMessage()], 422);
    }
});

Route::get('/pdv/comandas/{id}/pre-conta', function (int $id) {
    try {
        return response()->json(['html' => PdvComercialSupport::preContaHtml($id)]);
    } catch (\Throwable $e) {
        return response()->json(['error' => $e->getMessage()], 422);
    }
});

Route::get('/pdv/comandas/{id}', function (int $id) {
    if (! PdvComercialSupport::moduloAtivo()) {
        return response()->json(['error' => 'Módulo PDV não migrado'], 422);
    }

    return response()->json(PdvComercialSupport::comandaCompleta($id));
});

Route::post('/pdv/comandas/abrir', function (Request $request) {
    $uid = (int) $request->header('X-Usuario-Id', 0);
    if ($uid <= 0) {
        return response()->json(['error' => 'Usuário não autenticado'], 401);
    }
    $data = $request->validate([
        'unidade_id' => 'required|integer',
        'mesa_id' => 'nullable|integer',
        'reserva_mesa_id' => 'nullable|integer',
        'pessoas' => 'nullable|integer|min:1',
        'garcom_usuario_id' => 'nullable|integer',
        'origem' => 'nullable|string|max:24',
        'observacao' => 'nullable|string',
    ]);
    try {
        return response()->json(PdvComercialSupport::abrirComanda($data, $uid), 201);
    } catch (\Throwable $e) {
        return response()->json(['error' => $e->getMessage()], 422);
    }
});

Route::post('/pdv/comandas/{id}/itens', function (Request $request, int $id) {
    $data = $request->validate([
        'produto_id' => 'required|integer',
        'quantidade' => 'required|numeric|min:0.001',
        'preco_unitario' => 'nullable|numeric|min:0',
        'desconto' => 'nullable|numeric|min:0',
        'observacao' => 'nullable|string',
    ]);
    try {
        return response()->json(PdvComercialSupport::adicionarItem($id, $data));
    } catch (\Throwable $e) {
        return response()->json(['error' => $e->getMessage()], 422);
    }
});

Route::delete('/pdv/comandas/{id}/itens/{itemId}', function (int $id, int $itemId) {
    try {
        return response()->json(PdvComercialSupport::removerItem($id, $itemId));
    } catch (\Throwable $e) {
        return response()->json(['error' => $e->getMessage()], 422);
    }
});

Route::post('/pdv/comandas/{id}/finalizar', function (Request $request, int $id) {
    $uid = (int) $request->header('X-Usuario-Id', 0);
    if ($uid <= 0) {
        return response()->json(['error' => 'Usuário não autenticado'], 401);
    }
    $payload = $request->validate([
        'forma_pagamento' => 'nullable|string|max:40',
        'pdv_terminal' => 'nullable|string|max:64',
        'observacao' => 'nullable|string',
    ]);
    try {
        return response()->json(PdvComercialSupport::finalizarComanda($id, $payload, $uid), 201);
    } catch (\Throwable $e) {
        return response()->json(['error' => $e->getMessage()], 422);
    }
});

Route::post('/pdv/vendas/balcao', function (Request $request) {
    $uid = (int) $request->header('X-Usuario-Id', 0);
    if ($uid <= 0) {
        return response()->json(['error' => 'Usuário não autenticado'], 401);
    }
    try {
        $payload = $request->validate([
            'unidade_id' => 'required|integer|exists:unidades,id',
            'forma_pagamento' => 'nullable|string|max:40',
            'pdv_terminal' => 'nullable|string|max:64',
            'observacao' => 'nullable|string',
            'itens' => 'required|array|min:1',
            'itens.*.produto_id' => 'required|integer|exists:produtos,id',
            'itens.*.quantidade' => 'required|numeric|min:0.001',
            'itens.*.preco_unitario' => 'required|numeric|min:0',
            'itens.*.desconto' => 'nullable|numeric|min:0',
        ]);

        return response()->json(PdvComercialSupport::vendaBalcao($payload, $uid), 201);
    } catch (\Throwable $e) {
        return response()->json(['error' => $e->getMessage(), 'message' => $e->getMessage()], 422);
    }
});

Route::get('/pdv/vendas', function (Request $request) {
    if (! Schema::hasTable('vendas')) {
        return response()->json([]);
    }
    $q = DB::table('vendas')
        ->leftJoin('unidades', 'vendas.unidade_id', '=', 'unidades.id')
        ->leftJoin('mesas', 'vendas.mesa_id', '=', 'mesas.id')
        ->select(
            'vendas.*',
            'unidades.nome as unidade_nome',
            'mesas.numero_mesa',
            'mesas.nome_mesa'
        )
        ->where('vendas.status', 'finalizada');
    if ($request->filled('unidade_id')) {
        $q->where('vendas.unidade_id', (int) $request->unidade_id);
    }
    if ($request->filled('data_ini')) {
        $q->where('vendas.data_venda', '>=', $request->data_ini);
    }
    if ($request->filled('data_fim')) {
        $q->where('vendas.data_venda', '<=', $request->data_fim . ' 23:59:59');
    }

    return response()->json($q->orderByDesc('vendas.id')->limit(100)->get());
});
