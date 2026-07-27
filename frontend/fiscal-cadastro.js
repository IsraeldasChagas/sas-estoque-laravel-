/**
 * Módulo 1 — Cadastro Fiscal (empresas, perfis tributários, produto/unidade).
 */
(function () {
  "use strict";

  let fiscalMeta = null;
  let fiscalEmpresas = [];
  let fiscalPerfis = [];
  let fiscalPodeEditar = false;

  function toast(msg, type) {
    const fn = typeof showToast === "function" ? showToast : window.showToast;
    if (typeof fn === "function") fn(msg, type || "info");
  }

  async function fFetch(path, opts) {
    if (typeof window.fetchJSON === "function") return window.fetchJSON(path, opts);
    throw new Error("fetchJSON indisponível");
  }

  function esc(s) {
    if (typeof escapeHtml === "function") return escapeHtml(s);
    const d = document.createElement("div");
    d.textContent = s == null ? "" : String(s);
    return d.innerHTML;
  }

  function isAdmin() {
    const p = (window.currentUser?.perfil || "").toString().trim().toUpperCase();
    return p === "ADMIN" || p === "ADMINISTRADOR";
  }

  function isAdminOrGerente() {
    const p = (window.currentUser?.perfil || "").toString().trim().toUpperCase();
    return p === "ADMIN" || p === "ADMINISTRADOR" || p === "GERENTE";
  }

  async function ensureMeta() {
    if (fiscalMeta) return fiscalMeta;
    fiscalMeta = await fFetch("/fiscal/meta");
    return fiscalMeta;
  }

  async function loadEmpresasList() {
    if (!isAdminOrGerente()) return [];
    fiscalEmpresas = await fFetch("/fiscal/empresas");
    return fiscalEmpresas;
  }

  async function loadPerfisList(ativosOnly) {
    if (!isAdminOrGerente()) return [];
    const q = ativosOnly ? "?ativos=1" : "";
    fiscalPerfis = await fFetch(`/fiscal/perfis-tributarios${q}`);
    return fiscalPerfis;
  }

  function labelRegime(v) {
    const map = {
      simples_nacional: "Simples Nacional",
      lucro_presumido: "Lucro Presumido",
      lucro_real: "Lucro Real",
      outro: "Outro",
    };
    return map[v] || v || "—";
  }

  function labelTipo(v) {
    const map = {
      producao_propria: "Produção própria",
      revenda: "Revenda",
      insumo: "Insumo",
      uso_consumo: "Uso e consumo",
    };
    return map[v] || "—";
  }

  function badgeStatus(status) {
    const s = (status || "pendente").toLowerCase();
    if (s === "completo") return '<span class="fiscal-badge fiscal-badge--ok" title="Fiscal completo">Completo</span>';
    if (s === "incompleto") return '<span class="fiscal-badge fiscal-badge--warn" title="Fiscal incompleto">Incompleto</span>';
    return '<span class="fiscal-badge fiscal-badge--pend" title="Fiscal pendente">Pendente</span>';
  }

  function fmtCnpj(digits) {
    const d = String(digits || "").replace(/\D/g, "");
    if (d.length !== 14) return digits || "—";
    return d.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, "$1.$2.$3/$4-$5");
  }

  function maskNcmInput(el) {
    if (!el) return;
    el.addEventListener("input", () => {
      const d = el.value.replace(/\D/g, "").slice(0, 8);
      if (d.length <= 2) el.value = d;
      else if (d.length <= 4) el.value = `${d.slice(0, 2)}.${d.slice(2)}`;
      else if (d.length <= 6) el.value = `${d.slice(0, 2)}.${d.slice(2, 4)}.${d.slice(4)}`;
      else el.value = `${d.slice(0, 2)}.${d.slice(2, 4)}.${d.slice(4, 6)}-${d.slice(6)}`;
    });
  }

  function boolSelectValue(el) {
    if (!el) return null;
    const v = String(el.value ?? "").trim();
    if (v === "") return null;
    return v === "1";
  }

  function setBoolSelect(el, val) {
    if (!el) return;
    if (val === true || val === 1 || val === "1") el.value = "1";
    else if (val === false || val === 0 || val === "0") el.value = "0";
    else el.value = "";
  }

  const EMPRESA_REGIMES = [
    {
      value: "simples_nacional",
      title: "Simples Nacional",
      desc: "ME/EPP optantes pelo Simples. Indicado para CRT 1 na NF-e.",
    },
    {
      value: "lucro_presumido",
      title: "Lucro Presumido",
      desc: "Tributação com base em percentuais sobre a receita. CRT 3 (regime normal).",
    },
    {
      value: "lucro_real",
      title: "Lucro Real",
      desc: "IRPJ e CSLL sobre o lucro contábil. CRT 3 (regime normal).",
    },
    {
      value: "outro",
      title: "Outro / a definir",
      desc: "Quando ainda não houver definição ou situação especial.",
    },
  ];

  function empresaRegimeCardsMarkup() {
    return `<div class="fiscal-regime-cards" role="radiogroup" aria-label="Regime tributário da empresa">
      ${EMPRESA_REGIMES.map(
        (r) => `<button type="button" class="fiscal-regime-card" data-regime="${r.value}" role="radio" aria-checked="false">
          <span class="fiscal-regime-card__title">${esc(r.title)}</span>
          <span class="fiscal-regime-card__desc">${esc(r.desc)}</span>
        </button>`
      ).join("")}
    </div>
    <input type="hidden" name="regime_tributario" value="" />
    <p class="fiscal-regime-resumo hidden" id="fiscalEmpresaRegimeResumo" aria-live="polite"></p>`;
  }

  function bindEmpresaFormUi(root) {
    const form = root.querySelector("#fiscalEmpresaForm");
    if (!form || form.dataset.fiscalEmpresaBound) return;
    form.dataset.fiscalEmpresaBound = "1";

    const crtSel = form.elements.crt;
    const btnContinuar = form.querySelector("#fiscalEmpresaBtnContinuar");
    const btnVoltar = form.querySelector("#fiscalEmpresaBtnVoltar");
    const btnSalvar = form.querySelector("#fiscalEmpresaBtnSalvar");

    form.querySelector(".fiscal-regime-cards")?.addEventListener("click", (ev) => {
      const card = ev.target.closest(".fiscal-regime-card[data-regime]");
      if (!card) return;
      setEmpresaRegimeValue(form, card.dataset.regime, true);
    });

    btnContinuar?.addEventListener("click", () => {
      if (!form.elements.regime_tributario?.value) {
        toast("Escolha um regime tributário na lista para continuar.", "warning");
        form.querySelector(".fiscal-regime-cards")?.scrollIntoView({ behavior: "smooth", block: "center" });
        return;
      }
      setEmpresaWizardStep(form, 2);
      form.elements.razao_social?.focus();
    });

    btnVoltar?.addEventListener("click", () => setEmpresaWizardStep(form, 1));

    crtSel?.addEventListener("change", () => {
      form.dataset.crtManual = crtSel.value ? "1" : "";
    });

    form.querySelector("#fiscalEmpresaAlterarRegime")?.addEventListener("click", () => {
      if (form.dataset.empresaMode === "create") setEmpresaWizardStep(form, 1);
    });
  }

  function setEmpresaRegimeValue(form, value, suggestCrt) {
    if (!form) return;
    const hidden = form.elements.regime_tributario;
    if (hidden) hidden.value = value || "";
    form.querySelectorAll(".fiscal-regime-card").forEach((el) => {
      const on = el.dataset.regime === value;
      el.classList.toggle("fiscal-regime-card--selected", on);
      el.setAttribute("aria-checked", on ? "true" : "false");
    });
    const resumo = form.querySelector("#fiscalEmpresaRegimeResumo");
    const r = EMPRESA_REGIMES.find((x) => x.value === value);
    if (resumo) {
      if (r) {
        resumo.textContent = `Regime selecionado: ${r.title}`;
        resumo.classList.remove("hidden");
      } else {
        resumo.textContent = "";
        resumo.classList.add("hidden");
      }
    }
    const crtSel = form.elements.crt;
    if (suggestCrt && crtSel && form.dataset.crtManual !== "1") {
      suggestEmpresaCrtFromRegimeValue(value, crtSel, true);
    }
    updateEmpresaStepIndicator(form);
    refreshEmpresaRegimeChip(form);
  }

  function suggestEmpresaCrtFromRegimeValue(regime, crtSel, force) {
    if (!crtSel || !regime) return;
    if (!force && crtSel.value !== "" && crtSel.closest("form")?.dataset.crtManual === "1") return;
    if (regime === "simples_nacional") crtSel.value = "1";
    else if (regime === "lucro_presumido" || regime === "lucro_real") crtSel.value = "3";
    else crtSel.value = "";
  }

  function setEmpresaWizardStep(form, step) {
    if (!form) return;
    form.dataset.empresaStep = String(step);
    const isEdit = form.dataset.empresaMode === "edit";
    form.querySelectorAll("[data-empresa-step]").forEach((el) => {
      const n = Number(el.dataset.empresaStep);
      if (isEdit) {
        el.classList.remove("hidden");
        return;
      }
      el.classList.toggle("hidden", n !== step);
    });
    const btnContinuar = form.querySelector("#fiscalEmpresaBtnContinuar");
    const btnVoltar = form.querySelector("#fiscalEmpresaBtnVoltar");
    const btnSalvar = form.querySelector("#fiscalEmpresaBtnSalvar");
    if (isEdit) {
      btnContinuar?.classList.add("hidden");
      btnVoltar?.classList.add("hidden");
      btnSalvar?.classList.remove("hidden");
    } else {
      btnContinuar?.classList.toggle("hidden", step !== 1);
      btnVoltar?.classList.toggle("hidden", step !== 2);
      btnSalvar?.classList.toggle("hidden", step !== 2);
    }
    updateEmpresaStepIndicator(form);
    refreshEmpresaRegimeChip(form);
  }

  function updateEmpresaStepIndicator(form) {
    if (!form || form.dataset.empresaMode === "edit") return;
    const step = Number(form.dataset.empresaStep || 1);
    form.querySelectorAll("[data-step-dot]").forEach((dot) => {
      const n = Number(dot.dataset.stepDot);
      dot.classList.toggle("fiscal-empresa-step-dot--active", n === step);
      dot.classList.toggle("fiscal-empresa-step-dot--done", n < step);
    });
    const label = form.querySelector("#fiscalEmpresaStepLabel");
    if (label) {
      label.textContent = step === 1 ? "Passo 1 de 2 — Regime tributário" : "Passo 2 de 2 — Dados da empresa";
    }
  }

  // —— Empresas ——
  async function renderEmpresasPage() {
    const root = document.getElementById("fiscalEmpresasRoot");
    if (!root) return;
    fiscalPodeEditar = isAdmin();
    await loadEmpresasList();
    const rows = (fiscalEmpresas || [])
      .map(
        (e) => `<tr data-id="${e.id}">
        <td>${esc(e.razao_social)}</td>
        <td>${esc(e.nome_fantasia || "—")}</td>
        <td>${esc(fmtCnpj(e.cnpj))}</td>
        <td>${esc(labelRegime(e.regime_tributario))}</td>
        <td>${e.ativo ? "Ativa" : "Inativa"}</td>
        <td class="table-actions">${fiscalPodeEditar ? `<button type="button" class="table-action" data-acao="editar">Editar</button>` : ""}</td>
      </tr>`
      )
      .join("");
    root.innerHTML = `
      <div class="actions">${fiscalPodeEditar ? '<button type="button" class="btn primary" id="fiscalBtnNovaEmpresa">+ Nova empresa</button>' : ""}</div>
      <div class="table-card"><header>Empresas (CNPJ)</header>
      <div class="table-wrapper"><table><thead><tr>
        <th>Razão social</th><th>Nome fantasia</th><th>CNPJ</th><th>Regime tributário</th><th>Status</th><th>Ações</th>
      </tr></thead><tbody id="fiscalEmpresasTbody">${rows || '<tr><td colspan="6">Nenhuma empresa cadastrada.</td></tr>'}</tbody></table></div></div>
      <div class="modal-backdrop" id="fiscalEmpresaModal"><div class="modal modal--wide fiscal-empresa-modal"><header><h2 id="fiscalEmpresaModalTitle">Nova empresa</h2>
      <button type="button" class="close-btn" id="fiscalEmpresaModalClose">×</button></header>
      <form id="fiscalEmpresaForm" data-empresa-step="1" data-empresa-mode="create"><input type="hidden" name="id" />
        <div class="fiscal-empresa-wizard-head" id="fiscalEmpresaWizardHead">
          <p class="fiscal-empresa-step-label" id="fiscalEmpresaStepLabel">Passo 1 de 2 — Regime tributário</p>
          <div class="fiscal-empresa-step-dots" aria-hidden="true">
            <span class="fiscal-empresa-step-dot fiscal-empresa-step-dot--active" data-step-dot="1"></span>
            <span class="fiscal-empresa-step-dot" data-step-dot="2"></span>
          </div>
        </div>

        <section class="fiscal-empresa-step-panel" data-empresa-step="1">
          <h3 class="fiscal-empresa-step-title">Qual é o regime tributário desta empresa?</h3>
          <p class="fiscal-empresa-step-lead">Selecione uma opção na lista. É obrigatório para vincular unidades e uso fiscal futuro.</p>
          ${empresaRegimeCardsMarkup()}
          <div class="fiscal-form-block fiscal-form-block--tributacao fiscal-form-block--crt">
            <p class="fiscal-form-block__title">CRT na NF-e (opcional agora)</p>
            <label>Código CRT
              <select name="crt">
                <option value="">Sugerir automaticamente ao escolher o regime</option>
                <option value="1">1 — Simples Nacional</option>
                <option value="2">2 — Simples Nacional (excesso sublimite)</option>
                <option value="3">3 — Regime Normal (Lucro Presumido ou Real)</option>
              </select>
            </label>
          </div>
        </section>

        <section class="fiscal-empresa-step-panel hidden" data-empresa-step="2">
          <h3 class="fiscal-empresa-step-title">Dados da pessoa jurídica</h3>
          <p class="fiscal-empresa-step-lead">CNPJ, razão social e inscrições. O regime já foi definido no passo anterior.</p>
          <div class="fiscal-empresa-regime-chip-wrap hidden" id="fiscalEmpresaRegimeChipWrap">
            <span class="fiscal-empresa-regime-chip" id="fiscalEmpresaRegimeChip"></span>
            <button type="button" class="btn link-like" id="fiscalEmpresaAlterarRegime">Alterar regime</button>
          </div>
          <label>Razão social *<input name="razao_social" required maxlength="255" /></label>
          <label>Nome fantasia<input name="nome_fantasia" maxlength="255" /></label>
          <label>CNPJ<input name="cnpj" placeholder="00.000.000/0000-00" maxlength="20" /></label>
          <div class="fiscal-form-grid-2">
            <label>Inscrição estadual<input name="inscricao_estadual" maxlength="30" /></label>
            <label>Inscrição municipal<input name="inscricao_municipal" maxlength="30" /></label>
          </div>
          <div class="fiscal-form-grid-2">
            <label>UF<input name="uf" maxlength="2" placeholder="PA" /></label>
            <label>Município<input name="municipio" maxlength="120" /></label>
          </div>
          <label>Status<select name="ativo"><option value="1">Ativa</option><option value="0">Inativa</option></select></label>
        </section>

        <footer class="fiscal-empresa-form-footer">
          <button type="button" class="btn neutral" id="fiscalEmpresaCancelar">Cancelar</button>
          <button type="button" class="btn neutral hidden" id="fiscalEmpresaBtnVoltar">Voltar</button>
          ${fiscalPodeEditar ? '<button type="button" class="btn primary" id="fiscalEmpresaBtnContinuar">Continuar</button>' : ""}
          ${fiscalPodeEditar ? '<button type="submit" class="btn primary hidden" id="fiscalEmpresaBtnSalvar">Salvar empresa</button>' : ""}
        </footer>
      </form></div></div>`;

    root.querySelector("#fiscalEmpresasTbody")?.addEventListener("click", (ev) => {
      const btn = ev.target.closest('[data-acao="editar"]');
      if (!btn) return;
      const id = btn.closest("tr")?.dataset?.id;
      const emp = fiscalEmpresas.find((x) => String(x.id) === String(id));
      if (emp) openEmpresaModal(emp);
    });
    root.querySelector("#fiscalBtnNovaEmpresa")?.addEventListener("click", () => openEmpresaModal(null));
    root.querySelector("#fiscalEmpresaModalClose")?.addEventListener("click", closeEmpresaModal);
    root.querySelector("#fiscalEmpresaCancelar")?.addEventListener("click", closeEmpresaModal);
    root.querySelector("#fiscalEmpresaForm")?.addEventListener("submit", submitEmpresaForm);
    bindEmpresaFormUi(root);
  }

  function setEmpresaCrtSelect(form, crtValue) {
    const crtSel = form.elements.crt;
    if (!crtSel) return;
    const v = String(crtValue ?? "").trim();
    if (v === "1" || v === "2" || v === "3") crtSel.value = v;
    else crtSel.value = "";
  }

  function openEmpresaModal(emp) {
    const modal = document.getElementById("fiscalEmpresaModal");
    const form = document.getElementById("fiscalEmpresaForm");
    if (!modal || !form) return;
    const isEdit = !!emp;
    form.dataset.empresaMode = isEdit ? "edit" : "create";
    form.classList.toggle("fiscal-empresa-form--edit", isEdit);
    form.dataset.crtManual = "";
    document.getElementById("fiscalEmpresaModalTitle").textContent = isEdit ? "Editar empresa" : "Cadastrar empresa";
    document.getElementById("fiscalEmpresaWizardHead")?.classList.toggle("hidden", isEdit);
    form.reset();
    form.elements.id.value = emp?.id ?? "";
    setEmpresaRegimeValue(form, "", false);
    if (emp) {
      form.elements.razao_social.value = emp.razao_social || "";
      form.elements.nome_fantasia.value = emp.nome_fantasia || "";
      form.elements.cnpj.value = emp.cnpj ? fmtCnpj(emp.cnpj) : "";
      form.elements.inscricao_estadual.value = emp.inscricao_estadual || "";
      form.elements.inscricao_municipal.value = emp.inscricao_municipal || "";
      form.elements.uf.value = emp.uf || "";
      form.elements.municipio.value = emp.municipio || "";
      setEmpresaRegimeValue(form, emp.regime_tributario || "", false);
      if (emp.crt) form.dataset.crtManual = "1";
      setEmpresaCrtSelect(form, emp.crt);
      form.elements.ativo.value = emp.ativo ? "1" : "0";
      setEmpresaWizardStep(form, 2);
    } else {
      setEmpresaWizardStep(form, 1);
    }
    refreshEmpresaRegimeChip(form);
    modal.classList.add("active");
  }

  function refreshEmpresaRegimeChip(form) {
    const wrap = form?.querySelector("#fiscalEmpresaRegimeChipWrap");
    const chip = form?.querySelector("#fiscalEmpresaRegimeChip");
    if (!wrap || !chip) return;
    const v = form.elements.regime_tributario?.value;
    const show = form.dataset.empresaMode === "create" && form.dataset.empresaStep === "2" && v;
    wrap.classList.toggle("hidden", !show);
    if (show) chip.textContent = labelRegime(v);
  }

  function closeEmpresaModal() {
    document.getElementById("fiscalEmpresaModal")?.classList.remove("active");
  }

  async function submitEmpresaForm(e) {
    e.preventDefault();
    if (!fiscalPodeEditar) return;
    const form = e.target;
    const id = (form.elements.id.value || "").trim();
    const payload = {
      razao_social: form.elements.razao_social.value.trim(),
      nome_fantasia: form.elements.nome_fantasia.value.trim() || null,
      cnpj: form.elements.cnpj.value.trim() || null,
      inscricao_estadual: form.elements.inscricao_estadual.value.trim() || null,
      inscricao_municipal: form.elements.inscricao_municipal.value.trim() || null,
      uf: form.elements.uf.value.trim().toUpperCase() || null,
      municipio: form.elements.municipio.value.trim() || null,
      regime_tributario: form.elements.regime_tributario.value || null,
      crt: form.elements.crt.value.trim() || null,
      ativo: form.elements.ativo.value === "1",
    };
    if (!payload.regime_tributario) {
      toast("Escolha o regime tributário na lista (Passo 1).", "warning");
      if (!id) setEmpresaWizardStep(form, 1);
      return;
    }
    try {
      if (id) await fFetch(`/fiscal/empresas/${id}`, { method: "PUT", body: JSON.stringify(payload) });
      else await fFetch("/fiscal/empresas", { method: "POST", body: JSON.stringify(payload) });
      toast("Empresa salva.", "success");
      closeEmpresaModal();
      await renderEmpresasPage();
      await refreshUnidadeEmpresaSelect();
    } catch (err) {
      toast(err.message || "Falha ao salvar empresa.", "error");
    }
  }

  // —— Perfis ——
  async function renderPerfisPage() {
    const root = document.getElementById("fiscalPerfisRoot");
    if (!root) return;
    fiscalPodeEditar = isAdmin();
    await ensureMeta();
    await loadPerfisList(false);
    const rows = (fiscalPerfis || [])
      .map(
        (p) => `<tr data-id="${p.id}">
        <td>${esc(p.nome)}</td>
        <td>${esc(labelTipo(p.tipo_fiscal_padrao))}</td>
        <td>${p.ativo ? "Ativo" : "Inativo"}</td>
        <td class="table-actions">${fiscalPodeEditar ? `<button type="button" class="table-action" data-acao="editar">Editar</button>` : ""}</td>
      </tr>`
      )
      .join("");
    root.innerHTML = `
      <div class="actions">${fiscalPodeEditar ? '<button type="button" class="btn primary" id="fiscalBtnNovoPerfil">+ Novo perfil</button>' : ""}</div>
      <div class="table-card"><header>Perfis tributários</header>
      <p class="subtle-text">Conjuntos reutilizáveis de classificação fiscal para produtos semelhantes.</p>
      <div class="table-wrapper"><table><thead><tr><th>Nome</th><th>Tipo fiscal padrão</th><th>Status</th><th>Ações</th></tr></thead>
      <tbody id="fiscalPerfisTbody">${rows || '<tr><td colspan="4">Nenhum perfil cadastrado.</td></tr>'}</tbody></table></div></div>
      <div class="modal-backdrop" id="fiscalPerfilModal"><div class="modal modal--wide"><header><h2 id="fiscalPerfilModalTitle">Perfil tributário</h2>
      <button type="button" class="close-btn" id="fiscalPerfilModalClose">×</button></header>
      <form id="fiscalPerfilForm" class="fiscal-perfil-form"><input type="hidden" name="id" />
        <label>Nome *<input name="nome" required maxlength="150" /></label>
        <label>Descrição<textarea name="descricao" rows="2"></textarea></label>
        <label>Tipo fiscal padrão<select name="tipo_fiscal_padrao"><option value="">—</option>
          <option value="producao_propria">Produção própria</option><option value="revenda">Revenda</option>
          <option value="insumo">Insumo</option><option value="uso_consumo">Uso e consumo</option></select></label>
        <div class="grid-two">
          <label>NCM padrão<input name="ncm_padrao" maxlength="12" class="fiscal-ncm-mask" /></label>
          <label>CEST padrão<input name="cest_padrao" maxlength="10" /></label>
        </div>
        <div class="grid-two">
          <label>CST ICMS<input name="cst_icms" maxlength="5" /></label>
          <label>CSOSN<input name="csosn" maxlength="6" /></label>
        </div>
        <div class="grid-two">
          <label>CFOP entrada<input name="cfop_entrada_padrao" maxlength="6" /></label>
          <label>CFOP saída<input name="cfop_saida_padrao" maxlength="6" /></label>
        </div>
        <label>Status<select name="ativo"><option value="1">Ativo</option><option value="0">Inativo</option></select></label>
        <label>Observações<textarea name="observacoes" rows="2"></textarea></label>
        <footer><button type="button" class="btn neutral" id="fiscalPerfilCancelar">Cancelar</button>
        ${fiscalPodeEditar ? '<button type="submit" class="btn primary">Salvar</button>' : ""}</footer>
      </form></div></div>`;

    root.querySelectorAll(".fiscal-ncm-mask").forEach(maskNcmInput);
    root.querySelector("#fiscalPerfisTbody")?.addEventListener("click", (ev) => {
      const btn = ev.target.closest('[data-acao="editar"]');
      if (!btn) return;
      const id = btn.closest("tr")?.dataset?.id;
      const p = fiscalPerfis.find((x) => String(x.id) === String(id));
      if (p) openPerfilModal(p);
    });
    root.querySelector("#fiscalBtnNovoPerfil")?.addEventListener("click", () => openPerfilModal(null));
    root.querySelector("#fiscalPerfilModalClose")?.addEventListener("click", closePerfilModal);
    root.querySelector("#fiscalPerfilCancelar")?.addEventListener("click", closePerfilModal);
    root.querySelector("#fiscalPerfilForm")?.addEventListener("submit", submitPerfilForm);
  }

  function openPerfilModal(p) {
    const modal = document.getElementById("fiscalPerfilModal");
    const form = document.getElementById("fiscalPerfilForm");
    if (!modal || !form) return;
    document.getElementById("fiscalPerfilModalTitle").textContent = p ? "Editar perfil" : "Novo perfil";
    form.reset();
    form.elements.id.value = p?.id ?? "";
    if (p) {
      form.elements.nome.value = p.nome || "";
      form.elements.descricao.value = p.descricao || "";
      form.elements.tipo_fiscal_padrao.value = p.tipo_fiscal_padrao || "";
      form.elements.ncm_padrao.value = p.ncm_padrao || "";
      form.elements.cest_padrao.value = p.cest_padrao || "";
      form.elements.cst_icms.value = p.cst_icms || "";
      form.elements.csosn.value = p.csosn || "";
      form.elements.cfop_entrada_padrao.value = p.cfop_entrada_padrao || "";
      form.elements.cfop_saida_padrao.value = p.cfop_saida_padrao || "";
      form.elements.ativo.value = p.ativo ? "1" : "0";
      form.elements.observacoes.value = p.observacoes || "";
    }
    modal.classList.add("active");
  }

  function closePerfilModal() {
    document.getElementById("fiscalPerfilModal")?.classList.remove("active");
  }

  async function submitPerfilForm(e) {
    e.preventDefault();
    if (!fiscalPodeEditar) return;
    const form = e.target;
    const id = (form.elements.id.value || "").trim();
    const payload = {
      nome: form.elements.nome.value.trim(),
      descricao: form.elements.descricao.value.trim() || null,
      tipo_fiscal_padrao: form.elements.tipo_fiscal_padrao.value || null,
      ncm_padrao: form.elements.ncm_padrao.value.trim() || null,
      cest_padrao: form.elements.cest_padrao.value.trim() || null,
      cst_icms: form.elements.cst_icms.value.trim() || null,
      csosn: form.elements.csosn.value.trim() || null,
      cfop_entrada_padrao: form.elements.cfop_entrada_padrao.value.trim() || null,
      cfop_saida_padrao: form.elements.cfop_saida_padrao.value.trim() || null,
      ativo: form.elements.ativo.value === "1",
      observacoes: form.elements.observacoes.value.trim() || null,
    };
    try {
      if (id) await fFetch(`/fiscal/perfis-tributarios/${id}`, { method: "PUT", body: JSON.stringify(payload) });
      else await fFetch("/fiscal/perfis-tributarios", { method: "POST", body: JSON.stringify(payload) });
      toast("Perfil salvo.", "success");
      closePerfilModal();
      await renderPerfisPage();
      await refreshProdutoPerfilSelect();
    } catch (err) {
      toast(err.message || "Falha ao salvar perfil.", "error");
    }
  }

  // —— Produto (aba fiscal) ——
  function produtoFiscalEls() {
    return {
      tipo: document.getElementById("produtoFiscalTipo"),
      perfil: document.getElementById("produtoFiscalPerfil"),
      ncm: document.getElementById("produtoFiscalNcm"),
      cest: document.getElementById("produtoFiscalCest"),
      origem: document.getElementById("produtoFiscalOrigem"),
      cst: document.getElementById("produtoFiscalCst"),
      csosn: document.getElementById("produtoFiscalCsosn"),
      cfopEnt: document.getElementById("produtoFiscalCfopEntrada"),
      cfopSai: document.getElementById("produtoFiscalCfopSaida"),
      mono: document.getElementById("produtoFiscalMonofasico"),
      st: document.getElementById("produtoFiscalSt"),
      credIcms: document.getElementById("produtoFiscalCreditoIcms"),
      credPis: document.getElementById("produtoFiscalCreditoPis"),
      credCof: document.getElementById("produtoFiscalCreditoCofins"),
      obs: document.getElementById("produtoFiscalObs"),
    };
  }

  function setProdutoTab(tab) {
    const geral = document.getElementById("produtoTabPainelGeral");
    const fiscal = document.getElementById("produtoTabPainelFiscal");
    const btnG = document.getElementById("produtoTabBtnGeral");
    const btnF = document.getElementById("produtoTabBtnFiscal");
    const isF = tab === "fiscal";
    geral?.classList.toggle("hidden", isF);
    fiscal?.classList.toggle("hidden", !isF);
    btnG?.classList.toggle("active", !isF);
    btnF?.classList.toggle("active", isF);
  }

  async function refreshProdutoPerfilSelect() {
    const sel = document.getElementById("produtoFiscalPerfil");
    if (!sel) return;
    await loadPerfisList(true);
    const cur = sel.value;
    sel.innerHTML =
      '<option value="">— Nenhum —</option>' +
      (fiscalPerfis || [])
        .filter((p) => p.ativo)
        .map((p) => `<option value="${p.id}">${esc(p.nome)}</option>`)
        .join("");
    if (cur) sel.value = cur;
  }

  function resetProdutoFiscalForm() {
    const els = produtoFiscalEls();
    Object.values(els).forEach((el) => {
      if (!el) return;
      if (el.tagName === "SELECT") el.value = "";
      else el.value = "";
    });
    setProdutoTab("geral");
  }

  function fillProdutoFiscalForm(produto) {
    const p = produto || {};
    const els = produtoFiscalEls();
    if (els.tipo) els.tipo.value = p.tipo_fiscal || "";
    if (els.perfil) els.perfil.value = p.perfil_tributario_id != null ? String(p.perfil_tributario_id) : "";
    if (els.ncm) els.ncm.value = p.ncm || "";
    if (els.cest) els.cest.value = p.cest || "";
    if (els.origem) els.origem.value = p.origem_mercadoria != null ? String(p.origem_mercadoria) : "";
    if (els.cst) els.cst.value = p.cst_icms || "";
    if (els.csosn) els.csosn.value = p.csosn || "";
    if (els.cfopEnt) els.cfopEnt.value = p.cfop_entrada_padrao || "";
    if (els.cfopSai) els.cfopSai.value = p.cfop_saida_padrao || "";
    setBoolSelect(els.mono, p.monofasico);
    setBoolSelect(els.st, p.substituicao_tributaria);
    setBoolSelect(els.credIcms, p.gera_credito_icms);
    setBoolSelect(els.credPis, p.gera_credito_pis);
    setBoolSelect(els.credCof, p.gera_credito_cofins);
    if (els.obs) els.obs.value = p.observacao_fiscal || "";
  }

  function collectProdutoFiscalPayload() {
    const els = produtoFiscalEls();
    if (!els.tipo) return {};
    return {
      tipo_fiscal: els.tipo.value || null,
      perfil_tributario_id: els.perfil?.value ? Number(els.perfil.value) : null,
      ncm: els.ncm?.value.trim() || null,
      cest: els.cest?.value.trim() || null,
      origem_mercadoria: els.origem?.value !== "" ? els.origem.value : null,
      cst_icms: els.cst?.value.trim() || null,
      csosn: els.csosn?.value.trim() || null,
      cfop_entrada_padrao: els.cfopEnt?.value.trim() || null,
      cfop_saida_padrao: els.cfopSai?.value.trim() || null,
      monofasico: boolSelectValue(els.mono),
      substituicao_tributaria: boolSelectValue(els.st),
      gera_credito_icms: boolSelectValue(els.credIcms),
      gera_credito_pis: boolSelectValue(els.credPis),
      gera_credito_cofins: boolSelectValue(els.credCof),
      observacao_fiscal: els.obs?.value.trim() || null,
    };
  }

  function validateProdutoFiscal(isNew) {
    const els = produtoFiscalEls();
    if (!els.tipo) return true;
    if (isNew && !els.tipo.value) {
      toast("Informe o tipo fiscal do produto (aba Dados fiscais).", "warning");
      setProdutoTab("fiscal");
      els.tipo.focus();
      return false;
    }
    return true;
  }

  function appendProdutoFiscalFormData(formData) {
    const f = collectProdutoFiscalPayload();
    Object.entries(f).forEach(([k, v]) => {
      if (v === null || v === undefined) return;
      formData.append(k, typeof v === "boolean" ? (v ? "1" : "0") : String(v));
    });
  }

  async function aplicarSugestaoPerfil() {
    const sel = document.getElementById("produtoFiscalPerfil");
    if (!sel?.value) return;
    try {
      const res = await fFetch(`/fiscal/perfis-tributarios/${sel.value}/sugestao-produto`);
      const sug = res?.sugestao || {};
      const keys = Object.keys(sug);
      if (!keys.length) return;
      const els = produtoFiscalEls();
      const conflitos = keys.filter((k) => {
        const elMap = {
          tipo_fiscal: els.tipo,
          ncm: els.ncm,
          cest: els.cest,
          cst_icms: els.cst,
          csosn: els.csosn,
          cfop_entrada_padrao: els.cfopEnt,
          cfop_saida_padrao: els.cfopSai,
        };
        const el = elMap[k];
        if (!el) return false;
        const cur = String(el.value || "").trim();
        return cur !== "" && String(sug[k]) !== cur;
      });
      if (conflitos.length && !confirm("Alguns campos fiscais já estão preenchidos. Substituir pelos valores do perfil?")) {
        return;
      }
      if (sug.tipo_fiscal && els.tipo && (!els.tipo.value || conflitos.includes("tipo_fiscal"))) els.tipo.value = sug.tipo_fiscal;
      if (sug.ncm && els.ncm && (!els.ncm.value.trim() || conflitos.includes("ncm"))) els.ncm.value = sug.ncm;
      if (sug.cest && els.cest && (!els.cest.value.trim() || conflitos.includes("cest"))) els.cest.value = sug.cest;
      if (sug.cst_icms && els.cst && (!els.cst.value.trim() || conflitos.includes("cst_icms"))) els.cst.value = sug.cst_icms;
      if (sug.csosn && els.csosn && (!els.csosn.value.trim() || conflitos.includes("csosn"))) els.csosn.value = sug.csosn;
      if (sug.cfop_entrada_padrao && els.cfopEnt && (!els.cfopEnt.value.trim() || conflitos.includes("cfop_entrada_padrao")))
        els.cfopEnt.value = sug.cfop_entrada_padrao;
      if (sug.cfop_saida_padrao && els.cfopSai && (!els.cfopSai.value.trim() || conflitos.includes("cfop_saida_padrao")))
        els.cfopSai.value = sug.cfop_saida_padrao;
      toast("Dados sugeridos pelo perfil aplicados.", "success");
    } catch (err) {
      toast(err.message || "Não foi possível carregar sugestão do perfil.", "error");
    }
  }

  async function refreshUnidadeEmpresaSelect() {
    const selects = document.querySelectorAll('select[name="empresa_id"]');
    if (!selects.length) return;
    await loadEmpresasList();
    const optionsHtml =
      '<option value="">— Sem vínculo —</option>' +
      (fiscalEmpresas || [])
        .filter((e) => e.ativo)
        .map((e) => `<option value="${e.id}">${esc(e.nome_fantasia || e.razao_social)}</option>`)
        .join("");
    selects.forEach((sel) => {
      const cur = sel.value;
      sel.innerHTML = optionsHtml;
      if (cur) sel.value = cur;
    });
    updateUnidadeEmpresaHint();
  }

  function updateUnidadeEmpresaHint() {
    const selects = document.querySelectorAll('#unidadeForm select[name="empresa_id"], #unidadeInlineForm select[name="empresa_id"]');
    if (!selects.length) return;
    const activeSel = [...selects].find((s) => s.offsetParent !== null) || selects[0];
    const hints = [
      document.getElementById("unidadeEmpresaHint"),
      document.getElementById("unidadeInlineEmpresaHint"),
    ].filter(Boolean);
    const emp = fiscalEmpresas.find((e) => String(e.id) === String(activeSel.value));
    let msg;
    if (emp) {
      msg = `Empresa: ${emp.razao_social} · CNPJ ${fmtCnpj(emp.cnpj)} · Regime tributário: ${labelRegime(emp.regime_tributario)}`;
    } else if (!(fiscalEmpresas || []).filter((e) => e.ativo).length) {
      msg =
        "Nenhuma empresa cadastrada. Clique em “Ir para Empresas (CNPJ)” acima, cadastre a empresa e escolha o regime (Passo 1).";
    } else {
      msg = "Selecione a empresa (CNPJ). O regime tributário vem do cadastro da empresa, não da unidade.";
    }
    hints.forEach((hint) => {
      hint.textContent = msg;
    });
    const callout = document.getElementById("unidadeFiscalCallout");
    if (callout) {
      callout.classList.toggle("fiscal-unidade-callout--ok", !!emp?.regime_tributario);
    }
  }

  function initUnidadeFiscalCallout() {
    const btn = document.getElementById("btnIrCadastroEmpresaFiscal");
    if (!btn || btn.dataset.bound) return;
    btn.dataset.bound = "1";
    btn.addEventListener("click", () => {
      if (typeof window.navigateTo === "function") window.navigateTo("fiscalEmpresas");
      else showToast("Abra Configurações → Empresas (CNPJ) no menu.", "info");
    });
  }

  function initProdutoFiscalUi() {
    document.getElementById("produtoTabBtnGeral")?.addEventListener("click", () => setProdutoTab("geral"));
    document.getElementById("produtoTabBtnFiscal")?.addEventListener("click", () => setProdutoTab("fiscal"));
    maskNcmInput(document.getElementById("produtoFiscalNcm"));
    document.getElementById("produtoFiscalAplicarPerfil")?.addEventListener("click", () => void aplicarSugestaoPerfil());
    document.querySelectorAll('select[name="empresa_id"]').forEach((sel) => {
      if (sel.dataset.fiscalEmpresaBound) return;
      sel.dataset.fiscalEmpresaBound = "1";
      sel.addEventListener("change", updateUnidadeEmpresaHint);
    });
    const filtro = document.getElementById("produtoFiscalFiltro");
    if (filtro && !filtro.dataset.bound) {
      filtro.dataset.bound = "1";
      filtro.addEventListener("change", () => {
        if (typeof window.loadProdutos === "function") {
          const q = document.getElementById("produtoSearch")?.value?.trim() || "";
          window.loadProdutos(q);
        }
      });
    }
    void refreshProdutoPerfilSelect();
    void refreshUnidadeEmpresaSelect();
    initUnidadeFiscalCallout();
  }

  window.fiscalOnUnidadesSectionOpen = function () {
    initUnidadeFiscalCallout();
    return refreshUnidadeEmpresaSelect();
  };

  window.loadFiscalEmpresas = renderEmpresasPage;
  window.loadFiscalPerfisTributarios = renderPerfisPage;
  window.fiscalProdutoResetForm = resetProdutoFiscalForm;
  window.fiscalProdutoFillForm = fillProdutoFiscalForm;
  window.fiscalProdutoCollectPayload = collectProdutoFiscalPayload;
  window.fiscalProdutoValidate = validateProdutoFiscal;
  window.fiscalProdutoAppendFormData = appendProdutoFiscalFormData;
  window.fiscalBadgeStatus = badgeStatus;
  window.fiscalLabelTipo = labelTipo;
  window.fiscalRefreshUnidadeEmpresas = refreshUnidadeEmpresaSelect;

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initProdutoFiscalUi);
  } else {
    initProdutoFiscalUi();
  }
})();
