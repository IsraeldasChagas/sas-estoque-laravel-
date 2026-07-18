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

    public const TIPO_DINHEIRO = 'dinheiro';

    public const TIPO_PIX = 'pix';

    public const TIPO_CREDITO = 'credito';

    public const TIPO_DEBITO = 'debito';

    public const TIPO_RESGATE_FIDELIDADE = 'resgate_fidelidade';

    /** @var array<string, string> */
    public const TIPOS = [
        self::TIPO_DINHEIRO => 'Dinheiro',
        self::TIPO_PIX => 'PIX',
        self::TIPO_CREDITO => 'Crédito',
        self::TIPO_DEBITO => 'Débito',
        self::TIPO_RESGATE_FIDELIDADE => 'Resgate cartão fidelidade',
    ];

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public static function rotuloCompleto(string $tipo, string $nome): string
    {
        $tipoLabel = self::TIPOS[$tipo] ?? $tipo;

        return $tipoLabel.' — '.$nome;
    }
}
