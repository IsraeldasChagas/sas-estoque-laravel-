@extends('delivery.public.layout')
@section('title', 'Checkout · '.($config->nome_loja ?: 'Loja'))
@section('content')
@include('delivery.public.partials.voltar', [
    'voltarLabel' => 'Voltar ao cardápio',
    'voltarExtra' => '<button type="button" class="vf-back-btn vf-back-btn--ghost" data-cart-open>Ver carrinho</button>',
])
<nav class="breadcrumb"><a href="{{ route('delivery.public.store', $slug) }}">Cardápio</a> / Checkout</nav>
<h1>Finalizar pedido</h1>
<div class="checkout-grid">
<form class="checkout-form" data-checkout-form>
    <section class="form-card"><h2>Seus dados</h2><div class="form-grid">
        <label>Nome completo<input name="cliente_nome" required maxlength="160"></label>
        <label>Telefone<input name="cliente_telefone" required maxlength="30" inputmode="tel"></label>
        <label>WhatsApp (opcional)<input name="cliente_whatsapp" maxlength="30" inputmode="tel"></label>
        <label>E-mail (opcional)<input name="cliente_email" type="email" maxlength="190"></label>
    </div></section>
    <section class="form-card"><h2>Recebimento</h2><div class="choice-row">
        <label><input type="radio" name="fulfillment" value="entrega" checked> Entrega</label>
        @if($config->permite_retirada)<label><input type="radio" name="fulfillment" value="retirada"> Retirada na loja</label>@endif
    </div>
    <div class="form-grid" data-address>
        <label>CEP<input name="endereco_cep" maxlength="12"></label><label>Rua<input name="endereco_rua" maxlength="180"></label>
        <label>Número<input name="endereco_numero" maxlength="40"></label><label>Bairro<input name="endereco_bairro" maxlength="120"></label>
        <label>Cidade<input name="endereco_cidade" maxlength="120"></label><label>UF<input name="endereco_uf" maxlength="2"></label>
        <label class="span-2">Complemento<input name="endereco_complemento" maxlength="500"></label>
    </div><button type="button" class="btn ghost" data-freight>Calcular frete</button><p data-freight-result></p></section>
    <section class="form-card"><h2>Pagamento</h2><div class="payment-list">
        @foreach($pagamentos as $value=>$label)<label><input type="radio" name="pagamento_forma" value="{{ $value }}" @checked($loop->first)> {{ $label }}</label>@endforeach
    </div>
    @if(isset($pagamentos['pix']) && $config->pix_chave)<div class="pix-box"><strong>Chave PIX</strong><code>{{ $config->pix_chave }}</code>@if($config->pix_beneficiario)<small>{{ $config->pix_beneficiario }}</small>@endif</div>@endif
    <label>Observações<textarea name="observacoes" rows="3" maxlength="1000"></textarea></label></section>
    <div class="form-error" data-checkout-error></div>
    <button class="btn success wide" type="submit">Confirmar pedido</button>
    <a class="btn ghost wide vf-back-after-action" href="{{ route('delivery.public.store', $slug) }}">← Continuar comprando</a>
</form>
<aside class="order-summary"><h2>Resumo</h2><div data-checkout-items></div><hr><div><span>Subtotal</span><strong data-summary-subtotal></strong></div><div><span>Frete</span><strong data-summary-freight>—</strong></div><div class="grand-total"><span>Total</span><strong data-summary-total></strong></div></aside>
</div>
@push('scripts')
<script>
(function(){
 const form=document.querySelector('[data-checkout-form]'), items=DeliveryCart.items();
 if(!items.length){location.href=@json(route('delivery.public.store',$slug));return}
 const apiItems=()=>items.map(i=>({produto_id:i.produto_id,quantidade:i.quantidade,opcoes:{adicionais:(i.opcoes.adicionais||[]).map(a=>({id:a.id,quantidade:a.quantidade})),retiradas:(i.opcoes.retiradas||[]).map(r=>({id:r.id}))}}));
 const subtotal=items.reduce((s,i)=>s+(i.preco+(i.opcoes.adicionais||[]).reduce((a,x)=>a+x.preco*x.quantidade,0))*i.quantidade,0);
 document.querySelector('[data-checkout-items]').innerHTML=items.map(i=>'<p><strong>'+DeliveryCart.escape(i.nome)+'</strong><small>'+i.quantidade+'×</small></p>').join('');
 document.querySelector('[data-summary-subtotal]').textContent=DeliveryCart.money(subtotal); document.querySelector('[data-summary-total]').textContent=DeliveryCart.money(subtotal);
 function fulfillment(){return form.elements.fulfillment.value}
 function addressState(){document.querySelector('[data-address]').hidden=fulfillment()!=='entrega'} form.querySelectorAll('[name=fulfillment]').forEach(x=>x.onchange=addressState);addressState();
 async function freight(){
  const res=await fetch(@json(route('delivery.public.freight',$slug)),{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':window.deliveryStore.csrf},body:JSON.stringify({fulfillment:fulfillment(),cep:form.elements.endereco_cep.value,itens:apiItems()})});
  const data=await res.json(); if(!res.ok)throw new Error(Object.values(data.errors||{})[0]?.[0]||data.message||'Não foi possível calcular.');
  document.querySelector('[data-summary-subtotal]').textContent=DeliveryCart.money(data.subtotal);document.querySelector('[data-summary-freight]').textContent=DeliveryCart.money(data.frete_valor);document.querySelector('[data-summary-total]').textContent=DeliveryCart.money(data.subtotal+data.frete_valor);document.querySelector('[data-freight-result]').textContent=data.mensagem||'';return data;
 }
 document.querySelector('[data-freight]').onclick=()=>freight().catch(e=>document.querySelector('[data-freight-result]').textContent=e.message);
 form.onsubmit=async function(e){e.preventDefault();const error=document.querySelector('[data-checkout-error]'),button=form.querySelector('[type=submit]');error.textContent='';button.disabled=true;
  try{const data=Object.fromEntries(new FormData(form));data.itens=apiItems();const res=await fetch(@json(route('delivery.public.finish',$slug)),{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':window.deliveryStore.csrf},body:JSON.stringify(data)});const out=await res.json();if(!res.ok)throw new Error(Object.values(out.errors||{})[0]?.[0]||out.message||'Não foi possível finalizar.');DeliveryCart.clear();location.href=out.redirect_url}catch(ex){error.textContent=ex.message;button.disabled=false}
 };
})();
</script>
@endpush
@endsection
