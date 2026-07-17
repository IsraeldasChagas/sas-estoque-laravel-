/**
 * Orçamentos — módulo funcional.
 * Persistência exclusiva nas tabelas de orçamentos; não movimenta estoque, financeiro ou RH.
 */
(function () {
  "use strict";

  const SECTIONS = {
    dashboard: "orcamentosDashboard", novo: "orcamentosNovo", lista: "orcamentosLista",
    proposta: "orcamentosProposta", modelos: "orcamentosModelos", clientes: "orcamentosClientes",
    itens: "orcamentosItens", configuracoes: "orcamentosConfiguracoes", relatorios: "orcamentosRelatorios",
  };
  const STEPS = ["Cliente", "Tipo", "Produtos", "Equipe", "Equipamentos", "Consumo", "Frete", "Financeiro"];
  const TYPE_LABELS = {
    produto: "Produto", servico: "Serviço", equipamento: "Equipamento", evento: "Evento",
    buffet: "Buffet", mesa: "Mesa", locacao: "Locação", mao_obra: "Mão de obra", outro: "Outro",
  };
  const STATUS_LABELS = {
    rascunho: "Rascunho", pendente: "Pendente", em_negociacao: "Em negociação",
    aprovado: "Aprovado", recusado: "Recusado", convertido: "Convertido",
  };
  const STATUS_CLASS = { aprovado: "success", convertido: "success", pendente: "warning", em_negociacao: "warning", recusado: "danger" };

  const state = {
    wizardStep: 1,
    selectedId: null,
    draft: blankDraft(),
    clients: [],
    budgets: [],
    dashboard: null,
    activeItemTab: "produto_servico",
  };

  function today() {
    return new Date().toISOString().slice(0, 10);
  }

  function plusDays(days) {
    const date = new Date();
    date.setDate(date.getDate() + days);
    return date.toISOString().slice(0, 10);
  }

  function blankDraft() {
    return {
      id: null,
      codigo: null,
      status: "rascunho",
      etapa_wizard: 1,
      data_orcamento: today(),
      validade: plusDays(7),
      tipo: "evento",
      cliente: { id: null, nome: "", telefone: "", whatsapp: "", instagram: "", email: "", documento: "", empresa: "", origem: "", observacoes: "" },
      linhas: [],
      frete: { tipo: "sem_frete", valor: 0, distancia_km: "", observacoes: "" },
      financeiro: { desconto_percentual: 0, desconto_valor: 0, acrescimo_valor: 0, forma_pagamento: "pix", observacoes: "" },
      observacoes: "",
    };
  }

  function root(id) { return document.getElementById(id); }
  function esc(value) {
    return String(value == null ? "" : value).replace(/&/g, "&amp;").replace(/</g, "&lt;")
      .replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#39;");
  }
  function money(value) {
    return Number(value || 0).toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
  }
  function number(value) {
    const parsed = Number(String(value == null ? "" : value).replace(",", "."));
    return Number.isFinite(parsed) ? parsed : 0;
  }
  function toast(message, type) {
    if (typeof window.showToast === "function") window.showToast(message, type || "info");
  }
  function go(section) {
    if (typeof window.navigateTo === "function") window.navigateTo(section);
  }
  async function orcFetch(path, options) {
    if (typeof window.fetchJSON !== "function") throw new Error("Conexão com a API indisponível.");
    return window.fetchJSON(path, options || {});
  }
  async function busy(button, callback) {
    const original = button ? button.innerHTML : "";
    if (button) { button.disabled = true; button.innerHTML = "Aguarde…"; }
    try { return await callback(); }
    finally { if (button) { button.disabled = false; button.innerHTML = original; } }
  }
  function query(params) {
    const qs = new URLSearchParams();
    Object.entries(params || {}).forEach(([key, value]) => { if (value !== "" && value != null) qs.set(key, value); });
    const value = qs.toString();
    return value ? `?${value}` : "";
  }
  function shell(content) { return `<div class="orc-shell">${content}</div>`; }
  function header(title, subtitle, icon, actions, crumb) {
    return `<header class="orc-head"><div>
      <div class="orc-breadcrumb"><button type="button" data-orc-go="${SECTIONS.dashboard}">Orçamentos</button> / ${esc(crumb || title)}</div>
      <div class="orc-head__title"><span class="orc-head__icon" aria-hidden="true">${icon || "□"}</span>
      <div><h2>${esc(title)}</h2><p>${esc(subtitle || "")}</p></div></div></div>
      <div class="orc-actions">${actions || ""}</div></header>`;
  }
  function loading(target) {
    target.innerHTML = shell(`<div class="orc-skeleton"></div><div class="orc-kpis"><div class="orc-skeleton"></div><div class="orc-skeleton"></div><div class="orc-skeleton"></div></div>`);
  }
  function errorView(target, error, retry) {
    target.innerHTML = shell(`<div class="orc-notice"><strong>Não foi possível carregar.</strong><br>${esc(error.message || error)}</div>
      <button class="orc-btn orc-btn--primary" id="orcRetry">Tentar novamente</button>`);
    root("orcRetry")?.addEventListener("click", retry);
  }
  function bindNavigation(container) {
    container.querySelectorAll("[data-orc-go]").forEach(button => button.addEventListener("click", () => go(button.dataset.orcGo)));
  }
  function badge(status) {
    const key = String(status || "rascunho").toLowerCase();
    const cls = STATUS_CLASS[key] || "";
    return `<span class="orc-badge ${cls ? `orc-badge--${cls}` : ""}">${esc(STATUS_LABELS[key] || status)}</span>`;
  }
  function kpi(label, value, icon, detail, target) {
    return `<article class="orc-kpi" ${target ? `data-orc-go="${target}"` : ""}>
      <div class="orc-kpi__top"><span>${esc(label)}</span><span class="orc-kpi__icon">${icon}</span></div>
      <div class="orc-kpi__value">${value}</div><div class="orc-kpi__trend">${esc(detail || "")}</div></article>`;
  }
  function typeLabel(type) { return TYPE_LABELS[type] || type || "—"; }
  function displayDate(value) {
    if (!value) return "—";
    const parts = String(value).slice(0, 10).split("-");
    return parts.length === 3 ? `${parts[2]}/${parts[1]}/${parts[0]}` : value;
  }

  function lineSubtotal(line) {
    const qty = Math.max(0, number(line.quantidade));
    const unit = Math.max(0, number(line.valor_unitario));
    const discount = Math.min(100, Math.max(0, number(line.desconto_percentual)));
    let subtotal;
    if (line.tipo_linha === "equipe") subtotal = qty * (number(line.valor_evento) > 0 ? number(line.valor_evento) : number(line.horas) * unit);
    else if (line.tipo_linha === "equipamento") subtotal = qty * Math.max(1, number(line.dias)) * unit;
    else subtotal = qty * unit;
    return Math.max(0, subtotal * (1 - discount / 100));
  }

  function totals() {
    const result = { produto_servico: 0, equipe: 0, equipamento: 0, consumo: 0 };
    state.draft.linhas.forEach(line => { result[line.tipo_linha] = (result[line.tipo_linha] || 0) + lineSubtotal(line); });
    const freight = number(state.draft.frete.valor);
    const base = Object.values(result).reduce((sum, value) => sum + value, 0) + freight;
    const discount = base * number(state.draft.financeiro.desconto_percentual) / 100 + number(state.draft.financeiro.desconto_valor);
    const total = Math.max(0, base - discount + number(state.draft.financeiro.acrescimo_valor));
    return { ...result, frete: freight, base, desconto: discount, total };
  }

  function payload(finalize) {
    return {
      cliente: state.draft.cliente,
      tipo: state.draft.tipo,
      status: finalize ? "pendente" : (state.draft.status || "rascunho"),
      data_orcamento: state.draft.data_orcamento || today(),
      validade: state.draft.validade || null,
      linhas: state.draft.linhas,
      frete: state.draft.frete,
      financeiro: state.draft.financeiro,
      observacoes: state.draft.observacoes || null,
      etapa_wizard: state.wizardStep,
    };
  }

  function hydrate(data, duplicate) {
    state.draft = blankDraft();
    state.draft.id = duplicate ? null : data.id;
    state.draft.codigo = duplicate ? null : data.codigo;
    state.draft.status = duplicate ? "rascunho" : data.status;
    state.draft.etapa_wizard = duplicate ? 1 : (data.etapa_wizard || 1);
    state.draft.data_orcamento = duplicate ? today() : (data.data_orcamento || today());
    state.draft.validade = duplicate ? plusDays(7) : (data.validade || plusDays(7));
    state.draft.tipo = data.tipo || "evento";
    if (data.cliente) {
      state.draft.cliente = {
        id: duplicate ? null : data.cliente.id, nome: data.cliente.nome || "", telefone: data.cliente.telefone || "",
        whatsapp: data.cliente.whatsapp || "", instagram: data.cliente.instagram || "", email: data.cliente.email || "",
        documento: data.cliente.documento || "", empresa: data.cliente.empresa || "", origem: data.cliente.origem || "",
        observacoes: data.cliente.observacoes || "",
      };
    }
    state.draft.linhas = (data.linhas || []).map(line => ({
      tipo_linha: line.tipo_linha, descricao: line.descricao, quantidade: number(line.quantidade),
      unidade_medida: line.unidade_medida || "", horas: number(line.horas), dias: number(line.dias),
      valor_unitario: number(line.valor_unitario), desconto_percentual: number(line.desconto_percentual),
      valor_evento: number(line.valor_evento), custo_unitario: number(line.custo_unitario),
    }));
    state.draft.frete = { ...state.draft.frete, ...(data.frete || {}) };
    state.draft.financeiro = { ...state.draft.financeiro, ...(data.financeiro || {}) };
    state.draft.observacoes = data.observacoes || "";
    state.selectedId = duplicate ? null : data.id;
    state.wizardStep = duplicate ? 1 : Math.min(8, Math.max(1, data.etapa_wizard || 1));
  }

  async function saveDraft(finalize, button) {
    if (!state.draft.cliente.nome.trim()) {
      state.wizardStep = 1;
      renderWizard();
      throw new Error("Informe o nome do cliente.");
    }
    const data = await busy(button, () => orcFetch(
      state.draft.id ? `/orcamentos/${state.draft.id}` : "/orcamentos",
      { method: state.draft.id ? "PUT" : "POST", body: JSON.stringify(payload(finalize)) }
    ));
    hydrate(data, false);
    return data;
  }

  async function loadDashboard() {
    const target = root("orcamentosDashboardRoot");
    if (!target) return;
    loading(target);
    try {
      const data = await orcFetch("/orcamentos/dashboard");
      state.dashboard = data;
      const r = data.resumo || {};
      const evolution = data.evolucao || [];
      const max = Math.max(1, ...evolution.map(item => number(item.valor)));
      target.innerHTML = shell(`
        ${header("Dashboard de Orçamentos", "Indicadores atualizados com os orçamentos cadastrados.", "▦",
          `<button class="orc-btn orc-btn--primary" id="orcNewDashboard">＋ Novo Orçamento</button>`, "Dashboard")}
        <div class="orc-kpis">
          ${kpi("Total de orçamentos", String(r.total_orcamentos || 0), "▤", "Registros cadastrados", SECTIONS.lista)}
          ${kpi("Pendentes", String(r.pendentes || 0), "◷", "Aguardando retorno", SECTIONS.lista)}
          ${kpi("Aprovados", String(r.aprovados || 0), "✓", `${number(r.conversao_percentual).toFixed(1)}% de conversão`, SECTIONS.lista)}
          ${kpi("Recusados", String(r.recusados || 0), "×", "Propostas recusadas", SECTIONS.lista)}
          ${kpi("Em negociação", String(r.em_negociacao || 0), "↔", "Em acompanhamento", SECTIONS.lista)}
          ${kpi("Convertidos", String(r.convertidos || 0), "◆", "Orçamentos convertidos", SECTIONS.lista)}
          ${kpi("Valor total", money(r.valor_total), "R$", "Total orçado", SECTIONS.relatorios)}
          ${kpi("Ticket médio", money(r.ticket_medio), "∅", "Média por orçamento", SECTIONS.relatorios)}
        </div>
        <div class="orc-grid-2">
          <section class="orc-card"><div class="orc-card__head"><h3>Evolução dos valores</h3>${badge("aprovado")}</div>
            <div class="orc-chart">${evolution.length ? evolution.map(item => `<div class="orc-chart__bar" style="--h:${Math.max(8, number(item.valor) / max * 100)}%"><span>${esc(String(item.mes).slice(5))}/${esc(String(item.mes).slice(2,4))}</span></div>`).join("") : '<div class="orc-muted">Cadastre orçamentos para formar o gráfico.</div>'}</div>
          </section>
          <section class="orc-card"><div class="orc-card__head"><h3>Top clientes</h3><button class="orc-btn orc-btn--sm" data-orc-go="${SECTIONS.clientes}">Ver clientes</button></div>
            <div class="orc-list">${(data.top_clientes || []).map((c, i) => `<div class="orc-list-row"><div class="orc-avatar">${i + 1}</div><strong>${esc(c.nome)}</strong><span class="orc-money">${money(c.total)}</span></div>`).join("") || '<div class="orc-muted">Nenhum cliente ainda.</div>'}</div>
          </section>
        </div>
        <section class="orc-card"><div class="orc-card__head"><h3>Últimos orçamentos</h3><button class="orc-btn orc-btn--sm" data-orc-go="${SECTIONS.lista}">Abrir lista</button></div>
          <div class="orc-list">${(data.ultimos || []).map(b => `<button class="orc-list-row orc-clickable" data-open-budget="${b.id}" style="width:100%;border:0;text-align:left;color:inherit">
            <div class="orc-avatar">#</div><div><strong>${esc(b.codigo || `#${b.id}`)} · ${esc(b.cliente_nome)}</strong><div class="orc-muted">${typeLabel(b.tipo)} · ${displayDate(b.data_orcamento)}</div></div>
            <div style="text-align:right">${badge(b.status)}<div class="orc-money">${money(b.total)}</div></div></button>`).join("") || '<div class="orc-muted">Nenhum orçamento cadastrado.</div>'}</div>
        </section><button class="orc-fab" id="orcNewFab" title="Novo orçamento">＋</button>`);
      bindNavigation(target);
      const newBudget = () => { state.draft = blankDraft(); state.wizardStep = 1; state.selectedId = null; go(SECTIONS.novo); };
      root("orcNewDashboard")?.addEventListener("click", newBudget);
      root("orcNewFab")?.addEventListener("click", newBudget);
      target.querySelectorAll("[data-open-budget]").forEach(el => el.addEventListener("click", () => { state.selectedId = Number(el.dataset.openBudget); go(SECTIONS.proposta); }));
    } catch (error) { errorView(target, error, loadDashboard); }
  }

  function listFilters() {
    return `<section class="orc-card"><div class="orc-filter-grid">
      <label class="orc-field"><span>Pesquisar</span><input class="orc-input" id="orcListSearch" placeholder="Código, cliente ou responsável"></label>
      <label class="orc-field"><span>Cliente</span><select class="orc-select" id="orcListClient"><option value="">Todos</option>${state.clients.map(c => `<option value="${c.id}">${esc(c.nome)}</option>`).join("")}</select></label>
      <label class="orc-field"><span>Status</span><select class="orc-select" id="orcListStatus"><option value="">Todos</option>${Object.entries(STATUS_LABELS).map(([v,l]) => `<option value="${v}">${l}</option>`).join("")}</select></label>
      <label class="orc-field"><span>Tipo</span><select class="orc-select" id="orcListType"><option value="">Todos</option>${Object.entries(TYPE_LABELS).map(([v,l]) => `<option value="${v}">${l}</option>`).join("")}</select></label>
      <label class="orc-field"><span>Data inicial</span><input class="orc-input" id="orcListStart" type="date"></label>
      <label class="orc-field"><span>Data final</span><input class="orc-input" id="orcListEnd" type="date"></label>
      <div class="orc-actions" style="align-self:end"><button class="orc-btn orc-btn--primary" id="orcApplyFilter">Filtrar</button><button class="orc-btn" id="orcClearFilter">Limpar</button></div>
    </div></section>`;
  }

  function budgetRows(items) {
    return items.map(b => `<tr><td><strong>${esc(b.codigo || `#${b.id}`)}</strong></td><td>${esc(b.cliente_nome)}</td>
      <td>${esc(typeLabel(b.tipo))}</td><td>${displayDate(b.data_orcamento)}</td><td class="orc-money">${money(b.total)}</td>
      <td>${esc(b.responsavel_nome || "—")}</td><td>${badge(b.status)}</td><td><div class="orc-row-actions">
        <button class="orc-btn orc-btn--sm" data-action="view" data-id="${b.id}">Ver</button>
        <button class="orc-btn orc-btn--sm" data-action="edit" data-id="${b.id}">Editar</button>
        <button class="orc-btn orc-btn--sm" data-action="duplicate" data-id="${b.id}">Duplicar</button>
        <button class="orc-btn orc-btn--sm" data-action="pdf" data-id="${b.id}">PDF</button>
        <button class="orc-btn orc-btn--sm" data-action="whatsapp" data-id="${b.id}">WhatsApp</button>
        <button class="orc-btn orc-btn--sm orc-btn--danger" data-action="delete" data-id="${b.id}">Excluir</button>
      </div></td></tr>`).join("") || '<tr><td colspan="8" class="orc-muted" style="text-align:center">Nenhum orçamento encontrado.</td></tr>';
  }

  async function loadBudgetList(params) {
    const tbody = root("orcBudgetRows");
    if (tbody) tbody.innerHTML = '<tr><td colspan="8" class="orc-muted" style="text-align:center">Carregando…</td></tr>';
    const data = await orcFetch(`/orcamentos${query(params)}`);
    state.budgets = data.items || [];
    if (tbody) {
      tbody.innerHTML = budgetRows(state.budgets);
      bindBudgetActions(tbody);
    }
  }

  function bindBudgetActions(container) {
    container.querySelectorAll("[data-action]").forEach(button => button.addEventListener("click", async () => {
      const id = Number(button.dataset.id);
      const action = button.dataset.action;
      try {
        if (action === "delete") {
          if (!confirm("Excluir definitivamente este orçamento?")) return;
          await busy(button, () => orcFetch(`/orcamentos/${id}`, { method: "DELETE" }));
          toast("Orçamento excluído.", "success");
          await loadBudgetList();
          return;
        }
        if (action === "view" || action === "pdf" || action === "whatsapp") {
          state.selectedId = id;
          go(SECTIONS.proposta);
          if (action === "pdf") toast("Na proposta, use Imprimir para salvar em PDF.", "info");
          return;
        }
        const data = await busy(button, () => orcFetch(`/orcamentos/${id}`));
        hydrate(data, action === "duplicate");
        if (action === "duplicate") toast("Cópia preparada. Revise e salve.", "info");
        go(SECTIONS.novo);
      } catch (error) { toast(error.message || "Falha na operação.", "error"); }
    }));
  }

  async function loadLista() {
    const target = root("orcamentosListaRoot");
    if (!target) return;
    loading(target);
    try {
      const clientsData = await orcFetch("/orcamentos/clientes");
      state.clients = clientsData.items || [];
      target.innerHTML = shell(`${header("Orçamentos", "Consulte, filtre e gerencie propostas reais.", "☷",
        '<button class="orc-btn orc-btn--primary" id="orcNewList">＋ Novo</button>', "Lista")}
        ${listFilters()}<div class="orc-table-wrap"><table class="orc-table"><thead><tr><th>Número</th><th>Cliente</th><th>Tipo</th><th>Data</th><th>Valor</th><th>Responsável</th><th>Situação</th><th>Ações</th></tr></thead><tbody id="orcBudgetRows"></tbody></table></div>`);
      bindNavigation(target);
      root("orcNewList").addEventListener("click", () => { state.draft = blankDraft(); state.wizardStep = 1; go(SECTIONS.novo); });
      root("orcApplyFilter").addEventListener("click", () => loadBudgetList({
        busca: root("orcListSearch").value, cliente_id: root("orcListClient").value,
        status: root("orcListStatus").value, tipo: root("orcListType").value,
        data_inicio: root("orcListStart").value, data_fim: root("orcListEnd").value,
      }).catch(error => toast(error.message, "error")));
      root("orcClearFilter").addEventListener("click", () => {
        target.querySelectorAll("input").forEach(el => { el.value = ""; });
        target.querySelectorAll("select").forEach(el => { el.selectedIndex = 0; });
        loadBudgetList().catch(error => toast(error.message, "error"));
      });
      await loadBudgetList();
    } catch (error) { errorView(target, error, loadLista); }
  }

  function clientStep() {
    const c = state.draft.cliente;
    return `<div class="orc-card__head"><div><h3>Dados do cliente</h3><div class="orc-muted">Identificação e canais de contato</div></div>${badge("rascunho")}</div>
      <div class="orc-form-grid">
        ${field("Nome *", "nome", c.nome)}${field("Telefone", "telefone", c.telefone)}${field("WhatsApp", "whatsapp", c.whatsapp)}
        ${field("Instagram", "instagram", c.instagram)}${field("E-mail", "email", c.email, "email")}${field("CPF/CNPJ", "documento", c.documento)}
        ${field("Empresa", "empresa", c.empresa)}
        <label class="orc-field"><span>Origem</span><select class="orc-select" data-client-field="origem"><option value="">Selecione</option>${["Instagram","Indicação","WhatsApp","Site","Outro"].map(v => `<option ${c.origem === v ? "selected" : ""}>${v}</option>`).join("")}</select></label>
        <label class="orc-field orc-field--full"><span>Observações</span><textarea class="orc-textarea" data-client-field="observacoes">${esc(c.observacoes)}</textarea></label>
      </div>`;
  }
  function field(label, key, value, type) {
    return `<label class="orc-field"><span>${label}</span><input class="orc-input" type="${type || "text"}" data-client-field="${key}" value="${esc(value)}"></label>`;
  }

  function typeStep() {
    const icons = { produto:"◆", servico:"◇", equipamento:"▣", evento:"★", buffet:"◉", mesa:"▦", locacao:"⌂", mao_obra:"◎", outro:"＋" };
    return `<div class="orc-card__head"><div><h3>Tipo do orçamento</h3><div class="orc-muted">Selecione a categoria principal</div></div></div>
      <div class="orc-choice-grid">${Object.entries(TYPE_LABELS).map(([value,label]) => `<button class="orc-choice ${state.draft.tipo === value ? "is-selected" : ""}" data-budget-type="${value}">
        <span class="orc-choice__icon">${icons[value]}</span><strong>${label}</strong><span class="orc-muted">Orçamento de ${label.toLowerCase()}.</span></button>`).join("")}</div>`;
  }

  function linesBy(type) { return state.draft.linhas.map((line, index) => ({ line, index })).filter(item => item.line.tipo_linha === type); }
  function lineInput(index, key, value, inputType, step) {
    return `<input class="orc-input" style="min-width:${key === "descricao" ? "180px" : "80px"}" type="${inputType || "text"}" step="${step || "any"}" data-line-index="${index}" data-line-key="${key}" value="${esc(value)}">`;
  }
  function linesStep(type) {
    const configs = {
      produto_servico: { title:"Produtos e serviços", add:"Adicionar item", headers:["Produto/serviço","Quantidade","Valor","Desconto %","Subtotal","Ação"] },
      equipe: { title:"Equipe do evento", add:"Adicionar profissional", headers:["Função","Qtd.","Horas","Valor/hora","Valor evento","Subtotal","Ação"] },
      equipamento: { title:"Equipamentos", add:"Adicionar equipamento", headers:["Equipamento","Qtd.","Dias","Valor/dia","Subtotal","Ação"] },
      consumo: { title:"Produtos consumidos", add:"Adicionar produto", headers:["Produto","Quantidade","Valor","Subtotal","Ação"] },
    };
    const cfg = configs[type];
    const rows = linesBy(type).map(({ line, index }) => {
      const base = [lineInput(index,"descricao",line.descricao), lineInput(index,"quantidade",line.quantidade,"number","0.001")];
      if (type === "equipe") base.push(lineInput(index,"horas",line.horas,"number"), lineInput(index,"valor_unitario",line.valor_unitario,"number"), lineInput(index,"valor_evento",line.valor_evento,"number"));
      else if (type === "equipamento") base.push(lineInput(index,"dias",line.dias || 1,"number"), lineInput(index,"valor_unitario",line.valor_unitario,"number"));
      else {
        base.push(lineInput(index,"valor_unitario",line.valor_unitario,"number"));
        if (type === "produto_servico") base.push(lineInput(index,"desconto_percentual",line.desconto_percentual,"number"));
      }
      base.push(`<strong data-line-total="${index}">${money(lineSubtotal(line))}</strong>`, `<button class="orc-btn orc-btn--sm orc-btn--danger" data-remove-line="${index}">Remover</button>`);
      return `<tr>${base.map(value => `<td>${value}</td>`).join("")}</tr>`;
    }).join("");
    return `<div class="orc-card__head"><div><h3>${cfg.title}</h3><div class="orc-muted">Os valores são recalculados e validados no servidor</div></div><button class="orc-btn orc-btn--primary" data-add-line="${type}">＋ ${cfg.add}</button></div>
      <div class="orc-table-wrap"><table class="orc-table"><thead><tr>${cfg.headers.map(h => `<th>${h}</th>`).join("")}</tr></thead>
      <tbody>${rows || `<tr><td colspan="${cfg.headers.length}" class="orc-muted" style="text-align:center">Adicione o primeiro item.</td></tr>`}</tbody></table></div>
      ${type === "consumo" ? '<div class="orc-notice" style="margin-top:1rem">Itens de consumo pertencem somente ao orçamento e não baixam estoque.</div>' : ""}`;
  }

  function freightStep() {
    const f = state.draft.frete;
    const options = [["sem_frete","Sem frete","×"],["retirada","Retirada","↙"],["entrega","Entrega","→"],["montagem","Montagem","↑"],["desmontagem","Desmontagem","↓"]];
    return `<div class="orc-card__head"><div><h3>Frete e logística</h3><div class="orc-muted">Defina a modalidade e os custos</div></div></div>
      <div class="orc-choice-grid">${options.map(([v,l,i]) => `<button class="orc-choice ${f.tipo === v ? "is-selected" : ""}" data-freight-type="${v}"><span class="orc-choice__icon">${i}</span><strong>${l}</strong></button>`).join("")}</div>
      <div class="orc-form-grid" style="margin-top:1rem">
        <label class="orc-field"><span>Valor</span><input class="orc-input" type="number" step="0.01" data-freight-field="valor" value="${esc(f.valor)}"></label>
        <label class="orc-field"><span>Distância (km)</span><input class="orc-input" type="number" step="0.01" data-freight-field="distancia_km" value="${esc(f.distancia_km)}"></label>
        <label class="orc-field orc-field--full"><span>Observações</span><textarea class="orc-textarea" data-freight-field="observacoes">${esc(f.observacoes)}</textarea></label>
      </div>`;
  }

  function financeStep() {
    const f = state.draft.financeiro;
    const t = totals();
    return `<div class="orc-card__head"><div><h3>Financeiro</h3><div class="orc-muted">Condições e fechamento do orçamento</div></div></div>
      <div class="orc-grid-2"><div class="orc-form-grid">
        <label class="orc-field"><span>Desconto %</span><input class="orc-input" type="number" step="0.01" data-finance-field="desconto_percentual" value="${esc(f.desconto_percentual)}"></label>
        <label class="orc-field"><span>Desconto R$</span><input class="orc-input" type="number" step="0.01" data-finance-field="desconto_valor" value="${esc(f.desconto_valor)}"></label>
        <label class="orc-field"><span>Acréscimo R$</span><input class="orc-input" type="number" step="0.01" data-finance-field="acrescimo_valor" value="${esc(f.acrescimo_valor)}"></label>
        <label class="orc-field"><span>Forma de pagamento</span><select class="orc-select" data-finance-field="forma_pagamento">${["pix","dinheiro","cartao","parcelado"].map(v => `<option value="${v}" ${f.forma_pagamento === v ? "selected" : ""}>${v === "cartao" ? "Cartão" : v[0].toUpperCase()+v.slice(1)}</option>`).join("")}</select></label>
        <label class="orc-field"><span>Validade</span><input class="orc-input" type="date" id="orcValidity" value="${esc(state.draft.validade)}"></label>
        <label class="orc-field orc-field--full"><span>Observações financeiras</span><textarea class="orc-textarea" data-finance-field="observacoes">${esc(f.observacoes)}</textarea></label>
      </div><div class="orc-summary"><h3>Resumo financeiro</h3>
        <div class="orc-summary__row"><span>Produtos/serviços</span><strong>${money(t.produto_servico)}</strong></div>
        <div class="orc-summary__row"><span>Equipe</span><strong>${money(t.equipe)}</strong></div>
        <div class="orc-summary__row"><span>Equipamentos</span><strong>${money(t.equipamento)}</strong></div>
        <div class="orc-summary__row"><span>Consumo</span><strong>${money(t.consumo)}</strong></div>
        <div class="orc-summary__row"><span>Frete</span><strong>${money(t.frete)}</strong></div>
        <div class="orc-summary__row"><span>Desconto</span><strong>− ${money(t.desconto)}</strong></div>
        <div class="orc-summary__row orc-summary__row--total"><span>Total</span><span>${money(t.total)}</span></div>
      </div></div>`;
  }

  function wizardContent() {
    if (state.wizardStep === 1) return clientStep();
    if (state.wizardStep === 2) return typeStep();
    if (state.wizardStep === 3) return linesStep("produto_servico");
    if (state.wizardStep === 4) return linesStep("equipe");
    if (state.wizardStep === 5) return linesStep("equipamento");
    if (state.wizardStep === 6) return linesStep("consumo");
    if (state.wizardStep === 7) return freightStep();
    return financeStep();
  }

  function bindDraftInputs(target) {
    target.querySelectorAll("[data-client-field]").forEach(input => input.addEventListener("input", () => { state.draft.cliente[input.dataset.clientField] = input.value; }));
    target.querySelectorAll("[data-line-index]").forEach(input => input.addEventListener("input", () => {
      const line = state.draft.linhas[Number(input.dataset.lineIndex)];
      line[input.dataset.lineKey] = input.type === "number" ? number(input.value) : input.value;
      const total = target.querySelector(`[data-line-total="${input.dataset.lineIndex}"]`);
      if (total) total.textContent = money(lineSubtotal(line));
    }));
    target.querySelectorAll("[data-remove-line]").forEach(button => button.addEventListener("click", () => {
      state.draft.linhas.splice(Number(button.dataset.removeLine), 1); renderWizard();
    }));
    target.querySelectorAll("[data-add-line]").forEach(button => button.addEventListener("click", () => {
      const type = button.dataset.addLine;
      state.draft.linhas.push({ tipo_linha:type, descricao:"", quantidade:1, unidade_medida:"", horas:type === "equipe" ? 1 : 0, dias:type === "equipamento" ? 1 : 0, valor_unitario:0, desconto_percentual:0, valor_evento:0, custo_unitario:0 });
      renderWizard();
    }));
    target.querySelectorAll("[data-budget-type]").forEach(button => button.addEventListener("click", () => { state.draft.tipo = button.dataset.budgetType; renderWizard(); }));
    target.querySelectorAll("[data-freight-type]").forEach(button => button.addEventListener("click", () => { state.draft.frete.tipo = button.dataset.freightType; renderWizard(); }));
    target.querySelectorAll("[data-freight-field]").forEach(input => input.addEventListener("input", () => { state.draft.frete[input.dataset.freightField] = input.type === "number" ? number(input.value) : input.value; }));
    target.querySelectorAll("[data-finance-field]").forEach(input => input.addEventListener("input", () => { state.draft.financeiro[input.dataset.financeField] = input.type === "number" ? number(input.value) : input.value; }));
    root("orcValidity")?.addEventListener("input", event => { state.draft.validade = event.target.value; });
  }

  function renderWizard() {
    const target = root("orcamentosNovoRoot");
    if (!target) return;
    target.innerHTML = shell(`${header(state.draft.id ? `Editar ${state.draft.codigo}` : "Novo Orçamento",
      "Preencha as etapas; cada avanço salva o rascunho.", "＋", `<button class="orc-btn" data-orc-go="${SECTIONS.lista}">Cancelar</button>`, "Novo")}
      <div class="orc-wizard"><aside class="orc-wizard__steps">${STEPS.map((label, index) => `<button class="orc-step ${state.wizardStep === index + 1 ? "is-active" : ""} ${state.wizardStep > index + 1 ? "is-done" : ""}" data-wizard-step="${index + 1}">
        <span class="orc-step__num">${state.wizardStep > index + 1 ? "✓" : index + 1}</span><span class="orc-step__label">${label}</span></button>`).join("")}</aside>
      <main class="orc-card orc-wizard__body"><div>${wizardContent()}</div><footer class="orc-wizard__footer">
        <button class="orc-btn" id="orcWizardPrev" ${state.wizardStep === 1 ? "disabled" : ""}>← Voltar</button>
        <button class="orc-btn orc-btn--primary" id="orcWizardNext">${state.wizardStep === 8 ? "Salvar e enviar proposta" : "Salvar e continuar →"}</button>
      </footer></main></div>`);
    bindNavigation(target);
    bindDraftInputs(target);
    target.querySelectorAll("[data-wizard-step]").forEach(button => button.addEventListener("click", () => { state.wizardStep = Number(button.dataset.wizardStep); renderWizard(); }));
    root("orcWizardPrev").addEventListener("click", () => { if (state.wizardStep > 1) { state.wizardStep--; renderWizard(); } });
    root("orcWizardNext").addEventListener("click", async function () {
      try {
        const final = state.wizardStep === 8;
        await saveDraft(final, this);
        if (final) {
          toast("Orçamento salvo e enviado para aprovação.", "success");
          state.selectedId = state.draft.id;
          go(SECTIONS.proposta);
        } else {
          state.wizardStep++;
          state.draft.etapa_wizard = state.wizardStep;
          renderWizard();
          toast("Rascunho salvo.", "success");
        }
      } catch (error) { toast(error.message || "Não foi possível salvar.", "error"); }
    });
  }

  async function loadNovo() {
    if (!state.draft) state.draft = blankDraft();
    renderWizard();
  }

  async function loadProposta() {
    const target = root("orcamentosPropostaRoot");
    if (!target) return;
    if (!state.selectedId && state.draft.id) state.selectedId = state.draft.id;
    if (!state.selectedId) { go(SECTIONS.lista); return; }
    loading(target);
    try {
      const b = await orcFetch(`/orcamentos/${state.selectedId}`);
      hydrate(b, false);
      const c = b.cliente || {};
      target.innerHTML = shell(`${header(`Proposta ${b.codigo}`, "Documento comercial pronto para envio e aprovação.", "▤",
        `<button class="orc-btn" data-orc-go="${SECTIONS.lista}">← Orçamentos</button><button class="orc-btn orc-btn--primary" id="orcPrintProposal">Imprimir / PDF</button>`, "Proposta")}
        <article class="orc-proposal"><div class="orc-proposal__brand"><div style="display:flex;gap:1rem;align-items:center">
          <img src="imagens/logo-sem-fundo.png?v=20260717" alt="Logo Sabor Paraense" class="orc-proposal__logo"><div><h2>Grupo Sabor Paraense</h2><div>Proposta Comercial</div></div></div>
          <div style="text-align:right"><strong>${esc(b.codigo)}</strong><div>${displayDate(b.data_orcamento)}</div>${badge(b.status)}</div></div>
        <div class="orc-proposal__columns"><div><h4>Empresa</h4><p>Grupo Sabor Paraense<br>Atendimento comercial</p></div>
          <div><h4>Cliente</h4><p>${esc(c.nome)}<br>${esc(c.whatsapp || c.telefone || "")}<br>${esc(c.email || "")}</p></div></div>
        <div class="orc-table-wrap"><table class="orc-table" style="min-width:0"><thead><tr><th>Descrição</th><th>Qtd.</th><th>Valor</th><th>Total</th></tr></thead>
          <tbody>${(b.linhas || []).map(line => `<tr><td>${esc(line.descricao)}</td><td>${number(line.quantidade)}</td><td>${money(line.valor_unitario)}</td><td>${money(line.subtotal)}</td></tr>`).join("") || '<tr><td colspan="4">Sem itens.</td></tr>'}</tbody></table></div>
        <div class="orc-proposal__columns"><div><h4>Condições</h4><p>Forma de pagamento: ${esc(b.financeiro?.forma_pagamento || "A combinar")}<br>Validade: ${displayDate(b.validade)}</p>
          <h4>Observações</h4><p>${esc(b.financeiro?.observacoes || b.observacoes || "—")}</p></div>
          <div class="orc-summary"><div class="orc-summary__row"><span>Produtos/serviços</span><strong>${money(b.subtotal_produtos)}</strong></div>
          <div class="orc-summary__row"><span>Equipe</span><strong>${money(b.subtotal_equipe)}</strong></div><div class="orc-summary__row"><span>Equipamentos</span><strong>${money(b.subtotal_equipamentos)}</strong></div>
          <div class="orc-summary__row"><span>Frete</span><strong>${money(b.subtotal_frete)}</strong></div><div class="orc-summary__row"><span>Desconto</span><strong>− ${money(b.total_desconto)}</strong></div>
          <div class="orc-summary__row orc-summary__row--total"><span>Total</span><span>${money(b.total)}</span></div></div></div>
        <div class="orc-signature"><div>Grupo Sabor Paraense</div><div>${esc(c.nome)}</div></div></article>
        <section class="orc-card"><div class="orc-actions">
          <button class="orc-btn" id="orcPrintBottom">Imprimir / PDF</button><button class="orc-btn" id="orcWhatsApp">WhatsApp</button>
          <button class="orc-btn" id="orcEmail">E-mail</button><button class="orc-btn" id="orcCopyLink">Copiar link</button>
          <button class="orc-btn orc-btn--success" data-status="aprovado">Aprovar</button>
          <button class="orc-btn orc-btn--danger" data-status="recusado">Recusar</button>
        </div></section>`);
      bindNavigation(target);
      const print = () => window.print();
      root("orcPrintProposal").addEventListener("click", print);
      root("orcPrintBottom").addEventListener("click", print);
      root("orcWhatsApp").addEventListener("click", () => {
        const phone = String(c.whatsapp || c.telefone || "").replace(/\D/g, "");
        const message = `Olá, ${c.nome}! Segue a proposta ${b.codigo}, no valor de ${money(b.total)}: ${location.href}`;
        window.open(`https://wa.me/${phone.startsWith("55") ? phone : `55${phone}`}?text=${encodeURIComponent(message)}`, "_blank", "noopener");
      });
      root("orcEmail").addEventListener("click", () => { location.href = `mailto:${encodeURIComponent(c.email || "")}?subject=${encodeURIComponent(`Proposta ${b.codigo}`)}&body=${encodeURIComponent(`Olá, ${c.nome}! Segue sua proposta no valor de ${money(b.total)}: ${location.href}`)}`; });
      root("orcCopyLink").addEventListener("click", async () => { await navigator.clipboard.writeText(location.href); toast("Link copiado.", "success"); });
      target.querySelectorAll("[data-status]").forEach(button => button.addEventListener("click", async () => {
        try {
          await busy(button, () => orcFetch(`/orcamentos/${b.id}/status`, { method:"PATCH", body:JSON.stringify({ status:button.dataset.status }) }));
          toast("Situação atualizada.", "success"); await loadProposta();
        } catch (error) { toast(error.message, "error"); }
      }));
    } catch (error) { errorView(target, error, loadProposta); }
  }

  async function loadClientes() {
    const target = root("orcamentosClientesRoot");
    if (!target) return;
    loading(target);
    try {
      const data = await orcFetch("/orcamentos/clientes");
      state.clients = data.items || [];
      target.innerHTML = shell(`${header("Clientes", "Clientes cadastrados por meio dos orçamentos.", "◎",
        '<button class="orc-btn orc-btn--primary" id="orcClientNew">＋ Novo orçamento</button>', "Clientes")}
        <div class="orc-client-grid">${state.clients.map(c => `<article class="orc-client"><div class="orc-avatar">${esc(c.nome.split(" ").slice(0,2).map(n => n[0]).join(""))}</div>
          <div><h3>${esc(c.nome)}</h3><div class="orc-muted">${esc(c.whatsapp || c.telefone || "Sem telefone")}<br>${esc(c.email || "")}</div></div>
          <button class="orc-btn orc-btn--primary" data-client-budget="${c.id}">＋ Novo orçamento</button></article>`).join("") || '<div class="orc-muted">Os clientes aparecerão após o primeiro orçamento.</div>'}</div>`);
      bindNavigation(target);
      root("orcClientNew").addEventListener("click", () => { state.draft = blankDraft(); state.wizardStep = 1; go(SECTIONS.novo); });
      target.querySelectorAll("[data-client-budget]").forEach(button => button.addEventListener("click", () => {
        const client = state.clients.find(c => c.id === Number(button.dataset.clientBudget));
        state.draft = blankDraft();
        state.draft.cliente = { ...state.draft.cliente, ...client };
        state.wizardStep = 1; go(SECTIONS.novo);
      }));
    } catch (error) { errorView(target, error, loadClientes); }
  }

  function loadModelos() {
    const target = root("orcamentosModelosRoot");
    const models = [["buffet","Buffet Completo","◉"],["evento","Evento Corporativo","★"],["produto","Venda de Produtos","◆"],["servico","Prestação de Serviço","◇"],["locacao","Locação","⌂"],["equipamento","Equipamentos","▣"],["mesa","Mesa Posta","▦"]];
    target.innerHTML = shell(`${header("Modelos", "Estruturas rápidas para iniciar um orçamento.", "▣", "", "Modelos")}
      <div class="orc-model-grid">${models.map(m => `<article class="orc-model"><span class="orc-model__icon">${m[2]}</span><h3>${m[1]}</h3><p class="orc-muted">Inicia um rascunho real com este tipo.</p>
      <button class="orc-btn orc-btn--primary" data-use-model="${m[0]}">Usar modelo</button></article>`).join("")}</div>`);
    bindNavigation(target);
    target.querySelectorAll("[data-use-model]").forEach(button => button.addEventListener("click", () => {
      state.draft = blankDraft(); state.draft.tipo = button.dataset.useModel; state.wizardStep = 1; go(SECTIONS.novo);
    }));
  }

  function loadItens() {
    const target = root("orcamentosItensRoot");
    const labels = { produto_servico:"Produtos/Serviços", equipe:"Equipe", equipamento:"Equipamentos", consumo:"Consumo" };
    const items = linesBy(state.activeItemTab);
    target.innerHTML = shell(`${header("Itens do orçamento atual", "Visualize as linhas do rascunho em edição.", "◆",
      `<button class="orc-btn orc-btn--primary" data-orc-go="${SECTIONS.novo}">Editar no wizard</button>`, "Itens")}
      <section class="orc-card"><div class="orc-tabs">${Object.entries(labels).map(([v,l]) => `<button class="orc-tab ${state.activeItemTab === v ? "is-active" : ""}" data-item-tab="${v}">${l}</button>`).join("")}</div></section>
      <div class="orc-table-wrap"><table class="orc-table"><thead><tr><th>Descrição</th><th>Quantidade</th><th>Valor</th><th>Subtotal</th></tr></thead>
      <tbody>${items.map(({line}) => `<tr><td>${esc(line.descricao)}</td><td>${number(line.quantidade)}</td><td>${money(line.valor_unitario)}</td><td>${money(lineSubtotal(line))}</td></tr>`).join("") || '<tr><td colspan="4">Nenhum item no rascunho.</td></tr>'}</tbody></table></div>`);
    bindNavigation(target);
    target.querySelectorAll("[data-item-tab]").forEach(button => button.addEventListener("click", () => { state.activeItemTab = button.dataset.itemTab; loadItens(); }));
  }

  function loadConfiguracoes() {
    const target = root("orcamentosConfiguracoesRoot");
    target.innerHTML = shell(`${header("Configurações", "Mensagens utilizadas pelos canais de envio.", "⚙", "", "Configurações")}
      <div class="orc-notice">As condições financeiras e a validade são definidas em cada orçamento.</div>
      <section class="orc-card"><div class="orc-form-grid"><label class="orc-field orc-field--full"><span>Mensagem WhatsApp</span>
      <textarea class="orc-textarea" readonly>Olá, {cliente}! Segue a proposta {codigo}, no valor de {total}.</textarea></label>
      <label class="orc-field orc-field--full"><span>Mensagem E-mail</span><textarea class="orc-textarea" readonly>Olá, {cliente}. Segue sua proposta comercial.</textarea></label></div></section>`);
    bindNavigation(target);
  }

  async function loadRelatorios() {
    const target = root("orcamentosRelatoriosRoot");
    if (!target) return;
    loading(target);
    try {
      const data = await orcFetch("/orcamentos/dashboard");
      const r = data.resumo || {};
      target.innerHTML = shell(`${header("Relatórios", "Resumo comercial calculado com os dados cadastrados.", "⌁", "", "Relatórios")}
        <div class="orc-kpis">${kpi("Total orçado",money(r.valor_total),"R$","Valor acumulado")}${kpi("Conversão",`${number(r.conversao_percentual).toFixed(1)}%`,"↗","Aprovados e convertidos")}
        ${kpi("Clientes",String((data.top_clientes || []).length),"◎","Top clientes no período")}${kpi("Ticket médio",money(r.ticket_medio),"∅","Média por proposta")}</div>
        <section class="orc-card"><div class="orc-card__head"><h3>Ranking de clientes</h3></div><div class="orc-list">${(data.top_clientes || []).map((c,i) => `<div class="orc-list-row"><div class="orc-avatar">${i+1}</div><strong>${esc(c.nome)}</strong><span class="orc-money">${money(c.total)}</span></div>`).join("") || '<div class="orc-muted">Sem dados.</div>'}</div></section>`);
      bindNavigation(target);
    } catch (error) { errorView(target, error, loadRelatorios); }
  }

  window.loadOrcamentosDashboard = loadDashboard;
  window.loadOrcamentosNovo = loadNovo;
  window.loadOrcamentosLista = loadLista;
  window.loadOrcamentosProposta = loadProposta;
  window.loadOrcamentosModelos = loadModelos;
  window.loadOrcamentosClientes = loadClientes;
  window.loadOrcamentosItens = loadItens;
  window.loadOrcamentosConfiguracoes = loadConfiguracoes;
  window.loadOrcamentosRelatorios = loadRelatorios;
})();
