@extends('delivery.public.layout')
@section('title', $produto->nome.' · '.($config->nome_loja ?: 'Loja'))
@section('content')
@php
    $minEsc = max(0, (int) ($produto->acrescimo_escolhas_min ?? 0));
    $maxEsc = $produto->acrescimo_escolhas_max !== null ? max(0, (int) $produto->acrescimo_escolhas_max) : null;
    $maxEscForm = $maxEsc ?? 9999;
    $uiAd = (string) ($produto->acrescimos_loja_ui ?? 'stepper');
    if ($maxEsc !== null) {
        $uiAd = 'stepper';
    }
    $uiRem = (string) ($produto->ingredientes_retirar_ui ?? 'stepper');
    $maxRem = max(0, (int) ($produto->max_ingredientes_retirar ?? 0));
    $whatsRaw = trim((string) ($config->whatsapp ?? ''));
    if ($whatsRaw === '') {
        $whatsRaw = trim((string) ($config->telefone ?? ''));
    }
    $whatsDigits = $whatsRaw !== '' ? preg_replace('/\D+/', '', $whatsRaw) : '';
    if (is_string($whatsDigits) && $whatsDigits !== '' && (strlen($whatsDigits) === 10 || strlen($whatsDigits) === 11)) {
        $whatsDigits = '55'.$whatsDigits;
    }
    $igUrl = trim((string) ($config->instagram_url ?? ''));
    $fbUrl = trim((string) ($config->facebook_url ?? ''));
    $productUrl = url()->current();
    $shareNome = trim((string) ($produto->nome ?? 'Produto'));
    $shareLoja = trim((string) ($config->nome_loja ?? 'Loja'));
    $shareText = $shareNome.' — '.$shareLoja.' '.$productUrl;
    $waShareUrl = 'https://wa.me/?text='.rawurlencode($shareText);
    $waLojaUrl = $whatsDigits !== '' ? 'https://wa.me/'.$whatsDigits.'?text='.rawurlencode('Olá! Tenho interesse no produto: '.$shareNome) : null;
    $fbShareUrl = 'https://www.facebook.com/sharer/sharer.php?u='.rawurlencode($productUrl);
@endphp
<nav class="breadcrumb"><a href="{{ route('delivery.public.store', $slug) }}">Cardápio</a> / {{ $produto->nome }}</nav>
<div class="detail-grid">
    <div class="detail-photo">@if($produto->foto_url)<img src="{{ $produto->foto_url }}" alt="{{ $produto->nome }}">@else<span>▧</span>@endif</div>
    <section>
        <div class="detail-head">
            <h1>{{ $produto->nome }}</h1>
            <div class="vf-produto-share-bloco">
                <span id="vf-share-produto-legenda" class="vf-produto-share-legenda">Compartilhar</span>
                <div class="vf-produto-share" role="group" aria-labelledby="vf-share-produto-legenda">
                    <a href="{{ $waShareUrl }}" target="_blank" rel="noopener noreferrer"
                       class="btn success vf-produto-share__btn" title="WhatsApp" aria-label="Compartilhar no WhatsApp">
                        <svg class="vf-produto-share__ico" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M12.04 2c-5.46 0-9.91 4.43-9.91 9.9 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.9-4.44 9.9-9.9C21.94 6.43 17.5 2 12.04 2zm5.83 14.1c-.24.68-1.41 1.25-1.96 1.33-.5.07-1.14.1-1.84-.12-.42-.13-.97-.32-1.66-.62-2.92-1.26-4.82-4.2-4.96-4.4-.14-.19-1.15-1.53-1.15-2.92 0-1.39.73-2.07.99-2.35.26-.28.57-.35.76-.35h.55c.17 0 .4-.06.63.48.24.56.8 1.95.87 2.09.07.14.12.3.02.49-.1.19-.14.3-.28.47-.14.16-.3.36-.42.49-.14.14-.28.29-.12.56.16.28.71 1.17 1.53 1.9 1.05.93 1.94 1.22 2.22 1.36.28.14.44.12.6-.07.17-.19.7-.81.89-1.09.19-.28.37-.23.63-.14.26.09 1.64.77 1.92.91.28.14.47.21.54.33.07.12.07.7-.17 1.38z"/></svg>
                        <span class="vf-produto-share__label">WhatsApp</span>
                    </a>
                    <a href="{{ $fbShareUrl }}" target="_blank" rel="noopener noreferrer"
                       class="btn primary vf-produto-share__btn" title="Facebook" aria-label="Compartilhar no Facebook">
                        <svg class="vf-produto-share__ico" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.8-4.7 4.54-4.7 1.32 0 2.7.24 2.7.24v2.97h-1.52c-1.5 0-1.97.93-1.97 1.89v2.26h3.35l-.54 3.49h-2.81V24C19.61 23.1 24 18.1 24 12.07z"/></svg>
                        <span class="vf-produto-share__label">Facebook</span>
                    </a>
                    <button type="button" class="btn vf-produto-share__btn vf-share-instagram"
                            data-share-url="{{ $productUrl }}"
                            title="Instagram — copia o link para você colar no app"
                            aria-label="Copiar link do produto para compartilhar no Instagram">
                        <svg class="vf-produto-share__ico" viewBox="0 0 24 24" width="20" height="20" fill="none" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="2"/><circle cx="17.5" cy="6.5" r="1.2" fill="currentColor"/></svg>
                        <span class="vf-produto-share__label vf-share-instagram-label">Instagram</span>
                    </button>
                </div>
            </div>
        </div>
        @if($produto->estoque > 0)
            <div class="vf-produto-estrelas" id="vf-produto-estrelas-wrap">
                <span class="vf-produto-estrelas__label">Sua nota <span class="muted">(opcional)</span></span>
                <div class="vf-estrelas-grupo" role="group" aria-label="Dar de 1 a 5 estrelas">
                    @for($s = 1; $s <= 5; $s++)
                        <button type="button" class="vf-estrela-produto-btn" data-vf-estrela="{{ $s }}" aria-label="{{ $s }} estrela{{ $s > 1 ? 's' : '' }}">
                            <span class="vf-estrela-produto-ico" aria-hidden="true">☆</span>
                        </button>
                    @endfor
                </div>
                <input type="hidden" name="nota_produto" id="vf_nota_produto" value="">
            </div>
        @endif
        @if($produto->categoria_nome)<p class="muted">{{ $produto->categoria_nome }}</p>@endif
        <p class="description">{{ $produto->descricao ?: 'Sem descrição cadastrada.' }}</p>
        <div class="detail-price">R$ {{ number_format((float)$produto->preco, 2, ',', '.') }}</div>
        <p class="{{ $produto->estoque > 0 ? 'stock' : 'unavailable' }}">{{ $produto->estoque > 0 ? $produto->estoque.' unidade(s) disponível(is)' : 'Indisponível no momento' }}</p>
        @if($produto->estoque > 0)
        <form data-product-form
              data-min="{{ $minEsc }}"
              data-max="{{ $maxEscForm }}"
              data-remove-max="{{ $maxRem > 0 ? $maxRem : 9999 }}"
              data-ui-additional="{{ $uiAd }}"
              data-ui-removal="{{ $uiRem }}">
            @if($adicionais->isNotEmpty())
            <div class="option-card option-card--personalizar">
                <h2>Personalizar</h2>
                <p class="personalize-hint">
                    @if($maxEsc !== null && $minEsc === $maxEsc)
                        Escolha {{ $maxEsc }} opções: Mínimo: {{ $minEsc }} - Máximo: {{ $maxEsc }}.
                        Pode repetir a mesma opção (ex.: 3× {{ $adicionais->first()->nome ?? 'um item' }}).
                    @elseif($maxEsc !== null && $minEsc > 0)
                        Escolha entre {{ $minEsc }} e {{ $maxEsc }} opções.
                    @elseif($maxEsc !== null)
                        Escolha até {{ $maxEsc }} opções.
                    @elseif($minEsc > 0)
                        Escolha pelo menos {{ $minEsc }} opções.
                    @else
                        Escolha as opções desejadas.
                    @endif
                </p>
                @if($maxEsc !== null)
                    <p class="selection-counter" data-selection-counter aria-live="polite">0/{{ $maxEsc }} selecionado(s)</p>
                @endif
                <div class="personalize-grid">
                    @foreach($adicionais as $ad)
                    <div class="option-row-vf" data-additional-item>
                        <span class="option-row-vf__name">
                            <strong>{{ $ad->nome }}</strong>
                            @if((float) $ad->preco > 0)
                                <small>+ R$ {{ number_format((float) $ad->preco, 2, ',', '.') }}</small>
                            @endif
                        </span>
                        @if($uiAd === 'checkbox')
                            <label class="option-check">
                                <input type="checkbox"
                                       data-additional="{{ $ad->id }}"
                                       data-name="{{ $ad->nome }}"
                                       data-price="{{ $ad->preco }}"
                                       value="1">
                            </label>
                        @else
                            <div class="option-stepper" data-additional-stepper>
                                <button type="button" data-additional-minus aria-label="Diminuir {{ $ad->nome }}">−</button>
                                <span data-additional-qty>0</span>
                                <button type="button" data-additional-plus aria-label="Aumentar {{ $ad->nome }}">+</button>
                            </div>
                            <input type="hidden"
                                   data-additional="{{ $ad->id }}"
                                   data-name="{{ $ad->nome }}"
                                   data-price="{{ $ad->preco }}"
                                   value="0">
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
            @if($ingredientes->isNotEmpty() && $maxRem > 0)
            <div class="option-card option-card--remover">
                <h2>Retirar ingredientes</h2>
                <p class="personalize-hint">Você pode retirar até {{ $maxRem }}.</p>
                @if($uiRem !== 'checkbox')
                    <p class="selection-counter" data-removal-counter aria-live="polite">0/{{ $maxRem }} selecionado(s)</p>
                @endif
                <div class="personalize-grid">
                    @foreach($ingredientes as $ing)
                    <div class="option-row-vf" data-removal-item>
                        <span class="option-row-vf__name">{{ $ing->nome }}</span>
                        @if($uiRem === 'checkbox')
                            <label class="option-check">
                                <input type="checkbox"
                                       data-removal="{{ $ing->id }}"
                                       data-name="{{ $ing->nome }}"
                                       value="1">
                            </label>
                        @else
                            <div class="option-stepper" data-removal-stepper>
                                <button type="button" data-removal-minus aria-label="Manter {{ $ing->nome }}">−</button>
                                <span data-removal-qty>0</span>
                                <button type="button" data-removal-plus aria-label="Retirar {{ $ing->nome }}">+</button>
                            </div>
                            <input type="hidden"
                                   data-removal="{{ $ing->id }}"
                                   data-name="{{ $ing->nome }}"
                                   value="0">
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
            <label class="detail-field">
                <span class="detail-field__label">Observação (opcional)</span>
                <textarea name="observacao" rows="3" maxlength="500" placeholder="Ex.: ponto da carne, sem cebola…" data-product-obs></textarea>
            </label>
            <div class="detail-qty-row">
                <span class="detail-field__label">Quantidade</span>
                <div class="quantity"><button type="button" data-main-minus>−</button><input type="number" value="1" min="1" max="{{ $produto->estoque }}" data-main-qty><button type="button" data-main-plus>+</button></div>
            </div>
            <p class="form-error" data-product-error></p>
            <button class="btn primary wide" type="submit">Adicionar ao carrinho</button>
            @if($waLojaUrl)
                <a class="btn success wide detail-wa-loja" href="{{ $waLojaUrl }}" target="_blank" rel="noopener noreferrer">WhatsApp da loja</a>
            @endif
            <a class="btn ghost wide vf-back-after-action" href="{{ route('delivery.public.store', $slug) }}">← Continuar comprando</a>
        </form>
        @else
            <a class="btn ghost wide" href="{{ route('delivery.public.store', $slug) }}">← Voltar ao cardápio</a>
        @endif
    </section>
</div>
@push('scripts')
<script>
(function(){
  const form = document.querySelector('[data-product-form]');
  if (!form) return;

  const qty = form.querySelector('[data-main-qty]');
  const error = form.querySelector('[data-product-error]');
  const minChoices = Math.max(0, +form.dataset.min || 0);
  const maxChoices = Math.max(minChoices, +form.dataset.max || 9999);
  const maxRemovals = Math.max(0, +form.dataset.removeMax || 9999);
  const uiAdditional = form.dataset.uiAdditional || 'stepper';
  const uiRemoval = form.dataset.uiRemoval || 'stepper';
  const counterAdd = form.querySelector('[data-selection-counter]');
  const counterRem = form.querySelector('[data-removal-counter]');

  form.querySelector('[data-main-minus]').onclick = () => { qty.value = Math.max(1, +qty.value - 1); };
  form.querySelector('[data-main-plus]').onclick = () => { qty.value = Math.min(+qty.max, +qty.value + 1); };

  function additionalTotal() {
    if (uiAdditional === 'checkbox') {
      return form.querySelectorAll('[data-additional]:checked').length;
    }
    let total = 0;
    form.querySelectorAll('[data-additional-item]').forEach((row) => {
      const hidden = row.querySelector('[data-additional][type="hidden"]');
      if (hidden) total += Math.max(0, +hidden.value || 0);
    });
    return total;
  }

  function removalTotal() {
    if (uiRemoval === 'checkbox') {
      return form.querySelectorAll('[data-removal]:checked').length;
    }
    let total = 0;
    form.querySelectorAll('[data-removal-item]').forEach((row) => {
      const hidden = row.querySelector('[data-removal][type="hidden"]');
      if (hidden) total += Math.max(0, +hidden.value || 0);
    });
    return total;
  }

  function setAdditionalQty(row, value) {
    const qtyVal = Math.max(0, value);
    const hidden = row.querySelector('[data-additional][type="hidden"]');
    const span = row.querySelector('[data-additional-qty]');
    if (hidden) hidden.value = String(qtyVal);
    if (span) span.textContent = String(qtyVal);
  }

  function setRemovalQty(row, value) {
    const qtyVal = value > 0 ? 1 : 0;
    const hidden = row.querySelector('[data-removal][type="hidden"]');
    const span = row.querySelector('[data-removal-qty]');
    if (hidden) hidden.value = String(qtyVal);
    if (span) span.textContent = String(qtyVal);
  }

  function syncAdditionalLimits() {
    const total = additionalTotal();
    if (counterAdd) counterAdd.textContent = total + '/' + maxChoices + ' selecionado(s)';

    if (uiAdditional === 'checkbox') {
      form.querySelectorAll('[data-additional]').forEach((el) => {
        if (!el.checked) el.disabled = total >= maxChoices;
      });
      return;
    }

    form.querySelectorAll('[data-additional-item]').forEach((row) => {
      const hidden = row.querySelector('[data-additional][type="hidden"]');
      const current = hidden ? Math.max(0, +hidden.value || 0) : 0;
      const minus = row.querySelector('[data-additional-minus]');
      const plus = row.querySelector('[data-additional-plus]');
      if (minus) minus.disabled = current <= 0;
      if (plus) plus.disabled = total >= maxChoices;
    });
  }

  function syncRemovalLimits() {
    const total = removalTotal();
    if (counterRem) counterRem.textContent = total + '/' + maxRemovals + ' selecionado(s)';

    if (uiRemoval === 'checkbox') {
      form.querySelectorAll('[data-removal]').forEach((el) => {
        if (!el.checked) el.disabled = total >= maxRemovals;
      });
      return;
    }

    form.querySelectorAll('[data-removal-item]').forEach((row) => {
      const hidden = row.querySelector('[data-removal][type="hidden"]');
      const current = hidden ? Math.max(0, +hidden.value || 0) : 0;
      const minus = row.querySelector('[data-removal-minus]');
      const plus = row.querySelector('[data-removal-plus]');
      if (minus) minus.disabled = current <= 0;
      if (plus) plus.disabled = current >= 1 || total >= maxRemovals;
    });
  }

  form.querySelectorAll('[data-additional-item]').forEach((row) => {
    row.querySelector('[data-additional-minus]')?.addEventListener('click', () => {
      const hidden = row.querySelector('[data-additional][type="hidden"]');
      setAdditionalQty(row, Math.max(0, (+hidden?.value || 0) - 1));
      syncAdditionalLimits();
    });
    row.querySelector('[data-additional-plus]')?.addEventListener('click', () => {
      if (additionalTotal() >= maxChoices) return;
      const hidden = row.querySelector('[data-additional][type="hidden"]');
      setAdditionalQty(row, (+hidden?.value || 0) + 1);
      syncAdditionalLimits();
    });
  });

  form.querySelectorAll('[data-additional]').forEach((el) => {
    if (el.type === 'checkbox') {
      el.addEventListener('change', syncAdditionalLimits);
    }
  });

  form.querySelectorAll('[data-removal-item]').forEach((row) => {
    row.querySelector('[data-removal-minus]')?.addEventListener('click', () => {
      setRemovalQty(row, 0);
      syncRemovalLimits();
    });
    row.querySelector('[data-removal-plus]')?.addEventListener('click', () => {
      if (removalTotal() >= maxRemovals) return;
      setRemovalQty(row, 1);
      syncRemovalLimits();
    });
  });

  form.querySelectorAll('[data-removal]').forEach((el) => {
    if (el.type === 'checkbox') {
      el.addEventListener('change', syncRemovalLimits);
    }
  });

  syncAdditionalLimits();
  syncRemovalLimits();

  form.addEventListener('submit', function(e) {
    e.preventDefault();
    const additions = [];
    const removals = [];
    let choices = additionalTotal();

    if (uiAdditional === 'checkbox') {
      form.querySelectorAll('[data-additional]:checked').forEach((el) => {
        additions.push({
          id: +el.dataset.additional,
          nome: el.dataset.name,
          preco: +el.dataset.price,
          quantidade: 1,
        });
      });
    } else {
      form.querySelectorAll('[data-additional-item]').forEach((row) => {
        const hidden = row.querySelector('[data-additional][type="hidden"]');
        const q = hidden ? Math.max(0, +hidden.value || 0) : 0;
        if (q > 0) {
          additions.push({
            id: +hidden.dataset.additional,
            nome: hidden.dataset.name,
            preco: +hidden.dataset.price,
            quantidade: q,
          });
        }
      });
    }

    if (uiRemoval === 'checkbox') {
      form.querySelectorAll('[data-removal]:checked').forEach((el) => {
        removals.push({ id: +el.dataset.removal, nome: el.dataset.name });
      });
    } else {
      form.querySelectorAll('[data-removal-item]').forEach((row) => {
        const hidden = row.querySelector('[data-removal][type="hidden"]');
        if (hidden && +hidden.value > 0) {
          removals.push({ id: +hidden.dataset.removal, nome: hidden.dataset.name });
        }
      });
    }

    if (choices < minChoices || choices > maxChoices) {
      error.textContent = minChoices === maxChoices
        ? 'Escolha exatamente ' + maxChoices + ' opções.'
        : 'Escolha entre ' + minChoices + ' e ' + maxChoices + ' opções.';
      return;
    }
    if (removals.length > maxRemovals) {
      error.textContent = 'Você excedeu o limite de ingredientes para retirar.';
      return;
    }

    error.textContent = '';
    const obs = (form.querySelector('[data-product-obs]')?.value || '').trim();
    const notaRaw = document.getElementById('vf_nota_produto')?.value || '';
    const nota = parseInt(notaRaw, 10);
    DeliveryCart.add({
      key: Date.now().toString(36) + Math.random().toString(36).slice(2),
      produto_id: {{ $produto->id }},
      nome: @json($produto->nome),
      foto: @json($produto->foto_url),
      preco: {{ (float) $produto->preco }},
      quantidade: Math.max(1, +qty.value),
      opcoes: {
        adicionais: additions,
        retiradas: removals,
        observacao: obs !== '' ? obs : null,
        nota_produto: !isNaN(nota) && nota >= 1 && nota <= 5 ? nota : null,
      },
    });
    document.querySelector('[data-cart-open]').click();
  });
})();

(function(){
  const hid = document.getElementById('vf_nota_produto');
  const btns = document.querySelectorAll('.vf-estrela-produto-btn');
  if (!hid || !btns.length) return;

  function paintEstrelas(valor) {
    let n = parseInt(String(valor || ''), 10);
    if (isNaN(n) || n < 1 || n > 5) n = 0;
    btns.forEach((btn, i) => {
      const alvo = i + 1;
      const ico = btn.querySelector('.vf-estrela-produto-ico');
      if (ico) ico.textContent = n >= 1 && alvo <= n ? '★' : '☆';
      btn.classList.toggle('is-active', n >= 1 && alvo <= n);
    });
  }

  btns.forEach((btn) => {
    btn.addEventListener('click', () => {
      const val = parseInt(btn.getAttribute('data-vf-estrela'), 10);
      const cur = parseInt(String(hid.value || ''), 10);
      if (!isNaN(cur) && cur === val) {
        hid.value = cur <= 1 ? '' : String(cur - 1);
      } else {
        hid.value = String(val);
      }
      paintEstrelas(hid.value);
    });
  });
  paintEstrelas(hid.value);
})();

(function(){
  const btn = document.querySelector('.vf-share-instagram');
  if (!btn) return;
  btn.addEventListener('click', () => {
    const u = btn.getAttribute('data-share-url');
    if (!u) return;
    function feedback() {
      const sp = btn.querySelector('.vf-share-instagram-label');
      if (sp) {
        const t = sp.textContent;
        sp.textContent = 'Copiado!';
        setTimeout(() => { sp.textContent = t; }, 2000);
      }
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(u).then(feedback).catch(() => window.prompt('Copie o link do produto:', u));
    } else {
      window.prompt('Copie o link do produto:', u);
    }
  });
})();
</script>
@endpush
@endsection
