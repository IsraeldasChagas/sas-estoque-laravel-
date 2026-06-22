<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiAgent extends Model
{
    protected $table = 'ai_agents';

    protected $fillable = [
        'name',
        'role',
        'description',
        'system_prompt',
        'avatar',
        'model',
        'temperature',
        'is_active',
    ];

    protected $casts = [
        'temperature' => 'float',
        'is_active' => 'boolean',
    ];
}
