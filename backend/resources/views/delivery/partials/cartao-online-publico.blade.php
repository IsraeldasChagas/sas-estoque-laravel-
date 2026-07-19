@php
    use App\Support\Delivery\DeliveryPedidoPresenter;
    $isCartaoOnline = DeliveryPedidoPresenter::isCartaoOnline($pedido);
    $cartaoPago = $cartaoOnlinePago ?? DeliveryPedidoPresenter::isCartaoOnlinePago($pedido);
    $checkoutUrl = $cartaoCheckoutUrl ?? trim((string) ($pedido->pagamento_checkout_url ?? '')) ?: null;
    $pollUrl = $cartaoPollUrl ?? null;
@endphp
@if($isCartaoOnline)
<section class="form-card vf-cartao-online-public" id="vf-cartao-online-panel">
    <h2>Pagamento online</h2>
    @if($cartaoPago)
        <div class="vf-fid-alert vf-fid-alert--ok vf-cartao-status" role="status">
            <strong>Pagamento confirmado.</strong> Seu cartão foi aprovado e a loja já recebeu o pagamento.
        </div>
    @else
        <div class="vf-fid-alert vf-fid-alert--warn vf-cartao-status" role="status">
            <strong>Aguardando pagamento online.</strong> Valor: <strong>R$ {{ number_format((float) $pedido->total, 2, ',', '.') }}</strong>
        </div>
        @if($checkoutUrl)
            <p class="checkout-freight-note">Clique abaixo para pagar com cartão de crédito ou débito no ambiente seguro do gateway.</p>
            <a class="btn primary wide" href="{{ $checkoutUrl }}" target="_blank" rel="noopener noreferrer" id="vf-cartao-checkout-link">Pagar com cartão agora</a>
        @else
            <p class="checkout-freight-note muted">Link de pagamento indisponível. Entre em contato com a loja informando o código <strong>{{ $pedido->codigo_publico }}</strong>.</p>
        @endif
    @endif
</section>
@if($pollUrl && ! $cartaoPago)
@push('scripts')
<script>
(function(){
  const pollUrl = @json($pollUrl);
  let stopped = false;
  async function poll(){
    if (stopped || !pollUrl) return;
    try {
      const res = await fetch(pollUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
      const data = await res.json().catch(() => ({}));
      if (data.cartao_online_pago || data.pagamento_status === 'pago') {
        stopped = true;
        const panel = document.getElementById('vf-cartao-online-panel');
        const status = panel?.querySelector('.vf-cartao-status');
        if (status) {
          status.className = 'vf-fid-alert vf-fid-alert--ok vf-cartao-status';
          status.innerHTML = '<strong>Pagamento confirmado.</strong> Seu cartão foi aprovado e a loja já recebeu o pagamento.';
        }
        document.getElementById('vf-cartao-checkout-link')?.remove();
        return;
      }
      if (data.checkout_url && document.getElementById('vf-cartao-checkout-link')) {
        document.getElementById('vf-cartao-checkout-link').href = data.checkout_url;
      }
    } catch (_) {}
    if (!stopped) setTimeout(poll, 8000);
  }
  setTimeout(poll, 4000);
})();
</script>
@endpush
@endif
@endif
