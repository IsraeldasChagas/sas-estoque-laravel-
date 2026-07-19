@extends('delivery.public.layout')
@section('title', 'Carrinho · '.($config->nome_loja ?: 'Loja'))
@section('content')
<h1>Carrinho</h1>
<div id="vf-cart-empty" class="form-card cart-empty-state" hidden>
    <p>Seu carrinho está vazio.</p>
    <a class="btn primary" href="{{ route('delivery.public.store', $slug) }}">Ver cardápio</a>
</div>
<div id="vf-cart-page" class="checkout-grid" hidden>
    <div class="cart-page-main">
        <section class="form-card">
            <h2>Itens do carrinho</h2>
            <div class="cart-page-table-wrap">
                <table class="cart-page-table">
                    <thead><tr><th>Item</th><th>Qtd</th><th>Unit.</th><th>Total</th><th></th></tr></thead>
                    <tbody id="vf-cart-lines"></tbody>
                </table>
            </div>
            <a class="btn ghost" href="{{ route('delivery.public.store', $slug) }}">← Continuar comprando</a>
        </section>
    </div>
    <aside class="order-summary cart-page-sidebar">
        <div id="vf-cart-balcao-alert" class="checkout-alert checkout-alert--info" hidden>
            <strong>Retirada no balcão</strong> está selecionada — a taxa de entrega é <strong>R$ 0,00</strong>.
        </div>
        <section class="form-card">
            <h2>Como receber</h2>
            <div class="choice-row vf-cart-prefs">
                <label><input type="radio" name="modo" value="entrega" checked> Entrega</label>
                @if($permiteBalcao)
                    <label><input type="radio" name="modo" value="balcao"> Retirada no balcão <span class="muted">(sem taxa)</span></label>
                @endif
            </div>
            <label class="field-block">CEP <span class="muted">(para simular frete)</span>
                <div class="input-group-inline">
                    <input id="vf-cart-cep" maxlength="9" placeholder="00000-000" autocomplete="postal-code" value="{{ strlen($prefs['cep'] ?? '') === 8 ? substr($prefs['cep'],0,5).'-'.substr($prefs['cep'],5) : '' }}">
                    <button type="button" class="btn" id="vf-cart-prefs-btn">Atualizar</button>
                </div>
            </label>
            <p class="checkout-freight-note">No checkout o endereço completo é obrigatório para entrega.</p>
        </section>
        <section class="form-card">
            <h2>Resumo</h2>
            <div class="summary-line"><span>Subtotal</span><strong id="vf-cart-subtotal">R$ 0,00</strong></div>
            <div class="summary-line"><span>Taxa entrega</span><strong id="vf-cart-taxa">R$ 0,00</strong></div>
            <small id="vf-cart-taxa-rotulo" class="checkout-freight-note"></small>
            <div id="vf-cart-bloqueado" class="checkout-alert checkout-alert--warn" hidden>Este CEP parece fora da área de entrega. Ajuste o CEP ou escolha retirada no balcão.</div>
            <div class="grand-total"><span>Total</span><strong id="vf-cart-total">R$ 0,00</strong></div>
            <a id="vf-cart-checkout-btn" class="btn success wide" href="{{ route('delivery.public.checkout', $slug) }}">Ir para checkout</a>
            <span id="vf-cart-checkout-disabled" class="btn wide is-disabled" hidden>Fora da área — ajuste o CEP</span>
        </section>
    </aside>
</div>
@push('scripts')
<script>
(function(){
  const items = DeliveryCart.items();
  const empty = document.getElementById('vf-cart-empty');
  const page = document.getElementById('vf-cart-page');
  if (!items.length) { empty.hidden = false; return; }
  page.hidden = false;
  const prefsUrl = @json($prefsUrl);
  const initialPrefs = @json(['modo' => \App\Support\Delivery\DeliveryLojaCheckoutHelper::tipoEntregaFromFulfillment($prefs['modo']), 'cep' => $prefs['cep']]);
  let taxa = 0, rotulo = '', bloqueada = false, subtotal = 0;
  function money(v){ return DeliveryCart.money(v); }
  function unit(item){
    return Number(item.preco) + (item.opcoes?.adicionais||[]).reduce((s,a)=>s+Number(a.preco)*Number(a.quantidade),0);
  }
  function renderLines(){
    subtotal = items.reduce((s,i)=>s+unit(i)*Number(i.quantidade),0);
    document.getElementById('vf-cart-subtotal').textContent = money(subtotal);
    document.getElementById('vf-cart-lines').innerHTML = items.map((item, index) => {
      const opts = [
        ...(item.opcoes?.adicionais||[]).map(a=>`${a.quantidade}× ${DeliveryCart.escape(a.nome)}`),
        ...(item.opcoes?.retiradas||[]).map(r=>`Sem ${DeliveryCart.escape(r.nome)}`)
      ].join(', ');
      return `<tr>
        <td><strong>${DeliveryCart.escape(item.nome)}</strong>${opts?`<small>${opts}</small>`:''}</td>
        <td><input type="number" min="1" max="99" value="${item.quantidade}" data-qty="${index}" class="qty-input"></td>
        <td>${money(unit(item))}</td>
        <td>${money(unit(item)*item.quantidade)}</td>
        <td><button type="button" class="btn ghost" data-remove="${index}">Remover</button></td>
      </tr>`;
    }).join('');
    document.querySelectorAll('[data-qty]').forEach(inp=>inp.onchange=function(){
      const list = DeliveryCart.items(); const i = Number(this.dataset.qty);
      list[i].quantidade = Math.max(1, Math.min(99, Number(this.value)||1));
      localStorage.setItem('delivery-cart:'+window.deliveryStore.slug, JSON.stringify(list));
      location.reload();
    });
    document.querySelectorAll('[data-remove]').forEach(btn=>btn.onclick=function(){
      const list = DeliveryCart.items(); list.splice(Number(this.dataset.remove),1);
      localStorage.setItem('delivery-cart:'+window.deliveryStore.slug, JSON.stringify(list));
      location.reload();
    });
  }
  function syncResumo(){
    const modo = document.querySelector('input[name=modo]:checked')?.value || 'entrega';
    const isEnt = modo === 'entrega';
    document.getElementById('vf-cart-balcao-alert').hidden = !(!isEnt);
    const taxaShow = isEnt ? (bloqueada ? 0 : taxa) : 0;
    const total = isEnt && bloqueada ? subtotal : subtotal + taxaShow;
    document.getElementById('vf-cart-taxa').textContent = money(taxaShow);
    document.getElementById('vf-cart-taxa-rotulo').textContent = isEnt ? rotulo : 'Retirada no balcão';
    document.getElementById('vf-cart-total').textContent = money(total);
    document.getElementById('vf-cart-bloqueado').hidden = !(isEnt && bloqueada);
    document.getElementById('vf-cart-checkout-btn').hidden = isEnt && bloqueada;
    document.getElementById('vf-cart-checkout-disabled').hidden = !(isEnt && bloqueada);
  }
  async function updatePrefs(){
    const modo = document.querySelector('input[name=modo]:checked')?.value || 'entrega';
    const cep = document.getElementById('vf-cart-cep').value;
    const res = await fetch(prefsUrl, { method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':window.deliveryStore.csrf}, body:JSON.stringify({modo, cep, subtotal})});
    const data = await res.json();
    taxa = Number(data.taxa||0); rotulo = data.rotulo||''; bloqueada = !!data.entrega_bloqueada;
    syncResumo();
  }
  document.querySelectorAll('input[name=modo]').forEach(r=>r.onchange=updatePrefs);
  document.getElementById('vf-cart-prefs-btn').onclick = updatePrefs;
  if (initialPrefs.modo === 'balcao') {
    const balcao = document.querySelector('input[name=modo][value=balcao]');
    if (balcao) balcao.checked = true;
  }
  renderLines(); updatePrefs();
})();
</script>
@endpush
@endsection
