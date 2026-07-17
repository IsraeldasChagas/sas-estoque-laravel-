<?php

namespace App\Http\Controllers\Delivery;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DeliveryDashboardController extends DeliveryBaseController
{
    public function __invoke(Request $request): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryDashboard');
        $query = DB::table('dlv_pedidos');
        $this->access->aplicarEscopo($query, $usuario, $request);
        $scopedOrders = clone $query;

        if ($inicio = trim((string) $request->query('data_inicio', ''))) {
            $query->whereDate('created_at', '>=', $inicio);
        }
        if ($fim = trim((string) $request->query('data_fim', ''))) {
            $query->whereDate('created_at', '<=', $fim);
        }

        $rows = $query->orderByDesc('id')->get();
        $today = Carbon::today();
        $todayOrders = (clone $scopedOrders)
            ->whereDate('created_at', $today)
            ->where('status', '!=', 'cancelado');
        $todayOrderIds = (clone $todayOrders)->pluck('id');
        $todayItems = DB::table('dlv_pedido_itens')
            ->whereIn('pedido_id', $todayOrderIds);
        $products = DB::table('dlv_produtos');
        $this->access->aplicarEscopo($products, $usuario, $request);

        $sevenDays = collect(range(6, 0))->map(function (int $daysAgo) use ($scopedOrders, $today) {
            $date = $today->copy()->subDays($daysAgo);
            $dayOrders = (clone $scopedOrders)
                ->whereDate('created_at', $date)
                ->where('status', '!=', 'cancelado');

            return [
                'date' => $date->toDateString(),
                'label' => $date->locale('pt_BR')->translatedFormat('D'),
                'sales' => (int) (clone $dayOrders)->count(),
                'total' => round((float) (clone $dayOrders)->sum('total'), 2),
            ];
        })->values();

        $metrics = [
            'vendas_hoje' => (int) (clone $todayOrders)->count(),
            'produtos_vendidos_hoje' => (int) (clone $todayItems)->distinct()->count('produto_id'),
            'unidades_vendidas_hoje' => (float) (clone $todayItems)->sum('quantidade'),
            'produtos_cadastrados' => (int) (clone $products)->count(),
            'venda_total' => round((float) (clone $scopedOrders)->where('status', '!=', 'cancelado')->sum('total'), 2),
        ];
        $statusKeys = [
            'pendente_loja', 'recebido', 'preparo', 'pronto', 'rota',
            'entregue', 'cancelado', 'endereco_nao_encontrado',
        ];
        $contagens = array_fill_keys($statusKeys, 0);
        $faturamento = 0.0;

        foreach ($rows as $row) {
            $status = (string) $row->status;
            if (array_key_exists($status, $contagens)) {
                $contagens[$status]++;
            }
            if (! in_array($status, ['cancelado'], true)) {
                $faturamento += (float) $row->total;
            }
        }

        $abertos = $contagens['pendente_loja']
            + $contagens['recebido']
            + $contagens['preparo']
            + $contagens['pronto']
            + $contagens['rota'];

        return response()->json([
            'metrics' => $metrics,
            'seven_days' => $sevenDays,
            'resumo' => [
                'total_pedidos' => $rows->count(),
                'abertos' => $abertos,
                'pendente_loja' => $contagens['pendente_loja'],
                'em_preparo' => $contagens['preparo'],
                'em_rota' => $contagens['rota'],
                'entregues' => $contagens['entregue'],
                'cancelados' => $contagens['cancelado'],
                'faturamento' => round($faturamento, 2),
                'ticket_medio' => $rows->count() ? round($faturamento / max(1, $rows->count() - $contagens['cancelado']), 2) : 0,
            ],
            'por_status' => $contagens,
            'ultimos' => $rows->take(10)->map(fn ($row) => [
                'id' => (int) $row->id,
                'codigo_publico' => (string) $row->codigo_publico,
                'status' => (string) $row->status,
                'canal' => (string) $row->canal,
                'fulfillment' => (string) $row->fulfillment,
                'cliente_nome' => (string) $row->cliente_nome,
                'total' => (float) $row->total,
                'created_at' => $row->created_at,
            ])->values(),
        ]);
    }
}
