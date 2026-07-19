@php
    use App\Support\Delivery\DeliveryPedidoPresenter;
    $nomeLoja = trim((string) ($config->nome_loja ?? 'Loja'));
    $enderecoLinha = DeliveryPedidoPresenter::enderecoLinha($pedido);
    $cep = preg_replace('/\D+/', '', (string) ($pedido->endereco_cep ?? ''));
    $podeRegistrar = DeliveryPedidoPresenter::entregadorPodeRegistrarResultado($pedido->status ?? null);
    $statusRotulo = DeliveryPedidoPresenter::rotuloStatus($pedido->status ?? null);
    $createdAt = \Illuminate\Support\Carbon::parse($pedido->created_at ?? now());
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Entrega · {{ $nomeLoja }}</title>
    <style>
        :root { font-family: "Segoe UI", system-ui, sans-serif; color: #1f2937; }
        body { margin: 0; background: #f3f4f6; }
        .wrap { max-width: 520px; margin: 0 auto; padding: 16px; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; margin-bottom: 12px; }
        .card.highlight { border: 2px solid #93c5fd; }
        .muted { color: #6b7280; font-size: 13px; }
        .code { font-family: ui-monospace, monospace; font-size: 28px; font-weight: 700; color: #2563eb; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 12px; background: #dbeafe; color: #1d4ed8; }
        .item { padding: 10px 0; border-bottom: 1px solid #f3f4f6; }
        .item:last-child { border-bottom: 0; }
        .row { display: flex; justify-content: space-between; gap: 12px; }
        .btn { display: block; width: 100%; border: 0; border-radius: 10px; padding: 14px 16px; font-size: 16px; font-weight: 600; cursor: pointer; text-align: center; text-decoration: none; }
        .btn-success { background: #16a34a; color: #fff; }
        .btn-warning { background: #f59e0b; color: #111; }
        .btn-danger { background: #fff; color: #dc2626; border: 1px solid #fecaca; }
        .alert { padding: 12px; border-radius: 10px; font-size: 14px; margin-bottom: 12px; }
        .alert-ok { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-warn { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
        .opcoes { margin-top: 6px; padding-left: 10px; border-left: 2px solid #e5e7eb; font-size: 12px; color: #6b7280; }
        .total { font-weight: 700; font-size: 18px; color: #059669; }
        form { display: grid; gap: 8px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="muted">{{ $nomeLoja }}</div>
    <h1 style="font-size:20px;margin:4px 0 16px">Entrega</h1>

    @if (session('status'))
        <div class="alert alert-ok">{{ session('status') }}</div>
    @endif
    @if (session('warning'))
        <div class="alert alert-warn">{{ session('warning') }}</div>
    @endif

    <div class="card highlight">
        <div class="muted">Confirme com o cliente</div>
        <div class="code">{{ $pedido->codigo_publico }}</div>
        <p class="muted" style="margin:8px 0 0">
            Pedido em {{ $createdAt->format('d/m/Y H:i') }}
            · {{ DeliveryPedidoPresenter::rotuloFulfillment($pedido->fulfillment ?? null) }}
        </p>
    </div>

    <div class="card">
        <strong>Cliente</strong>
        <p style="margin:8px 0 4px">{{ $pedido->cliente_nome }}</p>
        @if ($pedido->cliente_telefone)
            <a href="tel:{{ preg_replace('/\D+/', '', (string) $pedido->cliente_telefone) }}">{{ $pedido->cliente_telefone }}</a>
        @endif
    </div>

    <div class="card">
        <strong>Endereço</strong>
        @if ($enderecoLinha !== '')
            <p style="margin:8px 0 0">{{ $enderecoLinha }}@if ($pedido->endereco_complemento), {{ $pedido->endereco_complemento }}@endif</p>
        @else
            <p class="muted" style="margin:8px 0 0">Endereço não registrado. Confira o CEP com o cliente.</p>
        @endif
        @if (strlen($cep) === 8)
            <p class="muted" style="margin:6px 0 0">CEP {{ substr($cep, 0, 5) }}-{{ substr($cep, 5) }}</p>
        @endif
    </div>

    <div class="card">
        <strong>Itens e valores</strong>
        @foreach ($itens as $it)
            @php
                $opcoes = $it->opcoes_json;
                if (is_string($opcoes)) { $opcoes = json_decode($opcoes, true); }
                $opcoesLinha = DeliveryPedidoPresenter::opcoesLinhaParaExibicao(is_array($opcoes) ? $opcoes : []);
            @endphp
            <div class="item">
                <div class="row">
                    <span><strong>{{ $it->nome_produto }}</strong> × {{ rtrim(rtrim(number_format((float) $it->quantidade, 3, '.', ''), '0'), '.') }}</span>
                    <span>R$ {{ number_format((float) $it->subtotal, 2, ',', '.') }}</span>
                </div>
                @include('delivery.partials.opcoes-pedido-item', ['opcoesLinha' => $opcoesLinha])
            </div>
        @endforeach
        <div class="row muted" style="margin-top:10px;padding-top:10px;border-top:1px solid #e5e7eb"><span>Subtotal</span><span>R$ {{ number_format((float) $pedido->subtotal, 2, ',', '.') }}</span></div>
        <div class="row muted"><span>Taxa de entrega</span><span>R$ {{ number_format((float) $pedido->frete_valor, 2, ',', '.') }}</span></div>
        <div class="row total" style="margin-top:8px;padding-top:8px;border-top:1px solid #e5e7eb"><span>Total</span><span>R$ {{ number_format((float) $pedido->total, 2, ',', '.') }}</span></div>
    </div>

    <div class="card">
        <strong>Pagamento</strong>
        <p style="margin:8px 0 0">{{ DeliveryPedidoPresenter::descricaoPagamento($pedido) }}</p>
        @if ($pedido->observacoes)
            <p class="muted" style="margin:8px 0 0"><strong>Obs.:</strong> {{ $pedido->observacoes }}</p>
        @endif
    </div>

    <div style="margin-bottom:12px"><span class="badge">{{ $statusRotulo }}</span></div>

    @if ($podeRegistrar)
        <form action="{{ route('delivery.public.entregador.registrar', ['slug' => $slug, 'codigo' => $pedido->codigo_publico, 'token' => $token]) }}" method="post">
            @csrf
            <button type="submit" name="resultado" value="entregue" class="btn btn-success">Entregue</button>
            <button type="submit" name="resultado" value="endereco" class="btn btn-warning">Não encontrei o endereço</button>
            <button type="submit" name="resultado" value="cancelado" class="btn btn-danger">Cancelado</button>
        </form>
        <p class="muted" style="margin-top:12px">Ao confirmar, a loja verá o novo status no painel.</p>
    @else
        <div class="card muted">Resultado já registrado ou pedido não está pronto/em rota.</div>
    @endif
</div>
</body>
</html>
