<?php

namespace App\Http\Controllers\Delivery;

use App\Services\Delivery\DeliveryEntregadorOfertaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DeliveryPublicMotoboyController extends DeliveryBaseController
{
    public function __construct(
        \App\Services\Delivery\DeliveryAccessService $access,
        private readonly DeliveryEntregadorOfertaService $ofertas,
    ) {
        parent::__construct($access);
    }

    public function app(string $slug, string $acessoToken): View
    {
        $ctx = $this->resolverEntregador($slug, $acessoToken);
        abort_unless($ctx, 404);

        return view('delivery.public.motoboy-app', [
            'slug' => $slug,
            'config' => $ctx['config'],
            'entregador' => $ctx['entregador'],
            'acessoToken' => $acessoToken,
            'ofertasUrl' => route('delivery.public.motoboy.ofertas', [
                'slug' => $slug,
                'acessoToken' => $acessoToken,
            ]),
            'manifestUrl' => route('delivery.public.motoboy.manifest', [
                'slug' => $slug,
                'acessoToken' => $acessoToken,
            ]),
        ]);
    }

    public function ofertas(string $slug, string $acessoToken): JsonResponse
    {
        $ctx = $this->resolverEntregador($slug, $acessoToken);
        abort_unless($ctx, 404);

        $items = $this->ofertas->listarOfertasAbertas($ctx['config'], $ctx['entregador']);

        return response()->json([
            'entregador' => [
                'id' => (int) $ctx['entregador']->id,
                'nome' => (string) $ctx['entregador']->nome,
            ],
            'loja' => (string) ($ctx['config']->nome_loja ?? 'Loja'),
            'count' => count($items),
            'items' => $items,
            'agora' => now()->toIso8601String(),
        ]);
    }

    public function aceitar(Request $request, string $slug, string $acessoToken, int $pedidoId): JsonResponse
    {
        $ctx = $this->resolverEntregador($slug, $acessoToken);
        abort_unless($ctx, 404);

        $result = $this->ofertas->aceitarOferta($ctx['config'], $ctx['entregador'], $pedidoId);

        return response()->json($result);
    }

    public function recusar(Request $request, string $slug, string $acessoToken, int $pedidoId): JsonResponse
    {
        $ctx = $this->resolverEntregador($slug, $acessoToken);
        abort_unless($ctx, 404);

        $result = $this->ofertas->recusarOferta($ctx['config'], $ctx['entregador'], $pedidoId);

        return response()->json($result);
    }

    public function manifest(string $slug, string $acessoToken): JsonResponse
    {
        $ctx = $this->resolverEntregador($slug, $acessoToken);
        abort_unless($ctx, 404);

        $nomeLoja = trim((string) ($ctx['config']->nome_loja ?? 'Delivery'));
        $start = route('delivery.public.motoboy.app', [
            'slug' => $slug,
            'acessoToken' => $acessoToken,
        ], absolute: true);

        return response()->json([
            'name' => 'Motoboy · '.$nomeLoja,
            'short_name' => 'Motoboy',
            'description' => 'Receba e aceite entregas do '.$nomeLoja,
            'start_url' => $start,
            'scope' => parse_url($start, PHP_URL_PATH) ?: '/',
            'display' => 'standalone',
            'background_color' => '#0f172a',
            'theme_color' => '#16a34a',
            'lang' => 'pt-BR',
            'icons' => [
                [
                    'src' => asset('assets/delivery/motoboy-icon-192.png'),
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any maskable',
                ],
                [
                    'src' => asset('assets/delivery/motoboy-icon-512.png'),
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any maskable',
                ],
            ],
        ])->header('Content-Type', 'application/manifest+json');
    }

    /** @return array{config: object, entregador: object}|null */
    private function resolverEntregador(string $slug, string $acessoToken): ?array
    {
        $token = trim($acessoToken);
        if ($token === '' || strlen($token) < 20) {
            return null;
        }

        $config = DB::table('dlv_loja_config')->where('slug', $slug)->where('ativo', 1)->first();
        if (! $config) {
            return null;
        }

        if (! \Illuminate\Support\Facades\Schema::hasColumn('dlv_entregadores', 'acesso_token')) {
            return null;
        }

        $entregador = DB::table('dlv_entregadores')
            ->where('unidade_id', $config->unidade_id)
            ->where('acesso_token', $token)
            ->where('ativo', 1)
            ->first();

        if (! $entregador) {
            return null;
        }

        return compact('config', 'entregador');
    }
}
