<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Integration extends Model
{
    protected $table = 'integrations';

    protected $fillable = [
        'provider',
        'name',
        'api_url',
        'bearer_token',
        'environment',
        'empresa_external_id',
        'unidade_mappings',
        'timeout_seconds',
        'retry_count',
        'webhook_secret',
        'enabled_resources',
        'connection_status',
        'last_sync_at',
        'last_error',
        'last_response_time_ms',
        'api_version',
        'is_active',
        'empresa_id',
        'unidade_id',
        'integration_status',
        'last_successful_connection_at',
        'consecutive_failures',
        'empresa_external_name',
        'observacoes',
        'config_json',
    ];

    protected $hidden = [
        'bearer_token',
        'webhook_secret',
    ];

    protected function casts(): array
    {
        return [
            'bearer_token' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'unidade_mappings' => 'array',
            'enabled_resources' => 'array',
            'config_json' => 'array',
            'is_active' => 'boolean',
            'last_sync_at' => 'datetime',
            'last_successful_connection_at' => 'datetime',
            'timeout_seconds' => 'integer',
            'retry_count' => 'integer',
            'consecutive_failures' => 'integer',
        ];
    }

    public function logs(): HasMany
    {
        return $this->hasMany(IntegrationLog::class);
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(IntegrationMapping::class);
    }

    public function webhooks(): HasMany
    {
        return $this->hasMany(IntegrationWebhook::class);
    }

    public static function mascararToken(?string $token): string
    {
        $token = trim((string) $token);
        if ($token === '') {
            return '';
        }
        if (strlen($token) <= 8) {
            return str_repeat('•', strlen($token));
        }

        return substr($token, 0, 4).'••••'.substr($token, -4);
    }

    /** @return array<string, mixed> */
    public function paraPainel(bool $incluirTokenMascarado = true): array
    {
        $token = $this->bearer_token;
        $secret = $this->webhook_secret;

        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'name' => $this->name,
            'api_url' => $this->api_url,
            'bearer_token_mascarado' => $incluirTokenMascarado ? self::mascararToken($token) : '',
            'bearer_token_configurado' => $token !== null && $token !== '',
            'environment' => $this->environment,
            'empresa_external_id' => $this->empresa_external_id,
            'empresa_external_name' => $this->empresa_external_name,
            'unidade_mappings' => $this->unidade_mappings ?? [],
            'timeout_seconds' => $this->timeout_seconds,
            'retry_count' => $this->retry_count,
            'webhook_secret_mascarado' => $incluirTokenMascarado ? self::mascararToken($secret) : '',
            'webhook_secret_configurado' => $secret !== null && $secret !== '',
            'enabled_resources' => $this->enabled_resources ?? [],
            'connection_status' => $this->connection_status,
            'integration_status' => $this->integration_status ?? 'not_configured',
            'integration_status_label' => \App\Support\Integrations\IntegrationStatus::label($this->integration_status ?? 'not_configured'),
            'last_sync_at' => $this->last_sync_at?->toIso8601String(),
            'last_successful_connection_at' => $this->last_successful_connection_at?->toIso8601String(),
            'last_error' => $this->last_error,
            'last_response_time_ms' => $this->last_response_time_ms,
            'consecutive_failures' => $this->consecutive_failures ?? 0,
            'api_version' => $this->api_version,
            'is_active' => $this->is_active,
            'empresa_id' => $this->empresa_id,
            'unidade_id' => $this->unidade_id,
            'config_json' => $this->config_json ?? [],
            'observacoes' => $this->observacoes,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function toProviderArray(): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'api_url' => $this->api_url,
            'bearer_token' => $this->bearer_token,
            'webhook_secret' => $this->webhook_secret,
            'environment' => $this->environment,
            'timeout_seconds' => $this->timeout_seconds,
            'retry_count' => $this->retry_count,
            'is_active' => $this->is_active,
            'config_json' => $this->config_json ?? [],
            'enabled_resources' => $this->enabled_resources ?? [],
            'empresa_external_id' => $this->empresa_external_id,
            'empresa_external_name' => $this->empresa_external_name,
            'api_version' => $this->api_version,
        ];
    }
}
