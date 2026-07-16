<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autenticação do Telegram Auth Bridge (token dedicado, separado do AYLA_SAS_TOKEN).
 */
class CheckAylaBridgeToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = trim((string) config('ayla.bridge_token', ''));
        if ($expected === '') {
            return response()->json([
                'success' => false,
                'message' => 'Bridge não configurado no servidor.',
            ], 503);
        }

        $auth = $request->header('Authorization', '');
        $token = '';
        if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
            $token = trim($m[1]);
        }

        if ($token === '' || ! hash_equals($expected, $token)) {
            return response()->json([
                'success' => false,
                'message' => 'Não autorizado.',
            ], 401);
        }

        return $next($request);
    }
}
