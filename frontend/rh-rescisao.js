/**
 * RH — Rescisão Trabalhista
 */
(function () {
  const rrState = {
    catalogos: null,
    ultimoCalculo: null,
    ultimoComparativo: null,
    ultimaRescisaoId: null,
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
    if (el.dataset?.value != null && el.dataset.value !== "") {
      const dv = parseFloat(el.dataset.value);
      if (Number.isFinite(dv)) return Math.max(0, Math.round(dv * 100) / 100);
    }
    const raw = String(el.value ?? "0").trim();
    if (!raw) return 0;
    let s = raw.replace(/[R$\s]/g, "");
    if (/^\d+$/.test(s)) {
      const digits = parseInt(s, 10);
      return digits >= 1000 ? Math.round(digits) / 100 : digits;
    }
    if (s.includes(",") && s.includes(".")) {
      s = s.replace(/\./g, "").replace(",", ".");
    } else if (s.includes(",")) {
      s = s.replace(",", ".");
    }
    const n = parseFloat(s.replace(/[^\d.-]/g, ""));
    return Number.isFinite(n) ? Math.max(0, Math.round(n * 100) / 100) : 0;
  };
  const rrSetMoeda = (id, val) => {
    const el = document.getElementById(id);
    if (!el || val == null || val === "") return;
    const n = Math.max(0, Math.round(Number(val) * 100) / 100);
    el.dataset.value = String(n);
    el.dataset.rrManual = "1";
    el.value = rrMoeda(n);
  };
  const rrCampoManual = (el) => !!(el && (el.dataset?.rrManual === "1" || (el.dataset?.value != null && el.dataset.value !== "")));
  const rrRound = (v) => Math.round((Number(v) || 0) * 100) / 100;

  const RR_LABELS_VERBAS = {
    saldo_salario: "Saldo de salário",
    horas_extras_50: "Horas extras 50%",
    horas_extras_60: "Horas extras 60%",
    reflexo_dsr: "Reflexo DSR",
    decimo_terceiro_proporcional: "13º proporcional",
    ferias_proporcionais: "Férias proporcionais",
    terco_constitucional: "1/3 férias",
    aviso_previo_indenizado: "Aviso prévio indenizado",
    decimo_terceiro_aviso_previo: "13º sobre aviso prévio",
    ferias_aviso_previo: "Férias sobre aviso prévio",
    ferias_vencidas: "Férias vencidas",
    gratificacao: "Gratificação",
    comissoes: "Comissões",
    outras_verbas: "Outras verbas",
  };
  const RR_LABELS_DESC = {
    adiantamento_salarial: "Adiantamento salarial",
    aviso_previo_descontado: "Aviso prévio descontado",
    inss_salario: "INSS salário",
    inss_13: "INSS 13º",
    irrf: "IRRF",
    irrf_13: "IRRF 13º",
    faltas: "Faltas",
    dsr_faltas: "DSR s/ faltas",
    outros_descontos: "Outros descontos",
  };

  function rrSugerirAvos13(demissao) {
    if (!demissao) return 0;
    const d = new Date(demissao + "T12:00:00");
    return Number.isNaN(d.getTime()) ? 0 : d.getMonth() + 1;
  }
  function rrSugerirAvosFerias(admissao, demissao) {
    if (!admissao || !demissao) return 0;
    try {
      const a = new Date(admissao + "T12:00:00");
      const d = new Date(demissao + "T12:00:00");
      let meses = 0;
      const cur = new Date(a.getFullYear(), a.getMonth(), 1);
      const fim = new Date(d.getFullYear(), d.getMonth(), 1);
      while (cur <= fim) {
        meses++;
        cur.setMonth(cur.getMonth() + 1);
      }
      return Math.max(0, Math.min(12, meses % 12 || (meses > 0 ? 12 : 0)));
    } catch {
      return 0;
    }
  }
  function rrEstimarInss(base) {
    const b = Math.min(Math.max(0, base), 7786.02);
    if (b <= 0) return 0;
    const faixas = [[1412, 0.075], [2666.68, 0.09], [4000.03, 0.12], [7786.02, 0.14]];
    let prev = 0;
    let inss = 0;
    for (const [limite, aliq] of faixas) {
      if (b <= prev) break;
      const fatia = Math.min(b, limite) - prev;
      if (fatia > 0) inss += fatia * aliq;
      prev = limite;
    }
    return rrRound(inss);
  }
  function rrEstimarIrrf(base) {
    const b = Math.max(0, base);
    if (b <= 2112) return 0;
    if (b <= 2826.65) return rrRound(Math.max(0, b * 0.075 - 158.4));
    if (b <= 3751.05) return rrRound(Math.max(0, b * 0.15 - 370.4));
    if (b <= 4664.68) return rrRound(Math.max(0, b * 0.225 - 651.73));
    return rrRound(Math.max(0, b * 0.275 - 884.96));
  }
  const rrSetupMoeda = (root) => {
    (root || document).querySelectorAll("[data-rr-moeda]").forEach((inp) => {
      if (inp.dataset.rrMoedaBound === "1") return;
      inp.dataset.rrMoedaBound = "1";
      inp.addEventListener("input", () => {
        const digits = (inp.value || "").replace(/\D/g, "");
        const n = digits ? parseInt(digits, 10) / 100 : 0;
        inp.dataset.value = String(n);
        inp.dataset.rrManual = "1";
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
    if (!res.ok) {
      const msg = (Array.isArray(data.erros) && data.erros.length)
        ? data.erros.join(" ")
        : (data.error || `Erro ${res.status}`);
      throw new Error(msg);
    }
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
    const sal = rrLerMoeda(g("Salario"));
    const adm = g("Admissao")?.value || "";
    const dem = g("Demissao")?.value || "";
    let avos13 = parseInt(g("Avos13")?.value ?? "", 10);
    let avosFer = parseInt(g("AvosFerias")?.value ?? "", 10);
    if (!Number.isFinite(avos13) && dem) avos13 = rrSugerirAvos13(dem);
    if (!Number.isFinite(avosFer) && adm && dem) avosFer = rrSugerirAvosFerias(adm, dem);
    const inssSalEl = g("InssSalario");
    const inss13El = g("Inss13");
    const irrfEl = g("Irrf");
    const irrf13El = g("Irrf13");
    const decimoEl = g("Decimo");
    const feriasPropEl = g("FeriasProp");
    const decimoAvisoEl = g("DecimoAviso");
    const feriasAvisoEl = g("FeriasAviso");
    const body = {
      unidade_id: g("Unidade")?.value || null,
      funcionario_id: g("Funcionario")?.value || null,
      cargo: g("Cargo")?.value || "",
      salario_base: sal,
      salario_cadastro: parseFloat(g("SalarioCadastro")?.value || "0") || 0,
      data_admissao: adm,
      data_demissao: dem,
      data_aviso_previo: g("DataAviso")?.value || dem,
      remuneracao_mes_anterior: rrLerMoeda(g("RemunMesAnt")) || sal,
      tipo_contrato: g("TipoContrato")?.value || "prazo_indeterminado",
      tipo_rescisao: g("TipoRescisao")?.value || "dispensa_sem_justa_causa",
      aviso_previo_tipo: g("AvisoPrevio")?.value || "indenizado",
      dias_trabalhados_mes: parseInt(g("DiasMes")?.value || "0", 10) || 0,
      avos_13: Math.max(0, Math.min(12, Number.isFinite(avos13) ? avos13 : 0)),
      avos_ferias: Math.max(0, Math.min(12, Number.isFinite(avosFer) ? avosFer : 0)),
      ferias_vencidas: rrLerMoeda(g("FeriasVenc")),
      ferias_proporcionais: rrLerMoeda(g("FeriasProp")),
      decimo_terceiro_proporcional: rrLerMoeda(g("Decimo")),
      decimo_aviso_previo: rrLerMoeda(g("DecimoAviso")),
      ferias_aviso_previo: rrLerMoeda(g("FeriasAviso")),
      horas_extras_50: rrLerMoeda(g("He50")),
      horas_extras_60: rrLerMoeda(g("He60")),
      gratificacao: rrLerMoeda(g("Gratificacao")),
      comissoes: rrLerMoeda(g("Comissoes")),
      reflexo_dsr: rrLerMoeda(g("ReflexoDsr")),
      outras_verbas: rrLerMoeda(g("OutrasVerbas")),
      faltas: rrLerMoeda(g("Faltas")),
      faltas_dias: parseInt(g("FaltasDias")?.value || "0", 10) || 0,
      dsr_faltas: rrLerMoeda(g("DsrFaltas")),
      adiantamentos: rrLerMoeda(g("Adiantamentos")),
      aviso_previo_descontado: rrLerMoeda(g("AvisoDescontado")),
      inss_salario: rrLerMoeda(g("InssSalario")),
      inss_13: rrLerMoeda(g("Inss13")),
      irrf_13: rrLerMoeda(g("Irrf13")),
      outros_descontos: rrLerMoeda(g("OutrosDescontos")),
      adiantamento_13: rrLerMoeda(g("Adiant13")),
      vale_transporte: rrLerMoeda(g("ValeTransp")),
      vale_alimentacao: rrLerMoeda(g("ValeAlim")),
      fgts_mensal: rrLerMoeda(g("FgtsMensal")),
      multa_fgts_percentual: parseInt(g("MultaFgts")?.value || "0", 10) || 0,
      funcionario_pis: g("Pis")?.value || "",
      funcionario_ctps: g("Ctps")?.value || "",
      funcionario_nome_mae: g("NomeMae")?.value || "",
      empresa_cnpj: g("EmpresaCnpj")?.value || "",
      empresa_razao: g("EmpresaRazao")?.value || "",
      observacoes: g("Obs")?.value || "",
      _manual_decimo: rrCampoManual(decimoEl),
      _manual_ferias_prop: rrCampoManual(feriasPropEl),
      _manual_decimo_aviso: rrCampoManual(decimoAvisoEl),
      _manual_ferias_aviso: rrCampoManual(feriasAvisoEl),
      _manual_inss_salario: rrCampoManual(inssSalEl),
      _manual_inss_13: rrCampoManual(inss13El),
      _manual_irrf: rrCampoManual(irrfEl),
      _manual_irrf_13: rrCampoManual(irrf13El),
    };
    if (irrfEl) body.irrf = rrLerMoeda(irrfEl);
    return body;
  }

  function rrAvisoEhIndenizado(tipo) {
    return String(tipo || "").toLowerCase().trim() === "indenizado";
  }

  function rrValidarEntrada(body) {
    const erros = [];
    if (!body.salario_base || body.salario_base <= 0) erros.push("Salário base é obrigatório e deve ser maior que zero.");
    if (!body.data_admissao) erros.push("Data de admissão é obrigatória.");
    if (!body.data_aviso_previo) erros.push("Data do aviso prévio é obrigatória.");
    if (!body.data_demissao) erros.push("Data do afastamento (demissão) é obrigatória.");
    if (!body.tipo_rescisao) erros.push("Tipo de rescisão é obrigatório.");
    if (!body.aviso_previo_tipo) erros.push("Tipo de aviso prévio é obrigatório.");
    if (body.dias_trabalhados_mes < 0 || body.dias_trabalhados_mes > 31) erros.push("Dias trabalhados no mês deve estar entre 0 e 31.");
    if (body.avos_13 < 0 || body.avos_13 > 12) erros.push("Avos de 13º salário deve estar entre 0 e 12.");
    if (body.avos_ferias < 0 || body.avos_ferias > 12) erros.push("Avos de férias proporcionais deve estar entre 0 e 12.");
    const alertas = [];
    if (body.salario_cadastro > 0 && body.salario_base >= body.salario_cadastro * 9.5) {
      alertas.push("Atenção: salário informado parece incompatível com o cadastro do funcionário.");
    }
    if ((body._manual_decimo_aviso || body._manual_ferias_aviso) && !rrAvisoEhIndenizado(body.aviso_previo_tipo)) {
      alertas.push("Aviso prévio não está como Indenizado — o valor de R$ 1.706,50 do aviso indenizado não entra no total bruto. Selecione Indenizado no campo Aviso prévio.");
    }
    return { erros, alertas };
  }

  function rrCalcularLocal(body) {
    const val = rrValidarEntrada(body);
    if (val.erros.length) {
      return { ok: false, erros: val.erros, alertas: val.alertas, aviso: rrState.catalogos?.aviso_legal || "" };
    }
    const e = body;
    const sal = e.salario_base;
    const avisoInd = rrAvisoEhIndenizado(e.aviso_previo_tipo) ? rrRound(sal) : 0;

    const verbasAuto = {
      saldo_salario: rrRound(sal / 30 * e.dias_trabalhados_mes),
      horas_extras_50: rrRound(e.horas_extras_50),
      horas_extras_60: rrRound(e.horas_extras_60),
      reflexo_dsr: rrRound(e.reflexo_dsr),
      decimo_terceiro_proporcional: rrRound(sal / 12 * e.avos_13),
      ferias_proporcionais: rrRound(sal / 12 * e.avos_ferias),
      terco_constitucional: 0,
      aviso_previo_indenizado: avisoInd,
      decimo_terceiro_aviso_previo: avisoInd > 0 ? rrRound(sal / 12) : 0,
      ferias_aviso_previo: avisoInd > 0 ? rrRound(sal / 12) : 0,
      ferias_vencidas: rrRound(e.ferias_vencidas),
      gratificacao: rrRound(e.gratificacao),
      comissoes: rrRound(e.comissoes),
      outras_verbas: rrRound(e.outras_verbas),
    };
    verbasAuto.terco_constitucional = rrRound(verbasAuto.ferias_proporcionais / 3);
    if (verbasAuto.ferias_vencidas > 0) verbasAuto.terco_constitucional += rrRound(verbasAuto.ferias_vencidas / 3);

    const verbasManual = {};
    if (e._manual_decimo) verbasManual.decimo_terceiro_proporcional = e.decimo_terceiro_proporcional;
    if (e._manual_ferias_prop) {
      verbasManual.ferias_proporcionais = e.ferias_proporcionais;
      verbasManual.terco_constitucional = rrRound(e.ferias_proporcionais / 3)
        + (verbasAuto.ferias_vencidas > 0 ? rrRound(verbasAuto.ferias_vencidas / 3) : 0);
    }
    if (e._manual_decimo_aviso) verbasManual.decimo_terceiro_aviso_previo = e.decimo_aviso_previo;
    if (e._manual_ferias_aviso) verbasManual.ferias_aviso_previo = e.ferias_aviso_previo;

    const verbasFinais = { ...verbasAuto, ...verbasManual };
    const horasExtras = verbasFinais.horas_extras_50 + verbasFinais.horas_extras_60;
    const totalBruto = rrRound(
      verbasFinais.saldo_salario + horasExtras + verbasFinais.reflexo_dsr
      + verbasFinais.decimo_terceiro_proporcional + verbasFinais.ferias_proporcionais + verbasFinais.terco_constitucional
      + verbasFinais.aviso_previo_indenizado + verbasFinais.decimo_terceiro_aviso_previo + verbasFinais.ferias_aviso_previo
      + verbasFinais.ferias_vencidas + verbasFinais.gratificacao + verbasFinais.comissoes + verbasFinais.outras_verbas
    );

    const baseInssSal = Math.max(0, verbasFinais.saldo_salario + horasExtras + verbasFinais.reflexo_dsr
      + verbasFinais.aviso_previo_indenizado + verbasFinais.ferias_vencidas + verbasFinais.ferias_proporcionais + verbasFinais.terco_constitucional);
    const baseInss13 = verbasFinais.decimo_terceiro_proporcional + verbasFinais.decimo_terceiro_aviso_previo;
    const inssAutoSal = rrEstimarInss(baseInssSal);
    const inssAuto13 = rrEstimarInss(baseInss13);

    const descontosAuto = {
      adiantamento_salarial: rrRound(e.adiantamentos),
      aviso_previo_descontado: rrRound(e.aviso_previo_descontado),
      inss_salario: inssAutoSal,
      inss_13: inssAuto13,
      irrf: rrEstimarIrrf(Math.max(0, totalBruto - inssAutoSal - inssAuto13)),
      irrf_13: 0,
      faltas: rrRound(e.faltas),
      dsr_faltas: rrRound(e.dsr_faltas),
      outros_descontos: rrRound(e.outros_descontos + e.vale_transporte + e.vale_alimentacao + e.adiantamento_13),
    };

    const descontosManual = {};
    if (e._manual_inss_salario) descontosManual.inss_salario = e.inss_salario;
    if (e._manual_inss_13) descontosManual.inss_13 = e.inss_13;
    if (e._manual_irrf) descontosManual.irrf = e.irrf ?? 0;
    if (e._manual_irrf_13) descontosManual.irrf_13 = e.irrf_13;

    const descontosFinais = { ...descontosAuto, ...descontosManual };
    const totalDescontos = rrRound(Object.values(descontosFinais).reduce((s, v) => s + (Number(v) || 0), 0));
    const totalLiquido = rrRound(Math.max(0, totalBruto - totalDescontos));

    const calc = {
      ok: true,
      aviso: rrState.catalogos?.aviso_legal || "",
      alertas: val.alertas,
      erros: [],
      verbas_automaticas: verbasAuto,
      verbas_manuais: verbasManual,
      verbas_finais: verbasFinais,
      descontos_automaticos: descontosAuto,
      descontos_manuais: descontosManual,
      descontos_finais: descontosFinais,
      saldo_salario: verbasFinais.saldo_salario,
      aviso_previo_indenizado: verbasFinais.aviso_previo_indenizado,
      decimo_terceiro_proporcional: verbasFinais.decimo_terceiro_proporcional,
      decimo_aviso_previo: verbasFinais.decimo_terceiro_aviso_previo,
      ferias_aviso_previo: verbasFinais.ferias_aviso_previo,
      ferias_proporcionais: verbasFinais.ferias_proporcionais,
      terco_ferias: verbasFinais.terco_constitucional,
      horas_extras: horasExtras,
      total_bruto: totalBruto,
      total_descontos: totalDescontos,
      total_liquido: totalLiquido,
      entrada: e,
      avos_13: e.avos_13,
      avos_ferias: e.avos_ferias,
    };
    calc.rubricas_trct = rrMontarRubricasTrct(calc);
    calc.descontos_trct = rrMontarDescontosTrct(calc);
    return calc;
  }

  function rrMontarRubricasTrct(calc) {
    const e = calc.entrada || {};
    const v = calc.verbas_finais || calc;
    const dias = e.dias_trabalhados_mes || 0;
    const faltasD = e.faltas_dias || 0;
    const m13 = calc.avos_13 || 0;
    const mFer = calc.avos_ferias || 0;
    const rub = [];
    const add = (cod, desc, val) => { if (Math.abs(val) >= 0.005) rub.push({ codigo: cod, descricao: desc, valor: rrRound(val) }); };
    add("50", `Saldo de ${dias} /dias Salário (líquido de ${faltasD} /faltas e DSR)`, v.saldo_salario || 0);
    add("56.1", "Horas Extras 50%", v.horas_extras_50 || 0);
    add("56.2", "Horas Extras a 60%", v.horas_extras_60 || 0);
    add("59", "Reflexo do DSR sobre salário variável", v.reflexo_dsr || 0);
    add("63", `13º Salário proporcional ${m13}/12 avos`, v.decimo_terceiro_proporcional || 0);
    add("65", `Férias proporc. ${mFer}/12 avos`, v.ferias_proporcionais || 0);
    add("68", "Terço constituc. de férias", v.terco_constitucional || calc.terco_ferias || 0);
    add("69", "Aviso prévio indenizado", v.aviso_previo_indenizado || 0);
    add("70", "13º Salário (aviso prévio indenizado)", v.decimo_terceiro_aviso_previo || 0);
    add("71", "Férias (aviso prévio indenizado)", v.ferias_aviso_previo || 0);
    add("95", "Outras verbas", v.outras_verbas || 0);
    return rub;
  }

  function rrMontarDescontosTrct(calc) {
    const d = calc.descontos_finais || calc;
    const desc = [];
    const add = (cod, lbl, val) => { if (Math.abs(val) >= 0.005) desc.push({ codigo: cod, descricao: lbl, valor: rrRound(val) }); };
    add("101", "Adiantamento salarial", d.adiantamento_salarial || 0);
    add("103", "Aviso prévio descontado", d.aviso_previo_descontado || 0);
    add("112.1", "Previdência social", d.inss_salario || 0);
    add("112.2", "Prev. social — 13º salário", d.inss_13 || 0);
    add("114.1", "IRRF", d.irrf || 0);
    add("114.2", "IRRF sobre 13º salário", d.irrf_13 || 0);
    add("115.1", "Faltas não justificadas", d.faltas || 0);
    add("115.2", "D.S.R. s/ faltas", d.dsr_faltas || 0);
    add("99", "Outros descontos", d.outros_descontos || 0);
    return desc;
  }

  function rrRenderBlocoValores(titulo, obj, labels, cssClass) {
    const linhas = Object.entries(labels)
      .filter(([k]) => obj && Math.abs(obj[k] || 0) >= 0.005)
      .map(([k, lbl]) => `<div class="rr-resultado-linha"><span>${rrEsc(lbl)}</span><strong>${rrMoeda(obj[k])}</strong></div>`)
      .join("");
    if (!linhas) return "";
    return `<div class="rr-resultado-box ${cssClass || ""}"><h4>${rrEsc(titulo)}</h4>${linhas}</div>`;
  }

  function rrTabelaTrct(itens, titulo) {
    if (!itens?.length) return "";
    const rows = [];
    for (let i = 0; i < itens.length; i += 3) {
      rows.push(`<tr>${[0, 1, 2].map((j) => {
        const r = itens[i + j];
        return r ? `<td><span class="rr-rub-cod">${rrEsc(r.codigo)}</span> ${rrEsc(r.descricao)}<br/><strong>${rrMoeda(r.valor)}</strong></td>` : "<td></td>";
      }).join("")}</tr>`);
    }
    return `<div class="rr-trct-tabela"><h5>${rrEsc(titulo)}</h5><table><tbody>${rows.join("")}</tbody></table></div>`;
  }

  function rrRenderResultado(containerId, calc) {
    const el = document.getElementById(containerId);
    if (!el || !calc) return;
    if (!calc.ok) {
      el.innerHTML = rrAvisoHtml()
        + `<div class="rr-erros-validacao">${(calc.erros || []).map((e) => `<p>${rrEsc(e)}</p>`).join("")}</div>`;
      return;
    }
    const rub = calc.rubricas_trct || [];
    const desc = calc.descontos_trct || [];
    const alertasHtml = (calc.alertas || []).length
      ? `<div class="rr-alerta-salario">${calc.alertas.map((a) => rrEsc(a)).join("<br/>")}</div>` : "";
    el.innerHTML = rrAvisoHtml() + alertasHtml
      + `<div class="rr-previa-grid">`
      + rrRenderBlocoValores("Verbas finais (entram no bruto)", calc.verbas_finais, RR_LABELS_VERBAS, "rr-bloco-final")
      + rrRenderBlocoValores("Deduções aplicadas (finais)", calc.descontos_finais, RR_LABELS_DESC, "rr-bloco-final")
      + `</div>`
      + `<div class="rr-resultado-box"><h4>TRCT — Verbas rescisórias</h4>`
      + rrTabelaTrct(rub, "VERBAS RESCISÓRIAS")
      + `<div class="rr-resultado-linha rr-total"><span>Total bruto</span><strong>${rrMoeda(calc.total_bruto)}</strong></div>`
      + `</div>`
      + `<div class="rr-resultado-box"><h4>Deduções</h4>`
      + rrTabelaTrct(desc, "DEDUÇÕES")
      + `<div class="rr-resultado-linha rr-total"><span>Total deduções</span><strong>${rrMoeda(calc.total_descontos)}</strong></div>`
      + `</div>`
      + `<div class="rr-resultado-box rr-liquido-final"><div class="rr-resultado-linha"><span>Valor líquido</span><strong>${rrMoeda(calc.total_liquido)}</strong></div>`
      + (calc.custo_empresa != null ? `<div class="rr-resultado-linha subtle-text"><span>Custo empresa (estim.)</span><strong>${rrMoeda(calc.custo_empresa)}</strong></div>` : "")
      + `</div>`;
    rrState.ultimoCalculo = calc;
    const prefix = containerId === "rrCalResultado" ? "rrCal" : "rrSim";
    document.getElementById(prefix + "BtnPdfTrct")?.removeAttribute("disabled");
    document.getElementById(prefix + "BtnPdfVia")?.removeAttribute("disabled");
  }

  function rrCorpoExemploAline() {
    return {
      salario_base: 1706.5,
      salario_cadastro: 1706.5,
      data_admissao: "2026-01-09",
      data_demissao: "2026-05-25",
      data_aviso_previo: "2026-05-25",
      tipo_contrato: "prazo_indeterminado",
      tipo_rescisao: "dispensa_sem_justa_causa",
      aviso_previo_tipo: "indenizado",
      dias_trabalhados_mes: 25,
      avos_13: 5,
      avos_ferias: 5,
      horas_extras_50: 0,
      horas_extras_60: 147.38,
      reflexo_dsr: 36.85,
      ferias_vencidas: 0,
      gratificacao: 0,
      comissoes: 0,
      outras_verbas: 0,
      decimo_terceiro_proporcional: 797.6,
      ferias_proporcionais: 797.6,
      decimo_aviso_previo: 159.51,
      ferias_aviso_previo: 159.51,
      adiantamentos: 177.96,
      aviso_previo_descontado: 0,
      faltas: 110.92,
      dsr_faltas: 110.92,
      inss_salario: 103.83,
      inss_13: 61.11,
      irrf: 0,
      irrf_13: 0,
      outros_descontos: 0,
      vale_transporte: 0,
      vale_alimentacao: 0,
      adiantamento_13: 0,
      _manual_decimo: true,
      _manual_ferias_prop: true,
      _manual_decimo_aviso: true,
      _manual_ferias_aviso: true,
      _manual_inss_salario: true,
      _manual_inss_13: true,
      _manual_irrf: true,
      _manual_irrf_13: false,
    };
  }

  /** Exemplo TRCT — ALINE FREIRE DA SILVA (caso de validação). */
  function rrPreencherExemploAline(prefix) {
    clearTimeout(rrState.calcTimer);
    const set = (id, val) => {
      const el = document.getElementById(prefix + id);
      if (el && val != null && val !== "") el.value = val;
    };
    const zeroMoeda = (id) => {
      const el = document.getElementById(prefix + id);
      if (!el) return;
      el.dataset.value = "0";
      el.dataset.rrManual = "";
      el.value = "";
    };
    const hid = document.getElementById(prefix + "SalarioCadastro");
    if (hid) hid.value = "1706.50";

    ["He50", "FeriasVenc", "Gratificacao", "Comissoes", "OutrasVerbas", "AvisoDescontado",
      "Irrf13", "OutrosDescontos", "Adiant13", "ValeTransp", "ValeAlim", "FgtsMensal"].forEach(zeroMoeda);

    set("Cargo", "Atendente");
    set("Admissao", "2026-01-09");
    set("Demissao", "2026-05-25");
    set("DataAviso", "2026-05-25");
    set("TipoContrato", "prazo_indeterminado");
    set("TipoRescisao", "dispensa_sem_justa_causa");
    set("AvisoPrevio", "indenizado");
    set("DiasMes", "25");
    set("Avos13", "5");
    set("AvosFerias", "5");
    set("Obs", "Exemplo TRCT — ALINE FREIRE DA SILVA (validação contador).");

    rrSetMoeda(prefix + "Salario", 1706.5);
    rrSetMoeda(prefix + "RemunMesAnt", 1706.5);
    rrSetMoeda(prefix + "He60", 147.38);
    rrSetMoeda(prefix + "ReflexoDsr", 36.85);
    rrSetMoeda(prefix + "Decimo", 797.6);
    rrSetMoeda(prefix + "FeriasProp", 797.6);
    rrSetMoeda(prefix + "DecimoAviso", 159.51);
    rrSetMoeda(prefix + "FeriasAviso", 159.51);
    rrSetMoeda(prefix + "Adiantamentos", 177.96);
    rrSetMoeda(prefix + "Faltas", 110.92);
    rrSetMoeda(prefix + "DsrFaltas", 110.92);
    rrSetMoeda(prefix + "InssSalario", 103.83);
    rrSetMoeda(prefix + "Inss13", 61.11);
    rrSetMoeda(prefix + "Irrf", 0);

    const apEl = document.getElementById(prefix + "AvisoPrevio");
    if (apEl) apEl.value = "indenizado";

    const calc = rrCalcularLocal(rrLerFormulario(prefix));
    rrState.ultimoCalculo = calc;
    rrRenderResultado(rrResultadoId(prefix), calc);
    const liq = calc.total_liquido ?? 0;
    const desc = calc.total_descontos ?? 0;
    const bru = calc.total_bruto ?? 0;
    rrToast(`Exemplo ALINE — Bruto ${rrMoeda(bru)} · Deduções ${rrMoeda(desc)} · Líquido ${rrMoeda(liq)}`, liq >= 4928 ? "success" : "error");
  }

  function rrSugerirAvosCampos(prefix) {
    const adm = document.getElementById(prefix + "Admissao")?.value;
    const dem = document.getElementById(prefix + "Demissao")?.value;
    const av13 = document.getElementById(prefix + "Avos13");
    const avFer = document.getElementById(prefix + "AvosFerias");
    if (av13 && dem && (av13.value === "" || av13.value === "0")) av13.value = String(rrSugerirAvos13(dem));
    if (avFer && adm && dem && (avFer.value === "" || avFer.value === "0")) avFer.value = String(rrSugerirAvosFerias(adm, dem));
  }

  async function rrPreencherDadosTrct(prefix, funcionarioId, unidadeId) {
    const set = (id, val) => { const el = document.getElementById(prefix + id); if (el && val != null && val !== "") el.value = val; };
    if (funcionarioId) {
      const f = await rrFetch(`/funcionarios/${funcionarioId}`).catch(() => null);
      if (f) {
        set("Cargo", f.cargo);
        set("Admissao", f.data_admissao || "");
        set("Ctps", f.ctps || "");
        if (f.data_nascimento) set("Nascimento", f.data_nascimento);
        const salCad = Number(f.salario_base || f.salario || f.remuneracao || 0) || 0;
        const hid = document.getElementById(prefix + "SalarioCadastro");
        if (hid) hid.value = String(salCad);
        if (salCad > 0) {
          const salEl = document.getElementById(prefix + "Salario");
          if (salEl && !rrLerMoeda(salEl)) rrSetMoeda(prefix + "Salario", salCad);
        }
      }
    }
    if (unidadeId) {
      const u = await rrFetch(`/unidades/${unidadeId}`).catch(() => null);
      if (u) {
        set("EmpresaCnpj", u.cnpj || "");
        set("EmpresaRazao", u.nome || "");
      }
    }
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
    rrState.calcTimer = setTimeout(() => rrExecutarCalculoLive(), 400);
  }

  function rrExecutarCalculoLive() {
    const prefix = rrFormPrefixAtivo();
    if (!prefix) return;
    const body = rrLerFormulario(prefix);
    if (!body.salario_base && !body.funcionario_id) return;
    const calc = rrCalcularLocal(body);
    rrState.ultimoCalculo = calc;
    rrRenderResultado(rrResultadoId(prefix), calc);
    return calc;
  }

  async function rrAbrirPdfRescisao(id, via) {
    if (!id || !currentUser) {
      rrToast("Sessão inválida. Faça login novamente.", "error");
      return;
    }
    const qs = via ? `?via=${encodeURIComponent(via)}` : "";
    const headers = {
      ...(typeof getDeviceHeaders === "function" ? getDeviceHeaders() : {}),
      ...(currentUser.token ? { Authorization: `Bearer ${currentUser.token}` } : {}),
      ...(currentUser.id != null ? { "X-Usuario-Id": String(currentUser.id) } : {}),
    };
    const res = await fetch(`${API_URL}/rh/rescisoes/${id}/pdf${qs}`, { method: "GET", headers, cache: "no-store" });
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
    if (tc) tc.innerHTML = rrOpts(cat.tipos_contrato, tc.value || "prazo_indeterminado");
    if (tr) tr.innerHTML = rrOpts(cat.tipos_rescisao, tr.value || "dispensa_sem_justa_causa");
    if (ap) ap.innerHTML = rrOpts(cat.aviso_previo, ap.value || "indenizado");
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
    if (tc) tc.innerHTML = rrOpts(cat.tipos_contrato, tc.value || "prazo_indeterminado");
    if (tr) tr.innerHTML = rrOpts(cat.tipos_rescisao, tr.value || "dispensa_sem_justa_causa");
    if (ap) ap.innerHTML = rrOpts(cat.aviso_previo, ap.value || "indenizado");
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
      tb.innerHTML = '<tr><td colspan="9" style="text-align:center;color:#607d8b">Nenhum registro.</td></tr>';
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
        <button type="button" class="table-action rr-btn-pdf" data-id="${r.id}" data-via="completo">TRCT</button>
        <button type="button" class="table-action rr-btn-pdf-via" data-id="${r.id}" data-via="funcionario">Via func.</button>
        ${r.status === "simulacao" ? `<button type="button" class="table-action rr-btn-confirm" data-id="${r.id}">Confirmar</button>` : ""}
      </td>
    </tr>`).join("");
  }

  async function loadRhRescisaoRelatorios() {
    await rrPreencherUnidades("rrRelUnidade", "Todas");
    document.getElementById("rrRelResumo").innerHTML = rrAvisoHtml();
  }

  function rrBodyParaApi(body) {
    const b = { ...body };
    ["_manual_decimo", "_manual_ferias_prop", "_manual_decimo_aviso", "_manual_ferias_aviso",
      "_manual_inss_salario", "_manual_inss_13", "_manual_irrf", "_manual_irrf_13"].forEach((k) => delete b[k]);
    if (!body._manual_inss_salario) delete b.inss_salario;
    if (!body._manual_inss_13) delete b.inss_13;
    if (!body._manual_irrf) delete b.irrf;
    if (!body._manual_irrf_13) delete b.irrf_13;
    if (!body._manual_decimo) delete b.decimo_terceiro_proporcional;
    if (!body._manual_ferias_prop) delete b.ferias_proporcionais;
    if (!body._manual_decimo_aviso) delete b.decimo_aviso_previo;
    if (!body._manual_ferias_aviso) delete b.ferias_aviso_previo;
    return b;
  }

  async function rrSalvarSimulacao(status, prefix) {
    const p = prefix || rrFormPrefixAtivo() || "rrSim";
    const body = rrLerFormulario(p);
    const local = rrCalcularLocal(body);
    if (!local.ok) {
      rrRenderResultado(rrResultadoId(p), local);
      throw new Error((local.erros || []).join(" ") || "Preencha os campos obrigatórios.");
    }
    body.status = status || "simulacao";
    const payload = rrBodyParaApi(body);
    payload.status = body.status;
    payload.salvar_cenarios = true;
    const salvo = await rrFetch("/rh/rescisoes", { method: "POST", body: JSON.stringify(payload) });
    rrState.ultimaRescisaoId = salvo?.id;
    rrState.ultimoCalculo = local;
    rrToast(status === "confirmada" ? "Rescisão confirmada." : "Simulação salva.", "success");
    return salvo;
  }

  async function rrGerarPdfTrct(prefix, via) {
    let id = rrState.ultimaRescisaoId;
    if (!id) {
      const salvo = await rrSalvarSimulacao("simulacao", prefix);
      id = salvo?.id;
    }
    if (!id) {
      rrToast("Salve a simulação antes de gerar o PDF.", "error");
      return;
    }
    await rrAbrirPdfRescisao(id, via || "completo");
  }

  let rrBound = false;
  function rrBindOnce() {
    if (rrBound) return;
    rrBound = true;

    function rrBindFuncionarioChange(prefix) {
      const unidadeId = prefix + "Unidade";
      const funcId = prefix + "Funcionario";
      document.getElementById(unidadeId)?.addEventListener("change", async (e) => {
        await rrPreencherFuncionarios(funcId, e.target.value);
        await rrPreencherDadosTrct(prefix, null, e.target.value);
        rrAgendarCalculo();
      });
      document.getElementById(funcId)?.addEventListener("change", async (e) => {
        const opt = e.target.selectedOptions?.[0];
        const uni = document.getElementById(unidadeId)?.value;
        if (opt) {
          const cargo = document.getElementById(prefix + "Cargo");
          const adm = document.getElementById(prefix + "Admissao");
          if (cargo && opt.dataset.cargo) cargo.value = opt.dataset.cargo;
          if (adm && opt.dataset.adm) adm.value = opt.dataset.adm;
        }
        await rrPreencherDadosTrct(prefix, e.target.value, uni);
        rrAgendarCalculo();
      });
      document.getElementById(prefix + "Demissao")?.addEventListener("change", (e) => {
        const av = document.getElementById(prefix + "DataAviso");
        if (av && !av.value) av.value = e.target.value;
        rrSugerirAvosCampos(prefix);
        rrAgendarCalculo();
      });
      document.getElementById(prefix + "Admissao")?.addEventListener("change", () => {
        rrSugerirAvosCampos(prefix);
        rrAgendarCalculo();
      });
    }
    rrBindFuncionarioChange("rrSim");
    rrBindFuncionarioChange("rrCal");

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

    document.getElementById("rrSimBtnExemplo")?.addEventListener("click", () => rrPreencherExemploAline("rrSim"));
    document.getElementById("rrCalBtnExemplo")?.addEventListener("click", () => rrPreencherExemploAline("rrCal"));

    document.getElementById("rrSimBtnSalvar")?.addEventListener("click", () => rrSalvarSimulacao("simulacao", "rrSim").catch((e) => rrToast(e.message, "error")));
    document.getElementById("rrSimBtnConfirmar")?.addEventListener("click", () => rrSalvarSimulacao("confirmada", "rrSim").catch((e) => rrToast(e.message, "error")));
    document.getElementById("rrCalBtnSalvar")?.addEventListener("click", () => rrSalvarSimulacao("simulacao", "rrCal").catch((e) => rrToast(e.message, "error")));
    document.getElementById("rrCalBtnConfirmar")?.addEventListener("click", () => rrSalvarSimulacao("confirmada", "rrCal").catch((e) => rrToast(e.message, "error")));
    document.getElementById("rrSimBtnPdfTrct")?.addEventListener("click", () => rrGerarPdfTrct("rrSim", "completo").catch((e) => rrToast(e.message, "error")));
    document.getElementById("rrSimBtnPdfVia")?.addEventListener("click", () => rrGerarPdfTrct("rrSim", "funcionario").catch((e) => rrToast(e.message, "error")));
    document.getElementById("rrCalBtnPdfTrct")?.addEventListener("click", () => rrGerarPdfTrct("rrCal", "completo").catch((e) => rrToast(e.message, "error")));
    document.getElementById("rrCalBtnPdfVia")?.addEventListener("click", () => rrGerarPdfTrct("rrCal", "funcionario").catch((e) => rrToast(e.message, "error")));

    document.getElementById("rrCompBtnComparar")?.addEventListener("click", () => rrExecutarComparativo().catch((e) => rrToast(e.message, "error")));
    document.getElementById("rrHistFiltrar")?.addEventListener("click", () => loadRhRescisaoHistorico().catch((e) => rrToast(e.message, "error")));

    document.getElementById("rhRescisaoHistoricoSection")?.addEventListener("click", async (e) => {
      const pdf = e.target.closest(".rr-btn-pdf, .rr-btn-pdf-via");
      const conf = e.target.closest(".rr-btn-confirm");
      if (pdf) rrAbrirPdfRescisao(pdf.dataset.id, pdf.dataset.via || "completo").catch((e) => rrToast(e.message, "error"));
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
