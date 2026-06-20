/**
 * SAS IA — agente flutuante (persiste ao navegar entre páginas)
 */
(function () {
  "use strict";

  var sasIaConversationId = null;
  var sasIaEnviando = false;
  var sasIaInited = false;
  var SAS_IA_FLOAT_KEY = "sas-ia-float-open";

  function sasToast(msg, type) {
    var fn = typeof showToast === "function" ? showToast : window.showToast;
    if (typeof fn === "function") fn(msg, type || "info");
  }

  async function sasFetch(path, opts) {
    if (typeof window.fetchJSON === "function") return window.fetchJSON(path, opts);
    throw new Error("fetchJSON não disponível");
  }

  function sasEsc(text) {
    if (typeof escapeHtml === "function") return escapeHtml(text);
    var d = document.createElement("div");
    d.textContent = text == null ? "" : String(text);
    return d.innerHTML;
  }

  function sasFmtMsg(text) {
    return sasEsc(text).replace(/\n/g, "<br>");
  }

  function sasEl(id) {
    return document.getElementById(id);
  }

  function sasIsOpen() {
    var root = sasEl("sasIaFloatRoot");
    return root && root.classList.contains("is-open");
  }

  function sasSetOpen(open) {
    var root = sasEl("sasIaFloatRoot");
    var panel = sasEl("sasIaFloatPanel");
    if (!root || !panel) return;

    root.classList.toggle("is-open", !!open);
    panel.classList.toggle("hidden", !open);

    try {
      sessionStorage.setItem(SAS_IA_FLOAT_KEY, open ? "1" : "0");
    } catch (_) {}

    if (open) {
      sasEl("sasIaChatInput")?.focus();
    }
  }

  function sasFloatOpen() {
    var root = sasEl("sasIaFloatRoot");
    if (!root || root.classList.contains("hidden")) return;
    sasSetOpen(true);
    if (!sasIaInited) {
      sasIaInited = true;
      loadSasIa().catch(function () {});
    }
  }

  function sasFloatMinimize() {
    sasSetOpen(false);
  }

  function sasFloatToggle() {
    if (sasIsOpen()) sasFloatMinimize();
    else sasFloatOpen();
  }

  /** Exibe/oculta widget conforme permissão do usuário logado. */
  function sasFloatSyncPerm(enabled) {
    var root = sasEl("sasIaFloatRoot");
    if (!root) return;
    if (enabled) {
      root.classList.remove("hidden");
      var wasOpen = false;
      try {
        wasOpen = sessionStorage.getItem(SAS_IA_FLOAT_KEY) === "1";
      } catch (_) {}
      if (wasOpen) sasFloatOpen();
    } else {
      root.classList.add("hidden");
      sasSetOpen(false);
    }
  }

  function sasRenderMessages(mensagens) {
    var box = sasEl("sasIaChatMessages");
    if (!box) return;
    if (!mensagens || !mensagens.length) {
      box.innerHTML =
        '<div class="ia-msg ia-msg--bot">' +
          '<div class="ia-msg__avatar">🤖</div>' +
          '<div class="ia-msg__bubble">Olá! Sou o <strong>SAS IA</strong>. Posso consultar estoque, financeiro, compras, logs e manuais do sistema.<br><br>Como posso ajudar?</div>' +
        "</div>";
      return;
    }
    box.innerHTML = mensagens
      .map(function (m) {
        var cls = m.role === "user" ? "ia-msg ia-msg--user" : "ia-msg ia-msg--bot";
        var av = m.role === "user" ? "👤" : "🤖";
        return (
          '<div class="' + cls + '">' +
            '<div class="ia-msg__avatar">' + av + "</div>" +
            '<div class="ia-msg__bubble">' + sasFmtMsg(m.content) + "</div>" +
          "</div>"
        );
      })
      .join("");
    box.scrollTop = box.scrollHeight;
  }

  function sasSetStatusText(text) {
    var el = sasEl("sasIaStatusBar");
    var pageEl = sasEl("sasIaStatusBarPage");
    if (el) el.textContent = text || "";
    if (pageEl) pageEl.textContent = text || "";
  }

  async function sasAtualizarStatus() {
    try {
      var st = await sasFetch("/sas-ia");
      var txt = st.ativo
        ? "Modelo: " + (st.modelo || "—") + " · Restam " + (st.restante_hoje ?? "—") + " perguntas hoje"
        : "IA não configurada — defina OPENAI_API_KEY no servidor.";
      sasSetStatusText(txt);
      return st;
    } catch (e) {
      sasSetStatusText(e?.message || "Erro ao carregar status.");
      return null;
    }
  }

  async function sasCarregarConversas() {
    var ul = sasEl("sasIaConversasList");
    if (!ul) return;
    try {
      var data = await sasFetch("/sas-ia/conversas");
      var lista = data.conversas || [];
      if (!lista.length) {
        ul.innerHTML = '<li class="subtle-text" style="padding:0.5rem;">Nenhuma conversa ainda.</li>';
        return;
      }
      ul.innerHTML = lista
        .map(function (c) {
          var active = sasIaConversationId === c.id ? " sas-ia-conv--active" : "";
          return (
            '<li><button type="button" class="sas-ia-conv-btn' + active + '" data-id="' + c.id + '">' +
              sasEsc(c.titulo || "Conversa #" + c.id) +
            "</button></li>"
          );
        })
        .join("");
      ul.querySelectorAll(".sas-ia-conv-btn").forEach(function (btn) {
        btn.addEventListener("click", function () {
          sasAbrirConversa(parseInt(btn.getAttribute("data-id"), 10));
        });
      });
    } catch (_) {
      ul.innerHTML = '<li class="subtle-text">Erro ao listar conversas.</li>';
    }
  }

  async function sasAbrirConversa(id) {
    if (!id) return;
    try {
      var data = await sasFetch("/sas-ia/conversas/" + id);
      sasIaConversationId = id;
      sasRenderMessages((data.mensagens || []).map(function (m) {
        return { role: m.role, content: m.content };
      }));
      await sasCarregarConversas();
    } catch (e) {
      sasToast(e?.message || "Erro ao abrir conversa.", "error");
    }
  }

  function sasNovaConversa() {
    sasIaConversationId = null;
    sasRenderMessages([]);
    sasCarregarConversas();
  }

  async function sasEnviar(e) {
    e?.preventDefault();
    if (sasIaEnviando) return;
    var input = sasEl("sasIaChatInput");
    var btn = sasEl("sasIaChatEnviar");
    var msg = (input?.value || "").trim();
    if (!msg) return;

    sasFloatOpen();

    sasIaEnviando = true;
    if (btn) btn.disabled = true;

    var box = sasEl("sasIaChatMessages");
    var loadingId = "sas-ia-load-" + Date.now();
    if (box) {
      if (box.querySelector(".ia-msg") && box.textContent.indexOf("Como posso ajudar") >= 0 && !sasIaConversationId) {
        box.innerHTML = "";
      }
      box.insertAdjacentHTML(
        "beforeend",
        '<div class="ia-msg ia-msg--user"><div class="ia-msg__avatar">👤</div><div class="ia-msg__bubble">' +
          sasFmtMsg(msg) + "</div></div>"
      );
      box.insertAdjacentHTML(
        "beforeend",
        '<div class="ia-msg ia-msg--bot" id="' + loadingId + '"><div class="ia-msg__avatar">🤖</div><div class="ia-msg__bubble ia-msg__bubble--loading">Consultando…</div></div>'
      );
      box.scrollTop = box.scrollHeight;
    }
    if (input) input.value = "";

    try {
      var body = { message: msg };
      if (sasIaConversationId) body.conversation_id = sasIaConversationId;
      var data = await sasFetch("/sas-ia/chat", { method: "POST", body: JSON.stringify(body) });
      document.getElementById(loadingId)?.remove();
      if (data.conversation_id) sasIaConversationId = data.conversation_id;
      if (box) {
        box.insertAdjacentHTML(
          "beforeend",
          '<div class="ia-msg ia-msg--bot"><div class="ia-msg__avatar">🤖</div><div class="ia-msg__bubble">' +
            sasFmtMsg(data.reply || "Sem resposta.") + "</div></div>"
        );
        box.scrollTop = box.scrollHeight;
      }
      await sasAtualizarStatus();
      await sasCarregarConversas();
    } catch (err) {
      document.getElementById(loadingId)?.remove();
      sasToast(err?.message || "Erro ao enviar.", "error");
    } finally {
      sasIaEnviando = false;
      if (btn) btn.disabled = false;
      input?.focus();
    }
  }

  async function loadSasIa() {
    if (!sasEl("sasIaChatMessages")) return;
    if (!sasIaInited) {
      sasIaInited = true;
    }
    await sasAtualizarStatus();
    await sasCarregarConversas();
    if (!sasIaConversationId && sasEl("sasIaChatMessages") && !sasEl("sasIaChatMessages").querySelector(".ia-msg")) {
      sasRenderMessages([]);
    }
  }

  async function loadSasIaDocumentos() {
    var tbody = sasEl("sasIaDocsList");
    var aviso = sasEl("sasIaDocsAviso");
    try {
      var data = await sasFetch("/sas-ia/documentos");
      var docs = data.documentos || [];
      if (tbody) {
        tbody.innerHTML = docs.length
          ? docs.map(function (d) {
              return "<tr><td>" + sasEsc(d.titulo) + "</td><td>" + sasEsc(d.tipo) + "</td><td>" + sasEsc(d.updated_at || "") + "</td></tr>";
            }).join("")
          : '<tr><td colspan="3" class="empty-row">Nenhum documento cadastrado.</td></tr>';
      }
      if (aviso) aviso.classList.add("hidden");
    } catch (e) {
      if (aviso) {
        aviso.classList.remove("hidden");
        aviso.textContent = e?.message || "Somente ADMIN pode gerenciar documentos.";
      }
    }
  }

  async function sasSalvarDocumento(e) {
    e?.preventDefault();
    try {
      await sasFetch("/sas-ia/upload-documento", {
        method: "POST",
        body: JSON.stringify({
          titulo: sasEl("sasIaDocTitulo")?.value,
          tipo: sasEl("sasIaDocTipo")?.value || "manual",
          conteudo_texto: sasEl("sasIaDocConteudo")?.value,
        }),
      });
      sasToast("Documento salvo.", "success");
      sasEl("sasIaDocForm")?.reset();
      await loadSasIaDocumentos();
    } catch (err) {
      sasToast(err?.message || "Erro ao salvar documento.", "error");
    }
  }

  function sasSetup() {
    sasEl("sasIaChatForm")?.addEventListener("submit", sasEnviar);
    sasEl("sasIaNovaConversa")?.addEventListener("click", sasNovaConversa);
    sasEl("sasIaDocForm")?.addEventListener("submit", sasSalvarDocumento);
    sasEl("sasIaFloatFab")?.addEventListener("click", sasFloatOpen);
    sasEl("sasIaFloatMinimize")?.addEventListener("click", sasFloatMinimize);
    sasEl("sasIaAbrirFloatBtn")?.addEventListener("click", sasFloatOpen);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", sasSetup);
  } else {
    sasSetup();
  }

  window.loadSasIa = loadSasIa;
  window.loadSasIaDocumentos = loadSasIaDocumentos;
  window.sasIaFloatOpen = sasFloatOpen;
  window.sasIaFloatMinimize = sasFloatMinimize;
  window.sasIaFloatToggle = sasFloatToggle;
  window.sasIaFloatSyncPerm = sasFloatSyncPerm;
})();
