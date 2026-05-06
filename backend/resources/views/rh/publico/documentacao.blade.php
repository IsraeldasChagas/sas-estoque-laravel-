<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8" />
    <meta name="robots" content="noindex, nofollow" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Documentos — Grupo Sabor Paraense</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
        body {
            background: radial-gradient(900px 380px at 18% 0%, rgba(255,140,0,.55), transparent 60%),
                linear-gradient(180deg, #ff7a00 0%, #2b1608 35%, #0b0b0d 100%);
            color: rgba(255,255,255,.92);
            min-height: 100vh;
        }
        .gsp-brand { display:flex; align-items:center; gap:.75rem; flex-wrap:wrap; margin-bottom: 1rem; }
        .gsp-mark { width: 72px; height: 72px; object-fit: contain; }
        .card-doc { border: 1px solid rgba(0,0,0,.08); border-radius: 12px; }
        .badge-tipo { font-weight: 600; white-space: normal; text-align: left; line-height: 1.25; }
        .checklist-row { font-size: .88rem; }
    </style>
</head>
<body>
@php
    $rotulosLista = isset($rotulos) && is_array($rotulos) ? $rotulos : \App\Support\Rh\RhTiposDocumento::rotulos();
    $tiposOrdem = array_keys($rotulosLista);
    $tiposOk = isset($tiposOk) && is_array($tiposOk) ? $tiposOk : [];
@endphp
<main class="container py-4" style="max-width: 760px;">
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
                        <div class="col-12">
                            <label class="form-label" for="grau_instrucao_escolar">Grau de instrução escolar</label>
                            <input type="text" class="form-control" id="grau_instrucao_escolar" name="grau_instrucao_escolar"
                                   maxlength="200" placeholder="Ex.: Ensino médio completo"
                                   value="{{ $grau_instrucao_escolar ?? '' }}" />
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="etnia_racial">Etnia / cor (autodeclaração)</label>
                            <input type="text" class="form-control" id="etnia_racial" name="etnia_racial"
                                   maxlength="120" placeholder="Conforme orientação do RH"
                                   value="{{ $etnia_racial ?? '' }}" />
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-outline-primary">Salvar informações</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <div class="card card-doc shadow-sm mb-3">
            <div class="card-body text-dark">
                <div class="fw-semibold mb-2">Checklist de envios</div>
                <div class="d-flex flex-column gap-2">
                    @foreach ($tiposOrdem as $t)
                        @php $rot = $rotulosLista[$t] ?? $t; @endphp
                        <div class="checklist-row d-flex align-items-start gap-2 flex-wrap">
                            @if(in_array($t, $tiposOk, true))
                                <span class="badge text-bg-success badge-tipo">✓ {{ $rot }}</span>
                            @else
                                <span class="badge text-bg-light text-dark border badge-tipo">Pendente — {{ $rot }}</span>
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
                    <div class="col-12">
                        <label class="form-label">Tipo de documento</label>
                        <select name="tipo" id="tipo-documento" class="form-select" required>
                            <option value="" disabled @selected(!old('tipo'))>Selecione…</option>
                            @foreach ($tiposOrdem as $t)
                                <option value="{{ $t }}" @selected(old('tipo') === $t)>{{ $rotulosLista[$t] ?? $t }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="input-arquivo">Arquivo</label>
                        <input type="file" name="arquivo" id="input-arquivo" class="form-control" required />
                        <div class="form-text" id="hint-arquivo">Escolha o tipo acima: PDF para documentos; JPG ou PNG apenas para foto 3×4.</div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Enviar</button>
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
