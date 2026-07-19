@extends('delivery.public.layout')
@section('title', 'Checkout · '.($config->nome_loja ?: 'Loja'))
@section('content')
@php
    use App\Support\Delivery\DeliveryLojaCheckoutHelper;
    $primeiraForma = array_key_first($formasCheckout) ?? DeliveryLojaCheckoutHelper::PAGAMENTO_CARTAO_CREDITO;
    $modoFreteUsaKm = in_array($freteModo ?? '', [
        \App\Services\Delivery\DeliveryFreteService::MODO_GOOGLE,
        \App\Services\Delivery\DeliveryFreteService::MODO_OSRM,
    ], true);
@endphp
<h1>Finalizar pedido</h1>
<div class="checkout-grid">
<form class="checkout-form" data-checkout-form id="vf-checkout-form">
    <div class="checkout-main">
        <section class="form-card">
            <h2>Seus dados e entrega</h2>
            <div class="field-block">
                <span class="field-label">Como deseja receber</span>
                <div class="choice-row">
                    <label><input class="vf-tipo-entrega" type="radio" name="tipo_entrega" value="entrega" data-vf-entrega="1" @checked($tipoCheckout === 'entrega')> Entrega no endereço</label>
                    @if($permiteBalcao)
                        <label><input class="vf-tipo-entrega" type="radio" name="tipo_entrega" value="balcao" data-vf-entrega="0" @checked($tipoCheckout === 'balcao')> Retirada no balcão <span class="muted success-text">(sem taxa de entrega)</span></label>
                    @endif
                </div>
            </div>
            <div id="vf-checkout-entrega-fields" class="form-grid">
                <label>CEP<input name="cep_entrega" id="cep_entrega" maxlength="9" placeholder="00000-000" autocomplete="postal-code" value="{{ strlen($cepDigits ?? '') === 8 ? substr($cepDigits,0,5).'-'.substr($cepDigits,5) : '' }}"></label>
                <div class="span-2 checkout-freight-note">
                    @if($modoFreteUsaKm)
                        O frete usa a <strong>rota de carro</strong> entre a loja e o endereço informado. No carrinho a simulação usa só o CEP; no pedido vale o endereço completo.
                    @elseif(($freteModo ?? '') === \App\Services\Delivery\DeliveryFreteService::MODO_PADRAO)
                        Esta loja usa <strong>taxa fixa</strong> de entrega (sem faixas de CEP).
                    @else
                        A taxa usa a <strong>faixa de CEP</strong> cadastrada pela loja; fora das faixas vale a taxa padrão.
                    @endif
                </div>
                <label class="span-2">Endereço de entrega<input name="endereco" id="endereco" maxlength="255" data-vf-entrega-req="1"></label>
                <label>Complemento<input name="complemento" id="complemento" maxlength="120"></label>
                @if($checkoutOsrm ?? false)
                <div class="span-2" id="vf-checkout-osrm-extras">
                    <p class="checkout-freight-note">Detalhes do endereço <span class="muted">(melhoram o cálculo no mapa)</span></p>
                    <div class="form-grid">
                        <label>Número<input name="entrega_numero" id="entrega_numero" maxlength="32"></label>
                        <label>Bairro<input name="entrega_bairro" id="entrega_bairro" maxlength="120"></label>
                        <label>Cidade<input name="entrega_cidade" id="entrega_cidade" maxlength="120"></label>
                        <label>UF<input name="entrega_estado" id="entrega_estado" maxlength="2" class="uppercase"></label>
                    </div>
                    <p class="checkout-freight-meta" id="vf-osrm-frete-meta" hidden></p>
                </div>
                @endif
            </div>
            <hr class="section-sep">
            <div class="form-grid">
                <label>Nome<input name="cliente_nome" required maxlength="160"></label>
                <label>Telefone / WhatsApp<input name="cliente_telefone" required maxlength="30" inputmode="tel"></label>
                <label class="span-2">E-mail <span class="muted">(opcional)</span><input name="cliente_email" type="email" maxlength="190"></label>
                @if($fidelidadeAtiva && $programaFidelidade)
                <div class="span-2 fidelidade-opt">
                    <label class="choice-inline"><input type="checkbox" id="vf-fidelidade-abrir" value="1"> Sim, quero o <strong>{{ $programaFidelidade->nome_exibicao ?? 'Cartão fidelidade' }}</strong> <span class="muted">(1 selo nesta compra)</span></label>
                    <p class="checkout-freight-note">Ao marcar, abrimos uma janela para confirmar telefone e CPF do cartão.</p>
                    <input type="hidden" name="fidelidade_quero" id="vf-fidelidade-quero" value="0">
                    <input type="hidden" name="fidelidade_telefone" id="vf-fidelidade-tel-hidden" value="">
                    <input type="hidden" name="fidelidade_cpf" id="vf-fidelidade-cpf-hidden" value="">
                </div>
                @endif
            </div>
        </section>

        <section class="form-card">
            <h2>Pagamento</h2>
            <div class="payment-list vf-pay-list">
                @foreach($formasCheckout as $val => $rotulo)
                    <label><input class="vf-pay-opt" type="radio" name="forma_pagamento" value="{{ $val }}" @checked($loop->first)> {{ $rotulo }}</label>
                @endforeach
            </div>

            @if($pixConfigurada)
            <div id="vf-pay-pix-extra" class="pay-extra-panel {{ $primeiraForma === DeliveryLojaCheckoutHelper::PAGAMENTO_PIX ? '' : 'is-hidden' }}">
                <h3>Pague com PIX</h3>
                @if(trim((string)($config->pix_chave ?? '')) !== '')
                <label class="field-block">Chave PIX ({{ DeliveryLojaCheckoutHelper::pixChaveRotuloTipo($config) }})
                    <div class="input-group-inline">
                        <input readonly id="field-pix-chave" value="{{ $config->pix_chave }}">
                        <button type="button" class="btn" onclick="navigator.clipboard.writeText(document.getElementById('field-pix-chave').value).then(()=>alert('Chave PIX copiada.'))">Copiar</button>
                    </div>
                    @if(trim((string)($config->pix_banco ?? '')) !== '')<small class="muted">Banco: {{ $config->pix_banco }}</small>@endif
                </label>
                @endif
                @if(trim((string)($config->pix_instrucoes ?? '')) !== '')
                    <div class="pix-instructions">{!! nl2br(e($config->pix_instrucoes)) !!}</div>
                @endif
                @if(trim((string)($config->pix_copia_cola ?? '')) !== '')
                <label class="field-block">Pix copia e cola<textarea readonly rows="4" id="field-pix-copia">{{ $config->pix_copia_cola }}</textarea></label>
                <button type="button" class="btn" onclick="(function(){const t=document.getElementById('field-pix-copia');navigator.clipboard.writeText(t.value).then(()=>alert('Código PIX copiado.'));})();">Copiar código PIX</button>
                @endif
                @if($pixQrDataUri)
                    <div class="pix-qr-wrap"><p class="checkout-freight-note">Escaneie com o app do banco</p><img src="{{ $pixQrDataUri }}" alt="QR Code PIX" class="pix-qr"></div>
                @endif
            </div>
            @endif

            <div id="vf-pay-dinheiro-extra" class="pay-extra-panel {{ $primeiraForma === DeliveryLojaCheckoutHelper::PAGAMENTO_DINHEIRO ? '' : 'is-hidden' }}">
                <h3>Pagamento em dinheiro</h3>
                <span class="field-label">Vai precisar de troco?</span>
                <label class="choice-inline"><input class="vf-dinheiro-modo" type="radio" name="pagamento_dinheiro_modo" value="exato" checked> Não — tenho o valor exato (sem troco)</label>
                <label class="choice-inline"><input class="vf-dinheiro-modo" type="radio" name="pagamento_dinheiro_modo" value="com_troco"> Sim — preciso de troco</label>
                <div id="vf-dinheiro-valor-wrap" class="is-hidden">
                    <label>Com quanto vai pagar? <span class="danger-text">*</span>
                        <div class="input-group-inline"><span class="input-prefix">R$</span><input type="number" name="pagamento_troco_para" id="pagamento_troco_para" min="0" step="0.01" placeholder="0,00"></div>
                    </label>
                    <p class="checkout-freight-note">Informe o valor da nota ou montante (deve ser igual ou maior ao total <span id="vf-dinheiro-min-total">R$ 0,00</span>).</p>
                </div>
                <p id="vf-dinheiro-ajuda-exato" class="checkout-freight-note">Leve dinheiro trocado para o valor exato do pedido na entrega ou retirada.</p>
            </div>
        </section>

        <section class="form-card">
            <h2>Observação <span class="muted">(opcional, uma só)</span></h2>
            <p class="checkout-freight-note">Até 220 caracteres — ex.: interfone, referência, melhor horário.</p>
            <textarea name="observacoes" rows="2" maxlength="220" placeholder="Ex.: portão azul, interfone 12…"></textarea>
        </section>
    </div>

    <aside class="order-summary checkout-sidebar">
        <section class="form-card">
            <h2>Pedido</h2>
            <div data-checkout-items class="checkout-items-list"></div>
            <div class="summary-line"><span>Taxa entrega</span><strong id="vf-side-taxa">R$ 0,00</strong></div>
            <small id="vf-side-taxa-rotulo" class="checkout-freight-note"></small>
            <div id="vf-frete-bloqueado-msg" class="checkout-alert checkout-alert--warn is-hidden">Este CEP está fora da área de entrega. Ajuste o CEP ou escolha retirada no balcão.</div>
            <div class="grand-total"><span>Total</span><strong id="vf-side-total">R$ 0,00</strong></div>
            <button class="btn success wide" type="submit" id="vf-checkout-submit">Confirmar pedido</button>
            <a class="btn ghost wide" href="{{ $cartUrl }}">Voltar ao carrinho</a>
        </section>
    </aside>
    <div class="form-error span-2" data-checkout-error></div>
</form>
</div>

@if($fidelidadeAtiva && ($programaFidelidade ?? null))
<dialog id="vfModalFidelidadeCheckout" class="vf-dialog">
    <div class="vf-dialog__head"><h2>{{ $programaFidelidade->nome_exibicao ?? 'Cartão fidelidade' }}</h2><button type="button" data-fid-close>×</button></div>
    <p class="checkout-freight-note">Confirme o telefone do pedido e informe seu CPF para ativar o cartão nesta loja.</p>
    <label>Telefone / WhatsApp<input type="tel" id="vf-fidelidade-tel-modal" maxlength="32"></label>
    <label>CPF<input type="text" id="vf-fidelidade-cpf-modal" maxlength="14" placeholder="000.000.000-00" inputmode="numeric"></label>
    <div class="vf-dialog__foot">
        <button type="button" class="btn ghost" id="vf-fidelidade-cancelar">Cancelar</button>
        <button type="button" class="btn primary" id="vf-fidelidade-confirmar">Confirmar</button>
    </div>
</dialog>
@endif

@push('scripts')
<script>
(function(){
  const form = document.getElementById('vf-checkout-form');
  const items = DeliveryCart.items();
  if (!items.length) { location.href = @json($cartUrl); return; }
  const apiItems = () => items.map(i => ({
    produto_id: i.produto_id, quantidade: i.quantidade,
    opcoes: {
      adicionais: (i.opcoes.adicionais||[]).map(a=>({id:a.id,quantidade:a.quantidade})),
      retiradas: (i.opcoes.retiradas||[]).map(r=>({id:r.id,quantidade:r.quantidade||1})),
      observacao: i.opcoes.observacao||null, nota_produto: i.opcoes.nota_produto||null
    }
  }));
  const subtotal = items.reduce((s,i)=>s+(Number(i.preco)+(i.opcoes.adicionais||[]).reduce((a,x)=>a+Number(x.preco)*Number(x.quantidade),0))*Number(i.quantidade),0);
  document.querySelector('[data-checkout-items]').innerHTML = items.map(i=>{
    const opts = [...(i.opcoes?.adicionais||[]).map(a=>`${a.quantidade}× ${DeliveryCart.escape(a.nome)}`), ...(i.opcoes?.retiradas||[]).map(r=>`Sem ${DeliveryCart.escape(r.nome)}`)].join(', ');
    return `<div class="checkout-item-line"><div><strong>${DeliveryCart.escape(i.nome)}</strong> × ${i.quantidade}${opts?`<small>${opts}</small>`:''}</div><strong>${DeliveryCart.money((Number(i.preco)+(i.opcoes.adicionais||[]).reduce((a,x)=>a+Number(x.preco)*Number(x.quantidade),0))*i.quantidade)}</strong></div>`;
  }).join('') + `<div class="summary-line"><span>Subtotal</span><strong>${DeliveryCart.money(subtotal)}</strong></div>`;

  const DIN = @json(\App\Support\Delivery\DeliveryLojaCheckoutHelper::PAGAMENTO_DINHEIRO);
  const PIX = @json(\App\Support\Delivery\DeliveryLojaCheckoutHelper::PAGAMENTO_PIX);
  const ENTREGA = 'entrega';
  let taxaEnt = 0, rotuloEnt = '', entregaBloq = false, debounceTimer = null;
  const freteResumoUrl = @json($freteResumoUrl);
  const osrmCheckout = @json($checkoutOsrm ?? false);
  const calcEntregaUrl = @json($calcularEntregaApiUrl ?? '');
  const slugLoja = @json($slug);
  const fmt = n => n.toFixed(2).replace('.', ',');
  const gv = id => { const e = document.getElementById(id); return e ? (e.value||'').trim() : ''; };

  function syncPayPanels(){
    const sel = document.querySelector('.vf-pay-opt:checked');
    const v = sel ? sel.value : '';
    document.getElementById('vf-pay-dinheiro-extra')?.classList.toggle('is-hidden', v !== DIN);
    document.getElementById('vf-pay-pix-extra')?.classList.toggle('is-hidden', v !== PIX);
  }
  document.querySelectorAll('.vf-pay-opt').forEach(r => r.onchange = syncPayPanels);
  function syncDinheiroModo(){
    const troco = document.getElementById('din-mod-troco') || document.querySelector('.vf-dinheiro-modo[value=com_troco]');
    const precisa = troco && troco.checked;
    document.getElementById('vf-dinheiro-valor-wrap')?.classList.toggle('is-hidden', !precisa);
    const inp = document.getElementById('pagamento_troco_para');
    if (inp) { inp.required = !!precisa; if (!precisa) inp.value = ''; }
    document.getElementById('vf-dinheiro-ajuda-exato')?.classList.toggle('is-hidden', !!precisa);
  }
  document.querySelectorAll('.vf-dinheiro-modo').forEach(r => r.onchange = syncDinheiroModo);

  function syncResumo(isEnt){
    const bloq = !!(isEnt && entregaBloq);
    const taxa = isEnt ? (bloq ? 0 : taxaEnt) : 0;
    const tot = Math.round((subtotal + taxa) * 100) / 100;
    document.getElementById('vf-side-taxa').textContent = 'R$ ' + fmt(taxa);
    document.getElementById('vf-side-taxa-rotulo').textContent = isEnt ? rotuloEnt : 'Retirada no balcão';
    document.getElementById('vf-side-total').textContent = 'R$ ' + fmt(tot);
    document.getElementById('vf-dinheiro-min-total').textContent = 'R$ ' + fmt(tot);
    document.getElementById('vf-frete-bloqueado-msg')?.classList.toggle('is-hidden', !bloq);
    document.getElementById('vf-checkout-submit').disabled = bloq;
  }
  function pedirFreteAtualizado(){
    const r = document.querySelector('.vf-tipo-entrega:checked');
    if (!r || r.value !== ENTREGA) return;
    const cepEl = document.getElementById('cep_entrega');
    document.getElementById('vf-side-taxa').textContent = '…';
    if (osrmCheckout && calcEntregaUrl) {
      const cepDig = (cepEl.value||'').replace(/\D+/g,'');
      if (cepDig.length !== 8) { syncResumo(true); return; }
      fetch(calcEntregaUrl, { method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':window.deliveryStore.csrf,'X-Requested-With':'XMLHttpRequest'}, body:JSON.stringify({
        slug:slugLoja, cep:cepEl.value, rua:document.getElementById('endereco')?.value||'',
        numero:gv('entrega_numero'), bairro:gv('entrega_bairro'), cidade:gv('entrega_cidade'), estado:gv('entrega_estado'), subtotal_pedido:subtotal
      })}).then(res=>res.json()).then(data=>{
        const meta = document.getElementById('vf-osrm-frete-meta');
        if (!data || !data.success) { syncResumo(true); return; }
        taxaEnt = parseFloat(data.taxa_entrega)||0; entregaBloq = !!data.entrega_bloqueada;
        rotuloEnt = data.endereco_formatado ? ('Aprox. '+(data.distancia_km!=null?Number(data.distancia_km).toFixed(1).replace('.',',')+' km, ~':'')+(data.tempo_minutos!=null?data.tempo_minutos+' min — ':'')+data.endereco_formatado.substring(0,120)) : '';
        if (meta && data.distancia_km != null) { meta.textContent = 'Distância ~'+Number(data.distancia_km).toFixed(1).replace('.',',')+' km · ~'+(data.tempo_minutos??'—')+' min'; meta.hidden = false; }
        syncResumo(true);
      }).catch(()=>syncResumo(true));
      return;
    }
    fetch(freteResumoUrl, { method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':window.deliveryStore.csrf}, body:JSON.stringify({cep:cepEl.value, subtotal})})
      .then(r=>r.json()).then(data=>{
        if (!data || data.incomplete) { syncResumo(true); return; }
        taxaEnt = parseFloat(data.taxa)||0; rotuloEnt = data.rotulo||''; entregaBloq = !!data.entrega_bloqueada; syncResumo(true);
      }).catch(()=>syncResumo(true));
  }
  function agendarFrete(){ clearTimeout(debounceTimer); debounceTimer = setTimeout(pedirFreteAtualizado, 450); }
  function syncEntregaFields(){
    const r = document.querySelector('.vf-tipo-entrega:checked');
    const isEnt = r && r.value === ENTREGA;
    document.getElementById('vf-checkout-entrega-fields')?.classList.toggle('is-hidden', !isEnt);
    ['cep_entrega','endereco'].forEach(id=>{ const el=document.getElementById(id); if(el) el.required = !!isEnt; });
    syncResumo(!!isEnt);
    if (isEnt) agendarFrete();
  }
  document.querySelectorAll('.vf-tipo-entrega').forEach(r=>r.onchange=syncEntregaFields);
  document.getElementById('cep_entrega')?.addEventListener('input', ()=>{ if(document.querySelector('.vf-tipo-entrega:checked')?.value===ENTREGA) agendarFrete(); });
  ['endereco','entrega_numero','entrega_bairro','entrega_cidade','entrega_estado'].forEach(id=>{
    document.getElementById(id)?.addEventListener('input', ()=>{ if(document.querySelector('.vf-tipo-entrega:checked')?.value===ENTREGA && osrmCheckout) agendarFrete(); });
  });
  syncEntregaFields(); syncPayPanels(); syncDinheiroModo();

  form.onsubmit = async function(e){
    e.preventDefault();
    const error = document.querySelector('[data-checkout-error]');
    const btn = document.getElementById('vf-checkout-submit');
    error.textContent = ''; btn.disabled = true;
    try {
      if (document.querySelector('.vf-tipo-entrega:checked')?.value === ENTREGA && entregaBloq) throw new Error('Entrega indisponível para este endereço.');
      const tipo = document.querySelector('.vf-tipo-entrega:checked')?.value || 'entrega';
      const fulfillment = tipo === 'balcao' ? 'retirada' : 'entrega';
      const body = Object.fromEntries(new FormData(form).entries());
      body.fulfillment = fulfillment;
      body.forma_pagamento = document.querySelector('.vf-pay-opt:checked')?.value;
      body.pagamento_forma = body.forma_pagamento;
      body.cliente_whatsapp = body.cliente_telefone;
      body.endereco_cep = body.cep_entrega;
      body.endereco_rua = body.endereco;
      body.endereco_numero = body.entrega_numero || '';
      body.endereco_bairro = body.entrega_bairro || '';
      body.endereco_cidade = body.entrega_cidade || '';
      body.endereco_uf = (body.entrega_estado || '').toUpperCase();
      body.endereco_complemento = body.complemento || '';
      body.itens = apiItems();
      const res = await fetch(@json(route('delivery.public.finish',$slug)), { method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':window.deliveryStore.csrf}, body:JSON.stringify(body) });
      const out = await res.json();
      if (!res.ok) throw new Error(Object.values(out.errors||{})[0]?.[0] || out.message || 'Não foi possível finalizar.');
      DeliveryCart.clear(); location.href = out.redirect_url;
    } catch (ex) {
      error.textContent = ex.message;
      btn.disabled = entregaBloq;
    }
  };
})();
</script>
@endpush
@endsection
