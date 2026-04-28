<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{{ $vaga->titulo }} — Vaga</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body class="bg-light">
<main class="container py-4" style="max-width: 860px;">
    <div class="mb-4">
        <h1 class="h3 mb-1">{{ $vaga->titulo }}</h1>
        <div class="text-muted">
            @if(!empty($vaga->unidade)) <span class="me-3"><strong>Unidade:</strong> {{ $vaga->unidade }}</span>@endif
            @if(!empty($vaga->setor)) <span class="me-3"><strong>Setor:</strong> {{ $vaga->setor }}</span>@endif
            <span><strong>Status:</strong> {{ strtoupper($vaga->status) }}</span>
        </div>
    </div>

    @if(request()->query('ok'))
        <div class="alert alert-success">
            Candidatura enviada com sucesso. Obrigado!
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <div class="fw-semibold mb-2">Verifique os campos e tente novamente.</div>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h5">Descrição</h2>
            <div class="mb-3" style="white-space: pre-wrap;">{{ $vaga->descricao }}</div>

            @if(!empty($vaga->requisitos))
                <h3 class="h6">Requisitos</h3>
                <div class="mb-3" style="white-space: pre-wrap;">{{ $vaga->requisitos }}</div>
            @endif

            @if(!empty($vaga->beneficios))
                <h3 class="h6">Benefícios</h3>
                <div class="mb-0" style="white-space: pre-wrap;">{{ $vaga->beneficios }}</div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h2 class="h5 mb-3">Candidatar-se</h2>

            <form method="POST" action="/vagas/{{ $vaga->slug }}/candidatar" enctype="multipart/form-data" class="row g-3">
                @csrf

                <div class="col-md-8">
                    <label class="form-label">Nome</label>
                    <input name="nome" class="form-control" value="{{ old('nome') }}" required maxlength="160" />
                </div>
                <div class="col-md-4">
                    <label class="form-label">Telefone</label>
                    <input name="telefone" class="form-control" value="{{ old('telefone') }}" maxlength="40" />
                </div>

                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email') }}" maxlength="160" />
                </div>
                <div class="col-md-3">
                    <label class="form-label">Cidade</label>
                    <input name="cidade" class="form-control" value="{{ old('cidade') }}" maxlength="120" />
                </div>
                <div class="col-md-3">
                    <label class="form-label">Bairro</label>
                    <input name="bairro" class="form-control" value="{{ old('bairro') }}" maxlength="120" />
                </div>

                <div class="col-12">
                    <label class="form-label">Experiência</label>
                    <textarea name="experiencia" class="form-control" rows="4" maxlength="20000">{{ old('experiencia') }}</textarea>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Último emprego</label>
                    <input name="ultimo_emprego" class="form-control" value="{{ old('ultimo_emprego') }}" maxlength="160" />
                </div>
                <div class="col-md-3">
                    <label class="form-label">Disponibilidade</label>
                    <input name="disponibilidade" class="form-control" value="{{ old('disponibilidade') }}" maxlength="80" />
                </div>
                <div class="col-md-3">
                    <label class="form-label">Pretensão salarial</label>
                    <input name="pretensao_salarial" class="form-control" value="{{ old('pretensao_salarial') }}" maxlength="80" />
                </div>

                <div class="col-md-8">
                    <label class="form-label">Currículo (PDF)</label>
                    <input type="file" name="curriculo" class="form-control" accept="application/pdf" required />
                    <div class="form-text">Não envie CPF/RG/CTPS na candidatura. Documentos só após aprovação.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Foto (opcional)</label>
                    <input type="file" name="foto" class="form-control" accept="image/jpeg,image/png,image/webp" />
                </div>

                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="lgpd" name="lgpd" required />
                        <label class="form-check-label" for="lgpd">
                            Autorizo o uso dos meus dados para fins de recrutamento e seleção.
                        </label>
                    </div>
                </div>

                <div class="col-12">
                    <button class="btn btn-primary">Enviar candidatura</button>
                </div>
            </form>
        </div>
    </div>
</main>
</body>
</html>

