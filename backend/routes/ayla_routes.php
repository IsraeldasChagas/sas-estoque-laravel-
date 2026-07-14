<?php

/**
 * Rotas API — Ayla v1 (somente leitura).
 * Fluxo: Telegram/OpenClaw -> MCP -> /api/ayla/v1 -> Services SAS -> Banco.
 * Autenticação: Authorization: Bearer TOKEN (middleware ayla.token).
 * Rate limit: aplicado dentro do middleware (token + IP).
 */

use App\Http\Controllers\Api\AylaController;
use Illuminate\Support\Facades\Route;

Route::prefix('ayla/v1')->middleware('ayla.token')->group(function () {
    // Preflight CORS (o middleware responde ao OPTIONS antes da validação de token).
    Route::options('/{any}', fn () => response('', 204))->where('any', '.*');

    Route::get('/status', [AylaController::class, 'status']);
    Route::get('/unidades', [AylaController::class, 'unidades']);
    Route::get('/produtos', [AylaController::class, 'produtos']);
    Route::get('/produtos/abaixo-minimo', [AylaController::class, 'produtosAbaixoMinimo']);
    Route::get('/estoque', [AylaController::class, 'estoque']);
    Route::get('/estoque/movimentacoes', [AylaController::class, 'movimentacoes']);
    Route::get('/lotes/vencendo', [AylaController::class, 'lotesVencendo']);
    Route::get('/compras', [AylaController::class, 'compras']);
    Route::get('/fornecedores', [AylaController::class, 'fornecedores']);
    Route::get('/dashboard', [AylaController::class, 'dashboard']);
    Route::get('/kanban', [AylaController::class, 'kanban']);

    // Patrimônio (somente leitura). Rotas específicas antes de /{id}.
    Route::get('/patrimonio', [AylaController::class, 'patrimonio']);
    Route::get('/patrimonio/resumo', [AylaController::class, 'patrimonioResumo']);
    Route::get('/patrimonio/alertas', [AylaController::class, 'patrimonioAlertas']);
    Route::get('/patrimonio/unidade/{id}', [AylaController::class, 'patrimonioUnidade'])->where('id', '[0-9]+');
    Route::get('/patrimonio/{id}', [AylaController::class, 'patrimonioDetalhe'])->where('id', '[0-9]+');

    // Reservas de mesas (somente leitura). Rotas específicas antes de /{id}.
    Route::get('/reservas', [AylaController::class, 'reservas']);
    Route::get('/reservas/resumo', [AylaController::class, 'reservasResumo']);
    Route::get('/reservas/disponibilidade', [AylaController::class, 'reservasDisponibilidade']);
    Route::get('/reservas/alertas', [AylaController::class, 'reservasAlertas']);
    Route::get('/reservas/unidade/{id}', [AylaController::class, 'reservasUnidade'])->where('id', '[0-9]+');
    Route::get('/reservas/{id}', [AylaController::class, 'reservasDetalhe'])->where('id', '[0-9]+');

    Route::get('/relatorios/unidade/{id}', [AylaController::class, 'relatorioUnidade']);

    // Validação de acesso do gateway (VPS). Autenticação de usuário Telegram
    // vinculada a usuário SAS; não é uma operação de escrita de dados.
    Route::post('/acesso/validar', [AylaController::class, 'validarAcesso']);
});
