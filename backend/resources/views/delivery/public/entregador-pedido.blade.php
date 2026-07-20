@php
    use App\Support\Delivery\DeliveryPedidoPresenter;
    $nomeLoja = trim((string) ($config->nome_loja ?? 'Loja'));
    $enderecoLinha = DeliveryPedidoPresenter::enderecoLinha($pedido);
    $cep = preg_replace('/\D+/', '', (string) ($pedido->endereco_cep ?? ''));
    $podeRegistrar = DeliveryPedidoPresenter::entregadorPodeRegistrarResultado($pedido->status ?? null);
    $statusRotulo = DeliveryPedidoPresenter::rotuloStatus($pedido->status ?? null);
    $createdAt = \Illuminate\Support\Carbon::parse($pedido->created_at ?? now());
    $codigoEsperadoJs = (string) $pedido->codigo_publico;
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Entrega · {{ $nomeLoja }}</title>
    <style>
        :root { font-family: "Segoe UI", system-ui, sans-serif; color: #1f2937; }
        body { margin: 0; background: #f3f4f6; }
        .wrap { max-width: 520px; margin: 0 auto; padding: 16px; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; margin-bottom: 12px; }
        .card.highlight { border: 2px solid #93c5fd; }
        .muted { color: #6b7280; font-size: 13px; }
        .code { font-family: ui-monospace, monospace; font-size: 28px; font-weight: 700; color: #2563eb; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 12px; background: #dbeafe; color: #1d4ed8; }
        .item { padding: 10px 0; border-bottom: 1px solid #f3f4f6; }
        .item:last-child { border-bottom: 0; }
        .row { display: flex; justify-content: space-between; gap: 12px; }
        .btn { display: block; width: 100%; border: 0; border-radius: 10px; padding: 14px 16px; font-size: 16px; font-weight: 600; cursor: pointer; text-align: center; text-decoration: none; }
        .btn-success { background: #16a34a; color: #fff; }
        .btn-warning { background: #f59e0b; color: #111; }
        .btn-danger { background: #fff; color: #dc2626; border: 1px solid #fecaca; }
        .alert { padding: 12px; border-radius: 10px; font-size: 14px; margin-bottom: 12px; }
        .alert-ok { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-warn { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
        .opcoes { margin-top: 6px; padding-left: 10px; border-left: 2px solid #e5e7eb; font-size: 12px; color: #6b7280; }
        .total { font-weight: 700; font-size: 18px; color: #059669; }
        form { display: grid; gap: 8px; }
        .field { display: grid; gap: 6px; margin-bottom: 4px; }
        .field label { font-size: 13px; font-weight: 600; color: #374151; }
        .field input {
            width: 100%; box-sizing: border-box; padding: 12px 14px;
            border: 1px solid #d1d5db; border-radius: 10px; font: inherit;
            font-family: ui-monospace, monospace; font-size: 20px; letter-spacing: .08em;
            text-transform: uppercase;
        }
        .field input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.15); }
        .field-hint { margin: 0; font-size: 12px; color: #6b7280; }
        .frete-destaque {
            margin-top: 12px; padding: 10px 12px; border-radius: 10px;
            background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46;
            display: flex; justify-content: space-between; align-items: center; gap: 8px;
            font-size: 14px; font-weight: 600;
        }
        .frete-destaque strong { font-size: 18px; }
        .field-error { color: #dc2626; font-size: 13px; margin: 0; }
        .btn:disabled { opacity: .45; cursor: not-allowed; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="muted">{{ $nomeLoja }}</div>
    <h1 style="font-size:20px;margin:4px 0 16px">Entrega</h1>

    @if (session('status'))
        <div class="alert alert-ok">{{ session('status') }}</div>
    @endif
    @if (session('warning'))
        <div class="alert alert-warn">{{ session('warning') }}</div>
    @endif

    <div class="card highlight">
        <div class="muted">Código desta entrega</div>
        <div class="code">{{ $pedido->codigo_publico }}</div>
        <p class="muted" style="margin:8px 0 0">
            Pedido em {{ $createdAt->format('d/m/Y H:i') }}
            · {{ DeliveryPedidoPresenter::rotuloFulfillment($pedido->fulfillment ?? null) }}
        </p>
        <div class="frete-destaque">
            Valor da entrega
            <strong>R$ {{ number_format((float) ($pedido->frete_valor ?? 0), 2, ',', '.') }}</strong>
        </div>
    </div>

    <div class="card">
        <strong>Cliente</strong>
        <p style="margin:8px 0 4px">{{ $pedido->cliente_nome }}</p>
        @if ($pedido->cliente_telefone)
            <a href="tel:{{ preg_replace('/\D+/', '', (string) $pedido->cliente_telefone) }}">{{ $pedido->cliente_telefone }}</a>
        @endif
    </div>

    <div class="card">
        <strong>Endereço</strong>
        @if ($enderecoLinha !== '')
            <p style="margin:8px 0 0">{{ $enderecoLinha }}@if ($pedido->endereco_complemento), {{ $pedido->endereco_complemento }}@endif</p>
        @else
            <p class="muted" style="margin:8px 0 0">Endereço não registrado. Confira o CEP com o cliente.</p>
        @endif
        @if (strlen($cep) === 8)
            <p class="muted" style="margin:6px 0 0">CEP {{ substr($cep, 0, 5) }}-{{ substr($cep, 5) }}</p>
        @endif
    </div>

    <div class="card">
        <strong>Itens e valores</strong>
        @foreach ($itens as $it)
            @php
                $opcoes = $it->opcoes_json;
                if (is_string($opcoes)) { $opcoes = json_decode($opcoes, true); }
                $opcoesLinha = DeliveryPedidoPresenter::opcoesLinhaParaExibicao(is_array($opcoes) ? $opcoes : []);
            @endphp
            <div class="item">
                <div class="row">
                    <span><strong>{{ $it->nome_produto }}</strong> × {{ rtrim(rtrim(number_format((float) $it->quantidade, 3, '.', ''), '0'), '.') }}</span>
                    <span>R$ {{ number_format((float) $it->subtotal, 2, ',', '.') }}</span>
                </div>
                @include('delivery.partials.opcoes-pedido-item', ['opcoesLinha' => $opcoesLinha])
            </div>
        @endforeach
        <div class="row muted" style="margin-top:10px;padding-top:10px;border-top:1px solid #e5e7eb"><span>Subtotal</span><span>R$ {{ number_format((float) $pedido->subtotal, 2, ',', '.') }}</span></div>
        <div class="row muted"><span>Taxa de entrega</span><span>R$ {{ number_format((float) $pedido->frete_valor, 2, ',', '.') }}</span></div>
        <div class="row total" style="margin-top:8px;padding-top:8px;border-top:1px solid #e5e7eb"><span>Total</span><span>R$ {{ number_format((float) $pedido->total, 2, ',', '.') }}</span></div>
    </div>

    <div class="card">
        <strong>Pagamento</strong>
        <p style="margin:8px 0 0">{{ DeliveryPedidoPresenter::descricaoPagamento($pedido) }}</p>
        @if ($pedido->observacoes)
            <p class="muted" style="margin:8px 0 0"><strong>Obs.:</strong> {{ $pedido->observacoes }}</p>
        @endif
    </div>

    <div style="margin-bottom:12px"><span class="badge">{{ $statusRotulo }}</span></div>

    @if ($podeRegistrar)
        <form id="form-entregador" action="{{ route('delivery.public.entregador.registrar', ['slug' => $slug, 'codigo' => $pedido->codigo_publico, 'token' => $token]) }}" method="post">
            @csrf
            <div class="field">
                <label for="codigo_confirmado">Peça o código ao cliente e confirme aqui</label>
                <input
                    type="text"
                    id="codigo_confirmado"
                    name="codigo_confirmado"
                    inputmode="text"
                    maxlength="{{ max(8, strlen((string) $pedido->codigo_publico)) }}"
                    autocomplete="off"
                    autocapitalize="characters"
                    spellcheck="false"
                    placeholder="{{ $pedido->codigo_publico }}"
                    value="{{ old('codigo_confirmado') }}"
                    required
                >
                <p class="field-hint">Digite só letras e números — os traços entram sozinhos.</p>
                @error('codigo_confirmado')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit" name="resultado" value="entregue" class="btn btn-success" disabled>Entregue</button>
            <button type="submit" name="resultado" value="endereco" class="btn btn-warning" disabled>Não encontrei o endereço</button>
            <button type="submit" name="resultado" value="cancelado" class="btn btn-danger" disabled>Cancelado</button>
        </form>
        <p class="muted" style="margin-top:12px">Digite o código informado pelo cliente para liberar as opções. A loja verá o novo status no painel.</p>
        <script>
        (function () {
            const esperado = @json($codigoEsperadoJs);
            const input = document.getElementById('codigo_confirmado');
            const botoes = document.querySelectorAll('#form-entregador button[type="submit"]');

            function soAlnum(v) {
                return String(v || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
            }

            function mascaraCodigo(template, digitado) {
                const limpo = soAlnum(digitado);
                let i = 0;
                let out = '';
                for (let t = 0; t < template.length; t++) {
                    const ch = template[t];
                    if (ch === '-') {
                        if (i > 0 && i <= limpo.length) out += '-';
                        continue;
                    }
                    if (i >= limpo.length) break;
                    out += limpo[i++];
                }
                return out;
            }

            function normalizar(v) {
                return String(v || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
            }

            function atualizar() {
                if (!input) return;
                const formatado = mascaraCodigo(esperado, input.value);
                if (input.value !== formatado) {
                    const end = formatado.length;
                    input.value = formatado;
                    try { input.setSelectionRange(end, end); } catch (_) {}
                }
                const ok = normalizar(input.value) === normalizar(esperado);
                botoes.forEach((btn) => { btn.disabled = !ok; });
            }

            input?.addEventListener('input', atualizar);
            input?.addEventListener('keydown', (ev) => {
                // Bloqueia caracteres que não são letra/número (exceto controle/backspace)
                if (ev.ctrlKey || ev.metaKey || ev.altKey) return;
                const k = ev.key;
                if (k.length === 1 && !/[A-Za-z0-9]/.test(k)) {
                    ev.preventDefault();
                }
            });
            atualizar();
        })();
        </script>
    @else
        <div class="card muted">Resultado já registrado ou pedido não está pronto/em rota.</div>
    @endif
</div>
</body>
</html>
