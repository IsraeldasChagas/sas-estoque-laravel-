@extends('delivery.public.layout')
@section('title', 'Fidelidade · '.($config->nome_loja ?: 'Loja'))
@section('content')
<section class="vf-fidelity-page">
    <div class="vf-fidelity-hero">
        <span class="vf-fidelity-hero__icon" aria-hidden="true">✦</span>
        <h1>Cartão fidelidade</h1>
        <p>{{ $programa->nome ?? 'Programa de fidelidade' }}</p>
    </div>
    <div class="vf-fidelity-card">
        <h2>Como funciona</h2>
        <ul>
            <li>Acumule pontos a cada pedido na loja.</li>
            <li>Troque pontos por prêmios e benefícios.</li>
            <li>Acompanhe seu saldo com a loja.</li>
        </ul>
        @if(! empty($programa->descricao))
            <p class="muted">{{ $programa->descricao }}</p>
        @endif
        <a class="btn primary" href="{{ route('delivery.public.store', $slug) }}">Voltar ao cardápio</a>
    </div>
</section>
@endsection
