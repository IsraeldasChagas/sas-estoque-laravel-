<?php

/**
 * Rotas API — Contagem de estoque (inventário físico).
 */

use App\Support\ContagemEstoqueException;
use App\Support\ContagemEstoqueSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('contagem')->middleware('sas.usuario')->group(function () {
    $executar = function (Request $request, callable $acao) {
        if (! ContagemEstoqueSupport::tabelasOk()) {
            return response()->json(['message' => 'Módulo de contagem ainda não instalado. Rode php artisan migrate no servidor.'], 503);
        }
        $u = ContagemEstoqueSupport::usuario($request->header('X-Usuario-Id'));
        if (! $u) {
            return response()->json(['message' => 'Faça login novamente.'], 401);
        }
        try {
            return response()->json($acao($u));
        } catch (ContagemEstoqueException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    };

    Route::options('/{path?}', fn () => response('', 204))->where('path', '.*');

    Route::get('/meta', fn (Request $r) => $executar($r, fn ($u) => ContagemEstoqueSupport::meta($u)));

    Route::get('/produtos', fn (Request $r) => $executar($r, fn ($u) => ContagemEstoqueSupport::produtosParaEscolha($u, (int) $r->query('unidade_id'))));

    Route::get('/inventarios', fn (Request $r) => $executar($r, fn ($u) => ContagemEstoqueSupport::listar(
        $u,
        $r->only(['unidade_id', 'status', 'de', 'ate']),
        $r->query('detalhe') === '1'
    )));

    Route::get('/inventarios/{id}', fn (Request $r, $id) => $executar($r, fn ($u) => ContagemEstoqueSupport::detalhe($u, (int) $id)))
        ->whereNumber('id');

    Route::post('/inventarios', fn (Request $r) => $executar($r, function ($u) use ($r) {
        $id = ContagemEstoqueSupport::salvarRascunho($u, $r->all());

        return ['message' => 'Rascunho da contagem salvo.', 'inventario' => ContagemEstoqueSupport::detalhe($u, $id)];
    }));

    Route::delete('/inventarios/{id}', fn (Request $r, $id) => $executar($r, function ($u) use ($id) {
        ContagemEstoqueSupport::excluirRascunho($u, (int) $id);

        return ['message' => 'Rascunho excluído.'];
    }))->whereNumber('id');

    Route::post('/inventarios/{id}/iniciar', fn (Request $r, $id) => $executar($r, function ($u) use ($id) {
        ContagemEstoqueSupport::iniciar($u, (int) $id);

        return ['message' => 'Contagem iniciada. O saldo de referência ficou registrado.', 'inventario' => ContagemEstoqueSupport::detalhe($u, (int) $id)];
    }))->whereNumber('id');

    Route::post('/inventarios/{id}/lancar', fn (Request $r, $id) => $executar($r, function ($u) use ($r, $id) {
        $msg = ContagemEstoqueSupport::lancar($u, (int) $id, $r->all());

        return ['message' => $msg, 'inventario' => ContagemEstoqueSupport::detalhe($u, (int) $id)];
    }))->whereNumber('id');

    Route::post('/inventarios/{id}/finalizar', fn (Request $r, $id) => $executar($r, function ($u) use ($id) {
        ContagemEstoqueSupport::finalizar($u, (int) $id);

        return ['message' => 'Contagem finalizada. As divergências foram calculadas.', 'inventario' => ContagemEstoqueSupport::detalhe($u, (int) $id)];
    }))->whereNumber('id');

    Route::post('/inventarios/{id}/decidir', fn (Request $r, $id) => $executar($r, function ($u) use ($r, $id) {
        $msg = ContagemEstoqueSupport::decidir($u, (int) $id, $r->all());

        return ['message' => $msg];
    }))->whereNumber('id');

    Route::post('/inventarios/{id}/encerrar', fn (Request $r, $id) => $executar($r, function ($u) use ($id) {
        ContagemEstoqueSupport::encerrar($u, (int) $id);

        return ['message' => 'Inventário encerrado.'];
    }))->whereNumber('id');

    Route::get('/config', fn (Request $r) => $executar($r, fn ($u) => ContagemEstoqueSupport::config()));

    Route::put('/config', fn (Request $r) => $executar($r, function ($u) use ($r) {
        ContagemEstoqueSupport::salvarConfig($u, $r->boolean('cega_padrao'));

        return ['message' => 'Configuração da contagem salva.', 'config' => ContagemEstoqueSupport::config()];
    }));
});
