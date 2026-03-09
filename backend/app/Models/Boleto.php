<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Boleto extends Model
{
    protected $table = 'boletos';

    protected $fillable = [
        'unidade_id',
        'fornecedor_id',
        'fornecedor',
        'nome_fornecedor',
        'cnpj_fornecedor',
        'descricao',
        'data_vencimento',
        'valor',
        'categoria',
        'status',
        'data_pagamento',
        'valor_pago',
        'juros_multa',
        'observacoes',
        'numero_boleto',
        'nome_pagador',
        'whatsapp_pagador',
        'usuario_id',
        'anexo_path',
        'anexo_nome',
        'anexo_tipo',
        'is_recorrente',
        'meses_recorrencia',
        'grupo_recorrencia'
    ];

    protected $casts = [
        'data_vencimento' => 'date',
        'data_pagamento' => 'date',
        'valor' => 'decimal:2',
        'valor_pago' => 'decimal:2',
        'juros_multa' => 'decimal:2',
        'is_recorrente' => 'boolean',
        'meses_recorrencia' => 'integer',
    ];

    // Relacionamento com Unidade
    public function unidade()
    {
        return $this->belongsTo(Unidade::class);
    }

    // Relacionamento com Usuario (quem cadastrou)
    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }
}
