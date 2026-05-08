<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Vagas — Grupo Sabor Paraense</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
    <style>
        :root {
            --gsp-orange: #ff7a00;
            --gsp-deep: #2b1608;
            --gsp-card-radius: 22px;
        }
        body {
            background: radial-gradient(1100px 420px at 15% -5%, rgba(255, 140, 0, 0.45), transparent 55%),
                linear-gradient(165deg, var(--gsp-orange) 0%, var(--gsp-deep) 42%, #070708 100%);
            color: rgba(255, 255, 255, 0.94);
            min-height: 100vh;
        }
        .gsp-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: nowrap;
            overflow-x: auto;
            overflow-y: hidden;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 0.25rem;
        }
        .gsp-group,
        .gsp-subbrand,
        .gsp-sep { flex: 0 0 auto; }
        .gsp-mark { width: 80px; height: 80px; flex: 0 0 auto; object-fit: contain; filter: drop-shadow(0 6px 18px rgba(0, 0, 0, 0.25)); }
        .gsp-group { display: flex; align-items: center; gap: 0.6rem; }
        .gsp-sep {
            width: 1px;
            align-self: stretch;
            min-height: 3rem;
            background: rgba(255, 255, 255, 0.2);
        }
        .gsp-subbrand { display: flex; align-items: center; gap: 0.6rem; }
        .gsp-submark {
            width: 120px;
            height: 78px;
            object-fit: contain;
            filter: drop-shadow(0 6px 18px rgba(0, 0, 0, 0.22));
        }
        .gsp-name { line-height: 1.08; }
        .gsp-name .title { font-weight: 800; letter-spacing: 0.3px; color: #fff; }
        .gsp-name .sub { font-size: 0.84rem; color: rgba(255, 255, 255, 0.76); }
        .text-muted-light { color: rgba(255, 255, 255, 0.68) !important; }

        .section-title {
            font-weight: 800;
            letter-spacing: 0.02em;
            font-size: 1.35rem;
            color: #fff;
            text-shadow: 0 2px 18px rgba(0, 0, 0, 0.35);
        }

        .vaga-card-ui {
            background: linear-gradient(155deg, #ffffff 0%, #f6f7f9 55%, #eef0f4 100%);
            border-radius: var(--gsp-card-radius);
            border: 1px solid rgba(255, 255, 255, 0.65);
            box-shadow: 0 14px 42px rgba(0, 0, 0, 0.18), 0 2px 0 rgba(255, 255, 255, 0.9) inset;
            overflow: hidden;
            transition: transform 0.22s ease, box-shadow 0.22s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .vaga-card-ui:hover {
            transform: translateY(-6px);
            box-shadow: 0 22px 52px rgba(0, 0, 0, 0.22), 0 2px 0 rgba(255, 255, 255, 0.95) inset;
        }
        .vaga-card-ui__head {
            display: flex;
            align-items: flex-start;
            gap: 0.85rem;
            padding: 1.15rem 1.15rem 0.65rem;
        }
        .vaga-card-ui__icon {
            flex: 0 0 auto;
            width: 54px;
            height: 54px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.45rem;
            color: #fff;
            background: linear-gradient(135deg, #ff9a3c 0%, #ff6b18 45%, #e85d04 100%);
            box-shadow: 0 10px 22px rgba(232, 93, 4, 0.38);
        }
        .vaga-card-ui__icon.vaga-tone-kitchen { background: linear-gradient(135deg, #ffb347, #ff7e28); }
        .vaga-card-ui__icon.vaga-tone-service { background: linear-gradient(135deg, #7dd3fc, #2563eb); }
        .vaga-card-ui__icon.vaga-tone-logistics { background: linear-gradient(135deg, #86efac, #16a34a); }
        .vaga-card-ui__icon.vaga-tone-clean { background: linear-gradient(135deg, #a5b4fc, #6366f1); }
        .vaga-card-ui__icon.vaga-tone-stock { background: linear-gradient(135deg, #fcd34d, #d97706); }
        .vaga-card-ui__icon.vaga-tone-lead { background: linear-gradient(135deg, #f472b6, #be185d); }

        .vaga-card-ui__title {
            font-weight: 800;
            font-size: 1.05rem;
            line-height: 1.25;
            color: #1c1917;
            margin: 0;
        }
        .vaga-card-ui__tag {
            display: inline-block;
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: rgba(28, 25, 23, 0.55);
            margin-top: 0.2rem;
        }
        .vaga-card-ui__body {
            padding: 0 1.15rem 1rem;
            flex: 1 1 auto;
            color: #44403c;
            font-size: 0.92rem;
            line-height: 1.45;
        }
        .vaga-card-ui__meta {
            font-size: 0.8rem;
            color: #78716c;
            margin-bottom: 0.6rem;
        }
        .vaga-card-ui__meta i { opacity: 0.85; vertical-align: -0.1em; }
        .vaga-card-ui__excerpt {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .vaga-card-ui__foot {
            padding: 0 1rem 1.1rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .vaga-card-ui__foot .btn-candidatura-vaga {
            border-radius: 12px;
            font-weight: 700;
            padding: 0.55rem 1rem;
            box-shadow: 0 8px 20px rgba(234, 88, 12, 0.35);
        }
        .vaga-card-ui__foot .btn-outline-secondary {
            border-radius: 12px;
            font-weight: 600;
            border-color: rgba(0, 0, 0, 0.15);
            color: #44403c;
        }
        .vaga-detalhe-full {
            margin-top: 0.75rem;
            padding-top: 0.75rem;
            border-top: 1px dashed rgba(0, 0, 0, 0.1);
        }
        .vaga-detalhe-full h4 {
            font-size: 0.82rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #78716c;
            margin: 0.85rem 0 0.35rem;
        }
        .vaga-detalhe-full h4:first-child { margin-top: 0; }
        .vaga-detalhe-full .block-txt { white-space: pre-wrap; color: #292524; font-size: 0.9rem; }

        #modalCandidaturaRh .modal-content {
            border-radius: 18px;
            border: none;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.35);
        }
        #modalCandidaturaRh .modal-header {
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
            padding: 1rem 1.25rem;
        }
        #modalCandidaturaRh .modal-title {
            font-weight: 800;
            font-size: 1.05rem;
            line-height: 1.3;
            padding-right: 0.5rem;
        }

        #modalCandidaturaRh .candidatura-rh-form .form-label {
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 0.35rem;
            color: #292524;
        }
        #modalCandidaturaRh .candidatura-rh-form .form-control,
        #modalCandidaturaRh .candidatura-rh-form .form-select {
            font-size: 0.95rem;
        }
        #modalCandidaturaRh .candidatura-rh-form fieldset.row {
            --bs-gutter-x: 0.9rem;
            --bs-gutter-y: 0.65rem;
        }

        /* Modo foco: uma vaga em destaque ao centro, demais recolhidas / suaves */
        .vagas-grid {
            position: relative;
            transition: filter 0.5s ease;
        }
        .vaga-col {
            transition:
                opacity 0.55s cubic-bezier(0.33, 1, 0.68, 1),
                filter 0.55s cubic-bezier(0.33, 1, 0.68, 1),
                transform 0.55s cubic-bezier(0.33, 1, 0.68, 1);
        }
        body.vaga-spotlight-on .vagas-grid {
            min-height: 50vh;
        }
        body.vaga-spotlight-on .vaga-col:not(.is-vaga-focus) {
            opacity: 0.16;
            filter: blur(6px) saturate(0.5);
            transform: scale(0.9);
            pointer-events: none;
            position: relative;
            z-index: 0;
        }
        body.vaga-spotlight-on .vaga-col:not(.is-vaga-focus) .vaga-card-ui {
            box-shadow: none;
        }
        /* Cartão em destaque: centro horizontal e vertical da tela */
        body.vaga-spotlight-on .vaga-col.is-vaga-focus {
            position: fixed;
            left: 50%;
            top: 50%;
            right: auto;
            bottom: auto;
            width: min(540px, calc(100vw - 1.75rem));
            max-width: 100%;
            max-height: min(88vh, 900px);
            overflow-x: hidden;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            margin: 0;
            transform: translate(-50%, -50%) scale(1.03);
            z-index: 1040;
            filter: none;
            opacity: 1;
            padding-left: calc(var(--bs-gutter-x, 1rem) * 0.5);
            padding-right: calc(var(--bs-gutter-x, 1rem) * 0.5);
            transition: opacity 0.35s ease, transform 0.45s cubic-bezier(0.33, 1.15, 0.48, 1);
        }
        body.vaga-spotlight-on .vaga-col.is-vaga-focus .vaga-card-ui {
            box-shadow:
                0 36px 90px rgba(0, 0, 0, 0.32),
                0 0 0 3px rgba(255, 160, 60, 0.55),
                0 0 48px rgba(255, 120, 0, 0.22);
            animation: vagaCardGlow 2.4s ease-in-out infinite;
        }
        body.vaga-spotlight-on .vaga-col.is-vaga-focus .vaga-card-ui:hover {
            transform: none;
            box-shadow:
                0 36px 90px rgba(0, 0, 0, 0.34),
                0 0 0 3px rgba(255, 180, 80, 0.65),
                0 0 56px rgba(255, 130, 0, 0.28);
        }
        @keyframes vagaCardGlow {
            0%, 100% {
                box-shadow:
                    0 32px 80px rgba(0, 0, 0, 0.3),
                    0 0 0 3px rgba(255, 160, 60, 0.5),
                    0 0 40px rgba(255, 120, 0, 0.18);
            }
            50% {
                box-shadow:
                    0 40px 100px rgba(0, 0, 0, 0.36),
                    0 0 0 4px rgba(255, 190, 100, 0.7),
                    0 0 64px rgba(255, 150, 40, 0.35);
            }
        }
    </style>
</head>
<body>
@php
    $items = isset($vagas) ? $vagas : collect();

    /**
     * Ícone e cor conforme palavras-chave no título, setor ou tipo de contratação (caixa, cozinha, etc.).
     *
     * @param  object  $row
     * @return array{icon: string, tag: string, tone: string}
     */
    $rhPublicoIconeVaga = static function ($row): array {
        $t = mb_strtolower(
            trim(($row->titulo ?? '') . ' ' . ($row->setor ?? '') . ' ' . ($row->tipo_contratacao ?? ''))
        );
        $rules = [
            [['caixa', 'pdv', 'operador de caixa', 'operador caixa'], 'bi-shop-window', 'Caixa / loja', ''],
            [['cozinha', 'cozinheiro', 'auxiliar de cozinha', 'chapa', 'grelha'], 'bi-egg-fried', 'Cozinha', 'kitchen'],
            [['garçom', 'garcom', 'atendente', 'atendimento', 'salão', 'salao', 'buffet'], 'bi-cup-hot', 'Atendimento', 'service'],
            [['entrega', 'motoboy', 'motorista', 'logística', 'logistica', 'frota'], 'bi-truck', 'Logística', 'logistics'],
            [['limpeza', 'auxiliar de limpeza', 'zelador', 'higien'], 'bi-droplet', 'Limpeza', 'clean'],
            [['padeiro', 'padaria', 'forno', 'confeiteiro', 'produção', 'producao'], 'bi-basket3', 'Produção / alimentos', ''],
            [['estoque', 'reposição', 'reposicao', 'almoxarifado', 'separação', 'separacao'], 'bi-box-seam', 'Estoque', 'stock'],
            [['administr', 'escritório', 'escritorio', 'financeiro', 'rh ', 'departamento pessoal'], 'bi-briefcase', 'Administrativo', ''],
            [['gerente', 'supervisor', 'coordenador', 'lider', 'líder'], 'bi-person-badge', 'Liderança', 'lead'],
        ];
        foreach ($rules as $rule) {
            [$keys, $icon, $tag, $tone] = $rule;
            foreach ($keys as $k) {
                if ($k !== '' && str_contains($t, $k)) {
                    return ['icon' => $icon, 'tag' => $tag, 'tone' => $tone];
                }
            }
        }

        return ['icon' => 'bi-briefcase', 'tag' => 'Oportunidade', 'tone' => ''];
    };
@endphp

<main class="container py-4 pb-5" style="max-width: 1140px;">
    <div class="mb-4">
        <div class="gsp-brand mb-3">
            <div class="gsp-group">
                <img class="gsp-mark" src="/imagens/logosemfundo.png" alt="Grupo Sabor Paraense" />
                <div class="gsp-name">
                    <div class="title">Grupo Sabor Paraense</div>
                    <div class="sub">Trabalhe conosco</div>
                </div>
            </div>
            <div class="gsp-sep" aria-hidden="true"></div>
            <div class="gsp-subbrand">
                <img class="gsp-submark" src="/imagens/logo-docemango.jpg" alt="Doce Mango" />
                <div class="gsp-name">
                    <div class="title" style="font-size: 1.02rem;">Doce Mango</div>
                    <div class="sub">Faz parte do grupo</div>
                </div>
            </div>
            <div class="gsp-sep" aria-hidden="true"></div>
            <div class="gsp-subbrand">
                <img class="gsp-submark" src="/imagens/logo-docenorte.jpg" alt="Doce Norte" />
                <div class="gsp-name">
                    <div class="title" style="font-size: 1.02rem;">Doce Norte</div>
                    <div class="sub">Faz parte do grupo</div>
                </div>
            </div>
        </div>
    </div>

    @if(request()->query('ok'))
        <div class="alert alert-success shadow-sm d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-check-circle-fill"></i>
            <span>Candidatura enviada com sucesso. Obrigado!</span>
        </div>
    @endif

    @if(session('candidatura_parcial'))
        <div class="alert alert-warning shadow-sm d-flex align-items-start gap-2">
            <i class="bi bi-exclamation-triangle-fill mt-1"></i>
            <span>{{ session('candidatura_parcial') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger shadow-sm">
            <div class="fw-semibold mb-2 d-flex align-items-center gap-2">
                <i class="bi bi-x-circle-fill"></i> Verifique os campos e tente novamente.
            </div>
            <ul class="mb-0 small">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(!count($items))
        <div class="alert alert-light shadow border-0 text-dark rounded-4 py-4 text-center">
            <i class="bi bi-inboxes fs-1 text-secondary d-block mb-2"></i>
            Nenhuma vaga cadastrada no momento.
        </div>
    @else
        <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
            <div>
                <h2 class="section-title mb-1">Vagas disponíveis</h2>
                <p class="text-muted-light small mb-0">Ao abrir <strong>Ver detalhes</strong>, a vaga vai para o centro em destaque; as outras ficam recolhidas e suaves ao fundo. Use <strong>Esconder</strong> ou a tecla <strong>Esc</strong> para voltar ao normal.</p>
            </div>
        </div>

        <div class="row g-4 vagas-grid">
            @foreach($items as $v)
                @php
                    $status = strtolower((string) ($v->status ?? ''));
                    $isOpen = $status === 'aberta';
                    $badgeClass = $isOpen ? 'bg-success' : ($status === 'pausada' ? 'bg-warning text-dark' : 'bg-secondary');
                    $ico = $rhPublicoIconeVaga($v);
                    $toneClass = $ico['tone'] !== '' ? ' vaga-tone-' . $ico['tone'] : '';
                    $excerpt = \Illuminate\Support\Str::limit((string) ($v->descricao ?? ''), 160);
                @endphp
                <div class="col-12 col-md-6 col-xl-4 vaga-col">
                    <article class="vaga-card-ui" id="vaga-{{ $v->slug }}">
                        <div class="vaga-card-ui__head">
                            <div class="vaga-card-ui__icon{{ $toneClass }}" title="{{ $ico['tag'] }}" aria-hidden="true">
                                <i class="bi {{ $ico['icon'] }}"></i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <p class="vaga-card-ui__title">{{ $v->titulo }}</p>
                                <span class="vaga-card-ui__tag">{{ $ico['tag'] }}</span>
                                <div class="mt-2">
                                    <span class="badge {{ $badgeClass }} rounded-pill">{{ strtoupper($status ?: '—') }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="vaga-card-ui__body">
                            <div class="vaga-card-ui__meta">
                                @if(!empty($v->unidade))
                                    <div><i class="bi bi-geo-alt me-1"></i>{{ $v->unidade }}</div>
                                @endif
                                @if(!empty($v->setor))
                                    <div><i class="bi bi-diagram-3 me-1"></i>{{ $v->setor }}</div>
                                @endif
                                @if(!empty($v->horarios_trabalho))
                                    <div><i class="bi bi-clock me-1"></i>{{ \Illuminate\Support\Str::limit($v->horarios_trabalho, 80) }}</div>
                                @endif
                            </div>
                            @if($excerpt !== '')
                                <p class="vaga-card-ui__excerpt mb-0">{{ $excerpt }}</p>
                            @endif

                            <div class="collapse vaga-detalhe-full" id="detail-vaga-{{ $v->id }}">
                                <h4>Descrição</h4>
                                <div class="block-txt">{{ $v->descricao }}</div>
                                @if(!empty($v->requisitos))
                                    <h4>Requisitos</h4>
                                    <div class="block-txt">{{ $v->requisitos }}</div>
                                @endif
                                @if(!empty($v->beneficios))
                                    <h4>Benefícios</h4>
                                    <div class="block-txt">{{ $v->beneficios }}</div>
                                @endif
                                @if(!empty($v->horarios_trabalho))
                                    <h4>Horários</h4>
                                    <div class="block-txt">{{ $v->horarios_trabalho }}</div>
                                @endif
                                @if(!$isOpen)
                                    <div class="alert alert-warning small mb-0 mt-2 py-2">Esta vaga não está aceitando novas candidaturas.</div>
                                @endif
                            </div>
                        </div>
                        <div class="vaga-card-ui__foot mt-auto">
                            <button
                                type="button"
                                class="btn btn-outline-secondary btn-sm w-100 btn-vaga-detalhe-toggle"
                                data-bs-toggle="collapse"
                                data-bs-target="#detail-vaga-{{ $v->id }}"
                                aria-expanded="false"
                                aria-controls="detail-vaga-{{ $v->id }}"
                            >
                                <span class="btn-vaga-detalhe-label"><i class="bi bi-file-text me-1"></i> Ver detalhes</span>
                            </button>
                            <button
                                type="button"
                                class="btn btn-primary btn-candidatura-vaga w-100"
                                data-bs-toggle="modal"
                                data-bs-target="#modalCandidaturaRh"
                                data-slug="{{ $v->slug }}"
                                data-vaga-id="{{ $v->id }}"
                                data-titulo="{{ $v->titulo }}"
                                @if(!$isOpen) disabled @endif
                            >
                                <i class="bi bi-person-plus me-1"></i> Candidatar-se
                            </button>
                        </div>
                    </article>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Formulário só no modal --}}
    <div class="modal fade" id="modalCandidaturaRh" tabindex="-1" aria-labelledby="modalCandidaturaRhTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title" id="modalCandidaturaRhTitle">Candidatar-se</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div id="candidatura-erros-ajax" class="alert alert-danger d-none" role="alert"></div>
                    <p class="text-muted small mb-3">Campos com <strong>*</strong> são obrigatórios.</p>
                    @include('rh.publico._form-candidatura-rh', [
                        'vaga' => $vaga ?? null,
                        'vagasAbertas' => $vagasAbertas ?? collect(),
                        'vagaBloqueada' => $vagaBloqueada ?? false,
                    ])
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@if ($errors->any())
<script>
document.addEventListener('DOMContentLoaded', function () {
    var el = document.getElementById('modalCandidaturaRh');
    if (el && typeof bootstrap !== 'undefined') {
        bootstrap.Modal.getOrCreateInstance(el).show();
    }
});
</script>
@endif
<script>
(function () {
    var modalEl = document.getElementById('modalCandidaturaRh');
    var form = document.getElementById('formCandidaturaRh');
    var titleEl = document.getElementById('modalCandidaturaRhTitle');
    if (modalEl) {
        modalEl.addEventListener('show.bs.modal', function () {
            document.body.classList.remove('vaga-spotlight-on');
            document.querySelectorAll('.vaga-col').forEach(function (c) {
                c.classList.remove('is-vaga-focus');
            });
        });
        modalEl.addEventListener('hidden.bs.modal', function () {
            syncVagaSpotlight();
        });
    }
    if (modalEl && form && titleEl) {
        modalEl.addEventListener('show.bs.modal', function (ev) {
            var trigger = ev.relatedTarget;
            if (!trigger || !trigger.classList.contains('btn-candidatura-vaga')) return;
            var slug = trigger.getAttribute('data-slug') || '';
            var vid = trigger.getAttribute('data-vaga-id') || '';
            var titulo = trigger.getAttribute('data-titulo') || '';
            if (slug) {
                form.action = '/vagas/' + encodeURIComponent(slug) + '/candidatar';
            }
            titleEl.textContent = titulo ? 'Candidatar-se — ' + titulo : 'Candidatar-se';
            var hid = form.querySelector('#rhCandidaturaVagaId');
            if (hid && vid) hid.value = String(vid);
        });
    }

    function syncVagaSpotlight() {
        var open = document.querySelector('.collapse.vaga-detalhe-full.show');
        document.querySelectorAll('.vaga-col').forEach(function (cell) {
            cell.classList.remove('is-vaga-focus');
        });
        if (!open) {
            document.body.classList.remove('vaga-spotlight-on');
            return;
        }
        var card = open.closest('.vaga-card-ui');
        var cell = card ? card.closest('.vaga-col') : null;
        document.body.classList.add('vaga-spotlight-on');
        if (cell) {
            cell.classList.add('is-vaga-focus');
        }
    }

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (!document.body.classList.contains('vaga-spotlight-on')) return;
        var open = document.querySelector('.collapse.vaga-detalhe-full.show');
        if (open && typeof bootstrap !== 'undefined') {
            var inst = bootstrap.Collapse.getInstance(open);
            if (inst) inst.hide();
        }
    });

    document.querySelectorAll('.collapse.vaga-detalhe-full').forEach(function (col) {
        col.addEventListener('show.bs.collapse', function (ev) {
            if (ev.target !== col) return;
            document.querySelectorAll('.collapse.vaga-detalhe-full').forEach(function (other) {
                if (other === col) return;
                if (!other.classList.contains('show')) return;
                var inst = bootstrap.Collapse.getInstance(other);
                if (inst) inst.hide();
            });
        });
    });

    document.querySelectorAll('.collapse.vaga-detalhe-full').forEach(function (col) {
        var sel = '#' + col.id;
        var btn = document.querySelector('.btn-vaga-detalhe-toggle[data-bs-target="' + sel + '"]');
        if (!btn) return;
        var label = btn.querySelector('.btn-vaga-detalhe-label');
        if (!label) return;
        col.addEventListener('shown.bs.collapse', function () {
            btn.setAttribute('aria-expanded', 'true');
            label.innerHTML = '<i class="bi bi-chevron-up me-1"></i> Esconder';
            syncVagaSpotlight();
        });
        col.addEventListener('hidden.bs.collapse', function () {
            btn.setAttribute('aria-expanded', 'false');
            label.innerHTML = '<i class="bi bi-file-text me-1"></i> Ver detalhes';
            syncVagaSpotlight();
        });
    });

    function openVagaFromHash() {
        var raw = window.location.hash.replace(/^#/, '');
        if (!raw) return;
        var id = raw.indexOf('vaga-') === 0 ? raw : ('vaga-' + raw);
        var card = document.getElementById(id);
        if (!card) return;
        var col = card.querySelector('.collapse.vaga-detalhe-full');
        if (col && typeof bootstrap !== 'undefined') {
            bootstrap.Collapse.getOrCreateInstance(col, { toggle: false }).show();
        }
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
                        try {
                            var modalEl = document.getElementById("modalCandidaturaRh");
                            if (modalEl && typeof bootstrap !== "undefined") {
                                bootstrap.Modal.getOrCreateInstance(modalEl).show();
                            }
                        } catch (_) {}
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
                w.className = "alert alert-warning shadow-sm d-flex align-items-start gap-2";
                w.setAttribute("role", "alert");
                w.innerHTML = '<i class="bi bi-exclamation-triangle-fill mt-1"></i><span></span>';
                w.querySelector("span").textContent = msg;
                var okEl = document.querySelector(".alert.alert-success");
                if (okEl && okEl.parentNode) okEl.parentNode.insertBefore(w, okEl.nextSibling);
            }
        }
    } catch (_) {}
})();
</script>
</body>
</html>
