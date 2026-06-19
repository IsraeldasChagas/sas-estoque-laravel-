<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Auditoria de cada ferramenta executada pelo agente SAS IA. */
class AiToolLog extends Model
{
    public $timestamps = false;

    protected $table = 'ai_tool_logs';

    protected $fillable = [
        'conversation_id',
        'message_id',
        'usuario_id',
        'tool_name',
        'params_json',
        'result_summary',
        'success',
        'duration_ms',
        'created_at',
    ];

    protected $casts = [
        'params_json' => 'array',
        'success' => 'boolean',
        'duration_ms' => 'integer',
        'created_at' => 'datetime',
    ];
}
