<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Mensagem individual (usuário, assistente ou resultado de ferramenta). */
class AiMessage extends Model
{
    public $timestamps = false;

    protected $table = 'ai_messages';

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'tool_name',
        'tokens_input',
        'tokens_output',
        'cost_estimate',
        'created_at',
    ];

    protected $casts = [
        'conversation_id' => 'integer',
        'tokens_input' => 'integer',
        'tokens_output' => 'integer',
        'cost_estimate' => 'float',
        'created_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }
}
