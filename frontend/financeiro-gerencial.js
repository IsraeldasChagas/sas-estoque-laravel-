/**
 * Módulo Financeiro Gerencial — SAS-Estoque / Grupo Sabor Paraense
 */
(function () {
  "use strict";

  const FG_CENTROS_PADRAO = ["Administrativo", "Manutenção", "Estoque", "Outros"];
  const FG_FORMAS_PGTO = ["Pix", "Dinheiro", "Credito", "Debito"];
  const fgState = { unidades: [], categorias: [], centros: [], clientes: [], fluxoLancamentos: [], fluxoEditId: null };

  function fgNormalizarFormaPgto(valor) {
    const raw = (valor ?? "").toString().trim();
    if (!raw) return "";
    const map = {
      pix: "Pix",
      dinheiro: "Dinheiro",
      credito: "Credito",
      crédito: "Credito",
      debito: "Debito",
      débito: "Debito",
      cartao: "Credito",
      cartão: "Credito",
      "cartao de credito": "Credito",
      "cartão de crédito": "Credito",
      "cartao de debito": "Debito",
      "cartão de débito": "Debito",
    };
    const key = raw.toLowerCase();
    if (map[key]) return map[key];
    if (FG_FORMAS_PGTO.includes(raw)) return raw;
    return raw;
  }

  function fgSetFormaPgtoSelect(valor) {
    const sel = document.getElementById("fgFluxoFormaPgto");
    if (!sel) return;
    const normalizado = fgNormalizarFormaPgto(valor);
    Array.from(sel.querySelectorAll("option[data-fg-legado]")).forEach((o) => o.remove());
    if (normalizado && !FG_FORMAS_PGTO.includes(normalizado)) {
      const legado = document.createElement("option");
      legado.value = normalizado;
      legado.textContent = normalizado;
      legado.dataset.fgLegado = "1";
      sel.appendChild(legado);
    }
    sel.value = normalizado || "";
  }

  function esc(s) {
    return (s ?? "").toString()
      .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
  }

  function fmtMoeda(n) {
    if (typeof formatCurrencyBRL === "function") return formatCurrencyBRL(n);
    const v = Number(n);
    if (!Number.isFinite(v)) return "—";
    return v.toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
  }

  function fmtData(d) {
    if (!d) return "—";
    const s = String(d).slice(0, 10);
    if (!/^\d{4}-\d{2}-\d{2}/.test(s)) return s;
    const [y, m, day] = s.split("-");
    return `${day}/${m}/${y}`;
  }

  function fgToast(msg, type = "info") {
    const fn = typeof showToast === "function" ? showToast : window.showToast;
    if (typeof fn === "function") fn(msg, type);
  }

  async function fgFetch(path, opts = {}) {
    if (typeof window.fetchJSON === "function") return window.fetchJSON(path, opts);
    throw new Error("fetchJSON não disponível");
  }

  function fgPeriodoPadrao() {
    const hoje = new Date();
    const de = `${hoje.getFullYear()}-${String(hoje.getMonth() + 1).padStart(2, "0")}-01`;
    const ate = hoje.toISOString().slice(0, 10);
    return { de, ate };
  }

  function fgQueryFiltros(prefix) {
    const deEl = document.getElementById(`${prefix}FiltroDe`);
    const ateEl = document.getElementById(`${prefix}FiltroAte`);
    const uniEl = document.getElementById(`${prefix}FiltroUnidade`);
    const p = fgPeriodoPadrao();
    const de = deEl?.value || p.de;
    const ate = ateEl?.value || p.ate;
    let q = `?de=${encodeURIComponent(de)}&ate=${encodeURIComponent(ate)}`;
    if (uniEl?.value) q += `&unidade_id=${encodeURIComponent(uniEl.value)}`;
    return { de, ate, q };
  }

  async function fgCarregarUnidades(selectIds) {
    if (fgState.unidades.length) {
      fgPopularSelectsUnidade(selectIds);
      return fgState.unidades;
    }
    try {
      const lista = await fgFetch("/unidades");
      fgState.unidades = Array.isArray(lista) ? lista : [];
    } catch (_) {
      fgState.unidades = [];
    }
    fgPopularSelectsUnidade(selectIds);
    return fgState.unidades;
  }

  function fgPopularSelectsUnidade(ids) {
    (ids || []).forEach((id) => {
      const el = document.getElementById(id);
      if (!el) return;
      const cur = el.value;
      el.innerHTML = '<option value="">Todas</option>';
      fgState.unidades.forEach((u) => {
        const o = document.createElement("option");
        o.value = u.id;
        o.textContent = u.nome || `Unidade ${u.id}`;
        el.appendChild(o);
      });
      if (cur) el.value = cur;
    });
  }

  function fgCardHtml(label, valor, extraClass = "") {
    return `<div class="fg-card"><div class="fg-card__label">${esc(label)}</div><div class="fg-card__value ${extraClass}">${esc(valor)}</div></div>`;
  }

  function fgSaudeHtml(saude) {
    if (!saude) return "";
    const st = saude.status || "atencao";
    return `<div class="fg-saude fg-saude--${st}"><span class="fg-saude-ring">${esc(saude.percentual)}%</span><span>${esc(saude.label || st)}</span></div>`;
  }

  function fgInitFiltrosDatas(prefix) {
    const p = fgPeriodoPadrao();
    const deEl = document.getElementById(`${prefix}FiltroDe`);
    const ateEl = document.getElementById(`${prefix}FiltroAte`);
    if (deEl && !deEl.value) deEl.value = p.de;
    if (ateEl && !ateEl.value) ateEl.value = p.ate;
  }

  function fgHojeIso() {
    return new Date().toISOString().slice(0, 10);
  }

  function fgNullableId(val) {
    const s = val != null ? String(val).trim() : "";
    if (!s) return null;
    const n = Number(s);
    return Number.isFinite(n) ? n : null;
  }

  function fgAjustarFiltroParaCompetencia(isoDate) {
    const d = String(isoDate || "").slice(0, 10);
    if (!/^\d{4}-\d{2}-\d{2}$/.test(d)) return;
    const [y, m] = d.split("-");
    const deEl = document.getElementById("fgFluxoFiltroDe");
    const ateEl = document.getElementById("fgFluxoFiltroAte");
    if (!deEl || !ateEl) return;
    deEl.value = `${y}-${m}-01`;
    const ultimoDia = new Date(Number(y), Number(m), 0).getDate();
    ateEl.value = `${y}-${m}-${String(ultimoDia).padStart(2, "0")}`;
  }

  function fgInitFormFluxoDatas() {
    if (fgState.fluxoEditId) return;
    const hoje = fgHojeIso();
    const comp = document.getElementById("fgFluxoCompetencia");
    const pgto = document.getElementById("fgFluxoPagamento");
    if (comp && !comp.value) comp.value = hoje;
    if (pgto && !pgto.value) pgto.value = hoje;
  }

  function fgLerValorFluxo() {
    const el = document.getElementById("fgFluxoValor");
    if (!el) return 0;
    if (typeof parseCurrencyInput === "function") {
      const v = parseCurrencyInput(el);
      if (Number.isFinite(v) && v > 0) return v;
    }
    const bruto = (el.value || el.dataset.value || "").toString();
    if (typeof parseCurrencyFromString === "function") {
      const v = parseCurrencyFromString(bruto);
      if (Number.isFinite(v) && v > 0) return v;
    }
    const n = Number(String(bruto).replace(/\./g, "").replace(",", "."));
    return Number.isFinite(n) ? n : 0;
  }

  // ——— Dashboard Executivo ———
  async function loadFinanceiroDashboard() {
    fgInitFiltrosDatas("fgDash");
    await fgCarregarUnidades(["fgDashFiltroUnidade"]);
    const { q } = fgQueryFiltros("fgDash");
    const data = await fgFetch(`/financeiro/dashboard${q}`);
    const cards = document.getElementById("fgDashCards");
    const saudeEl = document.getElementById("fgDashSaude");
    if (!cards) return;

    const lucro = Number(data.lucro_prejuizo || 0);
    const lucroCls = lucro >= 0 ? "fg-card__value--pos" : "fg-card__value--neg";

    cards.innerHTML = [
      fgCardHtml("Faturamento total", fmtMoeda(data.faturamento_total)),
      fgCardHtml("Total entradas", fmtMoeda(data.total_entradas)),
      fgCardHtml("Total saídas", fmtMoeda(data.total_saidas)),
      fgCardHtml("Lucro / prejuízo", fmtMoeda(lucro), lucroCls),
      fgCardHtml("Caixa disponível", fmtMoeda(data.caixa_disponivel)),
      fgCardHtml("Contas a pagar vencidas", fmtMoeda(data.contas_pagar_vencidas?.valor)),
      fgCardHtml("Contas a receber vencidas", fmtMoeda(data.contas_receber_vencidas?.valor)),
      fgCardHtml("Despesas fixas (mês)", fmtMoeda(data.despesas_fixas_mes)),
      fgCardHtml("Folha / proventos", fmtMoeda(data.folha_proventos_mes)),
      fgCardHtml("Custo saídas estoque", fmtMoeda(data.cmv_estimado)),
      fgCardHtml("Margem líquida", `${data.margem_liquida ?? 0}%`),
      fgCardHtml("Ponto de equilíbrio", data.ponto_equilibrio != null ? fmtMoeda(data.ponto_equilibrio) : "—"),
    ].join("");

    if (saudeEl) saudeEl.innerHTML = fgSaudeHtml(data.saude_financeira);

    const unidades = data.faturamento_por_unidade || [];
    const uniHtml = unidades.length
      ? unidades.map((r) => fgDashUnidadeCard(r)).join("")
      : '<p class="rh-cand-empty">Sem dados no período.</p>';
    fgMostrarLista("fgDashUnidadeLista", "fgDashUnidadeTbody", uniHtml);
  }

  function fgDashUnidadeCard(r) {
    const nome = r.unidade_nome || "Unidade";
    const valor = fmtMoeda(r.faturamento);
    return `<article class="rh-cand-card">
      <div class="rh-cand-card__top">
        <div class="usuarios-foto usuarios-foto--placeholder rh-cand-card__photo pat-card-icone" aria-hidden="true">🏪</div>
        <div class="rh-cand-card__main">
          <div class="rh-cand-card__head">
            <div>
              <h3 class="rh-cand-card__name">${esc(nome)}</h3>
              <p class="rh-cand-card__sub">Faturamento no período</p>
            </div>
            <span class="rh-cand-pill rh-cand-pill--info">${esc(valor)}</span>
          </div>
          <div class="rh-cand-card__meta">
            <div class="rh-cand-card__row"><span>Faturamento</span><strong>${esc(valor)}</strong></div>
          </div>
        </div>
      </div>
    </article>`;
  }

  function fgMostrarLista(listaId, tbodyId, html) {
    const lista = document.getElementById(listaId);
    if (lista) {
      lista.innerHTML = html;
      return;
    }
    const tbody = document.getElementById(tbodyId);
    const wrap = tbody?.closest(".table-wrapper");
    if (!wrap) return;
    const box = document.createElement("div");
    box.className = "rh-cand-list fg-fluxo-cards";
    box.id = listaId;
    box.innerHTML = html;
    wrap.replaceWith(box);
  }

  function fgFiltrarCentrosPadrao(lista) {
    const porNome = new Map();
    (lista || []).forEach((c) => {
      if (FG_CENTROS_PADRAO.includes(c.nome) && c.ativo !== false && c.ativo !== 0) {
        porNome.set(c.nome, c);
      }
    });
    return FG_CENTROS_PADRAO.map((nome) => porNome.get(nome)).filter(Boolean);
  }

  function fgPopularCentroCustoSelect(sel, centros, valorAtual) {
    if (!sel) return;
    const cur = valorAtual != null ? String(valorAtual) : sel.value;
    sel.innerHTML = '<option value="">—</option>';
    centros.forEach((c) => {
      const o = document.createElement("option");
      o.value = c.id;
      o.textContent = c.nome;
      sel.appendChild(o);
    });
    if (cur) sel.value = cur;
  }

  const FG_FLUXO_FORM_COLLAPSED_KEY = "fg-fluxo-form-collapsed";

  function fgSetFluxoFormCollapsed(collapsed, persist = true) {
    const card = document.getElementById("fgFluxoLancamentoCard");
    const btn = document.getElementById("fgFluxoToggleForm");
    if (!card) return;
    card.classList.toggle("fg-lancamento-card--collapsed", collapsed);
    if (btn) {
      btn.setAttribute("aria-expanded", collapsed ? "false" : "true");
      btn.title = collapsed ? "Expandir formulário" : "Recolher formulário";
    }
    if (persist) {
      try {
        localStorage.setItem(FG_FLUXO_FORM_COLLAPSED_KEY, collapsed ? "1" : "0");
      } catch (_) {}
    }
  }

  function fgExpandirFormFluxo() {
    fgSetFluxoFormCollapsed(false);
  }

  function fgInitFluxoFormCollapse() {
    const card = document.getElementById("fgFluxoLancamentoCard");
    if (!card || card.dataset.fgCollapseBound === "1") return;
    card.dataset.fgCollapseBound = "1";
    let collapsed = false;
    try {
      collapsed = localStorage.getItem(FG_FLUXO_FORM_COLLAPSED_KEY) === "1";
    } catch (_) {}
    fgSetFluxoFormCollapsed(collapsed, false);
    document.getElementById("fgFluxoToggleForm")?.addEventListener("click", () => {
      const isCollapsed = card.classList.contains("fg-lancamento-card--collapsed");
      fgSetFluxoFormCollapsed(!isCollapsed);
    });
  }

  function fgSetFluxoFormModo(edicao) {
    const titulo = document.getElementById("fgFluxoFormTitle");
    const submitBtn = document.getElementById("fgFluxoSubmitBtn");
    const cancelBtn = document.getElementById("fgFluxoCancelBtn");
    if (titulo) titulo.textContent = edicao ? "Editar lançamento" : "Novo lançamento";
    if (submitBtn) submitBtn.textContent = edicao ? "Salvar alterações" : "Salvar lançamento";
    if (cancelBtn) cancelBtn.classList.toggle("hidden", !edicao);
    if (edicao) fgExpandirFormFluxo();
  }

  function fgLimparFormFluxo() {
    const form = document.getElementById("fgFluxoForm");
    form?.reset();
    fgSetFormaPgtoSelect("");
    const valorEl = document.getElementById("fgFluxoValor");
    if (valorEl) {
      valorEl.value = "";
      valorEl.dataset.value = "0";
    }
    fgState.fluxoEditId = null;
    fgSetFluxoFormModo(false);
    fgInitFormFluxoDatas();
  }

  function fgPreencherFormFluxo(l) {
    if (!l) return;
    fgState.fluxoEditId = l.id;
    fgSetFluxoFormModo(true);
    const setVal = (id, val) => {
      const el = document.getElementById(id);
      if (el) el.value = val ?? "";
    };
    setVal("fgFluxoTipo", l.tipo || "saida");
    setVal("fgFluxoCompetencia", l.data_competencia ? String(l.data_competencia).slice(0, 10) : "");
    setVal("fgFluxoPagamento", l.data_pagamento ? String(l.data_pagamento).slice(0, 10) : "");
    setVal("fgFluxoStatus", l.status || "previsto");
    setVal("fgFluxoUnidade", l.unidade_id || "");
    fgPopularCategoriasFluxo(l.categoria_id || "");
    setVal("fgFluxoCentroCusto", l.centro_custo_id || "");
    fgSetFormaPgtoSelect(l.forma_pagamento || "");
    setVal("fgFluxoDescricao", l.descricao || "");
    setVal("fgFluxoObs", l.observacao || "");
    const valorEl = document.getElementById("fgFluxoValor");
    if (valorEl) {
      const valor = Number(l.valor) || 0;
      valorEl.dataset.value = String(valor);
      valorEl.value = valor > 0 && typeof formatCurrencyBRL === "function" ? formatCurrencyBRL(valor) : (valor > 0 ? String(valor) : "");
    }
    document.getElementById("fgFluxoForm")?.scrollIntoView({ behavior: "smooth", block: "start" });
  }

  // ——— Fluxo de Caixa ———
  function fgPopularCategoriasFluxo(valorAtual) {
    const catSel = document.getElementById("fgFluxoCategoria");
    const tipoSel = document.getElementById("fgFluxoTipo");
    if (!catSel) return;
    const tipo = tipoSel?.value || "saida";
    const cur = valorAtual != null ? String(valorAtual) : catSel.value;
    const lista = (fgState.categorias || []).filter((c) => c.tipo === tipo && c.ativo !== false);
    catSel.innerHTML = '<option value="">Selecione</option>';
    lista.forEach((c) => {
      const o = document.createElement("option");
      o.value = c.id;
      o.textContent = c.nome;
      catSel.appendChild(o);
    });
    if (cur && Array.from(catSel.options).some((o) => o.value === cur)) {
      catSel.value = cur;
    }
  }

  async function fgCarregarAuxFluxo(valorCentroAtual) {
    try {
      const [cats, cc] = await Promise.all([
        fgFetch("/financeiro/categorias"),
        fgFetch("/financeiro/centros-custo?padrao=1"),
      ]);
      fgState.categorias = Array.isArray(cats) ? cats : [];
      fgState.centros = fgFiltrarCentrosPadrao(Array.isArray(cc) ? cc : []);
      const catSel = document.getElementById("fgFluxoCategoria");
      const ccSel = document.getElementById("fgFluxoCentroCusto");
      const curCat = fgState.fluxoEditId
        ? document.getElementById("fgFluxoCategoria")?.value
        : null;
      if (catSel) {
        fgPopularCategoriasFluxo(curCat);
      }
      fgPopularCentroCustoSelect(ccSel, fgState.centros, valorCentroAtual);
    } catch (e) {
      fgToast(e?.message || "Falha ao carregar catálogos.", "error");
    }
  }

  async function loadFinanceiroFluxoCaixa() {
    fgInitFiltrosDatas("fgFluxo");
    fgInitFormFluxoDatas();
    fgInitFluxoFormCollapse();
    fgSetupMoeda();
    await Promise.all([fgCarregarUnidades(["fgFluxoFiltroUnidade", "fgFluxoUnidade"]), fgCarregarAuxFluxo()]);
    const { q } = fgQueryFiltros("fgFluxo");
    const data = await fgFetch(`/financeiro/fluxo-caixa${q}`);
    const rel = data.relatorio || {};
    const resumo = document.getElementById("fgFluxoResumo");
    const proj = document.getElementById("fgFluxoProjecoes");

    if (resumo) {
      resumo.innerHTML = [
        fgCardHtml("Saldo inicial", fmtMoeda(rel.saldo_inicial)),
        fgCardHtml("Entradas", fmtMoeda(rel.entradas)),
        fgCardHtml("Saídas", fmtMoeda(rel.saidas)),
        fgCardHtml("Saldo final", fmtMoeda(rel.saldo_final)),
      ].join("");
    }

    if (proj && rel.projecoes) {
      proj.innerHTML = Object.values(rel.projecoes).map((p) =>
        `<div class="fg-proj-card"><strong>${p.dias} dias</strong><div>${esc(fmtMoeda(p.saldo_projetado))}</div><small>+${esc(fmtMoeda(p.entradas_previstas))} / -${esc(fmtMoeda(p.saidas_previstas))}</small></div>`
      ).join("");
    }

    const lista = data.lancamentos || [];
    fgState.fluxoLancamentos = lista;
    const html = lista.length
      ? lista.map((l) => fgFluxoCard(l)).join("")
      : '<p class="rh-cand-empty">Nenhum lançamento no período.</p>';
    fgMostrarLista("fgFluxoLista", "fgFluxoTbody", html);
  }

  const FG_FLUXO_STATUS = {
    previsto: ["Previsto", "warn"],
    realizado: ["Realizado", "ok"],
    atrasado: ["Atrasado", "danger"],
    cancelado: ["Cancelado", "danger"],
  };

  function fgFluxoCard(l) {
    const entrada = l.tipo === "entrada";
    const [statusLabel] = FG_FLUXO_STATUS[l.status] || [l.status || "—"];
    const titulo = l.descricao || l.categoria_nome || (entrada ? "Entrada" : "Saída");
    const sub = [l.descricao && l.categoria_nome ? l.categoria_nome : "", l.unidade_nome].filter(Boolean).join(" · ");
    const linhas = [
      ["Competência", fmtData(l.data_competencia)],
      ["Valor", fmtMoeda(l.valor)],
      ["Status", statusLabel],
    ];
    if (l.data_pagamento) linhas.push(["Pagamento", fmtData(l.data_pagamento)]);
    if (l.forma_pagamento) linhas.push(["Forma", l.forma_pagamento]);
    if (l.centro_custo_nome) linhas.push(["Centro de custo", l.centro_custo_nome]);
    if (l.observacao) linhas.push(["Observação", l.observacao]);
    const acoes = `<button type="button" class="btn primary" data-fg-fluxo-edit="${esc(l.id)}">Editar</button>
      <button type="button" class="btn" data-fg-fluxo-del="${esc(l.id)}" style="background:#b71c1c;border-color:#b71c1c;color:#fff;">Excluir</button>`;
    return `<article class="rh-cand-card">
      <div class="rh-cand-card__top">
        <div class="usuarios-foto usuarios-foto--placeholder rh-cand-card__photo pat-card-icone" aria-hidden="true">${entrada ? "＋" : "－"}</div>
        <div class="rh-cand-card__main">
          <div class="rh-cand-card__head">
            <div>
              <h3 class="rh-cand-card__name">${esc(titulo)}</h3>
              ${sub ? `<p class="rh-cand-card__sub">${esc(sub)}</p>` : ""}
            </div>
            <span class="rh-cand-pill rh-cand-pill--${entrada ? "ok" : "danger"}">${entrada ? "Entrada" : "Saída"}</span>
          </div>
          <div class="rh-cand-card__meta">
            ${linhas.map(([rotulo, valor]) => `<div class="rh-cand-card__row"><span>${esc(rotulo)}</span><strong>${esc(valor)}</strong></div>`).join("")}
          </div>
        </div>
      </div>
      <div class="rh-cand-card__actions">${acoes}</div>
    </article>`;
  }

  async function fgSalvarFluxo(e) {
    e?.preventDefault();
    const form = document.getElementById("fgFluxoForm");
    if (!form) return;

    const valor = fgLerValorFluxo();
    if (!Number.isFinite(valor) || valor <= 0) {
      fgToast("Informe um valor válido.", "error");
      document.getElementById("fgFluxoValor")?.focus();
      return;
    }

    const dataCompetencia = document.getElementById("fgFluxoCompetencia")?.value || "";
    if (!dataCompetencia) {
      fgToast("Informe a data de competência.", "error");
      document.getElementById("fgFluxoCompetencia")?.focus();
      return;
    }

    const dataPagamento = document.getElementById("fgFluxoPagamento")?.value || null;
    const payload = {
      tipo: document.getElementById("fgFluxoTipo")?.value || "saida",
      valor,
      descricao: document.getElementById("fgFluxoDescricao")?.value || null,
      unidade_id: fgNullableId(document.getElementById("fgFluxoUnidade")?.value),
      categoria_id: fgNullableId(document.getElementById("fgFluxoCategoria")?.value),
      centro_custo_id: fgNullableId(document.getElementById("fgFluxoCentroCusto")?.value),
      forma_pagamento: document.getElementById("fgFluxoFormaPgto")?.value || null,
      data_competencia: dataCompetencia,
      data_pagamento: dataPagamento || null,
      status: document.getElementById("fgFluxoStatus")?.value || "previsto",
      observacao: document.getElementById("fgFluxoObs")?.value || null,
    };

    const submitBtn = document.getElementById("fgFluxoSubmitBtn");
    if (submitBtn) submitBtn.disabled = true;

    try {
      const editId = fgState.fluxoEditId;
      if (editId) {
        await fgFetch(`/financeiro/fluxo-caixa/${editId}`, { method: "PUT", body: JSON.stringify(payload) });
        fgToast(`Lançamento atualizado (${fmtData(dataCompetencia)}).`, "success");
      } else {
        await fgFetch("/financeiro/fluxo-caixa", { method: "POST", body: JSON.stringify(payload) });
        fgToast(`Lançamento salvo (${fmtData(dataCompetencia)}).`, "success");
      }
      fgAjustarFiltroParaCompetencia(dataCompetencia);
      fgLimparFormFluxo();
      await loadFinanceiroFluxoCaixa();
    } catch (err) {
      fgToast(err?.message || "Erro ao salvar.", "error");
    } finally {
      if (submitBtn) submitBtn.disabled = false;
    }
  }

  // ——— Contas a Receber ———
  async function fgCarregarClientes() {
    try {
      fgState.clientes = await fgFetch("/financeiro/clientes");
      if (!Array.isArray(fgState.clientes)) fgState.clientes = [];
    } catch (_) {
      fgState.clientes = [];
    }
    const sel = document.getElementById("fgCrCliente");
    if (sel) {
      sel.innerHTML = '<option value="">—</option>';
      fgState.clientes.forEach((c) => {
        const o = document.createElement("option");
        o.value = c.id;
        o.textContent = c.nome;
        sel.appendChild(o);
      });
    }
  }

  async function loadFinanceiroContasReceber() {
    fgInitFiltrosDatas("fgCr");
    await Promise.all([fgCarregarUnidades(["fgCrFiltroUnidade", "fgCrUnidade"]), fgCarregarClientes()]);
    const { q } = fgQueryFiltros("fgCr");
    const data = await fgFetch(`/financeiro/contas-receber${q}`);
    const tbody = document.getElementById("fgCrTbody");
    const inad = document.getElementById("fgCrInadimplencia");
    if (inad) {
      inad.innerHTML = [
        fgCardHtml("Vencidas (qtd)", String(data.inadimplencia?.quantidade ?? 0)),
        fgCardHtml("Valor vencido", fmtMoeda(data.inadimplencia?.valor)),
        fgCardHtml("Previstos", fmtMoeda(data.recebimentos_previstos)),
      ].join("");
    }
    const lista = data.contas || [];
    const html = lista.length
      ? lista.map((c) => fgCrCard(c)).join("")
      : '<p class="rh-cand-empty">Nenhuma conta no período.</p>';
    if (tbody || document.getElementById("fgCrLista")) {
      fgMostrarLista("fgCrLista", "fgCrTbody", html);
    }
  }

  function fgCrCard(c) {
    const status = String(c.status || "").toLowerCase();
    const statusLabel = status === "recebido" ? "Recebido" : status === "vencido" ? "Vencido" : status === "aberto" ? "Aberto" : (c.status || "—");
    const pill = status === "recebido" ? "ok" : status === "vencido" ? "danger" : status === "aberto" ? "warn" : "info";
    const titulo = c.cliente_nome || "Cliente";
    const sub = [c.descricao, c.parcela_num ? `Parcela ${c.parcela_num}/${c.total_parcelas}` : ""].filter(Boolean).join(" · ");
    const linhas = [
      ["Vencimento", fmtData(c.data_vencimento)],
      ["Valor", fmtMoeda(c.valor)],
      ["Status", statusLabel],
    ];
    if (c.forma_recebimento) linhas.push(["Forma", c.forma_recebimento]);
    if (c.unidade_nome) linhas.push(["Unidade", c.unidade_nome]);
    if (c.observacao) linhas.push(["Observação", c.observacao]);
    const acao = status !== "recebido"
      ? `<div class="rh-cand-card__actions"><button type="button" class="btn btn-rh-toggle" data-fg-cr-rec="${esc(c.id)}">Receber</button></div>`
      : "";
    return `<article class="rh-cand-card">
      <div class="rh-cand-card__top">
        <div class="usuarios-foto usuarios-foto--placeholder rh-cand-card__photo pat-card-icone" aria-hidden="true">📥</div>
        <div class="rh-cand-card__main">
          <div class="rh-cand-card__head">
            <div>
              <h3 class="rh-cand-card__name">${esc(titulo)}</h3>
              ${sub ? `<p class="rh-cand-card__sub">${esc(sub)}</p>` : ""}
            </div>
            <span class="rh-cand-pill rh-cand-pill--${pill}">${esc(statusLabel)}</span>
          </div>
          <div class="rh-cand-card__meta">
            ${linhas.map(([rotulo, valor]) => `<div class="rh-cand-card__row"><span>${esc(rotulo)}</span><strong>${esc(valor)}</strong></div>`).join("")}
          </div>
        </div>
      </div>
      ${acao}
    </article>`;
  }

  async function fgSalvarContaReceber(e) {
    e?.preventDefault();
    const valorEl = document.getElementById("fgCrValor");
    const valor = typeof parseCurrencyInput === "function" ? parseCurrencyInput(valorEl) : Number(valorEl?.value);
    if (!Number.isFinite(valor) || valor <= 0) {
      fgToast("Valor inválido.", "error");
      return;
    }
    const payload = {
      cliente_id: document.getElementById("fgCrCliente")?.value || null,
      unidade_id: document.getElementById("fgCrUnidade")?.value || null,
      descricao: document.getElementById("fgCrDescricao")?.value,
      valor,
      data_vencimento: document.getElementById("fgCrVencimento")?.value,
      total_parcelas: Number(document.getElementById("fgCrParcelas")?.value || 1),
      forma_recebimento: document.getElementById("fgCrForma")?.value,
      observacao: document.getElementById("fgCrObs")?.value,
    };
    try {
      await fgFetch("/financeiro/contas-receber", { method: "POST", body: JSON.stringify(payload) });
      fgToast("Conta a receber lançada.", "success");
      document.getElementById("fgCrForm")?.reset();
      loadFinanceiroContasReceber().catch(() => {});
    } catch (err) {
      fgToast(err?.message || "Erro ao salvar.", "error");
    }
  }

  // ——— DRE ———
  async function loadFinanceiroDre() {
    fgInitFiltrosDatas("fgDre");
    await fgCarregarUnidades(["fgDreFiltroUnidade"]);
    const { q } = fgQueryFiltros("fgDre");
    const data = await fgFetch(`/financeiro/dre${q}`);
    const d = data.dre || {};
    const linhas = [
      ["Receita bruta", d.receita_bruta],
      ["(−) Deduções / impostos", d.deducoes_impostos, true],
      ["Receita líquida", d.receita_liquida, false, true],
      ["(−) Custo saídas estoque", d.cmv, true],
      ["Lucro bruto", d.lucro_bruto, false, true],
      ["(−) Despesas operacionais", d.despesas_operacionais, true],
      ["Folha / proventos", d.folha_proventos, true, false, "Despesa operacional"],
      ["Despesas fixas", d.despesas_fixas, true, false, "Despesa operacional"],
      ["(−) Outras despesas", d.outras_despesas, true],
      ["Resultado operacional", d.resultado_operacional, false, true],
      ["Investimentos / reservas", d.investimentos_reservas],
      ["Lucro líquido", d.lucro_liquido, false, true],
    ];
    fgMostrarLista("fgDreLista", "fgDreTbody", linhas.map((linha) => fgDreCard(...linha)).join(""));
  }

  function fgDreCard(nome, val, neg, total, sub) {
    const n = Number(val);
    const valor = neg && Number.isFinite(n) ? `(${fmtMoeda(n)})` : fmtMoeda(val);
    const pill = neg ? "danger" : (total ? (Number.isFinite(n) && n < 0 ? "danger" : "ok") : "info");
    const titulo = String(nome || "").replace(/^[−\-]\s*/, "").replace(/^ /, "");
    const detalhe = sub || (total ? "Resultado" : (neg ? "Dedução" : "Conta"));
    const icone = neg ? "－" : (total ? "＝" : "＋");
    return `<article class="rh-cand-card">
      <div class="rh-cand-card__top">
        <div class="usuarios-foto usuarios-foto--placeholder rh-cand-card__photo pat-card-icone" aria-hidden="true">${icone}</div>
        <div class="rh-cand-card__main">
          <div class="rh-cand-card__head">
            <div>
              <h3 class="rh-cand-card__name">${esc(titulo)}</h3>
              <p class="rh-cand-card__sub">${esc(detalhe)}</p>
            </div>
            <span class="rh-cand-pill rh-cand-pill--${pill}">${esc(valor)}</span>
          </div>
          <div class="rh-cand-card__meta">
            <div class="rh-cand-card__row"><span>Valor</span><strong>${esc(valor)}</strong></div>
          </div>
        </div>
      </div>
    </article>`;
  }

  // ——— Custo de saídas de estoque ———
  async function loadFinanceiroCmv() {
    fgInitFiltrosDatas("fgCmv");
    await fgCarregarUnidades(["fgCmvFiltroUnidade"]);
    const { q } = fgQueryFiltros("fgCmv");
    const data = await fgFetch(`/financeiro/cmv${q}`);
    const cards = document.getElementById("fgCmvCards");
    const motivoTbody = document.getElementById("fgCmvMotivoTbody");
    const prodTbody = document.getElementById("fgCmvProdutoTbody");
    const uniTbody = document.getElementById("fgCmvUnidadeTbody");
    const obsEl = document.getElementById("fgCmvObservacao");
    const custoTotal = data.custo_saidas_total ?? data.cmv_total ?? 0;
    const margem = data.margem_estimada;
    const margemCls = margem != null && Number(margem) >= 0 ? "fg-card__value--pos" : "fg-card__value--neg";
    if (cards) {
      cards.innerHTML = [
        fgCardHtml("Custo total (saídas)", fmtMoeda(custoTotal)),
        fgCardHtml("Faturamento (caixa)", fmtMoeda(data.faturamento)),
        fgCardHtml("Margem estimada", fmtMoeda(margem), margemCls),
        fgCardHtml("% custo / faturamento", `${data.percentual_custo_sobre_faturamento ?? data.percentual_sobre_faturamento ?? 0}%`),
        fgCardHtml("Saídas sem custo", String(data.saidas_sem_custo ?? 0)),
      ].join("");
    }
    if (obsEl) {
      obsEl.textContent = data.observacao || "";
    }
    if (motivoTbody) {
      const rows = data.por_motivo || [];
      motivoTbody.innerHTML = rows.length
        ? rows.map((r) => `<tr><td>${esc(r.motivo_label || r.motivo)}</td><td>${esc(fmtMoeda(r.custo))}</td></tr>`).join("")
        : `<tr><td colspan="2" class="empty-row">Sem saídas no período.</td></tr>`;
    }
    if (prodTbody) {
      const rows = (data.por_produto || []).slice(0, 50);
      prodTbody.innerHTML = rows.length
        ? rows.map((r) => `<tr><td>${esc(r.produto_nome)}</td><td>${esc(fmtMoeda(r.cmv))}</td></tr>`).join("")
        : `<tr><td colspan="2" class="empty-row">Sem saídas com custo no período.</td></tr>`;
    }
    if (uniTbody) {
      const rows = data.por_unidade || [];
      uniTbody.innerHTML = rows.length
        ? rows.map((r) => `<tr><td>${esc(r.unidade_nome)}</td><td>${esc(fmtMoeda(r.cmv))}</td></tr>`).join("")
        : `<tr><td colspan="2" class="empty-row">—</td></tr>`;
    }
  }

  // ——— Centros de Custo ———
  async function loadFinanceiroCentrosCusto() {
    const lista = await fgFetch("/financeiro/centros-custo?padrao=1");
    const tbody = document.getElementById("fgCcTbody");
    const rows = fgFiltrarCentrosPadrao(Array.isArray(lista) ? lista : []);
    const html = rows.length
      ? rows.map((c) => fgCcCard(c)).join("")
      : '<p class="rh-cand-empty">Nenhum centro cadastrado.</p>';
    if (tbody || document.getElementById("fgCcLista")) {
      fgMostrarLista("fgCcLista", "fgCcTbody", html);
    }
  }

  function fgCcCard(c) {
    const nome = c.nome || "Centro de custo";
    const codigo = c.codigo || "—";
    return `<article class="rh-cand-card">
      <div class="rh-cand-card__top">
        <div class="usuarios-foto usuarios-foto--placeholder rh-cand-card__photo pat-card-icone" aria-hidden="true">🏷️</div>
        <div class="rh-cand-card__main">
          <div class="rh-cand-card__head">
            <div>
              <h3 class="rh-cand-card__name">${esc(nome)}</h3>
              <p class="rh-cand-card__sub">Centro de custo</p>
            </div>
            <span class="rh-cand-pill rh-cand-pill--ok">Ativo</span>
          </div>
          <div class="rh-cand-card__meta">
            <div class="rh-cand-card__row"><span>Código</span><strong>${esc(codigo)}</strong></div>
            <div class="rh-cand-card__row"><span>Nome</span><strong>${esc(nome)}</strong></div>
            <div class="rh-cand-card__row"><span>Status</span><strong>Ativo</strong></div>
          </div>
        </div>
      </div>
    </article>`;
  }

  async function fgSalvarCentroCusto(e) {
    e?.preventDefault();
    const nome = document.getElementById("fgCcNome")?.value?.trim();
    if (!nome) {
      fgToast("Informe o nome.", "error");
      return;
    }
    try {
      await fgFetch("/financeiro/centros-custo", {
        method: "POST",
        body: JSON.stringify({ nome, codigo: document.getElementById("fgCcCodigo")?.value }),
      });
      fgToast("Centro de custo salvo.", "success");
      document.getElementById("fgCcForm")?.reset();
      loadFinanceiroCentrosCusto().catch(() => {});
    } catch (err) {
      fgToast(err?.message || "Erro.", "error");
    }
  }

  // ——— Orçamento ———
  async function loadFinanceiroOrcamento() {
    await fgCarregarUnidades(["fgOrcFiltroUnidade"]);
    const compEl = document.getElementById("fgOrcCompetencia");
    if (compEl && !compEl.value) {
      const h = new Date();
      compEl.value = `${h.getFullYear()}-${String(h.getMonth() + 1).padStart(2, "0")}`;
    }
    let q = `?competencia=${encodeURIComponent(compEl?.value || "")}`;
    const uni = document.getElementById("fgOrcFiltroUnidade")?.value;
    if (uni) q += `&unidade_id=${uni}`;
    const data = await fgFetch(`/financeiro/orcamento${q}`);
    const comp = data.comparativo || {};
    const meta = comp.meta || {};
    const real = comp.realizado || {};
    const variacao = comp.variacao || {};
    const comparativoHtml = [
      fgOrcComparativoCard("Faturamento", meta.meta_faturamento, real.faturamento, variacao.faturamento, true),
      fgOrcComparativoCard("Despesa", meta.meta_despesa, real.despesa, variacao.despesa, false),
      fgOrcComparativoCard("Lucro", meta.meta_lucro, real.lucro, variacao.lucro, true),
    ].join("");
    fgMostrarLista("fgOrcLista", "fgOrcCards", comparativoHtml);
    const meses = data.evolucao_mensal || [];
    const evolucaoHtml = meses.length
      ? meses.map((e) => fgOrcMesCard(e)).join("")
      : '<p class="rh-cand-empty">Cadastre metas mensais para ver a evolução.</p>';
    fgMostrarLista("fgOrcEvolucao", "fgOrcChart", evolucaoHtml);
    const metaFat = document.getElementById("fgOrcMetaFat");
    const metaDesp = document.getElementById("fgOrcMetaDesp");
    const metaLuc = document.getElementById("fgOrcMetaLucro");
    if (comp.meta) {
      if (metaFat) metaFat.value = comp.meta.meta_faturamento || "";
      if (metaDesp) metaDesp.value = comp.meta.meta_despesa || "";
      if (metaLuc) metaLuc.value = comp.meta.meta_lucro || "";
    }
  }

  function fgOrcComparativoCard(titulo, metaValor, realValor, variacao, melhorQuandoMaior) {
    const metaN = Number(metaValor) || 0;
    const realN = Number(realValor) || 0;
    const dif = Number(variacao);
    const difN = Number.isFinite(dif) ? dif : realN - metaN;
    const ok = melhorQuandoMaior ? difN >= 0 : difN <= 0;
    const sinal = difN > 0 ? "+" : "";
    return `<article class="rh-cand-card">
      <div class="rh-cand-card__top">
        <div class="usuarios-foto usuarios-foto--placeholder rh-cand-card__photo pat-card-icone" aria-hidden="true">🎯</div>
        <div class="rh-cand-card__main">
          <div class="rh-cand-card__head">
            <div>
              <h3 class="rh-cand-card__name">${esc(titulo)}</h3>
              <p class="rh-cand-card__sub">Meta e realizado</p>
            </div>
            <span class="rh-cand-pill rh-cand-pill--${ok ? "ok" : "danger"}">${esc(sinal + fmtMoeda(difN))}</span>
          </div>
          <div class="rh-cand-card__meta">
            <div class="rh-cand-card__row"><span>Meta</span><strong>${esc(fmtMoeda(metaN))}</strong></div>
            <div class="rh-cand-card__row"><span>Realizado</span><strong>${esc(fmtMoeda(realN))}</strong></div>
            <div class="rh-cand-card__row"><span>Variação</span><strong>${esc(sinal + fmtMoeda(difN))}</strong></div>
          </div>
        </div>
      </div>
    </article>`;
  }

  function fgOrcMesCard(e) {
    const metaN = Number(e.meta_faturamento) || 0;
    const realN = Number(e.realizado_faturamento) || 0;
    const comp = String(e.competencia || "");
    const [ano, mes] = comp.split("-");
    const titulo = mes && ano ? `${mes}/${ano}` : (comp || "Mês");
    return `<article class="rh-cand-card">
      <div class="rh-cand-card__top">
        <div class="usuarios-foto usuarios-foto--placeholder rh-cand-card__photo pat-card-icone" aria-hidden="true">📅</div>
        <div class="rh-cand-card__main">
          <div class="rh-cand-card__head">
            <div>
              <h3 class="rh-cand-card__name">${esc(titulo)}</h3>
              <p class="rh-cand-card__sub">Faturamento do mês</p>
            </div>
            <span class="rh-cand-pill rh-cand-pill--${realN >= metaN ? "ok" : "warn"}">${esc(fmtMoeda(realN))}</span>
          </div>
          <div class="rh-cand-card__meta">
            <div class="rh-cand-card__row"><span>Meta</span><strong>${esc(fmtMoeda(metaN))}</strong></div>
            <div class="rh-cand-card__row"><span>Realizado</span><strong>${esc(fmtMoeda(realN))}</strong></div>
          </div>
        </div>
      </div>
    </article>`;
  }

  async function fgSalvarOrcamento(e) {
    e?.preventDefault();
    const payload = {
      competencia: document.getElementById("fgOrcCompetencia")?.value,
      unidade_id: document.getElementById("fgOrcFiltroUnidade")?.value || null,
      meta_faturamento: Number(document.getElementById("fgOrcMetaFat")?.value || 0),
      meta_despesa: Number(document.getElementById("fgOrcMetaDesp")?.value || 0),
      meta_lucro: Number(document.getElementById("fgOrcMetaLucro")?.value || 0),
    };
    try {
      await fgFetch("/financeiro/orcamento", { method: "POST", body: JSON.stringify(payload) });
      fgToast("Orçamento salvo.", "success");
      loadFinanceiroOrcamento().catch(() => {});
    } catch (err) {
      fgToast(err?.message || "Erro.", "error");
    }
  }

  // ——— Indicadores ———
  async function loadFinanceiroIndicadores() {
    fgInitFiltrosDatas("fgInd");
    await fgCarregarUnidades(["fgIndFiltroUnidade"]);
    const { q } = fgQueryFiltros("fgInd");
    const data = await fgFetch(`/financeiro/indicadores${q}`);
    const ind = data.indicadores || {};
    const saude = ind.saude_financeira || {};
    const saudeStatus = saude.status || "atencao";
    const saudePill = saudeStatus === "saudavel" ? "ok" : saudeStatus === "atencao" ? "warn" : "danger";
    const liquidez = Number(ind.liquidez);
    const liquidezPill = !Number.isFinite(liquidez) ? "info" : liquidez >= 1 ? "ok" : liquidez >= 0.5 ? "warn" : "danger";
    const margemLiq = Number(ind.margem_liquida) || 0;
    const margemBruta = Number(ind.margem_bruta) || 0;
    const endiv = Number(ind.endividamento) || 0;
    const endivPill = endiv <= 20 ? "ok" : endiv <= 40 ? "warn" : "danger";
    const giro = Number(ind.capital_giro) || 0;
    const html = [
      fgIndCard("Saúde financeira", "Visão geral do período", "💚", saudePill, saude.label || "Atenção", [["Pontuação", `${saude.percentual ?? 0}%`]]),
      fgIndCard("Liquidez", "Caixa dividido pelas despesas", "💧", liquidezPill, Number.isFinite(liquidez) ? String(liquidez) : "—", [["Índice", Number.isFinite(liquidez) ? String(liquidez) : "—"]]),
      fgIndCard("Margem líquida", "Lucro sobre o faturamento", "📈", margemLiq >= 0 ? "ok" : "danger", `${margemLiq}%`, [["Percentual", `${margemLiq}%`]]),
      fgIndCard("Margem bruta", "Faturamento menos o custo", "📊", margemBruta >= 0 ? "ok" : "danger", `${margemBruta}%`, [["Percentual", `${margemBruta}%`]]),
      fgIndCard("Endividamento", "Contas vencidas sobre o faturamento", "⚖️", endivPill, `${endiv}%`, [["Percentual", `${endiv}%`]]),
      fgIndCard("Capital de giro", "Caixa e a receber, menos a pagar", "💰", giro >= 0 ? "ok" : "danger", fmtMoeda(giro), [["Valor", fmtMoeda(giro)]]),
      fgIndCard("Ponto de equilíbrio", "Faturamento para cobrir os custos", "🎯", "info", ind.ponto_equilibrio != null ? fmtMoeda(ind.ponto_equilibrio) : "—", [["Valor", ind.ponto_equilibrio != null ? fmtMoeda(ind.ponto_equilibrio) : "—"]]),
    ].join("");
    fgMostrarLista("fgIndLista", "fgIndCards", html);
  }

  function fgIndCard(titulo, sub, icone, pillClass, pillText, rows) {
    const meta = (rows || []).map(([nome, valor]) => `<div class="rh-cand-card__row"><span>${esc(nome)}</span><strong>${esc(valor)}</strong></div>`).join("");
    return `<article class="rh-cand-card">
      <div class="rh-cand-card__top">
        <div class="usuarios-foto usuarios-foto--placeholder rh-cand-card__photo pat-card-icone" aria-hidden="true">${icone}</div>
        <div class="rh-cand-card__main">
          <div class="rh-cand-card__head">
            <div>
              <h3 class="rh-cand-card__name">${esc(titulo)}</h3>
              <p class="rh-cand-card__sub">${esc(sub)}</p>
            </div>
            <span class="rh-cand-pill rh-cand-pill--${pillClass}">${esc(pillText)}</span>
          </div>
          <div class="rh-cand-card__meta">${meta}</div>
        </div>
      </div>
    </article>`;
  }

  function fgSetupMoeda() {
    document.querySelectorAll("[data-fg-moeda]").forEach((inp) => {
      if (inp.dataset.fgMoedaBound === "1") return;
      inp.dataset.fgMoedaBound = "1";
      if (typeof attachCurrencyMask === "function") attachCurrencyMask(inp);
    });
  }

  function fgBindEvents() {
    if (window.__fgBound) return;
    window.__fgBound = true;

    document.getElementById("fgDashAtualizar")?.addEventListener("click", () => loadFinanceiroDashboard().catch((e) => fgToast(e?.message, "error")));
    document.getElementById("fgFluxoAtualizar")?.addEventListener("click", () => loadFinanceiroFluxoCaixa().catch((e) => fgToast(e?.message, "error")));
    document.getElementById("fgFluxoForm")?.addEventListener("submit", fgSalvarFluxo);
    document.getElementById("fgFluxoCancelBtn")?.addEventListener("click", () => fgLimparFormFluxo());
    document.getElementById("fgFluxoTipo")?.addEventListener("change", () => fgPopularCategoriasFluxo());
    document.getElementById("fgCrAtualizar")?.addEventListener("click", () => loadFinanceiroContasReceber().catch((e) => fgToast(e?.message, "error")));
    document.getElementById("fgCrForm")?.addEventListener("submit", fgSalvarContaReceber);
    document.getElementById("fgDreAtualizar")?.addEventListener("click", () => loadFinanceiroDre().catch((e) => fgToast(e?.message, "error")));
    document.getElementById("fgCmvAtualizar")?.addEventListener("click", () => loadFinanceiroCmv().catch((e) => fgToast(e?.message, "error")));
    document.getElementById("fgCcForm")?.addEventListener("submit", fgSalvarCentroCusto);
    document.getElementById("fgOrcAtualizar")?.addEventListener("click", () => loadFinanceiroOrcamento().catch((e) => fgToast(e?.message, "error")));
    document.getElementById("fgOrcForm")?.addEventListener("submit", fgSalvarOrcamento);
    document.getElementById("fgIndAtualizar")?.addEventListener("click", () => loadFinanceiroIndicadores().catch((e) => fgToast(e?.message, "error")));

    document.getElementById("fgCrNovoCliente")?.addEventListener("click", async () => {
      const nome = prompt("Nome do cliente:");
      if (!nome?.trim()) return;
      try {
        await fgFetch("/financeiro/clientes", { method: "POST", body: JSON.stringify({ nome: nome.trim() }) });
        await fgCarregarClientes();
        fgToast("Cliente cadastrado.", "success");
      } catch (e) {
        fgToast(e?.message, "error");
      }
    });

    document.addEventListener("click", async (ev) => {
      const edit = ev.target.closest("[data-fg-fluxo-edit]");
      if (edit) {
        const id = Number(edit.getAttribute("data-fg-fluxo-edit"));
        const lanc = fgState.fluxoLancamentos.find((l) => Number(l.id) === id);
        if (!lanc) {
          fgToast("Lançamento não encontrado.", "error");
          return;
        }
        await fgCarregarAuxFluxo(lanc.centro_custo_id);
        fgPreencherFormFluxo(lanc);
        return;
      }
      const del = ev.target.closest("[data-fg-fluxo-del]");
      if (del) {
        const id = del.getAttribute("data-fg-fluxo-del");
        if (!id || !confirm("Excluir lançamento? (registro mantido em auditoria)")) return;
        try {
          await fgFetch(`/financeiro/fluxo-caixa/${id}`, { method: "DELETE" });
          if (Number(fgState.fluxoEditId) === Number(id)) fgLimparFormFluxo();
          loadFinanceiroFluxoCaixa().catch(() => {});
        } catch (e) {
          fgToast(e?.message, "error");
        }
      }
      const rec = ev.target.closest("[data-fg-cr-rec]");
      if (rec) {
        const id = rec.getAttribute("data-fg-cr-rec");
        try {
          await fgFetch(`/financeiro/contas-receber/${id}`, {
            method: "PUT",
            body: JSON.stringify({ status: "recebido", data_recebimento: new Date().toISOString().slice(0, 10) }),
          });
          loadFinanceiroContasReceber().catch(() => {});
        } catch (e) {
          fgToast(e?.message, "error");
        }
      }
    });
  }

  fgBindEvents();
  fgSetupMoeda();

  window.loadFinanceiroDashboard = loadFinanceiroDashboard;
  window.loadFinanceiroFluxoCaixa = loadFinanceiroFluxoCaixa;
  window.loadFinanceiroContasReceber = loadFinanceiroContasReceber;
  window.loadFinanceiroDre = loadFinanceiroDre;
  window.loadFinanceiroCmv = loadFinanceiroCmv;
  window.loadFinanceiroCentrosCusto = loadFinanceiroCentrosCusto;
  window.loadFinanceiroOrcamento = loadFinanceiroOrcamento;
  window.loadFinanceiroIndicadores = loadFinanceiroIndicadores;
})();
