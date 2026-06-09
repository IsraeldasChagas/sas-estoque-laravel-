/**
 * Módulo Financeiro Gerencial — SAS-Estoque / Grupo Sabor Paraense
 */
(function () {
  "use strict";

  const fgState = { unidades: [], categorias: [], centros: [], clientes: [] };

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

  // ——— Fluxo de Caixa ———
  async function fgCarregarAuxFluxo() {
    try {
      const [cats, cc] = await Promise.all([
        fgFetch("/financeiro/categorias"),
        fgFetch("/financeiro/centros-custo"),
      ]);
      fgState.categorias = Array.isArray(cats) ? cats : [];
      fgState.centros = Array.isArray(cc) ? cc : [];
      const catSel = document.getElementById("fgFluxoCategoria");
      const ccSel = document.getElementById("fgFluxoCentroCusto");
      if (catSel) {
        catSel.innerHTML = '<option value="">—</option>';
        fgState.categorias.forEach((c) => {
          const o = document.createElement("option");
          o.value = c.id;
          o.textContent = `${c.nome} (${c.tipo})`;
          catSel.appendChild(o);
        });
      }
      if (ccSel) {
        ccSel.innerHTML = '<option value="">—</option>';
        fgState.centros.forEach((c) => {
          const o = document.createElement("option");
          o.value = c.id;
          o.textContent = c.nome;
          ccSel.appendChild(o);
        });
      }
    } catch (e) {
      fgToast(e?.message || "Falha ao carregar catálogos.", "error");
    }
  }

  async function loadFinanceiroFluxoCaixa() {
    fgInitFiltrosDatas("fgFluxo");
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
    if (tbody) {
      tbody.innerHTML = lista.length
        ? lista.map((l) => `<tr>
            <td>${esc(fmtData(l.data_competencia))}</td>
            <td><span class="status-pill">${esc(l.tipo)}</span></td>
            <td>${esc(l.descricao || l.categoria_nome || "—")}</td>
            <td>${esc(l.unidade_nome || "—")}</td>
            <td>${esc(fmtMoeda(l.valor))}</td>
            <td>${esc(l.status)}</td>
            <td><button type="button" class="btn btn-sm" data-fg-fluxo-del="${l.id}">Excluir</button></td>
          </tr>`).join("")
        : `<tr><td colspan="7" class="empty-row">Nenhum lançamento no período.</td></tr>`;
    }
  }

  async function fgSalvarFluxo(e) {
    e?.preventDefault();
    const form = document.getElementById("fgFluxoForm");
    if (!form) return;
    const valorRaw = document.getElementById("fgFluxoValor")?.value || "0";
    const valor = typeof parseCurrencyInput === "function"
      ? parseCurrencyInput(document.getElementById("fgFluxoValor"))
      : Number(String(valorRaw).replace(/\./g, "").replace(",", "."));
    if (!Number.isFinite(valor) || valor <= 0) {
      fgToast("Informe um valor válido.", "error");
      return;
    }
    const payload = {
      tipo: document.getElementById("fgFluxoTipo")?.value || "saida",
      valor,
      descricao: document.getElementById("fgFluxoDescricao")?.value,
      unidade_id: document.getElementById("fgFluxoUnidade")?.value || null,
      categoria_id: document.getElementById("fgFluxoCategoria")?.value || null,
      centro_custo_id: document.getElementById("fgFluxoCentroCusto")?.value || null,
      forma_pagamento: document.getElementById("fgFluxoFormaPgto")?.value,
      data_competencia: document.getElementById("fgFluxoCompetencia")?.value,
      data_pagamento: document.getElementById("fgFluxoPagamento")?.value || null,
      status: document.getElementById("fgFluxoStatus")?.value || "previsto",
      observacao: document.getElementById("fgFluxoObs")?.value,
    };
    try {
      await fgFetch("/financeiro/fluxo-caixa", { method: "POST", body: JSON.stringify(payload) });
      fgToast("Lançamento salvo.", "success");
      form.reset();
      loadFinanceiroFluxoCaixa().catch(() => {});
    } catch (err) {
      fgToast(err?.message || "Erro ao salvar.", "error");
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
    const lista = await fgFetch("/financeiro/centros-custo?ativos=0");
    const tbody = document.getElementById("fgCcTbody");
    const rows = Array.isArray(lista) ? lista : [];
    if (tbody) {
      tbody.innerHTML = rows.length
        ? rows.map((c) => `<tr><td>${esc(c.codigo || "—")}</td><td>${esc(c.nome)}</td><td>${c.ativo ? "Ativo" : "Inativo"}</td></tr>`).join("")
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
      const del = ev.target.closest("[data-fg-fluxo-del]");
      if (del) {
        const id = del.getAttribute("data-fg-fluxo-del");
        if (!id || !confirm("Excluir lançamento? (registro mantido em auditoria)")) return;
        try {
          await fgFetch(`/financeiro/fluxo-caixa/${id}`, { method: "DELETE" });
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
