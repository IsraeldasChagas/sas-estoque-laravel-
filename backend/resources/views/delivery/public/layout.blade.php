<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#2563eb">
    <title>@yield('title', $config->nome_loja ?: 'Loja')</title>
    <link rel="stylesheet" href="{{ asset('assets/delivery/store.css') }}">
</head>
<body data-store="{{ $slug }}">
<header class="store-header">
    <div class="shell header-inner">
        <a class="brand" href="{{ route('delivery.public.store', $slug) }}">
            @if($config->logo_url)
                <img src="{{ $config->logo_url }}" alt="">
            @else
                <span class="logo-placeholder">S</span>
            @endif
            <span><strong>{{ $config->nome_loja ?: 'Loja Delivery' }}</strong><small class="{{ $config->aberta ? 'open' : 'closed' }}">{{ $config->aberta ? 'Aberta' : 'Fechada' }}</small></span>
        </a>
        <nav class="header-actions">
            @if($config->telefone || $config->whatsapp || $config->endereco_texto)
            <details class="contact"><summary>Contato</summary><div>
                @if($config->endereco_texto)<p>📍 {{ $config->endereco_texto }}</p>@endif
                @if($config->whatsapp)<a href="https://wa.me/{{ preg_replace('/\D+/', '', $config->whatsapp) }}" rel="noopener" target="_blank">WhatsApp: {{ $config->whatsapp }}</a>@endif
                @if($config->telefone)<a href="tel:{{ preg_replace('/[^\d+]/', '', $config->telefone) }}">Telefone: {{ $config->telefone }}</a>@endif
            </div></details>
            @endif
            <button type="button" class="btn ghost" data-track-open>Acompanhar</button>
            <button type="button" class="btn primary cart-button" data-cart-open>Carrinho <span data-cart-count>0</span></button>
        </nav>
    </div>
</header>
<main class="shell page">@yield('content')</main>
<aside class="drawer" data-cart-drawer aria-hidden="true">
    <div class="drawer-head"><strong>Seu carrinho</strong><button type="button" data-cart-close>×</button></div>
    <div data-cart-items></div>
    <div class="drawer-total"><span>Subtotal</span><strong data-cart-total>R$ 0,00</strong></div>
    <a class="btn success wide" href="{{ route('delivery.public.checkout', $slug) }}">Ir para checkout</a>
</aside>
<div class="backdrop" data-cart-close></div>
<dialog data-track-dialog>
    <button class="dialog-close" data-track-close>×</button>
    <h2>Acompanhar pedido</h2>
    <p>Use o link seguro recebido após finalizar seu pedido.</p>
</dialog>
<footer><div class="shell">{{ $config->nome_loja ?: 'Loja Delivery' }} · Compra segura</div></footer>
<script>window.deliveryStore={slug:@json($slug),checkout:@json(route('delivery.public.checkout',$slug)),csrf:@json(csrf_token())};</script>
<script src="{{ asset('assets/delivery/store.js') }}"></script>
@stack('scripts')
</body>
</html>
