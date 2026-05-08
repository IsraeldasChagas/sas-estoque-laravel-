<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Vagas — Grupo Sabor Paraense</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
        body { background: radial-gradient(900px 380px at 18% 0%, rgba(255,140,0,.55), transparent 60%), linear-gradient(180deg, #ff7a00 0%, #2b1608 35%, #0b0b0d 100%); color: rgba(255,255,255,.92); }
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
        .gsp-name .title { font-weight: 800; letter-spacing: .2px; color: #fff; }
        .gsp-name .sub { font-size: .86rem; color: rgba(255,255,255,.75); }
        .text-muted { color: rgba(255,255,255,.72) !important; }
        .board-share {
            background: rgba(255,255,255,.1);
            border: 1px solid rgba(255,255,255,.2);
            border-radius: 14px;
            padding: 1rem 1.1rem;
        }
        .board-share .form-control { background: rgba(0,0,0,.2); border-color: rgba(255,255,255,.25); color: #fff; }
        .accordion.vagas-board .accordion-item { border-radius: 12px; overflow: hidden; margin-bottom: .65rem; border: 1px solid rgba(0,0,0,.08); }
        .accordion.vagas-board .accordion-button:not(.collapsed) { background: rgba(255,255,255,.95); color: #1a1a1a; }
        .accordion.vagas-board .accordion-button { font-weight: 600; }
        .vaga-body-inner { color: rgba(0,0,0,.85); }
        .vaga-body-inner h3 { font-size: 1rem; font-weight: 700; margin-top: 1rem; }
        .vaga-body-inner h3:first-child { margin-top: 0; }
        .vaga-choices { border: 1px solid rgba(0,0,0,.08); border-radius: .75rem; padding: .75rem; background: #fff; }
        .vaga-choices .form-check { margin: .2rem 0; }
        #sec-candidatura .card { border-radius: 14px; }
    </style>
</head>
<body>
@php
    $items = isset($vagas) ? $vagas : collect();
    $boardUrl = url('/vagas');
@endphp

<main class="container py-4" style="max-width: 980px;">
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-4">
        <div class="gsp-brand">
            <div class="gsp-group">
                <img class="gsp-mark" src="/imagens/logosemfundo.png" alt="Grupo Sabor Paraense" />
                <div class="gsp-name">
                    <div class="title">Grupo Sabor Paraense</div>
                    <div class="sub">Vagas abertas e candidatura</div>
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
    </div>

    <div class="board-share mb-4">
        <div class="fw-semibold mb-2">Divulgue esta página</div>
        <p class="small mb-2 opacity-90">Um único link e um QR Code para todas as vagas — compartilhe em redes, outdoors ou materiais impressos.</p>
        <div class="row g-2 align-items-center">
            <div class="col min-w-0">
                <label class="visually-hidden" for="boardPublicUrl">Link público</label>
                <input id="boardPublicUrl" type="text" class="form-control form-control-sm" readonly value="{{ $boardUrl }}" />
            </div>
            <div class="col-auto d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-light btn-sm" id="boardCopyLinkBtn">Copiar link</button>
                <a class="btn btn-outline-light btn-sm" href="{{ url('/vagas/qrcode') }}" target="_blank" rel="noopener noreferrer">Abrir QR Code</a>
            </div>
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

    @if(!count($items))
        <div class="alert alert-info shadow-sm">Nenhuma vaga cadastrada no momento.</div>
    @else
        <h2 class="h5 text-white mb-3">Todas as vagas</h2>
        <p class="text-muted small mb-3">Clique numa vaga para ver descrição, requisitos e benefícios.</p>
        <div class="accordion vagas-board mb-4" id="vagasAccordion">
            @foreach($items as $v)
                @php
                    $status = strtolower((string) ($v->status ?? ''));
                    $isOpen = $status === 'aberta';
                    $badgeClass = $isOpen ? 'bg-success' : ($status === 'pausada' ? 'bg-warning text-dark' : 'bg-secondary');
                @endphp
                <div class="accordion-item bg-white" id="vaga-{{ $v->slug }}">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-vaga-{{ $v->id }}" aria-expanded="false" aria-controls="collapse-vaga-{{ $v->id }}">
                            <span class="me-auto text-start">{{ $v->titulo }}</span>
                            <span class="badge {{ $badgeClass }} ms-2">{{ strtoupper($status ?: '—') }}</span>
                        </button>
                    </h2>
                    <div id="collapse-vaga-{{ $v->id }}" class="accordion-collapse collapse" data-bs-parent="#vagasAccordion">
                        <div class="accordion-body vaga-body-inner">
                            <div class="small text-muted mb-2">
                                @if(!empty($v->unidade)) <span class="me-3"><strong>Unidade:</strong> {{ $v->unidade }}</span>@endif
                                @if(!empty($v->setor)) <span class="me-3"><strong>Setor:</strong> {{ $v->setor }}</span>@endif
                            </div>
                            @if(!empty($v->horarios_trabalho))
                                <div class="small mb-3"><strong>Horários:</strong> {{ $v->horarios_trabalho }}</div>
                            @endif

                            <h3>Descrição</h3>
                            <div class="mb-3" style="white-space: pre-wrap;">{{ $v->descricao }}</div>

                            @if(!empty($v->requisitos))
                                <h3>Requisitos</h3>
                                <div class="mb-3" style="white-space: pre-wrap;">{{ $v->requisitos }}</div>
                            @endif

                            @if(!empty($v->beneficios))
                                <h3>Benefícios</h3>
                                <div class="mb-3" style="white-space: pre-wrap;">{{ $v->beneficios }}</div>
                            @endif

                            @if(!$isOpen)
                                <div class="alert alert-warning mb-0">Esta vaga não está aberta para novas candidaturas.</div>
                            @else
                                <p class="small text-muted mb-0">Use o formulário <a href="#sec-candidatura">Candidatar-se</a> no final da página e marque esta vaga (ou outras abertas).</p>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <section id="sec-candidatura" class="card shadow-sm border-0">
        <div class="card-body text-dark">
            <h2 class="h5 mb-3">Candidatar-se</h2>
            <div class="text-muted mb-3" style="font-size: .95rem;">Campos com <strong>*</strong> são obrigatórios.</div>

            @include('rh.publico._form-candidatura-rh', [
                'vaga' => $vaga ?? null,
                'vagasAbertas' => $vagasAbertas ?? collect(),
                'vagaBloqueada' => $vagaBloqueada ?? false,
            ])
        </div>
    </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    var btn = document.getElementById('boardCopyLinkBtn');
    var inp = document.getElementById('boardPublicUrl');
    if (btn && inp) {
        btn.addEventListener('click', function () {
            var url = inp.value || '';
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(function () {
                    btn.textContent = 'Copiado!';
                    setTimeout(function () { btn.textContent = 'Copiar link'; }, 2000);
                }).catch(function () {
                    inp.select();
                    document.execCommand('copy');
                });
            } else {
                inp.select();
                document.execCommand('copy');
            }
        });
    }

    function openVagaFromHash() {
        var raw = window.location.hash.replace(/^#/, '');
        if (!raw) return;
        var id = raw.indexOf('vaga-') === 0 ? raw : ('vaga-' + raw);
        var el = document.getElementById(id);
        if (!el) return;
        var collapse = el.querySelector('.accordion-collapse');
        if (!collapse || typeof bootstrap === 'undefined') return;
        var inst = bootstrap.Collapse.getOrCreateInstance(collapse, { toggle: false });
        inst.show();
        setTimeout(function () {
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 80);
    }
    document.addEventListener('DOMContentLoaded', openVagaFromHash);
    window.addEventListener('hashchange', openVagaFromHash);
})();
</script>
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
