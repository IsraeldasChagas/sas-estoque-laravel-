<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Kanban Administrativo — SAS Estoque</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <style>
        :root { --kanban-bg: #f4f6f8; --kanban-col: #eceff1; --kanban-border: #cfd8dc; --accent: #1565c0; }
        body { background: var(--kanban-bg); font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        .kanban-header { background: #fff; border-bottom: 1px solid var(--kanban-border); }
        .kanban-board-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; padding-bottom: 1rem; }
        .kanban-board { display: flex; gap: 1rem; min-height: 60vh; align-items: flex-start; }
        .kanban-col { flex: 1 1 0; min-width: 240px; background: var(--kanban-col); border-radius: 12px; border: 1px solid var(--kanban-border); display: flex; flex-direction: column; max-height: calc(100vh - 220px); }
        .kanban-col-head { padding: 0.65rem 0.85rem; font-weight: 600; border-bottom: 1px solid var(--kanban-border); background: #fff; border-radius: 12px 12px 0 0; display: flex; justify-content: space-between; align-items: center; }
        .kanban-col-body { padding: 0.5rem; overflow-y: auto; flex: 1; min-height: 120px; }
        .kanban-card { background: #fff; border-radius: 10px; border: 1px solid #e0e0e0; padding: 0.65rem 0.75rem; margin-bottom: 0.5rem; cursor: grab; box-shadow: 0 1px 2px rgba(0,0,0,.04); transition: box-shadow .15s; }
        .kanban-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .kanban-card--baixa { border-left: 3px solid #90a4ae; }
        .kanban-card--media { border-left: 3px solid #fb8c00; }
        .kanban-card--alta { border-left: 3px solid #c62828; }
        .kanban-card--atrasada { outline: 1px solid #c62828; background: #fff8f8; }
        .badge-atrasada { font-size: 0.65rem; }
        .empty-kanban { max-width: 520px; }
    </style>
</head>
<body>
<header class="kanban-header py-3 mb-3">
    <div class="container-fluid px-4 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h1 class="h4 mb-0">Kanban Administrativo</h1>
            <small class="text-muted">Grupo Sabor Paraense — SAS Estoque</small>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary btn-sm" id="kbBladeNova">Nova tarefa</button>
            <a href="/dashboard" class="btn btn-outline-secondary btn-sm">Voltar</a>
        </div>
    </div>
</header>

<div class="container-fluid px-4">
    <div id="kbBladeAlert" class="alert alert-warning d-none" role="alert"></div>

    <form class="row g-2 align-items-end mb-3" id="kbBladeFiltros">
        <div class="col-md-3">
            <label class="form-label small mb-0">Unidade</label>
            <select class="form-select form-select-sm" name="unidade_id" id="kbFUnidade">
                <option value="">Todas</option>
                @foreach ($unidades as $u)
                    <option value="{{ $u->id }}">{{ $u->nome }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small mb-0">Setor</label>
            <select class="form-select form-select-sm" id="kbFSetor">
                <option value="">Todos</option>
                @foreach (['Administrativo','Financeiro','Compras','RH','Marketing','Estoque','Manutenção','Geral'] as $s)
                    <option value="{{ $s }}">{{ $s }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-0">Prioridade</label>
            <select class="form-select form-select-sm" id="kbFPrioridade">
                <option value="">Todas</option>
                <option value="baixa">Baixa</option>
                <option value="media">Média</option>
                <option value="alta">Alta</option>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-sm btn-dark w-100">Aplicar</button>
        </div>
    </form>

    <div id="kbBladeEmpty" class="alert alert-light border text-center empty-kanban mx-auto d-none">
        Nenhuma tarefa encontrada. Crie uma nova tarefa ou ajuste os filtros.
    </div>

    <div class="kanban-board-wrap">
        <div class="kanban-board" id="kbBladeBoard">
            @php
                $cols = [
                    'planejamento' => 'Planejamento',
                    'a_fazer' => 'A Fazer',
                    'em_execucao' => 'Em Execução',
                    'aguardando' => 'Aguardando',
                    'finalizado' => 'Finalizado',
                ];
            @endphp
            @foreach ($cols as $key => $label)
                <div class="kanban-col" data-status="{{ $key }}">
                    <div class="kanban-col-head">
                        <span>{{ $label }}</span>
                        <span class="badge bg-secondary kb-count" data-col="{{ $key }}">0</span>
                    </div>
                    <div class="kanban-col-body kb-drop" data-status="{{ $key }}" id="kbDrop-{{ $key }}"></div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="kbBladeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="kbBladeModalTitle">Tarefa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <form id="kbBladeForm">
                    <input type="hidden" name="id" id="kbFormId" value="">
                    <div class="mb-2">
                        <label class="form-label">Título *</label>
                        <input type="text" class="form-control" name="titulo" id="kbFormTitulo" required maxlength="255">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Descrição</label>
                        <textarea class="form-control" name="descricao" id="kbFormDescricao" rows="2"></textarea>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">Unidade *</label>
                            <select class="form-select" name="unidade_id" id="kbFormUnidade" required>
                                <option value="">Selecione</option>
                                @foreach ($unidades as $u)
                                    <option value="{{ $u->id }}">{{ $u->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Setor *</label>
                            <select class="form-select" name="setor" id="kbFormSetor" required>
                                @foreach (['Administrativo','Financeiro','Compras','RH','Marketing','Estoque','Manutenção','Geral'] as $s)
                                    <option value="{{ $s }}">{{ $s }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mt-1">
                        <div class="col-md-6">
                            <label class="form-label">Responsável</label>
                            <input type="text" class="form-control" name="responsavel" id="kbFormResp" maxlength="255">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Prioridade *</label>
                            <select class="form-select" name="prioridade" id="kbFormPri" required>
                                <option value="baixa">Baixa</option>
                                <option value="media" selected>Média</option>
                                <option value="alta">Alta</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status *</label>
                            <select class="form-select" name="status" id="kbFormStatus" required>
                                @foreach ($cols as $k => $lbl)
                                    <option value="{{ $k }}">{{ $lbl }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mt-1">
                        <div class="col-md-6">
                            <label class="form-label">Prazo</label>
                            <input type="date" class="form-control" name="prazo" id="kbFormPrazo">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Observações</label>
                            <textarea class="form-control" name="observacoes" id="kbFormObs" rows="2"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-outline-danger d-none" id="kbFormExcluir">Excluir</button>
                <div>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="kbFormSalvar">Salvar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js" crossorigin="anonymous"></script>
<script>
(function () {
    const API = @json(rtrim($apiBase, '/'));
    const STATUSES = ['planejamento','a_fazer','em_execucao','aguardando','finalizado'];

    function getUserFromStorage() {
        try {
            const raw = localStorage.getItem('sas-estoque-user');
            if (!raw) return null;
            return JSON.parse(raw);
        } catch (e) { return null; }
    }

    function headersJson() {
        const u = getUserFromStorage();
        const h = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
        if (u && u.id) h['X-Usuario-Id'] = String(u.id);
        const t = document.querySelector('meta[name="csrf-token"]');
        if (t && t.content) h['X-CSRF-TOKEN'] = t.content;
        return h;
    }

    function esc(s) {
        if (s == null) return '';
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    function hojeISO() { return new Date().toISOString().slice(0, 10); }

    function cardAtrasada(t) {
        if (!t.prazo || t.status === 'finalizado') return false;
        return String(t.prazo).slice(0, 10) < hojeISO();
    }

    let tasks = [];
    let sortables = [];

    async function api(path, opts = {}) {
        const res = await fetch(API + path, { ...opts, headers: { ...headersJson(), ...(opts.headers || {}) } });
        const text = await res.text();
        let data = {};
        try { data = text ? JSON.parse(text) : {}; } catch (e) { data = { error: text }; }
        if (!res.ok) throw new Error(data.error || data.message || ('Erro ' + res.status));
        return data;
    }

    function render() {
        STATUSES.forEach(st => {
            const el = document.getElementById('kbDrop-' + st);
            if (el) el.innerHTML = '';
        });
        let total = 0;
        tasks.forEach(t => {
            total++;
            const el = document.getElementById('kbDrop-' + t.status);
            if (!el) return;
            const atraso = cardAtrasada(t);
            const pri = t.prioridade || 'media';
            const un = (t.unidade && t.unidade.nome) ? t.unidade.nome : ('#' + t.unidade_id);
            const div = document.createElement('div');
            div.className = 'kanban-card kanban-card--' + esc(pri) + (atraso ? ' kanban-card--atrasada' : '');
            div.dataset.taskId = String(t.id);
            div.innerHTML =
                '<div class="fw-semibold small">' + esc(t.titulo) + '</div>' +
                (atraso ? '<span class="badge text-bg-danger badge-atrasada">Atrasada</span> ' : '') +
                '<div class="small text-muted mt-1">' + esc(un) + ' · ' + esc(t.setor) + '</div>' +
                '<div class="small text-muted">' + (t.responsavel ? esc(t.responsavel) : '—') + '</div>' +
                '<div class="small mt-1"><span class="badge text-bg-light border">' + esc(pri) + '</span> ' +
                (t.prazo ? '<span class="text-muted">Prazo: ' + esc(String(t.prazo).slice(0,10)) + '</span>' : '') + '</div>';
            div.addEventListener('click', () => openModal(t));
            el.appendChild(div);
        });
        STATUSES.forEach(st => {
            const c = document.querySelector('.kb-count[data-col="' + st + '"]');
            const n = document.querySelectorAll('#kbDrop-' + st + ' .kanban-card').length;
            if (c) c.textContent = String(n);
        });
        document.getElementById('kbBladeEmpty').classList.toggle('d-none', total > 0);
    }

    function destroySortables() {
        sortables.forEach(s => { try { s.destroy(); } catch(e) {} });
        sortables = [];
    }

    function initSortables() {
        destroySortables();
        if (typeof Sortable === 'undefined') return;
        document.querySelectorAll('.kb-drop').forEach(drop => {
            const st = drop.getAttribute('data-status');
            const s = Sortable.create(drop, {
                group: 'kanban',
                animation: 150,
                onEnd: async (evt) => {
                    const id = evt.item.dataset.taskId;
                    const newStatus = evt.to.getAttribute('data-status');
                    const oldStatus = evt.from.getAttribute('data-status');
                    if (!id || !newStatus || oldStatus === newStatus) return;
                    try {
                        await api('/kanban-tasks/' + id + '/status', { method: 'PATCH', body: JSON.stringify({ status: newStatus }) });
                        const t = tasks.find(x => String(x.id) === String(id));
                        if (t) t.status = newStatus;
                    } catch (e) {
                        alert(e.message || 'Erro ao mover');
                        await loadTasks();
                    }
                }
            });
            sortables.push(s);
        });
    }

    async function loadTasks() {
        const p = new URLSearchParams();
        const u = document.getElementById('kbFUnidade').value;
        const se = document.getElementById('kbFSetor').value;
        const pr = document.getElementById('kbFPrioridade').value;
        if (u) p.set('unidade_id', u);
        if (se) p.set('setor', se);
        if (pr) p.set('prioridade', pr);
        const q = p.toString();
        tasks = await api('/kanban-tasks' + (q ? ('?' + q) : ''));
        if (!Array.isArray(tasks)) tasks = [];
        render();
        initSortables();
    }

    const modalEl = document.getElementById('kbBladeModal');
    const modal = new bootstrap.Modal(modalEl);

    function openModal(t) {
        document.getElementById('kbBladeModalTitle').textContent = t ? 'Editar tarefa' : 'Nova tarefa';
        document.getElementById('kbFormId').value = t ? t.id : '';
        document.getElementById('kbFormTitulo').value = t ? (t.titulo || '') : '';
        document.getElementById('kbFormDescricao').value = t ? (t.descricao || '') : '';
        document.getElementById('kbFormUnidade').value = t ? String(t.unidade_id || '') : '';
        document.getElementById('kbFormSetor').value = t ? (t.setor || 'Administrativo') : 'Administrativo';
        document.getElementById('kbFormResp').value = t ? (t.responsavel || '') : '';
        document.getElementById('kbFormPri').value = t ? (t.prioridade || 'media') : 'media';
        document.getElementById('kbFormStatus').value = t ? (t.status || 'planejamento') : 'planejamento';
        document.getElementById('kbFormPrazo').value = t && t.prazo ? String(t.prazo).slice(0, 10) : '';
        document.getElementById('kbFormObs').value = t ? (t.observacoes || '') : '';
        document.getElementById('kbFormExcluir').classList.toggle('d-none', !t);
        modal.show();
    }

    document.getElementById('kbBladeNova').addEventListener('click', () => openModal(null));
    document.getElementById('kbBladeFiltros').addEventListener('submit', (e) => { e.preventDefault(); loadTasks().catch(err => alert(err.message)); });

    document.getElementById('kbFormSalvar').addEventListener('click', async () => {
        const id = document.getElementById('kbFormId').value;
        const body = {
            titulo: document.getElementById('kbFormTitulo').value.trim(),
            descricao: document.getElementById('kbFormDescricao').value || null,
            unidade_id: parseInt(document.getElementById('kbFormUnidade').value, 10),
            setor: document.getElementById('kbFormSetor').value,
            responsavel: document.getElementById('kbFormResp').value.trim() || null,
            prioridade: document.getElementById('kbFormPri').value,
            status: document.getElementById('kbFormStatus').value,
            prazo: document.getElementById('kbFormPrazo').value || null,
            observacoes: document.getElementById('kbFormObs').value || null,
        };
        try {
            if (id) await api('/kanban-tasks/' + id, { method: 'PUT', body: JSON.stringify(body) });
            else await api('/kanban-tasks', { method: 'POST', body: JSON.stringify(body) });
            modal.hide();
            await loadTasks();
        } catch (e) { alert(e.message || 'Erro ao salvar'); }
    });

    document.getElementById('kbFormExcluir').addEventListener('click', async () => {
        const id = document.getElementById('kbFormId').value;
        if (!id || !confirm('Excluir esta tarefa?')) return;
        try {
            await api('/kanban-tasks/' + id, { method: 'DELETE' });
            modal.hide();
            await loadTasks();
        } catch (e) { alert(e.message || 'Erro ao excluir'); }
    });

    const u = getUserFromStorage();
    if (!u || !u.id) {
        const a = document.getElementById('kbBladeAlert');
        a.classList.remove('d-none');
        a.textContent = 'Faça login no SAS Estoque neste navegador (mesmo dispositivo) para carregar e salvar tarefas. A API exige o cabeçalho X-Usuario-Id.';
    } else {
        loadTasks().catch(err => {
            const a = document.getElementById('kbBladeAlert');
            a.classList.remove('d-none');
            a.classList.replace('alert-warning', 'alert-danger');
            a.textContent = err.message || 'Erro ao carregar tarefas.';
        });
    }
})();
</script>
</body>
</html>
