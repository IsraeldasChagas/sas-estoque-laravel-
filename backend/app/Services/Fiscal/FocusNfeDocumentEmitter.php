<?php

namespace App\Services\Fiscal;

use App\Contracts\Fiscal\FiscalDocumentEmitterInterface;
use App\Models\FiscalEmissaoConfig;
use App\Support\FiscalEmissaoConfigSupport;
use App\Support\FocusNfcePayloadBuilder;

final class FocusNfeDocumentEmitter implements FiscalDocumentEmitterInterface
{
    public function __construct(
        private readonly FocusNfeClient $client,
    ) {}

    /** @param array<string, mixed> $payload */
    public function emitirNfce(int $empresaId, array $payload): array
    {
        $ref = (string) ($payload['_ref'] ?? ('nfce-' . $empresaId . '-' . uniqid('', true)));

        $contingencia = ! empty($payload['_contingencia_offline']);
        unset($payload['_contingencia_offline'], $payload['_ref']);

        try {
            $send = $this->client->enviarNfce($ref, $payload, $contingencia);
            $result = $this->normalizarResposta($ref, $send);
            if ($this->deveConsultar($result)) {
                for ($i = 0; $i < 8; $i++) {
                    usleep(400000);
                    $get = $this->client->consultarNfce($ref);
                    $result = $this->normalizarResposta($ref, $get);
                    if (! $this->deveConsultar($result)) {
                        break;
                    }
                }
            }

            return $result;
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'ref' => $ref,
            ];
        }
    }

    public function consultarNfce(string $ref): array
    {
        $get = $this->client->consultarNfce($ref);

        return $this->normalizarResposta($ref, $get);
    }

    public function cancelarNfce(string $ref, string $justificativa): array
    {
        try {
            $send = $this->client->cancelarNfce($ref, $justificativa);
            $body = is_array($send['body'] ?? null) ? $send['body'] : [];
            $status = strtolower((string) ($body['status'] ?? ''));
            $mensagem = (string) ($body['mensagem_sefaz'] ?? $body['mensagem'] ?? $body['erro'] ?? '');
            $cancelado = ($send['ok'] ?? false)
                && (str_contains($status, 'cancel') || str_contains(strtolower($mensagem), 'cancel'));

            if ($cancelado && ! str_contains($status, 'erro') && ! str_contains($status, 'rejeit')) {
                return [
                    'success' => true,
                    'ref' => $ref,
                    'status' => $status !== '' ? $status : 'cancelado',
                    'mensagem' => $mensagem !== '' ? $mensagem : 'NFC-e cancelada.',
                ];
            }

            $errText = $mensagem
                ?: ($body['erros'][0]['mensagem'] ?? null)
                ?: ('HTTP '.($send['http_status'] ?? '?'));

            return [
                'success' => false,
                'ref' => $ref,
                'status' => $status ?: 'erro',
                'error' => is_string($errText) ? $errText : json_encode($errText),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'ref' => $ref,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function inutilizarNfce(array $payload): array
    {
        try {
            $send = $this->client->inutilizarNfce($payload);
            $body = is_array($send['body'] ?? null) ? $send['body'] : [];
            $status = strtolower((string) ($body['status'] ?? ''));
            $ok = ($send['ok'] ?? false) && (str_contains($status, 'autoriz') || str_contains($status, 'inutiliz') || ($send['http_status'] ?? 0) === 200);
            $mensagem = (string) ($body['mensagem_sefaz'] ?? $body['mensagem'] ?? '');

            if ($ok && ! str_contains($status, 'erro') && ! str_contains($status, 'rejeit')) {
                return [
                    'success' => true,
                    'status' => $status !== '' ? $status : 'inutilizado',
                    'mensagem' => $mensagem !== '' ? $mensagem : 'Numeração inutilizada.',
                ];
            }

            $errText = $mensagem
                ?: ($body['erros'][0]['mensagem'] ?? null)
                ?: ('HTTP '.($send['http_status'] ?? '?'));

            return [
                'success' => false,
                'status' => $status ?: 'erro',
                'error' => is_string($errText) ? $errText : json_encode($errText),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function emitirNfe(int $empresaId, array $payload): array
    {
        $ref = (string) ($payload['_ref'] ?? ('nfe-' . $empresaId . '-' . uniqid('', true)));
        unset($payload['_ref']);

        try {
            $send = $this->client->enviarNfe($ref, $payload);
            $result = $this->normalizarResposta($ref, $send);
            if ($this->deveConsultar($result)) {
                for ($i = 0; $i < 8; $i++) {
                    usleep(400000);
                    $get = $this->client->consultarNfe($ref);
                    $result = $this->normalizarResposta($ref, $get);
                    if (! $this->deveConsultar($result)) {
                        break;
                    }
                }
            }

            return $result;
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'ref' => $ref,
            ];
        }
    }

    /** @param array{http_status: int, body: array<string, mixed>, ok: bool} $http */
    /** @return array{success: bool, ref?: string, status?: string, chave?: string, numero?: string, serie?: string, danfe_url?: string, xml?: string, error?: string} */
    private function normalizarResposta(string $ref, array $http): array
    {
        $body = $http['body'] ?? [];
        $status = strtolower((string) ($body['status'] ?? ''));
        $chave = $body['chave_nfe'] ?? $body['chave'] ?? null;
        if (is_string($chave)) {
            $digits = preg_replace('/\D+/', '', $chave);
            if (is_string($digits) && strlen($digits) >= 44) {
                $chave = substr($digits, -44);
            }
        }
        $numero = isset($body['numero']) ? (string) $body['numero'] : null;
        $serie = isset($body['serie']) ? (string) $body['serie'] : null;
        $danfe = $body['caminho_danfe'] ?? $body['url_danfe'] ?? $body['qrcode_url'] ?? null;
        $mensagem = $body['mensagem_sefaz'] ?? $body['mensagem'] ?? null;

        $autorizado = in_array($status, ['autorizado', 'autorizada'], true);
        $processando = str_contains($status, 'processando');
        $erro = str_contains($status, 'erro') || str_contains($status, 'rejeit');

        $contingencia = ! empty($body['contingencia_offline']);
        $efetivada = ! empty($body['contingencia_offline_efetivada']);

        if ($autorizado || $contingencia) {
            return [
                'success' => true,
                'ref' => $ref,
                'status' => $contingencia && ! $efetivada ? 'contingencia' : $status,
                'chave' => $chave ? (string) $chave : null,
                'numero' => $numero,
                'serie' => $serie,
                'danfe_url' => $danfe ? (string) $danfe : null,
                'xml' => isset($body['caminho_xml_nota_fiscal']) ? (string) $body['caminho_xml_nota_fiscal'] : null,
                'qrcode_url' => isset($body['qrcode_url']) ? (string) $body['qrcode_url'] : null,
                'url_consulta_nf' => isset($body['url_consulta_nf']) ? (string) $body['url_consulta_nf'] : null,
                'contingencia_offline' => $contingencia,
                'contingencia_offline_efetivada' => $efetivada,
            ];
        }

        if ($processando) {
            return [
                'success' => false,
                'ref' => $ref,
                'status' => $status,
                'error' => 'Processando autorização na SEFAZ…',
            ];
        }

        $errText = $mensagem
            ?? ($body['mensagem_sefaz'] ?? null)
            ?? ($body['erros'][0]['mensagem'] ?? null)
            ?? ('HTTP ' . ($http['http_status'] ?? '?'));

        return [
            'success' => false,
            'ref' => $ref,
            'status' => $status ?: ($erro ? 'erro_autorizacao' : 'desconhecido'),
            'error' => is_string($errText) ? $errText : json_encode($errText),
        ];
    }

    /** @param array{success: bool, status?: string} $result */
    private function deveConsultar(array $result): bool
    {
        if ($result['success'] ?? false) {
            return false;
        }
        $blob = strtolower((string) ($result['error'] ?? '').' '.(string) ($result['status'] ?? ''));
        if (preg_match('/\b(108|109)\b/', $blob) || str_contains($blob, 'paralisado') || str_contains($blob, 'timeout')) {
            return false;
        }
        $st = (string) ($result['status'] ?? '');

        return str_contains($st, 'processando') || $st === '';
    }
}
