<?php

namespace App\Support\Integrations;

use InvalidArgumentException;

final class IntegrationUrlValidator
{
    public static function normalize(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            throw new InvalidArgumentException('Informe a URL base da API.');
        }

        return rtrim($url, '/');
    }

    public static function validate(string $url, string $environment = 'homologation'): void
    {
        $url = self::normalize($url);

        if (! preg_match('#^https?://#i', $url)) {
            throw new InvalidArgumentException('A URL deve usar o protocolo HTTP ou HTTPS.');
        }

        if (preg_match('#^(file|ftp|gopher|data)://#i', $url)) {
            throw new InvalidArgumentException('Protocolo de URL não permitido.');
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            throw new InvalidArgumentException('URL da API inválida.');
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        if ($environment === 'production' && $scheme !== 'https') {
            throw new InvalidArgumentException('Em produção, a URL da API deve usar HTTPS.');
        }

        $host = strtolower($parts['host']);
        $blocked = array_map('strtolower', config('integrations.ssrf_blocked_hosts', []));
        if (in_array($host, $blocked, true)) {
            throw new InvalidArgumentException('O host configurado não é permitido.');
        }

        if (! config('integrations.ssrf_allow_private', false)) {
            self::assertNotPrivateHost($host, $parts['port'] ?? null);
        }
    }

    private static function assertNotPrivateHost(string $host, mixed $port): void
    {
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            throw new InvalidArgumentException('URLs locais não são permitidas neste ambiente.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new InvalidArgumentException('Endereços de rede privada não são permitidos.');
            }
        }

        $resolved = gethostbynamel($host);
        if (is_array($resolved)) {
            foreach ($resolved as $ip) {
                if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    throw new InvalidArgumentException('O domínio resolve para uma rede privada não permitida.');
                }
            }
        }
    }
}
