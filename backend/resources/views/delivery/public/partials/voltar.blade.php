@php
    $voltarHref = $voltarHref ?? route('delivery.public.store', $slug);
    $voltarLabel = $voltarLabel ?? 'Voltar ao cardápio';
    $voltarExtra = $voltarExtra ?? null;
@endphp
<div class="vf-back-bar">
    <a class="vf-back-btn" href="{{ $voltarHref }}">
        <span aria-hidden="true">←</span>
        <span>{{ $voltarLabel }}</span>
    </a>
    @if($voltarExtra)
        {!! $voltarExtra !!}
    @endif
</div>
