@extends('delivery.public.layout')
@section('title', $produto->nome.' · '.($config->nome_loja ?: 'Loja'))
@section('content')
@php
    $minEsc = max(0, (int) ($produto->acrescimo_escolhas_min ?? 0));
    $maxEsc = $produto->acrescimo_escolhas_max !== null ? max(0, (int) $produto->acrescimo_escolhas_max) : null;
    $maxEscForm = $maxEsc ?? 9999;
    $uiAd = (string) ($produto->acrescimos_loja_ui ?? 'stepper');
    $uiRem = (string) ($produto->ingredientes_retirar_ui ?? 'stepper');
    $maxRem = max(0, (int) ($produto->max_ingredientes_retirar ?? 0));
@endphp
<nav class="breadcrumb"><a href="{{ route('delivery.public.store', $slug) }}">Cardápio</a> / {{ $produto->nome }}</nav>
<div class="detail-grid">
    <div class="detail-photo">@if($produto->foto_url)<img src="{{ $produto->foto_url }}" alt="{{ $produto->nome }}">@else<span>▧</span>@endif</div>
    <section>
        <h1>{{ $produto->nome }}</h1>
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
                        Escolha {{ $maxEsc }} opções: Mínimo: {{ $minEsc }} - Máximo: {{ $maxEsc }}
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
            <div class="quantity"><button type="button" data-main-minus>−</button><input type="number" value="1" min="1" max="{{ $produto->estoque }}" data-main-qty><button type="button" data-main-plus>+</button></div>
            <p class="form-error" data-product-error></p>
            <button class="btn success wide" type="submit">Adicionar ao carrinho</button>
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
    DeliveryCart.add({
      key: Date.now().toString(36) + Math.random().toString(36).slice(2),
      produto_id: {{ $produto->id }},
      nome: @json($produto->nome),
      foto: @json($produto->foto_url),
      preco: {{ (float) $produto->preco }},
      quantidade: Math.max(1, +qty.value),
      opcoes: { adicionais: additions, retiradas: removals },
    });
    document.querySelector('[data-cart-open]').click();
  });
})();
</script>
@endpush
@endsection
