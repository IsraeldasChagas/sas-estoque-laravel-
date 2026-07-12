<?php

namespace App\Http\Middleware;

use App\Models\AylaAuditLog;
use App\Support\Ayla\AylaResponse;
use App\Support\Ayla\AylaSettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autenticação da API Ayla.
 *  - Authorization: Bearer TOKEN validado com hash_equals
 *  - integração desativada => 503
 *  - token ausente/ inválido => 401 (nunca grava o token)
 *  - rate limit por token+IP => 429
 */
class CheckAylaToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $acao = 'ayla.auth';

        if ($request->isMethod('OPTIONS')) {
            return AylaResponse::success($acao, 'OK');
        }

        if (! AylaSettings::ativo()) {
            return AylaResponse::error($acao, 'Integração Ayla desativada.', 'INTEGRATION_DISABLED', 503);
        }

        $expected = AylaSettings::tokenEfetivo();
        if ($expected === '') {
            return AylaResponse::error($acao, 'Token Ayla não configurado no servidor.', 'TOKEN_NOT_CONFIGURED', 503);
        }

        $received = trim((string) $request->bearerToken());
        if ($received === '' || ! hash_equals($expected, $received)) {
            $this->registrarTentativaInvalida($request);

            return AylaResponse::error($acao, 'Token inválido ou ausente. Use Authorization: Bearer TOKEN.', 'UNAUTHORIZED', 401);
        }

        // Rate limit por combinação token + IP (token nunca aparece em claro na chave).
        $limite = AylaSettings::rateLimit();
        $chave = 'ayla:'.hash('sha256', $received.'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($chave, $limite)) {
            $espera = RateLimiter::availableIn($chave);

            return AylaResponse::error($acao, 'Muitas requisições. Tente novamente em instantes.', 'RATE_LIMITED', 429, [
                'retry_after' => $espera,
            ]);
        }
        RateLimiter::hit($chave, 60);

        return $next($request);
    }

    private function registrarTentativaInvalida(Request $request): void
    {
        AylaAuditLog::registrar([
            'user_id' => null,
            'ip' => $request->ip(),
            'metodo' => $request->method(),
            'rota' => $request->path(),
            'acao' => 'ayla.auth.invalid',
            'payload' => [],
            'resposta_resumo' => ['message' => 'Token inválido ou ausente.'],
            'status' => 'erro',
            'http_status' => 401,
            'duracao_ms' => null,
        ]);
    }
}
