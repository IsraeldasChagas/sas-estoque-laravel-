<?php

use App\Http\Controllers\AiAgentController;
use Illuminate\Support\Facades\Route;

$cors = fn () => response()->json([])
    ->header('Access-Control-Allow-Origin', '*')
    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');

foreach ([
    '/ai-agents',
    '/ai-agents/active',
    '/ai-agents/modules',
    '/ai-agents/{id}',
    '/ai-agents/{id}/toggle-active',
] as $p) {
    Route::options($p, $cors);
}

Route::get('/ai-agents/active', [AiAgentController::class, 'active']);
Route::get('/ai-agents/modules', [AiAgentController::class, 'moduleBindings']);
Route::put('/ai-agents/modules', [AiAgentController::class, 'updateModuleBindings']);
Route::get('/ai-agents', [AiAgentController::class, 'index']);
Route::post('/ai-agents', [AiAgentController::class, 'store']);
Route::get('/ai-agents/{id}', [AiAgentController::class, 'show']);
Route::post('/ai-agents/{id}', [AiAgentController::class, 'update']);
Route::put('/ai-agents/{id}', [AiAgentController::class, 'update']);
Route::delete('/ai-agents/{id}', [AiAgentController::class, 'destroy']);
Route::post('/ai-agents/{id}/toggle-active', [AiAgentController::class, 'toggleActive']);
