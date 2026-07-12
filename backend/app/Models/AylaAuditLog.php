<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoria das chamadas à API Ayla.
 * A gravação nunca pode quebrar a requisição (tudo dentro de try/catch).
 */
class AylaAuditLog extends Model
{
    protected $table = 'ayla_audit_logs';

    protected $fillable = [
        'user_id',
        'ip',
        'metodo',
        'rota',
        'acao',
        'payload',
        'resposta_resumo',
        'status',
        'http_status',
        'duracao_ms',
    ];

    protected $casts = [
        'payload' => 'array',
        'resposta_resumo' => 'array',
    ];

    /** Chaves sensíveis que nunca devem ser gravadas. */
    private const CHAVES_SENSIVEIS = [
        'authorization', 'token', 'password', 'senha', 'api_key',
        'apikey', 'secret', 'cpf', 'rg', 'senha_atual', 'nova_senha',
        'access_token', 'bearer',
    ];

    /**
     * Registra uma chamada de forma segura (nunca lança exceção).
     *
     * @param array<string, mixed> $data
     */
    public static function registrar(array $data): void
    {
        try {
            if (! Schema::hasTable('ayla_audit_logs')) {
                return;
            }

            $data['payload'] = self::sanitizar($data['payload'] ?? []);
            $data['resposta_resumo'] = self::sanitizar($data['resposta_resumo'] ?? []);

            self::create($data);
        } catch (\Throwable $e) {
            try {
                Log::warning('Falha ao gravar auditoria Ayla: '.$e->getMessage());
            } catch (\Throwable $ignored) {
                // Silencioso: auditoria nunca derruba a requisição.
            }
        }
    }

    /**
     * Remove/oculta chaves sensíveis recursivamente.
     *
     * @param mixed $valor
     * @return mixed
     */
    private static function sanitizar($valor)
    {
        if (! is_array($valor)) {
            return $valor;
        }

        $out = [];
        foreach ($valor as $chave => $item) {
            if (is_string($chave) && in_array(strtolower($chave), self::CHAVES_SENSIVEIS, true)) {
                $out[$chave] = '[REDACTED]';

                continue;
            }
            $out[$chave] = is_array($item) ? self::sanitizar($item) : $item;
        }

        return $out;
    }
}
