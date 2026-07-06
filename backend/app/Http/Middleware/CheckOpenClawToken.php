<?php

namespace App\Http\Middleware;

use App\Support\OpenClaw\OpenClawSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckOpenClawToken
{
    private function cors(Response $response): Response
    {
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization');

        return $response;
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('OPTIONS')) {
            return $this->cors(response('', 204));
        }

        if (! OpenClawSettings::ativo()) {
            return $this->cors(response()->json([
                'success' => false,
                'message' => 'Integração OpenClaw desativada.',
                'data' => [],
            ], 503));
        }

        $expected = OpenClawSettings::tokenEfetivo();
        if ($expected === '') {
            return $this->cors(response()->json([
                'success' => false,
                'message' => 'Token OpenClaw não configurado no servidor.',
                'data' => [],
            ], 503));
        }

        $received = trim((string) $request->bearerToken());
        if ($received === '' || ! hash_equals($expected, $received)) {
            return $this->cors(response()->json([
                'success' => false,
                'message' => 'Token inválido ou ausente. Use Authorization: Bearer TOKEN.',
                'data' => [],
            ], 401));
        }

        $response = $next($request);

        return $this->cors($response);
    }
}
