<?php

use App\Http\Controllers\ImpostoController;
use Illuminate\Support\Facades\Route;

$impCors = fn () => response()->json([])
    ->header('Access-Control-Allow-Origin', '*')
    ->header('Access-Control-Allow-Methods', 'GET, POST, DELETE, OPTIONS')
    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');

foreach ([
    '/impostos',
    '/impostos/{id}',
    '/impostos/{id}/gerar-boleto',
    '/impostos/anexos/{anexoId}',
] as $p) {
    Route::options($p, $impCors);
}

Route::get('/impostos', [ImpostoController::class, 'index']);
Route::post('/impostos', [ImpostoController::class, 'store']);
Route::get('/impostos/{id}', [ImpostoController::class, 'show']);
Route::post('/impostos/{id}', [ImpostoController::class, 'update']);
Route::delete('/impostos/{id}', [ImpostoController::class, 'destroy']);
Route::post('/impostos/{id}/gerar-boleto', [ImpostoController::class, 'gerarBoleto']);
Route::get('/impostos/anexos/{anexoId}', [ImpostoController::class, 'downloadAnexo']);
Route::delete('/impostos/anexos/{anexoId}', [ImpostoController::class, 'removerAnexo']);
