@extends('delivery.public.layout')
@section('title', $config->nome_loja ?: 'Loja Delivery')
@section('content')

@if($banners->isNotEmpty())
@php $multiBanner = $banners->count() > 1; @endphp
<section class="vf-loja-banner vf-loja-banner-card">
    <div id="vfLojaBanner" class="vf-loja-banner-carousel vf-loja-banner-media{{ $multiBanner ? ' vf-loja-banner-carousel--fire vf-loja-banner-carousel--fade' : '' }}" data-banner-carousel @if($multiBanner) data-banner-auto="4500" @endif>
        <div class="vf-loja-banner-inner" data-banner-inner>
            @foreach($banners as $banner)
                <div class="vf-loja-banner-item{{ $loop->first ? ' is-active' : '' }}" data-banner-item @if(!$loop->first) aria-hidden="true" @endif>
                    <div class="vf-loja-banner-slide-frame">
                        <img src="{{ $banner['url'] }}" alt="{{ $banner['alt'] }}" class="vf-loja-banner-img" loading="{{ $loop->first ? 'eager' : 'lazy' }}" decoding="async" fetchpriority="{{ $loop->first ? 'high' : 'auto' }}">
                    </div>
                </div>
            @endforeach
        </div>
        @if($multiBanner)
            <button type="button" class="vf-loja-banner-ctrl prev" data-banner-prev aria-label="Banner anterior">‹</button>
            <button type="button" class="vf-loja-banner-ctrl next" data-banner-next aria-label="Próximo banner">›</button>
        @endif
        <div class="vf-loja-banner-scrim vf-loja-banner-scrim--carousel">
            <a href="{{ route('delivery.public.store', $slug) }}">Ver Promoções</a>
        </div>
    </div>
</section>
@endif

<form class="vf-filter-bar" method="get" action="{{ route('delivery.public.store', $slug) }}">
    <label class="vf-filter-bar__label" for="loja-cat">Categoria</label>
    <select class="vf-filter-bar__select" id="loja-cat" name="categoria_id" data-category-select>
        <option value="">Todas</option>
        @if($adicionais->isNotEmpty())
            <option value="adicionais">Adicionais</option>
        @endif
        @foreach($categorias as $categoria)
            <option value="{{ $categoria->id }}">{{ $categoria->nome }}</option>
        @endforeach
    </select>
</form>

<section class="vf-catalog-block" data-products-block>
    <div class="vf-catalog-head" data-products-head>
        <h2>Cardápio</h2>
        <p>Escolha seus produtos favoritos</p>
    </div>
    <div class="vf-product-grid" data-products>
        @foreach($produtos as $produto)
        @php $vendaDisponivel = (int) $produto->ativo === 1; @endphp
        <a class="vf-product-card" data-category="cat-{{ $produto->categoria_id }}" href="{{ route('delivery.public.product', [$slug, $produto->id]) }}">
            <div class="vf-product-card__photo">
                @if($produto->foto_url)<img src="{{ $produto->foto_url }}" alt="" loading="lazy">@else<span aria-hidden="true">▧</span>@endif
                @if(!$vendaDisponivel)<b class="sold-out">Indisponível</b>@endif
            </div>
            <div class="vf-product-card__body">
                <strong>{{ $produto->nome }}</strong>
                @if($produto->categoria_nome)<small>{{ $produto->categoria_nome }}</small>@endif
                @if($produto->personalizavel)<em>Personalizável</em>@endif
                @if($produto->permite_adicionais && ($produto->acrescimo_escolhas_min || $produto->acrescimo_escolhas_max !== null))
                    <span class="limits">Opções · mín. {{ $produto->acrescimo_escolhas_min }} · máx. {{ $produto->acrescimo_escolhas_max ?? '—' }}</span>
                @endif
                <b class="price">R$ {{ number_format((float)$produto->preco, 2, ',', '.') }}</b>
            </div>
        </a>
        @endforeach
    </div>
    @if($produtos->isEmpty())
        <div class="empty">Nenhum produto disponível no momento.</div>
    @endif
</section>

@if($adicionais->isNotEmpty())
<section class="vf-catalog-block" data-additionals-block hidden>
    <div class="vf-catalog-head">
        <h2>Adicionais nesta parte do cardápio</h2>
        <p>Opções de acréscimo disponíveis na loja. Você também pode personalizar nos produtos.</p>
    </div>
    <div class="vf-product-grid">
        @foreach($adicionais as $adicional)
        <article class="vf-product-card vf-product-card--static" data-category="additionals">
            <div class="vf-product-card__photo">
                @if($adicional->foto_url)<img src="{{ $adicional->foto_url }}" alt="" loading="lazy">@else<span aria-hidden="true">＋</span>@endif
            </div>
            <div class="vf-product-card__body">
                <strong>{{ $adicional->nome }}</strong>
                <small>Disponível na personalização</small>
                <b class="price">+ R$ {{ number_format((float)$adicional->preco, 2, ',', '.') }}</b>
            </div>
        </article>
        @endforeach
    </div>
</section>
@endif

@push('scripts')
<script>
(function () {
  const el = document.querySelector('[data-banner-carousel]');
  if (el) {
    const items = [...el.querySelectorAll('[data-banner-item]')];
    if (items.length > 1) {
      let slide = 0;
      let igniteTimer = null;
      let autoTimer = null;
      const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      if (reduced) {
        el.classList.remove('vf-loja-banner-carousel--fire', 'vf-loja-banner-carousel--fade');
      }

      const show = (n) => {
        if (n === slide) return;
        if (!reduced) {
          clearTimeout(igniteTimer);
          el.classList.add('vf-loja-banner--ignite');
          igniteTimer = setTimeout(() => el.classList.remove('vf-loja-banner--ignite'), 620);
        }
        slide = (n + items.length) % items.length;
        items.forEach((item, i) => {
          const active = i === slide;
          item.classList.toggle('is-active', active);
          item.setAttribute('aria-hidden', active ? 'false' : 'true');
        });
      };

      const next = () => show(slide + 1);
      const prev = () => show(slide - 1);
      el.querySelector('[data-banner-prev]')?.addEventListener('click', () => { prev(); restartAuto(); });
      el.querySelector('[data-banner-next]')?.addEventListener('click', () => { next(); restartAuto(); });

      function restartAuto() {
        clearInterval(autoTimer);
        if (reduced) return;
        const interval = Number(el.dataset.bannerAuto || 4500);
        autoTimer = setInterval(next, interval);
      }
      restartAuto();
      el.addEventListener('mouseenter', () => clearInterval(autoTimer));
      el.addEventListener('mouseleave', restartAuto);
    }
  }

  const select = document.querySelector('[data-category-select]');
  const productsBlock = document.querySelector('[data-products-block]');
  const additionalsBlock = document.querySelector('[data-additionals-block]');
  const cards = () => [...document.querySelectorAll('[data-products] [data-category]')];

  function applyFilter() {
    const value = select?.value || '';
    if (value === 'adicionais') {
      if (productsBlock) productsBlock.hidden = true;
      if (additionalsBlock) additionalsBlock.hidden = false;
      return;
    }
    if (productsBlock) productsBlock.hidden = false;
    if (additionalsBlock) additionalsBlock.hidden = true;
    cards().forEach((card) => {
      card.hidden = value !== '' && card.dataset.category !== `cat-${value}`;
    });
  }
  select?.addEventListener('change', applyFilter);
})();
</script>
@endpush
@endsection
