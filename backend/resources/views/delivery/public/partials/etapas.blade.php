@php
    $passo = $passoAtual ?? 'loja';
    $ordem = ['loja', 'carrinho', 'checkout', 'pedido'];
    $idx = array_search($passo, $ordem, true);
    if ($idx === false) {
        $idx = 0;
    }
    $pedidoUrl = $pedidoShowUrl ?? null;
    $etapas = [
        ['id' => 'loja', 'label' => 'Cardápio', 'href' => route('delivery.public.store', $slug)],
        ['id' => 'carrinho', 'label' => 'Carrinho', 'href' => null, 'action' => 'cart'],
        ['id' => 'checkout', 'label' => 'Checkout', 'href' => route('delivery.public.checkout', $slug)],
        ['id' => 'pedido', 'label' => 'Pedido', 'href' => $pedidoUrl],
    ];
@endphp
<nav class="vf-compra-fluxo" aria-label="Etapas da compra">
    <ol class="vf-compra-fluxo__list">
        @foreach ($etapas as $i => $e)
            @php
                $isDone = $i < $idx;
                $isCurrent = $i === $idx;
            @endphp
            <li class="vf-compra-fluxo__item">
                @if ($i > 0)<span class="vf-compra-fluxo__sep" aria-hidden="true">›</span>@endif
                @if ($isCurrent)
                    <span class="vf-compra-fluxo__atual" aria-current="step">{{ $e['label'] }}</span>
                @elseif (($e['action'] ?? null) === 'cart')
                    <button type="button" class="vf-compra-fluxo__link {{ $isDone ? 'is-done' : '' }}" data-cart-open>{{ $e['label'] }}</button>
                @elseif (! empty($e['href']))
                    <a href="{{ $e['href'] }}" class="vf-compra-fluxo__link {{ $isDone ? 'is-done' : '' }}">
                        @if ($isDone)<span aria-hidden="true">✓</span>@endif
                        {{ $e['label'] }}
                    </a>
                @else
                    <span class="vf-compra-fluxo__todo">{{ $e['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
