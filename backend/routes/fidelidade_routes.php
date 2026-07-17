<?php

use App\Http\Controllers\Fidelidade\FidelidadeController;
use Illuminate\Support\Facades\Route;

Route::prefix('fidelidade')->middleware('sas.usuario')->group(function () {
    Route::options('/{path?}', fn () => response('', 204))
        ->where('path', '.*');

    Route::get('/programa', [FidelidadeController::class, 'getPrograma']);
    Route::put('/programa', [FidelidadeController::class, 'putPrograma']);

    Route::get('/cartoes', [FidelidadeController::class, 'listCartoes']);
    Route::post('/cartoes', [FidelidadeController::class, 'storeCartao']);
    Route::get('/cartoes/{id}', [FidelidadeController::class, 'showCartao'])->whereNumber('id');
    Route::post('/cartoes/{id}/selo', [FidelidadeController::class, 'postSelo'])->whereNumber('id');
    Route::post('/cartoes/{id}/ajuste', [FidelidadeController::class, 'postAjuste'])->whereNumber('id');
    Route::patch('/cartoes/{id}/status', [FidelidadeController::class, 'patchStatus'])->whereNumber('id');
    Route::get('/cartoes/{id}/extrato', [FidelidadeController::class, 'extrato'])->whereNumber('id');
    Route::post('/cartoes/{id}/estornar/{ledgerId}', [FidelidadeController::class, 'estornar'])
        ->whereNumber('id')
        ->whereNumber('ledgerId');
    Route::post('/cartoes/{id}/resgatar', [FidelidadeController::class, 'redeem'])->whereNumber('id');

    Route::get('/recompensas', [FidelidadeController::class, 'listRecompensas']);
    Route::post('/recompensas', [FidelidadeController::class, 'storeRecompensa']);
    Route::put('/recompensas/{id}', [FidelidadeController::class, 'updateRecompensa'])->whereNumber('id');
    Route::delete('/recompensas/{id}', [FidelidadeController::class, 'destroyRecompensa'])->whereNumber('id');

    Route::get('/resgates', [FidelidadeController::class, 'listResgates']);
    Route::patch('/resgates/{id}', [FidelidadeController::class, 'patchResgate'])->whereNumber('id');

    Route::get('/relatorios/resumo', [FidelidadeController::class, 'relatorioResumo']);
});
