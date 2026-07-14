<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ação de escrita da Ayla aguardando confirmação explícita do usuário.
 */
class AylaAcaoPendente extends Model
{
    protected $table = 'ayla_acoes_pendentes';

    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_CONFIRMADA = 'confirmada';

    public const STATUS_EXECUTADA = 'executada';

    public const STATUS_CANCELADA = 'cancelada';

    public const STATUS_EXPIRADA = 'expirada';

    public const STATUS_ERRO = 'erro';

    public const STATUS = [
        self::STATUS_PENDENTE,
        self::STATUS_CONFIRMADA,
        self::STATUS_EXECUTADA,
        self::STATUS_CANCELADA,
        self::STATUS_EXPIRADA,
        self::STATUS_ERRO,
    ];

    public const EXPIRACAO_MINUTOS = 10;

    protected $fillable = [
        'usuario_id',
        'telegram_user_id',
        'canal',
        'modulo',
        'acao',
        'payload',
        'resumo',
        'status',
        'expira_em',
        'confirmado_em',
        'executado_em',
        'resultado',
    ];

    protected $casts = [
        'payload' => 'array',
        'resultado' => 'array',
        'expira_em' => 'datetime',
        'confirmado_em' => 'datetime',
        'executado_em' => 'datetime',
    ];

    public function estaPendente(): bool
    {
        return $this->status === self::STATUS_PENDENTE;
    }

    public function expirou(): bool
    {
        return $this->expira_em !== null && $this->expira_em->isPast();
    }
}
