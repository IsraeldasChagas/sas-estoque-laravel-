<?php

use App\Http\Controllers\KanbanTaskController;
use App\Http\Controllers\Rh\RhPublicoController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Response;

Route::get('/', function () {
    return redirect('/dashboard');
});

Route::get('/dashboard', function () {
    return view('dashboard.index');
});

Route::get('/carteiras', function () {
    return view('carteiras.index');
});

Route::get('/relatorios', function () {
    return view('relatorios.index');
});

Route::get('/kanban-administrativo', [KanbanTaskController::class, 'showBoard'])
    ->name('kanban.administrativo');

Route::middleware(['web', 'sas.usuario'])->prefix('kanban-administrativo')->group(function () {
    Route::get('/tasks', [KanbanTaskController::class, 'index'])->name('kanban.web.tasks.index');
    Route::post('/tasks', [KanbanTaskController::class, 'store'])->name('kanban.web.tasks.store');
    Route::put('/tasks/{task}', [KanbanTaskController::class, 'update'])->name('kanban.web.tasks.update');
    Route::delete('/tasks/{task}', [KanbanTaskController::class, 'destroy'])->name('kanban.web.tasks.destroy');
    Route::patch('/tasks/{task}/status', [KanbanTaskController::class, 'updateStatus'])->name('kanban.web.tasks.updateStatus');
});

// ============================================
// RH (Recrutamento) - Link público de vagas
// ============================================
Route::get('/imagens/logosemfundo.png', function () {
    $path = base_path('../frontend/imagens/logosemfundo.png');
    if (! is_file($path)) {
        abort(404);
    }

    return Response::file($path, [
        'Content-Type' => 'image/png',
        'Cache-Control' => 'public, max-age=86400',
    ]);
});

Route::get('/imagens/logo-docemango.jpg', function () {
    $path = base_path('../frontend/imagens/LogoDoceMango.jpg');
    if (! is_file($path)) {
        abort(404);
    }

    return Response::file($path, [
        'Content-Type' => 'image/jpeg',
        'Cache-Control' => 'public, max-age=86400',
    ]);
});

Route::get('/vagas', [RhPublicoController::class, 'indexVagas']);
Route::get('/vagas/{slug}', [RhPublicoController::class, 'showVaga']);
Route::get('/vagas/{slug}/qrcode', [RhPublicoController::class, 'qrcodeVaga']);
Route::post('/vagas/{slug}/candidatar', [RhPublicoController::class, 'candidatar']);
