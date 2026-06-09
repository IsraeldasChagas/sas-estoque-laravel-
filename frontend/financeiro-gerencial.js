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
    const uniTbody = document.getElementById("fgDashUnidadeTbody");
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
      fgCardHtml("CMV estimado", fmtMoeda(data.cmv_estimado)),
      fgCardHtml("Margem líquida", `${data.margem_liquida ?? 0}%`),
      fgCardHtml("Ponto de equilíbrio", data.ponto_equilibrio != null ? fmtMoeda(data.ponto_equilibrio) : "—"),
    ].join("");

    if (saudeEl) saudeEl.innerHTML = fgSaudeHtml(data.saude_financeira);

    if (uniTbody) {
      const rows = data.faturamento_por_unidade || [];
      uniTbody.innerHTML = rows.length
        ? rows.map((r) => `<tr><td>${esc(r.unidade_nome)}</td><td>${esc(fmtMoeda(r.faturamento))}</td></tr>`).join("")
        : `<tr><td colspan="2" class="empty-row">Sem dados no período.</td></tr>`;
    }
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
    const tbody = document.getElementById("fgFluxoTbody");
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
    if (tbody) {
      tbody.innerHTML = lista.length
        ? lista.map((l) => {
            const tipoCls = l.tipo === "entrada" ? "fg-pill--entrada" : "fg-pill--saida";
            const tipoLabel = l.tipo === "entrada" ? "Entrada" : "Saída";
            return `<tr>
            <td>${esc(fmtData(l.data_competencia))}</td>
            <td><span class="status-pill ${tipoCls}">${esc(tipoLabel)}</span></td>
            <td>${esc(l.descricao || l.categoria_nome || "—")}</td>
            <td>${esc(l.unidade_nome || "—")}</td>
            <td>${esc(fmtMoeda(l.valor))}</td>
            <td>${esc(l.status)}</td>
            <td class="fg-acoes">
              <button type="button" class="btn btn-sm fg-btn-edit" data-fg-fluxo-edit="${l.id}" title="Editar">Editar</button>
              <button type="button" class="btn btn-sm fg-btn-del" data-fg-fluxo-del="${l.id}" title="Excluir">Excluir</button>
            </td>
          </tr>`;
          }).join("")
        : `<tr><td colspan="7" class="empty-row">Nenhum lançamento no período.</td></tr>`;
    }
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
    if (tbody) {
      tbody.innerHTML = lista.length
        ? lista.map((c) => `<tr>
            <td>${esc(c.cliente_nome || "—")}</td>
            <td>${esc(c.descricao || "—")}</td>
            <td>${c.parcela_num}/${c.total_parcelas}</td>
            <td>${esc(fmtData(c.data_vencimento))}</td>
            <td>${esc(fmtMoeda(c.valor))}</td>
            <td>${esc(c.status)}</td>
            <td>${c.status !== "recebido" ? `<button type="button" class="btn btn-sm primary" data-fg-cr-rec="${c.id}">Receber</button>` : "—"}</td>
          </tr>`).join("")
        : `<tr><td colspan="7" class="empty-row">Nenhuma conta no período.</td></tr>`;
    }
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
    const tbody = document.getElementById("fgDreTbody");
    const d = data.dre || {};
    const linhas = [
      ["Receita bruta", d.receita_bruta],
      ["(−) Deduções / impostos", d.deducoes_impostos, true],
      ["Receita líquida", d.receita_liquida, false, true],
      ["(−) CMV", d.cmv, true],
      ["Lucro bruto", d.lucro_bruto, false, true],
      ["(−) Despesas operacionais", d.despesas_operacionais, true],
      [" Folha / proventos", d.folha_proventos, true],
      [" Despesas fixas", d.despesas_fixas, true],
      ["(−) Outras despesas", d.outras_despesas, true],
      ["Resultado operacional", d.resultado_operacional, false, true],
      ["Investimentos / reservas", d.investimentos_reservas],
      ["Lucro líquido", d.lucro_liquido, false, true],
    ];
    if (tbody) {
      tbody.innerHTML = linhas.map(([nome, val, neg, total]) => {
        const cls = total ? "fg-dre-total" : "";
        const v = neg && val ? `(${fmtMoeda(val)})` : fmtMoeda(val);
        return `<tr class="${cls}"><td>${esc(nome)}</td><td>${esc(v)}</td></tr>`;
      }).join("");
    }
  }

  // ——— CMV ———
  async function loadFinanceiroCmv() {
    fgInitFiltrosDatas("fgCmv");
    await fgCarregarUnidades(["fgCmvFiltroUnidade"]);
    const { q } = fgQueryFiltros("fgCmv");
    const data = await fgFetch(`/financeiro/cmv${q}`);
    const cards = document.getElementById("fgCmvCards");
    const prodTbody = document.getElementById("fgCmvProdutoTbody");
    const uniTbody = document.getElementById("fgCmvUnidadeTbody");
    if (cards) {
      cards.innerHTML = [
        fgCardHtml("CMV total", fmtMoeda(data.cmv_total)),
        fgCardHtml("Faturamento", fmtMoeda(data.faturamento)),
        fgCardHtml("% CMV / faturamento", `${data.percentual_sobre_faturamento ?? 0}%`),
      ].join("");
    }
    if (prodTbody) {
      const rows = (data.por_produto || []).slice(0, 50);
      prodTbody.innerHTML = rows.length
        ? rows.map((r) => `<tr><td>${esc(r.produto_nome)}</td><td>${esc(fmtMoeda(r.cmv))}</td></tr>`).join("")
        : `<tr><td colspan="2" class="empty-row">Sem saídas de estoque no período.</td></tr>`;
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
    if (tbody) {
      tbody.innerHTML = rows.length
        ? rows.map((c) => `<tr><td>${esc(c.codigo || "—")}</td><td>${esc(c.nome)}</td><td>Ativo</td></tr>`).join("")
        : `<tr><td colspan="3" class="empty-row">Nenhum centro cadastrado.</td></tr>`;
    }
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
    const cards = document.getElementById("fgOrcCards");
    if (cards) {
      cards.innerHTML = [
        fgCardHtml("Meta faturamento", fmtMoeda(comp.meta?.meta_faturamento)),
        fgCardHtml("Realizado", fmtMoeda(comp.realizado?.faturamento)),
        fgCardHtml("Meta despesa", fmtMoeda(comp.meta?.meta_despesa)),
        fgCardHtml("Despesa real", fmtMoeda(comp.realizado?.despesa)),
        fgCardHtml("Meta lucro", fmtMoeda(comp.meta?.meta_lucro)),
        fgCardHtml("Lucro real", fmtMoeda(comp.realizado?.lucro)),
      ].join("");
    }
    const chart = document.getElementById("fgOrcChart");
    if (chart && data.evolucao_mensal?.length) {
      const max = Math.max(...data.evolucao_mensal.map((e) => Math.max(e.meta_faturamento, e.realizado_faturamento)), 1);
      chart.innerHTML = `<div class="fg-chart-bar">${data.evolucao_mensal.map((e) => {
        const h = Math.round((e.realizado_faturamento / max) * 100);
        return `<div class="fg-chart-bar__col"><div class="fg-chart-bar__fill" style="height:${h}%"></div><span class="fg-chart-bar__lbl">${esc(e.competencia?.slice(5) || "")}</span></div>`;
      }).join("")}</div>`;
    } else if (chart) {
      chart.innerHTML = "<p class='subtle-text'>Cadastre metas mensais para ver a evolução.</p>";
    }
    const metaFat = document.getElementById("fgOrcMetaFat");
    const metaDesp = document.getElementById("fgOrcMetaDesp");
    const metaLuc = document.getElementById("fgOrcMetaLucro");
    if (comp.meta) {
      if (metaFat) metaFat.value = comp.meta.meta_faturamento || "";
      if (metaDesp) metaDesp.value = comp.meta.meta_despesa || "";
      if (metaLuc) metaLuc.value = comp.meta.meta_lucro || "";
    }
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
    const cards = document.getElementById("fgIndCards");
    const saudeEl = document.getElementById("fgIndSaude");
    if (cards) {
      cards.innerHTML = [
        fgCardHtml("Liquidez (caixa / despesas)", String(ind.liquidez ?? "—")),
        fgCardHtml("Margem líquida", `${ind.margem_liquida ?? 0}%`),
        fgCardHtml("Margem bruta", `${ind.margem_bruta ?? 0}%`),
        fgCardHtml("Endividamento", `${ind.endividamento ?? 0}%`),
        fgCardHtml("Capital de giro", fmtMoeda(ind.capital_giro)),
        fgCardHtml("Ponto de equilíbrio", ind.ponto_equilibrio != null ? fmtMoeda(ind.ponto_equilibrio) : "—"),
      ].join("");
    }
    if (saudeEl) saudeEl.innerHTML = fgSaudeHtml(ind.saude_financeira);
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
