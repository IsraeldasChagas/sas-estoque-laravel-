(function () {
  const cfg = window.MOTOBOY_APP || {};
  const listEl = document.getElementById("mbList");
  const emptyEl = document.getElementById("mbEmpty");
  const pausedEl = document.getElementById("mbPaused");
  const toastEl = document.getElementById("mbToast");
  const dialog = document.getElementById("mbDialog");
  const installBtn = document.getElementById("mbInstall");
  const lockBtn = document.getElementById("mbLock");
  const pinGate = document.getElementById("mbPinGate");
  const appBody = document.getElementById("mbAppBody");
  const pinForm = document.getElementById("mbPinForm");
  const pinInput = document.getElementById("mbPinInput");
  const pinError = document.getElementById("mbPinError");
  const recebendoChk = document.getElementById("mbRecebendo");
  const statusLabel = document.getElementById("mbStatusLabel");
  const statusHint = document.getElementById("mbStatusHint");
  let deferredPrompt = null;
  let busy = false;
  let unlocked = !!cfg.desbloqueado;
  let recebendo = cfg.recebendo !== false;
  let pollTimer = null;
  let lastOfferIds = null; // null = ainda não fez o 1º poll (não notifica)
  let audioCtx = null;

  const INSTALL_KEY = "motoboy_install_id_v1";
  const INSTALL_FLAG_KEY = "motoboy_pwa_installed_v1";

  function money(v) {
    return Number(v || 0).toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
  }

  function toast(msg) {
    if (!toastEl) return;
    toastEl.textContent = msg;
    toastEl.hidden = false;
    setTimeout(() => { toastEl.hidden = true; }, 2800);
  }

  function urlWithId(tpl, id) {
    return String(tpl || "").replace("999999", String(id));
  }

  function escapeHtml(s) {
    return String(s ?? "")
      .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
  }

  async function pedirPermissaoAviso() {
    try {
      if (!("Notification" in window)) return;
      if (Notification.permission === "granted") return;
      if (Notification.permission === "denied") return;
      await Notification.requestPermission();
    } catch (_) {}
  }

  function atualizarBadge(count) {
    const n = Math.max(0, Number(count) || 0);
    try {
      if (n > 0 && navigator.setAppBadge) navigator.setAppBadge(n);
      else if (n === 0 && navigator.clearAppBadge) navigator.clearAppBadge();
    } catch (_) {}
  }

  function tocarAvisoUnico() {
    try {
      if (!audioCtx) {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) return;
        audioCtx = new Ctx();
      }
      if (audioCtx.state === "suspended") audioCtx.resume().catch(() => {});
      const now = audioCtx.currentTime;
      const osc = audioCtx.createOscillator();
      const gain = audioCtx.createGain();
      osc.type = "sine";
      osc.frequency.setValueAtTime(880, now);
      osc.frequency.exponentialRampToValueAtTime(660, now + 0.18);
      gain.gain.setValueAtTime(0.0001, now);
      gain.gain.exponentialRampToValueAtTime(0.22, now + 0.02);
      gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.28);
      osc.connect(gain);
      gain.connect(audioCtx.destination);
      osc.start(now);
      osc.stop(now + 0.3);
    } catch (_) {}
    try {
      if (navigator.vibrate) navigator.vibrate(120);
    } catch (_) {}
  }

  async function avisarEntregas(count, items) {
    atualizarBadge(count);
    if (count <= 0) return;

    const titulo = count === 1 ? "1 entrega disponível" : count + " entregas disponíveis";
    const primeiro = items[0] || {};
    const corpo = primeiro.codigo_publico
      ? ("Pedido " + primeiro.codigo_publico + (primeiro.endereco ? " · " + primeiro.endereco : ""))
      : "Abra o app para aceitar.";

    tocarAvisoUnico();

    try {
      if (!("Notification" in window) || Notification.permission !== "granted") return;
      const opts = {
        body: corpo,
        tag: "motoboy-ofertas",
        renotify: true,
        silent: true, // som nosso (um só); evita som duplo do sistema
        badge: "/assets/delivery/motoboy-icon-192.png?v=20260720-esp",
        icon: "/assets/delivery/motoboy-icon-192.png?v=20260720-esp",
        data: { url: location.href },
      };
      if (navigator.serviceWorker && navigator.serviceWorker.ready) {
        const reg = await navigator.serviceWorker.ready;
        await reg.showNotification(titulo, opts);
      } else {
        new Notification(titulo, opts);
      }
    } catch (_) {}
  }

  function processarAvisos(items) {
    const ids = (items || []).map((it) => String(it.id));
    const count = ids.length;

    if (!recebendo) {
      atualizarBadge(0);
      lastOfferIds = ids;
      return;
    }

    if (lastOfferIds === null) {
      // 1º poll após entrar: só atualiza badge, sem som
      lastOfferIds = ids;
      atualizarBadge(count);
      return;
    }

    const prev = new Set(lastOfferIds);
    const novas = ids.filter((id) => !prev.has(id));
    lastOfferIds = ids;
    atualizarBadge(count);

    if (novas.length > 0) {
      const novosItens = (items || []).filter((it) => novas.includes(String(it.id)));
      avisarEntregas(count, novosItens.length ? novosItens : items);
    } else if (count === 0) {
      atualizarBadge(0);
    }
  }

  function getInstallId() {
    try {
      let id = localStorage.getItem(INSTALL_KEY);
      if (id && /^[a-zA-Z0-9_-]{16,64}$/.test(id)) return id;
      const bytes = new Uint8Array(24);
      crypto.getRandomValues(bytes);
      id = Array.from(bytes, (b) => b.toString(16).padStart(2, "0")).join("");
      localStorage.setItem(INSTALL_KEY, id);
      return id;
    } catch (_) {
      return ("tmp" + Date.now() + Math.random().toString(16).slice(2)).slice(0, 32);
    }
  }

  function setRecebendoUi(value) {
    recebendo = !!value;
    if (recebendoChk) recebendoChk.checked = recebendo;
    if (statusLabel) statusLabel.textContent = recebendo ? "Recebendo entregas" : "Descansando";
    if (statusHint) {
      statusHint.textContent = recebendo
        ? "Desative se for descansar."
        : "Ative quando quiser voltar a receber pedidos.";
    }
    if (pausedEl) pausedEl.hidden = recebendo;
    if (!recebendo) {
      if (emptyEl) emptyEl.hidden = true;
      if (listEl) {
        listEl.hidden = true;
        listEl.innerHTML = "";
      }
      atualizarBadge(0);
    }
  }

  function setUnlocked(value) {
    unlocked = !!value;
    if (pinGate) pinGate.hidden = unlocked;
    if (appBody) appBody.hidden = !unlocked;
    if (lockBtn) lockBtn.hidden = !unlocked;
    if (!unlocked && listEl) {
      listEl.hidden = true;
      listEl.innerHTML = "";
    }
    if (unlocked) {
      lastOfferIds = null;
      pedirPermissaoAviso();
      startPolling();
      poll();
    } else {
      stopPolling();
      lastOfferIds = null;
      atualizarBadge(0);
    }
  }

  async function postJson(url, body) {
    const res = await fetch(url, {
      method: "POST",
      headers: {
        "Accept": "application/json",
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": cfg.csrf || "",
        "X-Requested-With": "XMLHttpRequest",
      },
      credentials: "same-origin",
      body: body ? JSON.stringify(body) : undefined,
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
      const msg = data?.message
        || data?.errors?.pin?.[0]
        || data?.errors?.oferta?.[0]
        || data?.mensagem
        || "Falha na operação.";
      throw new Error(msg);
    }
    return data;
  }

  function render(items, stillRecebendo) {
    setRecebendoUi(stillRecebendo !== false);
    processarAvisos(stillRecebendo === false ? [] : (items || []));
    if (!recebendo) return;
    if (!items.length) {
      emptyEl.hidden = false;
      listEl.hidden = true;
      listEl.innerHTML = "";
      document.title = (cfg.appNome || "Entrega") + " · sem entregas";
      return;
    }
    emptyEl.hidden = true;
    listEl.hidden = false;
    document.title = "(" + items.length + ") " + (cfg.appNome || "Entrega");
    listEl.innerHTML = items.map((it) => `
      <article class="mb-card" data-id="${it.id}">
        <h3>Pedido ${escapeHtml(it.codigo_publico)}</h3>
        <p class="mb-meta">${escapeHtml(it.status_rotulo || "")} · ${escapeHtml(it.loja_nome || "")}</p>
        <p class="mb-addr">${escapeHtml(it.endereco || "Endereço não informado")}</p>
        <ul class="mb-itens">${(it.itens_resumo || []).map((x) => `<li>${escapeHtml(x)}</li>`).join("")}</ul>
        <div class="mb-money-row">
          <span class="mb-frete">Entrega <strong>${money(it.frete_valor)}</strong></span>
          <span class="mb-total">Total ${money(it.total)}</span>
        </div>
        <div class="mb-actions">
          <button type="button" class="mb-btn mb-btn--primary" data-aceitar="${it.id}">Aceitar entrega</button>
          <button type="button" class="mb-btn mb-btn--danger" data-recusar="${it.id}">Não posso</button>
        </div>
      </article>
    `).join("");
  }

  function showAccepted(data) {
    document.getElementById("mbDialogTitle").textContent = "Entrega aceita";
    document.getElementById("mbDialogText").textContent = data.mensagem || "Pedido atribuído a você.";
    const cupom = document.getElementById("mbDialogCupom");
    if (data.cupom_texto) {
      cupom.hidden = false;
      cupom.textContent = data.cupom_texto;
    } else {
      cupom.hidden = true;
      cupom.textContent = "";
    }
    const open = document.getElementById("mbDialogOpen");
    open.href = data.url_entrega || "#";
    open.hidden = !data.url_entrega;
    dialog.showModal();
  }

  async function poll() {
    if (busy || !unlocked) return;
    try {
      const res = await fetch(cfg.ofertasUrl, { headers: { Accept: "application/json" }, credentials: "same-origin" });
      if (res.status === 401) {
        setUnlocked(false);
        return;
      }
      if (!res.ok) throw new Error("poll");
      const data = await res.json();
      render(data.items || [], data.recebendo_entregas !== false);
    } catch (_) {}
  }

  function startPolling() {
    if (pollTimer) return;
    pollTimer = setInterval(poll, 8000);
  }

  function stopPolling() {
    if (pollTimer) {
      clearInterval(pollTimer);
      pollTimer = null;
    }
  }

  pinForm?.addEventListener("submit", async (ev) => {
    ev.preventDefault();
    const pin = String(pinInput?.value || "").replace(/\D/g, "");
    if (pinError) {
      pinError.hidden = true;
      pinError.textContent = "";
    }
    if (pin.length !== 6) {
      if (pinError) {
        pinError.textContent = "Informe o PIN de 6 dígitos.";
        pinError.hidden = false;
      }
      return;
    }
    const btn = pinForm.querySelector('[type="submit"]');
    if (btn) btn.disabled = true;
    try {
      const data = await postJson(cfg.desbloquearUrl, {
        pin,
        install_id: getInstallId(),
      });
      if (pinInput) pinInput.value = "";
      setRecebendoUi(data.recebendo_entregas !== false);
      setUnlocked(true);
      toast(data.mensagem || "Acesso liberado.");
    } catch (err) {
      if (pinError) {
        pinError.textContent = err.message || "PIN incorreto.";
        pinError.hidden = false;
      }
    } finally {
      if (btn) btn.disabled = false;
    }
  });

  lockBtn?.addEventListener("click", async () => {
    try {
      await postJson(cfg.bloquearUrl);
    } catch (_) {}
    setUnlocked(false);
    toast("Saiu do app. Pode voltar com o mesmo PIN neste aparelho.");
  });

  recebendoChk?.addEventListener("change", async () => {
    const next = !!recebendoChk.checked;
    recebendoChk.disabled = true;
    try {
      const data = await postJson(cfg.recebendoUrl, { recebendo: next });
      lastOfferIds = null; // não dispara som ao ligar/desligar
      setRecebendoUi(data.recebendo_entregas !== false);
      toast(data.mensagem || (next ? "Recebendo entregas." : "Entregas pausadas."));
      await poll();
    } catch (err) {
      recebendoChk.checked = !next;
      toast(err.message || "Não foi possível atualizar.");
    } finally {
      recebendoChk.disabled = false;
    }
  });

  listEl?.addEventListener("click", async (ev) => {
    const aceitar = ev.target.closest("[data-aceitar]");
    const recusar = ev.target.closest("[data-recusar]");
    if (!aceitar && !recusar) return;
    if (busy || !unlocked || !recebendo) return;
    busy = true;
    try {
      if (aceitar) {
        const id = aceitar.getAttribute("data-aceitar");
        aceitar.disabled = true;
        const data = await postJson(urlWithId(cfg.aceitarUrlTpl, id));
        showAccepted(data);
        await poll();
      } else if (recusar) {
        const id = recusar.getAttribute("data-recusar");
        recusar.disabled = true;
        const data = await postJson(urlWithId(cfg.recusarUrlTpl, id));
        toast(data.mensagem || "Entrega recusada.");
        await poll();
      }
    } catch (err) {
      toast(err.message || "Erro");
      await poll();
    } finally {
      busy = false;
    }
  });

  document.getElementById("mbDialogCopy")?.addEventListener("click", async () => {
    const txt = document.getElementById("mbDialogCupom")?.textContent || "";
    if (!txt) return;
    try {
      await navigator.clipboard.writeText(txt);
      toast("Cupom copiado.");
    } catch (_) {
      window.prompt("Copie o cupom:", txt);
    }
  });

  function markInstalled() {
    try { localStorage.setItem(INSTALL_FLAG_KEY, "1"); } catch (_) {}
    hideInstall();
  }

  function wasInstalledBefore() {
    try { return localStorage.getItem(INSTALL_FLAG_KEY) === "1"; } catch (_) { return false; }
  }

  function isInstalled() {
    if (wasInstalledBefore()) return true;
    if (window.matchMedia("(display-mode: standalone)").matches) return true;
    if (window.matchMedia("(display-mode: fullscreen)").matches) return true;
    if (window.matchMedia("(display-mode: minimal-ui)").matches) return true;
    if (window.navigator.standalone === true) return true;
    // Android/Chrome: aberto pelo ícone do app instalado
    try {
      const ref = document.referrer || "";
      if (ref.includes("android-app://")) return true;
    } catch (_) {}
    return false;
  }

  function hideInstall() {
    if (!installBtn) return;
    installBtn.hidden = true;
    installBtn.style.display = "none";
    installBtn.setAttribute("aria-hidden", "true");
    deferredPrompt = null;
  }

  function showInstallIfNeeded() {
    if (!installBtn) return;
    if (isInstalled() || wasInstalledBefore() || !deferredPrompt) {
      hideInstall();
      return;
    }
    installBtn.hidden = false;
    installBtn.style.display = "";
    installBtn.removeAttribute("aria-hidden");
  }

  window.addEventListener("beforeinstallprompt", (e) => {
    e.preventDefault();
    // Já instalou antes neste celular: nunca mostrar de novo
    if (isInstalled() || wasInstalledBefore()) {
      hideInstall();
      return;
    }
    deferredPrompt = e;
    showInstallIfNeeded();
  });

  window.addEventListener("appinstalled", () => {
    markInstalled();
  });

  installBtn?.addEventListener("click", async () => {
    if (!deferredPrompt || isInstalled()) {
      markInstalled();
      return;
    }
    deferredPrompt.prompt();
    const choice = await deferredPrompt.userChoice.catch(() => null);
    if (!choice || choice.outcome === "accepted") {
      markInstalled();
    } else {
      hideInstall();
    }
  });

  // Se já está no modo app / já instalou, some o botão e grava a flag
  if (isInstalled()) {
    markInstalled();
  } else {
    hideInstall();
  }

  // getInstalledRelatedApps (quando o navegador suportar)
  try {
    if (navigator.getInstalledRelatedApps) {
      navigator.getInstalledRelatedApps().then((apps) => {
        if (Array.isArray(apps) && apps.length > 0) markInstalled();
      }).catch(() => {});
    }
  } catch (_) {}

  getInstallId();
  setRecebendoUi(recebendo);
  setUnlocked(unlocked);
})();
