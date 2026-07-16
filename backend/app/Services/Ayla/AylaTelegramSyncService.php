<?php

namespace App\Services\Ayla;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Comunicação segura com a VPS para sincronizar allowlist do OpenClaw.
 */
final class AylaTelegramSyncService
{
    /** @return array{success: bool, message?: string, data?: mixed} */
    public function adicionarAllowlist(string $telegramUserId): array
    {
        return $this->chamar('adicionar', $telegramUserId);
    }

    /** @return array{success: bool, message?: string, data?: mixed} */
    public function removerAllowlist(string $telegramUserId): array
    {
        return $this->chamar('remover', $telegramUserId);
    }

    /** @return array{success: bool, message?: string, data?: mixed} */
    public function sincronizar(): array
    {
        $url = $this->baseUrl().'/internal/allowlist/sincronizar';
        if ($url === '/internal/allowlist/sincronizar') {
            return ['success' => false, 'message' => 'AYLA_VPS_SYNC_URL não configurada.'];
        }

        try {
            $resp = Http::timeout(20)
                ->withToken($this->token())
                ->acceptJson()
                ->post($url, []);

            if ($resp->successful()) {
                return ['success' => true, 'message' => 'Sincronização solicitada.', 'data' => $resp->json()];
            }

            return ['success' => false, 'message' => 'Falha na sincronização (HTTP '.$resp->status().').'];
        } catch (\Throwable $e) {
            Log::warning('Ayla VPS sync falhou', ['erro' => $e->getMessage()]);

            return ['success' => false, 'message' => 'Não foi possível contatar a VPS para sincronização.'];
        }
    }

    /** @return array{success: bool, message?: string, data?: mixed} */
    private function chamar(string $acao, string $telegramUserId): array
    {
        $telegramUserId = trim($telegramUserId);
        if (! preg_match('/^[0-9]{3,32}$/', $telegramUserId)) {
            return ['success' => false, 'message' => 'Telegram User ID inválido.'];
        }

        $url = $this->baseUrl().'/internal/allowlist/'.$acao;
        if (str_starts_with($url, '/internal')) {
            // Sem VPS configurada: em dev, considera ok para não bloquear fluxo local.
            if (app()->environment(['local', 'testing'])) {
                return ['success' => true, 'message' => 'Sync ignorado (ambiente local sem VPS).'];
            }

            return ['success' => false, 'message' => 'AYLA_VPS_SYNC_URL não configurada.'];
        }

        try {
            $resp = Http::timeout(25)
                ->withToken($this->token())
                ->acceptJson()
                ->post($url, ['telegram_user_id' => $telegramUserId]);

            if ($resp->successful() && ($resp->json('success') === true || $resp->json('ok') === true)) {
                return ['success' => true, 'message' => 'Allowlist atualizada.', 'data' => $resp->json()];
            }

            $msg = $resp->json('message') ?? 'Falha ao atualizar allowlist na VPS.';

            return ['success' => false, 'message' => $msg];
        } catch (\Throwable $e) {
            Log::warning('Ayla allowlist '.$acao.' falhou', ['telegram_user_id' => $telegramUserId, 'erro' => $e->getMessage()]);

            return ['success' => false, 'message' => 'Não foi possível contatar a VPS.'];
        }
    }

    private function baseUrl(): string
    {
        return rtrim(trim((string) config('ayla.vps_sync_url', '')), '/');
    }

    private function token(): string
    {
        return trim((string) config('ayla.vps_sync_token', ''));
    }
}
