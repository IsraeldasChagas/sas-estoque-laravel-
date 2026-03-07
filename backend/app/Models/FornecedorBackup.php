<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FornecedorBackup extends Model
{
    protected $table = 'fornecedores_backup';

    protected $fillable = [
        'fornecedor_id_original',
        'dados_fornecedor',
        'data_backup',
        'usuario_exclusao',
        'motivo_exclusao',
        'restaurado',
    ];

    protected $casts = [
        'dados_fornecedor' => 'array',
        'data_backup' => 'datetime',
        'restaurado' => 'boolean',
    ];
}
