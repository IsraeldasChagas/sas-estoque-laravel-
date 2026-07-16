<?php

/**
 * Rotas API — Módulo Integrações (Fase 2 — conexão real VendaFácil).
 */

use App\Http\Controllers\Integrations\HealthCheckController;
use App\Http\Controllers\Integrations\IntegrationController;
use App\Http\Controllers\Integrations\IntegrationLogController;
use App\Http\Controllers\Integrations\VendaFacilController;
use App\Http\Controllers\Integrations\WebhookController;
use Illuminate\Support\Facades\Route;

$intCors = fn () => response()->json([])
    ->header('Access-Control-Allow-Origin', '*')
    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');

$paths = [
    '/integracoes/aplicacoes',
    '/integracoes/health-check',
    '/integracoes/logs',
    '/integracoes/vendafacil',
    '/integracoes/vendafacil/testar',
    '/integracoes/vendafacil/testar-conexao',
    '/integracoes/vendafacil/health',
    '/integracoes/vendafacil/logs',
    '/integracoes/vendafacil/unidades',
    '/integracoes/vendafacil/sincronizar',
    '/integracoes/vendafacil/limpar-cache',
    '/integracoes/vendafacil/desconectar',
    '/integracoes/webhooks',
    '/integracoes/webhooks/{provider}',
];

foreach ($paths as $p) {
    Route::options($p, $intCors);
}

Route::get('/integracoes/aplicacoes', [IntegrationController::class, 'aplicacoes']);
Route::get('/integracoes/health-check', [HealthCheckController::class, 'index']);
Route::get('/integracoes/logs', [IntegrationLogController::class, 'index']);

Route::get('/integracoes/vendafacil', [VendaFacilController::class, 'show']);
Route::put('/integracoes/vendafacil', [VendaFacilController::class, 'update']);
Route::post('/integracoes/vendafacil', [VendaFacilController::class, 'save']);
Route::post('/integracoes/vendafacil/testar', [VendaFacilController::class, 'testarConexao']);
Route::post('/integracoes/vendafacil/testar-conexao', [VendaFacilController::class, 'testarConexao']);
Route::get('/integracoes/vendafacil/health', [VendaFacilController::class, 'health']);
Route::get('/integracoes/vendafacil/logs', [VendaFacilController::class, 'logs']);
Route::get('/integracoes/vendafacil/unidades', [VendaFacilController::class, 'unidades']);
Route::put('/integracoes/vendafacil/unidades', [VendaFacilController::class, 'salvarUnidades']);
Route::post('/integracoes/vendafacil/unidades', [VendaFacilController::class, 'salvarUnidades']);
Route::post('/integracoes/vendafacil/sincronizar', [VendaFacilController::class, 'sincronizar']);
Route::post('/integracoes/vendafacil/limpar-cache', [VendaFacilController::class, 'limparCache']);
Route::post('/integracoes/vendafacil/desconectar', [VendaFacilController::class, 'desconectar']);

Route::get('/integracoes/webhooks', [WebhookController::class, 'index']);
Route::post('/integracoes/webhooks/{provider}', [WebhookController::class, 'receive']);
