<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BoletoAnexo extends Model
{
    protected $table = 'boleto_anexos';

    protected $fillable = [
        'boleto_id',
        'tipo',
        'path',
        'nome',
        'tipo_arquivo',
    ];

    public function boleto()
    {
        return $this->belongsTo(Boleto::class);
    }
}
