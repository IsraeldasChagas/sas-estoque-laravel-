@extends('delivery.public.layout')
@section('title', 'Privacidade · Fidelidade · '.($config->nome_loja ?: 'Loja'))
@section('content')
@php
    $nomeUnidade = trim((string) ($unidade_fidelidade_nome ?? '')) ?: ($config->nome_loja ?: 'Loja');
    $secoes = $politica_secoes ?? [];
@endphp
<section class="vf-fidelity-page">
    <div class="vf-fidelity-hero">
        <span class="vf-fidelity-hero__icon" aria-hidden="true">✦</span>
        <h1>Política de privacidade</h1>
        <p>Cartão fidelidade · {{ $nomeUnidade }}</p>
    </div>

    <div class="vf-fidelity-card vf-fid-lgpd-politica">
        <p class="muted">Versão {{ \App\Services\Fidelidade\FidelidadeLgpdService::VERSAO }} · {{ \App\Services\Fidelidade\FidelidadeLgpdService::CONTROLADOR }}</p>

        @foreach($secoes as $titulo => $texto)
            <h2>{{ $titulo }}</h2>
            <p>{{ $texto }}</p>
        @endforeach

        <p class="muted vf-fid-lgpd-politica__note">
            Esta política aplica-se à consulta pública do cartão fidelidade nesta vitrine. Outros serviços do {{ \App\Services\Fidelidade\FidelidadeLgpdService::CONTROLADOR }} podem possuir termos específicos.
        </p>
    </div>

    <p class="vf-fid-back">
        <a class="btn ghost" href="{{ route('delivery.public.fidelity', $slug) }}">← Voltar ao cartão fidelidade</a>
        <a class="btn ghost" href="{{ route('delivery.public.store', $slug) }}">Continuar comprando</a>
    </p>
</section>
@endsection
