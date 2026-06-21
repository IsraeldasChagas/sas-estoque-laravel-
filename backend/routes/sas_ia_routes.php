<?php

/**
 * Rotas API — Módulo SAS IA (agente inteligente)
 */

use App\Http\Controllers\SasIaController;
use Illuminate\Support\Facades\Route;

$sasIaCors = fn () => response()->json([])
    ->header('Access-Control-Allow-Origin', '*')
    ->header('Access-Control-Allow-Methods', 'GET, POST, DELETE, OPTIONS')
    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');

foreach ([
    '/sas-ia',
    '/sas-ia/chat',
    '/sas-ia/upload-documento',
    '/sas-ia/conversas',
    '/sas-ia/documentos',
    '/sas-ia/config',
    '/sas-ia/upload-foto',
] as $p) {
    Route::options($p, $sasIaCors);
}
Route::options('/sas-ia/conversas/{id}', $sasIaCors);

Route::get('/sas-ia', [SasIaController::class, 'index']);
Route::get('/sas-ia/config', [SasIaController::class, 'configShow']);
Route::post('/sas-ia/config', [SasIaController::class, 'configUpdate']);
Route::post('/sas-ia/upload-foto', [SasIaController::class, 'uploadFoto']);
Route::post('/sas-ia/chat', [SasIaController::class, 'chat']);
Route::post('/sas-ia/upload-documento', [SasIaController::class, 'uploadDocumento']);
Route::get('/sas-ia/conversas', [SasIaController::class, 'conversas']);
Route::get('/sas-ia/conversas/{id}', [SasIaController::class, 'conversaShow']);
Route::delete('/sas-ia/conversas/{id}', [SasIaController::class, 'conversaDestroy']);
Route::get('/sas-ia/documentos', [SasIaController::class, 'documentos']);
