<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AylaConvite extends Model
{
    protected $table = 'ayla_convites';

    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_USADO = 'usado';

    public const STATUS_EXPIRADO = 'expirado';

    public const STATUS_CANCELADO = 'cancelado';

    public const STATUS = [
        self::STATUS_PENDENTE,
        self::STATUS_USADO,
        self::STATUS_EXPIRADO,
        self::STATUS_CANCELADO,
    ];

    protected $fillable = [
        'ayla_usuario_autorizado_id',
        'usuario_id',
        'token_hash',
        'status',
        'expira_em',
        'usado_em',
        'cancelado_em',
        'telegram_user_id',
        'telegram_username',
        'telegram_nome',
        'telefone_telegram',
        'criado_por',
    ];

    protected function casts(): array
    {
        return [
            'expira_em' => 'datetime',
            'usado_em' => 'datetime',
            'cancelado_em' => 'datetime',
        ];
    }

    public function acesso(): BelongsTo
    {
        return $this->belongsTo(AylaUsuarioAutorizado::class, 'ayla_usuario_autorizado_id');
    }

    public function estaPendente(): bool
    {
        return $this->status === self::STATUS_PENDENTE && ! $this->expirou();
    }

    public function expirou(): bool
    {
        return $this->expira_em !== null && $this->expira_em->isPast();
    }
}
