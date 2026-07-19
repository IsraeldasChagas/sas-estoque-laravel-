@extends('delivery.public.layout')
@section('title', 'Pedido recebido')
@section('content')
@php
    $nomeProg = $programaFidelidade?->nome_exibicao ?? $programaFidelidade?->nome ?? 'Cartão fidelidade';
    $precisaFid = ($fidelidadeSnap['precisa_formulario'] ?? false) && ($programaFidelidade ?? null);
    $seloOk = ! empty($fidelidadeSnap['selo_creditado']);
    $fidelidadePostUrl = $precisaFid
        ? route('delivery.public.success.fidelity', [$slug, $pedido->codigo_publico, $token])
        : null;
@endphp
<section class="success-page">
    <div class="success-icon">✓</div>
    <h1>Pedido recebido!</h1>
    <p>Seu código é <strong>{{ $pedido->codigo_publico }}</strong>.</p>
    <p>Guarde o link abaixo para acompanhar o andamento com segurança.</p>
    @if($seloOk)
        <div class="vf-fid-alert vf-fid-alert--ok" role="status">
            Cartão fidelidade ativado — você ganhou <strong>1 selo</strong> nesta compra.
            @if(! empty($fidelidadeSnap['saldo_selos']))
                Saldo atual: <strong>{{ (int) $fidelidadeSnap['saldo_selos'] }}</strong> / {{ (int) ($fidelidadeSnap['meta_selos'] ?? 10) }} selos.
            @endif
        </div>
    @elseif($precisaFid)
        <div class="vf-fid-alert vf-fid-alert--warn" role="status">
            Você pediu o <strong>{{ $nomeProg }}</strong>. Complete seus dados na janela abaixo para ganhar o selo desta compra.
        </div>
    @endif
    @include('delivery.partials.pix-publico', compact('config', 'pedido', 'pixConfigurada', 'pixQrDataUri', 'pixPayload', 'pixAutomatico', 'pixPollUrl'))
    @include('delivery.partials.cartao-online-publico', compact('pedido', 'cartaoCheckoutUrl', 'cartaoOnlinePendente', 'cartaoOnlinePago', 'cartaoPollUrl'))
    <a class="btn primary" href="{{ route('delivery.public.order', [$slug, $pedido->codigo_publico, $token]) }}">Acompanhar pedido</a>
    <a class="btn ghost" href="{{ route('delivery.public.store', $slug) }}">← Continuar comprando</a>
</section>

@if($precisaFid)
<dialog id="vfModalFidelidadeSucesso" class="vf-dialog vf-dialog--fidelidade" open>
    <div class="vf-dialog__head">
        <h2>{{ $nomeProg }}</h2>
    </div>
    <p class="checkout-freight-note">Informe seus dados para ativar o cartão e receber <strong>1 selo</strong> desta compra.</p>
    <form id="vf-fidelidade-sucesso-form" class="vf-fid-form-stack">
        <label>Nome completo
            <input type="text" name="fidelidade_nome" id="vf-fid-nome" maxlength="160" required autocomplete="name" value="{{ old('fidelidade_nome', $pedido->cliente_nome) }}">
        </label>
        <label>E-mail
            <input type="email" name="fidelidade_email" id="vf-fid-email" maxlength="160" required autocomplete="email" value="{{ old('fidelidade_email', $pedido->cliente_email) }}">
        </label>
        <label>WhatsApp
            <input type="tel" name="fidelidade_whatsapp" id="vf-fid-whatsapp" maxlength="32" required inputmode="tel" value="{{ old('fidelidade_whatsapp', $pedido->cliente_whatsapp ?: $pedido->cliente_telefone) }}">
        </label>
        <label>CPF
            <input type="text" name="fidelidade_cpf" id="vf-fid-cpf" maxlength="14" required inputmode="numeric" placeholder="000.000.000-00">
        </label>
        <div class="vf-fid-lgpd">
            <h3>Termo de consentimento (LGPD)</h3>
            <div class="vf-fid-lgpd__text">
                <p>{{ $lgpdTexto }}</p>
                <p class="muted">
                    <a href="{{ route('delivery.public.fidelity.privacy', $slug) }}" target="_blank" rel="noopener noreferrer">Leia a política de privacidade completa</a>
                </p>
            </div>
            <label class="vf-fid-lgpd__check">
                <input type="checkbox" name="lgpd_autorizo" id="vf-fid-lgpd" value="1" required>
                <span>Autorizo o uso dos meus dados sensíveis exclusivamente para a segurança e consulta do meu cartão fidelidade.</span>
            </label>
        </div>
        <p class="form-error" id="vf-fid-error" role="alert"></p>
        <div class="vf-dialog__foot">
            <button type="button" class="btn ghost" id="vf-fid-pular">Agora não</button>
            <button type="submit" class="btn primary" id="vf-fid-submit">Ativar cartão e ganhar selo</button>
        </div>
    </form>
</dialog>
@endif

@push('scripts')
@if($precisaFid)
<script>
(function(){
  const dlg = document.getElementById('vfModalFidelidadeSucesso');
  const form = document.getElementById('vf-fidelidade-sucesso-form');
  const err = document.getElementById('vf-fid-error');
  const cpf = document.getElementById('vf-fid-cpf');
  const submit = document.getElementById('vf-fid-submit');
  const postUrl = @json($fidelidadePostUrl);

  function maskCpf(v){
    const d = String(v||'').replace(/\D+/g,'').slice(0,11);
    if (d.length <= 3) return d;
    if (d.length <= 6) return d.slice(0,3)+'.'+d.slice(3);
    if (d.length <= 9) return d.slice(0,3)+'.'+d.slice(3,6)+'.'+d.slice(6);
    return d.slice(0,3)+'.'+d.slice(3,6)+'.'+d.slice(6,9)+'-'+d.slice(9);
  }
  cpf?.addEventListener('input', () => { cpf.value = maskCpf(cpf.value); });

  document.getElementById('vf-fid-pular')?.addEventListener('click', () => {
    if (confirm('Sem os dados o selo desta compra não será creditado. Deseja pular agora?')) dlg?.close();
  });

  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    err.textContent = '';
    submit.disabled = true;
    try {
      const body = {
        fidelidade_nome: document.getElementById('vf-fid-nome')?.value?.trim(),
        fidelidade_email: document.getElementById('vf-fid-email')?.value?.trim(),
        fidelidade_whatsapp: document.getElementById('vf-fid-whatsapp')?.value?.trim(),
        fidelidade_cpf: document.getElementById('vf-fid-cpf')?.value?.trim(),
        lgpd_autorizo: document.getElementById('vf-fid-lgpd')?.checked ? 1 : 0,
      };
      const res = await fetch(postUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': window.deliveryStore?.csrf || '',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(body),
      });
      const out = await res.json().catch(() => ({}));
      if (!res.ok) {
        const msg = out.message
          || Object.values(out.errors || {})?.flat?.()?.[0]
          || 'Não foi possível ativar o cartão.';
        throw new Error(msg);
      }
      dlg?.close();
      const ok = document.createElement('div');
      ok.className = 'vf-fid-alert vf-fid-alert--ok';
      ok.setAttribute('role', 'status');
      ok.innerHTML = '<strong>Cartão ativado!</strong> ' + (out.mensagem || 'Selo creditado nesta compra.');
      document.querySelector('.success-page')?.prepend(ok);
    } catch (ex) {
      err.textContent = ex.message || 'Erro ao salvar.';
      submit.disabled = false;
    }
  });
})();
</script>
@endif
@endpush
@endsection
