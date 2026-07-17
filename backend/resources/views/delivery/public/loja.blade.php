@extends('delivery.public.layout')
@section('title', $config->nome_loja ?: 'Loja Delivery')
@section('content')

@if(! empty($fidelidadeAtiva))
<div class="vf-fidelity-chip">
    <a href="{{ route('delivery.public.fidelity', $slug) }}">✦ Cartão fidelidade</a>
</div>
@endif

@if($banners->isNotEmpty())
<section class="vf-loja-banner" data-banner-carousel>
    <div class="vf-loja-banner__media">
        @foreach($banners as $banner)
            <img src="{{ $banner['url'] }}" alt="{{ $banner['alt'] }}" fetchpriority="{{ $loop->first ? 'high' : 'auto' }}" @if(!$loop->first) hidden @endif>
        @endforeach
        @if($banners->count() > 1)
            <button type="button" class="vf-loja-banner__ctrl prev" data-banner-prev aria-label="Banner anterior">‹</button>
            <button type="button" class="vf-loja-banner__ctrl next" data-banner-next aria-label="Próximo banner">›</button>
        @endif
        <div class="vf-loja-banner__scrim">
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
        <a class="vf-product-card" data-category="cat-{{ $produto->categoria_id }}" href="{{ route('delivery.public.product', [$slug, $produto->id]) }}">
            <div class="vf-product-card__photo">
                @if($produto->foto_url)<img src="{{ $produto->foto_url }}" alt="" loading="lazy">@else<span aria-hidden="true">▧</span>@endif
                @if((int)$produto->estoque <= 0)<b class="sold-out">Esgotado</b>@endif
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
  const banner = document.querySelector('[data-banner-carousel]');
  if (banner && banner.querySelectorAll('img').length > 1) {
    let slide = 0;
    const slides = [...banner.querySelectorAll('img')];
    const show = (n) => slides.forEach((img, i) => { img.hidden = i !== n; });
    banner.querySelector('[data-banner-prev]')?.addEventListener('click', () => show(slide = (slide - 1 + slides.length) % slides.length));
    banner.querySelector('[data-banner-next]')?.addEventListener('click', () => show(slide = (slide + 1) % slides.length));
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
