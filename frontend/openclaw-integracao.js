/**
 * OpenClaw — Integração Assistente IA (Configurações > Integrações)
 */
(function () {
  "use strict";

  let ocTokenGerado = "";
  let ocPodeEditar = false;

  function ocToast(msg, type) {
    const fn = typeof showToast === "function" ? showToast : window.showToast;
    if (typeof fn === "function") fn(msg, type || "info");
  }

  async function ocFetch(path, opts) {
    if (typeof window.fetchJSON === "function") return window.fetchJSON(path, opts);
    throw new Error("fetchJSON não disponível");
  }

  function ocEsc(s) {
    if (typeof escapeHtml === "function") return escapeHtml(s);
    const d = document.createElement("div");
    d.textContent = s == null ? "" : String(s);
    return d.innerHTML;
  }

  function ocRenderAcoes(acoesDisponiveis, selecionadas) {
    const box = document.getElementById("ocAcoesPermitidas");
    if (!box) return;
    const sel = new Set((selecionadas || []).map(String));
    box.innerHTML = Object.entries(acoesDisponiveis || {})
      .map(
        ([key, label]) =>
          `<label class="checkbox-label"><input type="checkbox" name="oc_acao" value="${ocEsc(key)}" ${
            sel.has(key) ? "checked" : ""
          } /> ${ocEsc(label)}</label>`
      )
      .join("");
  }

  function ocRenderUnidades(unidades, selecionadas) {
    const box = document.getElementById("ocUnidadesPermitidas");
    if (!box) return;
    const sel = new Set((selecionadas || []).map((id) => String(id)));
    if (!unidades || !unidades.length) {
      box.innerHTML = '<p class="subtle-text">Nenhuma unidade cadastrada. Deixe vazio para permitir todas.</p>';
      return;
    }
    box.innerHTML = unidades
      .map(
        (u) =>
          `<label class="checkbox-label"><input type="checkbox" name="oc_unidade" value="${u.id}" ${
            sel.has(String(u.id)) ? "checked" : ""
          } /> ${ocEsc(u.nome)}</label>`
      )
      .join("");
  }

  function ocRenderLogs(logs) {
    const tbody = document.getElementById("ocLogsBody");
    if (!tbody) return;
    if (!logs || !logs.length) {
      tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#607d8b">Nenhum comando registrado ainda.</td></tr>';
      return;
    }
    tbody.innerHTML = logs
      .map((log) => {
        const quando = log.created_at ? new Date(log.created_at).toLocaleString("pt-BR") : "—";
        const msg =
          (log.resposta && log.resposta.message) ||
          (log.resposta && log.resposta.error) ||
          "—";
        return `<tr>
          <td>${ocEsc(quando)}</td>
          <td>${ocEsc(log.acao || "—")}</td>
          <td>${ocEsc(log.status || "—")}</td>
          <td>${ocEsc(msg)}</td>
          <td><code>${ocEsc(log.comando || "—")}</code></td>
        </tr>`;
      })
      .join("");
  }

  function ocColetarUnidades() {
    return Array.from(document.querySelectorAll('#ocUnidadesPermitidas input[name="oc_unidade"]:checked')).map((el) =>
      parseInt(el.value, 10)
    );
  }

  function ocColetarAcoes() {
    return Array.from(document.querySelectorAll('#ocAcoesPermitidas input[name="oc_acao"]:checked')).map((el) => el.value);
  }

  function ocPreencherForm(config, unidades) {
    const ativo = document.getElementById("ocAtivo");
    const url = document.getElementById("ocUrl");
    const token = document.getElementById("ocToken");
    const apiBase = document.getElementById("ocApiBase");
    const status = document.getElementById("ocStatusBadge");
    const envLine = document.getElementById("ocEnvLine");

    if (ativo) ativo.checked = !!config.ativo;
    if (url) url.value = config.url || "";
    if (token) token.value = config.token_mascarado || "";
    if (apiBase) apiBase.value = config.api_base || "";
    if (envLine) {
      const tok = ocTokenGerado || (config.token_configurado ? "SEU_TOKEN_AQUI" : "");
      envLine.textContent = tok && !tok.includes("•")
        ? `OPENCLAW_SAS_TOKEN=${tok}`
        : "OPENCLAW_SAS_TOKEN=oc_sas_... (gere o token abaixo)";
    }

    if (status) {
      const partes = [];
      partes.push(config.ativo ? "Integração ativa" : "Integração desativada");
      partes.push(config.token_configurado ? "Token configurado" : "Sem token");
      status.textContent = partes.join(" · ");
    }

    ocRenderUnidades(unidades, config.unidades_permitidas);
    ocRenderAcoes(config.acoes_disponiveis, config.acoes_permitidas);
    ocAtualizarSetup(config);

    document.querySelectorAll("#ocForm input, #ocForm textarea, #ocForm button").forEach((el) => {
      if (el.id === "ocBtnTestar" || el.id === "ocBtnGerarToken" || el.id === "ocBtnCopiarToken" || el.id === "ocBtnCopiarApi") return;
      if (!ocPodeEditar) el.setAttribute("disabled", "disabled");
      else el.removeAttribute("disabled");
    });
    const salvar = document.getElementById("ocSalvarBtn");
    if (salvar) {
      salvar.disabled = !ocPodeEditar;
      salvar.title = ocPodeEditar ? "" : "Somente administrador pode salvar";
    }
    const gerar = document.getElementById("ocBtnGerarToken");
    if (gerar) gerar.disabled = !ocPodeEditar;
  }

  function ocAtualizarSetup(config) {
    const tokenOk = !!(config.token_configurado || (ocTokenGerado && !ocTokenGerado.includes("•")));
    const ativoOk = !!config.ativo;
    const urlOk = !!(config.url && String(config.url).trim());
    const map = {
      token: tokenOk,
      env: tokenOk,
      ativo: ativoOk,
      vps: urlOk,
      teste: false,
    };
    document.querySelectorAll("#ocSetupSteps li[data-step]").forEach((li) => {
      const step = li.dataset.step;
      li.classList.toggle("oc-step-done", !!map[step]);
    });
    const resumo = document.getElementById("ocSetupResumo");
    if (!resumo) return;
    const faltam = [];
    if (!tokenOk) faltam.push("gerar token");
    if (!ativoOk) faltam.push("ativar e salvar");
    if (!urlOk) faltam.push("URL da VPS");
    resumo.textContent = faltam.length
      ? `Falta: ${faltam.join(", ")}. Depois clique em Testar conexão.`
      : "Configuração básica OK. Instale a skill na VPS e teste a conexão.";
  }

  async function ocCopiar(texto, okMsg) {
    if (!texto) {
      ocToast("Nada para copiar.", "warning");
      return;
    }
    try {
      await navigator.clipboard.writeText(texto);
      ocToast(okMsg || "Copiado.", "success");
    } catch (_) {
      ocToast("Não foi possível copiar.", "error");
    }
  }

  async function loadOpenClawIntegracao() {
    const perfil = (window.currentUser?.perfil || "").toString().trim().toUpperCase();
    ocPodeEditar = perfil === "ADMIN";
    const aviso = document.getElementById("ocAvisoAdmin");
    if (aviso) {
      aviso.classList.toggle("hidden", ocPodeEditar);
      aviso.textContent = ocPodeEditar
        ? ""
        : "Somente ADMIN pode alterar configurações. Você pode visualizar o status da integração.";
    }

    try {
      const data = await ocFetch("/openclaw/config");
      ocPreencherForm(data.config || {}, data.unidades || []);
      const logsData = await ocFetch("/openclaw/logs?limit=30");
      ocRenderLogs(logsData.logs || []);
    } catch (e) {
      ocToast(e?.message || "Falha ao carregar integração OpenClaw.", "error");
    }
  }

  function ocBindEvents() {
    const form = document.getElementById("ocForm");
    if (form && !form.dataset.bound) {
      form.dataset.bound = "1";
      form.addEventListener("submit", async (ev) => {
        ev.preventDefault();
        if (!ocPodeEditar) return;
        try {
          await ocFetch("/openclaw/config", {
            method: "POST",
            body: JSON.stringify({
              ativo: document.getElementById("ocAtivo")?.checked || false,
              url: document.getElementById("ocUrl")?.value || "",
              unidades_permitidas: ocColetarUnidades(),
              acoes_permitidas: ocColetarAcoes(),
            }),
          });
          ocToast("Configurações OpenClaw salvas.", "success");
          await loadOpenClawIntegracao();
        } catch (e) {
          ocToast(e?.message || "Erro ao salvar.", "error");
        }
      });
    }

    const btnGerar = document.getElementById("ocBtnGerarToken");
    if (btnGerar && !btnGerar.dataset.bound) {
      btnGerar.dataset.bound = "1";
      btnGerar.addEventListener("click", async () => {
        if (!ocPodeEditar) return;
        if (!confirm("Gerar novo token? O token anterior deixará de funcionar.")) return;
        try {
          const data = await ocFetch("/openclaw/gerar-token", { method: "POST", body: "{}" });
          ocTokenGerado = data.token || "";
          const tokenEl = document.getElementById("ocToken");
          if (tokenEl) tokenEl.value = ocTokenGerado;
          const envLine = document.getElementById("ocEnvLine");
          if (envLine) envLine.textContent = `OPENCLAW_SAS_TOKEN=${ocTokenGerado}`;
          ocToast(data.aviso || "Token gerado. Copie agora!", "success");
          ocAtualizarSetup({ token_configurado: true, ativo: document.getElementById("ocAtivo")?.checked });
        } catch (e) {
          ocToast(e?.message || "Erro ao gerar token.", "error");
        }
      });
    }

    const btnCopiar = document.getElementById("ocBtnCopiarToken");
    if (btnCopiar && !btnCopiar.dataset.bound) {
      btnCopiar.dataset.bound = "1";
      btnCopiar.addEventListener("click", async () => {
        const val = ocTokenGerado || document.getElementById("ocToken")?.value || "";
        if (!val || val.includes("•")) {
          ocToast("Gere um token novo para copiar o valor completo.", "warning");
          return;
        }
        await ocCopiar(val, "Token copiado.");
      });
    }

    const btnCopiarEnv = document.getElementById("ocBtnCopiarEnv");
    if (btnCopiarEnv && !btnCopiarEnv.dataset.bound) {
      btnCopiarEnv.dataset.bound = "1";
      btnCopiarEnv.addEventListener("click", () => {
        const line = document.getElementById("ocEnvLine")?.textContent || "";
        ocCopiar(line, "Linha .env copiada.");
      });
    }

    const btnCopiarApi = document.getElementById("ocBtnCopiarApi");
    if (btnCopiarApi && !btnCopiarApi.dataset.bound) {
      btnCopiarApi.dataset.bound = "1";
      btnCopiarApi.addEventListener("click", () => {
        ocCopiar(document.getElementById("ocApiBase")?.value || "", "URL da API copiada.");
      });
    }

    const btnTestar = document.getElementById("ocBtnTestar");
    if (btnTestar && !btnTestar.dataset.bound) {
      btnTestar.dataset.bound = "1";
      btnTestar.addEventListener("click", async () => {
        try {
          const data = await ocFetch("/openclaw/testar-conexao", { method: "POST", body: "{}" });
          const msg = data.message || (data.ok ? "Conexão OK" : "Falha no teste");
          ocToast(msg, data.ok ? "success" : "error");
          if (data.ok) {
            const li = document.getElementById("ocStep5");
            if (li) li.classList.add("oc-step-done");
            const resumo = document.getElementById("ocSetupResumo");
            if (resumo) resumo.textContent = "Tudo certo! OpenClaw pode usar a API do SAS-Estoque.";
          }
        } catch (e) {
          ocToast(e?.message || "Falha no teste de conexão.", "error");
        }
      });
    }

    const btnLogs = document.getElementById("ocBtnAtualizarLogs");
    if (btnLogs && !btnLogs.dataset.bound) {
      btnLogs.dataset.bound = "1";
      btnLogs.addEventListener("click", async () => {
        try {
          const data = await ocFetch("/openclaw/logs?limit=50");
          ocRenderLogs(data.logs || []);
        } catch (e) {
          ocToast(e?.message || "Erro ao carregar logs.", "error");
        }
      });
    }
  }

  window.loadOpenClawIntegracao = loadOpenClawIntegracao;
  document.addEventListener("DOMContentLoaded", ocBindEvents);
})();
