/**
 * Fiscal — Configuração de emissão (NF-e / NFC-e).
 */
(function () {
  "use strict";

  let femMeta = null;
  let femPodeEditar = false;
  let femEmpresaId = null;
  let femBusy = false;

  const FOCUS_URL = {
    homologation: "https://homologacao.focusnfe.com.br",
    production: "https://api.focusnfe.com.br",
  };

  function focusBaseUrl(env) {
    return FOCUS_URL[env] || FOCUS_URL.homologation;
  }

  function applyFocusUrlIfEmpty(root, force) {
    if (root.querySelector("#femProvider")?.value !== "focus_nfe") return;
    const env = root.querySelector("#femAmbiente")?.value || "homologation";
    const urlEl = root.querySelector("#femApiUrl");
    if (!urlEl) return;
    const cur = urlEl.value.trim().replace(/\/$/, "");
    const known = Object.values(FOCUS_URL).map((u) => u.replace(/\/$/, ""));
    if (force || !cur || known.includes(cur)) {
      urlEl.value = focusBaseUrl(env);
    }
  }

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

  function fmtCnpj(digits) {
    const d = String(digits || "").replace(/\D/g, "");
    if (d.length !== 14) return digits || "—";
    return d.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, "$1.$2.$3/$4-$5");
  }

  function badgeProntidao(status) {
    const s = (status || "not_configured").toLowerCase();
    if (s === "ready") return '<span class="fem-badge fem-badge--ready">Pronto</span>';
    if (s === "pending") return '<span class="fem-badge fem-badge--pending">Pendências</span>';
    return '<span class="fem-badge fem-badge--off">Não configurado</span>';
  }

  function checklistHtml(prontidao) {
    const itens = prontidao?.itens || [];
    if (!itens.length) return "<p class=\"subtle-text\">Salve a configuração para ver o checklist.</p>";
    return `<ul class="fem-checklist">${itens
      .map((i) => {
        const cls = i.ok ? "fem-ok" : "fem-pend";
        const hint = i.hint ? ` <span class="subtle-text">— ${esc(i.hint)}</span>` : "";
        return `<li class="${cls}">${i.ok ? "✓" : "○"} ${esc(i.label)}${hint}</li>`;
      })
      .join("")}</ul>`;
  }

  function providerOptions(selected) {
    const prov = femMeta?.providers || {};
    return Object.entries(prov)
      .map(([k, label]) => `<option value="${esc(k)}" ${k === selected ? "selected" : ""}>${esc(label)}</option>`)
      .join("");
  }

  function readForm(root) {
    const g = (id) => root.querySelector(`#${id}`)?.value;
    const chk = (id) => !!root.querySelector(`#${id}`)?.checked;
    return {
      provider: g("femProvider") || "focus_nfe",
      environment: g("femAmbiente") || "homologation",
      api_url: g("femApiUrl")?.trim() || null,
      api_token: g("femApiToken")?.trim() || null,
      certificado_senha: g("femCertSenha")?.trim() || null,
      csc_id: g("femCscId")?.trim() || null,
      csc_token: g("femCscToken")?.trim() || null,
      serie_nfce: g("femSerieNfce") ? Number(g("femSerieNfce")) : null,
      serie_nfe: g("femSerieNfe") ? Number(g("femSerieNfe")) : null,
      numero_proximo_nfce: g("femNumNfce") ? Number(g("femNumNfce")) : null,
      numero_proximo_nfe: g("femNumNfe") ? Number(g("femNumNfe")) : null,
      emitir_nfce_pdv: chk("femEmitNfce"),
      emitir_nfe_pedido: chk("femEmitNfe"),
      is_active: chk("femAtivo"),
      observacoes: g("femObs")?.trim() || null,
    };
  }

  function toggleProviderBlocks(root) {
    const p = root.querySelector("#femProvider")?.value || "focus_nfe";
    root.querySelector("#femBlocoApi")?.classList.toggle("hidden", p === "certificado_a1");
    root.querySelector("#femBlocoCert")?.classList.toggle("hidden", p !== "certificado_a1");
    const vfHint = root.querySelector("#femVfHint");
    if (vfHint) vfHint.classList.toggle("hidden", p !== "vendafacil");
    const focusGuide = root.querySelector("#femFocusGuide");
    if (focusGuide) focusGuide.classList.toggle("hidden", p !== "focus_nfe");
    if (p === "focus_nfe") applyFocusUrlIfEmpty(root, false);
  }

  async function loadCertBase64(file) {
    if (!file) return null;
    const buf = await file.arrayBuffer();
    let binary = "";
    const bytes = new Uint8Array(buf);
    for (let i = 0; i < bytes.length; i++) binary += String.fromCharCode(bytes[i]);
    return btoa(binary);
  }

  function renderForm(root, data) {
    const emp = data.empresa;
    const cfg = data.config || {};
    const def = data.defaults || {};
    const pr = data.prontidao || {};
    const env = cfg.environment || def.environment || "homologation";
    const prov = cfg.provider || def.provider || "focus_nfe";

    root.innerHTML = `
      <div class="fem-page">
        <div class="int-card">
          <header><h3>${esc(emp.nome_fantasia || emp.razao_social)}</h3>
            <p class="subtle-text">CNPJ ${esc(fmtCnpj(emp.cnpj))} · ${esc(emp.uf || "—")} · ${badgeProntidao(pr.status)}</p>
          </header>
          ${femMeta?.mensagem ? `<p class="subtle-text">${esc(femMeta.mensagem)}</p>` : ""}
          <div class="fem-block">
            <h4>Checklist para emitir</h4>
            <div id="femChecklist">${checklistHtml(pr)}</div>
          </div>
        </div>

        <div class="int-card">
          <h3>Provedor e ambiente</h3>
          <div class="fem-grid-2">
            <label>Provedor de emissão
              <select id="femProvider" ${femPodeEditar ? "" : "disabled"}>${providerOptions(prov)}</select>
            </label>
            <label>Ambiente SEFAZ
              <select id="femAmbiente" ${femPodeEditar ? "" : "disabled"}>
                <option value="homologation" ${env === "homologation" ? "selected" : ""}>Homologação (testes)</option>
                <option value="production" ${env === "production" ? "selected" : ""}>Produção</option>
              </select>
            </label>
          </div>
          <p id="femVfHint" class="subtle-text hidden">Para VendaFácil, configure também <strong>Integrações → VendaFácil</strong> (token e ambiente).</p>

          <div id="femFocusGuide" class="fem-focus-guide ${prov === "focus_nfe" ? "" : "hidden"}">
            <h4>Passo a passo Focus NFe</h4>
            <ol class="subtle-text">
              <li>Crie conta em <a href="https://focusnfe.com.br" target="_blank" rel="noopener">focusnfe.com.br</a> e acesse o painel.</li>
              <li><strong>Empresas → Adicionar</strong> — mesmo CNPJ do SAS; informe certificado <strong>A1</strong> (.pfx) e senha no painel Focus (obrigatório lá).</li>
              <li><strong>Painel API → Tokens</strong> — copie o <strong>Token de Homologação</strong> (testes) ou <strong>Token de Produção</strong> (notas válidas).</li>
              <li>Cole o token abaixo. A URL muda só com o ambiente (homologação × produção).</li>
              <li>CSC NFC-e: portal SEFAZ do estado (ex. PA) → cadastro NFC-e → gerar CSC → preencher ID e código nesta tela.</li>
            </ol>
            <p class="subtle-text">Documentação: <a href="https://doc.focusnfe.com.br/reference/ambiente" target="_blank" rel="noopener">ambientes e URLs</a> · Autenticação HTTP Basic (usuário = token, senha vazia).</p>
          </div>

          <div id="femBlocoApi" class="fem-block">
            <h4>API Focus NFe</h4>
            <div class="fem-grid-2">
              <label>URL da API
                <input type="url" id="femApiUrl" placeholder="${esc(focusBaseUrl(env))}" value="${esc(cfg.api_url || "")}" ${femPodeEditar ? "" : "disabled"} />
              </label>
              <label>Token Focus (homologação ou produção)
                <input type="password" id="femApiToken" autocomplete="new-password" placeholder="${cfg.api_token_configurado ? "Deixe vazio para manter" : "Token do Painel API → Tokens"}" ${femPodeEditar ? "" : "disabled"} />
              </label>
            </div>
            ${femPodeEditar ? `<button type="button" class="btn neutral fem-btn-inline" id="femFocusUrlPadrao">Usar URL padrão Focus</button>` : ""}
            ${cfg.api_token_configurado ? `<p class="subtle-text">Token atual: ${esc(cfg.api_token_mascarado)}</p>` : ""}
            <p class="subtle-text fem-file-hint">Homologação: ${esc(FOCUS_URL.homologation)} · Produção: ${esc(FOCUS_URL.production)}</p>
          </div>

          <div id="femBlocoCert" class="fem-block hidden">
            <h4>Certificado A1</h4>
            <label>Arquivo .pfx
              <input type="file" id="femCertFile" accept=".pfx,.p12" ${femPodeEditar ? "" : "disabled"} />
            </label>
            <p class="fem-file-hint">${cfg.certificado_configurado ? "Certificado já enviado (substitua escolhendo novo arquivo)." : "Opcional até ativar emissão direta."}</p>
            <label>Senha do certificado
              <input type="password" id="femCertSenha" autocomplete="new-password" placeholder="${cfg.certificado_senha_configurada ? "Deixe vazio para manter" : ""}" ${femPodeEditar ? "" : "disabled"} />
            </label>
          </div>

          <div class="fem-block">
            <h4>NFC-e (PDV / cupom)</h4>
            <label class="checkbox-label"><input type="checkbox" id="femEmitNfce" ${cfg.emitir_nfce_pdv !== false ? "checked" : ""} ${femPodeEditar ? "" : "disabled"} /> Emitir NFC-e nas vendas PDV (quando motor estiver ativo)</label>
            <div class="fem-grid-2" style="margin-top:0.65rem">
              <label>Série NFC-e<input type="number" id="femSerieNfce" min="1" max="999" value="${esc(cfg.serie_nfce ?? "1")}" ${femPodeEditar ? "" : "disabled"} /></label>
              <label>Próximo número<input type="number" id="femNumNfce" min="1" value="${esc(cfg.numero_proximo_nfce ?? "1")}" ${femPodeEditar ? "" : "disabled"} /></label>
              <label>ID CSC<input id="femCscId" maxlength="20" value="${esc(cfg.csc_id || "")}" ${femPodeEditar ? "" : "disabled"} /></label>
              <label>Token CSC<input type="password" id="femCscToken" autocomplete="new-password" placeholder="${cfg.csc_token_configurado ? "Deixe vazio para manter" : ""}" ${femPodeEditar ? "" : "disabled"} /></label>
            </div>
            ${cfg.csc_token_configurado ? `<p class="subtle-text">CSC: ${esc(cfg.csc_token_mascarado)}</p>` : ""}
          </div>

          <div class="fem-block">
            <h4>NF-e (pedido / nota completa)</h4>
            <label class="checkbox-label"><input type="checkbox" id="femEmitNfe" ${cfg.emitir_nfe_pedido ? "checked" : ""} ${femPodeEditar ? "" : "disabled"} /> Habilitar NF-e além do cupom</label>
            <div class="fem-grid-2" style="margin-top:0.65rem">
              <label>Série NF-e<input type="number" id="femSerieNfe" min="1" max="999" value="${esc(cfg.serie_nfe ?? "1")}" ${femPodeEditar ? "" : "disabled"} /></label>
              <label>Próximo número<input type="number" id="femNumNfe" min="1" value="${esc(cfg.numero_proximo_nfe ?? "1")}" ${femPodeEditar ? "" : "disabled"} /></label>
            </div>
          </div>

          <div class="fem-block">
            <label class="checkbox-label"><input type="checkbox" id="femAtivo" ${cfg.is_active ? "checked" : ""} ${femPodeEditar ? "" : "disabled"} /> Emissão ativa para este CNPJ</label>
            <label style="margin-top:0.65rem;display:block">Observações<textarea id="femObs" rows="2" ${femPodeEditar ? "" : "disabled"}>${esc(cfg.observacoes || "")}</textarea></label>
          </div>

          <div class="fem-actions cfg-form-actions">
            <button type="button" class="btn primary" id="femSalvar" ${femPodeEditar ? "" : "disabled"}>Salvar configuração</button>
            <button type="button" class="btn secondary" id="femValidar">Validar checklist</button>
            <button type="button" class="btn neutral" id="femTestar" ${femPodeEditar ? "" : "disabled"}>Testar credenciais</button>
          </div>
        </div>
      </div>`;

    root.querySelector("#femProvider")?.addEventListener("change", () => toggleProviderBlocks(root));
    root.querySelector("#femAmbiente")?.addEventListener("change", () => applyFocusUrlIfEmpty(root, true));
    root.querySelector("#femFocusUrlPadrao")?.addEventListener("click", () => applyFocusUrlIfEmpty(root, true));
    toggleProviderBlocks(root);
    if (prov === "focus_nfe" && !cfg.api_url) applyFocusUrlIfEmpty(root, true);

    root.querySelector("#femSalvar")?.addEventListener("click", () => saveConfig(root));
    root.querySelector("#femValidar")?.addEventListener("click", () => validar(root));
    root.querySelector("#femTestar")?.addEventListener("click", () => testar(root));
  }

  async function saveConfig(root) {
    if (!femEmpresaId || femBusy) return;
    femBusy = true;
    try {
      const body = readForm(root);
      const file = root.querySelector("#femCertFile")?.files?.[0];
      if (file) body.certificado_pfx_base64 = await loadCertBase64(file);
      const res = await fFetch(`/fiscal/emissao/config/${femEmpresaId}`, { method: "PUT", body: JSON.stringify(body) });
      toast("Configuração de emissão salva.", "success");
      const cl = root.querySelector("#femChecklist");
      if (cl && res.prontidao) cl.innerHTML = checklistHtml(res.prontidao);
    } catch (e) {
      toast(e?.message || "Falha ao salvar.", "error");
    } finally {
      femBusy = false;
    }
  }

  async function validar(root) {
    if (!femEmpresaId) return;
    try {
      const res = await fFetch(`/fiscal/emissao/config/${femEmpresaId}/validar`, { method: "POST" });
      const cl = root.querySelector("#femChecklist");
      if (cl && res.prontidao) cl.innerHTML = checklistHtml(res.prontidao);
      toast(res.prontidao?.pronto ? "Checklist completo." : "Ainda há pendências.", res.prontidao?.pronto ? "success" : "info");
    } catch (e) {
      toast(e?.message || "Falha na validação.", "error");
    }
  }

  async function testar(root) {
    if (!femEmpresaId) return;
    try {
      const res = await fFetch(`/fiscal/emissao/config/${femEmpresaId}/testar`, { method: "POST" });
      toast(res.message || (res.success ? "OK" : "Verifique os dados."), res.success ? "success" : "warning");
    } catch (e) {
      toast(e?.message || "Falha no teste.", "error");
    }
  }

  async function loadEmpresaConfig(root, empresaId) {
    femEmpresaId = empresaId;
    root.innerHTML = "<p class=\"subtle-text\">Carregando…</p>";
    const data = await fFetch(`/fiscal/emissao/config/${empresaId}`);
    renderForm(root, data);
  }

  async function renderResumo(root) {
    root.innerHTML = "<p class=\"subtle-text\">Carregando empresas…</p>";
    femMeta = femMeta || (await fFetch("/fiscal/emissao/meta"));
    const res = await fFetch("/fiscal/emissao/resumo");
    femPodeEditar = !!res.pode_editar;
    const list = res.empresas || [];
    if (!list.length) {
      root.innerHTML = `<div class="int-card"><p>Cadastre uma empresa em <strong>Empresas (CNPJ)</strong> antes de configurar a emissão.</p></div>`;
      return;
    }

    const cards = list
      .map((row) => {
        const e = row.empresa;
        const pr = row.prontidao || {};
        return `<button type="button" class="fem-resumo-card" data-fem-empresa="${e.id}">
          <span class="subtle-text">${esc(fmtCnpj(e.cnpj))}</span>
          <strong>${esc(e.nome_fantasia || e.razao_social)}</strong>
          ${badgeProntidao(pr.status)}
        </button>`;
      })
      .join("");

    root.innerHTML = `
      <div class="fem-page">
        <div class="fem-resumo-cards">${cards}</div>
        <div class="int-card">
          <h3>Emissão de nota fiscal (NF-e / NFC-e)</h3>
          <p class="subtle-text">${esc(femMeta.mensagem || "")}</p>
          <div class="fem-empresa-bar" style="margin-top:1rem">
            <label>Empresa (CNPJ)
              <select id="femSelectEmpresa">
                ${list.map((row) => {
                  const e = row.empresa;
                  return `<option value="${e.id}">${esc(e.nome_fantasia || e.razao_social)} · ${esc(fmtCnpj(e.cnpj))}</option>`;
                }).join("")}
              </select>
            </label>
            <button type="button" class="btn primary" id="femAbrirEmpresa">Configurar</button>
          </div>
        </div>
        <div id="femDetalhe"></div>
      </div>`;

    const det = root.querySelector("#femDetalhe");
    const open = (id) => {
      if (det) loadEmpresaConfig(det, id);
      root.querySelector("#femSelectEmpresa").value = String(id);
    };
    root.querySelector("#femAbrirEmpresa")?.addEventListener("click", () => {
      const id = Number(root.querySelector("#femSelectEmpresa")?.value);
      if (id) open(id);
    });
    root.querySelectorAll("[data-fem-empresa]").forEach((btn) => {
      btn.addEventListener("click", () => open(Number(btn.dataset.femEmpresa)));
    });
    open(Number(list[0].empresa.id));
  }

  async function renderPage(opts) {
    const rootId = opts?.rootId || "fiscalEmissaoConfigRoot";
    const root = document.getElementById(rootId);
    if (!root) return;
    try {
      femMeta = await fFetch("/fiscal/emissao/meta");
      await renderResumo(root);
    } catch (e) {
      root.innerHTML = `<div class="int-card"><p class="subtle-text">${esc(e?.message || "Não foi possível carregar. Execute a migration fiscal_emissao_configs.")}</p></div>`;
    }
  }

  window.loadFiscalEmissaoConfig = renderPage;
})();
