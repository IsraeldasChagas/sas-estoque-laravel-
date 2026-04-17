<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureSasUsuario
{
    public function handle(Request $request, Closure $next): Response
    {
        $uid = $request->header('X-Usuario-Id');
        if (! $uid || ! DB::table('usuarios')->where('id', $uid)->where('ativo', 1)->exists()) {
            return response()->json(['error' => 'Faça login novamente. Sessão expirada ou usuário não identificado.'], 401)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id, X-Device-Model, X-Device-Platform');
        }

        $response = $next($request);

        return $response
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id, X-Device-Model, X-Device-Platform');
    }
}
