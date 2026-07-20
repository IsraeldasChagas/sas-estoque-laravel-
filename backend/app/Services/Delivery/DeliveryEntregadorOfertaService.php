<?php

namespace App\Services\Delivery;

use App\Support\Delivery\DeliveryCupomPedido;
use App\Support\Delivery\DeliveryPedidoPresenter;
use App\Support\Delivery\DeliveryWhatsAppHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DeliveryEntregadorOfertaService
{
    public function __construct(
        private readonly DeliveryPedidoService $pedidos,
    ) {}

    public function garantirAcessoToken(object $entregador): string
    {
        $token = trim((string) ($entregador->acesso_token ?? ''));
        if ($token !== '' && Schema::hasColumn('dlv_entregadores', 'acesso_token')) {
            return $token;
        }
        if (! Schema::hasColumn('dlv_entregadores', 'acesso_token')) {
            return '';
        }
        $token = Str::lower(Str::random(48));
        DB::table('dlv_entregadores')->where('id', $entregador->id)->update([
            'acesso_token' => $token,
            'updated_at' => now(),
        ]);

        return $token;
    }

    public function urlAppEntregador(object $config, object $entregador): ?string
    {
        $slug = trim((string) ($config->slug ?? ''));
        $token = $this->garantirAcessoToken($entregador);
        if ($slug === '' || $token === '') {
            return null;
        }

        return route('delivery.public.motoboy.app', [
            'slug' => $slug,
            'acessoToken' => $token,
        ], absolute: true);
    }

    public function abrirOferta(object $pedido, ?int $usuarioId = null): object
    {
        $this->exigirColunasOferta();
        if (strtolower(trim((string) ($pedido->fulfillment ?? ''))) !== 'entrega') {
            throw ValidationException::withMessages(['oferta' => 'Só pedidos de entrega podem ser oferecidos aos motoboys.']);
        }

        $status = strtolower(trim((string) ($pedido->status ?? '')));
        if (! in_array($status, ['pronto', 'rota', 'preparo', 'recebido'], true)) {
            throw ValidationException::withMessages(['oferta' => 'Abra a oferta quando o pedido estiver em preparo/pronto para entrega.']);
        }

        $atual = strtolower(trim((string) ($pedido->oferta_status ?? '')));
        if ($atual === 'aberta') {
            return DB::table('dlv_pedidos')->where('id', $pedido->id)->first();
        }
        if ($atual === 'aceita' && ! empty($pedido->entregador_id)) {
            throw ValidationException::withMessages(['oferta' => 'Este pedido já foi aceito por um entregador.']);
        }

        DB::table('dlv_pedidos')->where('id', $pedido->id)->update([
            'oferta_status' => 'aberta',
            'oferta_aberta_em' => now(),
            'oferta_aceita_em' => null,
            'entregador_id' => null,
            'updated_at' => now(),
        ]);

        if (Schema::hasTable('dlv_pedido_oferta_recusas')) {
            DB::table('dlv_pedido_oferta_recusas')->where('pedido_id', $pedido->id)->delete();
        }

        return DB::table('dlv_pedidos')->where('id', $pedido->id)->first();
    }

    public function cancelarOferta(object $pedido): object
    {
        $this->exigirColunasOferta();
        $atual = strtolower(trim((string) ($pedido->oferta_status ?? '')));
        if ($atual !== 'aberta') {
            throw ValidationException::withMessages(['oferta' => 'Não há oferta aberta para cancelar.']);
        }

        DB::table('dlv_pedidos')->where('id', $pedido->id)->update([
            'oferta_status' => 'cancelada',
            'updated_at' => now(),
        ]);

        return DB::table('dlv_pedidos')->where('id', $pedido->id)->first();
    }

    /** @return list<array<string, mixed>> */
    public function listarOfertasAbertas(object $config, object $entregador): array
    {
        if (! Schema::hasColumn('dlv_pedidos', 'oferta_status')) {
            return [];
        }

        $recusados = [];
        if (Schema::hasTable('dlv_pedido_oferta_recusas')) {
            $recusados = DB::table('dlv_pedido_oferta_recusas')
                ->where('entregador_id', $entregador->id)
                ->pluck('pedido_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $query = DB::table('dlv_pedidos')
            ->where('unidade_id', $config->unidade_id)
            ->where('fulfillment', 'entrega')
            ->where('oferta_status', 'aberta')
            ->whereIn('status', ['pronto', 'rota', 'preparo', 'recebido'])
            ->orderByDesc('oferta_aberta_em')
            ->orderByDesc('id');

        if ($recusados !== []) {
            $query->whereNotIn('id', $recusados);
        }

        return $query->get()->map(function ($pedido) use ($config) {
            $itens = DB::table('dlv_pedido_itens')
                ->where('pedido_id', $pedido->id)
                ->orderBy('ordem')
                ->orderBy('id')
                ->get();

            return [
                'id' => (int) $pedido->id,
                'codigo_publico' => (string) $pedido->codigo_publico,
                'status' => (string) $pedido->status,
                'status_rotulo' => DeliveryPedidoPresenter::rotuloStatus($pedido->status ?? null),
                'cliente_nome' => (string) ($pedido->cliente_nome ?? ''),
                'endereco' => DeliveryPedidoPresenter::enderecoLinha($pedido),
                'bairro' => (string) ($pedido->endereco_bairro ?? ''),
                'pagamento_forma' => (string) ($pedido->pagamento_forma ?? ''),
                'total' => (float) ($pedido->total ?? 0),
                'frete_valor' => (float) ($pedido->frete_valor ?? 0),
                'itens_qtd' => $itens->sum(fn ($i) => (float) ($i->quantidade ?? 1)),
                'itens_resumo' => $itens->take(4)->map(fn ($i) => trim(($i->quantidade ?? 1).'x '.($i->nome_produto ?? $i->produto_nome ?? 'Item')))->values()->all(),
                'oferta_aberta_em' => $pedido->oferta_aberta_em,
                'loja_nome' => (string) ($config->nome_loja ?? 'Loja'),
            ];
        })->values()->all();
    }

    /** @return array<string, mixed> */
    public function aceitarOferta(object $config, object $entregador, int $pedidoId): array
    {
        $this->exigirColunasOferta();

        return DB::transaction(function () use ($config, $entregador, $pedidoId) {
            $pedido = DB::table('dlv_pedidos')
                ->where('id', $pedidoId)
                ->where('unidade_id', $config->unidade_id)
                ->lockForUpdate()
                ->first();

            abort_unless($pedido, 404, 'Pedido não encontrado.');

            if (strtolower(trim((string) ($pedido->oferta_status ?? ''))) !== 'aberta') {
                throw ValidationException::withMessages(['oferta' => 'Esta entrega não está mais disponível.']);
            }

            if (Schema::hasTable('dlv_pedido_oferta_recusas')) {
                $jaRecusou = DB::table('dlv_pedido_oferta_recusas')
                    ->where('pedido_id', $pedido->id)
                    ->where('entregador_id', $entregador->id)
                    ->exists();
                if ($jaRecusou) {
                    throw ValidationException::withMessages(['oferta' => 'Você já recusou esta entrega.']);
                }
            }

            $token = trim((string) ($pedido->entregador_token ?? ''));
            if ($token === '') {
                $token = Str::random(40);
            }

            DB::table('dlv_pedidos')->where('id', $pedido->id)->update([
                'oferta_status' => 'aceita',
                'oferta_aceita_em' => now(),
                'entregador_id' => $entregador->id,
                'entregador_token' => $token,
                'status' => 'rota',
                'updated_at' => now(),
            ]);

            $pedido = DB::table('dlv_pedidos')->where('id', $pedido->id)->first();
            $itens = DB::table('dlv_pedido_itens')->where('pedido_id', $pedido->id)->orderBy('ordem')->orderBy('id')->get();

            $urlEntrega = route('delivery.public.entregador.show', [
                'slug' => $config->slug,
                'codigo' => $pedido->codigo_publico,
                'token' => $token,
            ], absolute: true);

            $cupom = DeliveryCupomPedido::textoCupomCompleto($pedido, $config, $itens);

            return [
                'ok' => true,
                'mensagem' => 'Entrega aceita. Pedido atribuído a você.',
                'pedido_id' => (int) $pedido->id,
                'codigo_publico' => (string) $pedido->codigo_publico,
                'url_entrega' => $urlEntrega,
                'cupom_texto' => $cupom,
                'url_cupom_whatsapp' => DeliveryWhatsAppHelper::urlComTexto(
                    $entregador->whatsapp ?: $entregador->telefone,
                    $cupom
                ),
            ];
        });
    }

    public function recusarOferta(object $config, object $entregador, int $pedidoId): array
    {
        $pedido = DB::table('dlv_pedidos')
            ->where('id', $pedidoId)
            ->where('unidade_id', $config->unidade_id)
            ->first();

        abort_unless($pedido, 404, 'Pedido não encontrado.');

        if (strtolower(trim((string) ($pedido->oferta_status ?? ''))) !== 'aberta') {
            throw ValidationException::withMessages(['oferta' => 'Esta entrega não está mais disponível.']);
        }

        if (Schema::hasTable('dlv_pedido_oferta_recusas')) {
            DB::table('dlv_pedido_oferta_recusas')->updateOrInsert(
                ['pedido_id' => $pedido->id, 'entregador_id' => $entregador->id],
                ['created_at' => now()]
            );
        }

        return [
            'ok' => true,
            'mensagem' => 'Você recusou esta entrega. Ela continua disponível para outros motoboys.',
        ];
    }

    private function exigirColunasOferta(): void
    {
        if (! Schema::hasColumn('dlv_pedidos', 'oferta_status')) {
            throw ValidationException::withMessages([
                'oferta' => 'Execute a migration de ofertas de entregador antes de usar este recurso.',
            ]);
        }
    }
}
