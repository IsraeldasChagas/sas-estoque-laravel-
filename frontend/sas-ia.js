/**
 * SAS IA — agente inteligente (OpenAI + ferramentas internas)
 */
(function () {
  "use strict";

  var sasIaConversationId = null;
  var sasIaEnviando = false;

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

  function sasRenderMessages(mensagens) {
    var box = document.getElementById("sasIaChatMessages");
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

  async function sasAtualizarStatus() {
    var el = document.getElementById("sasIaStatusBar");
    try {
      var st = await sasFetch("/sas-ia");
      if (el) {
        el.textContent = st.ativo
          ? "Modelo: " + (st.modelo || "—") + " · Restam " + (st.restante_hoje ?? "—") + " perguntas hoje"
          : "IA não configurada — defina OPENAI_API_KEY no servidor.";
      }
      return st;
    } catch (e) {
      if (el) el.textContent = e?.message || "Erro ao carregar status.";
      return null;
    }
  }

  async function sasCarregarConversas() {
    var ul = document.getElementById("sasIaConversasList");
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
    var input = document.getElementById("sasIaChatInput");
    var btn = document.getElementById("sasIaChatEnviar");
    var msg = (input?.value || "").trim();
    if (!msg) return;

    sasIaEnviando = true;
    if (btn) btn.disabled = true;

    var box = document.getElementById("sasIaChatMessages");
    var tempUser =
      '<div class="ia-msg ia-msg--user"><div class="ia-msg__avatar">👤</div><div class="ia-msg__bubble">' +
      sasFmtMsg(msg) + "</div></div>";
    var loadingId = "sas-ia-load-" + Date.now();
    if (box) {
      if (box.querySelector(".ia-msg") && box.textContent.indexOf("Como posso ajudar") >= 0 && !sasIaConversationId) {
        box.innerHTML = "";
      }
      box.insertAdjacentHTML("beforeend", tempUser);
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
    sasRenderMessages([]);
    await sasAtualizarStatus();
    await sasCarregarConversas();
  }

  async function loadSasIaDocumentos() {
    var tbody = document.getElementById("sasIaDocsList");
    var aviso = document.getElementById("sasIaDocsAviso");
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
          titulo: document.getElementById("sasIaDocTitulo")?.value,
          tipo: document.getElementById("sasIaDocTipo")?.value || "manual",
          conteudo_texto: document.getElementById("sasIaDocConteudo")?.value,
        }),
      });
      sasToast("Documento salvo.", "success");
      document.getElementById("sasIaDocForm")?.reset();
      await loadSasIaDocumentos();
    } catch (err) {
      sasToast(err?.message || "Erro ao salvar documento.", "error");
    }
  }

  function sasSetup() {
    document.getElementById("sasIaChatForm")?.addEventListener("submit", sasEnviar);
    document.getElementById("sasIaNovaConversa")?.addEventListener("click", sasNovaConversa);
    document.getElementById("sasIaDocForm")?.addEventListener("submit", sasSalvarDocumento);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", sasSetup);
  } else {
    sasSetup();
  }

  window.loadSasIa = loadSasIa;
  window.loadSasIaDocumentos = loadSasIaDocumentos;
})();
