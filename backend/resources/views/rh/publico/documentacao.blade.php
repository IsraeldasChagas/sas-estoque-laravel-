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
        .badge-tipo { font-weight: 600; }
    </style>
</head>
<body>
<main class="container py-4" style="max-width: 640px;">
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
                <p class="mb-0 small text-muted">Envie cada documento em <strong>PDF</strong> (máx. 6 MB por arquivo). Você pode voltar a esta página para enviar ou atualizar um tipo até o RH concluir a contratação.</p>
            </div>
        </div>

        @php
            $rotulos = [
                'cpf' => 'CPF',
                'rg' => 'RG',
                'comprovante' => 'Comprovante de residência',
                'ctps' => 'CTPS',
            ];
            $tiposOk = isset($tiposOk) && is_array($tiposOk) ? $tiposOk : [];
        @endphp

        <div class="card card-doc shadow-sm mb-3">
            <div class="card-body text-dark">
                <div class="fw-semibold mb-2">Status dos envios</div>
                <div class="d-flex flex-wrap gap-2">
                    @foreach (['cpf', 'rg', 'comprovante', 'ctps'] as $t)
                        @if(in_array($t, $tiposOk, true))
                            <span class="badge text-bg-success badge-tipo">{{ $rotulos[$t] ?? $t }} ✓</span>
                        @else
                            <span class="badge text-bg-light text-dark border badge-tipo">{{ $rotulos[$t] ?? $t }} — pendente</span>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card card-doc shadow-sm">
            <div class="card-body text-dark">
                <h2 class="h6 mb-3">Enviar documento</h2>
                <form method="POST" action="/documentacao/{{ $token }}" enctype="multipart/form-data" class="row g-3">
                    @csrf
                    <div class="col-12">
                        <label class="form-label">Tipo</label>
                        <select name="tipo" class="form-select" required>
                            <option value="" disabled @selected(!old('tipo'))>Selecione…</option>
                            <option value="cpf" @selected(old('tipo') === 'cpf')>CPF</option>
                            <option value="rg" @selected(old('tipo') === 'rg')>RG</option>
                            <option value="comprovante" @selected(old('tipo') === 'comprovante')>Comprovante de residência</option>
                            <option value="ctps" @selected(old('tipo') === 'ctps')>CTPS</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Arquivo (PDF)</label>
                        <input type="file" name="arquivo" class="form-control" accept="application/pdf,.pdf" required />
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Enviar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</main>
</body>
</html>
