<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Imposto extends Model
{
    protected $table = 'impostos';

    protected $fillable = [
        'unidade_id',
        'boleto_id',
        'tipo_imposto',
        'descricao',
        'orgao',
        'competencia',
        'numero_documento',
        'data_vencimento',
        'valor',
        'status',
        'observacoes',
        'usuario_id',
    ];

    protected $casts = [
        'data_vencimento' => 'date',
        'valor' => 'decimal:2',
    ];

    public function unidade()
    {
        return $this->belongsTo(Unidade::class);
    }

    public function boleto()
    {
        return $this->belongsTo(Boleto::class);
    }

    public function anexos()
    {
        return $this->hasMany(ImpostoAnexo::class)->orderBy('id');
    }

    public function statusAberto(): string
    {
        if ($this->data_vencimento && $this->data_vencimento->lt(now()->startOfDay())) {
            return 'VENCIDO';
        }

        return 'A_VENCER';
    }

    public function sincronizarComBoleto(?Boleto $boleto = null): void
    {
        $boleto = $boleto ?: ($this->boleto_id ? Boleto::find($this->boleto_id) : null);
        if (! $boleto) {
            if (! in_array($this->status, ['PAGO', 'CANCELADO'], true)) {
                $this->update(['status' => $this->statusAberto()]);
            }

            return;
        }

        $this->update([
            'boleto_id' => $boleto->id,
            'status' => match ($boleto->status) {
                'PAGO' => 'PAGO',
                'CANCELADO' => 'CANCELADO',
                default => $this->statusAberto(),
            },
        ]);
    }

    public static function sincronizarDeBoleto(Boleto $boleto): void
    {
        if (! $boleto->imposto_id) {
            return;
        }

        $imposto = self::find($boleto->imposto_id);
        if ($imposto) {
            $imposto->sincronizarComBoleto($boleto);
        }
    }
}
