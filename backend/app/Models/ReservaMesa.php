<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservaMesa extends Model
{
    protected $table = 'reservas_mesas';

    protected $fillable = [
        'unidade_id',
        'mesa_id',
        'usuario_id',
        'nome_cliente',
        'telefone_cliente',
        'data_reserva',
        'hora_reserva',
        'qtd_pessoas',
        'status',
        'observacao',
    ];

    protected $casts = [
        'data_reserva' => 'date',
        'qtd_pessoas' => 'integer',
    ];

    public const STATUS_PENDENTE = 'pendente';
    public const STATUS_CONFIRMADA = 'confirmada';
    public const STATUS_CANCELADA = 'cancelada';
    public const STATUS_CLIENTE_CHEGOU = 'cliente_chegou';
    public const STATUS_NO_SHOW = 'no_show';
    public const STATUS_FINALIZADA = 'finalizada';

    public function unidade()
    {
        return $this->belongsTo(Unidade::class);
    }

    public function mesa()
    {
        return $this->belongsTo(Mesa::class);
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }
}
