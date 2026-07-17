<?php

use App\Http\Controllers\Orcamentos\OrcamentoController;
use Illuminate\Support\Facades\Route;

Route::prefix('orcamentos')->middleware('sas.usuario')->group(function () {
    Route::options('/{path?}', fn () => response('', 204))
        ->where('path', '.*');

    Route::get('/catalogos', [OrcamentoController::class, 'catalogos']);
    Route::get('/clientes', [OrcamentoController::class, 'clientes']);
    Route::get('/dashboard', [OrcamentoController::class, 'dashboard']);
    Route::get('/', [OrcamentoController::class, 'index']);
    Route::post('/', [OrcamentoController::class, 'store']);
    Route::get('/{id}', [OrcamentoController::class, 'show'])->whereNumber('id');
    Route::put('/{id}', [OrcamentoController::class, 'update'])->whereNumber('id');
    Route::patch('/{id}/status', [OrcamentoController::class, 'status'])->whereNumber('id');
    Route::delete('/{id}', [OrcamentoController::class, 'destroy'])->whereNumber('id');
});
