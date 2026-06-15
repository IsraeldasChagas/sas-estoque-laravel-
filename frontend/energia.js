/**
 * Módulo Manutenção — Energia (SAS-Estoque)
 * Dashboard, equipamentos, projeção e relatórios.
 */
(function () {
  "use strict";

  const ENERGIA_MODULOS = ["energiaDashboard", "energiaEquipamentos", "energiaProjecao", "energiaRelatorios"];
  const ENERGIA_CHARTS = {};
  const ENERGIA_API_URL = (window.APP_CONFIG && window.APP_CONFIG.API_URL) || "https://api.gruposaborparaense.com.br/api";

  const energiaState = {
    equipamentos: [],
    dashboard: null,
    edicaoId: null,
    projecaoBase: null,
  };

  function esc(s) {
    return (s ?? "").toString()
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function fmtNum(n, dec = 2) {
    const v = Number(n);
    if (!Number.isFinite(v)) return "0";
    return v.toLocaleString("pt-BR", { minimumFractionDigits: dec, maximumFractionDigits: dec });
  }

  function fmtMoeda(n) {
    return "R$ " + fmtNum(n, 2);
  }

  /** Cálculo em tempo real (mesma fórmula do backend). */
  function energiaCalcular(params) {
    const w = Math.max(0, Number(params.potencia_watts) || 0);
    const q = Math.max(0, parseInt(params.quantidade, 10) || 0);
    const h = Math.max(0, Number(params.horas_por_dia) || 0);
    const d = Math.max(0, parseInt(params.dias_uso_mes, 10) || 0);
    const vk = Math.max(0, Number(params.valor_kwh) || 0);
    const consumo_kwh = Math.round(((w * q * h * d) / 1000) * 10000) / 10000;
    const custo_estimado = Math.round(consumo_kwh * vk * 100) / 100;
    return { consumo_kwh, custo_estimado };
  }

  function energiaQueryFromFiltros(prefix) {
    const p = prefix || "energiaFiltro";
    const qs = new URLSearchParams();
    const u = document.getElementById(p + "Unidade")?.value;
    const setor = document.getElementById(p + "Setor")?.value?.trim();
    const eq = document.getElementById(p + "Equipamento")?.value?.trim();
    const tipo = document.getElementById(p + "Tipo")?.value?.trim();
    const tensao = document.getElementById(p + "Tensao")?.value;
    const mes = document.getElementById(p + "Mes")?.value;
    const maiorConsumo = document.getElementById(p + "MaiorConsumo")?.checked;
    const maiorCusto = document.getElementById(p + "MaiorCusto")?.checked;
    if (u) qs.set("unidade_id", u);
    if (setor) qs.set("setor", setor);
    if (eq) qs.set("equipamento", eq);
    if (tipo) qs.set("equipamento_tipo", tipo);
    if (tensao) qs.set("tensao", tensao);
    if (mes) qs.set("mes", mes);
    if (maiorConsumo) qs.set("maior_consumo", "1");
    else if (maiorCusto) qs.set("maior_custo", "1");
    return qs.toString() ? "?" + qs.toString() : "";
  }

  /** Carrega unidades na API e preenche todos os selects do módulo Energia. */
  async function populateEnergiaUnidadeSelects() {
    const ids = [
      "energiaFiltroUnidade", "energiaDashFiltroUnidade", "energiaFormUnidade",
      "energiaProjUnidade", "energiaRelFiltroUnidade",
    ];
    const valoresSalvos = {};
    ids.forEach((id) => {
      const el = document.getElementById(id);
      if (el) valoresSalvos[id] = el.value;
    });

    if (typeof window.loadUnidades === "function") {
      await window.loadUnidades(false).catch(() => {});
    }
    let unidades = window.state?.unidades || [];
    if (!unidades.length && typeof window.fetchJSON === "function") {
      try {
        unidades = await window.fetchJSON("/unidades?todas=1");
        if (window.state) window.state.unidades = Array.isArray(unidades) ? unidades : [];
      } catch (_) {
        unidades = [];
      }
    }
    const opts = unidades
      .map((u) => `<option value="${u.id}">${esc(u.nome || `Unidade ${u.id}`)}</option>`)
      .join("");
    ids.forEach((id) => {
      const el = document.getElementById(id);
      if (!el) return;
      if (id === "energiaFormUnidade") {
        el.innerHTML = `<option value="">Selecione a unidade</option>${opts}`;
      } else {
        el.innerHTML = `<option value="">Todas as unidades</option>${opts}`;
      }
      const prev = valoresSalvos[id];
      if (prev && [...el.options].some((o) => o.value === prev)) {
        el.value = prev;
      }
    });
  }

  function atualizarEnergiaCalcPreview() {
    const form = document.getElementById("energiaEquipamentoForm");
    if (!form) return;
    const params = {
      potencia_watts: form.potencia_watts?.value,
      quantidade: form.quantidade?.value,
      horas_por_dia: form.horas_por_dia?.value,
      dias_uso_mes: form.dias_uso_mes?.value,
      valor_kwh: form.valor_kwh?.value,
    };
    const { consumo_kwh, custo_estimado } = energiaCalcular(params);
    const kEl = document.getElementById("energiaPreviewKwh");
    const cEl = document.getElementById("energiaPreviewCusto");
    if (kEl) kEl.textContent = fmtNum(consumo_kwh, 4) + " kWh";
    if (cEl) cEl.textContent = fmtMoeda(custo_estimado);
  }

  function destroyEnergiaCharts() {
    Object.keys(ENERGIA_CHARTS).forEach((k) => {
      if (ENERGIA_CHARTS[k]) {
        ENERGIA_CHARTS[k].destroy();
        ENERGIA_CHARTS[k] = null;
      }
    });
  }

  function renderEnergiaCharts(data) {
    if (typeof Chart === "undefined") return;
    destroyEnergiaCharts();
    const porU = data.por_unidade || [];
    const porS = data.por_setor || [];
    const rank = data.ranking_equipamentos || [];
    const comp = data.comparativo_mensal || [];

    const mk = (id, cfg) => {
      const cv = document.getElementById(id);
      if (!cv) return;
      ENERGIA_CHARTS[id] = new Chart(cv, cfg);
    };

    mk("energiaChartUnidadeKwh", {
      type: "bar",
      data: {
        labels: porU.map((x) => (x.unidade_nome || "").slice(0, 18)),
        datasets: [{ label: "kWh", data: porU.map((x) => x.consumo_kwh), backgroundColor: "#42a5f5" }],
      },
      options: { responsive: true, plugins: { legend: { display: false } } },
    });
    mk("energiaChartUnidadeCusto", {
      type: "bar",
      data: {
        labels: porU.map((x) => (x.unidade_nome || "").slice(0, 18)),
        datasets: [{ label: "R$", data: porU.map((x) => x.custo_estimado), backgroundColor: "#66bb6a" }],
      },
      options: { responsive: true, plugins: { legend: { display: false } } },
    });
    mk("energiaChartSetor", {
      type: "pie",
      data: {
        labels: porS.slice(0, 8).map((x) => `${(x.setor || "").slice(0, 12)} (${(x.unidade_nome || "").slice(0, 8)})`),
        datasets: [{ data: porS.slice(0, 8).map((x) => x.consumo_kwh), backgroundColor: ["#ef5350", "#ab47bc", "#5c6bc0", "#29b6f6", "#26a69a", "#9ccc65", "#ffa726", "#8d6e63"] }],
      },
      options: { responsive: true },
    });
    mk("energiaChartRanking", {
      type: "bar",
      data: {
        labels: rank.slice(0, 10).map((x) => (x.equipamento_nome || "").slice(0, 20)),
        datasets: [{ label: "kWh", data: rank.slice(0, 10).map((x) => x.consumo_kwh), backgroundColor: "#ff7043" }],
      },
      options: { indexAxis: "y", responsive: true, plugins: { legend: { display: false } } },
    });
    mk("energiaChartMesKwh", {
      type: "line",
      data: {
        labels: comp.map((x) => x.mes),
        datasets: [{ label: "kWh", data: comp.map((x) => Number(x.consumo_kwh)), borderColor: "#1976d2", fill: false }],
      },
      options: { responsive: true },
    });
    mk("energiaChartMesCusto", {
      type: "line",
      data: {
        labels: comp.map((x) => x.mes),
        datasets: [{ label: "R$", data: comp.map((x) => Number(x.custo_estimado)), borderColor: "#388e3c", fill: false }],
      },
      options: { responsive: true },
    });
  }

  function renderEnergiaDashboard(data) {
    const c = data.cards || {};
    const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
    set("energiaCardKwh", fmtNum(c.consumo_total_kwh, 2) + " kWh");
    set("energiaCardCusto", fmtMoeda(c.custo_total_estimado));
    set("energiaCardUnidade", c.unidade_maior_consumo?.unidade_nome || "—");
    set("energiaCardSetor", c.setor_maior_consumo ? `${c.setor_maior_consumo.setor} (${c.setor_maior_consumo.unidade_nome})` : "—");
    set("energiaCardEquip", c.equipamento_maior_consumo?.equipamento_nome || "—");
    set("energiaCardQtd", String(c.qtd_equipamentos ?? 0));

    const alertHost = document.getElementById("energiaAlertasLista");
    if (alertHost) {
      const alertas = data.alertas || [];
      alertHost.innerHTML = alertas.length
        ? alertas.map((a) => `<div class="energia-alerta-item ${a.tipo === "alto_consumo" ? "energia-alerta-item--alto" : ""}">${esc(a.msg)}</div>`).join("")
        : '<p class="subtle-text">Nenhum alerta no momento.</p>';
    }
    renderEnergiaCharts(data);
  }

  async function loadEnergiaDashboardSection() {
    await populateEnergiaUnidadeSelects();
    const qs = energiaQueryFromFiltros("energiaDashFiltro");
    try {
      const data = await window.fetchJSON("/energia/dashboard" + qs);
      energiaState.dashboard = data;
      renderEnergiaDashboard(data);
    } catch (err) {
      window.showToast?.(err?.message || "Erro ao carregar dashboard de energia.", "error");
    }
  }

  function renderEnergiaEquipamentosTable(rows) {
    const tb = document.getElementById("energiaEquipamentosTable");
    if (!tb) return;
    if (!rows.length) {
      tb.innerHTML = '<tr><td colspan="13" style="text-align:center;color:#607d8b">Nenhum equipamento cadastrado.</td></tr>';
      return;
    }
    tb.innerHTML = rows.map((r) => `<tr>
      <td data-label="Unidade">${esc(r.unidade_nome)}</td>
      <td data-label="Setor">${esc(r.setor)}</td>
      <td data-label="Equipamento">${esc(r.equipamento_nome)}</td>
      <td data-label="Tipo">${esc(r.equipamento_tipo || "—")}</td>
      <td data-label="Potência">${fmtNum(r.potencia_watts, 0)} W</td>
      <td data-label="Tensão">${r.tensao}V</td>
      <td data-label="Qtd">${r.quantidade}</td>
      <td data-label="Horas/dia">${Number(r.horas_por_dia) > 0 ? fmtNum(r.horas_por_dia, 1) : "—"}</td>
      <td data-label="Dias/mês">${Number(r.dias_uso_mes) > 0 ? r.dias_uso_mes : "—"}</td>
      <td data-label="Consumo kWh">${fmtNum(r.consumo_kwh, 4)}</td>
      <td data-label="Custo">${fmtMoeda(r.custo_estimado)}</td>
      <td data-label="Ações" class="table-actions">
        <button type="button" class="btn secondary btn-sm" data-energia-ver="${r.id}">Ver</button>
        <button type="button" class="btn secondary btn-sm" data-energia-edit="${r.id}">Editar</button>
        <button type="button" class="btn danger btn-sm" data-energia-del="${r.id}">Excluir</button>
      </td>
    </tr>`).join("");
  }

  async function loadEnergiaEquipamentosSection() {
    await populateEnergiaUnidadeSelects();
    const qs = energiaQueryFromFiltros("energiaFiltro");
    try {
      const list = await window.fetchJSON("/energia/equipamentos" + qs);
      energiaState.equipamentos = Array.isArray(list) ? list : [];
      renderEnergiaEquipamentosTable(energiaState.equipamentos);
    } catch (err) {
      window.showToast?.(err?.message || "Erro ao listar equipamentos.", "error");
    }
  }

  async function abrirEnergiaFormModal(row) {
    energiaState.edicaoId = row?.id ?? null;
    const title = document.getElementById("energiaEquipamentoModalTitle");
    const form = document.getElementById("energiaEquipamentoForm");
    if (!form) return;
    await populateEnergiaUnidadeSelects();
    form.reset();
    await populateEnergiaUnidadeSelects();
    if (title) title.textContent = row ? "Editar equipamento" : "Novo equipamento";
    const unidadeSelect = form.elements.unidade_id || document.getElementById("energiaFormUnidade");
    if (row) {
      if (unidadeSelect) unidadeSelect.value = String(row.unidade_id);
      form.setor.value = row.setor || "";
      form.equipamento_nome.value = row.equipamento_nome || "";
      form.equipamento_tipo.value = row.equipamento_tipo || "";
      form.potencia_watts.value = row.potencia_watts;
      form.tensao.value = String(row.tensao);
      form.quantidade.value = row.quantidade;
      form.horas_por_dia.value = row.horas_por_dia;
      form.dias_uso_mes.value = row.dias_uso_mes;
      form.valor_kwh.value = row.valor_kwh;
      form.observacoes.value = row.observacoes || "";
    } else {
      form.tensao.value = "220";
      form.quantidade.value = "1";
      if (form.horas_por_dia) form.horas_por_dia.value = "";
      if (form.dias_uso_mes) form.dias_uso_mes.value = "";
      form.valor_kwh.value = "0.75";
      const user = typeof window.getUser === "function" ? window.getUser() : null;
      if (unidadeSelect && user?.unidade_id) {
        unidadeSelect.value = String(user.unidade_id);
      }
    }
    atualizarEnergiaCalcPreview();
    window.toggleModal?.(document.getElementById("energiaEquipamentoModal"), true);
  }

  async function salvarEnergiaEquipamento(e) {
    e.preventDefault();
    const form = document.getElementById("energiaEquipamentoForm");
    if (!form) return;
    const unidadeEl = form.elements.unidade_id || document.getElementById("energiaFormUnidade");
    const unidadeId = unidadeEl?.value?.trim();
    if (!unidadeId) {
      window.showToast?.("Selecione a unidade.", "warning");
      return;
    }
    const payload = {
      unidade_id: unidadeId,
      setor: form.setor.value.trim(),
      equipamento_nome: form.equipamento_nome.value.trim(),
      equipamento_tipo: form.equipamento_tipo.value.trim() || null,
      potencia_watts: form.potencia_watts.value,
      tensao: form.tensao.value,
      quantidade: form.quantidade.value,
      horas_por_dia: form.horas_por_dia.value === "" ? 0 : form.horas_por_dia.value,
      dias_uso_mes: form.dias_uso_mes.value === "" ? 0 : form.dias_uso_mes.value,
      valor_kwh: form.valor_kwh.value,
      observacoes: form.observacoes.value.trim() || null,
    };
    const calc = energiaCalcular(payload);
    try {
      if (energiaState.edicaoId) {
        await window.fetchJSON(`/energia/equipamentos/${energiaState.edicaoId}`, { method: "PUT", body: JSON.stringify(payload) });
        window.showToast?.("Equipamento atualizado.", "success");
      } else {
        await window.fetchJSON("/energia/equipamentos", { method: "POST", body: JSON.stringify(payload) });
        window.showToast?.("Equipamento cadastrado.", "success");
      }
      window.toggleModal?.(document.getElementById("energiaEquipamentoModal"), false);
      energiaState.edicaoId = null;
      await loadEnergiaEquipamentosSection();
    } catch (err) {
      window.showToast?.(err?.message || "Erro ao salvar.", "error");
    }
  }

  function preencherEnergiaProjecaoSelect() {
    const sel = document.getElementById("energiaProjEquipamento");
    if (!sel) return;
    const u = document.getElementById("energiaProjUnidade")?.value;
    let lista = energiaState.equipamentos;
    if (u) lista = lista.filter((x) => String(x.unidade_id) === u);
    sel.innerHTML = '<option value="">Selecione o equipamento</option>' +
      lista.map((r) => `<option value="${r.id}">${esc(r.equipamento_nome)} — ${esc(r.unidade_nome)}</option>`).join("");
  }

  function aplicarEnergiaProjecaoBase() {
    const id = document.getElementById("energiaProjEquipamento")?.value;
    const row = energiaState.equipamentos.find((x) => String(x.id) === String(id));
    energiaState.projecaoBase = row || null;
    const f = document.getElementById("energiaProjecaoForm");
    if (!f || !row) return;
    f.horas_por_dia.value = row.horas_por_dia;
    f.dias_uso_mes.value = row.dias_uso_mes;
    f.valor_kwh.value = row.valor_kwh;
    f.quantidade.value = row.quantidade;
    calcularEnergiaProjecao();
  }

  function calcularEnergiaProjecao() {
    const base = energiaState.projecaoBase;
    const f = document.getElementById("energiaProjecaoForm");
    const out = document.getElementById("energiaProjecaoResultado");
    if (!base || !f || !out) {
      if (out) out.innerHTML = '<p class="subtle-text">Selecione um equipamento para simular.</p>';
      return;
    }
    const atual = { potencia_watts: base.potencia_watts, quantidade: base.quantidade, horas_por_dia: base.horas_por_dia, dias_uso_mes: base.dias_uso_mes, valor_kwh: base.valor_kwh };
    const proj = { potencia_watts: base.potencia_watts, quantidade: f.quantidade.value, horas_por_dia: f.horas_por_dia.value, dias_uso_mes: f.dias_uso_mes.value, valor_kwh: f.valor_kwh.value };
    const ca = energiaCalcular(atual);
    const cp = energiaCalcular(proj);
    const dk = cp.consumo_kwh - ca.consumo_kwh;
    const dc = cp.custo_estimado - ca.custo_estimado;
    const cls = dk <= 0 ? "energia-projecao-diff--economia" : "energia-projecao-diff--aumento";
    out.innerHTML = `
      <p><strong>Atual:</strong> ${fmtNum(ca.consumo_kwh, 4)} kWh — ${fmtMoeda(ca.custo_estimado)}</p>
      <p><strong>Projetado:</strong> ${fmtNum(cp.consumo_kwh, 4)} kWh — ${fmtMoeda(cp.custo_estimado)}</p>
      <p class="${cls}"><strong>Diferença:</strong> ${dk >= 0 ? "+" : ""}${fmtNum(dk, 4)} kWh | ${dc >= 0 ? "+" : ""}${fmtMoeda(dc)}</p>
    `;
  }

  async function loadEnergiaProjecaoSection() {
    await loadEnergiaEquipamentosSection();
    populateEnergiaUnidadeSelects();
    preencherEnergiaProjecaoSelect();
  }

  async function downloadEnergiaRelatorio(tipo) {
    const qs = energiaQueryFromFiltros("energiaRelFiltro");
    const path = tipo === "pdf" ? "/energia/relatorio-pdf" : "/energia/relatorio-csv";
    const user = typeof window.getUser === "function" ? window.getUser() : null;
    const headers = {
      ...(user?.token ? { Authorization: `Bearer ${user.token}` } : {}),
      ...(user?.id != null ? { "X-Usuario-Id": String(user.id) } : {}),
    };
    const res = await fetch(`${ENERGIA_API_URL}${path}${qs}`, { method: "GET", headers, cache: "no-store" });
    if (!res.ok) throw new Error("Erro ao gerar relatório");
    const blob = await res.blob();
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = tipo === "pdf" ? "relatorio-energia.pdf" : "relatorio-energia.csv";
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
  }

  async function loadEnergiaRelatoriosSection() {
    await populateEnergiaUnidadeSelects();
    const host = document.getElementById("energiaRelatorioPreview");
    if (!host) return;
    const qs = energiaQueryFromFiltros("energiaRelFiltro");
    try {
      const list = await window.fetchJSON("/energia/equipamentos" + qs);
      if (!list.length) {
        host.innerHTML = '<p class="subtle-text">Nenhum registro para os filtros.</p>';
        return;
      }
      let tk = 0, tc = 0;
      host.innerHTML = `<div class="table-wrapper"><table><thead><tr><th>Unidade</th><th>Setor</th><th>Equipamento</th><th>kWh</th><th>Custo</th></tr></thead><tbody>${
        list.map((r) => { tk += Number(r.consumo_kwh); tc += Number(r.custo_estimado);
          return `<tr><td>${esc(r.unidade_nome)}</td><td>${esc(r.setor)}</td><td>${esc(r.equipamento_nome)}</td><td>${fmtNum(r.consumo_kwh, 2)}</td><td>${fmtMoeda(r.custo_estimado)}</td></tr>`;
        }).join("")
      }</tbody></table></div><p class="subtle-text" style="margin-top:8px">Total: ${fmtNum(tk, 2)} kWh | ${fmtMoeda(tc)} | ${list.length} registro(s)</p>`;
    } catch (err) {
      host.innerHTML = `<p style="color:#c62828">${esc(err?.message)}</p>`;
    }
  }

  function setupEnergiaModule() {
    document.getElementById("energiaDashAtualizar")?.addEventListener("click", () => loadEnergiaDashboardSection());
    document.getElementById("energiaFiltroAplicar")?.addEventListener("click", () => loadEnergiaEquipamentosSection());
    document.getElementById("energiaFiltroLimpar")?.addEventListener("click", () => {
      ["energiaFiltroUnidade", "energiaFiltroSetor", "energiaFiltroEquipamento", "energiaFiltroTipo", "energiaFiltroTensao", "energiaFiltroMes"].forEach((id) => {
        const el = document.getElementById(id);
        if (el) el.value = "";
      });
      document.getElementById("energiaFiltroMaiorConsumo").checked = false;
      document.getElementById("energiaFiltroMaiorCusto").checked = false;
      loadEnergiaEquipamentosSection();
    });
    document.getElementById("energiaNovoEquipamento")?.addEventListener("click", () => {
      abrirEnergiaFormModal(null).catch((err) => window.showToast?.(err?.message || "Erro ao abrir formulário.", "error"));
    });
    document.getElementById("energiaEquipamentoForm")?.addEventListener("submit", salvarEnergiaEquipamento);
    document.getElementById("energiaEquipamentoForm")?.addEventListener("input", atualizarEnergiaCalcPreview);
    document.getElementById("closeEnergiaEquipamentoModal")?.addEventListener("click", () => window.toggleModal?.(document.getElementById("energiaEquipamentoModal"), false));
    document.getElementById("cancelEnergiaEquipamento")?.addEventListener("click", () => window.toggleModal?.(document.getElementById("energiaEquipamentoModal"), false));

    document.getElementById("energiaEquipamentosTable")?.addEventListener("click", async (e) => {
      const ver = e.target.closest("[data-energia-ver]");
      const edit = e.target.closest("[data-energia-edit]");
      const del = e.target.closest("[data-energia-del]");
      if (ver) {
        const row = energiaState.equipamentos.find((x) => String(x.id) === ver.getAttribute("data-energia-ver"));
        if (row) window.alert(`Equipamento: ${row.equipamento_nome}\nConsumo: ${fmtNum(row.consumo_kwh, 4)} kWh\nCusto: ${fmtMoeda(row.custo_estimado)}`);
      }
      if (edit) {
        const row = energiaState.equipamentos.find((x) => String(x.id) === edit.getAttribute("data-energia-edit"));
        if (row) abrirEnergiaFormModal(row).catch(() => {});
      }
      if (del) {
        const id = del.getAttribute("data-energia-del");
        if (!id || !window.confirm("Excluir este equipamento?")) return;
        try {
          await window.fetchJSON(`/energia/equipamentos/${id}`, { method: "DELETE" });
          window.showToast?.("Excluído.", "success");
          loadEnergiaEquipamentosSection();
        } catch (err) {
          window.showToast?.(err?.message || "Erro ao excluir.", "error");
        }
      }
    });

    document.getElementById("energiaProjUnidade")?.addEventListener("change", preencherEnergiaProjecaoSelect);
    document.getElementById("energiaProjEquipamento")?.addEventListener("change", aplicarEnergiaProjecaoBase);
    document.getElementById("energiaProjecaoForm")?.addEventListener("input", calcularEnergiaProjecao);
    document.getElementById("energiaRelAtualizar")?.addEventListener("click", loadEnergiaRelatoriosSection);
    document.getElementById("energiaRelPdf")?.addEventListener("click", () => downloadEnergiaRelatorio("pdf").then(() => window.showToast?.("PDF baixado.", "success")).catch((e) => window.showToast?.(e.message, "error")));
    document.getElementById("energiaRelCsv")?.addEventListener("click", () => downloadEnergiaRelatorio("csv").then(() => window.showToast?.("Planilha CSV baixada (abra no Excel).", "success")).catch((e) => window.showToast?.(e.message, "error")));
  }

  window.ENERGIA_MODULOS = ENERGIA_MODULOS;
  window.energiaCalcular = energiaCalcular;
  window.loadEnergiaDashboardSection = loadEnergiaDashboardSection;
  window.loadEnergiaEquipamentosSection = loadEnergiaEquipamentosSection;
  window.loadEnergiaProjecaoSection = loadEnergiaProjecaoSection;
  window.loadEnergiaRelatoriosSection = loadEnergiaRelatoriosSection;
  window.setupEnergiaModule = setupEnergiaModule;
  window.destroyEnergiaCharts = destroyEnergiaCharts;
})();
