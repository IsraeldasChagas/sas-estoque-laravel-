<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vínculo de um usuário do SAS com o assistente Ayla (Telegram/OpenClaw).
 * O acesso da Ayla é SEMPRE atrelado a um usuário existente e ativo no SAS.
 */
class AylaUsuarioAutorizado extends Model
{
    protected $table = 'ayla_usuarios_autorizados';

    public const STATUS = ['pendente', 'ativo', 'bloqueado', 'revogado'];

    protected $fillable = [
        'usuario_id',
        'telegram_user_id',
        'telegram_username',
        'telegram_nome',
        'cargo',
        'unidades_permitidas',
        'modulos_permitidos',
        'pode_usar_texto',
        'pode_usar_audio',
        'pode_consultar_dados',
        'pode_executar_acoes',
        'status',
        'ultimo_acesso_em',
        'autorizado_por',
        'autorizado_em',
        'observacoes',
    ];

    protected $casts = [
        'unidades_permitidas' => 'array',
        'modulos_permitidos' => 'array',
        'pode_usar_texto' => 'boolean',
        'pode_usar_audio' => 'boolean',
        'pode_consultar_dados' => 'boolean',
        'pode_executar_acoes' => 'boolean',
        'ultimo_acesso_em' => 'datetime',
        'autorizado_em' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id', 'id');
    }
}
