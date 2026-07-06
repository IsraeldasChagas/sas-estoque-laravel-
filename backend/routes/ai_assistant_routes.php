<?php

/**
 * Rotas API — OpenClaw / Assistente IA (consultas e ações no estoque).
 * Autenticação: Authorization: Bearer TOKEN (middleware openclaw.token).
 */

use App\Http\Controllers\Api\AiAssistantController;
use Illuminate\Support\Facades\Route;

$iaCors = fn () => response()->json([])
    ->header('Access-Control-Allow-Origin', '*')
    ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');

foreach ([
    '/ia/estoque-baixo',
    '/ia/produtos-vencendo',
    '/ia/produto',
    '/ia/lancar-perda',
    '/ia/cadastrar-compra',
] as $p) {
    Route::options($p, $iaCors);
}
Route::options('/ia/relatorio-unidade/{id}', $iaCors);

Route::middleware('openclaw.token')->group(function () {
    Route::get('/ia/estoque-baixo', [AiAssistantController::class, 'estoqueBaixo']);
    Route::get('/ia/produtos-vencendo', [AiAssistantController::class, 'produtosVencendo']);
    Route::get('/ia/produto', [AiAssistantController::class, 'produto']);
    Route::get('/ia/relatorio-unidade/{id}', [AiAssistantController::class, 'relatorioUnidade']);
    Route::post('/ia/lancar-perda', [AiAssistantController::class, 'lancarPerda']);
    Route::post('/ia/cadastrar-compra', [AiAssistantController::class, 'cadastrarCompra']);
});
