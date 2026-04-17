<?php

use App\Http\Controllers\KanbanTaskController;
use Illuminate\Support\Facades\Route;

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
