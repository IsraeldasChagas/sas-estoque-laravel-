<?php

use App\Http\Controllers\Delivery\DeliveryAdicionalController;
use App\Http\Controllers\Delivery\DeliveryCatalogoController;
use App\Http\Controllers\Delivery\DeliveryCategoriaController;
use App\Http\Controllers\Delivery\DeliveryConfiguracaoController;
use App\Http\Controllers\Delivery\DeliveryDashboardController;
use App\Http\Controllers\Delivery\DeliveryEntregadorController;
use App\Http\Controllers\Delivery\DeliveryFreteController;
use App\Http\Controllers\Delivery\DeliveryPedidoController;
use App\Http\Controllers\Delivery\DeliveryProdutoController;
use Illuminate\Support\Facades\Route;

Route::prefix('delivery')->middleware('sas.usuario')->group(function () {
    Route::options('/{path?}', fn () => response('', 204))
        ->where('path', '.*');

    Route::get('/dashboard', DeliveryDashboardController::class)->name('delivery.dashboard');
    Route::get('/catalogo', DeliveryCatalogoController::class)->name('delivery.catalogo');

    Route::get('/categorias', [DeliveryCategoriaController::class, 'index']);
    Route::post('/categorias', [DeliveryCategoriaController::class, 'store']);
    Route::get('/categorias/{id}', [DeliveryCategoriaController::class, 'show'])->whereNumber('id');
    Route::put('/categorias/{id}', [DeliveryCategoriaController::class, 'update'])->whereNumber('id');
    Route::delete('/categorias/{id}', [DeliveryCategoriaController::class, 'destroy'])->whereNumber('id');

    Route::get('/produtos', [DeliveryProdutoController::class, 'index']);
    Route::post('/produtos', [DeliveryProdutoController::class, 'store']);
    Route::get('/produtos/{id}', [DeliveryProdutoController::class, 'show'])->whereNumber('id');
    Route::put('/produtos/{id}', [DeliveryProdutoController::class, 'update'])->whereNumber('id');
    Route::delete('/produtos/{id}', [DeliveryProdutoController::class, 'destroy'])->whereNumber('id');
    Route::post('/produtos/{id}/adicionais', [DeliveryProdutoController::class, 'syncAdicionais'])->whereNumber('id');

    Route::get('/adicionais', [DeliveryAdicionalController::class, 'index']);
    Route::post('/adicionais', [DeliveryAdicionalController::class, 'store']);
    Route::get('/adicionais/{id}', [DeliveryAdicionalController::class, 'show'])->whereNumber('id');
    Route::put('/adicionais/{id}', [DeliveryAdicionalController::class, 'update'])->whereNumber('id');
    Route::delete('/adicionais/{id}', [DeliveryAdicionalController::class, 'destroy'])->whereNumber('id');

    Route::get('/vitrine', [DeliveryConfiguracaoController::class, 'vitrineShow']);
    Route::put('/vitrine', [DeliveryConfiguracaoController::class, 'vitrineUpdate']);
    Route::get('/configuracoes', [DeliveryConfiguracaoController::class, 'show']);
    Route::put('/configuracoes', [DeliveryConfiguracaoController::class, 'update']);

    Route::get('/fretes/faixas', [DeliveryFreteController::class, 'index']);
    Route::post('/fretes/faixas', [DeliveryFreteController::class, 'store']);
    Route::get('/fretes/faixas/{id}', [DeliveryFreteController::class, 'show'])->whereNumber('id');
    Route::put('/fretes/faixas/{id}', [DeliveryFreteController::class, 'update'])->whereNumber('id');
    Route::delete('/fretes/faixas/{id}', [DeliveryFreteController::class, 'destroy'])->whereNumber('id');
    Route::post('/fretes/calcular', [DeliveryFreteController::class, 'calcular']);

    Route::get('/entregadores', [DeliveryEntregadorController::class, 'index']);
    Route::post('/entregadores', [DeliveryEntregadorController::class, 'store']);
    Route::get('/entregadores/{id}', [DeliveryEntregadorController::class, 'show'])->whereNumber('id');
    Route::put('/entregadores/{id}', [DeliveryEntregadorController::class, 'update'])->whereNumber('id');
    Route::delete('/entregadores/{id}', [DeliveryEntregadorController::class, 'destroy'])->whereNumber('id');

    Route::get('/pedidos', [DeliveryPedidoController::class, 'index']);
    Route::post('/pedidos', [DeliveryPedidoController::class, 'store']);
    Route::get('/pedidos/{id}', [DeliveryPedidoController::class, 'show'])->whereNumber('id');
    Route::put('/pedidos/{id}', [DeliveryPedidoController::class, 'update'])->whereNumber('id');
    Route::patch('/pedidos/{id}/status', [DeliveryPedidoController::class, 'status'])->whereNumber('id');
});
