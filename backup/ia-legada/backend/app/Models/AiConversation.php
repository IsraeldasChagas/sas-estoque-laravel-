<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Conversa do SAS IA vinculada ao usuário logado. */
class AiConversation extends Model
{
    protected $table = 'ai_conversations';

    protected $fillable = [
        'usuario_id',
        'unidade_id',
        'titulo',
    ];

    protected $casts = [
        'usuario_id' => 'integer',
        'unidade_id' => 'integer',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class, 'conversation_id')->orderBy('created_at');
    }
}
