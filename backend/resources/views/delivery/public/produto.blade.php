@extends('delivery.public.layout')
@section('title', $produto->nome.' · '.($config->nome_loja ?: 'Loja'))
@php
    $minEsc = max(0, (int) ($produto->acrescimo_escolhas_min ?? 0));
    $maxEsc = $produto->acrescimo_escolhas_max !== null ? max(0, (int) $produto->acrescimo_escolhas_max) : null;
    $maxEscForm = $maxEsc ?? 9999;
    $uiAd = (string) ($produto->acrescimos_loja_ui ?? 'stepper');
    $temLimiteAcrescimo = $maxEsc !== null || $minEsc > 0;
    if ($temLimiteAcrescimo) {
        $uiAd = 'stepper';
    }
    $uiRem = (string) ($produto->ingredientes_retirar_ui ?? 'stepper');
    $maxRem = max(0, (int) ($produto->max_ingredientes_retirar ?? 0));
    if ($maxRem > 0) {
        $uiRem = 'stepper';
    }
    $temRetirarIng = $ingredientes->isNotEmpty() && $maxRem > 0;
    $temPersonalizar = $adicionais->isNotEmpty() || $temRetirarIng;
    $escolhaSoIngredientes = $adicionais->isEmpty() && $temRetirarIng;
    $vendaDisponivel = (int) ($produto->ativo ?? 1) === 1;
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
    $sharePreco = 'R$ '.number_format((float) ($produto->preco ?? 0), 2, ',', '.');
    $waLojaUrl = $whatsDigits !== '' ? 'https://wa.me/'.$whatsDigits.'?text='.rawurlencode('Olá! Tenho interesse no produto: '.$shareNome) : null;
    $fbShareUrl = 'https://www.facebook.com/sharer/sharer.php?u='.rawurlencode($productUrl);
    $fotoShare = trim((string) ($produto->foto_url ?? ''));
    if ($fotoShare !== '' && ! preg_match('#^https?://#i', $fotoShare)) {
        $fotoShare = url($fotoShare);
    }
    $ogTitle = $shareNome.' — '.$shareLoja;
    $ogDescBase = trim((string) ($produto->descricao ?? ''));
    if ($ogDescBase === '') {
        $ogDescBase = 'Confira este produto na '.$shareLoja.'.';
    }
    $ogDesc = $sharePreco.' · '.$ogDescBase;
    if (mb_strlen($ogDesc) > 180) {
        $ogDesc = mb_substr($ogDesc, 0, 177).'…';
    }
    $ogImage = trim((string) ($produto->foto_url ?? ''));
    if ($ogImage !== '' && ! preg_match('#^https?://#i', $ogImage)) {
        $ogImage = url($ogImage);
    }
    if ($ogImage === '') {
        $logoFallback = trim((string) ($config->logo_url ?? $config->filial_logo_url ?? ''));
        if ($logoFallback !== '') {
            $ogImage = preg_match('#^https?://#i', $logoFallback) ? $logoFallback : url($logoFallback);
        }
    }
@endphp
@push('head_meta')
    <meta property="og:title" content="{{ $ogTitle }}">
    <meta property="og:description" content="{{ $ogDesc }}">
    <meta property="og:type" content="product">
    <meta property="og:url" content="{{ $productUrl }}">
    <meta property="og:site_name" content="{{ $shareLoja }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $ogTitle }}">
    <meta name="twitter:description" content="{{ $ogDesc }}">
    @if ($ogImage !== '')
        <meta property="og:image" content="{{ $ogImage }}">
        <meta property="og:image:secure_url" content="{{ $ogImage }}">
        <meta property="og:image:alt" content="{{ $shareNome }}">
        <meta name="twitter:image" content="{{ $ogImage }}">
        <link rel="image_src" href="{{ $ogImage }}">
    @endif
    <meta name="description" content="{{ $ogDesc }}">
@endpush
@section('content')
<nav class="breadcrumb"><a href="{{ route('delivery.public.store', $slug) }}">Cardápio</a> / {{ $produto->nome }}</nav>
<div class="detail-grid">
    <div class="detail-photo">@if($produto->foto_url)<img src="{{ $produto->foto_url }}" alt="{{ $produto->nome }}">@else<span>▧</span>@endif</div>
    <section>
        <div class="detail-head">
            <h1>{{ $produto->nome }}</h1>
            <div class="vf-produto-share-bloco">
                <span id="vf-share-produto-legenda" class="vf-produto-share-legenda">Compartilhar</span>
                <div class="vf-produto-share" role="group" aria-labelledby="vf-share-produto-legenda">
                    <button type="button"
                       class="vf-produto-share__btn vf-produto-share__btn--wa vf-share-whatsapp"
                       data-share-image="{{ $fotoShare }}"
                       title="WhatsApp — envia só a foto do produto"
                       aria-label="Compartilhar foto no WhatsApp"
                       @disabled($fotoShare === '')>
                        <svg class="vf-produto-share__ico" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M12.04 2c-5.46 0-9.91 4.43-9.91 9.9 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.9-4.44 9.9-9.9C21.94 6.43 17.5 2 12.04 2zm5.83 14.1c-.24.68-1.41 1.25-1.96 1.33-.5.07-1.14.1-1.84-.12-.42-.13-.97-.32-1.66-.62-2.92-1.26-4.82-4.2-4.96-4.4-.14-.19-1.15-1.53-1.15-2.92 0-1.39.73-2.07.99-2.35.26-.28.57-.35.76-.35h.55c.17 0 .4-.06.63.48.24.56.8 1.95.87 2.09.07.14.12.3.02.49-.1.19-.14.3-.28.47-.14.16-.3.36-.42.49-.14.14-.28.29-.12.56.16.28.71 1.17 1.53 1.9 1.05.93 1.94 1.22 2.22 1.36.28.14.44.12.6-.07.17-.19.7-.81.89-1.09.19-.28.37-.23.63-.14.26.09 1.64.77 1.92.91.28.14.47.21.54.33.07.12.07.7-.17 1.38z"/></svg>
                        <span class="vf-produto-share__label">WhatsApp</span>
                    </button>
                    <a href="{{ $fbShareUrl }}" target="_blank" rel="noopener noreferrer"
                       class="vf-produto-share__btn vf-produto-share__btn--fb" title="Facebook" aria-label="Compartilhar no Facebook">
                        <svg class="vf-produto-share__ico" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.8-4.7 4.54-4.7 1.32 0 2.7.24 2.7.24v2.97h-1.52c-1.5 0-1.97.93-1.97 1.89v2.26h3.35l-.54 3.49h-2.81V24C19.61 23.1 24 18.1 24 12.07z"/></svg>
                        <span class="vf-produto-share__label">Facebook</span>
                    </a>
                    <button type="button" class="vf-produto-share__btn vf-produto-share__btn--ig vf-share-instagram"
                            data-share-url="{{ $productUrl }}"
                            title="Instagram — copia o link para você colar no app"
                            aria-label="Copiar link do produto para compartilhar no Instagram">
                        <svg class="vf-produto-share__ico" viewBox="0 0 24 24" width="18" height="18" fill="none" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="2"/><circle cx="17.5" cy="6.5" r="1.2" fill="currentColor"/></svg>
                        <span class="vf-produto-share__label vf-share-instagram-label">Instagram</span>
                    </button>
                </div>
            </div>
        </div>
        @if($vendaDisponivel)
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
        <p class="{{ $vendaDisponivel ? 'stock' : 'unavailable' }}">
            @if($vendaDisponivel)
                Disponível para pedido
            @else
                Indisponível no momento
            @endif
        </p>
        @if($vendaDisponivel)
        <form data-product-form
              data-min="{{ $minEsc }}"
              data-max="{{ $maxEscForm }}"
              data-remove-max="{{ $maxRem > 0 ? $maxRem : 9999 }}"
              data-ui-additional="{{ $uiAd }}"
              data-ui-removal="{{ $uiRem }}">
            @if($temPersonalizar)
            <div class="option-card option-card--personalizar vf-card-personalizar-produto">
                <h2>Personalizar</h2>

                @if($adicionais->isNotEmpty())
                    @if($temLimiteAcrescimo && $maxEsc !== null && $minEsc === $maxEsc)
                        <p class="personalize-limit-line">
                            <span class="personalize-limit-line__title">Escolha {{ $maxEsc }} opções</span>
                            <span class="vf-personalizar-limite-chip">Mínimo: {{ $minEsc }} · Máximo: {{ $maxEsc }}</span>
                        </p>
                    @elseif($temLimiteAcrescimo && $maxEsc !== null)
                        <p class="personalize-limit-line">
                            <span class="personalize-limit-line__title">Opções</span>
                            @if($minEsc > 0)
                                <span class="vf-personalizar-limite-chip">Mínimo: {{ $minEsc }} · Máximo: {{ $maxEsc }}</span>
                            @else
                                <span class="vf-personalizar-limite-chip">Máximo: {{ $maxEsc }}</span>
                            @endif
                        </p>
                    @endif
                    @if($uiAd === 'checkbox')
                    <div class="vf-personalizar-grid vf-acrescimo-checkbox-grid"
                         data-usa-limite="{{ $temLimiteAcrescimo ? '1' : '0' }}"
                         data-min="{{ $temLimiteAcrescimo ? $minEsc : 0 }}"
                         data-max="{{ $temLimiteAcrescimo && $maxEsc !== null ? $maxEsc : 99999 }}">
                        @foreach($adicionais as $ad)
                        <div class="vf-escolha-card vf-escolha-card--acrescimo-chk" data-additional-item>
                            <div class="vf-escolha-card-inner vf-escolha-card-inner--retirar-chk">
                                <span class="vf-escolha-bar" aria-hidden="true"></span>
                                <label class="vf-retirar-chk-wrap">
                                    <input type="checkbox" class="vf-acrescimo-chk"
                                           data-additional="{{ $ad->id }}"
                                           data-name="{{ $ad->nome }}"
                                           data-price="{{ $ad->preco }}">
                                    <span class="vf-personalizar-nome">
                                        {{ $ad->nome }}
                                        @if((float) $ad->preco > 0)
                                            <small>+ R$ {{ number_format((float) $ad->preco, 2, ',', '.') }} @if($temLimiteAcrescimo)<span class="muted">cada</span>@endif</small>
                                        @endif
                                    </span>
                                </label>
                                <input type="hidden" class="vf-acrescimo-qty-input"
                                       data-additional="{{ $ad->id }}"
                                       data-name="{{ $ad->nome }}"
                                       data-price="{{ $ad->preco }}"
                                       value="0">
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <div class="vf-personalizar-grid vf-acrescimo-stepper-grid"
                         id="vf-acrescimos-stepper"
                         data-usa-limite="{{ $temLimiteAcrescimo ? '1' : '0' }}"
                         data-min="{{ $temLimiteAcrescimo ? $minEsc : 0 }}"
                         data-max="{{ $temLimiteAcrescimo && $maxEsc !== null ? $maxEsc : ($maxEscForm) }}">
                        @foreach($adicionais as $ad)
                        <div class="vf-escolha-card" data-additional-item data-ad-id="{{ $ad->id }}">
                            <div class="vf-escolha-card-inner">
                                <span class="vf-escolha-bar" aria-hidden="true"></span>
                                <div class="vf-escolha-textos">
                                    <span class="vf-personalizar-nome">
                                        {{ $ad->nome }}
                                        @if((float) $ad->preco > 0)
                                            <small>+ R$ {{ number_format((float) $ad->preco, 2, ',', '.') }} @if($temLimiteAcrescimo)<span class="muted">cada</span>@endif</small>
                                        @endif
                                    </span>
                                    <span class="vf-escolha-badge">✓ Selecionado</span>
                                </div>
                                <div class="vf-escolha-stepper" role="group" aria-label="Quantidade {{ $ad->nome }}">
                                    <button type="button" class="vf-escolha-btn vf-escolha-btn--menos" data-additional-minus aria-label="Diminuir {{ $ad->nome }}">−</button>
                                    <span class="vf-escolha-qty-wrap"><span class="vf-escolha-qty-disp" data-additional-qty>0</span></span>
                                    <input type="hidden" class="vf-acrescimo-qty-input"
                                           data-additional="{{ $ad->id }}"
                                           data-name="{{ $ad->nome }}"
                                           data-price="{{ $ad->preco }}"
                                           value="0">
                                    <button type="button" class="vf-escolha-btn vf-escolha-btn--mais" data-additional-plus aria-label="Aumentar {{ $ad->nome }}">+</button>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @endif
                @endif

                @if($temRetirarIng)
                    <p class="personalize-limit-line {{ $adicionais->isNotEmpty() ? 'personalize-limit-line--spaced' : '' }}">
                        @if($escolhaSoIngredientes || $uiRem === 'stepper')
                            <span class="personalize-limit-line__title">Escolha {{ $maxRem }} {{ $maxRem === 1 ? 'opção' : 'opções' }}</span>
                            <span class="vf-personalizar-limite-chip">Mínimo: {{ $maxRem }} · Máximo: {{ $maxRem }}</span>
                        @else
                            <span class="personalize-limit-line__title">Retirar ingredientes</span>
                            <span class="vf-personalizar-limite-chip">Até {{ $maxRem }}</span>
                        @endif
                    </p>
                    @if($uiRem === 'checkbox')
                    <div class="vf-personalizar-grid vf-retirar-checkbox-grid"
                         id="vf-retirar-checkbox"
                         data-min-total="{{ $maxRem }}"
                         data-max-total="{{ $maxRem }}">
                        @foreach($ingredientes as $ing)
                        <div class="vf-escolha-card vf-escolha-card--retirar vf-escolha-card--retirar-chk" data-removal-item>
                            <div class="vf-escolha-card-inner vf-escolha-card-inner--retirar-chk">
                                <span class="vf-escolha-bar" aria-hidden="true"></span>
                                <label class="vf-retirar-chk-wrap">
                                    <input type="checkbox" class="vf-retirar-chk"
                                           data-removal="{{ $ing->id }}"
                                           data-name="{{ $ing->nome }}">
                                    <span class="vf-personalizar-nome">{{ $ing->nome }}</span>
                                </label>
                                <input type="hidden" class="vf-retirar-qty-input"
                                       data-removal="{{ $ing->id }}"
                                       data-name="{{ $ing->nome }}"
                                       value="0">
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <div class="vf-personalizar-grid vf-acrescimo-stepper-grid vf-retirar-stepper-grid {{ $adicionais->isNotEmpty() ? 'vf-retirar-stepper-grid--after-acrescimo' : '' }}"
                         id="vf-retirar-stepper"
                         data-min-total="{{ $maxRem }}"
                         data-max-total="{{ $maxRem }}">
                        @foreach($ingredientes as $ing)
                        <div class="vf-escolha-card vf-escolha-card--retirar" data-removal-item data-ing-id="{{ $ing->id }}">
                            <div class="vf-escolha-card-inner">
                                <span class="vf-escolha-bar" aria-hidden="true"></span>
                                <div class="vf-escolha-textos">
                                    <span class="vf-personalizar-nome">{{ $ing->nome }}</span>
                                    <span class="vf-escolha-badge vf-escolha-badge--retirar">✓ Selecionado</span>
                                </div>
                                <div class="vf-escolha-stepper" role="group" aria-label="Quantidade {{ $ing->nome }}">
                                    <button type="button" class="vf-escolha-btn vf-escolha-btn--menos" data-removal-minus aria-label="Diminuir {{ $ing->nome }}">−</button>
                                    <span class="vf-escolha-qty-wrap"><span class="vf-escolha-qty-disp" data-removal-qty>0</span></span>
                                    <input type="hidden" class="vf-retirar-qty-input"
                                           data-removal="{{ $ing->id }}"
                                           data-name="{{ $ing->nome }}"
                                           value="0">
                                    <button type="button" class="vf-escolha-btn vf-escolha-btn--mais" data-removal-plus aria-label="Aumentar {{ $ing->nome }}">+</button>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @endif
                @endif
            </div>
            @endif
            <label class="detail-field">
                <span class="detail-field__label">Observação (opcional)</span>
                <textarea name="observacao" rows="3" maxlength="500" placeholder="Ex.: ponto da carne, sem cebola…" data-product-obs></textarea>
            </label>
            <div class="detail-qty-row">
                <span class="detail-field__label">Quantidade</span>
                <div class="quantity"><button type="button" data-main-minus>−</button><input type="number" value="1" min="1" max="99" data-main-qty><button type="button" data-main-plus>+</button></div>
            </div>
            <p class="form-error" data-product-error></p>
            <div class="detail-actions">
                <button class="btn primary wide" type="submit">Adicionar ao carrinho</button>
                @if($waLojaUrl)
                    <a class="btn success wide detail-wa-loja" href="{{ $waLojaUrl }}" target="_blank" rel="noopener noreferrer">WhatsApp da loja</a>
                @endif
                <a class="btn ghost wide vf-back-after-action" href="{{ route('delivery.public.store', $slug) }}">← Continuar comprando</a>
            </div>
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
  const stepperWrap = document.getElementById('vf-acrescimos-stepper');
  const usaLimite = stepperWrap ? stepperWrap.dataset.usaLimite === '1' : true;
  const minChoices = stepperWrap
    ? Math.max(0, +stepperWrap.dataset.min || 0)
    : Math.max(0, +form.dataset.min || 0);
  const maxChoices = stepperWrap
    ? Math.max(minChoices, +stepperWrap.dataset.max || 9999)
    : Math.max(minChoices, +form.dataset.max || 9999);
  const maxRemovals = Math.max(0, +form.dataset.removeMax || 9999);
  const uiAdditional = form.dataset.uiAdditional || 'stepper';
  const uiRemoval = form.dataset.uiRemoval || 'stepper';
  const maxPorOpcao = 999;

  form.querySelector('[data-main-minus]').onclick = () => { qty.value = Math.max(1, +qty.value - 1); };
  form.querySelector('[data-main-plus]').onclick = () => { qty.value = Math.min(+qty.max, +qty.value + 1); };

  function somaAcrescimos() {
    let total = 0;
    form.querySelectorAll('.vf-acrescimo-qty-input').forEach((inp) => {
      total += Math.max(0, +inp.value || 0);
    });
    return total;
  }

  function additionalTotal() {
    if (uiAdditional === 'checkbox') {
      let total = 0;
      form.querySelectorAll('.vf-acrescimo-chk:checked').forEach(() => { total += 1; });
      return total;
    }
    return somaAcrescimos();
  }

  function atualizarCardAcrescimo(card) {
    const inp = card.querySelector('.vf-acrescimo-qty-input');
    if (!inp) return;
    const q = Math.max(0, +inp.value || 0);
    const disp = card.querySelector('[data-additional-qty]');
    const btnMais = card.querySelector('[data-additional-plus]');
    const btnMenos = card.querySelector('[data-additional-minus]');
    if (disp) disp.textContent = String(q);
    card.classList.toggle('vf-escolha-card--ativo', q > 0);
    if (btnMenos) btnMenos.disabled = q < 1;
    const total = somaAcrescimos();
    let podeMais = q < maxPorOpcao;
    if (usaLimite && podeMais && total >= maxChoices) podeMais = false;
    if (btnMais) btnMais.disabled = !podeMais;
  }

  function syncAllAcrescimoCards() {
    form.querySelectorAll('#vf-acrescimos-stepper .vf-escolha-card').forEach(atualizarCardAcrescimo);
  }

  if (stepperWrap) {
    stepperWrap.querySelectorAll('.vf-escolha-card').forEach((card) => {
      const inp = card.querySelector('.vf-acrescimo-qty-input');
      const btnMais = card.querySelector('[data-additional-plus]');
      const btnMenos = card.querySelector('[data-additional-minus]');

      function setQ(novo) {
        const q = Math.max(0, Math.min(maxPorOpcao, +novo || 0));
        if (inp) inp.value = String(q);
        syncAllAcrescimoCards();
      }

      btnMais?.addEventListener('click', () => {
        const q = Math.max(0, +inp?.value || 0);
        if (q >= maxPorOpcao) return;
        if (usaLimite && somaAcrescimos() >= maxChoices) {
          error.textContent = 'Você já atingiu o máximo de ' + maxChoices + ' opções (somando as quantidades).';
          return;
        }
        error.textContent = '';
        setQ(q + 1);
      });

      btnMenos?.addEventListener('click', () => {
        setQ(Math.max(0, (+inp?.value || 0) - 1));
        error.textContent = '';
      });

      atualizarCardAcrescimo(card);
    });
  }

  form.querySelectorAll('.vf-acrescimo-chk').forEach((chk) => {
    chk.addEventListener('change', () => {
      const card = chk.closest('[data-additional-item]');
      const hid = card?.querySelector('.vf-acrescimo-qty-input');
      if (hid) hid.value = chk.checked ? '1' : '0';
      card?.classList.toggle('vf-escolha-card--ativo', chk.checked);
      const total = additionalTotal();
      form.querySelectorAll('.vf-acrescimo-chk').forEach((el) => {
        if (!el.checked && usaLimite) el.disabled = total >= maxChoices;
      });
    });
  });

  const retirarWrap = document.getElementById('vf-retirar-stepper');
  const minRemTotal = retirarWrap ? Math.max(0, +retirarWrap.dataset.minTotal || 0) : 0;
  const maxRemTotal = retirarWrap ? Math.max(minRemTotal, +retirarWrap.dataset.maxTotal || maxRemovals) : maxRemovals;

  function somaRetiradas() {
    let total = 0;
    form.querySelectorAll('.vf-retirar-qty-input').forEach((inp) => {
      total += Math.max(0, +inp.value || 0);
    });
    return total;
  }

  function removalTotal() {
    if (uiRemoval === 'checkbox') {
      let total = 0;
      form.querySelectorAll('.vf-retirar-chk:checked').forEach(() => { total += 1; });
      return total;
    }
    return somaRetiradas();
  }

  function atualizarCardRetirada(card) {
    const inp = card.querySelector('.vf-retirar-qty-input');
    if (!inp) return;
    const q = Math.max(0, +inp.value || 0);
    const disp = card.querySelector('[data-removal-qty]');
    const btnMais = card.querySelector('[data-removal-plus]');
    const btnMenos = card.querySelector('[data-removal-minus]');
    if (disp) disp.textContent = String(q);
    card.classList.toggle('vf-escolha-card--ativo', q > 0);
    if (btnMenos) btnMenos.disabled = q < 1;
    const total = somaRetiradas();
    let podeMais = q < maxPorOpcao;
    if (podeMais && total >= maxRemTotal) podeMais = false;
    if (btnMais) btnMais.disabled = !podeMais;
  }

  function syncAllRetiradaCards() {
    form.querySelectorAll('#vf-retirar-stepper .vf-escolha-card--retirar').forEach(atualizarCardRetirada);
  }

  if (retirarWrap) {
    retirarWrap.querySelectorAll('.vf-escolha-card--retirar').forEach((card) => {
      const inp = card.querySelector('.vf-retirar-qty-input');
      const btnMais = card.querySelector('[data-removal-plus]');
      const btnMenos = card.querySelector('[data-removal-minus]');

      function setQ(novo) {
        const q = Math.max(0, Math.min(maxPorOpcao, +novo || 0));
        if (inp) inp.value = String(q);
        syncAllRetiradaCards();
      }

      btnMais?.addEventListener('click', () => {
        const q = Math.max(0, +inp?.value || 0);
        if (q >= maxPorOpcao) return;
        if (somaRetiradas() >= maxRemTotal) {
          error.textContent = 'Você já atingiu o máximo de ' + maxRemTotal + ' opções (somando as quantidades).';
          return;
        }
        error.textContent = '';
        setQ(q + 1);
      });

      btnMenos?.addEventListener('click', () => {
        setQ(Math.max(0, (+inp?.value || 0) - 1));
        error.textContent = '';
      });

      atualizarCardRetirada(card);
    });
  }

  form.querySelectorAll('.vf-retirar-chk').forEach((chk) => {
    chk.addEventListener('change', () => {
      const card = chk.closest('[data-removal-item]');
      const hid = card?.querySelector('.vf-retirar-qty-input');
      if (hid) hid.value = chk.checked ? '1' : '0';
      card?.classList.toggle('vf-escolha-card--ativo', chk.checked);
      const total = removalTotal();
      form.querySelectorAll('.vf-retirar-chk').forEach((el) => {
        if (!el.checked) el.disabled = total >= maxRemTotal;
      });
    });
  });

  form.addEventListener('submit', function(e) {
    e.preventDefault();
    const additions = [];
    const removals = [];
    let choices = additionalTotal();

    if (uiAdditional === 'checkbox') {
      form.querySelectorAll('.vf-acrescimo-chk:checked').forEach((el) => {
        additions.push({
          id: +el.dataset.additional,
          nome: el.dataset.name,
          preco: +el.dataset.price,
          quantidade: 1,
        });
      });
    } else {
      form.querySelectorAll('.vf-acrescimo-qty-input').forEach((hidden) => {
        const q = Math.max(0, +hidden.value || 0);
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
      form.querySelectorAll('.vf-retirar-chk:checked').forEach((el) => {
        removals.push({ id: +el.dataset.removal, nome: el.dataset.name, quantidade: 1 });
      });
    } else {
      form.querySelectorAll('.vf-retirar-qty-input').forEach((hidden) => {
        const q = Math.max(0, +hidden.value || 0);
        if (q > 0) {
          removals.push({
            id: +hidden.dataset.removal,
            nome: hidden.dataset.name,
            quantidade: q,
          });
        }
      });
    }

    const temAcrescimos = !!stepperWrap || form.querySelector('.vf-acrescimo-chk');
    if (usaLimite && temAcrescimos && (choices < minChoices || choices > maxChoices)) {
      error.textContent = minChoices === maxChoices
        ? 'Escolha exatamente ' + maxChoices + ' opções (somando as quantidades).'
        : 'Escolha entre ' + minChoices + ' e ' + maxChoices + ' opções (somando as quantidades).';
      return;
    }
    if (retirarWrap && (removalTotal() < minRemTotal || removalTotal() > maxRemTotal)) {
      error.textContent = minRemTotal === maxRemTotal
        ? 'Escolha exatamente ' + maxRemTotal + ' opções (somando as quantidades).'
        : 'Escolha entre ' + minRemTotal + ' e ' + maxRemTotal + ' opções (somando as quantidades).';
      return;
    }
    if (!retirarWrap && removals.length > maxRemovals) {
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
  const btnWa = document.querySelector('.vf-share-whatsapp');
  if (btnWa) {
    btnWa.addEventListener('click', () => {
      const imgUrl = btnWa.getAttribute('data-share-image');
      if (!imgUrl) {
        window.alert('Este produto não tem foto para compartilhar.');
        return;
      }
      btnWa.disabled = true;
      fetch(imgUrl, { credentials: 'same-origin', cache: 'force-cache' })
        .then((res) => {
          if (!res.ok) throw new Error('foto');
          return res.blob();
        })
        .then((blob) => {
          const tipo = blob.type || 'image/jpeg';
          const ext = (tipo.split('/')[1] || 'jpg').replace('jpeg', 'jpg');
          const file = new File([blob], 'produto.' + ext, { type: tipo });
          if (navigator.canShare && navigator.canShare({ files: [file] })) {
            // Só a imagem — sem texto e sem link.
            return navigator.share({ files: [file] });
          }
          const a = document.createElement('a');
          a.href = URL.createObjectURL(blob);
          a.download = 'produto.' + ext;
          document.body.appendChild(a);
          a.click();
          a.remove();
          setTimeout(() => URL.revokeObjectURL(a.href), 1500);
          window.alert('Foto baixada. Envie a imagem no WhatsApp.');
        })
        .catch(() => {
          window.alert('Não foi possível compartilhar a foto. Tente no celular.');
        })
        .finally(() => {
          btnWa.disabled = false;
        });
    });
  }

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
