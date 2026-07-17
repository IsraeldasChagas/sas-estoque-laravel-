@extends('delivery.public.layout')
@section('title', $produto->nome.' · '.($config->nome_loja ?: 'Loja'))
@section('content')
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
        <form data-product-form data-min="{{ (int)$produto->acrescimo_escolhas_min }}" data-max="{{ $produto->acrescimo_escolhas_max ?? 9999 }}" data-remove-max="{{ $produto->max_ingredientes_retirar ?? 9999 }}">
            @if($adicionais->isNotEmpty())
            <div class="option-card"><h2>Adicionais</h2><p>Escolha entre {{ (int)$produto->acrescimo_escolhas_min }} e {{ $produto->acrescimo_escolhas_max ?? 'quantas quiser' }} opções.</p>
                @foreach($adicionais as $ad)
                <label class="option-row"><span><strong>{{ $ad->nome }}</strong><small>+ R$ {{ number_format((float)$ad->preco,2,',','.') }}</small></span>
                    @if($produto->acrescimos_loja_ui === 'checkbox')
                    <input type="checkbox" data-additional="{{ $ad->id }}" data-name="{{ $ad->nome }}" data-price="{{ $ad->preco }}" value="1">
                    @else
                    <input class="qty-input" type="number" min="0" max="{{ $produto->acrescimo_escolhas_max ?? 20 }}" value="0" data-additional="{{ $ad->id }}" data-name="{{ $ad->nome }}" data-price="{{ $ad->preco }}">
                    @endif
                </label>
                @endforeach
            </div>
            @endif
            @if($ingredientes->isNotEmpty() && (int)$produto->max_ingredientes_retirar > 0)
            <div class="option-card"><h2>Retirar ingredientes</h2><p>Você pode retirar até {{ $produto->max_ingredientes_retirar }}.</p>
                @foreach($ingredientes as $ing)
                <label class="option-row"><span>{{ $ing->nome }}</span><input type="checkbox" data-removal="{{ $ing->id }}" data-name="{{ $ing->nome }}"></label>
                @endforeach
            </div>
            @endif
            <div class="quantity"><button type="button" data-main-minus>−</button><input type="number" value="1" min="1" max="{{ $produto->estoque }}" data-main-qty><button type="button" data-main-plus>+</button></div>
            <p class="form-error" data-product-error></p>
            <button class="btn success wide" type="submit">Adicionar ao carrinho</button>
        </form>
        @endif
    </section>
</div>
@push('scripts')
<script>
(function(){
 const form=document.querySelector('[data-product-form]'); if(!form)return;
 const qty=form.querySelector('[data-main-qty]');
 form.querySelector('[data-main-minus]').onclick=()=>qty.value=Math.max(1,+qty.value-1);
 form.querySelector('[data-main-plus]').onclick=()=>qty.value=Math.min(+qty.max,+qty.value+1);
 form.addEventListener('submit',function(e){
   e.preventDefault(); const additions=[], removals=[]; let choices=0;
   form.querySelectorAll('[data-additional]').forEach(el=>{const q=el.type==='checkbox'?(el.checked?1:0):Math.max(0,+el.value);if(q){choices+=q;additions.push({id:+el.dataset.additional,nome:el.dataset.name,preco:+el.dataset.price,quantidade:q});}});
   form.querySelectorAll('[data-removal]:checked').forEach(el=>removals.push({id:+el.dataset.removal,nome:el.dataset.name}));
   const error=form.querySelector('[data-product-error]');
   if(choices < +form.dataset.min || choices > +form.dataset.max){error.textContent='Confira a quantidade mínima e máxima de adicionais.';return;}
   if(removals.length > +form.dataset.removeMax){error.textContent='Você excedeu o limite de ingredientes para retirar.';return;}
   error.textContent='';
   DeliveryCart.add({key:Date.now().toString(36)+Math.random().toString(36).slice(2),produto_id:{{ $produto->id }},nome:@json($produto->nome),foto:@json($produto->foto_url),preco:{{ (float)$produto->preco }},quantidade:Math.max(1,+qty.value),opcoes:{adicionais:additions,retiradas:removals}});
   document.querySelector('[data-cart-open]').click();
 });
})();
</script>
@endpush
@endsection
