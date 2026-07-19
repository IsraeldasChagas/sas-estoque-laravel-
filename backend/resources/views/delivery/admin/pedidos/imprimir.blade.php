@php
    use App\Support\Delivery\DeliveryCupomPedido;
    use App\Support\Delivery\DeliveryPedidoPresenter;
    $nomeLoja = trim((string) ($config->nome_loja ?? 'Loja'));
    $logoUrl = DeliveryPedidoPresenter::logoUrl($config);
    $enderecoLinha = DeliveryPedidoPresenter::enderecoLinha($pedido);
    $createdAt = \Illuminate\Support\Carbon::parse($pedido->created_at ?? now());
    $cep = preg_replace('/\D+/', '', (string) ($pedido->endereco_cep ?? ''));
    $waCupom = DeliveryCupomPedido::urlWhatsAppCupom($pedido, $config, $itens);
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cupom {{ $pedido->codigo_publico }} — {{ $nomeLoja }}</title>
    <style>
        @page { size: 80mm auto; margin: 5mm; }
        :root { font-family: ui-sans-serif, system-ui, "Segoe UI", Roboto, sans-serif; font-size: 11px; line-height: 1.35; color: #111; }
        body { margin: 0 auto; padding: 10px 8px 24px; max-width: 72mm; }
        .cupom-head { text-align: center; margin-bottom: 8px; }
        .cupom-logo { display: block; margin: 0 auto 8px; max-width: 46mm; max-height: 22mm; object-fit: contain; }
        .cupom-marca { font-size: 10px; letter-spacing: 0.12em; text-transform: uppercase; color: #444; margin: 0 0 2px; }
        .cupom-nome { font-size: 14px; font-weight: 800; margin: 0 0 6px; text-transform: uppercase; letter-spacing: 0.03em; line-height: 1.2; }
        .cupom-meta { font-size: 10px; color: #444; margin: 2px 0; }
        hr.sep { border: none; border-top: 1px dashed #555; margin: 10px 0; }
        .cupom-sec { font-size: 10px; font-weight: 700; letter-spacing: 0.1em; margin: 12px 0 6px; color: #333; }
        .cupom-codigo { font-family: ui-monospace, monospace; font-size: 18px; font-weight: 700; letter-spacing: 0.06em; margin: 4px 0 8px; text-align: center; }
        .item-row { display: flex; justify-content: space-between; gap: 8px; align-items: flex-start; padding: 6px 0; border-bottom: 1px dotted #ccc; }
        .item-row:last-of-type { border-bottom: none; }
        .item-nome { flex: 1; min-width: 0; font-weight: 600; }
        .item-val { white-space: nowrap; font-variant-numeric: tabular-nums; }
        .item-opcoes { margin-top: 4px; font-size: 10px; color: #444; }
        .item-opcoes-inner .muted { color: #666; }
        .item-op-list { margin: 4px 0 0; padding-left: 14px; }
        table.tot { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 11px; }
        table.tot td { padding: 3px 0; vertical-align: top; }
        table.tot td:last-child { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        table.tot tr.total td { font-weight: 800; font-size: 13px; padding-top: 8px; border-top: 2px solid #111; }
        .cupom-link { word-break: break-all; font-size: 9px; margin-top: 6px; }
        .cupom-rodape { text-align: center; font-size: 10px; color: #555; margin-top: 14px; padding-top: 10px; border-top: 1px dashed #999; }
        .no-print { margin-top: 20px; text-align: center; display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; }
        .no-print button, .no-print a.btn-link { padding: 10px 16px; font-size: 14px; cursor: pointer; border-radius: 8px; border: 1px solid #333; background: #fff; text-decoration: none; color: #111; display: inline-block; }
        .no-print .wa { background: #25d366; border-color: #128c7e; color: #fff; }
        @media print { .no-print { display: none !important; } body { padding: 0; } }
    </style>
</head>
<body>
    <div class="cupom-head">
        @if ($logoUrl)
            <img src="{{ url($logoUrl) }}" alt="" class="cupom-logo">
        @endif
        <p class="cupom-marca mb-0">Pedido online</p>
        <h1 class="cupom-nome">{{ $nomeLoja }}</h1>
        @if (trim((string) ($config->endereco_texto ?? '')))
            <p class="cupom-meta mb-0">{{ $config->endereco_texto }}</p>
        @endif
        @if (trim((string) ($config->whatsapp ?? '')))
            <p class="cupom-meta mb-0">WhatsApp loja: {{ $config->whatsapp }}</p>
        @endif
    </div>

    <hr class="sep">
    <div class="cupom-meta" style="text-align:center">Cupom fiscal simplificado / comanda</div>
    <div class="cupom-codigo">{{ $pedido->codigo_publico }}</div>
    <p class="cupom-meta mb-0" style="text-align:center">
        {{ $createdAt->format('d/m/Y H:i') }}
        · {{ DeliveryPedidoPresenter::rotuloStatus($pedido->status ?? null) }}
        · {{ DeliveryPedidoPresenter::rotuloFulfillment($pedido->fulfillment ?? null) }}
    </p>

    <hr class="sep">
    <div class="cupom-sec">Cliente e entrega</div>
    <p class="mb-1"><strong>{{ $pedido->cliente_nome }}</strong></p>
    <p class="cupom-meta mb-0">{{ $pedido->cliente_telefone }}</p>
    @if (trim((string) ($pedido->cliente_email ?? '')))
        <p class="cupom-meta mb-0">{{ $pedido->cliente_email }}</p>
    @endif
    @if ($enderecoLinha !== '')
        <p class="mb-0 mt-2">{{ $enderecoLinha }}@if ($pedido->endereco_complemento), {{ $pedido->endereco_complemento }}@endif</p>
    @endif
    @if (strlen($cep) === 8)
        <p class="cupom-meta mb-0">CEP {{ substr($cep, 0, 5) }}-{{ substr($cep, 5) }}</p>
    @endif

    <div class="cupom-sec">Itens</div>
    @foreach ($itens as $it)
        @php
            $opcoes = $it->opcoes_json;
            if (is_string($opcoes)) { $opcoes = json_decode($opcoes, true); }
            $opcoesLinha = DeliveryPedidoPresenter::opcoesLinhaParaExibicao(is_array($opcoes) ? $opcoes : []);
        @endphp
        <div class="item-row">
            <div class="item-nome">
                {{ $it->nome_produto }} × {{ rtrim(rtrim(number_format((float) $it->quantidade, 3, '.', ''), '0'), '.') }}
                <div class="item-opcoes">
                    @include('delivery.partials.opcoes-pedido-item', ['opcoesLinha' => $opcoesLinha])
                </div>
            </div>
            <div class="item-val">R$ {{ number_format((float) $it->subtotal, 2, ',', '.') }}</div>
        </div>
    @endforeach

    <table class="tot">
        <tr><td>Subtotal</td><td>R$ {{ number_format((float) $pedido->subtotal, 2, ',', '.') }}</td></tr>
        <tr>
            <td>{{ strtolower(trim((string) ($pedido->fulfillment ?? 'entrega'))) === 'entrega' ? 'Taxa de entrega' : 'Retirada (sem frete)' }}</td>
            <td>R$ {{ number_format((float) $pedido->frete_valor, 2, ',', '.') }}</td>
        </tr>
        <tr class="total"><td>Total</td><td>R$ {{ number_format((float) $pedido->total, 2, ',', '.') }}</td></tr>
    </table>

    <div class="cupom-sec">Pagamento</div>
    <p class="mb-0">{{ DeliveryPedidoPresenter::descricaoPagamento($pedido) }}</p>

    @if (trim((string) ($pedido->observacoes ?? '')))
        <div class="cupom-sec">Observações</div>
        <p class="mb-0">{{ $pedido->observacoes }}</p>
    @endif

    @if (($config->slug ?? '') && ($pedido->codigo_publico ?? '') && strlen(trim((string) ($pedido->cliente_token ?? ''))) === 64)
        <div class="cupom-sec">Acompanhar pedido</div>
        <p class="cupom-link mb-0">{{ route('delivery.public.order', ['slug' => $config->slug, 'codigo' => $pedido->codigo_publico, 'token' => $pedido->cliente_token], absolute: true) }}</p>
    @endif

    <div class="cupom-rodape">Obrigado pela preferência!<br>{{ config('app.name') }}</div>

    <div class="no-print">
        <button type="button" onclick="window.print()">Imprimir na térmica</button>
        @if ($waCupom)
            <a class="wa btn-link" href="{{ $waCupom }}" target="_blank" rel="noopener noreferrer">Enviar cupom no WhatsApp</a>
        @endif
        <button type="button" onclick="window.close()">Fechar</button>
    </div>
    <script>
        if (new URLSearchParams(location.search).get('auto') === '1') {
            window.addEventListener('load', function () { window.print(); });
        }
    </script>
</body>
</html>
