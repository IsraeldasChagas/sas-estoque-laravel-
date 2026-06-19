<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Manual, procedimento ou regra interna consultável pelo agente. */
class AiDocument extends Model
{
    protected $table = 'ai_documents';

    protected $fillable = [
        'titulo',
        'tipo',
        'conteudo_texto',
        'arquivo_path',
        'ativo',
        'usuario_id',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'usuario_id' => 'integer',
    ];
}
