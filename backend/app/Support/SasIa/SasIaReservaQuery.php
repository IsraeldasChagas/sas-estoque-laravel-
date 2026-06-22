<?php

namespace App\Support\SasIa;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Consulta reservas de mesa — mesma lógica do módulo Reserva de Mesa.
 */
final class SasIaReservaQuery
{
    /** Status que não contam como reserva ativa. */
    private const STATUS_INATIVOS = ['cancelada', 'no_show', 'finalizada'];

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public static function consultar(SasIaContext $ctx, array $args): array
    {
        if (! Schema::hasTable('reservas_mesas')) {
            return [
                'total' => 0,
                'tem_reservas' => false,
                'reservas_hoje' => 0,
                'reservas' => [],
                'mensagem' => 'Módulo de reservas não instalado.',
            ];
        }

        try {
            [$de, $ate] = self::resolverPeriodo($args);
            $hoje = now()->format('Y-m-d');

            $select = [
                'r.id',
                'r.nome_cliente',
                'r.telefone_cliente',
                'r.data_reserva',
                'r.hora_reserva',
                'r.qtd_pessoas',
                'r.status',
                'r.observacao',
                'r.unidade_id',
                'u.nome as unidade',
            ];

            foreach (['local', 'ocasiao'] as $col) {
                if (Schema::hasColumn('reservas_mesas', $col)) {
                    $select[] = 'r.'.$col;
                }
            }

            if (Schema::hasTable('mesas')) {
                $select[] = 'm.numero_mesa';
                if (Schema::hasColumn('mesas', 'nome_mesa')) {
                    $select[] = 'm.nome_mesa';
                }
                if (Schema::hasColumn('mesas', 'capacidade')) {
                    $select[] = 'm.capacidade';
                }
            }

            $q = DB::table('reservas_mesas as r')
                ->leftJoin('unidades as u', 'r.unidade_id', '=', 'u.id');

            if (Schema::hasTable('mesas')) {
                $q->leftJoin('mesas as m', 'r.mesa_id', '=', 'm.id');
            }

            $q->whereBetween('r.data_reserva', [$de, $ate])
                ->whereNotIn('r.status', self::STATUS_INATIVOS)
                ->select($select)
                ->orderBy('r.data_reserva')
                ->orderBy('r.hora_reserva')
                ->limit(80);

            $unidadeId = isset($args['unidade_id']) ? (int) $args['unidade_id'] : $ctx->unidadeEfetiva();
            if ($unidadeId) {
                $q->where('r.unidade_id', $unidadeId);
            }

            $buscaCliente = trim((string) ($args['busca_cliente'] ?? ''));
            if ($buscaCliente !== '') {
                $q->where('r.nome_cliente', 'like', '%'.$buscaCliente.'%');
            }

            $rows = $q->get();
            $reservas = $rows->map(fn ($r) => [
                'id' => $r->id,
                'cliente' => $r->nome_cliente,
                'telefone' => $r->telefone_cliente ?? null,
                'data' => $r->data_reserva,
                'hora' => is_string($r->hora_reserva) ? substr($r->hora_reserva, 0, 5) : $r->hora_reserva,
                'pessoas' => (int) ($r->qtd_pessoas ?? 1),
                'status' => $r->status,
                'unidade' => $r->unidade,
                'unidade_id' => $r->unidade_id,
                'mesa' => $r->numero_mesa ?? ($r->nome_mesa ?? null),
                'capacidade_mesa' => isset($r->capacidade) ? (int) $r->capacidade : null,
                'local' => $r->local ?? null,
                'ocasiao' => $r->ocasiao ?? null,
                'observacao' => $r->observacao ?? null,
            ])->values()->all();

            $hojeCount = $rows->filter(fn ($r) => (string) $r->data_reserva === $hoje)->count();

            $porDia = [];
            foreach ($rows as $r) {
                $dia = (string) $r->data_reserva;
                $porDia[$dia] = ($porDia[$dia] ?? 0) + 1;
            }

            return [
                'periodo' => ['de' => $de, 'ate' => $ate],
                'data_hoje' => $hoje,
                'total' => count($reservas),
                'tem_reservas' => count($reservas) > 0,
                'reservas_hoje' => $hojeCount,
                'tem_reservas_hoje' => $hojeCount > 0,
                'por_dia' => $porDia,
                'reservas' => $reservas,
                'escopo_unidade_id' => $unidadeId,
                'observacao' => 'Reservas ativas: pendente, confirmada ou cliente já chegou. Canceladas e finalizadas não entram.',
            ];
        } catch (\Throwable $e) {
            Log::error('SAS IA consultar reservas: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return [
                'erro' => true,
                'mensagem' => 'Falha ao consultar reservas no banco.',
                'total' => 0,
                'tem_reservas' => false,
                'reservas' => [],
            ];
        }
    }

    /** @param  array<string, mixed>  $args
     * @return array{0: string, 1: string}
     */
    private static function resolverPeriodo(array $args): array
    {
        if (! empty($args['data'])) {
            $d = trim((string) $args['data']);

            return [$d, $d];
        }

        $de = trim((string) ($args['de'] ?? now()->subDays(7)->format('Y-m-d')));
        $ate = trim((string) ($args['ate'] ?? now()->addDays(60)->format('Y-m-d')));

        if ($de > $ate) {
            [$de, $ate] = [$ate, $de];
        }

        return [$de, $ate];
    }
}
