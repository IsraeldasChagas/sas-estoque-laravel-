<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="theme-color" content="#16a34a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Motoboy">
    <link rel="manifest" href="{{ $manifestUrl }}">
    <link rel="apple-touch-icon" href="{{ asset('assets/delivery/motoboy-icon-192.png') }}">
    <title>Motoboy · {{ $config->nome_loja ?? 'Delivery' }}</title>
    <link rel="stylesheet" href="{{ asset('assets/delivery/motoboy-app.css') }}?v=20260720-m1">
</head>
<body>
<header class="mb-head">
    <div>
        <p class="mb-kicker">{{ $config->nome_loja ?? 'Loja' }}</p>
        <h1>Olá, {{ $entregador->nome }}</h1>
    </div>
    <button type="button" class="mb-btn mb-btn--ghost" id="mbInstall" hidden>Instalar app</button>
</header>

<main class="mb-main">
    <div id="mbEmpty" class="mb-empty">
        <div class="mb-empty__icon">🛵</div>
        <h2>Nenhuma entrega no momento</h2>
        <p>Quando a loja oferecer um pedido, ele aparece aqui para você aceitar.</p>
    </div>
    <div id="mbList" class="mb-list" hidden></div>
    <div id="mbToast" class="mb-toast" hidden></div>
</main>

<dialog id="mbDialog" class="mb-dialog">
    <form method="dialog" id="mbDialogForm">
        <h3 id="mbDialogTitle">Entrega aceita</h3>
        <p id="mbDialogText" class="mb-muted"></p>
        <pre id="mbDialogCupom" class="mb-cupom" hidden></pre>
        <div class="mb-dialog-actions">
            <a id="mbDialogOpen" class="mb-btn mb-btn--primary" href="#" target="_blank" rel="noopener">Abrir pedido</a>
            <button type="button" class="mb-btn mb-btn--ghost" id="mbDialogCopy">Copiar cupom</button>
            <button value="close" class="mb-btn">Fechar</button>
        </div>
    </form>
</dialog>

<script>
  window.MOTOBOY_APP = {
    ofertasUrl: @json($ofertasUrl),
    aceitarUrlTpl: @json(route('delivery.public.motoboy.aceitar', ['slug' => $slug, 'acessoToken' => $acessoToken, 'pedidoId' => 999999])),
    recusarUrlTpl: @json(route('delivery.public.motoboy.recusar', ['slug' => $slug, 'acessoToken' => $acessoToken, 'pedidoId' => 999999])),
    csrf: @json(csrf_token()),
  };
</script>
<script src="{{ asset('assets/delivery/motoboy-app.js') }}?v=20260720-m1"></script>
<script>
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register(@json(asset('assets/delivery/motoboy-sw.js')) + '?v=20260720-m1').catch(function () {});
  }
</script>
</body>
</html>
