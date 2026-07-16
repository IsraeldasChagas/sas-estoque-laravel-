<?php

namespace App\Support\Integrations;

final class IntegrationStatus
{
    public const NOT_CONFIGURED = 'not_configured';

    public const CONFIGURED = 'configured';

    public const CONNECTED = 'connected';

    public const DEGRADED = 'degraded';

    public const DISCONNECTED = 'disconnected';

    public const AUTHENTICATION_ERROR = 'authentication_error';

    public const CONNECTION_ERROR = 'connection_error';

    public const DISABLED = 'disabled';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::NOT_CONFIGURED,
            self::CONFIGURED,
            self::CONNECTED,
            self::DEGRADED,
            self::DISCONNECTED,
            self::AUTHENTICATION_ERROR,
            self::CONNECTION_ERROR,
            self::DISABLED,
        ];
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::CONNECTED => 'Online',
            self::DEGRADED => 'Instável',
            self::AUTHENTICATION_ERROR => 'Token inválido',
            self::CONNECTION_ERROR => 'Offline',
            self::DISABLED => 'Desativado',
            self::CONFIGURED => 'Configurado',
            self::DISCONNECTED => 'Desconectado',
            default => 'Não configurado',
        };
    }
}
