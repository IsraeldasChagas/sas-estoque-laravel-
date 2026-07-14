<?php

namespace App\Services\Reservas;

use App\Models\Mesa;
use App\Models\ReservaMesa;
use App\Support\Ayla\AylaSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Motor de disponibilidade / composição / cadeiras extras.
 * Prioridade: mesa exata → mesa maior → extras → composição (até 4) → ajuste → cadastro emergencial.
 */
class ReservaDisponibilidadeService
{
    public const MAX_MESAS_COMPOSICAO = 4;

    private const STATUS_ATIVAS = ['pendente', 'confirmada', 'cliente_chegou'];

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function analisar(array $args): array
    {
        $unidadeId = (int) ($args['unidade_id'] ?? 0);
        $data = (string) ($args['data'] ?? $args['data_reserva'] ?? '');
        $horario = $this->horaCurta((string) ($args['horario'] ?? $args['hora_reserva'] ?? ''));
        $qtd = max(1, (int) ($args['quantidade_pessoas'] ?? $args['qtd_pessoas'] ?? 1));
        $excetoReservaId = isset($args['exceto_reserva_id']) ? (int) $args['exceto_reserva_id'] : null;
        $duracao = isset($args['duracao_minutos']) ? (int) $args['duracao_minutos'] : null;

        $base = [
            'unidade_id' => $unidadeId,
            'data' => $data,
            'horario' => $horario,
            'quantidade_pessoas' => $qtd,
            'duracao_minutos' => $duracao,
            'mesa_individual' => null,
            'cadeiras_extras' => null,
            'composicoes' => [],
            'alternativas' => [],
            'mesas_disponiveis' => [],
            'mesas_ocupadas' => [],
            'capacidade_total' => 0,
            'exige_cadastro_emergencial' => false,
            'exige_ajuste_capacidade' => false,
            'sugestao_operacional' => null,
            'motivo_indisponibilidade' => null,
            'observacoes' => [],
        ];

        if ($unidadeId < 1 || $data === '' || $horario === null) {
            $base['motivo_indisponibilidade'] = 'Informe unidade, data e horário.';

            return $base;
        }

        if (! AylaSettings::unidadePermitida($unidadeId)) {
            $base['motivo_indisponibilidade'] = 'Unidade não autorizada.';

            return $base;
        }

        if (! Schema::hasTable('mesas')) {
            $base['motivo_indisponibilidade'] = 'Tabela de mesas indisponível.';
            $base['exige_cadastro_emergencial'] = true;

            return $base;
        }

        if ($duracao !== null && $duracao > 0) {
            $base['observacoes'][] = 'O sistema não armazena duração; conflito é por data+horário exatos.';
        }

        $mesas = Mesa::query()
            ->where('unidade_id', $unidadeId)
            ->where('ativo', 1)
            ->orderBy('numero_mesa')
            ->get();

        $ocupadasIds = $this->mesasOcupadasNoSlot($unidadeId, $data, $horario, $excetoReservaId);

        $disponiveis = [];
        $ocupadas = [];

        foreach ($mesas as $mesa) {
            $item = $this->formatarMesa($mesa);
            if ($mesa->status === Mesa::STATUS_BLOQUEADA) {
                $item['motivo'] = 'Mesa bloqueada.';
                $ocupadas[] = $item;

                continue;
            }
            if (in_array((int) $mesa->id, $ocupadasIds, true)) {
                $item['motivo'] = 'Já possui reserva ativa neste horário.';
                $ocupadas[] = $item;

                continue;
            }
            $disponiveis[] = $item;
        }

        $base['mesas_disponiveis'] = $disponiveis;
        $base['mesas_ocupadas'] = $ocupadas;
        $base['capacidade_total'] = array_sum(array_map(
            fn ($m) => (int) ($m['capacidade_maxima'] ?? $m['capacidade'] ?? 0),
            $disponiveis
        ));

        $candidatas = $mesas->filter(function (Mesa $m) use ($ocupadasIds) {
            return $m->status !== Mesa::STATUS_BLOQUEADA
                && ! in_array((int) $m->id, $ocupadasIds, true);
        })->values();

        // 1) Mesa exata sem cadeira extra
        $exata = $candidatas
            ->filter(fn (Mesa $m) => $m->capacidadeBase() >= $qtd && $m->capacidadeBase() === $qtd)
            ->sortBy(fn (Mesa $m) => $m->capacidadeBase())
            ->first();

        if ($exata) {
            return $this->montarSugestaoIndividual($base, $exata, $qtd, 0, 'mesa_exata');
        }

        // 2) Menor mesa acima da capacidade (sem extras)
        $maior = $candidatas
            ->filter(fn (Mesa $m) => $m->capacidadeBase() >= $qtd)
            ->sortBy(fn (Mesa $m) => $m->capacidadeBase())
            ->first();

        if ($maior) {
            return $this->montarSugestaoIndividual($base, $maior, $qtd, 0, 'mesa_maior');
        }

        // 3) Mesa com menor quantidade de cadeiras extras
        $comExtras = $candidatas
            ->filter(function (Mesa $m) use ($qtd) {
                if (! $m->permiteAdicionarCadeiras()) {
                    return false;
                }
                $baseCap = $m->capacidadeBase();
                $extras = $qtd - $baseCap;

                return $extras > 0
                    && $extras <= (int) ($m->cadeiras_extras_max ?? 0)
                    && $qtd <= $m->capacidadeMaximaCalculada();
            })
            ->sortBy(function (Mesa $m) use ($qtd) {
                return $qtd - $m->capacidadeBase();
            })
            ->values();

        if ($comExtras->isNotEmpty()) {
            $m = $comExtras->first();
            $extras = $qtd - $m->capacidadeBase();

            return $this->montarSugestaoIndividual($base, $m, $qtd, $extras, 'cadeiras_extras');
        }

        // 4+5) Compostações
        $composicoes = $this->buscarComposicoes($candidatas, $qtd);
        $base['composicoes'] = $composicoes;

        if ($composicoes !== []) {
            $melhor = $composicoes[0];
            $base['mesa_individual'] = null;
            $base['cadeiras_extras'] = null;
            $base['sugestao_operacional'] = $melhor;
            $base['alternativas'] = array_slice($composicoes, 1, 5);
            $base['observacoes'][] = 'Não há mesa individual suficiente; sugestão por composição.';

            return $base;
        }

        // 6) Ajuste emergencial de capacidade (menor alteração)
        $ajuste = $candidatas
            ->filter(fn (Mesa $m) => $m->capacidadeBase() < $qtd)
            ->sortBy(fn (Mesa $m) => $qtd - $m->capacidadeBase())
            ->first();

        if ($ajuste) {
            $extrasNecessarias = $qtd - $ajuste->capacidadeBase();
            $base['exige_ajuste_capacidade'] = true;
            $base['sugestao_operacional'] = [
                'tipo' => 'ajuste_capacidade',
                'mesa' => $this->formatarMesa($ajuste),
                'capacidade_atual' => $ajuste->capacidadeBase(),
                'capacidade_desejada' => $qtd,
                'cadeiras_extras_necessarias' => $extrasNecessarias,
                'mensagem' => sprintf(
                    'A Mesa %s possui capacidade cadastrada para %d pessoas. Para atender, será necessário acrescentar %d cadeira(s). Deseja autorizar o ajuste da capacidade máxima para %d?',
                    $ajuste->nome_mesa ?: $ajuste->numero_mesa,
                    $ajuste->capacidadeBase(),
                    $extrasNecessarias,
                    $qtd
                ),
            ];
            $base['motivo_indisponibilidade'] = 'Capacidade insuficiente nas mesas cadastradas sem ajuste emergencial.';
            $base['observacoes'][] = 'Confirmação obrigatória para ajustar capacidade.';

            return $base;
        }

        // 7) Cadastro emergencial
        $base['exige_cadastro_emergencial'] = true;
        $base['motivo_indisponibilidade'] = 'Não encontrei mesa ou composição suficiente.';
        $base['sugestao_operacional'] = [
            'tipo' => 'cadastro_emergencial',
            'opcoes' => [
                'cadastrar_nova_mesa',
                'ampliar_capacidade_mesa',
                'cadastrar_composicao_emergencial',
                'verificar_outro_horario',
                'verificar_outra_unidade',
            ],
            'mensagem' => sprintf(
                'Não encontrei uma mesa ou composição cadastrada com capacidade para %d pessoas na unidade informada. Para não perder o atendimento, posso preparar um cadastro emergencial.',
                $qtd
            ),
        ];

        return $base;
    }

    /**
     * @param  Collection<int, Mesa>  $candidatas
     * @return array<int, array<string, mixed>>
     */
    private function buscarComposicoes(Collection $candidatas, int $qtd): array
    {
        $juntaveis = $candidatas->filter(fn (Mesa $m) => (bool) $m->pode_juntar)->values();
        if ($juntaveis->count() < 2) {
            return [];
        }

        $resultados = [];
        $lista = $juntaveis->all();
        $n = count($lista);
        $limite = min(self::MAX_MESAS_COMPOSICAO, $n);

        for ($size = 2; $size <= $limite; $size++) {
            foreach ($this->combinacoes($lista, $size) as $combo) {
                /** @var Mesa[] $combo */
                $ok = true;
                for ($i = 0; $i < count($combo) && $ok; $i++) {
                    for ($j = $i + 1; $j < count($combo); $j++) {
                        if (! $combo[$i]->podeSerCombinadaCom($combo[$j])) {
                            $ok = false;
                            break;
                        }
                    }
                }
                if (! $ok) {
                    continue;
                }

                $capTotal = 0;
                $mesasFmt = [];
                foreach ($combo as $idx => $m) {
                    $cap = $m->capacidadeBase();
                    $capTotal += $cap;
                    $mesasFmt[] = array_merge($this->formatarMesa($m), [
                        'principal' => $idx === 0,
                        'capacidade_utilizada' => $cap,
                        'cadeiras_extras_utilizadas' => 0,
                    ]);
                }

                if ($capTotal < $qtd) {
                    // tenta usar extras na última mesa
                    $ultima = $combo[count($combo) - 1];
                    if ($ultima->permiteAdicionarCadeiras()) {
                        $faltam = $qtd - $capTotal;
                        $maxExtra = (int) ($ultima->cadeiras_extras_max ?? 0);
                        if ($faltam <= $maxExtra && ($capTotal + $faltam) <= $ultima->capacidadeMaximaCalculada() + ($capTotal - $ultima->capacidadeBase())) {
                            $capTotal += $faltam;
                            $mesasFmt[count($mesasFmt) - 1]['cadeiras_extras_utilizadas'] = $faltam;
                            $mesasFmt[count($mesasFmt) - 1]['capacidade_utilizada'] = $ultima->capacidadeBase() + $faltam;
                        }
                    }
                }

                if ($capTotal < $qtd) {
                    continue;
                }

                // redistribuir capacidade_utilizada proporcionalmente / priorizar principal
                $restante = $qtd;
                foreach ($mesasFmt as $i => &$mf) {
                    $baseCap = (int) $combo[$i]->capacidadeBase();
                    $extra = (int) ($mf['cadeiras_extras_utilizadas'] ?? 0);
                    $maxLocal = $baseCap + $extra;
                    $usa = min($maxLocal, $restante);
                    $mf['capacidade_utilizada'] = $usa;
                    $mf['cadeiras_extras_utilizadas'] = max(0, $usa - $baseCap);
                    $restante -= $usa;
                }
                unset($mf);

                $sobra = $capTotal - $qtd;
                $resultados[] = [
                    'tipo' => 'composicao',
                    'mesas' => $mesasFmt,
                    'quantidade_mesas' => count($mesasFmt),
                    'capacidade_total' => $capTotal,
                    'capacidade_utilizada' => $qtd,
                    'sobra_lugares' => $sobra,
                    'mensagem' => sprintf(
                        'Não há uma mesa individual para %d pessoas. Posso juntar %d mesas (capacidade total: %d). Deseja confirmar a composição?',
                        $qtd,
                        count($mesasFmt),
                        $capTotal
                    ),
                ];
            }
        }

        usort($resultados, function ($a, $b) {
            if ($a['quantidade_mesas'] !== $b['quantidade_mesas']) {
                return $a['quantidade_mesas'] <=> $b['quantidade_mesas'];
            }

            return $a['sobra_lugares'] <=> $b['sobra_lugares'];
        });

        return array_slice($resultados, 0, 10);
    }

    /**
     * @param  array<int, Mesa>  $items
     * @return array<int, array<int, Mesa>>
     */
    private function combinacoes(array $items, int $size): array
    {
        $n = count($items);
        if ($size > $n || $size < 1) {
            return [];
        }
        $out = [];
        $indexes = range(0, $size - 1);
        while (true) {
            $combo = [];
            foreach ($indexes as $i) {
                $combo[] = $items[$i];
            }
            $out[] = $combo;

            $i = $size - 1;
            while ($i >= 0 && $indexes[$i] === $i + $n - $size) {
                $i--;
            }
            if ($i < 0) {
                break;
            }
            $indexes[$i]++;
            for ($j = $i + 1; $j < $size; $j++) {
                $indexes[$j] = $indexes[$j - 1] + 1;
            }
            // Limite de segurança para não explodir memória
            if (count($out) >= 200) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    private function montarSugestaoIndividual(array $base, Mesa $mesa, int $qtd, int $extras, string $tipo): array
    {
        $fmt = $this->formatarMesa($mesa);
        $sugestao = [
            'tipo' => $tipo,
            'mesa' => $fmt,
            'mesa_id' => (int) $mesa->id,
            'principal' => true,
            'capacidade_utilizada' => $qtd,
            'cadeiras_extras_utilizadas' => $extras,
            'capacidade_base' => $mesa->capacidadeBase(),
            'capacidade_maxima' => $mesa->capacidadeMaximaCalculada(),
            'mesas' => [[
                'mesa_id' => (int) $mesa->id,
                'principal' => true,
                'capacidade_utilizada' => $qtd,
                'cadeiras_extras_utilizadas' => $extras,
                'numero_mesa' => (string) $mesa->numero_mesa,
                'nome' => $mesa->nome_mesa ?: ('Mesa '.$mesa->numero_mesa),
                'capacidade_base' => $mesa->capacidadeBase(),
                'capacidade_maxima' => $mesa->capacidadeMaximaCalculada(),
            ]],
            'mensagem' => $extras > 0
                ? sprintf(
                    'Encontrei a Mesa %s. Capacidade atual: %d pessoas. Será necessário acrescentar %d cadeira(s). Capacidade máxima: %d. Deseja confirmar essa configuração?',
                    $mesa->nome_mesa ?: $mesa->numero_mesa,
                    $mesa->capacidadeBase(),
                    $extras,
                    $mesa->capacidadeMaximaCalculada()
                )
                : sprintf(
                    'Encontrei a Mesa %s com capacidade para %d pessoas. Deseja confirmar?',
                    $mesa->nome_mesa ?: $mesa->numero_mesa,
                    $mesa->capacidadeBase()
                ),
        ];

        $base['mesa_individual'] = $tipo !== 'cadeiras_extras' ? $sugestao : null;
        $base['cadeiras_extras'] = $extras > 0 ? $sugestao : null;
        $base['sugestao_operacional'] = $sugestao;
        $base['alternativas'] = array_values(array_filter($base['mesas_disponiveis'], fn ($m) => (int) $m['mesa_id'] !== (int) $mesa->id));

        return $base;
    }

    /**
     * @return int[]
     */
    private function mesasOcupadasNoSlot(int $unidadeId, string $data, string $horario, ?int $excetoReservaId = null): array
    {
        $ids = [];

        if (Schema::hasTable('reservas_mesas')) {
            $q = ReservaMesa::query()
                ->where('unidade_id', $unidadeId)
                ->whereDate('data_reserva', $data)
                ->whereTime('hora_reserva', $horario)
                ->whereIn('status', self::STATUS_ATIVAS);
            if ($excetoReservaId) {
                $q->where('id', '!=', $excetoReservaId);
            }
            $ids = $q->pluck('mesa_id')->map(fn ($id) => (int) $id)->all();

            // Também mesas do pivô (composições)
            if (Schema::hasTable('reserva_mesas')) {
                $reservaIds = (clone $q)->pluck('id')->all();
                if ($reservaIds !== []) {
                    $extra = DB::table('reserva_mesas')
                        ->whereIn('reserva_id', $reservaIds)
                        ->pluck('mesa_id')
                        ->map(fn ($id) => (int) $id)
                        ->all();
                    $ids = array_merge($ids, $extra);
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /** @return array<string, mixed> */
    private function formatarMesa(Mesa $m): array
    {
        return [
            'mesa_id' => (int) $m->id,
            'numero_mesa' => (string) $m->numero_mesa,
            'nome' => $m->nome_mesa ? (string) $m->nome_mesa : ('Mesa '.$m->numero_mesa),
            'capacidade' => (int) $m->capacidade,
            'capacidade_base' => $m->capacidadeBase(),
            'cadeiras_extras_max' => (int) ($m->cadeiras_extras_max ?? 0),
            'capacidade_maxima' => $m->capacidadeMaximaCalculada(),
            'permite_cadeiras_extras' => $m->permiteAdicionarCadeiras(),
            'pode_juntar' => (bool) $m->pode_juntar,
            'grupo_composicao' => $m->grupo_composicao ? (string) $m->grupo_composicao : null,
            'status_mesa' => (string) $m->status,
            'localizacao' => $m->localizacao ? (string) $m->localizacao : null,
            'cadastro_emergencial' => $m->foiCadastradaEmergencialmente(),
        ];
    }

    private function horaCurta(string $hora): ?string
    {
        $hora = trim($hora);
        if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', $hora, $m)) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        return null;
    }
}
