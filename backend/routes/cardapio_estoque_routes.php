<?php

use App\Support\CardapioEstoqueSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$cardapioEstoqueAuth = function (Request $request) {
    $uid = (int) $request->header('X-Usuario-Id', 0);
    if ($uid <= 0) {
        return null;
    }

    return DB::table('usuarios')->where('id', $uid)->first();
};

Route::get('/cardapio-estoque', function (Request $request) use ($cardapioEstoqueAuth) {
    if (! $cardapioEstoqueAuth($request)) {
        return response()->json(['error' => 'Usuário não autenticado'], 401);
    }
    if (! CardapioEstoqueSupport::moduloAtivo()) {
        return response()->json(['error' => 'Módulo indisponível. Rode as migrations.'], 503);
    }
    $unidadeId = (int) $request->query('unidade_id', 0);
    if ($unidadeId <= 0) {
        return response()->json(['error' => 'Informe unidade_id.'], 422);
    }
    $search = $request->query('search');

    return response()->json([
        'unidade_id' => $unidadeId,
        'itens' => CardapioEstoqueSupport::listarSaldos($unidadeId, is_string($search) ? $search : null),
    ]);
});

Route::get('/cardapio-estoque/movimentacoes', function (Request $request) use ($cardapioEstoqueAuth) {
    if (! $cardapioEstoqueAuth($request)) {
        return response()->json(['error' => 'Usuário não autenticado'], 401);
    }
    if (! CardapioEstoqueSupport::moduloAtivo()) {
        return response()->json(['error' => 'Módulo indisponível.'], 503);
    }
    $unidadeId = (int) $request->query('unidade_id', 0);
    if ($unidadeId <= 0) {
        return response()->json(['error' => 'Informe unidade_id.'], 422);
    }
    $dlvId = $request->filled('dlv_produto_id') ? (int) $request->query('dlv_produto_id') : null;
    $limit = (int) $request->query('limit', 200);

    return response()->json([
        'unidade_id' => $unidadeId,
        'movimentacoes' => CardapioEstoqueSupport::listarMovimentacoes($unidadeId, $dlvId, $limit),
    ]);
});

Route::post('/cardapio-estoque/entrada', function (Request $request) use ($cardapioEstoqueAuth) {
    $usuario = $cardapioEstoqueAuth($request);
    if (! $usuario) {
        return response()->json(['error' => 'Usuário não autenticado'], 401);
    }
    if (! CardapioEstoqueSupport::moduloAtivo()) {
        return response()->json(['error' => 'Módulo indisponível.'], 503);
    }
    $data = $request->validate([
        'unidade_id' => 'required|integer',
        'dlv_produto_id' => 'required|integer',
        'quantidade' => 'required|numeric|min:0.001',
        'motivo' => 'nullable|string|max:255',
        'origem' => 'nullable|string|max:40',
    ]);
    try {
        $origem = (string) ($data['origem'] ?? CardapioEstoqueSupport::ORIGEM_ABASTECIMENTO);
        $res = CardapioEstoqueSupport::entrada(
            (int) $data['unidade_id'],
            (int) $data['dlv_produto_id'],
            (float) $data['quantidade'],
            $origem,
            [
                'usuario_id' => (int) $usuario->id,
                'motivo' => $data['motivo'] ?? 'Abastecimento cardápio',
            ]
        );

        return response()->json($res, 201);
    } catch (\Throwable $e) {
        return response()->json(['error' => $e->getMessage()], 422);
    }
});

Route::post('/cardapio-estoque/ajuste', function (Request $request) use ($cardapioEstoqueAuth) {
    $usuario = $cardapioEstoqueAuth($request);
    if (! $usuario) {
        return response()->json(['error' => 'Usuário não autenticado'], 401);
    }
    if (! CardapioEstoqueSupport::moduloAtivo()) {
        return response()->json(['error' => 'Módulo indisponível.'], 503);
    }
    $data = $request->validate([
        'unidade_id' => 'required|integer',
        'dlv_produto_id' => 'required|integer',
        'quantidade' => 'required|numeric|min:0',
        'estoque_minimo' => 'nullable|numeric|min:0',
        'motivo' => 'nullable|string|max:255',
        'controla_estoque_cardapio' => 'nullable|boolean',
    ]);
    try {
        $unidadeId = (int) $data['unidade_id'];
        $dlvId = (int) $data['dlv_produto_id'];
        if (array_key_exists('controla_estoque_cardapio', $data)
            && Schema::hasTable('dlv_produtos')
            && Schema::hasColumn('dlv_produtos', 'controla_estoque_cardapio')
        ) {
            DB::table('dlv_produtos')->where('id', $dlvId)->update([
                'controla_estoque_cardapio' => (bool) $data['controla_estoque_cardapio'],
                'updated_at' => now(),
            ]);
        }
        if (array_key_exists('estoque_minimo', $data)) {
            CardapioEstoqueSupport::definirMinimo($unidadeId, $dlvId, (float) $data['estoque_minimo']);
        }
        $res = CardapioEstoqueSupport::ajustar($unidadeId, $dlvId, (float) $data['quantidade'], [
            'usuario_id' => (int) $usuario->id,
            'motivo' => $data['motivo'] ?? 'Ajuste inventário cardápio',
        ]);

        return response()->json($res);
    } catch (\Throwable $e) {
        return response()->json(['error' => $e->getMessage()], 422);
    }
});
