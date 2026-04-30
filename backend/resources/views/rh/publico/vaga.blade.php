<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{{ $vaga->titulo }} — Vaga</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
        .gsp-brand { display:flex; align-items:center; gap:.75rem; }
        .gsp-mark { width: 44px; height: 44px; flex: 0 0 auto; object-fit: contain; }
        .gsp-name { line-height: 1.05; }
        .gsp-name .title { font-weight: 800; letter-spacing: .2px; }
        .gsp-name .sub { font-size: .86rem; color: rgba(0,0,0,.55); }
        .vaga-choices { border: 1px solid rgba(0,0,0,.08); border-radius: .75rem; padding: .75rem; background: #fff; }
        .vaga-choices .form-check { margin: .2rem 0; }
    </style>
</head>
<body class="bg-light">
<main class="container py-4" style="max-width: 860px;">
    <div class="mb-4">
        <div class="gsp-brand mb-3">
            <img class="gsp-mark" src="/imagens/logosemfundo.png" alt="Grupo Sabor Paraense" />
            <div class="gsp-name">
                <div class="title">Grupo Sabor Paraense</div>
                <div class="sub">Recrutamento e seleção</div>
            </div>
        </div>

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
            <div class="text-muted mb-3" style="font-size: .95rem;">Campos com <strong>*</strong> são obrigatórios.</div>

            @if(!empty($vagaBloqueada) && $vagaBloqueada)
                <div class="alert alert-warning">
                    Esta vaga está <strong>{{ strtoupper($vaga->status) }}</strong> no momento e não está aceitando novas candidaturas.
                </div>
            @endif

            <form method="POST" action="/vagas/{{ $vaga->slug }}/candidatar" enctype="multipart/form-data" class="row g-3">
                @csrf
                @php
                    $disabled = (!empty($vagaBloqueada) && $vagaBloqueada);
                    $cidadesRo = [
                        'Alta Floresta d\'Oeste',
                        'Alto Alegre dos Parecis',
                        'Alto Paraíso',
                        'Alvorada d\'Oeste',
                        'Ariquemes',
                        'Buritis',
                        'Cabixi',
                        'Cacaulândia',
                        'Cacoal',
                        'Campo Novo de Rondônia',
                        'Candeias do Jamari',
                        'Castanheiras',
                        'Cerejeiras',
                        'Chupinguaia',
                        'Colorado do Oeste',
                        'Corumbiara',
                        'Costa Marques',
                        'Cujubim',
                        'Espigão d\'Oeste',
                        'Governador Jorge Teixeira',
                        'Guajará-Mirim',
                        'Itapuã do Oeste',
                        'Jaru',
                        'Ji-Paraná',
                        'Machadinho d\'Oeste',
                        'Ministro Andreazza',
                        'Mirante da Serra',
                        'Monte Negro',
                        'Nova Brasilândia d\'Oeste',
                        'Nova Mamoré',
                        'Nova União',
                        'Novo Horizonte do Oeste',
                        'Ouro Preto do Oeste',
                        'Parecis',
                        'Pimenta Bueno',
                        'Pimenteiras do Oeste',
                        'Porto Velho',
                        'Presidente Médici',
                        'Primavera de Rondônia',
                        'Rio Crespo',
                        'Rolim de Moura',
                        'Santa Luzia d\'Oeste',
                        'São Felipe d\'Oeste',
                        'São Francisco do Guaporé',
                        'São Miguel do Guaporé',
                        'Seringueiras',
                        'Teixeirópolis',
                        'Theobroma',
                        'Urupá',
                        'Vale do Anari',
                        'Vale do Paraíso',
                        'Vilhena',
                    ];
                @endphp
                <fieldset {{ $disabled ? 'disabled="disabled"' : '' }} class="row g-3 m-0 p-0" style="border:0;">

                @php
                    $vagas = isset($vagasAbertas) ? $vagasAbertas : collect();
                    $vagasCount = is_countable($vagas) ? count($vagas) : 0;
                @endphp

                @if($vagasCount > 1)
                    <div class="col-12">
                        <div class="vaga-choices">
                            <div class="fw-semibold mb-2">Escolha a(s) vaga(s)</div>
                            @foreach($vagas as $v)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="vaga_ids[]" id="vaga_{{ $v->id }}" value="{{ $v->id }}"
                                           @checked(old('vaga_ids') ? in_array($v->id, (array) old('vaga_ids')) : ($v->id === $vaga->id)) />
                                    <label class="form-check-label" for="vaga_{{ $v->id }}">
                                        {{ $v->titulo }}@if(!empty($v->unidade)) — <span class="text-muted">{{ $v->unidade }}</span>@endif
                                    </label>
                                </div>
                            @endforeach
                            <div class="form-text mt-2">Se marcar mais de uma, sua candidatura será enviada para cada vaga selecionada.</div>
                        </div>
                    </div>
                @else
                    <input type="hidden" name="vaga_ids[]" value="{{ $vaga->id }}" />
                @endif

                <div class="col-md-8">
                    <label class="form-label">Nome <span class="text-danger">*</span></label>
                    <input name="nome" class="form-control" value="{{ old('nome') }}" required maxlength="160" />
                </div>
                <div class="col-md-4">
                    <label class="form-label">WhatsApp <span class="text-danger">*</span></label>
                    <input name="telefone" class="form-control" value="{{ old('telefone') }}" required maxlength="40" />
                </div>

                <div class="col-md-6">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" value="{{ old('email') }}" required maxlength="160" />
                </div>
                <div class="col-md-3">
                    <label class="form-label">Cidade <span class="text-danger">*</span></label>
                    <select name="cidade" class="form-control" required>
                        <option value="">Selecione a cidade</option>
                        @foreach($cidadesRo as $cidadeNome)
                            <option value="{{ $cidadeNome }}" @selected(old('cidade') === $cidadeNome)>{{ $cidadeNome }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Bairro <span class="text-danger">*</span></label>
                    <input name="bairro" class="form-control" value="{{ old('bairro') }}" required maxlength="120" />
                </div>

                <div class="col-md-6">
                    <label class="form-label">Disponibilidade <span class="text-danger">*</span></label>
                    <select name="disponibilidade" class="form-control" required>
                        <option value="">Selecione...</option>
                        <option value="sim" @selected(old('disponibilidade') === 'sim')>Sim</option>
                        <option value="nao" @selected(old('disponibilidade') === 'nao')>Não</option>
                    </select>
                </div>

                <div class="col-md-8">
                    <label class="form-label">Currículo (PDF) <span class="text-danger">*</span></label>
                    <input type="file" name="curriculo" class="form-control" accept="application/pdf" required />
                    <div class="form-text">Não envie CPF/RG/CTPS na candidatura. Documentos só após aprovação.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Foto <span class="text-danger">*</span></label>
                    <input type="file" name="foto" class="form-control" accept="image/jpeg,image/png" required />
                </div>

                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="lgpd" name="lgpd" required />
                        <label class="form-check-label" for="lgpd">
                            Autorizo o uso dos meus dados para fins de recrutamento e seleção. <span class="text-danger">*</span>
                        </label>
                    </div>
                </div>

                <div class="col-12">
                    <button class="btn btn-primary" {{ $disabled ? 'disabled="disabled"' : '' }}>Enviar candidatura</button>
                </div>
                </fieldset>
            </form>
        </div>
    </div>
</main>
</body>
</html>

