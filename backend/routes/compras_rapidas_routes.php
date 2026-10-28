<?php

/**
 * Rotas API — Lista de compras rápida e logo da unidade.
 */

use App\Support\ComprasRapidasSupport;
use App\Support\RegraNegocioException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

$comprasRapidasExecutar = function (Request $request, callable $acao, bool $precisaTabelas = true) {
    if ($precisaTabelas && ! ComprasRapidasSupport::tabelasOk()) {
        return response()->json(['message' => 'Lista de compras rápida ainda não instalada. Rode php artisan migrate no servidor.'], 503);
    }
    $u = ComprasRapidasSupport::usuario($request->header('X-Usuario-Id'));
    if (! $u) {
        return response()->json(['message' => 'Faça login novamente.'], 401);
    }
    try {
        return response()->json($acao($u));
    } catch (RegraNegocioException $e) {
        return response()->json(['message' => $e->getMessage()], $e->status);
    }
};

Route::prefix('compras-rapidas')->middleware('sas.usuario')->group(function () use ($comprasRapidasExecutar) {
    Route::options('/{path?}', fn () => response('', 204))->where('path', '.*');

    Route::get('/meta', fn (Request $r) => $comprasRapidasExecutar($r, fn ($u) => ComprasRapidasSupport::meta($u)));

    Route::get('/logo', fn (Request $r) => $comprasRapidasExecutar($r, fn ($u) => [
        'data_url' => ComprasRapidasSupport::logoDataUrl((int) $r->query('unidade_id')),
    ], false));

    Route::get('/', fn (Request $r) => $comprasRapidasExecutar($r, fn ($u) => ComprasRapidasSupport::listar($u, $r->only(['status', 'unidade_id']))));

    Route::post('/', fn (Request $r) => $comprasRapidasExecutar($r, fn ($u) => [
        'message' => 'Lista salva.',
        'lista' => ComprasRapidasSupport::salvar($u, $r->all()),
    ]));

    Route::put('/{id}', fn (Request $r, $id) => $comprasRapidasExecutar($r, fn ($u) => [
        'message' => 'Lista atualizada.',
        'lista' => ComprasRapidasSupport::salvar($u, $r->all(), (int) $id),
    ]))->whereNumber('id');

    Route::delete('/{id}', fn (Request $r, $id) => $comprasRapidasExecutar($r, function ($u) use ($id) {
        ComprasRapidasSupport::excluir($u, (int) $id);

        return ['message' => 'Lista excluída.'];
    }))->whereNumber('id');
});

Route::prefix('unidades/{id}/logo')->middleware('sas.usuario')->whereNumber('id')->group(function () use ($comprasRapidasExecutar) {
    Route::options('/', fn () => response('', 204));

    Route::post('/', fn (Request $r, $id) => $comprasRapidasExecutar($r, fn ($u) => [
        'message' => 'Logo da unidade salvo.',
        'logo' => ComprasRapidasSupport::salvarLogoUnidade($u, (int) $id, $r->file('logo')),
    ], false));

    Route::delete('/', fn (Request $r, $id) => $comprasRapidasExecutar($r, function ($u) use ($id) {
        ComprasRapidasSupport::salvarLogoUnidade($u, (int) $id, null);

        return ['message' => 'Logo da unidade removido.'];
    }, false));
});
