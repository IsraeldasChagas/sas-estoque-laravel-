<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImpostoAnexo extends Model
{
    protected $table = 'imposto_anexos';

    protected $fillable = [
        'imposto_id',
        'tipo',
        'path',
        'nome',
        'tipo_arquivo',
    ];

    public function imposto()
    {
        return $this->belongsTo(Imposto::class);
    }
}
