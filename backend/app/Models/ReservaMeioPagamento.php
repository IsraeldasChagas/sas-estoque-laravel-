<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservaMeioPagamento extends Model
{
    protected $table = 'reserva_meios_pagamento';

    protected $fillable = [
        'unidade_id',
        'tipo',
        'nome',
        'ativo',
        'ordem',
    ];

    protected $casts = [
        'unidade_id' => 'integer',
        'ativo' => 'boolean',
        'ordem' => 'integer',
    ];

    public const TIPO_PIX = 'pix';

    public const TIPO_MAQUININHA = 'maquininha';

    public const TIPO_DINHEIRO = 'dinheiro';

    /** @var array<string, string> */
    public const TIPOS = [
        self::TIPO_PIX => 'PIX',
        self::TIPO_MAQUININHA => 'Maquininha',
        self::TIPO_DINHEIRO => 'Dinheiro',
    ];

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }
}
