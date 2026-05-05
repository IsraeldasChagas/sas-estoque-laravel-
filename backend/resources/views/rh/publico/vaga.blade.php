<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{{ $vaga->titulo }} — Vaga</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
        .gsp-brand {
            display: flex;
            align-items: center;
            gap: .75rem;
            flex-wrap: nowrap;
            overflow-x: auto;
            overflow-y: hidden;
            -webkit-overflow-scrolling: touch;
        }
        .gsp-group,
        .gsp-subbrand,
        .gsp-sep { flex: 0 0 auto; }
        .gsp-mark { width: 88px; height: 88px; flex: 0 0 auto; object-fit: contain; }
        .gsp-group { display: flex; align-items: center; gap: .6rem; }
        .gsp-sep {
            width: 1px;
            align-self: stretch;
            min-height: 3rem;
            background: rgba(255,255,255,.18);
        }
        .gsp-subbrand { display: flex; align-items: center; gap: .6rem; }
        .gsp-submark {
            width: 132px;
            height: 88px;
            object-fit: contain;
            filter: drop-shadow(0 6px 18px rgba(0,0,0,.22));
        }
        .gsp-name { line-height: 1.05; }
        .gsp-name .title { font-weight: 800; letter-spacing: .2px; }
        .gsp-name .sub { font-size: .86rem; color: rgba(255,255,255,.75); }
        .vaga-choices { border: 1px solid rgba(0,0,0,.08); border-radius: .75rem; padding: .75rem; background: #fff; }
        .vaga-choices .form-check { margin: .2rem 0; }
        body { color: rgba(255,255,255,.92); }
        .gsp-name .title { color: #ffffff; }
        .gsp-name .sub { font-size: .86rem; color: rgba(255,255,255,.75); }
        .text-muted { color: rgba(255,255,255,.72) !important; }
    </style>
</head>
<body class="bg-light" style="background: radial-gradient(900px 380px at 18% 0%, rgba(255,140,0,.55), transparent 60%), linear-gradient(180deg, #ff7a00 0%, #2b1608 35%, #0b0b0d 100%);">
<main class="container py-4" style="max-width: 860px;">
    <div class="mb-4">
        <div class="gsp-brand mb-3">
            <div class="gsp-group">
                <img class="gsp-mark" src="/imagens/logosemfundo.png" alt="Grupo Sabor Paraense" />
                <div class="gsp-name">
                    <div class="title">Grupo Sabor Paraense</div>
                    <div class="sub">Recrutamento e seleção</div>
                </div>
            </div>
            <div class="gsp-sep" aria-hidden="true"></div>
            <div class="gsp-subbrand">
                <img class="gsp-submark" src="/imagens/logo-docemango.jpg" alt="Doce Mango" />
                <div class="gsp-name">
                    <div class="title" style="font-size: 1.05rem;">Doce Mango</div>
                    <div class="sub">Faz parte do grupo</div>
                </div>
            </div>
            <div class="gsp-sep" aria-hidden="true"></div>
            <div class="gsp-subbrand">
                <img class="gsp-submark" src="/imagens/logo-docenorte.jpg" alt="Doce Norte" />
                <div class="gsp-name">
                    <div class="title" style="font-size: 1.05rem;">Doce Norte</div>
                    <div class="sub">Faz parte do grupo</div>
                </div>
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

    @if(session('candidatura_parcial'))
        <div class="alert alert-warning">
            {{ session('candidatura_parcial') }}
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

    <div id="candidatura-erros-ajax" class="alert alert-danger d-none" role="alert"></div>

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

            @if(!empty($vaga->horarios_trabalho))
                <h3 class="h6 mt-3">Horários de trabalho</h3>
                <div class="mb-0" style="white-space: pre-wrap;">{{ $vaga->horarios_trabalho }}</div>
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

            <form id="formCandidaturaRh" method="POST" action="/vagas/{{ $vaga->slug }}/candidatar" enctype="multipart/form-data" class="row g-3">
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
                                    <label class="form-check-label" for="vaga_{{ $v->id }}" style="display:flex; align-items:center; gap:.5rem; justify-content:space-between;">
                                        <span>
                                            {{ $v->titulo }}@if(!empty($v->unidade)) — <span class="text-muted">{{ $v->unidade }}</span>@endif
                                        </span>
                                        <a class="btn btn-sm btn-outline-primary" href="/vagas/{{ $v->slug }}">Ver</a>
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

                <div class="col-12">
                    <label class="form-label">Observações (opcional)</label>
                    <textarea name="observacoes" class="form-control" rows="3" maxlength="500" placeholder="Se quiser, deixe uma observação rápida (ex.: disponibilidade de horário, informação importante, etc.).">{{ old('observacoes') }}</textarea>
                    <div class="form-text">Máximo de 500 caracteres. Não é obrigatório.</div>
                </div>

                <div class="col-md-8">
                    <label class="form-label">Currículo (PDF) <span class="text-danger">*</span></label>
                    <input type="file" name="curriculo" class="form-control" accept="application/pdf" required />
                    <div class="form-text">Tamanho máximo do PDF: <strong>7,5 MB</strong>. Se der erro, comprima o arquivo antes de enviar. Não envie CPF/RG/CTPS na candidatura (documentos só após aprovação).</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Foto <span class="text-danger">*</span></label>
                    <input type="file" name="foto" class="form-control" accept="image/jpeg,image/png" required />
                    <div class="form-text">JPG ou PNG, até <strong>3 MB</strong>. Se necessário, reduza a qualidade da foto.</div>
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
                    <button type="submit" id="btnCandidaturaRh" class="btn btn-primary" {{ $disabled ? 'disabled="disabled"' : '' }}>Enviar candidatura</button>
                </div>
                </fieldset>
            </form>
        </div>
    </div>
</main>
<script>
(function () {
    var avisoKey = "rhCandidaturaAvisoParcial";
    var form = document.getElementById("formCandidaturaRh");
    var btn = document.getElementById("btnCandidaturaRh");
    var boxAjax = document.getElementById("candidatura-erros-ajax");
    if (!form || !btn || form.hasAttribute("data-rh-async-bound")) return;
    form.setAttribute("data-rh-async-bound", "1");
    form.addEventListener("submit", function (e) {
        if (btn.disabled) return;
        e.preventDefault();
        if (boxAjax) {
            boxAjax.classList.add("d-none");
            boxAjax.innerHTML = "";
        }
        btn.disabled = true;
        var txt = btn.textContent;
        btn.textContent = "Enviando...";
        var fd = new FormData(form);
        fetch(form.action, {
            method: "POST",
            body: fd,
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                "Accept": "application/json"
            },
            credentials: "same-origin"
        })
            .then(function (res) {
                var ct = res.headers.get("Content-Type") || "";
                if (res.status === 422 && ct.indexOf("application/json") !== -1) {
                    return res.json().then(function (data) {
                        var errs = data.errors || {};
                        var lines = [];
                        Object.keys(errs).forEach(function (k) {
                            var v = errs[k];
                            if (Array.isArray(v)) v.forEach(function (x) { lines.push(x); });
                            else if (v) lines.push(String(v));
                        });
                        if (lines.length === 0 && data.message) lines.push(data.message);
                        if (boxAjax) {
                            boxAjax.innerHTML = "<div class=\"fw-semibold mb-2\">Não foi possível enviar. Corrija e tente de novo.</div><ul class=\"mb-0\">" +
                                lines.map(function (t) { return "<li>" + String(t).replace(/</g, "&lt;") + "</li>"; }).join("") + "</ul>";
                            boxAjax.classList.remove("d-none");
                            boxAjax.scrollIntoView({ behavior: "smooth", block: "nearest" });
                        }
                    });
                }
                if (res.ok && ct.indexOf("application/json") !== -1) {
                    return res.json().then(function (data) {
                        if (data.ok && data.redirect) {
                            try {
                                if (data.aviso_parcial) sessionStorage.setItem(avisoKey, data.aviso_parcial);
                                else sessionStorage.removeItem(avisoKey);
                            } catch (_) {}
                            window.location.href = data.redirect;
                            return;
                        }
                    });
                }
                if (res.ok && res.redirected && res.url) {
                    window.location.href = res.url;
                    return;
                }
                form.submit();
            })
            .catch(function () {
                try {
                    form.submit();
                } catch (_) {
                    window.location.reload();
                }
            })
            .finally(function () {
                btn.disabled = false;
                btn.textContent = txt;
            });
    });
    try {
        var u = new URL(window.location.href);
        if (u.searchParams.get("ok") === "1") {
            var msg = sessionStorage.getItem(avisoKey);
            if (msg) {
                sessionStorage.removeItem(avisoKey);
                var w = document.createElement("div");
                w.className = "alert alert-warning";
                w.setAttribute("role", "alert");
                w.textContent = msg;
                var okEl = document.querySelector(".alert.alert-success");
                if (okEl && okEl.parentNode) okEl.parentNode.insertBefore(w, okEl.nextSibling);
            }
        }
    } catch (_) {}
})();
</script>
</body>
</html>

