/**
 * Módulo Integrações — Fase 2 (conexão real VendaFácil).
 */
(function () {
  "use strict";

  const RECURSOS_VF_PADRAO = [
    "produtos", "estoque", "clientes", "pedidos", "delivery",
    "vendas", "caixa", "fiscal", "pagamentos",
  ];

  let intPodeConfigurar = false;
  let intUnidades = [];
  let intUnitMappings = [];
  let intBusy = false;

  function intToast(msg, type) {
    const fn = typeof showToast === "function" ? showToast : window.showToast;
    if (typeof fn === "function") fn(msg, type || "info");
  }

  async function intFetch(path, opts) {
    if (typeof window.fetchJSON === "function") return window.fetchJSON(path, opts);
    throw new Error("fetchJSON não disponível");
  }

  function intEsc(s) {
    if (typeof escapeHtml === "function") return escapeHtml(s);
    const d = document.createElement("div");
    d.textContent = s == null ? "" : String(s);
    return d.innerHTML;
  }

  function intFmtData(iso) {
    if (!iso) return "—";
    try {
      return new Date(iso).toLocaleString("pt-BR");
    } catch (_) {
      return "—";
    }
  }

  function intBadgeConnection(status) {
    const s = (status || "offline").toLowerCase();
    if (s === "online") return '<span class="int-badge int-badge--online">● Online</span>';
    if (s === "error") return '<span class="int-badge int-badge--error">● Erro</span>';
    return '<span class="int-badge int-badge--offline">● Offline</span>';
  }

  function intBadgeIntegrationStatus(status, label) {
    const s = (status || "not_configured").toLowerCase();
    const text = label || s;
    const map = {
      connected: "int-badge--online",
      degraded: "int-badge--soon",
      authentication_error: "int-badge--error",
      connection_error: "int-badge--error",
      disabled: "int-badge--offline",
      disconnected: "int-badge--offline",
      configured: "int-badge--soon",
      not_configured: "int-badge--offline",
    };
    const cls = map[s] || "int-badge--offline";
    return `<span class="int-badge ${cls}">● ${intEsc(text)}</span>`;
  }

  function intPerfilAdmin() {
    const p = (window.currentUser?.perfil || "").toString().trim().toUpperCase();
    return p === "ADMIN" || p === "ADMINISTRADOR";
  }

  function intSetBusy(busy) {
    intBusy = busy;
    document.querySelectorAll(".int-actions .btn").forEach((btn) => {
      if (btn.dataset.intAlwaysDisabled === "1") return;
      btn.disabled = busy || btn.dataset.intNeedsAdmin === "1";
    });
  }

  function intBtnLoading(btn, loading, labelLoading) {
    if (!btn) return;
    if (loading) {
      btn.dataset.intPrevText = btn.textContent;
      btn.textContent = labelLoading || "Aguarde…";
      btn.disabled = true;
    } else {
      btn.textContent = btn.dataset.intPrevText || btn.textContent;
      btn.disabled = btn.dataset.intNeedsAdmin === "1" && !intPodeConfigurar;
    }
  }

  function intMappingRow(u, mapping) {
    const m = mapping || {};
    const extId = m.external_id || "";
    const extName = m.external_name || "";
    const primary = !!m.is_primary;
    const active = m.is_active !== false;
    const dis = intPodeConfigurar ? "" : "disabled";

    return `<div class="int-unidade-map__row" data-unidade-id="${u.id}">
      <div class="int-unidade-map__local">
        <strong>${intEsc(u.nome)}</strong>
        <span class="int-meta">ID local ${u.id}</span>
      </div>
      <label class="int-field">
        <span>ID externo VendaFácil</span>
        <input type="text" class="int-map-external-id" data-unidade-id="${u.id}" placeholder="external_id" value="${intEsc(extId)}" ${dis} />
      </label>
      <label class="int-field">
        <span>Nome externo</span>
        <input type="text" class="int-map-external-name" data-unidade-id="${u.id}" placeholder="Nome remoto" value="${intEsc(extName)}" ${dis} />
      </label>
      <label class="checkbox-label int-unidade-map__flags">
        <input type="checkbox" class="int-map-primary" data-unidade-id="${u.id}" ${primary ? "checked" : ""} ${dis} /> Principal
      </label>
      <label class="checkbox-label int-unidade-map__flags">
        <input type="checkbox" class="int-map-active" data-unidade-id="${u.id}" ${active ? "checked" : ""} ${dis} /> Ativo
      </label>
    </div>`;
  }

  function intRenderUnidadeMappings(mappingsJson, unitMappingsApi, unidades) {
    const jsonMap = mappingsJson && typeof mappingsJson === "object" ? mappingsJson : {};
    const apiMap = {};
    (unitMappingsApi || []).forEach((m) => {
      apiMap[String(m.local_id)] = {
        external_id: m.external_id,
        external_name: m.meta_json?.external_name || m.external_name,
        is_primary: m.meta_json?.is_primary,
        is_active: m.meta_json?.is_active,
      };
    });

    if (!unidades.length) {
      return '<p class="int-meta">Nenhuma unidade cadastrada.</p>';
    }

    return unidades.map((u) => {
      const key = String(u.id);
      const mapping = apiMap[key] || jsonMap[key] || null;
      return intMappingRow(u, mapping);
    }).join("");
  }

  function intColetarUnitMappingsPayload() {
    const rows = [];
    intUnidades.forEach((u) => {
      const id = String(u.id);
      const extId = document.querySelector(`.int-map-external-id[data-unidade-id="${u.id}"]`)?.value?.trim();
      if (!extId) return;
      rows.push({
        local_id: id,
        external_id: extId,
        external_name: document.querySelector(`.int-map-external-name[data-unidade-id="${u.id}"]`)?.value?.trim() || null,
        is_primary: !!document.querySelector(`.int-map-primary[data-unidade-id="${u.id}"]`)?.checked,
        is_active: !!document.querySelector(`.int-map-active[data-unidade-id="${u.id}"]`)?.checked,
      });
    });
    return rows;
  }

  function intColetarRecursos() {
    const sel = [];
    document.querySelectorAll('input[name="int_recurso"]:checked').forEach((cb) => sel.push(cb.value));
    return sel;
  }

  function intRenderRecursos(disponiveis, selecionados) {
    const sel = new Set((selecionados || []).map(String));
    const entries = Object.entries(disponiveis || {});
    if (!entries.length) {
      return RECURSOS_VF_PADRAO.map(
        (k) =>
          `<label class="checkbox-label"><input type="checkbox" name="int_recurso" value="${k}" ${sel.has(k) ? "checked" : ""} ${intPodeConfigurar ? "" : "disabled"} /> ${intEsc(k)}</label>`
      ).join("");
    }
    return entries
      .map(
        ([key, label]) =>
          `<label class="checkbox-label"><input type="checkbox" name="int_recurso" value="${intEsc(key)}" ${sel.has(key) ? "checked" : ""} ${intPodeConfigurar ? "" : "disabled"} /> ${intEsc(label)}</label>`
      )
      .join("");
  }

  function intAtualizarCardsStatus(cfg, resultado) {
    const statusEl = document.getElementById("intVfStatusBadge");
    const connEl = document.getElementById("intVfConnBadge");
    if (statusEl) {
      statusEl.innerHTML = intBadgeIntegrationStatus(cfg.integration_status, cfg.integration_status_label);
    }
    if (connEl) {
      connEl.innerHTML = intBadgeConnection(cfg.connection_status);
    }
    const meta = document.getElementById("intVfStatusMeta");
    if (meta && cfg) {
      meta.innerHTML = `
        <div>Empresa remota: <strong>${intEsc(cfg.empresa_external_name || "—")}</strong> (${intEsc(cfg.empresa_external_id || "—")})</div>
        <div>Ambiente remoto: <strong>${intEsc(cfg.config_json?.remote_environment || cfg.environment || "—")}</strong></div>
        <div>Versão API: <strong>${intEsc(cfg.api_version || "—")}</strong></div>
        <div>Último teste: <strong>${intFmtData(cfg.last_sync_at)}</strong></div>
        <div>Última conexão OK: <strong>${intFmtData(cfg.last_successful_connection_at)}</strong></div>
        <div>Tempo de resposta: <strong>${cfg.last_response_time_ms != null ? cfg.last_response_time_ms + " ms" : "—"}</strong></div>
        <div>Último erro: <strong>${intEsc(cfg.last_error || "—")}</strong></div>
        ${resultado?.http_status != null ? `<div>HTTP: <strong>${resultado.http_status}</strong></div>` : ""}`;
    }
    const empField = document.getElementById("intVfEmpresa");
    if (empField && cfg.empresa_external_id) {
      empField.value = cfg.empresa_external_id;
    }
    const empName = document.getElementById("intVfEmpresaNome");
    if (empName) {
      empName.textContent = cfg.empresa_external_name || "—";
    }
  }

  async function loadIntegracaoVendafacil() {
    const root = document.getElementById("integracaoVendafacilRoot");
    if (!root) return;
    root.innerHTML = '<p class="subtle-text">Carregando…</p>';
    intPodeConfigurar = intPerfilAdmin();

    try {
      const data = await intFetch("/integracoes/vendafacil");
      const cfg = data.integration || {};
      intUnidades = data.unidades || [];
      intUnitMappings = data.unit_mappings || [];

      root.innerHTML = `
        <div class="int-page-wrap">
          <div class="int-grid-2 cfg-stats-row">
            <div class="int-card">
              <header><h3>Status da integração</h3></header>
              <div class="int-status-row">
                <span id="intVfStatusBadge">${intBadgeIntegrationStatus(cfg.integration_status, cfg.integration_status_label)}</span>
                <span id="intVfConnBadge">${intBadgeConnection(cfg.connection_status)}</span>
                <span class="int-meta">Ambiente config.: <strong>${cfg.environment === "production" ? "Produção" : "Homologação"}</strong></span>
              </div>
              <div class="int-meta" id="intVfStatusMeta" style="margin-top:0.75rem;">
                <div>Empresa remota: <strong>${intEsc(cfg.empresa_external_name || "—")}</strong> (${intEsc(cfg.empresa_external_id || "—")})</div>
                <div>Ambiente remoto: <strong>${intEsc(cfg.config_json?.remote_environment || "—")}</strong></div>
                <div>Versão API: <strong>${intEsc(cfg.api_version || "—")}</strong></div>
                <div>Último teste: <strong>${intFmtData(cfg.last_sync_at)}</strong></div>
                <div>Última conexão OK: <strong>${intFmtData(cfg.last_successful_connection_at)}</strong></div>
                <div>Tempo de resposta: <strong>${cfg.last_response_time_ms != null ? cfg.last_response_time_ms + " ms" : "—"}</strong></div>
                <div>Último erro: <strong>${intEsc(cfg.last_error || "—")}</strong></div>
              </div>
            </div>
            <div class="int-card">
              <header><h3>Resumo</h3></header>
              <p class="int-meta">Token: <strong>${cfg.bearer_token_configurado ? intEsc(cfg.bearer_token_mascarado) : "Não configurado"}</strong></p>
              <p class="int-meta">Webhook secret: <strong>${cfg.webhook_secret_configurado ? intEsc(cfg.webhook_secret_mascarado || "••••") : "Não configurado"}</strong></p>
              <p class="int-meta">Integração ativa: <strong>${cfg.is_active ? "Sim" : "Não"}</strong></p>
              <p class="int-meta">Falhas consecutivas: <strong>${cfg.consecutive_failures ?? 0}</strong></p>
            </div>
          </div>

          <div class="int-card">
            <header><h3>Configuração</h3></header>
            <form id="intVfForm" class="int-form-grid" onsubmit="return false;">
              <label class="int-field int-field--full">
                <span>URL base da API</span>
                <input type="url" id="intVfApiUrl" placeholder="https://vendaffacil.com.br/api/v1" value="${intEsc(cfg.api_url || "")}" ${intPodeConfigurar ? "" : "disabled"} />
              </label>
              <label class="int-field int-field--full">
                <span>Bearer Token</span>
                <input type="password" id="intVfToken" placeholder="${cfg.bearer_token_configurado ? "Deixe vazio para manter o token atual" : "Cole o token"}" autocomplete="new-password" ${intPodeConfigurar ? "" : "disabled"} />
              </label>
              <label class="int-field">
                <span>Ambiente</span>
                <select id="intVfAmbiente" ${intPodeConfigurar ? "" : "disabled"}>
                  <option value="homologation" ${cfg.environment !== "production" ? "selected" : ""}>Homologação</option>
                  <option value="production" ${cfg.environment === "production" ? "selected" : ""}>Produção</option>
                </select>
              </label>
              <label class="int-field">
                <span>Empresa remota (identificada pela API)</span>
                <input type="text" id="intVfEmpresa" value="${intEsc(cfg.empresa_external_id || "")}" readonly disabled />
                <span class="int-meta" id="intVfEmpresaNome">${intEsc(cfg.empresa_external_name || "Será preenchido após teste de conexão")}</span>
              </label>
              <label class="int-field">
                <span>Timeout (3–60 s)</span>
                <input type="number" id="intVfTimeout" min="3" max="60" value="${cfg.timeout_seconds ?? 30}" ${intPodeConfigurar ? "" : "disabled"} />
              </label>
              <label class="int-field">
                <span>Tentativas (0–5)</span>
                <input type="number" id="intVfRetry" min="0" max="5" value="${cfg.retry_count ?? 3}" ${intPodeConfigurar ? "" : "disabled"} />
              </label>
              <label class="int-field int-field--full">
                <span>Webhook Secret</span>
                <input type="password" id="intVfWebhook" placeholder="${cfg.webhook_secret_configurado ? "Deixe vazio para manter" : "Secret para validar webhooks"}" autocomplete="new-password" ${intPodeConfigurar ? "" : "disabled"} />
              </label>
              <label class="int-field int-field--full">
                <span>Observações</span>
                <textarea id="intVfObs" rows="2" ${intPodeConfigurar ? "" : "disabled"}>${intEsc(cfg.observacoes || "")}</textarea>
              </label>
              <div class="int-field int-field--full">
                <span>Mapeamento de unidades (manual)</span>
                <div class="int-unidade-map int-unidade-map--phase2" id="intVfUnidadeMap">${intRenderUnidadeMappings(cfg.unidade_mappings, intUnitMappings, intUnidades)}</div>
              </div>
              <div class="int-field int-field--full">
                <span>Recursos habilitados (estrutural — sem sync na Fase 2)</span>
                <div class="int-check-grid" id="intVfRecursos">${intRenderRecursos(data.recursos_disponiveis, cfg.enabled_resources)}</div>
              </div>
              <label class="checkbox-label int-field--full">
                <input type="checkbox" id="intVfAtivo" ${cfg.is_active ? "checked" : ""} ${intPodeConfigurar ? "" : "disabled"} />
                Integração ativa
              </label>
            </form>
            <div class="int-actions cfg-form-actions">
              <button type="button" class="btn primary" id="intVfSalvar" data-int-needs-admin="1" ${intPodeConfigurar ? "" : "disabled"}>Salvar</button>
              <button type="button" class="btn secondary" id="intVfTestar" data-int-needs-admin="1" ${intPodeConfigurar ? "" : "disabled"}>Testar conexão</button>
              <button type="button" class="btn secondary" id="intVfSalvarUnidades" data-int-needs-admin="1" ${intPodeConfigurar ? "" : "disabled"}>Salvar unidades</button>
              <button type="button" class="btn neutral" id="intVfCache" data-int-needs-admin="1" ${intPodeConfigurar ? "" : "disabled"}>Limpar cache</button>
              <button type="button" class="btn neutral" id="intVfLogsBtn">Visualizar logs</button>
              <button type="button" class="btn danger" id="intVfDesconectar" data-int-needs-admin="1" ${intPodeConfigurar ? "" : "disabled"}>Desconectar</button>
            </div>
          </div>
        </div>`;

      document.getElementById("intVfSalvar")?.addEventListener("click", intSalvarVendafacil);
      document.getElementById("intVfTestar")?.addEventListener("click", intTestarVendafacil);
      document.getElementById("intVfSalvarUnidades")?.addEventListener("click", intSalvarUnidadesVf);
      document.getElementById("intVfCache")?.addEventListener("click", () => intAcaoVf("/integracoes/vendafacil/limpar-cache", "Cache limpo."));
      document.getElementById("intVfDesconectar")?.addEventListener("click", intDesconectarVf);
      document.getElementById("intVfLogsBtn")?.addEventListener("click", () => {
        if (typeof navigateTo === "function") navigateTo("integracaoLogs");
      });
    } catch (e) {
      root.innerHTML = `<p class="subtle-text">Erro ao carregar: ${intEsc(e.message || e)}</p>`;
    }
  }

  async function intSalvarVendafacil() {
    if (intBusy) return;
    const btn = document.getElementById("intVfSalvar");
    intSetBusy(true);
    intBtnLoading(btn, true, "Salvando…");
    try {
      const payload = {
        api_url: document.getElementById("intVfApiUrl")?.value?.trim() || "",
        environment: document.getElementById("intVfAmbiente")?.value || "homologation",
        timeout_seconds: parseInt(document.getElementById("intVfTimeout")?.value || "30", 10),
        retry_count: parseInt(document.getElementById("intVfRetry")?.value || "3", 10),
        enabled_resources: intColetarRecursos(),
        is_active: !!document.getElementById("intVfAtivo")?.checked,
        observacoes: document.getElementById("intVfObs")?.value?.trim() || null,
      };
      const token = document.getElementById("intVfToken")?.value?.trim();
      const wh = document.getElementById("intVfWebhook")?.value?.trim();
      if (token) payload.bearer_token = token;
      if (wh) payload.webhook_secret = wh;

      const res = await intFetch("/integracoes/vendafacil", { method: "PUT", body: JSON.stringify(payload) });
      intToast(res.mensagem || "Configuração salva.", "success");
      if (res.integration) intAtualizarCardsStatus(res.integration);
      loadIntegracaoVendafacil();
    } catch (e) {
      intToast(e.message || "Erro ao salvar.", "error");
    } finally {
      intBtnLoading(btn, false);
      intSetBusy(false);
    }
  }

  async function intTestarVendafacil() {
    if (intBusy) return;
    const btn = document.getElementById("intVfTestar");
    intSetBusy(true);
    intBtnLoading(btn, true, "Testando…");
    try {
      const res = await intFetch("/integracoes/vendafacil/testar", { method: "POST", body: "{}" });
      const ok = res.ok || res.resultado?.success;
      intToast(res.mensagem || (ok ? "Conexão estabelecida." : "Falha no teste."), ok ? "success" : "error");
      if (res.integration) intAtualizarCardsStatus(res.integration, res.resultado);
      if (ok) loadIntegracaoVendafacil();
    } catch (e) {
      intToast(e.message || "Erro no teste.", "error");
    } finally {
      intBtnLoading(btn, false);
      intSetBusy(false);
    }
  }

  async function intSalvarUnidadesVf() {
    if (intBusy) return;
    const btn = document.getElementById("intVfSalvarUnidades");
    intSetBusy(true);
    intBtnLoading(btn, true, "Salvando…");
    try {
      const mappings = intColetarUnitMappingsPayload();
      const res = await intFetch("/integracoes/vendafacil/unidades", {
        method: "PUT",
        body: JSON.stringify({ mappings }),
      });
      intToast(res.mensagem || "Unidades salvas.", "success");
    } catch (e) {
      intToast(e.message || "Erro ao salvar unidades.", "error");
    } finally {
      intBtnLoading(btn, false);
      intSetBusy(false);
    }
  }

  async function intAcaoVf(path, okMsg) {
    if (intBusy) return;
    intSetBusy(true);
    try {
      const res = await intFetch(path, { method: "POST", body: "{}" });
      intToast(res.mensagem || okMsg, res.ok === false ? "error" : "info");
      loadIntegracaoVendafacil();
    } catch (e) {
      intToast(e.message || "Erro na operação.", "error");
    } finally {
      intSetBusy(false);
    }
  }

  async function intDesconectarVf() {
    if (intBusy) return;
    if (!confirm("Desconectar a integração VendaFácil?\n\nTokens serão removidos. Logs e histórico serão preservados.")) return;
    const clearMappings = confirm("Deseja também remover os mapeamentos de unidades?");
    intSetBusy(true);
    try {
      const res = await intFetch("/integracoes/vendafacil/desconectar", {
        method: "POST",
        body: JSON.stringify({ clear_mappings: clearMappings }),
      });
      intToast(res.mensagem || "Desconectado.", "success");
      loadIntegracaoVendafacil();
    } catch (e) {
      intToast(e.message || "Erro ao desconectar.", "error");
    } finally {
      intSetBusy(false);
    }
  }

  async function loadIntegracaoAplicacoes() {
    const root = document.getElementById("integracaoAplicacoesRoot");
    if (!root) return;
    root.innerHTML = '<p class="subtle-text">Carregando…</p>';

    try {
      const data = await intFetch("/integracoes/aplicacoes");
      const apps = data.apps || [];
      root.innerHTML = `
        <div class="int-apps-grid">
          ${apps
            .map((app) => {
              const btn =
                app.section && app.implemented
                  ? `<button type="button" class="btn secondary btn-sm int-app-open" data-section="${intEsc(app.section)}">Abrir</button>`
                  : app.implemented
                    ? ""
                    : `<span class="int-badge int-badge--soon">Em breve</span>`;
              return `<article class="int-app-card">
                <div class="int-app-card__icon">${app.icon || "🔌"}</div>
                <h4>${intEsc(app.name)}</h4>
                <p>${intEsc(app.description)}</p>
                <div class="int-status-row">${intBadgeConnection(app.connection_status)} ${btn}</div>
              </article>`;
            })
            .join("")}
        </div>`;
      root.querySelectorAll(".int-app-open").forEach((btn) => {
        btn.addEventListener("click", () => {
          const sec = btn.getAttribute("data-section");
          if (sec && typeof navigateTo === "function") navigateTo(sec);
        });
      });
    } catch (e) {
      root.innerHTML = `<p class="subtle-text">Erro: ${intEsc(e.message)}</p>`;
    }
  }

  async function loadIntegracaoHealthCheck(live) {
    const root = document.getElementById("integracaoHealthCheckRoot");
    if (!root) return;
    root.innerHTML = '<p class="subtle-text">Carregando…</p>';

    try {
      const qs = live === false ? "?live=0" : "?live=1";
      const data = await intFetch("/integracoes/health-check" + qs);
      const providers = data.providers || [];
      const rows =
        providers.length === 0
          ? `<tr><td colspan="10" class="int-logs-empty">Nenhum provider configurado.</td></tr>`
          : providers
              .map((p) => {
                const st = p.integration_status || p.status || "not_configured";
                const stLabel = p.status_label || st;
                return `<tr>
              <td>${intEsc(p.name || p.provider)}</td>
              <td>${intBadgeIntegrationStatus(st, stLabel)}</td>
              <td>${p.api_online ? intBadgeConnection("online") : intBadgeConnection("offline")}</td>
              <td>${p.token_valid === true ? "Válido" : p.token_valid === false ? "Inválido" : "—"}</td>
              <td>${intEsc(p.company?.name || p.empresa_external_name || "—")}</td>
              <td>${intEsc(p.environment || "—")}</td>
              <td>${intEsc(p.api_version || "—")}</td>
              <td>${p.response_time_ms != null ? p.response_time_ms + " ms" : "—"}</td>
              <td>${intFmtData(p.last_successful_connection_at)}</td>
              <td>${intEsc(p.last_error || "—")}</td>
            </tr>`;
              })
              .join("");

      root.innerHTML = `
        <div class="int-health-toolbar">
          <button type="button" class="btn secondary btn-sm" id="intHealthRefresh">Atualizar</button>
          <span class="int-meta">Online: ${data.resumo?.online ?? 0} / ${data.resumo?.total ?? 0}</span>
        </div>
        <div class="table-card">
          <div class="table-wrapper">
            <table class="data-table int-health-table">
              <thead>
                <tr>
                  <th>Sistema</th>
                  <th>Status</th>
                  <th>API</th>
                  <th>Token</th>
                  <th>Empresa</th>
                  <th>Ambiente</th>
                  <th>Versão</th>
                  <th>Tempo</th>
                  <th>Última OK</th>
                  <th>Último erro</th>
                </tr>
              </thead>
              <tbody>${rows}</tbody>
            </table>
          </div>
        </div>`;

      document.getElementById("intHealthRefresh")?.addEventListener("click", () => loadIntegracaoHealthCheck(true));
    } catch (e) {
      root.innerHTML = `<p class="subtle-text">Erro: ${intEsc(e.message)}</p>`;
    }
  }

  function intBuildLogsQuery() {
    const params = new URLSearchParams();
    params.set("limit", "100");
    const provider = document.getElementById("intLogProvider")?.value;
    const status = document.getElementById("intLogStatus")?.value;
    const op = document.getElementById("intLogOperation")?.value;
    const sucesso = document.getElementById("intLogSucesso")?.value;
    const di = document.getElementById("intLogDataInicio")?.value;
    const df = document.getElementById("intLogDataFim")?.value;
    if (provider) params.set("provider", provider);
    if (status) params.set("status", status);
    if (op) params.set("operation", op);
    if (sucesso) params.set("sucesso", sucesso);
    if (di) params.set("data_inicio", di);
    if (df) params.set("data_fim", df);
    return params.toString();
  }

  async function loadIntegracaoLogs() {
    const section = document.getElementById("integracaoLogsSection");
    const tbody = document.getElementById("integracaoLogsBody");
    if (!section || !tbody) return;

    let toolbar = document.getElementById("intLogsToolbar");
    if (!toolbar) {
      const head = section.querySelector(".int-section-head");
      toolbar = document.createElement("div");
      toolbar.id = "intLogsToolbar";
      toolbar.className = "int-logs-toolbar";
      toolbar.innerHTML = `
        <div class="int-form-grid int-logs-filters">
          <label class="int-field"><span>Sistema</span>
            <select id="intLogProvider"><option value="">Todos</option><option value="vendafacil">VendaFácil</option></select>
          </label>
          <label class="int-field"><span>Status</span>
            <select id="intLogStatus"><option value="">Todos</option><option value="success">Sucesso</option><option value="error">Erro</option></select>
          </label>
          <label class="int-field"><span>Operação</span>
            <input type="text" id="intLogOperation" placeholder="test_connection" />
          </label>
          <label class="int-field"><span>Resultado</span>
            <select id="intLogSucesso"><option value="">Todos</option><option value="1">Sucesso</option><option value="0">Falha</option></select>
          </label>
          <label class="int-field"><span>De</span><input type="date" id="intLogDataInicio" /></label>
          <label class="int-field"><span>Até</span><input type="date" id="intLogDataFim" /></label>
        </div>
        <button type="button" class="btn secondary btn-sm" id="intLogsFiltrar">Filtrar</button>`;
      head?.after(toolbar);
      document.getElementById("intLogsFiltrar")?.addEventListener("click", () => loadIntegracaoLogs());
    }

    tbody.innerHTML = '<tr><td colspan="9">Carregando…</td></tr>';

    try {
      const data = await intFetch("/integracoes/logs?" + intBuildLogsQuery());
      const logs = data.logs || [];

      if (!logs.length) {
        tbody.innerHTML = '<tr><td colspan="9" class="int-logs-empty">Nenhum log encontrado.</td></tr>';
      } else {
        tbody.innerHTML = logs
          .map(
            (log) => `<tr>
            <td>${intFmtData(log.data)}</td>
            <td>${intEsc(log.sistema)}</td>
            <td><code>${intEsc(log.endpoint || "—")}</code></td>
            <td>${intEsc(log.metodo || "—")}</td>
            <td>${log.tempo_ms != null ? log.tempo_ms + " ms" : "—"}</td>
            <td>${intEsc(log.status)}${log.http_status != null ? ` (${log.http_status})` : ""}</td>
            <td>${intEsc(log.mensagem || "—")}</td>
            <td>${log.empresa_id ?? "—"}</td>
            <td>${log.usuario_id ?? "—"}</td>
          </tr>`
          )
          .join("");
      }
    } catch (e) {
      tbody.innerHTML = `<tr><td colspan="9">Erro: ${intEsc(e.message)}</td></tr>`;
    }
  }

  function loadIntegracaoPlaceholder(rootId, titulo) {
    const root = document.getElementById(rootId);
    if (!root) return;
    root.innerHTML = `
      <div class="int-placeholder">
        <div class="int-placeholder__icon">🚧</div>
        <h3>${intEsc(titulo)}</h3>
        <p class="subtle-text">Módulo preparado para expansão futura.</p>
      </div>`;
  }

  function loadIntegracaoWebhooks() {
    loadIntegracaoPlaceholder("integracaoWebhooksRoot", "Webhooks");
  }

  function loadIntegracaoTokens() {
    loadIntegracaoPlaceholder("integracaoTokensRoot", "Tokens");
  }

  function loadIntegracaoConfiguracoes() {
    loadIntegracaoPlaceholder("integracaoConfiguracoesRoot", "Configurações de Integrações");
  }

  function setupIntegracoesModule() {}

  window.loadIntegracaoVendafacil = loadIntegracaoVendafacil;
  window.loadIntegracaoAplicacoes = loadIntegracaoAplicacoes;
  window.loadIntegracaoHealthCheck = loadIntegracaoHealthCheck;
  window.loadIntegracaoLogs = loadIntegracaoLogs;
  window.loadIntegracaoWebhooks = loadIntegracaoWebhooks;
  window.loadIntegracaoTokens = loadIntegracaoTokens;
  window.loadIntegracaoConfiguracoes = loadIntegracaoConfiguracoes;
  window.setupIntegracoesModule = setupIntegracoesModule;
})();
