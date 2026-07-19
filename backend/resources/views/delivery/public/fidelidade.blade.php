@extends('delivery.public.layout')
@section('title', 'Fidelidade · '.($config->nome_loja ?: 'Loja'))
@section('content')
@php
    $meta = (int) ($programa->pedidos_meta ?? 10);
    $nomeProg = $programa->nome_exibicao ?? $programa->nome ?? 'Cartão fidelidade';
    $nomeUnidade = trim((string) ($unidade_fidelidade_nome ?? '')) ?: ($config->nome_loja ?: 'Loja');
@endphp
<section class="vf-fidelity-page">
    <div class="vf-fidelity-hero">
        <span class="vf-fidelity-hero__icon" aria-hidden="true">✦</span>
        <h1>{{ $nomeProg }}</h1>
        <p>{{ $nomeUnidade }}</p>
    </div>

    @if(session('status'))
        <div class="vf-fid-alert vf-fid-alert--ok" role="status">{{ session('status') }}</div>
    @endif
    @if(session('warning'))
        <div class="vf-fid-alert vf-fid-alert--warn" role="status">{{ session('warning') }}</div>
    @endif

    <div class="vf-fidelity-card">
        <h2>Como funciona</h2>
        @php
            $cf = is_array($linhas_como_funciona ?? null) ? $linhas_como_funciona : [];
            $regrasCf = $cf['regras'] ?? [];
            $recTitulo = trim((string) ($cf['recompensa_titulo'] ?? ''));
            $recLinhas = $cf['recompensa_linhas'] ?? [];
            $recTipo = (string) ($cf['tipo'] ?? '');
            $catalogoQtd = max(1, (int) ($cf['catalogo_qtd_escolhas'] ?? 1));
            $catalogoProdutos = is_array($cf['catalogo_produtos'] ?? null) ? $cf['catalogo_produtos'] : [];
            $catalogoComProdutos = $recTipo === 'catalogo_consulta' && $catalogoProdutos !== [];
        @endphp
        <ul class="vf-fid-como-regras">
            @foreach($regrasCf as $linha)
                <li>{{ $linha }}</li>
            @endforeach
        </ul>

        @if($catalogoComProdutos || ! empty($recLinhas))
            <div class="vf-fid-como-recompensa" data-tipo="{{ $recTipo }}">
                <h3 class="vf-fid-como-recompensa__titulo">{{ $recTitulo !== '' ? $recTitulo : 'Recompensa' }}</h3>

                @if($catalogoComProdutos)
                    <div class="vf-fid-catalogo-mini">
                        <p class="vf-fid-catalogo-intro">
                            {{ count($catalogoProdutos) }} opção(ões) na vitrine · no resgate, escolha
                            <strong>{{ $catalogoQtd }}</strong>
                            {{ $catalogoQtd === 1 ? 'item' : 'itens' }}:
                        </p>
                        @if($catalogoQtd > 1)
                            <p class="vf-fid-catalogo-nota muted">Pode repetir o mesmo produto até o limite.</p>
                        @endif
                        <ul class="vf-fid-catalogo-produtos">
                            @foreach($catalogoProdutos as $prod)
                                <li class="vf-fid-catalogo-produto-card">
                                    <div class="vf-fid-catalogo-produto-card__foto" aria-hidden="true">
                                        @if(! empty($prod['foto_url']))
                                            <img src="{{ $prod['foto_url'] }}" alt="" loading="lazy">
                                        @else
                                            <span>▧</span>
                                        @endif
                                    </div>
                                    <div class="vf-fid-catalogo-produto-card__body">
                                        <strong title="{{ $prod['nome'] ?? '' }}">{{ $prod['nome'] ?? '' }}</strong>
                                        @if(isset($prod['preco']))
                                            <small>R$ {{ number_format((float) $prod['preco'], 2, ',', '.') }}</small>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @else
                    <ul>
                        @foreach($recLinhas as $rl)
                            <li>{{ $rl }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif

        <h2 class="vf-fid-subtitle">Ver meus selos</h2>

        @if(!($lgpd_aceito ?? false))
            <div class="vf-fid-lgpd">
                <h3>Termo de consentimento (LGPD)</h3>
                <div class="vf-fid-lgpd__text">
                    <p>{{ $lgpd_texto ?? '' }}</p>
                    <p class="muted">
                        <a href="{{ route('delivery.public.fidelity.privacy', $slug) }}" target="_blank" rel="noopener noreferrer">Leia a política de privacidade completa</a>
                    </p>
                </div>
                <form method="post" action="{{ route('delivery.public.fidelity.lgpd', $slug) }}" class="vf-fid-form">
                    @csrf
                    <label class="vf-fid-lgpd__check">
                        <input type="checkbox" name="lgpd_autorizo" value="1" {{ old('lgpd_autorizo') ? 'checked' : '' }} required>
                        <span>Autorizo o uso dos meus dados sensíveis exclusivamente para a segurança e consulta do meu cartão fidelidade.</span>
                    </label>
                    @error('lgpd_autorizo')<p class="vf-fid-error">{{ $message }}</p>@enderror
                    <button type="submit" class="btn primary">Autorizo e continuar</button>
                </form>
            </div>
        @else
        @if($fidelidade_otp_pending ?? false)
            @php
                $pend = session('sas_fid_otp_pending', []);
                $telPend = is_array($pend) ? (string) ($pend['tel_norm'] ?? '') : '';
                $suf = strlen($telPend) >= 4 ? substr($telPend, -4) : '****';
                $canalOtp = is_array($pend) ? (string) ($pend['canal'] ?? '') : '';
                $otpPorEmail = $canalOtp === \App\Services\Fidelidade\FidelidadePublicOtpEntrega::CANAL_EMAIL;
                $otpPorWame = $canalOtp === \App\Services\Fidelidade\FidelidadePublicOtpEntrega::CANAL_WAME;
                $waMeUrl = is_array($pend) ? ($pend['wa_me_url'] ?? null) : null;
                $waMeUrl = is_string($waMeUrl) && str_starts_with($waMeUrl, 'https://wa.me/') ? $waMeUrl : null;
            @endphp

            @if($otpPorEmail)
                <p class="muted">Enviamos o código de <strong>6 dígitos</strong> por <strong>e-mail</strong> (telefone ***{{ $suf }}). Confira spam/lixeira.</p>
            @elseif($otpPorWame && $waMeUrl)
                <div class="vf-fid-alert vf-fid-alert--warn">
                    <strong>Importante:</strong> este modo abre o WhatsApp com o texto pronto — o código aparece na mensagem. No celular, abra esta página no próprio aparelho.
                </div>
                <p class="muted">Telefone com final <strong>***{{ $suf }}</strong>.</p>
                <a class="btn primary vf-fid-wa" href="{{ $waMeUrl }}" target="_blank" rel="noopener noreferrer">Abrir WhatsApp com o código</a>
            @else
                <p class="muted">Digite o código enviado ao WhatsApp do número ***{{ $suf }}.</p>
            @endif

            <form method="post" action="{{ route('delivery.public.fidelity.verify', $slug) }}" class="vf-fid-form">
                @csrf
                <label>Código de 6 dígitos
                    <input type="text" name="codigo" value="{{ old('codigo') }}" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" placeholder="000000" required>
                </label>
                @error('codigo')<p class="vf-fid-error">{{ $message }}</p>@enderror
                <button type="submit" class="btn primary">Confirmar e ver selos</button>
            </form>
            <form method="post" action="{{ route('delivery.public.fidelity.resend', $slug) }}" class="vf-fid-form-inline">
                @csrf
                <button type="submit" class="btn ghost">{{ $otpPorEmail ? 'Reenviar por e-mail' : 'Gerar novo código / WhatsApp' }}</button>
            </form>
            <form method="post" action="{{ route('delivery.public.fidelity.cancel', $slug) }}" class="vf-fid-form-inline">
                @csrf
                <button type="submit" class="btn link">Usar outro telefone</button>
            </form>
        @else
            <p class="muted">Digite o celular usado na <strong>reserva de mesa</strong> e solicite o código. Sem o código, o saldo não aparece — medida de segurança.</p>
            <form method="post" action="{{ route('delivery.public.fidelity.request', $slug) }}" class="vf-fid-form">
                @csrf
                <label>Seu celular
                    <input type="tel" name="telefone" value="{{ old('telefone') }}" placeholder="(11) 98888-7777" autocomplete="tel" required maxlength="32">
                </label>
                @error('telefone')<p class="vf-fid-error">{{ $message }}</p>@enderror
                <button type="submit" class="btn primary">Solicitar código</button>
            </form>
        @endif
        @endif
    </div>

    @if(($lgpd_aceito ?? false) && ($mostrar_progresso_selos ?? false))
        @if($conta)
            @php
                $selos = (int) ($conta->saldo_selos ?? 0);
                $pontos = (int) ($conta->saldo_pontos ?? 0);
                $modoProg = (string) ($programa->modo ?? 'selos');
                $filled = $modoProg === 'pontos' ? min($pontos, $meta) : min($selos, $meta);
                $cheio = $modoProg === 'pontos'
                    ? ($pontos >= $meta && $meta > 0)
                    : ($selos >= $meta && $meta > 0);
                $nomeCliente = trim((string) ($conta->nome ?? ''));
                $telSuf = strlen((string) ($conta->telefone_normalizado ?? '')) >= 4
                    ? substr((string) $conta->telefone_normalizado, -4)
                    : null;
            @endphp
            <div class="vf-fidelity-card">
                <h2>Seu progresso</h2>
                @if($nomeCliente !== '' || $telSuf)
                    <p class="muted vf-fid-mask">
                        @if($nomeCliente !== '')
                            Olá, <strong>{{ $nomeCliente }}</strong>
                            @if($telSuf) · telefone ***{{ $telSuf }} @endif
                        @elseif($telSuf)
                            Telefone ***{{ $telSuf }}
                        @endif
                    </p>
                @endif
                <div class="vf-fid-stamps" aria-label="Progresso de selos">
                    @for($i = 1; $i <= $meta; $i++)
                        <span class="vf-fid-stamp {{ $i <= $filled ? 'is-on' : '' }}">{{ $i <= $filled ? '✓' : $i }}</span>
                    @endfor
                </div>
                <p class="vf-fid-saldo">
                    <strong>{{ $selos }}</strong> selo(s) · meta <strong>{{ $meta }}</strong>
                    @if($pontos > 0) · <strong>{{ $pontos }}</strong> ponto(s) @endif
                </p>
                @if($nomeUnidade !== '')
                    <p class="muted">Unidade: <strong>{{ $nomeUnidade }}</strong></p>
                @endif
                @php
                    $resgateCat = is_array($resgate_catalogo ?? null) ? $resgate_catalogo : [];
                    $podeResgatarCat = ! empty($resgateCat['ativo']);
                    $resgateQtd = max(1, (int) ($resgateCat['qtd'] ?? 1));
                    $resgateProdutos = is_array($resgateCat['produtos'] ?? null) ? $resgateCat['produtos'] : [];
                @endphp
                @if($cheio && $podeResgatarCat)
                    <div class="vf-fid-resgate-catalogo">
                        <h3 class="vf-fid-resgate-catalogo__titulo">Resgatar recompensa</h3>
                        <p class="muted">Escolha {{ $resgateQtd === 1 ? '1 opção' : ($resgateQtd.' itens') }} abaixo e confirme. A loja preparará seu pedido.</p>
                        @error('catalogo_produto_id')<p class="vf-fid-error">{{ $message }}</p>@enderror
                        @error('catalogo_escolhas')<p class="vf-fid-error">{{ $message }}</p>@enderror
                        @error('catalogo_qtd')<p class="vf-fid-error">{{ $message }}</p>@enderror
                        <form method="post" action="{{ route('delivery.public.fidelity.redeem', $slug) }}" class="vf-fid-form">
                            @csrf
                            <ul class="vf-fid-resgate-opcoes">
                                @foreach($resgateProdutos as $prod)
                                    <li class="vf-fid-resgate-opcao">
                                        @if($resgateQtd === 1)
                                            <label class="vf-fid-resgate-opcao__pick">
                                                <input type="radio" name="catalogo_produto_id" value="{{ (int) ($prod['id'] ?? 0) }}" required>
                                                <span class="vf-fid-resgate-opcao__card">
                                                    <span class="vf-fid-resgate-opcao__foto">
                                                        @if(! empty($prod['foto_url']))
                                                            <img src="{{ $prod['foto_url'] }}" alt="" loading="lazy">
                                                        @else
                                                            <span>▧</span>
                                                        @endif
                                                    </span>
                                                    <span class="vf-fid-resgate-opcao__info">
                                                        <strong>{{ $prod['nome'] ?? '' }}</strong>
                                                        @if(isset($prod['preco']))
                                                            <small>R$ {{ number_format((float) $prod['preco'], 2, ',', '.') }}</small>
                                                        @endif
                                                    </span>
                                                </span>
                                            </label>
                                        @else
                                            <div class="vf-fid-resgate-opcao__card vf-fid-resgate-opcao__card--qty">
                                                <span class="vf-fid-resgate-opcao__foto">
                                                    @if(! empty($prod['foto_url']))
                                                        <img src="{{ $prod['foto_url'] }}" alt="" loading="lazy">
                                                    @else
                                                        <span>▧</span>
                                                    @endif
                                                </span>
                                                <span class="vf-fid-resgate-opcao__info">
                                                    <strong>{{ $prod['nome'] ?? '' }}</strong>
                                                    @if(isset($prod['preco']))
                                                        <small>R$ {{ number_format((float) $prod['preco'], 2, ',', '.') }}</small>
                                                    @endif
                                                </span>
                                                <label class="vf-fid-resgate-qty">
                                                    Qtd
                                                    <input type="number" name="catalogo_qtd[{{ (int) ($prod['id'] ?? 0) }}]" min="0" max="{{ $resgateQtd }}" value="0" inputmode="numeric">
                                                </label>
                                            </div>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                            @if($resgateQtd > 1)
                                <p class="vf-fid-catalogo-nota muted">Total: escolha exatamente {{ $resgateQtd }} item(ns). Pode repetir o mesmo produto.</p>
                            @endif
                            <button type="submit" class="btn primary">Confirmar resgate</button>
                        </form>
                    </div>
                @elseif($cheio)
                    <div class="vf-fid-alert vf-fid-alert--ok">Você completou a meta! Na próxima visita, peça à loja para usar a recompensa.</div>
                @endif
                <form method="post" action="{{ route('delivery.public.fidelity.logout', $slug) }}" class="vf-fid-form-inline">
                    @csrf
                    <button type="submit" class="btn link">Sair / consultar outro telefone</button>
                </form>
            </div>
        @else
            <div class="vf-fid-alert vf-fid-alert--warn">Ainda não há selos neste telefone. Após a loja confirmar o pagamento da sua reserva de mesa, o selo aparece aqui.</div>
        @endif
    @endif

    <p class="vf-fid-back">
        <a class="btn ghost" href="{{ route('delivery.public.store', $slug) }}">← Continuar comprando</a>
    </p>
</section>
@endsection
