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
        unset($payload['_ref']);

        try {
            $send = $this->client->enviarNfce($ref, $payload);
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

    public function emitirNfe(int $empresaId, array $payload): array
    {
        return [
            'success' => false,
            'error' => 'Emissão NF-e ainda não implementada. Use NFC-e (PDV) ou habilite emitir_nfe_pedido em fase posterior.',
        ];
    }

    /** @param array{http_status: int, body: array<string, mixed>, ok: bool} $http */
    /** @return array{success: bool, ref?: string, status?: string, chave?: string, numero?: string, serie?: string, danfe_url?: string, xml?: string, error?: string} */
    private function normalizarResposta(string $ref, array $http): array
    {
        $body = $http['body'] ?? [];
        $status = strtolower((string) ($body['status'] ?? ''));
        $chave = $body['chave_nfe'] ?? $body['chave'] ?? null;
        $numero = isset($body['numero']) ? (string) $body['numero'] : null;
        $serie = isset($body['serie']) ? (string) $body['serie'] : null;
        $danfe = $body['caminho_danfe'] ?? $body['url_danfe'] ?? $body['qrcode_url'] ?? null;
        $mensagem = $body['mensagem_sefaz'] ?? $body['mensagem'] ?? null;

        $autorizado = in_array($status, ['autorizado', 'autorizada'], true);
        $processando = str_contains($status, 'processando');
        $erro = str_contains($status, 'erro') || str_contains($status, 'rejeit');

        if ($autorizado) {
            return [
                'success' => true,
                'ref' => $ref,
                'status' => $status,
                'chave' => $chave ? (string) $chave : null,
                'numero' => $numero,
                'serie' => $serie,
                'danfe_url' => $danfe ? (string) $danfe : null,
                'xml' => isset($body['caminho_xml_nota_fiscal']) ? (string) $body['caminho_xml_nota_fiscal'] : null,
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
        $st = (string) ($result['status'] ?? '');

        return str_contains($st, 'processando') || $st === '';
    }
}
