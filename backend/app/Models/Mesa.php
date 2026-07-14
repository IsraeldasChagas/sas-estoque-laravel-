<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Mesa extends Model
{
    protected $table = 'mesas';

    protected $fillable = [
        'unidade_id',
        'numero_mesa',
        'nome_mesa',
        'capacidade',
        'capacidade_base',
        'permite_cadeiras_extras',
        'cadeiras_extras_max',
        'capacidade_maxima',
        'localizacao',
        'pode_juntar',
        'pode_separar',
        'grupo_composicao',
        'status',
        'observacao',
        'ativo',
        'cadastro_emergencial',
        'cadastrado_pela_ayla',
        'cadastrado_por_usuario_id',
        'motivo_cadastro',
    ];

    protected $casts = [
        'capacidade' => 'integer',
        'capacidade_base' => 'integer',
        'capacidade_maxima' => 'integer',
        'cadeiras_extras_max' => 'integer',
        'ativo' => 'boolean',
        'pode_juntar' => 'boolean',
        'pode_separar' => 'boolean',
        'permite_cadeiras_extras' => 'boolean',
        'cadastro_emergencial' => 'boolean',
        'cadastrado_pela_ayla' => 'boolean',
    ];

    public const STATUS_LIVRE = 'livre';
    public const STATUS_RESERVADA = 'reservada';
    public const STATUS_AGUARDANDO_CLIENTE = 'aguardando_cliente';
    public const STATUS_OCUPADA = 'ocupada';
    public const STATUS_BLOQUEADA = 'bloqueada';

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function reservas()
    {
        return $this->hasMany(ReservaMesa::class);
    }

    public function reservasCompostas(): BelongsToMany
    {
        return $this->belongsToMany(ReservaMesa::class, 'reserva_mesas', 'mesa_id', 'reserva_id')
            ->withPivot([
                'capacidade_utilizada',
                'cadeiras_extras_utilizadas',
                'principal',
                'configuracao_emergencial',
            ])
            ->withTimestamps();
    }

    public function capacidadeBase(): int
    {
        $base = (int) ($this->capacidade_base ?? 0);
        if ($base < 1) {
            $base = (int) ($this->capacidade ?? 1);
        }

        return max(1, $base);
    }

    public function capacidadeMaximaCalculada(): int
    {
        $max = (int) ($this->capacidade_maxima ?? 0);
        if ($max < 1) {
            $extras = $this->permiteAdicionarCadeiras() ? (int) ($this->cadeiras_extras_max ?? 0) : 0;
            $max = $this->capacidadeBase() + max(0, $extras);
        }

        return max($this->capacidadeBase(), $max);
    }

    public function permiteAdicionarCadeiras(): bool
    {
        return (bool) ($this->permite_cadeiras_extras ?? false)
            || (int) ($this->cadeiras_extras_max ?? 0) > 0;
    }

    public function foiCadastradaEmergencialmente(): bool
    {
        return (bool) ($this->cadastro_emergencial ?? false)
            || (bool) ($this->cadastrado_pela_ayla ?? false);
    }

    public function podeSerCombinadaCom(Mesa $outra): bool
    {
        if ((int) $this->id === (int) $outra->id) {
            return false;
        }
        if (! $this->ativo || ! $outra->ativo) {
            return false;
        }
        if ($this->status === self::STATUS_BLOQUEADA || $outra->status === self::STATUS_BLOQUEADA) {
            return false;
        }
        if (! $this->pode_juntar || ! $outra->pode_juntar) {
            return false;
        }
        if ((int) $this->unidade_id !== (int) $outra->unidade_id) {
            return false;
        }

        $g1 = trim((string) ($this->grupo_composicao ?? ''));
        $g2 = trim((string) ($outra->grupo_composicao ?? ''));
        if ($g1 !== '' && $g2 !== '' && $g1 !== $g2) {
            return false;
        }

        return true;
    }

    public function reservaAtivaNaData($data, $hora = null)
    {
        $query = $this->reservas()
            ->where('data_reserva', $data)
            ->whereNotIn('status', ['cancelada', 'no_show', 'finalizada']);

        if ($hora) {
            $query->where('hora_reserva', $hora);
        }

        return $query->first();
    }
}
