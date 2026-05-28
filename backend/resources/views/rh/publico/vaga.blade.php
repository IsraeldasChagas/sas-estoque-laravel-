<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{{ $vaga->titulo }} — Vaga</title>
    <link rel="icon" type="image/png" href="/imagens/favicon.png" />
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

            @include('rh.publico._form-candidatura-rh', [
                'vaga' => $vaga,
                'vagasAbertas' => $vagasAbertas ?? collect(),
                'vagaBloqueada' => $vagaBloqueada ?? false,
            ])
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

