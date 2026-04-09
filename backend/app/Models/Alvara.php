<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Alvara extends Model
{
    protected $table = 'alvaras';

    protected $fillable = [
        'unidade_id',
        'tipo',
        'data_inicio',
        'data_vencimento',
        'valor_pago',
        'anexo_path',
        'anexo_nome',
        'anexo_tipo',
        'usuario_id',
    ];

    protected $casts = [
        'data_inicio' => 'date',
        'data_vencimento' => 'date',
        'valor_pago' => 'decimal:2',
    ];

    public function unidade()
    {
        return $this->belongsTo(Unidade::class);
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }
}

