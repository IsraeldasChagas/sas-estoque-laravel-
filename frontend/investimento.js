/**
 * Módulo Investimento — Tesouraria e Reservas Empresariais (SAS-Estoque)
 */
(function () {
  "use strict";

  const invState = { catalogos: null, reservas: [], carteira: [], resgates: [], unidades: [], mercado: null, titulosOficiais: [], tituloSelecionado: null, filtroCategoria: "todos", buscaTitulo: "" };

  const INV_TIPO_VISUAL = {
    tesouro_selic: { label: "Tesouro Selic", cor: "selic", icon: "📈" },
    tesouro_ipca: { label: "Tesouro IPCA+", cor: "ipca", icon: "🛡️" },
    tesouro_prefixado: { label: "Tesouro Prefixado", cor: "prefixado", icon: "📊" },
    cdb_liquidez: { label: "CDB liquidez diária", cor: "cdb", icon: "🏦" },
    fundo_di: { label: "Fundo DI", cor: "cdb", icon: "💹" },
    outros: { label: "Outros", cor: "outros", icon: "📁" },
  };

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

  function invLerMoeda(el) {
    if (!el) return null;
    if (typeof parseCurrencyInput === "function") {
      const n = parseCurrencyInput(el);
      return Number.isFinite(n) ? n : null;
    }
    const n = Number(String(el.value || el.dataset.value || "").replace(/\./g, "").replace(",", "."));
    return Number.isFinite(n) ? n : null;
  }

  function invEscreverMoeda(el, valor) {
    if (!el) return;
    const n = Number(valor);
    const num = Number.isFinite(n) ? n : 0;
    if (typeof applyFechamentoValorInput === "function") {
      applyFechamentoValorInput(el, num);
      return;
    }
    el.dataset.value = String(num);
    el.value = num > 0 ? fmtMoeda(num) : "";
  }

  function invSetupMoedaInputs(root) {
    const scope = root || document;
    scope.querySelectorAll("[data-inv-moeda]").forEach((inp) => {
      if (inp.dataset.invMoedaBound === "1") return;
      inp.dataset.invMoedaBound = "1";
      if (typeof attachCurrencyMask === "function") attachCurrencyMask(inp);
    });
  }

  function invToast(msg, type = "info") {
    const fn = typeof showToast === "function" ? showToast : window.showToast;
    if (typeof fn === "function") fn(msg, type);
  }

  async function invFetch(path, opts = {}) {
    if (typeof window.fetchJSON === "function") return window.fetchJSON(path, opts);
    throw new Error("fetchJSON não disponível");
  }

  async function invCarregarCatalogos() {
    if (invState.catalogos) return invState.catalogos;
    invState.catalogos = await invFetch("/investimento/catalogos");
    return invState.catalogos;
  }

  async function invCarregarUnidades() {
    if (invState.unidades.length) return invState.unidades;
    try {
      const lista = await invFetch("/unidades");
      invState.unidades = Array.isArray(lista) ? lista : [];
    } catch (_) {
      invState.unidades = [];
    }
    return invState.unidades;
  }

  function invPreencherSelectUnidades(selectId, valorAtual) {
    const sel = document.getElementById(selectId);
    if (!sel) return;
    const cur = valorAtual != null ? String(valorAtual) : sel.value;
    sel.innerHTML = '<option value="">Todas</option>';
    invState.unidades.forEach((u) => {
      const o = document.createElement("option");
      o.value = String(u.id);
      o.textContent = u.nome || `Unidade ${u.id}`;
      sel.appendChild(o);
    });
    if (cur) sel.value = cur;
  }

  function invOptsObjetivos(selected) {
    const cats = invState.catalogos?.objetivos || {};
    return Object.entries(cats).map(([k, v]) =>
      `<option value="${esc(k)}"${k === selected ? " selected" : ""}>${esc(v)}</option>`
    ).join("");
  }

  function invOptsTipos(selected, objetivo) {
    const all = invState.catalogos?.tipos || {};
    const alta = invState.catalogos?.tipos_alta_liquidez || [];
    const keys = objetivo === "emergencia" ? alta : Object.keys(all);
    return keys.map((k) => {
      const t = all[k];
      const label = t?.label || k;
      return `<option value="${esc(k)}"${k === selected ? " selected" : ""}>${esc(label)}</option>`;
    }).join("");
  }

  function invStatusBadge(status) {
    const map = { ativo: "Ativo", resgatado: "Resgatado", vencido: "Vencido" };
    const cls = status === "ativo" ? "inv-badge--ativo" : status === "resgatado" ? "inv-badge--resgatado" : "inv-badge--vencido";
    return `<span class="inv-badge ${cls}">${esc(map[status] || status)}</span>`;
  }

  function invAlertaHtml(alerta) {
    if (!alerta) return "";
    const cls = alerta.tipo === "vencido" ? "inv-alerta--danger" : "inv-alerta--warn";
    return `<span class="inv-alerta ${cls}" title="${esc(alerta.mensagem)}">⚠ ${esc(alerta.mensagem)}</span>`;
  }

  // ——— Dashboard ———
  async function loadInvestimentoDashboard() {
    await invCarregarUnidades();
    invPreencherSelectUnidades("invDashFiltroUnidade");
    const unidadeId = document.getElementById("invDashFiltroUnidade")?.value || "";
    const qs = unidadeId ? `?unidade_id=${encodeURIComponent(unidadeId)}` : "";
    const data = await invFetch(`/investimento/dashboard${qs}`);
    const cards = data?.cards || {};
    const elCards = document.getElementById("invDashCards");
    if (elCards) {
      elCards.innerHTML = `
        <div class="inv-card"><span class="inv-card__label">Total reservado</span><strong>${fmtMoeda(cards.total_reservado)}</strong></div>
        <div class="inv-card"><span class="inv-card__label">Aporte mensal</span><strong>${fmtMoeda(cards.total_aporte_mensal)}</strong></div>
        <div class="inv-card"><span class="inv-card__label">Total aplicado</span><strong>${fmtMoeda(cards.total_aplicado)}</strong></div>
        <div class="inv-card"><span class="inv-card__label">Rendimento estimado</span><strong>${fmtMoeda(cards.rendimento_estimado)}</strong></div>
        <div class="inv-card"><span class="inv-card__label">Reservas ativas</span><strong>${cards.qtd_reservas ?? 0}</strong></div>
      `;
    }
    const tbodyObj = document.getElementById("invDashObjetivoTbody");
    if (tbodyObj) {
      const rows = (data?.por_objetivo || []).map((o) =>
        `<tr><td>${esc(o.label)}</td><td>${o.qtd ?? "—"}</td><td>${fmtMoeda(o.total)}</td></tr>`
      ).join("");
      tbodyObj.innerHTML = rows || `<tr><td colspan="3" style="text-align:center;color:#607d8b">Nenhuma reserva cadastrada</td></tr>`;
    }
    const tbodyVenc = document.getElementById("invDashVencTbody");
    if (tbodyVenc) {
      const rows = (data?.proximos_vencimentos || []).map((v) =>
        `<tr><td>${fmtData(v.vencimento)}</td><td>${esc(v.instituicao)}</td><td>${esc(v.unidade_nome || "—")}</td><td>${fmtMoeda(v.valor_aplicado)}</td></tr>`
      ).join("");
      tbodyVenc.innerHTML = rows || `<tr><td colspan="4" style="text-align:center;color:#607d8b">Sem vencimentos próximos</td></tr>`;
    }
    const elAlertas = document.getElementById("invDashAlertas");
    if (elAlertas) {
      const alertas = data?.alertas || [];
      elAlertas.innerHTML = alertas.length
        ? alertas.map((a) => `<div class="inv-alerta-item inv-alerta--warn">${esc(a.objetivo_label)} — ${esc(a.unidade_nome || "—")}: ${esc(a.mensagem)} (${fmtData(a.data_alvo)})</div>`).join("")
        : '<p class="subtle-text">Nenhum alerta de data alvo no momento.</p>';
    }
  }

  // ——— Reservas ———
  async function loadInvestimentoReservas() {
    await invCarregarCatalogos();
    await invCarregarUnidades();
    invPreencherSelectUnidades("invResFiltroUnidade");
    const unidadeId = document.getElementById("invResFiltroUnidade")?.value || "";
    const objetivo = document.getElementById("invResFiltroObjetivo")?.value || "";
    const params = new URLSearchParams();
    if (unidadeId) params.set("unidade_id", unidadeId);
    if (objetivo) params.set("objetivo", objetivo);
    const qs = params.toString() ? `?${params}` : "";
    invState.reservas = await invFetch(`/investimento/reservas${qs}`);
    const tbody = document.getElementById("invResTbody");
    if (!tbody) return;
    const rows = (invState.reservas || []).map((r) => `
      <tr>
        <td>${esc(r.unidade_nome || "—")}</td>
        <td>${esc(r.objetivo_label)} ${invAlertaHtml(r.alerta_data_alvo)}</td>
        <td>${fmtMoeda(r.valor_inicial)}</td>
        <td>${fmtMoeda(r.aporte_mensal)}</td>
        <td>${r.prazo_meses ?? "—"}</td>
        <td>${fmtData(r.data_alvo)}</td>
        <td>
          <button type="button" class="btn secondary btn-sm inv-btn-edit-res" data-id="${r.id}">Editar</button>
          <button type="button" class="btn danger btn-sm inv-btn-del-res" data-id="${r.id}">Excluir</button>
        </td>
      </tr>
    `).join("");
    tbody.innerHTML = rows || `<tr><td colspan="7" style="text-align:center;color:#607d8b">Nenhuma reserva</td></tr>`;
  }

  async function invAbrirModalReserva(item) {
    await invCarregarCatalogos();
    await invCarregarUnidades();
    const modal = document.getElementById("invReservaModal");
    const form = document.getElementById("invReservaForm");
    if (!modal || !form) return;
    form.reset();
    document.getElementById("invReservaId").value = item?.id || "";
    document.getElementById("invReservaModalTitle").textContent = item ? "Editar reserva" : "Nova reserva";
    const selObj = document.getElementById("invResObjetivo");
    if (selObj) selObj.innerHTML = `<option value="">Selecione</option>${invOptsObjetivos(item?.objetivo)}`;
    invPreencherSelectUnidades("invResUnidade", item?.unidade_id);
    if (item) {
      invEscreverMoeda(document.getElementById("invResValorInicial"), item.valor_inicial);
      invEscreverMoeda(document.getElementById("invResAporteMensal"), item.aporte_mensal);
      document.getElementById("invResPrazo").value = item.prazo_meses ?? "";
      document.getElementById("invResDataAlvo").value = item.data_alvo ? String(item.data_alvo).slice(0, 10) : "";
      document.getElementById("invResObs").value = item.observacoes || "";
    }
    invSetupMoedaInputs(modal);
    invAtualizarAvisoObjetivo();
    modal.classList.add("open");
  }

  function invAtualizarAvisoObjetivo() {
    const obj = document.getElementById("invResObjetivo")?.value;
    const box = document.getElementById("invResAvisoObjetivo");
    if (!box) return;
    if (obj === "emergencia") {
      box.textContent = "Reserva de emergência: ao aplicar recursos, use apenas investimentos com alta liquidez.";
      box.className = "inv-aviso inv-aviso--info";
    } else if (["rescisoes", "ferias", "decimo_terceiro"].includes(obj)) {
      box.textContent = "Informe a data alvo — o sistema alertará quando estiver próxima (90 dias).";
      box.className = "inv-aviso inv-aviso--warn";
    } else {
      box.textContent = "";
      box.className = "inv-aviso hidden";
    }
  }

  // ——— Simulador (vitrine visual) ———
  function invMesesAteVencimento(dataVencBr) {
    if (!dataVencBr || !/^\d{2}\/\d{2}\/\d{4}$/.test(String(dataVencBr))) return null;
    const [d, m, y] = String(dataVencBr).split("/").map(Number);
    const venc = new Date(y, m - 1, d);
    const hoje = new Date();
    hoje.setHours(12, 0, 0, 0);
    return Math.max(0, (venc.getFullYear() - hoje.getFullYear()) * 12 + (venc.getMonth() - hoje.getMonth()));
  }

  function invTaxaEfetivaTitulo(titulo) {
    if (!titulo) return 0;
    if (titulo.tipo_sistema === "tesouro_selic" && invState.mercado?.selic?.valor != null) {
      return Number(invState.mercado.selic.valor);
    }
    return Number(titulo.taxa_compra_aa || titulo.taxa_anual || 0);
  }

  function invRenderIrCards(tabela) {
    const box = document.getElementById("invSimIrCards");
    if (!box) return;
    box.innerHTML = (tabela || []).map((r) => `
      <div class="inv-ir-card">
        <span class="inv-ir-card__faixa">${esc(r.faixa)}</span>
        <strong class="inv-ir-card__aliq">${Number(r.aliquota_percent).toFixed(1).replace(".", ",")}%</strong>
      </div>
    `).join("");
  }

  function invReferenciasRapidasHtml() {
    const selic = invState.mercado?.selic;
    const cdi = invState.mercado?.cdi;
    const cards = [];
    if (selic?.valor != null) {
      cards.push({
        id: "ref_selic",
        tipo: "tesouro_selic",
        nome: "Meta Selic (Bacen)",
        taxa: selic.valor,
        extra: selic.data ? `Ref. ${selic.data}` : "Referência oficial",
        pu: null,
        vencimento: null,
        liquidez: "alta",
        ref: true,
      });
    }
    if (cdi?.valor != null) {
      cards.push({
        id: "ref_cdb",
        tipo: "cdb_liquidez",
        nome: "CDB liquidez diária",
        taxa: cdi.valor,
        extra: `CDI ref. ${cdi.data || "Bacen"} — estimativa`,
        pu: null,
        vencimento: "D+0",
        liquidez: "alta",
        ref: true,
      });
      cards.push({
        id: "ref_fundo",
        tipo: "fundo_di",
        nome: "Fundo DI",
        taxa: cdi.valor,
        extra: `CDI ref. ${cdi.data || "Bacen"} — estimativa`,
        pu: null,
        vencimento: "D+1",
        liquidez: "alta",
        ref: true,
      });
    }
    return cards;
  }

  function invTituloPassaFiltro(t) {
    const cat = invState.filtroCategoria;
    if (cat === "todos") { /* ok */ }
    else if (cat === "renda_fixa") return false;
    else if (t.tipo_sistema !== cat) return false;

    const q = (invState.buscaTitulo || "").trim().toLowerCase();
    if (!q) return true;
    const hay = `${t.nome} ${t.data_vencimento} ${t.tipo_sistema || ""}`.toLowerCase();
    return hay.includes(q);
  }

  function invRenderCardTitulo(item, isRef) {
    const vis = INV_TIPO_VISUAL[item.tipo_sistema] || INV_TIPO_VISUAL.outros;
    const taxa = invTaxaEfetivaTitulo(item);
    const selId = invState.tituloSelecionado?.id;
    const selected = selId && String(selId) === String(item.id);
    const puHtml = item.pu_compra != null && item.pu_compra > 0
      ? `<div class="inv-titulo-card__pu"><span>Preço unitário (PU)</span><strong>${fmtMoeda(item.pu_compra)}</strong></div>`
      : `<div class="inv-titulo-card__pu"><span>Referência</span><strong>${esc(item.extra || "CDI / Selic")}</strong></div>`;
    return `
      <article class="inv-titulo-card inv-titulo-card--${vis.cor}${selected ? " inv-titulo-card--selected" : ""}" data-id="${esc(item.id)}">
        <div class="inv-titulo-card__top">
          <span class="inv-titulo-card__icon">${vis.icon}</span>
          <span class="inv-titulo-card__tipo">${esc(vis.label)}</span>
        </div>
        <h4 class="inv-titulo-card__nome">${esc(item.nome)}</h4>
        <div class="inv-titulo-card__taxa">
          <span>Taxa</span>
          <strong>${Number(taxa).toFixed(2).replace(".", ",")}% <small>a.a.</small></strong>
        </div>
        ${puHtml}
        <div class="inv-titulo-card__meta">
          ${item.vencimento ? `<span>📅 Venc. ${esc(item.vencimento)}</span>` : ""}
          <span class="inv-titulo-card__liq inv-titulo-card__liq--${esc(item.liquidez || "media")}">${esc(item.liquidez === "alta" ? "Alta liquidez" : item.liquidez === "baixa" ? "Baixa liquidez" : "Liquidez média")}</span>
        </div>
        <button type="button" class="btn primary inv-titulo-card__btn" data-usar-id="${esc(item.id)}">${selected ? "✓ Selecionado" : "Usar na simulação"}</button>
      </article>
    `;
  }

  function invRenderVitrineTitulos() {
    const grid = document.getElementById("invSimTitulosGrid");
    if (!grid) return;

    const refs = invReferenciasRapidasHtml();
    const titulos = (invState.titulosOficiais || []).filter(invTituloPassaFiltro);
    const cat = invState.filtroCategoria;

    let html = "";

    if (cat === "todos" || cat === "renda_fixa") {
      const refsFiltradas = cat === "renda_fixa"
        ? refs.filter((r) => r.tipo === "cdb_liquidez" || r.tipo === "fundo_di" || r.tipo === "tesouro_selic")
        : refs;
      if (refsFiltradas.length) {
        html += `<div class="inv-vitrine-subtitulo">${cat === "renda_fixa" ? "Referências Bacen (CDB / DI / Selic)" : "Destaques — Selic e renda fixa bancária"}</div>`;
        html += `<div class="inv-titulos-grid inv-titulos-grid--refs">${refsFiltradas.map((r) => invRenderCardTitulo(r, true)).join("")}</div>`;
      }
    }

    if (cat !== "renda_fixa") {
      if (titulos.length) {
        html += `<div class="inv-vitrine-subtitulo">Títulos do Tesouro Direto — cotação ${esc(invState.mercado?.tesouro?.data_base || "do dia")}</div>`;
        html += `<div class="inv-titulos-grid">${titulos.map((t) => invRenderCardTitulo(t, false)).join("")}</div>`;
      } else if (!html) {
        html = `<p class="subtle-text inv-vitrine-empty">Nenhum título encontrado para este filtro.</p>`;
      }
    } else if (!html) {
      html = `<p class="subtle-text inv-vitrine-empty">Carregue as cotações para ver referências.</p>`;
    }

    grid.innerHTML = html;
  }

  function invAtualizarBarraSelecionado(item) {
    const bar = document.getElementById("invSimSelecionadoBar");
    const info = document.getElementById("invSimSelecionadoInfo");
    const hint = document.getElementById("invSimFormHint");
    if (!item) {
      bar?.classList.add("hidden");
      if (hint) hint.textContent = "Selecione um card acima ou preencha manualmente.";
      return;
    }
    const vis = INV_TIPO_VISUAL[item.tipo_sistema] || INV_TIPO_VISUAL.outros;
    const taxa = invTaxaEfetivaTitulo(item);
    bar?.classList.remove("hidden");
    if (info) {
      info.innerHTML = `
        <span class="inv-selecionado-badge">${vis.icon} ${esc(vis.label)}</span>
        <strong>${esc(item.nome)}</strong>
        <span class="inv-selecionado-detalhe">
          Taxa <b>${Number(taxa).toFixed(2).replace(".", ",")}% a.a.</b>
          ${item.pu_compra ? ` · PU <b>${fmtMoeda(item.pu_compra)}</b>` : ""}
          ${item.vencimento ? ` · Venc. ${esc(item.vencimento)}` : ""}
        </span>
      `;
    }
    if (hint) hint.textContent = "Valores replicados do título selecionado. Ajuste valor aplicado e clique em Calcular.";
  }

  function invAplicarItemNaSimulacao(item) {
    if (!item) return;
    invState.tituloSelecionado = item;

    const selTipo = document.getElementById("invSimTipo");
    if (selTipo && item.tipo_sistema) selTipo.value = item.tipo_sistema;

    const taxa = invTaxaEfetivaTitulo(item);
    const elAnual = document.getElementById("invSimTaxaAnual");
    const elMensal = document.getElementById("invSimTaxaMensal");
    if (elAnual) elAnual.value = Number(taxa).toFixed(4);
    if (elMensal) elMensal.value = "";

    if (item.pu_compra > 0) {
      invEscreverMoeda(document.getElementById("invSimValor"), item.pu_compra);
    }

    const meses = invMesesAteVencimento(item.vencimento || item.data_vencimento);
    const elPrazo = document.getElementById("invSimPrazo");
    if (elPrazo && meses != null && meses > 0) elPrazo.value = String(meses);

    invAtualizarBarraSelecionado(item);
    invRenderVitrineTitulos();
    invToast(`"${item.nome}" replicado na simulação.`, "success");
  }

  function invResolverItemPorId(id) {
    const ref = invReferenciasRapidasHtml().find((r) => String(r.id) === String(id));
    if (ref) return ref;
    return invState.titulosOficiais.find((t) => String(t.id) === String(id)) || null;
  }

  function invRenderMercado(data) {
    invState.mercado = data;
    invState.titulosOficiais = data?.tesouro?.titulos || [];

    const atualizado = document.getElementById("invSimMercadoAtualizado");
    if (atualizado) {
      const parts = [];
      if (data?.tesouro?.data_base) parts.push(`Tesouro: ${data.tesouro.data_base}`);
      if (data?.selic?.valor != null) parts.push(`Selic ${Number(data.selic.valor).toFixed(2)}%`);
      if (data?.cdi?.valor != null) parts.push(`CDI ${Number(data.cdi.valor).toFixed(2)}%`);
      atualizado.textContent = parts.length ? parts.join(" · ") : "Cotações carregadas";
    }

    invRenderIrCards(data?.tabela_ir_regressiva);
    invRenderVitrineTitulos();

    const avisos = document.getElementById("invSimMercadoAvisos");
    if (avisos) {
      avisos.innerHTML = (data?.avisos || []).slice(0, 2).map((a) => `<div class="inv-aviso inv-aviso--info">${esc(a)}</div>`).join("");
    }
  }

  async function invCarregarMercadoOficial(force = false) {
    const btn = document.getElementById("invSimBuscarOficial");
    const grid = document.getElementById("invSimTitulosGrid");
    if (btn) { btn.disabled = true; btn.textContent = force ? "Atualizando…" : "Carregando…"; }
    if (grid && !invState.titulosOficiais.length) {
      grid.innerHTML = '<p class="subtle-text inv-vitrine-loading">Buscando títulos oficiais (pode levar até 1 min na 1ª vez)…</p>';
    }
    try {
      const data = await invFetch(`/investimento/mercado/referencias${force ? "?force=1" : ""}`);
      invRenderMercado(data);
      invToast("Vitrine atualizada.", "success");
    } catch (e) {
      if (grid) grid.innerHTML = `<p class="inv-aviso inv-aviso--warn">${esc(e.message || "Falha ao carregar. Clique em Atualizar cotações.")}</p>`;
      invToast(e.message || "Falha ao buscar cotações.", "error");
    } finally {
      if (btn) { btn.disabled = false; btn.textContent = "↻ Atualizar cotações"; }
    }
  }

  function invVitrineRecolher() {
    const card = document.getElementById("invSimVitrineCard");
    const body = document.getElementById("invSimVitrineBody");
    const btn = document.getElementById("invSimVitrineToggle");
    if (card) card.classList.add("inv-vitrine-card--collapsed");
    if (body) body.hidden = true;
    if (btn) {
      btn.setAttribute("aria-expanded", "false");
      btn.title = "Mostrar vitrine";
    }
  }

  function invVitrineToggle() {
    const card = document.getElementById("invSimVitrineCard");
    const body = document.getElementById("invSimVitrineBody");
    const btn = document.getElementById("invSimVitrineToggle");
    if (!card || !btn) return;
    const recolhido = card.classList.toggle("inv-vitrine-card--collapsed");
    if (body) body.hidden = recolhido;
    btn.setAttribute("aria-expanded", recolhido ? "false" : "true");
    btn.title = recolhido ? "Mostrar vitrine" : "Recolher vitrine";
  }

  async function loadInvestimentoSimulador() {
    invVitrineRecolher();
    invSetupMoedaInputs(document.getElementById("investimentoSimuladorSection"));
    document.getElementById("invSimResultado")?.classList.add("hidden");
    try {
      await invCarregarCatalogos();
      const selObj = document.getElementById("invSimObjetivo");
      const selTipo = document.getElementById("invSimTipo");
      if (selObj) selObj.innerHTML = `<option value="">— Opcional —</option>${invOptsObjetivos()}`;
      if (selTipo) selTipo.innerHTML = `<option value="">Selecione</option>${invOptsTipos()}`;
    } catch (err) {
      invToast(err.message || "Erro ao carregar catálogos.", "error");
    }
    if (invState.mercado) invRenderMercado(invState.mercado);
    else invCarregarMercadoOficial(false).catch(() => {});
  }

  async function invExecutarSimulacao() {
    const valor = invLerMoeda(document.getElementById("invSimValor")) || 0;
    const aporte = invLerMoeda(document.getElementById("invSimAporte")) || 0;
    const prazo = parseInt(document.getElementById("invSimPrazo")?.value || "0", 10);
    const taxaAnual = document.getElementById("invSimTaxaAnual")?.value;
    const taxaMensal = document.getElementById("invSimTaxaMensal")?.value;
    const tipo = document.getElementById("invSimTipo")?.value || "";
    const objetivo = document.getElementById("invSimObjetivo")?.value || "";
    const body = { valor_aplicado: valor, aporte_mensal: aporte, prazo_meses: prazo, tipo_investimento: tipo, objetivo };
    if (taxaAnual) body.taxa_anual = parseFloat(taxaAnual);
    if (taxaMensal) body.taxa_mensal = parseFloat(taxaMensal);
    const res = await invFetch("/investimento/simular", { method: "POST", body: JSON.stringify(body) });
    const box = document.getElementById("invSimResultado");
    if (!box) return;
    box.classList.remove("hidden");
    box.innerHTML = `
      <h3>Resultado da simulação</h3>
      <div class="inv-sim-grid">
        <div><span>Valor bruto</span><strong>${fmtMoeda(res.valor_bruto)}</strong></div>
        <div><span>Imposto estimado</span><strong>${fmtMoeda(res.imposto)}</strong></div>
        <div><span>Valor líquido</span><strong>${fmtMoeda(res.valor_liquido)}</strong></div>
        <div><span>Rendimento bruto</span><strong>${fmtMoeda(res.rendimento_bruto)}</strong></div>
        <div><span>Rendimento líquido</span><strong>${fmtMoeda(res.rendimento_liquido)}</strong></div>
        <div><span>Taxa mensal</span><strong>${Number(res.taxa_mensal_percent || 0).toFixed(4)}%</strong></div>
        <div><span>Taxa anual</span><strong>${Number(res.taxa_anual_percent || 0).toFixed(4)}%</strong></div>
        <div><span>Liquidez</span><strong>${esc(res.liquidez || "—")}</strong></div>
      </div>
      ${(res.avisos || []).map((a) => `<div class="inv-aviso inv-aviso--warn">${esc(a)}</div>`).join("")}
    `;
  }

  function invFiltrarTiposSimulador() {
    const obj = document.getElementById("invSimObjetivo")?.value;
    const sel = document.getElementById("invSimTipo");
    if (!sel) return;
    const cur = sel.value;
    sel.innerHTML = `<option value="">Selecione</option>${invOptsTipos(cur, obj)}`;
  }

  // ——— Carteira ———
  async function loadInvestimentoCarteira() {
    await invCarregarCatalogos();
    await invCarregarUnidades();
    invPreencherSelectUnidades("invCartFiltroUnidade");
    const unidadeId = document.getElementById("invCartFiltroUnidade")?.value || "";
    const status = document.getElementById("invCartFiltroStatus")?.value || "";
    const params = new URLSearchParams();
    if (unidadeId) params.set("unidade_id", unidadeId);
    if (status) params.set("status", status);
    const qs = params.toString() ? `?${params}` : "";
    invState.carteira = await invFetch(`/investimento/carteira${qs}`);
    const tbody = document.getElementById("invCartTbody");
    if (!tbody) return;
    tbody.innerHTML = (invState.carteira || []).map((c) => `
      <tr>
        <td>${fmtData(c.data_compra)}</td>
        <td>${esc(c.instituicao)}</td>
        <td>${esc(c.tipo_label)}</td>
        <td>${fmtMoeda(c.valor_aplicado)}</td>
        <td>${c.taxa_contratada != null ? `${Number(c.taxa_contratada).toFixed(2)}% a.a.` : "—"}</td>
        <td>${fmtData(c.vencimento)}</td>
        <td>${esc(c.objetivo_label || "—")}</td>
        <td>${invStatusBadge(c.status)}</td>
        <td>${fmtMoeda(c.rendimento_estimado?.rendimento_liquido)}</td>
        <td>
          <button type="button" class="btn secondary btn-sm inv-btn-edit-cart" data-id="${c.id}">Editar</button>
          <button type="button" class="btn danger btn-sm inv-btn-del-cart" data-id="${c.id}">Excluir</button>
        </td>
      </tr>
    `).join("") || `<tr><td colspan="10" style="text-align:center;color:#607d8b">Carteira vazia</td></tr>`;
  }

  async function invAbrirModalCarteira(item) {
    await invCarregarCatalogos();
    await invCarregarUnidades();
    if (!invState.reservas.length) {
      try { invState.reservas = await invFetch("/investimento/reservas?ativo=1"); } catch (_) { invState.reservas = []; }
    }
    const modal = document.getElementById("invCarteiraModal");
    const form = document.getElementById("invCarteiraForm");
    if (!modal || !form) return;
    form.reset();
    document.getElementById("invCartId").value = item?.id || "";
    document.getElementById("invCartModalTitle").textContent = item ? "Editar aplicação" : "Nova aplicação";
    invPreencherSelectUnidades("invCartUnidade", item?.unidade_id);
    const selRes = document.getElementById("invCartReserva");
    if (selRes) {
      selRes.innerHTML = '<option value="">— Sem vínculo —</option>' +
        (invState.reservas || []).map((r) =>
          `<option value="${r.id}"${String(r.id) === String(item?.reserva_id) ? " selected" : ""}>${esc(r.objetivo_label)} — ${esc(r.unidade_nome || "—")}</option>`
        ).join("");
    }
    const selTipo = document.getElementById("invCartTipo");
    if (selTipo) selTipo.innerHTML = `<option value="">Selecione</option>${invOptsTipos(item?.tipo_investimento)}`;
    if (item) {
      document.getElementById("invCartDataCompra").value = item.data_compra ? String(item.data_compra).slice(0, 10) : "";
      document.getElementById("invCartInstituicao").value = item.instituicao || "";
      invEscreverMoeda(document.getElementById("invCartValor"), item.valor_aplicado);
      document.getElementById("invCartTaxaAnual").value = item.taxa_contratada ?? "";
      document.getElementById("invCartVencimento").value = item.vencimento ? String(item.vencimento).slice(0, 10) : "";
      document.getElementById("invCartStatus").value = item.status || "ativo";
      document.getElementById("invCartObs").value = item.observacoes || "";
    }
    invSetupMoedaInputs(modal);
    modal.classList.add("open");
  }

  // ——— Resgates ———
  async function loadInvestimentoResgates() {
    await invCarregarUnidades();
    invPreencherSelectUnidades("invResgFiltroUnidade");
    const unidadeId = document.getElementById("invResgFiltroUnidade")?.value || "";
    const qs = unidadeId ? `?unidade_id=${encodeURIComponent(unidadeId)}` : "";
    invState.resgates = await invFetch(`/investimento/resgates${qs}`);
    const tbody = document.getElementById("invResgTbody");
    if (!tbody) return;
    tbody.innerHTML = (invState.resgates || []).map((g) => `
      <tr>
        <td>${fmtData(g.data_resgate)}</td>
        <td>${esc(g.instituicao || "—")}</td>
        <td>${esc(g.tipo_label || "—")}</td>
        <td>${esc(g.unidade_nome || "—")}</td>
        <td>${fmtMoeda(g.valor_bruto)}</td>
        <td>${fmtMoeda(g.imposto)}</td>
        <td>${fmtMoeda(g.valor_liquido)}</td>
        <td><button type="button" class="btn danger btn-sm inv-btn-del-resg" data-id="${g.id}">Excluir</button></td>
      </tr>
    `).join("") || `<tr><td colspan="8" style="text-align:center;color:#607d8b">Nenhum resgate registrado</td></tr>`;
  }

  async function invAbrirModalResgate() {
    const carteira = await invFetch("/investimento/carteira?status=ativo");
    const modal = document.getElementById("invResgateModal");
    const form = document.getElementById("invResgateForm");
    if (!modal || !form) return;
    form.reset();
    const sel = document.getElementById("invResgCarteira");
    if (sel) {
      sel.innerHTML = '<option value="">Selecione o investimento</option>' +
        (carteira || []).map((c) =>
          `<option value="${c.id}">${esc(c.instituicao)} — ${esc(c.tipo_label)} — ${fmtMoeda(c.valor_aplicado)}</option>`
        ).join("");
    }
    document.getElementById("invResgData").value = new Date().toISOString().slice(0, 10);
    invSetupMoedaInputs(modal);
    modal.classList.add("open");
  }

  // ——— Relatórios ———
  async function loadInvestimentoRelatorios() {
    await invCarregarUnidades();
    invPreencherSelectUnidades("invRelFiltroUnidade");
    const unidadeId = document.getElementById("invRelFiltroUnidade")?.value || "";
    const qs = unidadeId ? `?unidade_id=${encodeURIComponent(unidadeId)}` : "";
    const data = await invFetch(`/investimento/relatorios${qs}`);
    document.getElementById("invRelTotalReservado").textContent = fmtMoeda(data.total_reservado);
    document.getElementById("invRelTotalAplicado").textContent = fmtMoeda(data.total_aplicado);
    document.getElementById("invRelRendimento").textContent = fmtMoeda(data.rendimento_estimado);
    const tbodyObj = document.getElementById("invRelObjetivoTbody");
    if (tbodyObj) {
      tbodyObj.innerHTML = (data.reserva_por_objetivo || []).map((o) =>
        `<tr><td>${esc(o.label)}</td><td>${fmtMoeda(o.total)}</td></tr>`
      ).join("") || `<tr><td colspan="2" style="text-align:center;color:#607d8b">—</td></tr>`;
    }
    const tbodyVenc = document.getElementById("invRelVencTbody");
    if (tbodyVenc) {
      tbodyVenc.innerHTML = (data.proximos_vencimentos || []).map((v) =>
        `<tr><td>${fmtData(v.vencimento)}</td><td>${esc(v.instituicao)}</td><td>${esc(v.unidade_nome || "—")}</td><td>${fmtMoeda(v.valor_aplicado)}</td></tr>`
      ).join("") || `<tr><td colspan="4" style="text-align:center;color:#607d8b">—</td></tr>`;
    }
    const tbodyResg = document.getElementById("invRelResgTbody");
    if (tbodyResg) {
      tbodyResg.innerHTML = (data.resgates_realizados || []).map((g) =>
        `<tr><td>${fmtData(g.data_resgate)}</td><td>${esc(g.instituicao)}</td><td>${esc(g.unidade_nome || "—")}</td><td>${fmtMoeda(g.imposto)}</td><td>${fmtMoeda(g.valor_liquido)}</td></tr>`
      ).join("") || `<tr><td colspan="5" style="text-align:center;color:#607d8b">—</td></tr>`;
    }
  }

  // ——— Bindings ———
  let invBound = false;
  function investimentoBindOnce() {
    if (invBound) return;
    invBound = true;

    document.getElementById("invDashAtualizar")?.addEventListener("click", () => loadInvestimentoDashboard().catch((e) => invToast(e.message, "error")));
    document.getElementById("invResBtnNova")?.addEventListener("click", () => invAbrirModalReserva(null).catch((e) => invToast(e.message, "error")));
    document.getElementById("invResFiltroAplicar")?.addEventListener("click", () => loadInvestimentoReservas().catch((e) => invToast(e.message, "error")));
    document.getElementById("invResObjetivo")?.addEventListener("change", invAtualizarAvisoObjetivo);
    document.getElementById("invReservaForm")?.addEventListener("submit", async (e) => {
      e.preventDefault();
      const id = document.getElementById("invReservaId")?.value;
      const body = {
        unidade_id: document.getElementById("invResUnidade")?.value || null,
        objetivo: document.getElementById("invResObjetivo")?.value,
        valor_inicial: invLerMoeda(document.getElementById("invResValorInicial")) || 0,
        aporte_mensal: invLerMoeda(document.getElementById("invResAporteMensal")) || 0,
        prazo_meses: document.getElementById("invResPrazo")?.value || null,
        data_alvo: document.getElementById("invResDataAlvo")?.value || null,
        observacoes: document.getElementById("invResObs")?.value || null,
      };
      try {
        if (id) await invFetch(`/investimento/reservas/${id}`, { method: "PUT", body: JSON.stringify(body) });
        else await invFetch("/investimento/reservas", { method: "POST", body: JSON.stringify(body) });
        document.getElementById("invReservaModal")?.classList.remove("open");
        invToast("Reserva salva.", "success");
        loadInvestimentoReservas().catch(() => {});
      } catch (err) {
        invToast(err.message || "Erro ao salvar.", "error");
      }
    });
    document.getElementById("investimentoReservasSection")?.addEventListener("click", async (ev) => {
      const edit = ev.target.closest(".inv-btn-edit-res");
      const del = ev.target.closest(".inv-btn-del-res");
      if (edit) {
        const item = invState.reservas.find((r) => String(r.id) === edit.dataset.id);
        if (item) invAbrirModalReserva(item).catch((e) => invToast(e.message, "error"));
      }
      if (del && confirm("Excluir esta reserva?")) {
        try {
          await invFetch(`/investimento/reservas/${del.dataset.id}`, { method: "DELETE" });
          invToast("Reserva excluída.", "success");
          loadInvestimentoReservas().catch(() => {});
        } catch (err) {
          invToast(err.message, "error");
        }
      }
    });

    document.getElementById("invSimObjetivo")?.addEventListener("change", invFiltrarTiposSimulador);
    document.getElementById("invSimBuscarOficial")?.addEventListener("click", () => invCarregarMercadoOficial(true).catch((e) => invToast(e.message, "error")));
    document.getElementById("invSimTaxaAnual")?.addEventListener("input", () => {
      const el = document.getElementById("invSimTaxaMensal");
      if (el) el.value = "";
    });
    document.getElementById("invSimTaxaMensal")?.addEventListener("input", () => {
      const el = document.getElementById("invSimTaxaAnual");
      if (el) el.value = "";
    });
    document.getElementById("invSimCalcular")?.addEventListener("click", () => invExecutarSimulacao().catch((e) => invToast(e.message, "error")));

    document.getElementById("investimentoSimuladorSection")?.addEventListener("click", (e) => {
      if (e.target.closest("a")) return;
      if (e.target.closest("#invSimVitrineToggle") || e.target.closest(".inv-vitrine-head-text")) {
        e.preventDefault();
        invVitrineToggle();
        return;
      }
      const filtroBtn = e.target.closest(".inv-filtro-btn");
      if (filtroBtn?.dataset?.cat) {
        invState.filtroCategoria = filtroBtn.dataset.cat;
        document.querySelectorAll("#invSimFiltrosCategoria .inv-filtro-btn").forEach((b) => {
          b.classList.toggle("active", b === filtroBtn);
        });
        invRenderVitrineTitulos();
        return;
      }
      const usar = e.target.closest("[data-usar-id]");
      if (usar) {
        const item = invResolverItemPorId(usar.dataset.usarId);
        if (item) invAplicarItemNaSimulacao(item);
      }
    });
    document.getElementById("invSimBuscaTitulo")?.addEventListener("input", (e) => {
      invState.buscaTitulo = e.target.value || "";
      invRenderVitrineTitulos();
    });
    document.getElementById("invSimIrParaForm")?.addEventListener("click", () => {
      document.getElementById("invSimFormCard")?.scrollIntoView({ behavior: "smooth", block: "start" });
    });

    document.getElementById("invCartBtnNova")?.addEventListener("click", () => invAbrirModalCarteira(null).catch((e) => invToast(e.message, "error")));
    document.getElementById("invCartFiltroAplicar")?.addEventListener("click", () => loadInvestimentoCarteira().catch((e) => invToast(e.message, "error")));
    document.getElementById("invCarteiraForm")?.addEventListener("submit", async (e) => {
      e.preventDefault();
      const id = document.getElementById("invCartId")?.value;
      const body = {
        unidade_id: document.getElementById("invCartUnidade")?.value || null,
        data_compra: document.getElementById("invCartDataCompra")?.value,
        instituicao: document.getElementById("invCartInstituicao")?.value,
        tipo_investimento: document.getElementById("invCartTipo")?.value,
        valor_aplicado: invLerMoeda(document.getElementById("invCartValor")) || 0,
        taxa_contratada: document.getElementById("invCartTaxaAnual")?.value || null,
        vencimento: document.getElementById("invCartVencimento")?.value || null,
        reserva_id: document.getElementById("invCartReserva")?.value || null,
        status: document.getElementById("invCartStatus")?.value || "ativo",
        observacoes: document.getElementById("invCartObs")?.value || null,
      };
      try {
        if (id) await invFetch(`/investimento/carteira/${id}`, { method: "PUT", body: JSON.stringify(body) });
        else await invFetch("/investimento/carteira", { method: "POST", body: JSON.stringify(body) });
        document.getElementById("invCarteiraModal")?.classList.remove("open");
        invToast("Aplicação salva.", "success");
        loadInvestimentoCarteira().catch(() => {});
      } catch (err) {
        invToast(err.message || "Erro ao salvar.", "error");
      }
    });
    document.getElementById("investimentoCarteiraSection")?.addEventListener("click", async (ev) => {
      const edit = ev.target.closest(".inv-btn-edit-cart");
      const del = ev.target.closest(".inv-btn-del-cart");
      if (edit) {
        const item = invState.carteira.find((c) => String(c.id) === edit.dataset.id);
        if (item) invAbrirModalCarteira(item).catch((e) => invToast(e.message, "error"));
      }
      if (del && confirm("Excluir esta aplicação?")) {
        try {
          await invFetch(`/investimento/carteira/${del.dataset.id}`, { method: "DELETE" });
          invToast("Registro excluído.", "success");
          loadInvestimentoCarteira().catch(() => {});
        } catch (err) {
          invToast(err.message, "error");
        }
      }
    });

    document.getElementById("invResgBtnNova")?.addEventListener("click", () => invAbrirModalResgate().catch((e) => invToast(e.message, "error")));
    document.getElementById("invResgFiltroAplicar")?.addEventListener("click", () => loadInvestimentoResgates().catch((e) => invToast(e.message, "error")));
    document.getElementById("invResgateForm")?.addEventListener("submit", async (e) => {
      e.preventDefault();
      const body = {
        carteira_id: document.getElementById("invResgCarteira")?.value,
        data_resgate: document.getElementById("invResgData")?.value,
        valor_resgatado: invLerMoeda(document.getElementById("invResgValor")) || 0,
        valor_bruto: invLerMoeda(document.getElementById("invResgBruto")) || null,
        imposto: invLerMoeda(document.getElementById("invResgImposto")) || 0,
        valor_liquido: invLerMoeda(document.getElementById("invResgLiquido")) || null,
        observacoes: document.getElementById("invResgObs")?.value || null,
      };
      try {
        await invFetch("/investimento/resgates", { method: "POST", body: JSON.stringify(body) });
        document.getElementById("invResgateModal")?.classList.remove("open");
        invToast("Resgate registrado.", "success");
        loadInvestimentoResgates().catch(() => {});
      } catch (err) {
        invToast(err.message || "Erro ao salvar.", "error");
      }
    });
    document.getElementById("investimentoResgatesSection")?.addEventListener("click", async (ev) => {
      const del = ev.target.closest(".inv-btn-del-resg");
      if (del && confirm("Excluir este resgate?")) {
        try {
          await invFetch(`/investimento/resgates/${del.dataset.id}`, { method: "DELETE" });
          invToast("Resgate excluído.", "success");
          loadInvestimentoResgates().catch(() => {});
        } catch (err) {
          invToast(err.message, "error");
        }
      }
    });

    document.getElementById("invRelAtualizar")?.addEventListener("click", () => loadInvestimentoRelatorios().catch((e) => invToast(e.message, "error")));

    ["invReservaModal", "invCarteiraModal", "invResgateModal"].forEach((id) => {
      document.getElementById(id)?.querySelector(".close-btn")?.addEventListener("click", () => {
        document.getElementById(id)?.classList.remove("open");
      });
    });

    const filtroObj = document.getElementById("invResFiltroObjetivo");
    if (filtroObj && invState.catalogos) {
      filtroObj.innerHTML = `<option value="">Todos</option>${invOptsObjetivos()}`;
    }
  }

  window.loadInvestimentoDashboard = loadInvestimentoDashboard;
  window.loadInvestimentoReservas = loadInvestimentoReservas;
  window.loadInvestimentoSimulador = loadInvestimentoSimulador;
  window.loadInvestimentoCarteira = loadInvestimentoCarteira;
  window.loadInvestimentoResgates = loadInvestimentoResgates;
  window.loadInvestimentoRelatorios = loadInvestimentoRelatorios;

  window.setupInvestimentoModule = function setupInvestimentoModule() {
    investimentoBindOnce();
    invSetupMoedaInputs(document);
    const menu = document.getElementById("investimentoMenu");
    if (menu && menu.dataset.sasSubmenuToggleBound !== "1") {
      menu.dataset.sasSubmenuToggleBound = "1";
      menu.addEventListener("click", (ev) => {
        ev.preventDefault();
        menu.closest(".nav-submenu")?.classList.toggle("open");
      });
    }
    invCarregarCatalogos().then(() => {
      const filtroObj = document.getElementById("invResFiltroObjetivo");
      if (filtroObj) filtroObj.innerHTML = `<option value="">Todos</option>${invOptsObjetivos()}`;
    }).catch(() => {});
  };
})();
