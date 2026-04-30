<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureSasUsuario
{
    private function aplicarCors(Response $response): Response
    {
        // Importante: algumas respostas (ex.: PDF via stream) são Symfony Response e não suportam ->header() fluente.
        // Por isso aplicamos CORS via $response->headers->set(...), que funciona para JSON e stream igualmente.
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id, X-Device-Model, X-Device-Platform');

        return $response;
    }

    public function handle(Request $request, Closure $next): Response
    {
        // Preflight CORS: navegador não envia X-Usuario-Id no OPTIONS.
        if ($request->isMethod('OPTIONS')) {
            return $this->aplicarCors(response('', 204));
        }

        $uid = $request->header('X-Usuario-Id');
        if (! $uid || ! DB::table('usuarios')->where('id', $uid)->where('ativo', 1)->exists()) {
            return $this->aplicarCors(
                response()->json(['error' => 'Faça login novamente. Sessão expirada ou usuário não identificado.'], 401)
            );
        }

        $response = $next($request);

        return $this->aplicarCors($response);
    }
}
