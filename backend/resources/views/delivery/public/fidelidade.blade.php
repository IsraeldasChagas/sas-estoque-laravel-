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
        <ul>
            <li>A cada <strong>reserva de mesa</strong> no Sabor Paraense, na unidade <strong>{{ $nomeUnidade }}</strong> em que você reservou, você ganha selos após o <strong>pagamento da conta</strong>.</li>
            <li>O cartão é criado automaticamente pela loja na reserva — não é possível cadastrar por aqui.</li>
            <li>Meta: <strong>{{ $meta }}</strong> selos para a recompensa.</li>
            @if(! empty($programa->texto_recompensa))
                <li>{{ $programa->texto_recompensa }}</li>
            @endif
            <li>Use o <strong>mesmo telefone da reserva</strong> e confirme com um código de 6 dígitos para ver seu saldo.</li>
        </ul>

        <h2 class="vf-fid-subtitle">Ver meus selos</h2>

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
    </div>

    @if($mostrar_progresso_selos ?? false)
        @if($conta)
            @php
                $selos = (int) ($conta->saldo_selos ?? 0);
                $pontos = (int) ($conta->saldo_pontos ?? 0);
                $filled = min($selos, $meta);
                $cheio = $selos >= $meta && $meta > 0;
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
                @if($cheio)
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
