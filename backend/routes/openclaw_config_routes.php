<?php

/**
 * Rotas API — Painel de configuração OpenClaw (ADMIN, X-Usuario-Id).
 */

use App\Http\Controllers\OpenClawConfigController;
use Illuminate\Support\Facades\Route;

$ocCors = fn () => response()->json([])
    ->header('Access-Control-Allow-Origin', '*')
    ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');

foreach ([
    '/openclaw/config',
    '/openclaw/gerar-token',
    '/openclaw/testar-conexao',
    '/openclaw/logs',
] as $p) {
    Route::options($p, $ocCors);
}

Route::get('/openclaw/config', [OpenClawConfigController::class, 'show']);
Route::post('/openclaw/config', [OpenClawConfigController::class, 'update']);
Route::post('/openclaw/gerar-token', [OpenClawConfigController::class, 'gerarToken']);
Route::post('/openclaw/testar-conexao', [OpenClawConfigController::class, 'testarConexao']);
Route::get('/openclaw/logs', [OpenClawConfigController::class, 'logs']);
