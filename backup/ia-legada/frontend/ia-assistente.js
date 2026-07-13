/**
 * Assistente IA — configuração OpenAI e chat de ajuda
 */
(function () {
  "use strict";

  const IA_WELCOME =
    "Olá! Sou o assistente do SAS Estoque.\n\n" +
    "Posso ajudar com:\n" +
    "• Estoque, lotes e movimentações (consumo, produção, transferência, perda)\n" +
    "• Financeiro, boletos, vale/consumo e fechamento\n" +
    "• RH, funcionários e reservas de mesa\n" +
    "• Onde encontrar telas e como usar o sistema\n\n" +
    "Não altero dados — só oriento. Como posso ajudar?";

  let iaHistorico = [];
  let iaEnviando = false;

  function iaToast(msg, type) {
    const fn = typeof showToast === "function" ? showToast : window.showToast;
    if (typeof fn === "function") fn(msg, type || "info");
  }

  async function iaFetch(path, opts) {
    if (typeof window.fetchJSON === "function") return window.fetchJSON(path, opts);
    throw new Error("fetchJSON não disponível");
  }

  function iaEsc(text) {
    if (typeof escapeHtml === "function") return escapeHtml(text);
    const d = document.createElement("div");
    d.textContent = text == null ? "" : String(text);
    return d.innerHTML;
  }

  function iaFormatMsg(text) {
    return iaEsc(text).replace(/\n/g, "<br>");
  }

  function iaRenderChat() {
    const box = document.getElementById("iaChatMessages");
    if (!box) return;
    if (!iaHistorico.length) {
      box.innerHTML =
        '<div class="ia-msg ia-msg--bot">' +
          '<div class="ia-msg__avatar">🤖</div>' +
          '<div class="ia-msg__bubble">' + iaFormatMsg(IA_WELCOME) + "</div>" +
        "</div>";
      return;
    }
    box.innerHTML = iaHistorico
      .map(function (m) {
        const cls = m.role === "user" ? "ia-msg ia-msg--user" : "ia-msg ia-msg--bot";
        const av = m.role === "user" ? "👤" : "🤖";
        return (
          '<div class="' + cls + '">' +
            '<div class="ia-msg__avatar">' + av + "</div>" +
            '<div class="ia-msg__bubble">' + iaFormatMsg(m.content) + "</div>" +
          "</div>"
        );
      })
      .join("");
    box.scrollTop = box.scrollHeight;
  }

  async function loadIaAssistente() {
    const aviso = document.getElementById("iaChatAviso");
    const form = document.getElementById("iaChatForm");
    const input = document.getElementById("iaChatInput");
    try {
      const st = await iaFetch("/ia/status");
      const ativo = !!st.ativo;
      if (aviso) {
        aviso.classList.toggle("hidden", ativo);
        aviso.textContent = ativo
          ? ""
          : "Assistente desativado ou sem chave API. ADMIN: configure em IA → Configurações.";
      }
      if (form) form.classList.toggle("ia-chat-form--disabled", !ativo);
      if (input) input.disabled = !ativo;
    } catch (e) {
      if (aviso) {
        aviso.classList.remove("hidden");
        aviso.textContent = e?.message || "Não foi possível verificar o assistente.";
      }
    }
    iaRenderChat();
  }

  async function iaEnviarMensagem(e) {
    e?.preventDefault();
    if (iaEnviando) return;
    const input = document.getElementById("iaChatInput");
    const btn = document.getElementById("iaChatEnviar");
    const msg = (input?.value || "").trim();
    if (!msg) return;

    iaEnviando = true;
    if (btn) btn.disabled = true;
    iaHistorico.push({ role: "user", content: msg });
    iaRenderChat();
    if (input) input.value = "";

    const loadingId = "ia-loading-" + Date.now();
    const box = document.getElementById("iaChatMessages");
    if (box) {
      box.insertAdjacentHTML(
        "beforeend",
        '<div class="ia-msg ia-msg--bot" id="' + loadingId + '">' +
          '<div class="ia-msg__avatar">🤖</div>' +
          '<div class="ia-msg__bubble ia-msg__bubble--loading">Pensando…</div>' +
        "</div>"
      );
      box.scrollTop = box.scrollHeight;
    }

    try {
      const data = await iaFetch("/ia/chat", {
        method: "POST",
        body: JSON.stringify({
          message: msg,
          history: iaHistorico.slice(0, -1),
        }),
      });
      document.getElementById(loadingId)?.remove();
      iaHistorico.push({ role: "assistant", content: data.reply || "Sem resposta." });
      iaRenderChat();
    } catch (err) {
      document.getElementById(loadingId)?.remove();
      iaHistorico.pop();
      iaRenderChat();
      iaToast(err?.message || "Erro ao enviar mensagem.", "error");
    } finally {
      iaEnviando = false;
      if (btn) btn.disabled = false;
      input?.focus();
    }
  }

  function iaLimparChat() {
    iaHistorico = [];
    iaRenderChat();
  }

  async function loadIaConfiguracoes() {
    const form = document.getElementById("iaConfigForm");
    const aviso = document.getElementById("iaConfigAviso");
    try {
      const data = await iaFetch("/ia/config");
      const c = data.config || {};
      const ativoEl = document.getElementById("iaCfgAtivo");
      const keyEl = document.getElementById("iaCfgApiKey");
      const modeloEl = document.getElementById("iaCfgModelo");
      const instrEl = document.getElementById("iaCfgInstrucoes");
      const statusEl = document.getElementById("iaCfgStatus");
      if (ativoEl) ativoEl.checked = !!c.ia_ativo;
      if (keyEl) {
        keyEl.value = "";
        keyEl.placeholder = c.ia_api_key_configurada
          ? "Chave configurada (" + c.ia_api_key + ") — deixe em branco para manter"
          : "sk-... (cole a chave da OpenAI)";
      }
      if (modeloEl) {
        const modelos = data.modelos_sugeridos || ["gpt-4o-mini", "gpt-4o"];
        modeloEl.innerHTML = modelos
          .map(function (m) {
            return (
              '<option value="' + iaEsc(m) + '"' +
              (c.ia_modelo === m ? " selected" : "") +
              ">" + iaEsc(m) + "</option>"
            );
          })
          .join("");
        if (c.ia_modelo && !modelos.includes(c.ia_modelo)) {
          modeloEl.insertAdjacentHTML(
            "afterbegin",
            '<option value="' + iaEsc(c.ia_modelo) + '" selected>' + iaEsc(c.ia_modelo) + "</option>"
          );
        }
      }
      if (instrEl) instrEl.value = c.ia_instrucoes || "";
      if (statusEl) {
        statusEl.textContent = c.ia_ativo && c.ia_api_key_configurada
          ? "✅ Assistente pronto para uso"
          : c.ia_ativo
            ? "⚠️ Ativo, mas falta a chave API"
            : "⏸ Assistente desativado";
      }
      if (aviso) aviso.classList.add("hidden");
    } catch (e) {
      if (aviso) {
        aviso.classList.remove("hidden");
        aviso.textContent = e?.message || "Somente ADMIN pode configurar a IA.";
      }
      if (form) form.querySelectorAll("input,select,textarea,button").forEach(function (el) {
        el.disabled = true;
      });
    }
  }

  async function iaSalvarConfig(e) {
    e?.preventDefault();
    const btn = document.getElementById("iaCfgSalvar");
    try {
      if (btn) btn.disabled = true;
      const payload = {
        ia_ativo: document.getElementById("iaCfgAtivo")?.checked ? "1" : "0",
        ia_modelo: document.getElementById("iaCfgModelo")?.value || "gpt-4o-mini",
        ia_instrucoes: document.getElementById("iaCfgInstrucoes")?.value || "",
        ia_api_key: document.getElementById("iaCfgApiKey")?.value || "",
        ia_api_key_limpar: document.getElementById("iaCfgLimparChave")?.checked ? "1" : "0",
      };
      await iaFetch("/ia/config", { method: "POST", body: JSON.stringify(payload) });
      document.getElementById("iaCfgLimparChave") && (document.getElementById("iaCfgLimparChave").checked = false);
      iaToast("Configurações da IA salvas.", "success");
      await loadIaConfiguracoes();
    } catch (err) {
      iaToast(err?.message || "Erro ao salvar.", "error");
    } finally {
      if (btn) btn.disabled = false;
    }
  }

  function iaSetupEvents() {
    document.getElementById("iaChatForm")?.addEventListener("submit", iaEnviarMensagem);
    document.getElementById("iaChatLimpar")?.addEventListener("click", iaLimparChat);
    document.getElementById("iaConfigForm")?.addEventListener("submit", iaSalvarConfig);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", iaSetupEvents);
  } else {
    iaSetupEvents();
  }

  window.loadIaAssistente = loadIaAssistente;
  window.loadIaConfiguracoes = loadIaConfiguracoes;
})();
