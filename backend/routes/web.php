<?php

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
