<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KanbanTask extends Model
{
    protected $table = 'kanban_tasks';

    protected $fillable = [
        'titulo',
        'descricao',
        'unidade_id',
        'setor',
        'responsavel',
        'prioridade',
        'status',
        'prazo',
        'observacoes',
    ];

    protected $casts = [
        'prazo' => 'date',
    ];

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class, 'unidade_id');
    }
}
