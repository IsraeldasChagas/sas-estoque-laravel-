/**
 * SAS IA — agente flutuante (persiste ao navegar entre páginas)
 */
(function () {
  "use strict";

  var sasIaConversationId = null;
  var sasIaEnviando = false;
  var sasIaInited = false;
  var SAS_IA_FLOAT_KEY = "sas-ia-float-open";
  var SAS_IA_FLOAT_DISMISSED_KEY = "sas-ia-float-dismissed";
  var SAS_IA_EXPAND_KEY = "sas-ia-float-expanded";
  var SAS_IA_AUTO_SPEAK_KEY = "sas-ia-auto-speak";

  var sasIaListening = false;
  var sasIaRecognition = null;
  var sasIaAutoSpeak = false;
  var sasIaSpeaking = false;
  var sasIaMicSupported = false;
  var sasIaVoiceSendPending = false;
  var sasIaVoiceTranscript = "";
  var sasIaSilenceTimer = null;
  var sasIaMicManualStop = false;
  var SAS_IA_SILENCE_MS = 2600;

  var sasIaBranding = { nome: "SAS IA", foto: "", foto_url: null };

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

  function sasStripMarkdown(text) {
    if (text == null) return "";
    var s = String(text);
    s = s.replace(/\*\*([^*]+)\*\*/g, "$1");
    s = s.replace(/__([^_]+)__/g, "$1");
    s = s.replace(/(?<![*\w])\*([^*\n]+)\*(?![*\w])/g, "$1");
    s = s.replace(/(?<![_\w])_([^_\n]+)_(?![_\w])/g, "$1");
    s = s.replace(/\*+/g, "");
    s = s.replace(/`+/g, "");
    s = s.replace(/^[\s]*[-•*]\s+/gm, "");
    return s.trim();
  }

  function sasCleanLaughs(text) {
    if (text == null) return "";
    var s = String(text);
    s = s.replace(/[\u{1F602}\u{1F923}\u{1F606}\u{1F605}\u{1F92A}]/gu, "");
    var laughEnd = /(?:\s*[,.]?\s*(?:kk{2,}|k{3,}|rs{1,4}|rsrs|h[ae]{2,}|hehe|haha|huehue|huahuahua|lol|kkj+|kkkk+|\(risos\)|\(haha\)|\(rs\)))+[.!?…\s]*$/i;
    var prev;
    do {
      prev = s;
      s = s.replace(laughEnd, "").trim();
    } while (s !== prev);
    s = s.replace(/\s+(?:kk|rs)\b[.!?…]*\s*$/gi, "");
    return s.replace(/\s{2,}/g, " ").trim();
  }

  function sasFmtMsg(text) {
    return sasEsc(sasCleanLaughs(sasStripMarkdown(text))).replace(/\n/g, "<br>");
  }

  function sasPlainText(text) {
    if (text == null) return "";
    var s = sasCleanLaughs(sasStripMarkdown(text));
    s = s.replace(/<[^>]+>/g, " ");
    s = s.replace(/R\$\s*/gi, "reais ");
    s = s.replace(/(\d)[.,](\d{3})/g, "$1$2");
    s = s.replace(/\s+([,.!?;:])/g, "$1");
    s = s.replace(/\s+/g, " ");
    return s.trim();
  }

  function sasPauseAfterSegment(text) {
    var t = String(text || "").trim();
    if (!t) return 300;
    var last = t.charAt(t.length - 1);
    if (last === "!") return 460;
    if (last === "?" || last === "？") return 440;
    if (last === "." || last === "…") return 420;
    if (last === ";" || last === ":") return 340;
    if (last === ",") return 300;
    return 320;
  }

  function sasSplitSpeechChunks(text) {
    var plain = sasPlainText(text);
    if (!plain) return [];

    var segments = [];
    var sentences = plain.match(/[^.!?…]+[.!?…]+|[^.!?…]+$/g) || [plain];

    sentences.forEach(function (sentence) {
      sentence = sentence.trim();
      if (!sentence) return;

      if (sentence.length <= 68) {
        segments.push(sentence);
        return;
      }

      var parts = sentence.split(/(?<!\d),(?!\d)\s*|(?<!\d);(?!\d)\s*|(?<!\d): (?!\d)/);
      parts.forEach(function (part, idx) {
        part = part.trim();
        if (!part) return;
        if (idx < parts.length - 1 && !/[,.!?…;:]$/.test(part)) {
          part += ",";
        }
        segments.push(part);
      });
    });

    return segments.map(function (seg) {
      return { text: seg, pause: sasPauseAfterSegment(seg) };
    }).filter(function (item) { return item.text; });
  }

  var sasIaVoiceCache = null;
  var sasIaSpeakQueue = [];
  var sasIaSpeakRunning = false;

  function sasPickFeminineVoice() {
    if (sasIaVoiceCache) return sasIaVoiceCache;
    if (!window.speechSynthesis) return null;

    var voices = window.speechSynthesis.getVoices() || [];
    var pt = voices.filter(function (v) {
      return (v.lang || "").toLowerCase().replace("_", "-").indexOf("pt") === 0;
    });
    if (!pt.length) return null;

    var hints = [
      "maria",
      "francisca",
      "luciana",
      "vitória",
      "vitoria",
      "fernanda",
      "google português do brasil",
      "português do brasil",
      "brazil",
      "female",
      "feminina",
    ];

    var natural = pt.filter(function (v) {
      var n = (v.name || "").toLowerCase();
      return n.indexOf("natural") >= 0 || n.indexOf("online") >= 0 || n.indexOf("neural") >= 0;
    });
    var pool = natural.length ? natural : pt;

    for (var i = 0; i < hints.length; i++) {
      var found = pool.find(function (v) {
        return (v.name || "").toLowerCase().indexOf(hints[i]) >= 0;
      });
      if (found) {
        sasIaVoiceCache = found;
        return found;
      }
    }

    sasIaVoiceCache = pool[0];
    return sasIaVoiceCache;
  }

  function sasSetMicStatus(text) {
    var status = sasEl("sasIaMicStatus");
    if (status) status.textContent = text || "";
  }

  function sasClearSilenceTimer() {
    if (sasIaSilenceTimer) {
      clearTimeout(sasIaSilenceTimer);
      sasIaSilenceTimer = null;
    }
  }

  function sasScheduleVoiceSend() {
    sasClearSilenceTimer();
    if (!sasIaVoiceSendPending) return;
    sasIaSilenceTimer = setTimeout(function () {
      sasMicFinishAndSend();
    }, SAS_IA_SILENCE_MS);
  }

  function sasMicStopRecognition() {
    sasIaMicManualStop = true;
    sasClearSilenceTimer();
    try {
      sasIaRecognition.stop();
    } catch (_) {}
    sasIaListening = false;
  }

  function sasMicFinishAndSend() {
    sasClearSilenceTimer();
    if (!sasIaVoiceSendPending || sasIaEnviando) return;

    sasMicStopRecognition();

    var input = sasEl("sasIaChatInput");
    var text = (input?.value || sasIaVoiceTranscript || "").trim();
    sasIaVoiceTranscript = "";
    sasIaVoiceSendPending = false;
    sasSetMicStatus("");

    if (!text) {
      sasToast("Não captei nada. Pode tentar de novo?", "info");
      sasUpdateAudioUi();
      return;
    }

    if (input) input.value = text;
    sasUpdateAudioUi();
    sasEnviar();
  }

  function sasInitAudio() {
    try {
      sasIaAutoSpeak = localStorage.getItem(SAS_IA_AUTO_SPEAK_KEY) === "1";
    } catch (_) {}

    var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    sasIaMicSupported = !!SpeechRecognition && !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);

    if (SpeechRecognition) {
      sasIaRecognition = new SpeechRecognition();
      sasIaRecognition.lang = "pt-BR";
      sasIaRecognition.continuous = true;
      sasIaRecognition.interimResults = true;
      sasIaRecognition.maxAlternatives = 1;

      sasIaRecognition.onstart = function () {
        sasIaListening = true;
        sasIaMicManualStop = false;
        sasSetMicStatus("Pode falar… envio quando você terminar");
        sasUpdateAudioUi();
      };

      sasIaRecognition.onend = function () {
        sasIaListening = false;
        if (sasIaMicManualStop) {
          sasIaMicManualStop = false;
          sasUpdateAudioUi();
          return;
        }
        if (sasIaVoiceSendPending && !sasIaSilenceTimer) {
          var pending = (sasEl("sasIaChatInput")?.value || sasIaVoiceTranscript || "").trim();
          if (pending) {
            sasMicFinishAndSend();
            return;
          }
          try {
            sasIaRecognition.start();
            sasIaListening = true;
          } catch (_) {}
        }
        sasUpdateAudioUi();
      };

      sasIaRecognition.onerror = function (ev) {
        sasIaListening = false;
        sasClearSilenceTimer();
        if (ev.error === "not-allowed") {
          sasIaVoiceSendPending = false;
          sasIaVoiceTranscript = "";
          sasSetMicStatus("");
          sasToast("Permita o acesso ao microfone para usar voz.", "warning");
        } else if (ev.error === "no-speech") {
          if (sasIaVoiceSendPending) sasScheduleVoiceSend();
        } else if (ev.error !== "aborted") {
          sasIaVoiceSendPending = false;
          sasIaVoiceTranscript = "";
          sasSetMicStatus("");
          sasToast("Erro no reconhecimento de voz: " + (ev.error || "desconhecido"), "error");
        }
        sasUpdateAudioUi();
      };

      sasIaRecognition.onresult = function (ev) {
        var interim = "";
        var newFinal = "";
        for (var i = ev.resultIndex; i < ev.results.length; i++) {
          var chunk = ev.results[i][0]?.transcript || "";
          if (ev.results[i].isFinal) newFinal += chunk;
          else interim += chunk;
        }

        if (newFinal) sasIaVoiceTranscript += newFinal;

        var input = sasEl("sasIaChatInput");
        if (input) {
          input.value = (sasIaVoiceTranscript + interim).trim();
        }

        if (interim) {
          sasSetMicStatus("Ouvindo… pode continuar falando");
        } else if (sasIaVoiceTranscript.trim()) {
          sasSetMicStatus("Pausou… aguardando você terminar");
        }

        if (sasIaVoiceSendPending) sasScheduleVoiceSend();
      };
    }

    sasUpdateAudioUi();
  }

  function sasUpdateAudioUi() {
    var micBtn = sasEl("sasIaMicBtn");
    var autoBtn = sasEl("sasIaAutoSpeakBtn");
    var stopBtn = sasEl("sasIaStopSpeakBtn");
    var status = sasEl("sasIaMicStatus");

    if (micBtn) {
      micBtn.disabled = !sasIaMicSupported || sasIaEnviando;
      micBtn.classList.toggle("is-active", sasIaListening);
      micBtn.title = sasIaMicSupported
        ? (sasIaListening ? "Terminar e enviar" : "Falar pergunta (microfone)")
        : "Microfone não suportado neste navegador (use Chrome ou Edge)";
      micBtn.textContent = sasIaListening ? "⏹" : "🎤";
    }

    if (autoBtn) {
      autoBtn.classList.toggle("is-on", sasIaAutoSpeak);
      autoBtn.textContent = sasIaAutoSpeak ? "🔊" : "🔇";
      autoBtn.title = sasIaAutoSpeak
        ? "Respostas serão lidas em voz alta (clique para desativar)"
        : "Ler respostas em voz alta";
    }

    if (stopBtn) {
      stopBtn.classList.toggle("hidden", !sasIaSpeaking);
    }

    if (status) {
      status.classList.toggle("hidden", !sasIaListening && !sasIaVoiceSendPending);
    }
  }

  function sasStopSpeak() {
    sasIaSpeakQueue = [];
    sasIaSpeakRunning = false;
    if (window.speechSynthesis) {
      window.speechSynthesis.cancel();
    }
    sasIaSpeaking = false;
    sasUpdateAudioUi();
  }

  function sasSpeakChunk(text, voice, onDone) {
    var utter = new SpeechSynthesisUtterance(text);
    utter.lang = "pt-BR";
    utter.rate = 0.88;
    utter.pitch = 1.03;
    if (voice) utter.voice = voice;
    utter.onend = function () { onDone?.(); };
    utter.onerror = function () { onDone?.(); };
    window.speechSynthesis.speak(utter);
  }

  function sasSpeakNextChunk() {
    if (!sasIaSpeakQueue.length) {
      sasIaSpeakRunning = false;
      sasIaSpeaking = false;
      sasUpdateAudioUi();
      return;
    }
    var item = sasIaSpeakQueue.shift();
    var text = typeof item === "string" ? item : item.text;
    var pause = typeof item === "object" && item.pause ? item.pause : sasPauseAfterSegment(text);
    if (!text) {
      sasSpeakNextChunk();
      return;
    }
    sasSpeakChunk(text, sasPickFeminineVoice(), function () {
      setTimeout(sasSpeakNextChunk, pause);
    });
  }

  function sasSpeak(text) {
    if (!window.speechSynthesis) return;
    var chunks = sasSplitSpeechChunks(text);
    if (!chunks.length) return;

    sasStopSpeak();
    sasIaSpeakQueue = chunks.slice();
    sasIaSpeakRunning = true;
    sasIaSpeaking = true;
    sasUpdateAudioUi();
    sasSpeakNextChunk();
  }

  function sasToggleMic() {
    if (!sasIaMicSupported || !sasIaRecognition) {
      sasToast("Seu navegador não suporta entrada por voz. Use Chrome ou Edge.", "warning");
      return;
    }
    if (sasIaEnviando) return;

    sasFloatOpen();

    if (sasIaListening || sasIaVoiceSendPending) {
      sasMicFinishAndSend();
      return;
    }

    sasStopSpeak();
    sasIaVoiceTranscript = "";
    sasClearSilenceTimer();
    var input = sasEl("sasIaChatInput");
    if (input) input.value = "";
    sasIaVoiceSendPending = true;
    try {
      sasIaRecognition.start();
    } catch (e) {
      sasIaVoiceSendPending = false;
      sasToast("Não foi possível iniciar o microfone.", "error");
    }
  }

  function sasToggleAutoSpeak() {
    sasIaAutoSpeak = !sasIaAutoSpeak;
    try {
      localStorage.setItem(SAS_IA_AUTO_SPEAK_KEY, sasIaAutoSpeak ? "1" : "0");
    } catch (_) {}
    if (!sasIaAutoSpeak) sasStopSpeak();
    sasUpdateAudioUi();
    sasToast(
      sasIaAutoSpeak ? "Respostas serão lidas em voz alta." : "Leitura em voz alta desativada.",
      "info"
    );
  }

  function sasEl(id) {
    return document.getElementById(id);
  }

  function sasAgentNome() {
    return (sasIaBranding.nome || "SAS IA").trim() || "SAS IA";
  }

  function sasAgentFotoUrl() {
    return sasIaBranding.foto_url || (sasIaBranding.foto ? sasUsuarioFotoUrl(sasIaBranding.foto) : null);
  }

  async function sasLoadBranding() {
    try {
      var data = await sasFetch("/sas-ia/config");
      sasIaBranding.nome = data.nome || "SAS IA";
      sasIaBranding.foto = data.foto || "";
      sasIaBranding.foto_url = sasIaBranding.foto ? sasUsuarioFotoUrl(sasIaBranding.foto) : null;
      sasApplyBrandingUi();
      return data;
    } catch (_) {
      sasApplyBrandingUi();
      return null;
    }
  }

  function sasApplyBrandingUi() {
    var nome = sasAgentNome();
    var fotoUrl = sasAgentFotoUrl();
    var title = sasEl("sasIaHeaderTitle");
    if (title) title.textContent = nome;
    var pageTitle = document.querySelector("#sasIaSection .ia-section-head h2");
    if (pageTitle) pageTitle.textContent = "🧠 " + nome;
    var fab = sasEl("sasIaFloatFab");
    if (fab) {
      fab.title = "Abrir " + nome;
      fab.setAttribute("aria-label", "Abrir " + nome);
      if (fotoUrl) {
        fab.innerHTML = '<img src="' + sasEsc(fotoUrl) + '" alt="" class="sas-ia-float__fab-img" />';
      } else {
        fab.innerHTML = '<span class="sas-ia-float__fab-icon" aria-hidden="true">🧠</span>';
      }
    }
    var box = sasEl("sasIaChatMessages");
    if (box && !sasIaConversationId) {
      var welcomeOnly = box.querySelector(".ia-msg--bot") && !box.querySelector(".ia-msg--user");
      if (welcomeOnly || !box.querySelector(".ia-msg")) {
        sasRenderMessages([]);
      }
    }
  }

  function sasWelcomeHtml() {
    var nome = sasAgentNome();
    return (
      "E aí! Sou a " + nome + ", tô aqui pra te ajudar no dia a dia 😊" +
      "<br><br>Me pergunta sobre estoque, financeiro, compras, RH… o que precisar!"
    );
  }
  function sasUsuarioFotoUrl(path) {
    if (!path || typeof path !== "string") return null;
    var api = (window.APP_CONFIG && window.APP_CONFIG.API_URL) || "https://api.gruposaborparaense.com.br/api";
    var base = api.replace(/\/api\/?$/, "") || "https://api.gruposaborparaense.com.br";
    var p = path.replace(/^\//, "");
    if (!p) return null;
    if (p.indexOf("storage/") === 0) return base + "/" + p;
    if (p.indexOf("usuarios/") === 0 && p.indexOf("uploads/") !== 0) return base + "/storage/" + p;
    return base + "/" + p;
  }

  function sasLoggedUser() {
    if (typeof window.getUser === "function") return window.getUser();
    return null;
  }

  function sasUserAvatarHtml() {
    var u = sasLoggedUser();
    var nome = (u && (u.nome || u.email)) || "Você";
    var url = u ? sasUsuarioFotoUrl(u.foto || u.foto_path) : null;
    if (url) {
      return (
        '<div class="ia-msg__avatar ia-msg__avatar--photo">' +
          '<img src="' + sasEsc(url) + '" alt="' + sasEsc(nome) + '" class="ia-msg__avatar-img" />' +
        "</div>"
      );
    }
    var parts = String(nome).trim().split(/\s+/).filter(Boolean);
    var initials = (parts[0] ? parts[0][0] : "") + (parts[1] ? parts[1][0] : "");
    initials = (initials || "?").toUpperCase();
    return (
      '<div class="ia-msg__avatar ia-msg__avatar--initials" aria-label="' + sasEsc(nome) + '">' +
        sasEsc(initials) +
      "</div>"
    );
  }

  function sasBotAvatarHtml() {
    var url = sasAgentFotoUrl();
    var nome = sasAgentNome();
    if (url) {
      return (
        '<div class="ia-msg__avatar ia-msg__avatar--photo ia-msg__avatar--agent">' +
          '<img src="' + sasEsc(url) + '" alt="' + sasEsc(nome) + '" class="ia-msg__avatar-img" />' +
        "</div>"
      );
    }
    return '<div class="ia-msg__avatar ia-msg__avatar--bot" aria-hidden="true">🧠</div>';
  }

  function sasIsExpanded() {
    var root = sasEl("sasIaFloatRoot");
    return root && root.classList.contains("is-expanded");
  }

  function sasFloatResetLayout() {
    var root = sasEl("sasIaFloatRoot");
    var panel = sasEl("sasIaFloatPanel");
    if (root) {
      root.style.left = "";
      root.style.top = "";
      root.style.width = "";
      root.style.height = "";
      root.style.maxWidth = "";
      root.style.maxHeight = "";
    }
    if (panel) {
      panel.style.width = "";
      panel.style.height = "";
      panel.style.maxHeight = "";
    }
  }

  function sasSetExpanded(expanded) {
    var root = sasEl("sasIaFloatRoot");
    var panel = sasEl("sasIaFloatPanel");
    var btn = sasEl("sasIaFloatExpand");
    if (!root) return;

    root.classList.toggle("is-expanded", !!expanded);
    sasFloatResetLayout();
    if (btn) {
      btn.textContent = expanded ? "⊟" : "⛶";
      btn.title = expanded ? "Restaurar tamanho" : "Expandir";
      btn.setAttribute("aria-label", expanded ? "Restaurar tamanho" : "Expandir");
    }

    try {
      sessionStorage.setItem(SAS_IA_EXPAND_KEY, expanded ? "1" : "0");
    } catch (_) {}
  }

  function sasFloatToggleExpand() {
    sasSetExpanded(!sasIsExpanded());
  }

  function sasAtualizarExcluirBtn() {
    var btn = sasEl("sasIaExcluirConversa");
    if (!btn) return;
    if (sasIaConversationId) {
      btn.classList.remove("hidden");
    } else {
      btn.classList.add("hidden");
    }
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

    if (!open) {
      sasSetExpanded(false);
      sasFloatResetLayout();
    }

    try {
      sessionStorage.setItem(SAS_IA_FLOAT_KEY, open ? "1" : "0");
    } catch (_) {}

    if (open) {
      sasEl("sasIaChatInput")?.focus();
      if (typeof window.sasIaRefreshAvatars === "function") window.sasIaRefreshAvatars();
    }
  }

  function sasFloatOpen() {
    var root = sasEl("sasIaFloatRoot");
    if (!root) return;
    root.classList.remove("hidden");
    try {
      sessionStorage.setItem(SAS_IA_FLOAT_DISMISSED_KEY, "0");
    } catch (_) {}
    var wasExpanded = false;
    try {
      wasExpanded = sessionStorage.getItem(SAS_IA_EXPAND_KEY) === "1";
    } catch (_) {}
    sasSetExpanded(wasExpanded);
    sasSetOpen(true);
    if (!sasIaInited) {
      sasIaInited = true;
      loadSasIa().catch(function () {});
    }
  }

  function sasFloatClose() {
    sasStopSpeak();
    if (sasIaListening) sasMicStopRecognition();
    sasSetExpanded(false);
    sasSetOpen(false);
    sasFloatResetLayout();
    var root = sasEl("sasIaFloatRoot");
    if (root) root.classList.add("hidden");
    try {
      sessionStorage.setItem(SAS_IA_FLOAT_KEY, "0");
      sessionStorage.setItem(SAS_IA_FLOAT_DISMISSED_KEY, "1");
    } catch (_) {}
  }

  function sasFloatMinimize() {
    sasSetExpanded(false);
    sasSetOpen(false);
    sasFloatResetLayout();
  }

  function sasFloatToggle() {
    if (sasIsOpen()) sasFloatMinimize();
    else sasFloatOpen();
  }

  /** Exibe/oculta widget conforme permissão do usuário logado. */
  function sasFloatSyncPerm(enabled) {
    var root = sasEl("sasIaFloatRoot");
    if (!root) return;

    var loginEl = document.getElementById("loginOverlay");
    var loggedIn = loginEl ? loginEl.classList.contains("hidden") : !!enabled;
    if (!loggedIn) enabled = false;

    if (enabled) {
      var dismissed = false;
      try {
        dismissed = sessionStorage.getItem(SAS_IA_FLOAT_DISMISSED_KEY) === "1";
      } catch (_) {}
      if (!dismissed) {
        root.classList.remove("hidden");
        sasLoadBranding().catch(function () {});
        var wasOpen = false;
        try {
          wasOpen = sessionStorage.getItem(SAS_IA_FLOAT_KEY) === "1";
        } catch (_) {}
        if (wasOpen) sasFloatOpen();
      } else {
        root.classList.add("hidden");
        sasSetOpen(false);
      }
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
          sasBotAvatarHtml() +
          '<div class="ia-msg__bubble">' + sasWelcomeHtml() + "</div>" +
        "</div>";
      return;
    }
    box.innerHTML = mensagens
      .map(function (m) {
        var cls = m.role === "user" ? "ia-msg ia-msg--user" : "ia-msg ia-msg--bot";
        var av = m.role === "user" ? sasUserAvatarHtml() : sasBotAvatarHtml();
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
      sasAtualizarExcluirBtn();
      await sasCarregarConversas();
    } catch (e) {
      sasToast(e?.message || "Erro ao abrir conversa.", "error");
    }
  }

  function sasNovaConversa() {
    sasIaConversationId = null;
    sasRenderMessages([]);
    sasAtualizarExcluirBtn();
    sasCarregarConversas();
  }

  async function sasExcluirConversa() {
    if (!sasIaConversationId || sasIaEnviando) return;
    var id = sasIaConversationId;
    if (!window.confirm("Excluir esta conversa? Esta ação não pode ser desfeita.")) return;

    try {
      await sasFetch("/sas-ia/conversas/" + id, { method: "DELETE" });
      sasToast("Conversa excluída.", "success");
      sasNovaConversa();
    } catch (e) {
      sasToast(e?.message || "Erro ao excluir conversa.", "error");
    }
  }

  async function sasEnviar(e) {
    e?.preventDefault();
    if (sasIaEnviando) return;
    if (sasIaListening || sasIaVoiceSendPending) {
      sasMicFinishAndSend();
      return;
    }
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
        '<div class="ia-msg ia-msg--user">' + sasUserAvatarHtml() + '<div class="ia-msg__bubble">' +
          sasFmtMsg(msg) + "</div></div>"
      );
      box.insertAdjacentHTML(
        "beforeend",
        '<div class="ia-msg ia-msg--bot" id="' + loadingId + '">' + sasBotAvatarHtml() + '<div class="ia-msg__bubble ia-msg__bubble--loading">Deixa eu dar uma olhada…</div></div>'
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
      sasAtualizarExcluirBtn();
      if (box) {
        box.insertAdjacentHTML(
          "beforeend",
          '<div class="ia-msg ia-msg--bot">' + sasBotAvatarHtml() + '<div class="ia-msg__bubble">' +
            sasFmtMsg(data.reply || "Sem resposta.") + "</div></div>"
        );
        box.scrollTop = box.scrollHeight;
      }
      if (sasIaAutoSpeak && data.reply) {
        sasSpeak(data.reply);
      }
      await sasAtualizarStatus();
      await sasCarregarConversas();
    } catch (err) {
      document.getElementById(loadingId)?.remove();
      sasToast(err?.message || "Erro ao enviar.", "error");
    } finally {
      sasIaEnviando = false;
      if (btn) btn.disabled = false;
      sasUpdateAudioUi();
      input?.focus();
    }
  }

  async function loadSasIa() {
    if (!sasEl("sasIaChatMessages")) return;
    if (!sasIaInited) {
      sasIaInited = true;
    }
    await sasLoadBranding();
    await sasAtualizarStatus();
    await sasCarregarConversas();
    sasAtualizarExcluirBtn();
    if (!sasIaConversationId && sasEl("sasIaChatMessages") && !sasEl("sasIaChatMessages").querySelector(".ia-msg")) {
      sasRenderMessages([]);
    }
  }

  function sasCfgAtualizarPreview(fotoPath) {
    var preview = sasEl("sasIaCfgFotoPreview");
    if (!preview) return;
    var url = fotoPath ? sasUsuarioFotoUrl(fotoPath) : null;
    if (url) {
      preview.className = "sas-ia-cfg-foto__preview ia-msg__avatar ia-msg__avatar--photo ia-msg__avatar--agent";
      preview.innerHTML = '<img src="' + sasEsc(url) + '" alt="" class="ia-msg__avatar-img" />';
    } else {
      preview.className = "sas-ia-cfg-foto__preview ia-msg__avatar ia-msg__avatar--bot";
      preview.innerHTML = "🧠";
    }
  }

  async function loadSasIaConfiguracoes() {
    var aviso = sasEl("sasIaCfgAviso");
    var form = sasEl("sasIaCfgForm");
    try {
      var data = await sasFetch("/sas-ia/config");
      var pode = !!data.pode_editar;
      if (aviso) aviso.classList.toggle("hidden", pode);
      if (aviso && !pode) aviso.textContent = "Somente ADMIN pode configurar o assistente.";
      var nomeEl = sasEl("sasIaCfgNome");
      if (nomeEl) nomeEl.value = data.nome || "";
      sasCfgAtualizarPreview(data.foto || "");
      if (form) {
        form.querySelectorAll("input,button").forEach(function (el) {
          if (el.id === "sasIaCfgRemoverFoto") el.disabled = !pode;
          else if (el.type === "file") el.disabled = !pode;
          else if (el.tagName === "BUTTON") el.disabled = !pode;
          else el.readOnly = !pode;
        });
      }
      var rm = sasEl("sasIaCfgRemoverFoto");
      if (rm) rm.checked = false;
    } catch (e) {
      if (aviso) {
        aviso.classList.remove("hidden");
        aviso.textContent = e?.message || "Erro ao carregar configurações.";
      }
    }
  }

  async function sasSalvarConfig(e) {
    e?.preventDefault();
    var btn = sasEl("sasIaCfgSalvar");
    try {
      if (btn) btn.disabled = true;
      await sasFetch("/sas-ia/config", {
        method: "POST",
        body: JSON.stringify({
          nome: sasEl("sasIaCfgNome")?.value || "",
          remover_foto: sasEl("sasIaCfgRemoverFoto")?.checked ? "1" : "0",
        }),
      });
      var fileInput = sasEl("sasIaCfgFotoInput");
      var file = fileInput?.files?.[0];
      if (file) {
        var fd = new FormData();
        fd.append("foto", file);
        if (typeof window.fetchForm === "function") {
          await window.fetchForm("/sas-ia/upload-foto", "POST", fd);
        } else {
          throw new Error("Upload indisponível");
        }
        if (fileInput) fileInput.value = "";
      }
      await sasLoadBranding();
      await loadSasIaConfiguracoes();
      sasToast("Configurações do assistente salvas.", "success");
    } catch (err) {
      sasToast(err?.message || "Erro ao salvar.", "error");
    } finally {
      if (btn) btn.disabled = false;
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
              var origem = d.tem_arquivo ? "📎 Arquivo" : "✍️ Texto";
              var tam = d.tamanho_texto ? d.tamanho_texto + " caracteres" : "—";
              return (
                "<tr data-id=\"" + d.id + "\">" +
                  "<td>" + sasEsc(d.titulo) + "</td>" +
                  "<td>" + sasEsc(d.tipo) + "</td>" +
                  "<td>" + origem + "</td>" +
                  "<td>" + sasEsc(tam) + "</td>" +
                  "<td>" + sasEsc(d.updated_at || "") + "</td>" +
                  "<td><button type=\"button\" class=\"btn danger sas-ia-doc-del\" data-id=\"" + d.id + "\" title=\"Remover\">🗑</button></td>" +
                "</tr>"
              );
            }).join("")
          : '<tr><td colspan="6" class="empty-row">Nenhum documento cadastrado.</td></tr>';
      }
      if (aviso) aviso.classList.add("hidden");
    } catch (e) {
      if (aviso) {
        aviso.classList.remove("hidden");
        aviso.textContent = e?.message || "Somente ADMIN pode gerenciar documentos.";
      }
    }
  }

  async function sasExcluirDocumento(id) {
    if (!id || !window.confirm("Remover este documento da base de conhecimento da IA?")) return;
    try {
      await sasFetch("/sas-ia/documentos/" + id, { method: "DELETE" });
      sasToast("Documento removido.", "success");
      await loadSasIaDocumentos();
    } catch (e) {
      sasToast(e?.message || "Erro ao excluir.", "error");
    }
  }

  async function sasSalvarDocumento(e) {
    e?.preventDefault();
    var titulo = (sasEl("sasIaDocTitulo")?.value || "").trim();
    var texto = (sasEl("sasIaDocConteudo")?.value || "").trim();
    var file = sasEl("sasIaDocArquivo")?.files?.[0];
    if (!titulo) {
      sasToast("Informe o título.", "warning");
      return;
    }
    if (!file && !texto) {
      sasToast("Envie um arquivo ou cole o texto.", "warning");
      return;
    }
    if (file && file.size > 5 * 1024 * 1024) {
      sasToast("Arquivo muito grande (máx. 5 MB).", "warning");
      return;
    }
    try {
      var fd = new FormData();
      fd.append("titulo", titulo);
      fd.append("tipo", sasEl("sasIaDocTipo")?.value || "manual");
      if (texto) fd.append("conteudo_texto", texto);
      if (file) fd.append("arquivo", file);
      if (typeof window.fetchForm === "function") {
        await window.fetchForm("/sas-ia/upload-documento", "POST", fd);
      } else {
        throw new Error("Upload indisponível");
      }
      sasToast("Documento salvo — a IA já pode consultar.", "success");
      sasEl("sasIaDocForm")?.reset();
      await loadSasIaDocumentos();
    } catch (err) {
      sasToast(err?.message || "Erro ao salvar documento.", "error");
    }
  }

  function sasSetup() {
    sasInitAudio();
    if (window.speechSynthesis) {
      window.speechSynthesis.onvoiceschanged = function () {
        sasIaVoiceCache = null;
        sasPickFeminineVoice();
        sasUpdateAudioUi();
      };
    }
    sasEl("sasIaChatForm")?.addEventListener("submit", sasEnviar);
    sasEl("sasIaMicBtn")?.addEventListener("click", sasToggleMic);
    sasEl("sasIaAutoSpeakBtn")?.addEventListener("click", sasToggleAutoSpeak);
    sasEl("sasIaStopSpeakBtn")?.addEventListener("click", sasStopSpeak);
    sasEl("sasIaNovaConversa")?.addEventListener("click", sasNovaConversa);
    sasEl("sasIaExcluirConversa")?.addEventListener("click", sasExcluirConversa);
    sasEl("sasIaDocForm")?.addEventListener("submit", sasSalvarDocumento);
    sasEl("sasIaDocsList")?.addEventListener("click", function (ev) {
      var btn = ev.target.closest(".sas-ia-doc-del");
      if (!btn) return;
      sasExcluirDocumento(parseInt(btn.getAttribute("data-id"), 10));
    });
    sasEl("sasIaCfgForm")?.addEventListener("submit", sasSalvarConfig);
    sasEl("sasIaCfgFotoInput")?.addEventListener("change", function () {
      var file = sasEl("sasIaCfgFotoInput")?.files?.[0];
      if (!file) return;
      var url = URL.createObjectURL(file);
      var preview = sasEl("sasIaCfgFotoPreview");
      if (preview) {
        preview.className = "sas-ia-cfg-foto__preview ia-msg__avatar ia-msg__avatar--photo ia-msg__avatar--agent";
        preview.innerHTML = '<img src="' + url + '" alt="" class="ia-msg__avatar-img" />';
      }
    });
    sasEl("sasIaFloatFab")?.addEventListener("click", sasFloatOpen);
    sasEl("sasIaFloatExpand")?.addEventListener("click", sasFloatToggleExpand);
    sasEl("sasIaFloatMinimize")?.addEventListener("click", sasFloatMinimize);
    sasEl("sasIaFloatClose")?.addEventListener("click", sasFloatClose);
    sasEl("sasIaAbrirFloatBtn")?.addEventListener("click", sasFloatOpen);
    sasFloatSyncPerm(false);
    window.addEventListener("resize", function () {
      if (!sasIsOpen()) sasFloatResetLayout();
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", sasSetup);
  } else {
    sasSetup();
  }

  window.loadSasIa = loadSasIa;
  window.loadSasIaDocumentos = loadSasIaDocumentos;
  window.loadSasIaConfiguracoes = loadSasIaConfiguracoes;
  window.sasIaLoadBranding = sasLoadBranding;
  window.sasIaFloatOpen = sasFloatOpen;
  window.sasIaFloatMinimize = sasFloatMinimize;
  window.sasIaFloatClose = sasFloatClose;
  window.sasIaFloatToggle = sasFloatToggle;
  window.sasIaFloatSyncPerm = sasFloatSyncPerm;
  window.sasIaRefreshAvatars = function () {
    var box = sasEl("sasIaChatMessages");
    if (!box) return;
    box.querySelectorAll(".ia-msg--user .ia-msg__avatar").forEach(function (el) {
      el.outerHTML = sasUserAvatarHtml();
    });
  };
})();
