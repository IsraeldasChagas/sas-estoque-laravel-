<?php

namespace App\Http\Controllers\Delivery;

use App\Services\Delivery\DeliveryPedidoService;
use App\Support\Delivery\DeliveryPedidoPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DeliveryPublicEntregadorController extends DeliveryBaseController
{
    public function __construct(
        \App\Services\Delivery\DeliveryAccessService $access,
        private readonly DeliveryPedidoService $pedidos,
    ) {
        parent::__construct($access);
    }

    public function show(string $slug, string $codigo, string $token): View
    {
        $contexto = $this->resolverPedido($slug, $codigo, $token);
        abort_unless($contexto, 404);

        ['config' => $config, 'pedido' => $pedido, 'itens' => $itens] = $contexto;

        return view('delivery.public.entregador-pedido', [
            'slug' => $slug,
            'config' => $config,
            'pedido' => $pedido,
            'itens' => $itens,
            'token' => $token,
        ]);
    }

    public function registrar(Request $request, string $slug, string $codigo, string $token): RedirectResponse
    {
        $contexto = $this->resolverPedido($slug, $codigo, $token);
        abort_unless($contexto, 404);

        $pedido = $contexto['pedido'];
        if (! DeliveryPedidoPresenter::entregadorPodeRegistrarResultado($pedido->status ?? null)) {
            return redirect()
                ->route('delivery.public.entregador.show', ['slug' => $slug, 'codigo' => $codigo, 'token' => $token])
                ->with('warning', 'Este pedido já foi finalizado ou não pode ser atualizado por aqui.');
        }

        $data = $request->validate([
            'resultado' => ['required', 'string', 'in:entregue,cancelado,endereco'],
            'codigo_confirmado' => ['required', 'string', 'max:64'],
        ]);

        if (! DeliveryPedidoPresenter::codigoPublicoConfere(
            $data['codigo_confirmado'],
            (string) ($pedido->codigo_publico ?? '')
        )) {
            return redirect()
                ->route('delivery.public.entregador.show', ['slug' => $slug, 'codigo' => $codigo, 'token' => $token])
                ->withInput()
                ->withErrors(['codigo_confirmado' => 'Código do pedido não confere. Peça ao cliente o código correto.']);
        }

        $novoStatus = match ($data['resultado']) {
            'entregue' => 'entregue',
            'cancelado' => 'cancelado',
            'endereco' => 'endereco_nao_encontrado',
        };

        $this->pedidos->alterarStatus($pedido, $novoStatus, null, 'Registrado pelo entregador');

        return redirect()
            ->route('delivery.public.entregador.show', ['slug' => $slug, 'codigo' => $codigo, 'token' => $token])
            ->with('status', match ($data['resultado']) {
                'entregue' => 'Pedido marcado como entregue. Obrigado!',
                'cancelado' => 'Pedido marcado como cancelado.',
                'endereco' => 'Registrado: endereço não encontrado.',
            });
    }

    /** @return array{config: object, pedido: object, itens: \Illuminate\Support\Collection}|null */
    private function resolverPedido(string $slug, string $codigo, string $token): ?array
    {
        $config = DB::table('dlv_loja_config')->where('slug', $slug)->where('ativo', 1)->first();
        if (! $config) {
            return null;
        }

        $codigoNorm = DeliveryPedidoPresenter::normalizarCodigoPublico($codigo);

        $pedido = DB::table('dlv_pedidos')
            ->where('unidade_id', $config->unidade_id)
            ->where('codigo_publico', $codigoNorm)
            ->first();

        if (! $pedido) {
            return null;
        }

        if (strtolower(trim((string) ($pedido->fulfillment ?? 'entrega'))) !== 'entrega') {
            return null;
        }

        $t = $pedido->entregador_token;
        if (! is_string($t) || $t === '' || ! hash_equals($t, $token)) {
            return null;
        }

        $itens = DB::table('dlv_pedido_itens')
            ->where('pedido_id', $pedido->id)
            ->orderBy('ordem')
            ->orderBy('id')
            ->get();

        return compact('config', 'pedido', 'itens');
    }
}
