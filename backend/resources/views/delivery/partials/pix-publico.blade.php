@php
    use App\Support\Delivery\DeliveryLojaCheckoutHelper;
    use App\Support\Delivery\DeliveryPedidoPresenter;
    $pixPayload = $pixPayload ?? trim((string) ($pedido->pagamento_pix_payload ?? '')) ?: null;
    $pixConfigurada = $pixConfigurada ?? (DeliveryLojaCheckoutHelper::pixConfiguradaParaCheckout($config) || $pixPayload !== null);
    $pixQrDataUri = $pixQrDataUri ?? ($pixPayload
        ? DeliveryLojaCheckoutHelper::pixQrCodeDataUri((object) ['pix_copia_cola' => $pixPayload])
        : DeliveryLojaCheckoutHelper::pixQrCodeDataUri($config));
    $pixAutomatico = $pixAutomatico ?? (trim((string) ($pedido->pagamento_externo_id ?? '')) !== '');
    $isPix = DeliveryPedidoPresenter::isPix($pedido);
    $pixPago = DeliveryPedidoPresenter::isPixPago($pedido);
    $copiaCola = $pixPayload ?: trim((string) ($config->pix_copia_cola ?? ''));
    $mostrarChaveManual = ! $pixAutomatico && trim((string)($config->pix_chave ?? '')) !== '';
@endphp
@if($isPix)
<section class="form-card vf-pix-public" id="vf-pix-public-panel">
    <h2>Pagamento PIX</h2>
    @if($pixPago)
        <div class="vf-fid-alert vf-fid-alert--ok vf-pix-status" role="status">
            <strong>PIX confirmado.</strong> A loja já recebeu seu pagamento.
        </div>
    @else
        <div class="vf-fid-alert vf-fid-alert--warn vf-pix-status" role="status">
            <strong>Aguardando confirmação do PIX.</strong> Pague o valor de <strong>R$ {{ number_format((float) $pedido->total, 2, ',', '.') }}</strong>@if($pixAutomatico) — a confirmação é automática após o pagamento.@else e informe o código do pedido <strong>{{ $pedido->codigo_publico }}</strong> se a loja pedir.@endif
        </div>
    @endif

    @if($pixConfigurada && ! $pixPago)
        <div class="pay-extra-panel pay-extra-panel--compact">
            @if($mostrarChaveManual)
            <label class="field-block field-block--compact">Chave PIX ({{ DeliveryLojaCheckoutHelper::pixChaveRotuloTipo($config) }})
                <div class="input-copy-row">
                    <input readonly id="field-pix-chave-public" value="{{ $config->pix_chave }}">
                    <button type="button" class="btn btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('field-pix-chave-public').value).then(()=>alert('Chave PIX copiada.'))">Copiar</button>
                </div>
                @if(trim((string)($config->pix_banco ?? '')) !== '')<small class="muted">Banco: {{ $config->pix_banco }}</small>@endif
            </label>
            @endif
            @if(! $pixAutomatico && trim((string)($config->pix_instrucoes ?? '')) !== '')
                <div class="pix-instructions pix-instructions--compact">{!! nl2br(e($config->pix_instrucoes)) !!}</div>
            @elseif($pixAutomatico)
                <p class="checkout-freight-note">Use o QR Code ou copia e cola abaixo — valor exclusivo deste pedido.</p>
            @endif
            @if($copiaCola !== '')
            <label class="field-block field-block--compact">Pix copia e cola
                <textarea readonly rows="2" id="field-pix-copia-public">{{ $copiaCola }}</textarea>
            </label>
            <button type="button" class="btn btn-sm" onclick="(function(){const t=document.getElementById('field-pix-copia-public');navigator.clipboard.writeText(t.value).then(()=>alert('Código PIX copiado.'));})();">Copiar código PIX</button>
            @endif
            @if($pixQrDataUri)
                <div class="pix-qr-wrap pix-qr-wrap--compact">
                    <p class="checkout-freight-note">Escaneie com o app do banco</p>
                    <img src="{{ $pixQrDataUri }}" alt="QR Code PIX" class="pix-qr pix-qr--compact" id="vf-pix-qr-img">
                </div>
            @endif
        </div>
    @endif
</section>
@if(($pixPollUrl ?? null) && ! $pixPago && $pixAutomatico)
@push('scripts')
<script>
(function(){
  const pollUrl = @json($pixPollUrl);
  let stopped = false;
  async function poll(){
    if (stopped || !pollUrl) return;
    try {
      const res = await fetch(pollUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
      const data = await res.json().catch(() => ({}));
      if (data.pix_pago || data.pagamento_status === 'pago') {
        stopped = true;
        const panel = document.getElementById('vf-pix-public-panel');
        const status = panel?.querySelector('.vf-pix-status');
        if (status) {
          status.className = 'vf-fid-alert vf-fid-alert--ok vf-pix-status';
          status.innerHTML = '<strong>PIX confirmado.</strong> A loja já recebeu seu pagamento.';
        }
        return;
      }
      if (data.payload && document.getElementById('field-pix-copia-public')) {
        document.getElementById('field-pix-copia-public').value = data.payload;
      }
      if (data.qr_data_uri && document.getElementById('vf-pix-qr-img')) {
        document.getElementById('vf-pix-qr-img').src = data.qr_data_uri;
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
