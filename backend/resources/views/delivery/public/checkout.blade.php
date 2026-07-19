@extends('delivery.public.layout')
@section('title', 'Checkout · '.($config->nome_loja ?: 'Loja'))
@section('content')
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
        <label>CEP<input name="endereco_cep" maxlength="12" data-checkout-cep></label><label>Rua<input name="endereco_rua" maxlength="180" data-checkout-rua></label>
        <label>Número<input name="endereco_numero" maxlength="40" data-checkout-numero></label><label>Bairro<input name="endereco_bairro" maxlength="120" data-checkout-bairro></label>
        <label>Cidade<input name="endereco_cidade" maxlength="120" data-checkout-cidade></label><label>UF<input name="endereco_uf" maxlength="2" data-checkout-uf></label>
        <label class="span-2">Complemento<input name="endereco_complemento" maxlength="500"></label>
    </div>
    <p class="checkout-freight-note" data-freight-result>Informe o CEP para calcular o frete.</p>
    <p class="checkout-freight-blocked" data-freight-blocked hidden>Entrega indisponível para este endereço.</p>
    <p class="checkout-freight-meta" data-freight-meta hidden></p>
    </section>
    <section class="form-card"><h2>Pagamento</h2><div class="payment-list">
        @foreach($pagamentos as $value=>$label)<label><input type="radio" name="pagamento_forma" value="{{ $value }}" @checked($loop->first)> {{ $label }}</label>@endforeach
    </div>
    @if(isset($pagamentos['pix']) && $config->pix_chave)<div class="pix-box"><strong>Chave PIX</strong><code>{{ $config->pix_chave }}</code>@if($config->pix_beneficiario)<small>{{ $config->pix_beneficiario }}</small>@endif</div>@endif
    <label>Observações<textarea name="observacoes" rows="3" maxlength="1000"></textarea></label></section>
    <div class="form-error" data-checkout-error></div>
    <button class="btn success wide" type="submit" data-checkout-submit>Confirmar pedido</button>
    <a class="btn ghost wide vf-back-after-action" href="{{ route('delivery.public.store', $slug) }}">← Continuar comprando</a>
</form>
<aside class="order-summary"><h2>Resumo</h2><div data-checkout-items></div><hr><div><span>Subtotal</span><strong data-summary-subtotal></strong></div><div><span>Frete</span><strong data-summary-freight>—</strong></div><small data-summary-freight-label></small><div class="grand-total"><span>Total</span><strong data-summary-total></strong></div></aside>
</div>
@push('scripts')
<script>
(function(){
 const form=document.querySelector('[data-checkout-form]'), items=DeliveryCart.items();
 if(!items.length){location.href=@json(route('delivery.public.store',$slug));return}
 const apiItems=()=>items.map(i=>({produto_id:i.produto_id,quantidade:i.quantidade,opcoes:{adicionais:(i.opcoes.adicionais||[]).map(a=>({id:a.id,quantidade:a.quantidade})),retiradas:(i.opcoes.retiradas||[]).map(r=>({id:r.id,quantidade:r.quantidade||1})),observacao:i.opcoes.observacao||null,nota_produto:i.opcoes.nota_produto||null}}));
 const subtotal=items.reduce((s,i)=>s+(i.preco+(i.opcoes.adicionais||[]).reduce((a,x)=>a+x.preco*x.quantidade,0))*i.quantidade,0);
 document.querySelector('[data-checkout-items]').innerHTML=items.map(i=>'<p><strong>'+DeliveryCart.escape(i.nome)+'</strong><small>'+i.quantidade+'×</small></p>').join('');
 document.querySelector('[data-summary-subtotal]').textContent=DeliveryCart.money(subtotal);
 const freteUrl=@json(route('delivery.public.freight',$slug));
 const freteResumoUrl=@json($freteResumoUrl ?? route('delivery.public.freight.summary',$slug));
 const osrmCheckout=@json($checkoutOsrm ?? false);
 const calcEntregaUrl=@json($calcularEntregaApiUrl ?? route('delivery.public.calcular-entrega'));
 const slugLoja=@json($slug);
 let taxaEnt=0, rotuloEnt='Informe o CEP para calcular o frete.', entregaBloq=false, debounceTimer=null;
 function fulfillment(){return form.elements.fulfillment.value}
 function addressState(){document.querySelector('[data-address]').hidden=fulfillment()!=='entrega'}
 function gv(sel){const el=form.querySelector(sel);return el?(el.value||'').trim():''}
 function syncSummary(){
  const isEnt=fulfillment()==='entrega';
  const bloq=isEnt&&entregaBloq;
  const taxa=isEnt?(bloq?0:taxaEnt):0;
  const total=Math.round((subtotal+taxa)*100)/100;
  document.querySelector('[data-summary-freight]').textContent=isEnt?(bloq?'Indisponível':DeliveryCart.money(taxa)):'—';
  document.querySelector('[data-summary-freight-label]').textContent=isEnt?rotuloEnt:'Retirada sem frete';
  document.querySelector('[data-summary-total]').textContent=DeliveryCart.money(total);
  document.querySelector('[data-freight-result]').textContent=isEnt?rotuloEnt:'Retirada na loja — sem frete';
  document.querySelector('[data-freight-blocked]').hidden=!bloq;
  document.querySelector('[data-checkout-submit]').disabled=bloq;
 }
 async function freightFull(){
  const res=await fetch(freteUrl,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':window.deliveryStore.csrf},body:JSON.stringify({
   fulfillment:fulfillment(),cep:form.elements.endereco_cep.value,endereco_cep:form.elements.endereco_cep.value,
   endereco_rua:form.elements.endereco_rua.value,endereco_numero:form.elements.endereco_numero.value,
   endereco_bairro:form.elements.endereco_bairro.value,endereco_cidade:form.elements.endereco_cidade.value,
   endereco_uf:form.elements.endereco_uf.value,itens:apiItems()
  })});
  const data=await res.json(); if(!res.ok)throw new Error(Object.values(data.errors||{})[0]?.[0]||data.message||'Não foi possível calcular.');
  taxaEnt=Number(data.frete_valor||0); rotuloEnt=data.rotulo||data.mensagem||data.label||''; entregaBloq=!!data.bloqueado;
  if(data.distancia_km!=null){document.querySelector('[data-freight-meta]').textContent='Distância ~'+Number(data.distancia_km).toFixed(1).replace('.',',')+' km'+(data.tempo_minutos!=null?' · ~'+data.tempo_minutos+' min':'');document.querySelector('[data-freight-meta]').hidden=false;}
  syncSummary(); return data;
 }
 function pedirFreteAtualizado(){
  if(fulfillment()!=='entrega'){taxaEnt=0;rotuloEnt='Retirada na loja — sem frete';entregaBloq=false;syncSummary();return}
  const cepDig=(form.elements.endereco_cep.value||'').replace(/\D+/g,'');
  if(cepDig.length!==8){syncSummary();return}
  document.querySelector('[data-summary-freight]').textContent='…';
  if(osrmCheckout&&calcEntregaUrl){
   fetch(calcEntregaUrl,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':window.deliveryStore.csrf,'X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({
    slug:slugLoja,cep:form.elements.endereco_cep.value,rua:form.elements.endereco_rua.value,
    numero:form.elements.endereco_numero.value,bairro:form.elements.endereco_bairro.value,
    cidade:form.elements.endereco_cidade.value,estado:form.elements.endereco_uf.value,subtotal_pedido:subtotal
   })}).then(r=>r.json()).then(data=>{
    if(!data||!data.success){syncSummary();return}
    taxaEnt=parseFloat(data.taxa_entrega)||0; entregaBloq=!!data.entrega_bloqueada;
    rotuloEnt=data.endereco_formatado?('Aprox. '+(data.distancia_km!=null?Number(data.distancia_km).toFixed(1).replace('.',',')+' km, ~':'')+(data.tempo_minutos!=null?data.tempo_minutos+' min — ':'')+data.endereco_formatado.substring(0,120)):('Rota ~'+(data.distancia_km!=null?Number(data.distancia_km).toFixed(1).replace('.',',')+' km':''));
    if(data.distancia_km!=null){document.querySelector('[data-freight-meta]').textContent='Distância pela rota ~'+Number(data.distancia_km).toFixed(1).replace('.',',')+' km · tempo ~'+(data.tempo_minutos!=null?data.tempo_minutos:'—')+' min';document.querySelector('[data-freight-meta]').hidden=false;}
    syncSummary();
   }).catch(()=>syncSummary());
   return;
  }
  fetch(freteResumoUrl,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':window.deliveryStore.csrf,'X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({cep:form.elements.endereco_cep.value,subtotal:subtotal})})
   .then(r=>r.json()).then(data=>{
    if(!data||data.incomplete){syncSummary();return}
    taxaEnt=parseFloat(data.taxa)||0; rotuloEnt=data.rotulo||''; entregaBloq=!!data.entrega_bloqueada; syncSummary();
   }).catch(()=>syncSummary());
 }
 function scheduleFrete(){clearTimeout(debounceTimer);debounceTimer=setTimeout(pedirFreteAtualizado,450)}
 form.querySelectorAll('[name=fulfillment]').forEach(x=>x.onchange=function(){addressState();pedirFreteAtualizado()});
 ['[data-checkout-cep]','[data-checkout-rua]','[data-checkout-numero]','[data-checkout-bairro]','[data-checkout-cidade]','[data-checkout-uf]'].forEach(sel=>form.querySelector(sel)?.addEventListener('input',scheduleFrete));
 addressState(); syncSummary();
 form.onsubmit=async function(e){e.preventDefault();const error=document.querySelector('[data-checkout-error]'),button=form.querySelector('[type=submit]');error.textContent='';button.disabled=true;
  try{if(fulfillment()==='entrega'&&entregaBloq)throw new Error('Entrega indisponível para este endereço.');await freightFull();
   const data=Object.fromEntries(new FormData(form));data.itens=apiItems();const res=await fetch(@json(route('delivery.public.finish',$slug)),{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':window.deliveryStore.csrf},body:JSON.stringify(data)});const out=await res.json();if(!res.ok)throw new Error(Object.values(out.errors||{})[0]?.[0]||out.message||'Não foi possível finalizar.');DeliveryCart.clear();location.href=out.redirect_url}catch(ex){error.textContent=ex.message;button.disabled=entregaBloq}
 };
})();
</script>
@endpush
@endsection
