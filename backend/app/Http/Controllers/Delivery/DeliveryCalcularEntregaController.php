<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Services\Delivery\DeliveryFreteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryCalcularEntregaController extends Controller
{
    public function __invoke(Request $request, DeliveryFreteService $frete): JsonResponse
    {
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:120'],
            'cep' => ['required', 'string', 'max:16'],
            'rua' => ['nullable', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:32'],
            'bairro' => ['nullable', 'string', 'max:120'],
            'cidade' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', 'string', 'max:2'],
            'subtotal_pedido' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ]);

        $slug = strtolower(trim($data['slug']));
        $config = DB::table('dlv_loja_config')->where('slug', $slug)->where('ativo', 1)->first();
        if (! $config) {
            throw ValidationException::withMessages(['slug' => 'Loja não encontrada.']);
        }

        if ($frete->modoEfetivo($config) !== DeliveryFreteService::MODO_OSRM) {
            return response()->json([
                'success' => false,
                'message' => 'Esta loja não usa frete por rota (OSRM).',
            ], 422);
        }

        $sub = isset($data['subtotal_pedido']) ? (float) $data['subtotal_pedido'] : null;
        $r = $frete->calcularOsrmDetalhado($config, [
            'cep' => $data['cep'],
            'rua' => $data['rua'] ?? '',
            'numero' => $data['numero'] ?? '',
            'bairro' => $data['bairro'] ?? '',
            'cidade' => $data['cidade'] ?? '',
            'estado' => $data['estado'] ?? '',
        ], $sub);

        if (! ($r['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $r['message'] ?? 'Não foi possível calcular a entrega.',
            ]);
        }

        $taxa = round((float) ($r['taxa_entrega'] ?? 0), 2);
        if (! ($r['entrega_bloqueada'] ?? false) && (bool) ($config->frete_chuva_ativa ?? false)) {
            $pct = (float) ($config->frete_acrescimo_chuva_percent ?? 0);
            if ($pct > 0 && $taxa > 0) {
                $taxa = round($taxa * (1 + ($pct / 100)), 2);
            }
        }

        return response()->json([
            'success' => true,
            'distancia_km' => $r['distancia_km'],
            'tempo_minutos' => $r['tempo_minutos'],
            'taxa_entrega' => $taxa,
            'endereco_formatado' => $r['endereco_formatado'] ?? '',
            'lat_cliente' => $r['lat_cliente'],
            'lng_cliente' => $r['lng_cliente'],
            'entrega_bloqueada' => (bool) ($r['entrega_bloqueada'] ?? false),
        ]);
    }
}
