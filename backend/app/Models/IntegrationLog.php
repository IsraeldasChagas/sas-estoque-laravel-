<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationLog extends Model
{
    public $timestamps = false;

    protected $table = 'integration_logs';

    protected $fillable = [
        'integration_id',
        'provider',
        'direction',
        'http_method',
        'endpoint',
        'response_time_ms',
        'http_status',
        'status',
        'message',
        'empresa_id',
        'unidade_id',
        'operation',
        'attempt_number',
        'ip',
        'usuario_id',
        'request_payload',
        'response_payload',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'created_at' => 'datetime',
            'response_time_ms' => 'integer',
            'http_status' => 'integer',
        ];
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    /** Remove chaves sensíveis antes de persistir. */
    public static function sanitizarPayload(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        $sensivel = ['token', 'bearer', 'authorization', 'secret', 'password', 'senha', 'api_key', 'webhook_secret'];
        $out = [];
        foreach ($payload as $key => $value) {
            $k = strtolower((string) $key);
            $bloqueado = false;
            foreach ($sensivel as $s) {
                if (str_contains($k, $s)) {
                    $bloqueado = true;
                    break;
                }
            }
            if ($bloqueado) {
                $out[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $out[$key] = self::sanitizarPayload($value);
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
