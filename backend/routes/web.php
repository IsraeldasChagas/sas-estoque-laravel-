<?php

use App\Http\Controllers\Delivery\DeliveryCalcularEntregaController;
use App\Http\Controllers\Delivery\DeliveryPublicController;
use App\Http\Controllers\Delivery\DeliveryPublicEntregadorController;
use App\Http\Controllers\Delivery\DeliveryFidelidadePublicController;
use App\Http\Controllers\KanbanTaskController;
use App\Http\Controllers\Rh\RhPublicoController;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/dashboard');
});

Route::prefix('loja/{slug}')->name('delivery.public.')->group(function () {
    Route::get('/', [DeliveryPublicController::class, 'loja'])->name('store');
    Route::get('/produto/{id}', [DeliveryPublicController::class, 'produto'])->whereNumber('id')->name('product');
    Route::get('/fidelidade', [DeliveryFidelidadePublicController::class, 'show'])->name('fidelity');
    Route::get('/fidelidade/privacidade', [DeliveryFidelidadePublicController::class, 'privacidade'])->name('fidelity.privacy');
    Route::post('/fidelidade/aceitar-lgpd', [DeliveryFidelidadePublicController::class, 'aceitarLgpd'])
        ->middleware('throttle:20,1')->name('fidelity.lgpd');
    Route::post('/fidelidade/solicitar-codigo', [DeliveryFidelidadePublicController::class, 'solicitarCodigo'])
        ->middleware('throttle:12,1')->name('fidelity.request');
    Route::post('/fidelidade/reenviar-codigo', [DeliveryFidelidadePublicController::class, 'reenviarCodigo'])
        ->middleware('throttle:12,1')->name('fidelity.resend');
    Route::post('/fidelidade/cancelar-otp', [DeliveryFidelidadePublicController::class, 'cancelarOtp'])
        ->middleware('throttle:20,1')->name('fidelity.cancel');
    Route::post('/fidelidade/sair', [DeliveryFidelidadePublicController::class, 'sair'])
        ->middleware('throttle:20,1')->name('fidelity.logout');
    Route::post('/fidelidade/verificar-codigo', [DeliveryFidelidadePublicController::class, 'verificarCodigo'])
        ->middleware('throttle:30,1')->name('fidelity.verify');
    Route::post('/fidelidade/cadastro', [DeliveryFidelidadePublicController::class, 'cadastrar'])
        ->middleware('throttle:15,1')->name('fidelity.register');
    Route::get('/checkout', [DeliveryPublicController::class, 'checkout'])->name('checkout');
    Route::post('/frete', [DeliveryPublicController::class, 'frete'])->middleware('throttle:30,1')->name('freight');
    Route::post('/frete-resumo', [DeliveryPublicController::class, 'freteResumo'])->middleware('throttle:30,1')->name('freight.summary');
    Route::post('/checkout', [DeliveryPublicController::class, 'finalizar'])->middleware('throttle:10,1')->name('finish');
    Route::get('/sucesso/{codigo}/{token}', [DeliveryPublicController::class, 'sucesso'])
        ->where('token', '[a-f0-9]{64}')->name('success');
    Route::get('/pedido/{codigo}/{token}', [DeliveryPublicController::class, 'pedido'])
        ->where('token', '[a-f0-9]{64}')->name('order');
    Route::get('/entrega/{codigo}/{token}', [DeliveryPublicEntregadorController::class, 'show'])
        ->name('entregador.show');
    Route::post('/entrega/{codigo}/{token}', [DeliveryPublicEntregadorController::class, 'registrar'])
        ->middleware('throttle:30,1')->name('entregador.registrar');
});

Route::post('/api/calcular-entrega', DeliveryCalcularEntregaController::class)
    ->middleware('throttle:30,1')
    ->name('delivery.public.calcular-entrega');

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
Route::get('/imagens/favicon.png', function () {
    $path = base_path('../frontend/imagens/favicon.png');
    if (! is_file($path)) {
        abort(404);
    }

    return Response::file($path, [
        'Content-Type' => 'image/png',
        'Cache-Control' => 'public, max-age=86400',
    ]);
});

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

Route::get('/imagens/logo-docenorte.jpg', function () {
    $path = base_path('../frontend/imagens/logoDocenorte.jpg');
    if (! is_file($path)) {
        abort(404);
    }

    return Response::file($path, [
        'Content-Type' => 'image/jpeg',
        'Cache-Control' => 'public, max-age=86400',
    ]);
});

Route::get('/vagas', [RhPublicoController::class, 'indexVagas']);
Route::get('/vagas/qrcode', [RhPublicoController::class, 'qrcodeVagas']);
Route::get('/vagas/{slug}', [RhPublicoController::class, 'showVaga']);
Route::get('/vagas/{slug}/qrcode', [RhPublicoController::class, 'qrcodeVaga']);
Route::post('/vagas/{slug}/candidatar', [RhPublicoController::class, 'candidatar']);

Route::get('/documentacao/{token}', [RhPublicoController::class, 'documentacaoForm'])
    ->where('token', '[a-fA-F0-9]{64}');
Route::post('/documentacao/{token}', [RhPublicoController::class, 'documentacaoEnviar'])
    ->where('token', '[a-fA-F0-9]{64}');
