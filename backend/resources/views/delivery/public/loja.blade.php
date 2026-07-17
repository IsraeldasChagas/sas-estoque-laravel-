@extends('delivery.public.layout')
@section('title', $config->nome_loja ?: 'Loja Delivery')
@section('content')
@if($banners->isNotEmpty())
<section class="banner" data-banner-carousel>
    @foreach($banners as $banner)<img src="{{ $banner['url'] }}" alt="{{ $banner['alt'] }}" fetchpriority="{{ $loop->first ? 'high' : 'auto' }}" @if(!$loop->first) hidden @endif>@endforeach
    @if($banners->count() > 1)<button type="button" data-banner-prev aria-label="Banner anterior">‹</button><button type="button" data-banner-next aria-label="Próximo banner">›</button>@endif
</section>
@endif
@if($config->descricao)<p class="store-description">{{ $config->descricao }}</p>@endif
<section>
    <div class="section-title"><div><h1>Cardápio</h1><p>Escolha seus produtos favoritos</p></div></div>
    <div class="filters" role="tablist">
        <button class="active" data-filter="all">Todas</button>
        @if($adicionais->isNotEmpty())<button data-filter="additionals">Adicionais</button>@endif
        @foreach($categorias as $categoria)<button data-filter="cat-{{ $categoria->id }}">{{ $categoria->nome }}</button>@endforeach
    </div>
    <div class="product-grid" data-products>
        @foreach($produtos as $produto)
        <a class="product-card" data-category="cat-{{ $produto->categoria_id }}" href="{{ route('delivery.public.product', [$slug, $produto->id]) }}">
            <div class="photo">
                @if($produto->foto_url)<img src="{{ $produto->foto_url }}" alt="" loading="lazy">@else<span>▧</span>@endif
                @if((int)$produto->estoque <= 0)<b class="sold-out">Esgotado</b>@endif
            </div>
            <div class="product-body">
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
        @foreach($adicionais as $adicional)
        <article class="product-card additional-card" data-category="additionals" hidden>
            <div class="photo">@if($adicional->foto_url)<img src="{{ $adicional->foto_url }}" alt="" loading="lazy">@else<span>＋</span>@endif</div>
            <div class="product-body"><strong>{{ $adicional->nome }}</strong><small>Disponível na personalização dos produtos</small><b class="price">+ R$ {{ number_format((float)$adicional->preco, 2, ',', '.') }}</b></div>
        </article>
        @endforeach
    </div>
    @if($produtos->isEmpty())<div class="empty">Nenhum produto disponível no momento.</div>@endif
</section>
@push('scripts')
<script>
const banner=document.querySelector('[data-banner-carousel]');if(banner&&banner.querySelectorAll('img').length>1){let slide=0;const slides=[...banner.querySelectorAll('img')],show=n=>slides.forEach((img,i)=>img.hidden=i!==n);banner.querySelector('[data-banner-prev]').onclick=()=>show(slide=(slide-1+slides.length)%slides.length);banner.querySelector('[data-banner-next]').onclick=()=>show(slide=(slide+1)%slides.length);}
document.querySelectorAll('[data-filter]').forEach(function(button){
  button.addEventListener('click',function(){
    document.querySelectorAll('[data-filter]').forEach(b=>b.classList.remove('active')); button.classList.add('active');
    document.querySelectorAll('[data-category]').forEach(card=>card.hidden=button.dataset.filter!=='all'&&card.dataset.category!==button.dataset.filter);
  });
});
</script>
@endpush
@endsection
