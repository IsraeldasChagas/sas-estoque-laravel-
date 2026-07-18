<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Schema;

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
        'participa_fidelidade',
        'conta_paga',
        'valor_conta',
        'conta_paga_em',
        'pagamentos_conta',
        'observacao',
        'local',
        'ocasiao',
    ];

    protected $casts = [
        'data_reserva' => 'date',
        'qtd_pessoas' => 'integer',
        'participa_fidelidade' => 'boolean',
        'conta_paga' => 'boolean',
        'valor_conta' => 'decimal:2',
        'conta_paga_em' => 'datetime',
        'pagamentos_conta' => 'array',
    ];

    public const STATUS_PENDENTE = 'pendente';
    public const STATUS_CONFIRMADA = 'confirmada';
    public const STATUS_CANCELADA = 'cancelada';
    public const STATUS_CLIENTE_CHEGOU = 'cliente_chegou';
    public const STATUS_NO_SHOW = 'no_show';
    public const STATUS_FINALIZADA = 'finalizada';

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function mesa(): BelongsTo
    {
        return $this->belongsTo(Mesa::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }

    public function mesas(): BelongsToMany
    {
        return $this->belongsToMany(Mesa::class, 'reserva_mesas', 'reserva_id', 'mesa_id')
            ->withPivot([
                'capacidade_utilizada',
                'cadeiras_extras_utilizadas',
                'principal',
                'configuracao_emergencial',
            ])
            ->withTimestamps();
    }

    public function mesaPrincipal(): ?Mesa
    {
        if (Schema::hasTable('reserva_mesas')) {
            $principal = $this->mesas()->wherePivot('principal', true)->first();
            if ($principal) {
                return $principal;
            }
        }

        return $this->mesa;
    }

    public function capacidadeTotalReservada(): int
    {
        if (Schema::hasTable('reserva_mesas') && $this->mesas()->exists()) {
            return (int) $this->mesas()->sum('reserva_mesas.capacidade_utilizada');
        }

        return (int) $this->qtd_pessoas;
    }

    public function cadeirasExtrasUtilizadas(): int
    {
        if (Schema::hasTable('reserva_mesas') && $this->mesas()->exists()) {
            return (int) $this->mesas()->sum('reserva_mesas.cadeiras_extras_utilizadas');
        }

        return 0;
    }

    public function configuracaoEmergencial(): bool
    {
        if (! Schema::hasTable('reserva_mesas')) {
            return false;
        }

        return $this->mesas()->wherePivot('configuracao_emergencial', true)->exists();
    }
}
