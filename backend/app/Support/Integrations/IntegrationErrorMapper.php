<?php

namespace App\Support\Integrations;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

final class IntegrationErrorMapper
{
    /**
     * @return array{code: string, message: string}
     */
    public static function fromHttpStatus(int $status, ?string $bodyMessage = null): array
    {
        $message = match ($status) {
            401 => 'Token inválido ou expirado.',
            403 => 'O token não possui permissão para esta operação.',
            404 => 'O endpoint da API não foi encontrado. Verifique a URL configurada.',
            408 => 'A API demorou mais que o limite configurado.',
            422 => 'A configuração enviada é inválida.',
            429 => 'Limite de requisições excedido.',
            default => ($status >= 500 && $status <= 599)
                ? 'O VendaFácil apresentou uma falha temporária.'
                : ($bodyMessage ?: 'Não foi possível concluir a operação.'),
        };

        $code = match ($status) {
            401 => 'INVALID_TOKEN',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            408 => 'TIMEOUT',
            422 => 'VALIDATION_ERROR',
            429 => 'RATE_LIMIT',
            default => ($status >= 500) ? 'SERVER_ERROR' : 'HTTP_ERROR',
        };

        return ['code' => $code, 'message' => $message];
    }

    /**
     * @return array{code: string, message: string}
     */
    public static function fromThrowable(\Throwable $e): array
    {
        $msg = strtolower($e->getMessage());

        if ($e instanceof ConnectionException) {
            if (str_contains($msg, 'timed out') || str_contains($msg, 'timeout')) {
                return ['code' => 'TIMEOUT', 'message' => 'A API demorou mais que o limite configurado.'];
            }
            if (str_contains($msg, 'could not resolve host') || str_contains($msg, 'getaddrinfo')) {
                return ['code' => 'DNS_ERROR', 'message' => 'O domínio configurado não pôde ser localizado.'];
            }
            if (str_contains($msg, 'ssl') || str_contains($msg, 'certificate')) {
                return ['code' => 'SSL_ERROR', 'message' => 'O certificado HTTPS da API não pôde ser validado.'];
            }
            if (str_contains($msg, 'connection refused')) {
                return ['code' => 'CONNECTION_REFUSED', 'message' => 'Não foi possível conectar ao servidor do VendaFácil.'];
            }

            return ['code' => 'CONNECTION_ERROR', 'message' => 'Não foi possível conectar ao servidor do VendaFácil.'];
        }

        if ($e instanceof RequestException && $e->response) {
            return self::fromHttpStatus($e->response->status());
        }

        return ['code' => 'UNKNOWN', 'message' => 'Não foi possível concluir a operação. Tente novamente.'];
    }

    public static function invalidJson(): array
    {
        return ['code' => 'INVALID_JSON', 'message' => 'A API respondeu em formato inesperado.'];
    }
}
