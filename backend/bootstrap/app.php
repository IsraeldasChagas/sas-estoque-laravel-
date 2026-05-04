<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Configuração de CORS para permitir requisições do frontend local
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
        $middleware->alias([
            'sas.usuario' => \App\Http\Middleware\EnsureSasUsuario::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'kanban-administrativo/tasks',
            'kanban-administrativo/tasks/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            if ($request->is('vagas/*/candidatar')) {
                return redirect()->back()->withErrors([
                    'upload' => 'O envio ficou grande demais para o servidor (limite de upload). Diminua o tamanho do PDF e da foto: comprima os arquivos ou envie imagens mais leves e tente novamente.',
                ])->withInput();
            }

            return null;
        });
    })->create();
