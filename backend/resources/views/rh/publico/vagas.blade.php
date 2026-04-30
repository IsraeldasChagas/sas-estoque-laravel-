<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Vagas — Grupo Sabor Paraense</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
        body { background: radial-gradient(900px 380px at 12% 8%, rgba(25,135,84,.14), transparent 60%), radial-gradient(800px 420px at 88% 22%, rgba(13,110,253,.10), transparent 55%), linear-gradient(180deg, #f7faf9 0%, #f1f5f9 60%, #eef2f7 100%); }
        .gsp-brand { display:flex; align-items:center; gap:.75rem; }
        .gsp-mark { width: 44px; height: 44px; flex: 0 0 auto; object-fit: contain; }
        .gsp-name { line-height: 1.05; }
        .gsp-name .title { font-weight: 800; letter-spacing: .2px; }
        .gsp-name .sub { font-size: .86rem; color: rgba(0,0,0,.55); }
        .vaga-card { border: 1px solid rgba(0,0,0,.08); border-radius: 14px; overflow:hidden; background:#fff; }
        .vaga-card__top { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; padding: 1rem 1rem .75rem; }
        .vaga-meta { color: rgba(0,0,0,.62); font-size: .92rem; }
        .badge-soft { font-weight: 700; }
        .vaga-card__bottom { display:flex; gap:.5rem; flex-wrap:wrap; padding: .75rem 1rem 1rem; border-top: 1px solid rgba(0,0,0,.06); }
        .vaga-card__desc { padding: 0 1rem .75rem; white-space: pre-wrap; color: rgba(0,0,0,.78); }
    </style>
</head>
<body>
<main class="container py-4" style="max-width: 980px;">
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-4">
        <div class="gsp-brand">
            <img class="gsp-mark" src="/imagens/logosemfundo.png" alt="Grupo Sabor Paraense" />
            <div class="gsp-name">
                <div class="title">Grupo Sabor Paraense</div>
                <div class="sub">Vagas abertas e candidatura</div>
            </div>
        </div>
        <div class="text-muted" style="max-width: 520px;">
            Selecione uma vaga e clique em <strong>Ver vaga</strong>.
        </div>
    </div>

    @php($items = isset($vagas) ? $vagas : collect())

    @if(!count($items))
        <div class="alert alert-info">Nenhuma vaga cadastrada no momento.</div>
    @else
        <form id="vagasPublicasForm">
            <div class="row g-3">
                @foreach($items as $v)
                    @php
                        $status = strtolower((string) ($v->status ?? ''));
                        $isOpen = $status === 'aberta';
                        $badgeClass = $isOpen ? 'bg-success' : ($status === 'pausada' ? 'bg-warning text-dark' : 'bg-secondary');
                    @endphp
                    <div class="col-12 col-md-6">
                        <div class="vaga-card">
                            <div class="vaga-card__top">
                                <div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="slug" value="{{ $v->slug }}" id="vaga_{{ $v->id }}" {{ $isOpen ? '' : 'disabled="disabled"' }}>
                                        <label class="form-check-label fw-semibold" for="vaga_{{ $v->id }}">{{ $v->titulo }}</label>
                                    </div>
                                    <div class="vaga-meta mt-1">
                                        @if(!empty($v->unidade)) <span><strong>Unidade:</strong> {{ $v->unidade }}</span>@endif
                                        @if(!empty($v->setor)) <span class="ms-2"><strong>Setor:</strong> {{ $v->setor }}</span>@endif
                                    </div>
                                    @if(!empty($v->horarios_trabalho))
                                        <div class="vaga-meta mt-1"><strong>Horários:</strong> {{ $v->horarios_trabalho }}</div>
                                    @endif
                                </div>
                                <span class="badge {{ $badgeClass }} badge-soft">{{ strtoupper($status ?: '—') }}</span>
                            </div>
                            @if(!empty($v->descricao))
                                <div class="vaga-card__desc">{{ \Illuminate\Support\Str::limit($v->descricao, 240) }}</div>
                            @endif
                            <div class="vaga-card__bottom">
                                <a class="btn btn-outline-primary btn-sm" href="/vagas/{{ $v->slug }}">Ver detalhes</a>
                                <a class="btn btn-outline-secondary btn-sm" href="/vagas/{{ $v->slug }}/qrcode" target="_blank" rel="noopener noreferrer">QR Code</a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-4">
                <div class="text-muted">Apenas vagas <strong>ABERTAS</strong> podem ser selecionadas.</div>
                <button type="button" class="btn btn-primary" id="vagasPublicasVerBtn">Ver vaga</button>
            </div>
        </form>
    @endif
</main>

<script>
  (function () {
    const form = document.getElementById('vagasPublicasForm');
    const btn = document.getElementById('vagasPublicasVerBtn');
    if (!form || !btn) return;

    // seleção única
    form.addEventListener('change', function (e) {
      const t = e.target;
      if (!t || t.name !== 'slug') return;
      if (t.checked) {
        form.querySelectorAll('input[name="slug"]').forEach(function (el) {
          if (el !== t) el.checked = false;
        });
      }
    });

    btn.addEventListener('click', function () {
      const checked = form.querySelector('input[name="slug"]:checked');
      if (!checked) {
        alert('Selecione uma vaga aberta.');
        return;
      }
      window.location.href = '/vagas/' + encodeURIComponent(checked.value);
    });
  })();
</script>
</body>
</html>

