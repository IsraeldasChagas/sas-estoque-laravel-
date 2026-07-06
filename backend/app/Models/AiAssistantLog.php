<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiAssistantLog extends Model
{
    protected $table = 'ai_assistant_logs';

    protected $fillable = [
        'user_id',
        'origem',
        'comando',
        'acao',
        'payload',
        'resposta',
        'status',
    ];

    protected $casts = [
        'payload' => 'array',
        'resposta' => 'array',
    ];
}
