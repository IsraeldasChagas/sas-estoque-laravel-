<?php

/**
 * Rotas API — Painel administrativo do módulo "Ayla IA".
 * Autenticação: X-Usuario-Id (ADMIN para alterações; ADMIN/GERENTE para leitura).
 */

use App\Http\Controllers\AylaConviteController;
use App\Http\Controllers\AylaUsuarioController;
use Illuminate\Support\Facades\Route;

$aylaAdminCors = fn () => response()->json([])
    ->header('Access-Control-Allow-Origin', '*')
    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');

Route::options('/ayla-admin/{any}', $aylaAdminCors)->where('any', '.*');

Route::prefix('ayla-admin')->group(function () {
    Route::get('/opcoes', [AylaUsuarioController::class, 'opcoes']);
    Route::get('/dashboard', [AylaUsuarioController::class, 'dashboard']);
    Route::get('/logs', [AylaUsuarioController::class, 'logs']);

    Route::get('/config', [AylaUsuarioController::class, 'config']);
    Route::put('/config', [AylaUsuarioController::class, 'updateConfig']);
    Route::post('/gerar-token', [AylaUsuarioController::class, 'gerarToken']);
    Route::post('/testar-conexao', [AylaUsuarioController::class, 'testarConexao']);
    Route::post('/admin-principal', [AylaUsuarioController::class, 'adminPrincipal']);

    Route::get('/usuarios', [AylaUsuarioController::class, 'index']);
    Route::post('/usuarios', [AylaUsuarioController::class, 'store']);
    Route::get('/usuarios/{id}', [AylaUsuarioController::class, 'show']);
    Route::put('/usuarios/{id}', [AylaUsuarioController::class, 'update']);
    Route::patch('/usuarios/{id}/status', [AylaUsuarioController::class, 'status']);
    Route::delete('/usuarios/{id}', [AylaUsuarioController::class, 'destroy']);

    Route::get('/usuarios/{id}/convite', [AylaConviteController::class, 'show']);
    Route::post('/usuarios/{id}/convite', [AylaConviteController::class, 'gerar']);
    Route::post('/usuarios/{id}/convite/renovar', [AylaConviteController::class, 'renovar']);
    Route::delete('/usuarios/{id}/convite', [AylaConviteController::class, 'cancelar']);
    Route::post('/usuarios/{id}/telegram/sincronizar', [AylaConviteController::class, 'sincronizar']);
    Route::post('/usuarios/{id}/telegram/desvincular', [AylaConviteController::class, 'desvincular']);
});
