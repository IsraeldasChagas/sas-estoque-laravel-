@php
    $nomeLoja = $config->nome_loja ?: 'Loja Delivery';
    $primary = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($config->cor_primaria ?? '')) ? $config->cor_primaria : '#2563eb';
    $endereco = trim((string) ($config->endereco_texto ?? ''));
    $whatsRaw = trim((string) ($config->whatsapp ?? ''));
    $whatsDigits = $whatsRaw !== '' ? preg_replace('/\D+/', '', $whatsRaw) : '';
    if (is_string($whatsDigits) && $whatsDigits !== '' && (strlen($whatsDigits) === 10 || strlen($whatsDigits) === 11)) {
        $whatsDigits = '55'.$whatsDigits;
    }
    $telRaw = trim((string) ($config->telefone ?? ''));
    $igUrl = trim((string) ($config->instagram_url ?? ''));
    $fbUrl = trim((string) ($config->facebook_url ?? ''));
    $temContato = $endereco !== '' || $whatsDigits !== '' || $telRaw !== '' || $igUrl !== '' || $fbUrl !== '';
    $filialNome = trim((string) ($config->filial_nome ?? ''));
    $filialHref = trim((string) ($config->filial_link_url ?? ''));
    $filialLogo = $config->filial_logo_url ?? null;
    $fidelidadeAtiva = (bool) ($fidelidadeAtiva ?? false);
    $passoAtual = $passoAtual ?? 'loja';
    $entregaTexto = trim((string) ($config->entrega_texto ?? '')) ?: 'Entrega em até 45 min · Pagamento na entrega ou online';
    $footerFixed = ($footerFixed ?? true) && in_array($passoAtual, ['loja', 'carrinho'], true);
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="{{ $primary }}">
    <title>@yield('title', $nomeLoja)</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/delivery/store.css') }}?v=20260717-vf4">
    <style>:root{--primary:{{ $primary }};--primary-soft:color-mix(in srgb, {{ $primary }} 14%, white);}</style>
</head>
<body class="vf-body {{ $footerFixed ? 'vf-body--footer-fixed' : '' }}" data-store="{{ $slug }}">
<header class="vf-publico-header">
    <div class="shell header-pad">
        <div class="vf-header-row">
            <div class="vf-brands">
                <a href="{{ route('delivery.public.store', $slug) }}" class="vf-store-brand vf-store-brand--atual" title="Você está nesta unidade">
                    @if($config->logo_url)
                        <img src="{{ $config->logo_url }}" alt="" class="vf-store-logo" width="66" height="66">
                    @else
                        <span class="vf-store-logo vf-store-logo--ph" aria-hidden="true">{{ mb_strtoupper(mb_substr($nomeLoja, 0, 1)) }}</span>
                    @endif
                    <span class="vf-store-brand-text">
                        <span class="vf-store-name">{{ $nomeLoja }} <span class="vf-pill vf-pill--aqui">Aqui</span></span>
                        <span class="vf-status {{ $config->aberta ? 'is-open' : 'is-closed' }}">{{ $config->aberta ? 'Aberta' : 'Fechada' }}</span>
                    </span>
                </a>
                @if($filialNome !== '')
                    <span class="vf-brand-sep" aria-hidden="true"></span>
                    @if($filialHref !== '')
                        <a href="{{ $filialHref }}" class="vf-store-brand vf-store-brand--outra" rel="noopener">
                    @else
                        <div class="vf-store-brand vf-store-brand--outra" role="group" aria-label="Outra unidade">
                    @endif
                        @if($filialLogo)
                            <img src="{{ $filialLogo }}" alt="" class="vf-store-logo" width="66" height="66">
                        @else
                            <span class="vf-store-logo vf-store-logo--ph" aria-hidden="true">{{ mb_strtoupper(mb_substr($filialNome, 0, 1)) }}</span>
                        @endif
                        <span class="vf-store-brand-text">
                            <span class="vf-store-name">{{ $filialNome }} <span class="vf-pill vf-pill--ver">Ver</span></span>
                        </span>
                    @if($filialHref !== '')
                        </a>
                    @else
                        </div>
                    @endif
                @endif
            </div>
            <div class="vf-header-actions">
                @if($temContato)
                    <button class="btn ghost icon-only" type="button" data-contact-toggle aria-expanded="true" aria-controls="vf-store-info" title="Contato">
                        <span aria-hidden="true">▾</span>
                    </button>
                @endif
                @if($fidelidadeAtiva)
                    <a class="btn ghost" href="{{ route('delivery.public.fidelity', $slug) }}" title="Cartão fidelidade">
                        <span aria-hidden="true">✦</span><span class="btn-label">Fidelidade</span>
                    </a>
                @endif
                <button class="btn ghost" type="button" data-track-open title="Acompanhar pedido">
                    <span aria-hidden="true">⌕</span><span class="btn-label">Pedido</span>
                </button>
                <button class="btn primary cart-button" type="button" data-cart-open title="Carrinho">
                    <span aria-hidden="true">🛒</span><span class="btn-label">Carrinho</span> <span data-cart-count>0</span>
                </button>
            </div>
        </div>

        @if($temContato)
            <div class="vf-store-info" id="vf-store-info" data-store-info>
                @if($endereco !== '')
                    <div class="vf-info-line"><span class="vf-ico" aria-hidden="true">📍</span><div>{{ $endereco }}</div></div>
                @endif
                @if($whatsDigits !== '')
                    <div class="vf-info-line"><span class="vf-ico" aria-hidden="true">📞</span>
                        <a href="https://wa.me/{{ $whatsDigits }}" target="_blank" rel="noopener">{{ $whatsRaw }}</a>
                    </div>
                @elseif($telRaw !== '')
                    <div class="vf-info-line"><span class="vf-ico" aria-hidden="true">📞</span>
                        <a href="tel:{{ preg_replace('/[^\d+]/', '', $telRaw) }}">{{ $telRaw }}</a>
                    </div>
                @endif
                @if($igUrl !== '')
                    <div class="vf-info-line">
                        <span class="vf-ico vf-ico--ig" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5" stroke="url(#ig)" stroke-width="2"/><circle cx="12" cy="12" r="4" stroke="url(#ig)" stroke-width="2"/><circle cx="17.5" cy="6.5" r="1.2" fill="#E1306C"/><defs><linearGradient id="ig" x1="4" y1="20" x2="20" y2="4"><stop stop-color="#f58529"/><stop offset=".5" stop-color="#dd2a7b"/><stop offset="1" stop-color="#515bd4"/></linearGradient></defs></svg>
                        </span>
                        <a href="{{ $igUrl }}" target="_blank" rel="noopener noreferrer">Instagram</a>
                    </div>
                @endif
                @if($fbUrl !== '')
                    <div class="vf-info-line">
                        <span class="vf-ico vf-ico--fb" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="#1877F2" d="M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.8-4.7 4.54-4.7 1.32 0 2.7.24 2.7.24v2.97h-1.52c-1.5 0-1.97.93-1.97 1.89v2.26h3.35l-.54 3.49h-2.81V24C19.61 23.1 24 18.1 24 12.07z"/></svg>
                        </span>
                        <a href="{{ $fbUrl }}" target="_blank" rel="noopener noreferrer">Facebook</a>
                    </div>
                @endif
            </div>
        @endif
    </div>
</header>

<main class="shell page">
    @include('delivery.public.partials.etapas')
    @yield('content')
</main>

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

<footer class="vf-publico-footer {{ $footerFixed ? 'vf-publico-footer--fixed' : '' }}">
    <div class="shell footer-inner">
        <span class="vf-moto" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none"><path d="M5 17a2.5 2.5 0 1 0 0 .01M19 17a2.5 2.5 0 1 0 0 .01M5 17H3l1.5-5h4L10 17m9 0h-3l-1.2-4H19l1.5 1.5M8.5 12l1.5-4h4l2 4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </span>
        <span>{{ $entregaTexto }}</span>
    </div>
</footer>

<script>window.deliveryStore={slug:@json($slug),checkout:@json(route('delivery.public.checkout',$slug)),csrf:@json(csrf_token())};</script>
<script src="{{ asset('assets/delivery/store.js') }}?v=20260717-vf3"></script>
@stack('scripts')
</body>
</html>
