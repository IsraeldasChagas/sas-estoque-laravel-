/**
 * RH — Rescisão Trabalhista
 */
(function () {
  const rrState = {
    catalogos: null,
    ultimoCalculo: null,
    ultimoComparativo: null,
    charts: {},
    calcTimer: null,
  };

  const rrEsc = (s) => (s == null ? "" : String(s).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;"));
  const rrMoeda = (v) => {
    const n = Number(v) || 0;
    return n.toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
  };
  const rrLerMoeda = (el) => {
    if (!el) return 0;
    const raw = el.dataset?.value ?? el.value ?? "0";
    const n = parseFloat(String(raw).replace(/\./g, "").replace(",", ".").replace(/[^\d.-]/g, ""));
    return Number.isFinite(n) ? n : 0;
  };
  const rrSetupMoeda = (root) => {
    (root || document).querySelectorAll("[data-rr-moeda]").forEach((inp) => {
      if (inp.dataset.rrMoedaBound === "1") return;
      inp.dataset.rrMoedaBound = "1";
      inp.addEventListener("input", () => {
        const digits = (inp.value || "").replace(/\D/g, "");
        const n = digits ? parseInt(digits, 10) / 100 : 0;
        inp.dataset.value = String(n);
        inp.value = rrMoeda(n);
        rrAgendarCalculo();
      });
    });
  };

  async function rrFetch(path, opts = {}) {
    if (typeof fetchJSON === "function" && (!opts.method || opts.method === "GET")) {
      return fetchJSON(path);
    }
    const headers = {
      "Content-Type": "application/json",
      ...(currentUser?.token ? { Authorization: `Bearer ${currentUser.token}` } : {}),
      ...(currentUser?.id != null ? { "X-Usuario-Id": String(currentUser.id) } : {}),
      ...(typeof getDeviceHeaders === "function" ? getDeviceHeaders() : {}),
    };
    const res = await fetch(`${API_URL}${path}`, { ...opts, headers: { ...headers, ...(opts.headers || {}) } });
    const text = await res.text();
    let data = {};
    try { data = text ? JSON.parse(text) : {}; } catch { data = { error: text }; }
    if (!res.ok) throw new Error(data.error || `Erro ${res.status}`);
    return data;
  }

  function rrToast(msg, type) {
    if (typeof showToast === "function") showToast(msg, type || "info");
  }

  function rrAvisoHtml() {
    const txt = rrState.catalogos?.aviso_legal || "Valores estimados. Conferir com contador.";
    return `<div class="rr-aviso-legal">⚠️ ${rrEsc(txt)}</div>`;
  }

  async function rrCarregarCatalogos() {
    if (rrState.catalogos) return rrState.catalogos;
    rrState.catalogos = await rrFetch("/rh/rescisoes/catalogos");
    return rrState.catalogos;
  }

  function rrOpts(map, sel) {
    return Object.entries(map || {}).map(([k, v]) => `<option value="${rrEsc(k)}"${sel === k ? " selected" : ""}>${rrEsc(v)}</option>`).join("");
  }

  async function rrPreencherUnidades(selectId, placeholder) {
    const sel = document.getElementById(selectId);
    if (!sel) return;
    if (typeof loadUnidades === "function") await loadUnidades(false).catch(() => {});
    const unidades = (typeof state !== "undefined" && state.unidades) ? state.unidades : [];
    sel.innerHTML = `<option value="">${rrEsc(placeholder || "Selecione")}</option>`
      + unidades.map((u) => `<option value="${u.id}">${rrEsc(u.nome)}</option>`).join("");
  }

  async function rrPreencherFuncionarios(selectId, unidadeId, placeholder) {
    const sel = document.getElementById(selectId);
    if (!sel) return;
    const cur = sel.value;
    let url = "/funcionarios?status=ativo";
    if (unidadeId) url += `&unidade_id=${encodeURIComponent(unidadeId)}`;
    const lista = await rrFetch(url).catch(() => []);
    const ph = placeholder || "Selecione";
    sel.innerHTML = `<option value="">${rrEsc(ph)}</option>`
      + (Array.isArray(lista) ? lista : []).map((f) => `<option value="${f.id}" data-cargo="${rrEsc(f.cargo)}" data-adm="${rrEsc(f.data_admissao || "")}">${rrEsc(f.nome_completo)}</option>`).join("");
    if (cur) sel.value = cur;
  }

  function rrLerFormulario(prefix) {
    const g = (id) => document.getElementById(prefix + id);
    return {
      unidade_id: g("Unidade")?.value || null,
      funcionario_id: g("Funcionario")?.value || null,
      cargo: g("Cargo")?.value || "",
      salario_base: rrLerMoeda(g("Salario")),
      data_admissao: g("Admissao")?.value || "",
      data_demissao: g("Demissao")?.value || "",
      tipo_contrato: g("TipoContrato")?.value || "prazo_indeterminado",
      tipo_rescisao: g("TipoRescisao")?.value || "dispensa_sem_justa_causa",
      aviso_previo_tipo: g("AvisoPrevio")?.value || "indenizado",
      dias_trabalhados_mes: parseInt(g("DiasMes")?.value || "0", 10) || 0,
      ferias_vencidas: rrLerMoeda(g("FeriasVenc")),
      ferias_proporcionais: rrLerMoeda(g("FeriasProp")),
      decimo_terceiro_proporcional: rrLerMoeda(g("Decimo")),
      horas_extras: rrLerMoeda(g("HorasExtras")),
      adicionais: rrLerMoeda(g("Adicionais")),
      descontos: rrLerMoeda(g("Descontos")),
      faltas: rrLerMoeda(g("Faltas")),
      adiantamentos: rrLerMoeda(g("Adiantamentos")),
      vale_transporte: rrLerMoeda(g("ValeTransp")),
      vale_alimentacao: rrLerMoeda(g("ValeAlim")),
      fgts_mensal: rrLerMoeda(g("FgtsMensal")),
      multa_fgts_percentual: parseInt(g("MultaFgts")?.value || "0", 10) || 0,
      observacoes: g("Obs")?.value || "",
    };
  }

  function rrRenderResultado(containerId, calc) {
    const el = document.getElementById(containerId);
    if (!el || !calc) return;
    const linhas = [
      ["Saldo de salário", calc.saldo_salario],
      ["Aviso prévio indenizado", calc.aviso_previo_indenizado],
      ["Desconto aviso prévio", calc.aviso_previo_desconto],
      ["13º proporcional", calc.decimo_terceiro_proporcional],
      ["Férias vencidas", calc.ferias_vencidas],
      ["Férias proporcionais", calc.ferias_proporcionais],
      ["1/3 de férias", calc.terco_ferias],
      ["Horas extras", calc.horas_extras],
      ["Adicionais", calc.adicionais],
      ["INSS estimado", calc.inss_estimado],
      ["IRRF estimado", calc.irrf_estimado],
      ["FGTS estimado", calc.fgts_estimado],
      ["Multa FGTS", calc.multa_fgts_valor],
      ["Total bruto", calc.total_bruto],
      ["Total descontos", calc.total_descontos],
      ["Total líquido", calc.total_liquido],
      ["Custo empresa", calc.custo_empresa],
    ];
    el.innerHTML = rrAvisoHtml() + `<div class="rr-resultado-box"><h4>Resultado estimado</h4>`
      + linhas.map(([lbl, val]) => `<div class="rr-resultado-linha"><span>${rrEsc(lbl)}</span><strong>${rrMoeda(val)}</strong></div>`).join("")
      + `</div>`;
  }

  function rrFormPrefixAtivo() {
    const sim = document.getElementById("rhRescisaoSimuladorSection");
    const cal = document.getElementById("rhRescisaoCalculoSection");
    if (cal && !cal.classList.contains("hidden")) return "rrCal";
    if (sim && !sim.classList.contains("hidden")) return "rrSim";
    return null;
  }

  function rrResultadoId(prefix) {
    return prefix === "rrCal" ? "rrCalResultado" : "rrSimResultado";
  }

  function rrAgendarCalculo() {
    clearTimeout(rrState.calcTimer);
    rrState.calcTimer = setTimeout(() => rrExecutarCalculoLive().catch(() => {}), 400);
  }

  async function rrExecutarCalculoLive() {
    const prefix = rrFormPrefixAtivo();
    if (!prefix) return;
    const body = rrLerFormulario(prefix);
    if (!body.salario_base && !body.funcionario_id) return;
    const calc = await rrFetch("/rh/rescisoes/calcular", { method: "POST", body: JSON.stringify(body) });
    rrState.ultimoCalculo = calc;
    rrRenderResultado(rrResultadoId(prefix), calc);
  }

  async function rrAbrirPdfRescisao(id) {
    if (!id || !currentUser) {
      rrToast("Sessão inválida. Faça login novamente.", "error");
      return;
    }
    const headers = {
      ...(typeof getDeviceHeaders === "function" ? getDeviceHeaders() : {}),
      ...(currentUser.token ? { Authorization: `Bearer ${currentUser.token}` } : {}),
      ...(currentUser.id != null ? { "X-Usuario-Id": String(currentUser.id) } : {}),
    };
    const res = await fetch(`${API_URL}/rh/rescisoes/${id}/pdf`, { method: "GET", headers, cache: "no-store" });
    if (!res.ok) {
      let msg = `Erro ${res.status}`;
      try {
        const ct = res.headers.get("Content-Type") || "";
        if (ct.includes("json")) {
          const j = await res.json();
          if (j.error) msg = j.error;
        }
      } catch (_) {}
      throw new Error(msg);
    }
    const blob = await res.blob();
    const url = URL.createObjectURL(blob);
    window.open(url, "_blank", "noopener,noreferrer");
    setTimeout(() => URL.revokeObjectURL(url), 120000);
    rrToast("PDF aberto — use o navegador para salvar ou imprimir.", "success");
  }

  function rrDestroyChart(key) {
    if (rrState.charts[key]) {
      rrState.charts[key].destroy();
      delete rrState.charts[key];
    }
  }

  function rrChartBar(canvasId, key, labels, data, label) {
    const cv = document.getElementById(canvasId);
    if (!cv || typeof Chart === "undefined") return;
    rrDestroyChart(key);
    rrState.charts[key] = new Chart(cv, {
      type: "bar",
      data: { labels, datasets: [{ label, data, backgroundColor: "#3949ab" }] },
      options: { responsive: true, plugins: { legend: { display: false } } },
    });
  }

  async function loadRhRescisaoDashboard() {
    await rrCarregarCatalogos();
    const wrap = document.getElementById("rrDashConteudo");
    if (!wrap) return;
    wrap.innerHTML = '<p class="subtle-text">Carregando dashboard…</p>';
    const data = await rrFetch("/rh/rescisoes/dashboard");
    const c = data.cards || {};
    wrap.innerHTML = rrAvisoHtml() + `
      <div class="rr-cards">
        <div class="rr-card"><span>Rescisões no mês</span><strong>${c.total_mes ?? 0}</strong></div>
        <div class="rr-card"><span>Custo estimado no mês</span><strong>${rrMoeda(c.custo_mes)}</strong></div>
        <div class="rr-card"><span>Unidades com rescisão</span><strong>${(c.por_unidade || []).length}</strong></div>
        <div class="rr-card"><span>Tipos distintos</span><strong>${(c.por_tipo || []).length}</strong></div>
      </div>
      <div class="rr-grid-2">
        <div class="rr-chart-wrap"><h4>Rescisões por mês</h4><canvas id="rrChartMes"></canvas></div>
        <div class="rr-chart-wrap"><h4>Custo por mês</h4><canvas id="rrChartCustoMes"></canvas></div>
        <div class="rr-chart-wrap"><h4>Por unidade</h4><canvas id="rrChartUnidade"></canvas></div>
        <div class="rr-chart-wrap"><h4>Por tipo</h4><canvas id="rrChartTipo"></canvas></div>
      </div>
      <div class="rr-grid-2">
        <div class="table-card form-card--wide"><header>Alertas — experiência</header><div class="rr-alertas" id="rrDashAlertasExp"></div></div>
        <div class="table-card form-card--wide"><header>Alertas — férias vencidas</header><div class="rr-alertas" id="rrDashAlertasFerias"></div></div>
        <div class="table-card form-card--wide"><header>Ranking maior custo</header><div id="rrDashRanking"></div></div>
        <div class="table-card form-card--wide"><header>Evolução de custos trabalhistas</header><canvas id="rrChartEvolucao"></canvas></div>
      </div>`;

    const gMes = data.graficos?.por_mes || [];
    rrChartBar("rrChartMes", "mes", gMes.map((x) => x.mes), gMes.map((x) => x.total), "Rescisões");
    rrChartBar("rrChartCustoMes", "custoMes", gMes.map((x) => x.mes), gMes.map((x) => Number(x.custo)), "Custo");

    const gUni = data.graficos?.por_unidade || [];
    rrChartBar("rrChartUnidade", "uni", gUni.map((x) => x.unidade_nome || "—"), gUni.map((x) => x.total), "Qtd");

    const gTipo = data.graficos?.por_tipo || [];
    rrChartBar("rrChartTipo", "tipo", gTipo.map((x) => rrState.catalogos?.tipos_rescisao?.[x.tipo_rescisao] || x.tipo_rescisao), gTipo.map((x) => x.total), "Qtd");

    const exp = data.alertas?.experiencia || [];
    document.getElementById("rrDashAlertasExp").innerHTML = exp.length
      ? exp.map((a) => `<div class="rr-alerta"><strong>${rrEsc(a.nome)}</strong> — contrato de experiência vence em ${rrEsc(a.fim_experiencia)} (${a.dias_restantes} dias)</div>`).join("")
      : '<p class="subtle-text">Nenhum contrato de experiência próximo do fim.</p>';

    const ferias = data.alertas?.ferias_vencidas || [];
    document.getElementById("rrDashAlertasFerias").innerHTML = ferias.length
      ? ferias.map((a) => `<div class="rr-alerta"><strong>${rrEsc(a.nome)}</strong> — ${rrEsc(a.msg || "Verificar férias vencidas")} (${a.anos} ano(s) de casa)</div>`).join("")
      : '<p class="subtle-text">Nenhum alerta de férias no momento.</p>';

    rrChartBar("rrChartEvolucao", "evolucao", gMes.map((x) => x.mes), gMes.map((x) => Number(x.custo)), "Custo rescisório");

    const rank = data.ranking_custo || [];
    document.getElementById("rrDashRanking").innerHTML = rank.length
      ? `<table><thead><tr><th>Funcionário</th><th>Custo</th><th>Data</th></tr></thead><tbody>`
        + rank.map((r) => `<tr><td>${rrEsc(r.funcionario_nome)}</td><td>${rrMoeda(r.custo_empresa)}</td><td>${rrEsc(r.data_demissao)}</td></tr>`).join("")
        + "</tbody></table>"
      : '<p class="subtle-text">Sem rescisões registradas.</p>';
  }

  async function loadRhRescisaoSimulador() {
    await rrCarregarCatalogos();
    await rrPreencherUnidades("rrSimUnidade", "Selecione a unidade");
    await rrPreencherFuncionarios("rrSimFuncionario");
    const cat = rrState.catalogos;
    const tc = document.getElementById("rrSimTipoContrato");
    const tr = document.getElementById("rrSimTipoRescisao");
    const ap = document.getElementById("rrSimAvisoPrevio");
    if (tc) tc.innerHTML = rrOpts(cat.tipos_contrato);
    if (tr) tr.innerHTML = rrOpts(cat.tipos_rescisao);
    if (ap) ap.innerHTML = rrOpts(cat.aviso_previo);
    rrSetupMoeda(document.getElementById("rhRescisaoSimuladorSection"));
    const simRes = document.getElementById("rrSimResultado");
    if (simRes) simRes.innerHTML = rrAvisoHtml();
  }

  async function loadRhRescisaoCalculo() {
    await rrCarregarCatalogos();
    await rrPreencherUnidades("rrCalUnidade", "Selecione a unidade");
    await rrPreencherFuncionarios("rrCalFuncionario");
    const cat = rrState.catalogos;
    const tc = document.getElementById("rrCalTipoContrato");
    const tr = document.getElementById("rrCalTipoRescisao");
    const ap = document.getElementById("rrCalAvisoPrevio");
    if (tc) tc.innerHTML = rrOpts(cat.tipos_contrato);
    if (tr) tr.innerHTML = rrOpts(cat.tipos_rescisao);
    if (ap) ap.innerHTML = rrOpts(cat.aviso_previo);
    rrSetupMoeda(document.getElementById("rhRescisaoCalculoSection"));
    const res = document.getElementById("rrCalResultado");
    if (res) res.innerHTML = rrAvisoHtml();
    if (rrState.ultimoCalculo) rrRenderResultado("rrCalResultado", rrState.ultimoCalculo);
  }

  async function loadRhRescisaoComparativo() {
    await rrCarregarCatalogos();
    await rrPreencherUnidades("rrCompUnidade", "Unidade");
    await rrPreencherFuncionarios("rrCompFuncionario");
    document.getElementById("rrCompResultado").innerHTML = rrAvisoHtml() + '<p class="subtle-text">Preencha os dados e clique em Comparar cenários.</p>';
    rrSetupMoeda(document.getElementById("rhRescisaoComparativoSection"));
  }

  async function rrExecutarComparativo() {
    const body = {
      unidade_id: document.getElementById("rrCompUnidade")?.value || null,
      funcionario_id: document.getElementById("rrCompFuncionario")?.value || null,
      salario_base: rrLerMoeda(document.getElementById("rrCompSalario")),
      data_admissao: document.getElementById("rrCompAdmissao")?.value || "",
      data_demissao: document.getElementById("rrCompDemissao")?.value || "",
      dias_trabalhados_mes: parseInt(document.getElementById("rrCompDiasMes")?.value || "0", 10) || 0,
      ferias_vencidas: rrLerMoeda(document.getElementById("rrCompFeriasVenc")),
      fgts_mensal: rrLerMoeda(document.getElementById("rrCompFgtsMensal")),
    };
    const data = await rrFetch("/rh/rescisoes/comparar", { method: "POST", body: JSON.stringify(body) });
    rrState.ultimoComparativo = data;
    const melhorEmp = data.melhor_cenario_empresa;
    const melhorFunc = data.melhor_cenario_funcionario;
    const html = rrAvisoHtml() + `<div class="rr-grid-2">`
      + (data.cenarios || []).map((c) => {
        const destaque = c.tipo_cenario === melhorEmp || c.tipo_cenario === melhorFunc;
        const badges = [];
        if (c.tipo_cenario === melhorEmp) badges.push("Melhor p/ empresa");
        if (c.tipo_cenario === melhorFunc) badges.push("Melhor p/ funcionário");
        return `<div class="rr-cenario-card${destaque ? " rr-cenario-card--destaque" : ""}">
          <h4>${rrEsc(c.tipo_label)} ${badges.length ? `<small>(${badges.join(" · ")})</small>` : ""}</h4>
          <div class="rr-cenario-metric">Líquido: <strong>${rrMoeda(c.total_liquido)}</strong></div>
          <div class="rr-cenario-metric">Custo empresa: ${rrMoeda(c.custo_empresa)}</div>
          <div class="rr-cenario-metric">FGTS/multa: ${rrMoeda(c.fgts_estimado)} + ${rrMoeda(c.multa_fgts_valor)}</div>
          <div class="rr-cenario-metric">Aviso prévio: ${c.necessita_aviso_previo ? "Sim" : "Não"}</div>
        </div>`;
      }).join("") + `</div>`;
    document.getElementById("rrCompResultado").innerHTML = html + `<div class="rr-chart-wrap" style="margin-top:1rem;"><h4>Comparativo de cenários</h4><canvas id="rrChartComparativo"></canvas></div>`;
    const labels = (data.cenarios || []).map((c) => c.tipo_label);
    const liquidos = (data.cenarios || []).map((c) => Number(c.total_liquido));
    const custos = (data.cenarios || []).map((c) => Number(c.custo_empresa));
    const cv = document.getElementById("rrChartComparativo");
    if (cv && typeof Chart !== "undefined") {
      rrDestroyChart("comparativo");
      rrState.charts.comparativo = new Chart(cv, {
        type: "bar",
        data: {
          labels,
          datasets: [
            { label: "Líquido funcionário", data: liquidos, backgroundColor: "#3949ab" },
            { label: "Custo empresa", data: custos, backgroundColor: "#ff7043" },
          ],
        },
        options: { responsive: true },
      });
    }
  }

  async function loadRhRescisaoHistorico() {
    await rrCarregarCatalogos();
    await rrPreencherUnidades("rrHistUnidade", "Todas");
    const uHist = document.getElementById("rrHistUnidade")?.value;
    await rrPreencherFuncionarios("rrHistFuncionario", uHist || null, "Todos");
    const selTipo = document.getElementById("rrHistTipoRescisao");
    if (selTipo && rrState.catalogos?.tipos_rescisao) {
      const cur = selTipo.value;
      selTipo.innerHTML = '<option value="">Todos</option>' + rrOpts(rrState.catalogos.tipos_rescisao, cur || null);
    }
    const params = new URLSearchParams();
    const u = document.getElementById("rrHistUnidade")?.value;
    const f = document.getElementById("rrHistFuncionario")?.value;
    const tr = document.getElementById("rrHistTipoRescisao")?.value;
    const st = document.getElementById("rrHistStatus")?.value;
    const di = document.getElementById("rrHistDataIni")?.value;
    const df = document.getElementById("rrHistDataFim")?.value;
    if (u) params.append("unidade_id", u);
    if (f) params.append("funcionario_id", f);
    if (tr) params.append("tipo_rescisao", tr);
    if (st) params.append("status", st);
    if (di) params.append("data_inicio", di);
    if (df) params.append("data_fim", df);
    const qs = params.toString();
    const lista = await rrFetch(`/rh/rescisoes${qs ? `?${qs}` : ""}`);
    const tb = document.getElementById("rrHistTable");
    if (!tb) return;
    if (!lista.length) {
      tb.innerHTML = '<tr><td colspan="8" style="text-align:center;color:#607d8b">Nenhum registro.</td></tr>';
      return;
    }
    tb.innerHTML = lista.map((r) => `<tr>
      <td>${r.id}</td>
      <td>${rrEsc(r.funcionario_nome)}</td>
      <td>${rrEsc(r.unidade_nome)}</td>
      <td>${rrEsc(r.tipo_rescisao_label)}</td>
      <td>${rrMoeda(r.total_liquido)}</td>
      <td>${rrMoeda(r.custo_empresa)}</td>
      <td>${rrEsc(r.status)}</td>
      <td class="table-actions">
        <button type="button" class="table-action rr-btn-pdf" data-id="${r.id}">PDF</button>
        ${r.status === "simulacao" ? `<button type="button" class="table-action rr-btn-confirm" data-id="${r.id}">Confirmar</button>` : ""}
      </td>
    </tr>`).join("");
  }

  async function loadRhRescisaoRelatorios() {
    await rrPreencherUnidades("rrRelUnidade", "Todas");
    document.getElementById("rrRelResumo").innerHTML = rrAvisoHtml();
  }

  async function rrSalvarSimulacao(status, prefix) {
    const p = prefix || rrFormPrefixAtivo() || "rrSim";
    const body = rrLerFormulario(p);
    body.status = status || "simulacao";
    const salvo = await rrFetch("/rh/rescisoes", { method: "POST", body: JSON.stringify({ ...body, status: body.status, salvar_cenarios: true }) });
    rrToast(status === "confirmada" ? "Rescisão confirmada." : "Simulação salva.", "success");
    return salvo;
  }

  let rrBound = false;
  function rrBindOnce() {
    if (rrBound) return;
    rrBound = true;

    function rrBindFuncionarioChange(unidadeId, funcId, cargoId, admId) {
      document.getElementById(unidadeId)?.addEventListener("change", async (e) => {
        await rrPreencherFuncionarios(funcId, e.target.value);
        rrAgendarCalculo();
      });
      document.getElementById(funcId)?.addEventListener("change", (e) => {
        const opt = e.target.selectedOptions?.[0];
        if (opt) {
          const cargo = document.getElementById(cargoId);
          const adm = document.getElementById(admId);
          if (cargo && opt.dataset.cargo) cargo.value = opt.dataset.cargo;
          if (adm && opt.dataset.adm) adm.value = opt.dataset.adm;
        }
        rrAgendarCalculo();
      });
    }
    rrBindFuncionarioChange("rrSimUnidade", "rrSimFuncionario", "rrSimCargo", "rrSimAdmissao");
    rrBindFuncionarioChange("rrCalUnidade", "rrCalFuncionario", "rrCalCargo", "rrCalAdmissao");

    document.getElementById("rrHistUnidade")?.addEventListener("change", async (e) => {
      await rrPreencherFuncionarios("rrHistFuncionario", e.target.value || null);
    });

    ["rhRescisaoSimuladorSection", "rhRescisaoCalculoSection"].forEach((secId) => {
      document.getElementById(secId)?.addEventListener("input", (e) => {
        if (e.target.closest("form")) rrAgendarCalculo();
      });
      document.getElementById(secId)?.addEventListener("change", (e) => {
        if (e.target.closest("form")) rrAgendarCalculo();
      });
    });

    document.getElementById("rrSimBtnSalvar")?.addEventListener("click", () => rrSalvarSimulacao("simulacao", "rrSim").catch((e) => rrToast(e.message, "error")));
    document.getElementById("rrSimBtnConfirmar")?.addEventListener("click", () => rrSalvarSimulacao("confirmada", "rrSim").catch((e) => rrToast(e.message, "error")));
    document.getElementById("rrCalBtnSalvar")?.addEventListener("click", () => rrSalvarSimulacao("simulacao", "rrCal").catch((e) => rrToast(e.message, "error")));
    document.getElementById("rrCalBtnConfirmar")?.addEventListener("click", () => rrSalvarSimulacao("confirmada", "rrCal").catch((e) => rrToast(e.message, "error")));

    document.getElementById("rrCompBtnComparar")?.addEventListener("click", () => rrExecutarComparativo().catch((e) => rrToast(e.message, "error")));
    document.getElementById("rrHistFiltrar")?.addEventListener("click", () => loadRhRescisaoHistorico().catch((e) => rrToast(e.message, "error")));

    document.getElementById("rhRescisaoHistoricoSection")?.addEventListener("click", async (e) => {
      const pdf = e.target.closest(".rr-btn-pdf");
      const conf = e.target.closest(".rr-btn-confirm");
      if (pdf) rrAbrirPdfRescisao(pdf.dataset.id).catch((e) => rrToast(e.message, "error"));
      if (conf) {
        await rrFetch(`/rh/rescisoes/${conf.dataset.id}/confirmar`, { method: "POST", body: "{}" });
        rrToast("Confirmada.", "success");
        loadRhRescisaoHistorico().catch(() => {});
      }
    });

    document.getElementById("rrRelGerarPdf")?.addEventListener("click", () => {
      rrToast("Use o PDF individual em Histórico ou salve uma simulação.", "info");
    });
  }

  window.loadRhRescisaoDashboard = loadRhRescisaoDashboard;
  window.loadRhRescisaoSimulador = loadRhRescisaoSimulador;
  window.loadRhRescisaoCalculo = loadRhRescisaoCalculo;
  window.loadRhRescisaoComparativo = loadRhRescisaoComparativo;
  window.loadRhRescisaoHistorico = loadRhRescisaoHistorico;
  window.loadRhRescisaoRelatorios = loadRhRescisaoRelatorios;

  window.setupRhRescisaoModule = function () {
    rrBindOnce();
    rrCarregarCatalogos().catch(() => {});
  };
})();
