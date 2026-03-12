<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mesa extends Model
{
    protected $table = 'mesas';

    protected $fillable = [
        'unidade_id',
        'numero_mesa',
        'nome_mesa',
        'capacidade',
        'localizacao',
        'status',
        'observacao',
        'ativo',
    ];

    protected $casts = [
        'capacidade' => 'integer',
        'ativo' => 'boolean',
    ];

    public const STATUS_LIVRE = 'livre';
    public const STATUS_RESERVADA = 'reservada';
    public const STATUS_AGUARDANDO_CLIENTE = 'aguardando_cliente';
    public const STATUS_OCUPADA = 'ocupada';
    public const STATUS_BLOQUEADA = 'bloqueada';

    public function unidade()
    {
        return $this->belongsTo(Unidade::class);
    }

    public function reservas()
    {
        return $this->hasMany(ReservaMesa::class);
    }

    public function reservaAtivaNaData($data, $hora = null)
    {
        $query = $this->reservas()
            ->where('data_reserva', $data)
            ->whereNotIn('status', ['cancelada', 'no_show', 'finalizada']);

        if ($hora) {
            $query->where('hora_reserva', $hora);
        }

        return $query->first();
    }
}
