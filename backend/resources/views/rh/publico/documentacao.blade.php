<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8" />
    <meta name="robots" content="noindex, nofollow" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta name="theme-color" content="#ff7a00" />
    <title>Documentos — Grupo Sabor Paraense</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
        html {
            -webkit-text-size-adjust: 100%;
        }
        body {
            background: radial-gradient(900px 380px at 18% 0%, rgba(255,140,0,.55), transparent 60%),
                linear-gradient(180deg, #ff7a00 0%, #2b1608 35%, #0b0b0d 100%);
            color: rgba(255,255,255,.92);
            min-height: 100vh;
            min-height: 100dvh;
        }
        /* Área segura (celular com notch) */
        .gsp-page-wrap {
            padding-left: max(0.75rem, env(safe-area-inset-left));
            padding-right: max(0.75rem, env(safe-area-inset-right));
            padding-bottom: max(1.25rem, env(safe-area-inset-bottom));
        }
        .gsp-brand { display:flex; align-items:center; gap:.75rem; flex-wrap:wrap; margin-bottom: 1rem; }
        .gsp-mark { width: 72px; height: 72px; object-fit: contain; flex-shrink: 0; }
        .card-doc { border: 1px solid rgba(0,0,0,.08); border-radius: 12px; overflow: hidden; }
        /* Formulário: pergunta em cima, campo/resposta em baixo (sempre em coluna no mobile) */
        .gsp-qablock {
            margin-bottom: 1rem;
        }
        .gsp-qablock:last-child { margin-bottom: 0; }
        .gsp-pergunta {
            display: block;
            font-weight: 600;
            font-size: 0.9375rem;
            color: #212529;
            margin-bottom: 0.4rem;
            line-height: 1.35;
        }
        .gsp-resposta,
        .gsp-resposta-wrap {
            font-size: 0.9375rem;
            color: #495057;
            line-height: 1.45;
        }
        .gsp-resposta-wrap .form-control,
        .gsp-resposta-wrap .form-select {
            min-height: 2.75rem;
            font-size: 1rem; /* evita zoom automático no iOS */
        }
        /* Radio: pergunta em cima, opções empilhadas em baixo */
        .gsp-radio-grupo .gsp-pergunta { margin-bottom: 0.5rem; }
        .gsp-radio-opcoes {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .gsp-radio-opcoes .form-check {
            padding: 0.5rem 0.65rem 0.5rem 2rem;
            margin: 0;
            border: 1px solid rgba(0,0,0,.1);
            border-radius: 10px;
            background: #fafafa;
        }
        .gsp-radio-opcoes .form-check-input {
            margin-top: 0.35rem;
            width: 1.15rem;
            height: 1.15rem;
        }
        /* Checklist: documento = pergunta, status = resposta em baixo */
        .checklist-stack {
            display: flex;
            flex-direction: column;
            gap: 0.65rem;
        }
        .checklist-item {
            background: #fff;
            border: 1px solid rgba(0,0,0,.1);
            border-radius: 12px;
            padding: 0.85rem 1rem;
        }
        .checklist-item .checklist-pergunta {
            font-weight: 600;
            font-size: 0.95rem;
            color: #212529;
            margin: 0 0 0.4rem 0;
            line-height: 1.35;
        }
        .checklist-item .checklist-resposta {
            font-size: 0.9rem;
            margin: 0;
            padding-top: 0.35rem;
            border-top: 1px solid rgba(0,0,0,.08);
        }
        .checklist-item .checklist-resposta.ok {
            color: #146c43;
            font-weight: 600;
        }
        .checklist-item .checklist-resposta.pend {
            color: #6c757d;
        }
        .btn-doc-submit {
            min-height: 2.75rem;
            font-size: 1rem;
            padding: 0.5rem 1.25rem;
            width: 100%;
        }
        @media (min-width: 768px) {
            .btn-doc-submit {
                width: auto;
            }
        }
        @media (max-width: 767.98px) {
            .gsp-mark { width: 56px; height: 56px; }
            main.container {
                max-width: 100% !important;
                padding-left: 0.75rem;
                padding-right: 0.75rem;
            }
            .card-doc .card-body { padding: 1rem !important; }
        }
        @media (min-width: 768px) and (max-width: 991.98px) {
            main.container { max-width: 640px !important; }
        }
    </style>
</head>
<body>
@php
    $rotulosLista = isset($rotulos) && is_array($rotulos) ? $rotulos : \App\Support\Rh\RhTiposDocumento::rotulos();
    $tiposOrdem = array_keys($rotulosLista);
    $tiposOk = isset($tiposOk) && is_array($tiposOk) ? $tiposOk : [];
@endphp
<main class="container py-4 gsp-page-wrap" style="max-width: 760px;">
    <div class="gsp-brand">
        <img class="gsp-mark" src="/imagens/logosemfundo.png" alt="Grupo Sabor Paraense" />
        <div>
            <div class="fw-bold fs-5">Grupo Sabor Paraense</div>
            <div class="small" style="color: rgba(255,255,255,.75);">Documentos para contratação</div>
        </div>
    </div>

    @if(!empty($invalido) && $invalido)
        <div class="card card-doc shadow-sm">
            <div class="card-body text-dark">
                <h1 class="h5">Link indisponível</h1>
                <p class="mb-0">Este link não é válido, já foi substituído ou o processo não está mais nesta etapa. Em dúvida, fale com o RH que enviou o link.</p>
            </div>
        </div>
    @else
        @if(request()->query('ok'))
            <div class="alert alert-success">Arquivo recebido. Obrigado!</div>
        @endif
        @if(request()->query('dados_ok'))
            <div class="alert alert-success">Informações salvas. Obrigado!</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card card-doc shadow-sm mb-3">
            <div class="card-body text-dark">
                <h1 class="h5 mb-2">Olá, {{ $nome }}</h1>
                <p class="mb-0 small text-muted">Envie cada item conforme indicado: na maioria dos casos em <strong>PDF</strong> (máx. 6 MB). A <strong>foto 3×4</strong> pode ser <strong>JPG ou PNG</strong>. Você pode voltar a esta página para complementar ou atualizar até o RH concluir a contratação.</p>
            </div>
        </div>

        @if(!empty($dados_complementares_disponivel))
            <div class="card card-doc shadow-sm mb-3">
                <div class="card-body text-dark">
                    <h2 class="h6 mb-3">Dados complementares</h2>
                    <form method="POST" action="/documentacao/{{ $token }}" class="row g-3">
                        @csrf
                        <input type="hidden" name="salvar_dados" value="1" />
                        <div class="col-12 gsp-qablock">
                            <label class="gsp-pergunta" for="grau_instrucao_escolar">Grau de instrução escolar</label>
                            <div class="gsp-resposta-wrap">
                                <input type="text" class="form-control" id="grau_instrucao_escolar" name="grau_instrucao_escolar"
                                       maxlength="200" placeholder="Ex.: Ensino médio completo"
                                       value="{{ $grau_instrucao_escolar ?? '' }}" autocomplete="off" />
                                <div class="form-text text-muted small mt-1">Sua resposta (texto livre).</div>
                            </div>
                        </div>
                        @php
                            $opcoesCor = isset($cor_raca_opcoes) && is_array($cor_raca_opcoes) ? $cor_raca_opcoes : \App\Support\Rh\RhCorRacaIbge::opcoes();
                            $etniaAtual = old('etnia_racial', $etnia_racial ?? '');
                        @endphp
                        <div class="col-12 gsp-qablock gsp-radio-grupo">
                            <span class="gsp-pergunta">Cor ou raça (autodeclaração)</span>
                            <div class="gsp-resposta-wrap">
                                <div class="gsp-radio-opcoes">
                                    @foreach ($opcoesCor as $opcao)
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="etnia_racial" id="cor-raca-{{ $loop->index }}"
                                                   value="{{ $opcao }}" @checked($etniaAtual === $opcao) />
                                            <label class="form-check-label" for="cor-raca-{{ $loop->index }}">{{ $opcao }}</label>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="form-text text-muted small mt-2">Marque uma opção abaixo.</div>
                            </div>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-outline-primary btn-doc-submit">Salvar informações</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <div class="card card-doc shadow-sm mb-3">
            <div class="card-body text-dark">
                <div class="fw-semibold mb-3">Checklist de envios</div>
                <div class="checklist-stack" role="list">
                    @foreach ($tiposOrdem as $t)
                        @php $rot = $rotulosLista[$t] ?? $t; @endphp
                        <div class="checklist-item" role="listitem">
                            <p class="checklist-pergunta">{{ $rot }}</p>
                            @if(in_array($t, $tiposOk, true))
                                <p class="checklist-resposta ok mb-0">Resposta: arquivo já enviado ✓</p>
                            @else
                                <p class="checklist-resposta pend mb-0">Resposta: pendente — envie usando o formulário abaixo.</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card card-doc shadow-sm">
            <div class="card-body text-dark">
                <h2 class="h6 mb-3">Enviar ou atualizar arquivo</h2>
                <form method="POST" action="/documentacao/{{ $token }}" enctype="multipart/form-data" class="row g-3" id="form-doc-upload">
                    @csrf
                    <div class="col-12 gsp-qablock">
                        <label class="gsp-pergunta" for="tipo-documento">Qual documento você está enviando?</label>
                        <div class="gsp-resposta-wrap">
                            <select name="tipo" id="tipo-documento" class="form-select" required>
                                <option value="" disabled @selected(!old('tipo'))>Selecione…</option>
                                @foreach ($tiposOrdem as $t)
                                    <option value="{{ $t }}" @selected(old('tipo') === $t)>{{ $rotulosLista[$t] ?? $t }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-12 gsp-qablock">
                        <label class="gsp-pergunta" for="input-arquivo">Arquivo (sua resposta)</label>
                        <div class="gsp-resposta-wrap">
                            <input type="file" name="arquivo" id="input-arquivo" class="form-control" required />
                            <div class="form-text" id="hint-arquivo">Escolha o tipo acima: PDF para documentos; JPG ou PNG apenas para foto 3×4.</div>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-doc-submit">Enviar</button>
                    </div>
                </form>
            </div>
        </div>
        <script>
(function () {
    var sel = document.getElementById('tipo-documento');
    var input = document.getElementById('input-arquivo');
    var hint = document.getElementById('hint-arquivo');
    function sync() {
        var v = sel && sel.value;
        if (v === 'foto_3x4') {
            input.accept = 'image/jpeg,image/png,.jpg,.jpeg,.png';
            if (hint) hint.textContent = 'Para foto 3×4 envie JPG ou PNG (máx. 6 MB).';
        } else {
            input.accept = 'application/pdf,.pdf';
            if (hint) hint.textContent = 'Para este documento envie PDF (máx. 6 MB).';
        }
    }
    if (sel) {
        sel.addEventListener('change', sync);
        sync();
    }
})();
        </script>
    @endif
</main>
</body>
</html>
