<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unidade extends Model
{
    protected $table = 'unidades';

    protected $fillable = ['nome', 'endereco', 'cnpj', 'telefone', 'email', 'gerente_usuario_id', 'ativo'];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    public function mesas()
    {
        return $this->hasMany(Mesa::class);
    }

    public function reservas()
    {
        return $this->hasMany(ReservaMesa::class, 'unidade_id');
    }

    public function kanbanTasks()
    {
        return $this->hasMany(KanbanTask::class, 'unidade_id');
    }
}
