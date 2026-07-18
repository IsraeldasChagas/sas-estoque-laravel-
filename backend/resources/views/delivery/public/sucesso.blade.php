@extends('delivery.public.layout')
@section('title', 'Pedido recebido')
@section('content')
<section class="success-page">
    <div class="success-icon">✓</div>
    <h1>Pedido recebido!</h1>
    <p>Seu código é <strong>{{ $pedido->codigo_publico }}</strong>.</p>
    <p>Guarde o link abaixo para acompanhar o andamento com segurança.</p>
    <a class="btn primary" href="{{ route('delivery.public.order', [$slug, $pedido->codigo_publico, $token]) }}">Acompanhar pedido</a>
    <a class="btn ghost" href="{{ route('delivery.public.store', $slug) }}">← Continuar comprando</a>
</section>
@endsection
