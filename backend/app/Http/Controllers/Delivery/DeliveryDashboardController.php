<?php

namespace App\Http\Controllers\Delivery;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryDashboardController extends DeliveryBaseController
{
    public function __invoke(Request $request): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryDashboard');
        $query = DB::table('dlv_pedidos');
        $this->access->aplicarEscopo($query, $usuario, $request);

        if ($inicio = trim((string) $request->query('data_inicio', ''))) {
            $query->whereDate('created_at', '>=', $inicio);
        }
        if ($fim = trim((string) $request->query('data_fim', ''))) {
            $query->whereDate('created_at', '<=', $fim);
        }

        $rows = $query->orderByDesc('id')->get();
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
                'cliente_nome' => (string) $row->cliente_nome,
                'total' => (float) $row->total,
                'created_at' => $row->created_at,
            ])->values(),
        ]);
    }
}
