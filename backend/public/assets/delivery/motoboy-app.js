(function () {
  const cfg = window.MOTOBOY_APP || {};
  const listEl = document.getElementById("mbList");
  const emptyEl = document.getElementById("mbEmpty");
  const toastEl = document.getElementById("mbToast");
  const dialog = document.getElementById("mbDialog");
  const installBtn = document.getElementById("mbInstall");
  let deferredPrompt = null;
  let busy = false;

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

  async function postJson(url) {
    const res = await fetch(url, {
      method: "POST",
      headers: {
        "Accept": "application/json",
        "X-CSRF-TOKEN": cfg.csrf || "",
        "X-Requested-With": "XMLHttpRequest",
      },
      credentials: "same-origin",
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
      const msg = data?.message || data?.errors?.oferta?.[0] || data?.mensagem || "Falha na operação.";
      throw new Error(msg);
    }
    return data;
  }

  function render(items) {
    if (!items.length) {
      emptyEl.hidden = false;
      listEl.hidden = true;
      listEl.innerHTML = "";
      document.title = (cfg.appNome || "Entrega") + " · sem entregas";
      return;
    }
    emptyEl.hidden = true;
    listEl.hidden = false;
    document.title = (cfg.appNome || "Entrega") + " · " + items.length + " entrega" + (items.length > 1 ? "s" : "");
    listEl.innerHTML = items.map((it) => `
      <article class="mb-card" data-id="${it.id}">
        <h3>Pedido ${escapeHtml(it.codigo_publico)}</h3>
        <p class="mb-meta">${escapeHtml(it.status_rotulo || "")} · ${escapeHtml(it.loja_nome || "")}</p>
        <p class="mb-addr">${escapeHtml(it.endereco || "Endereço não informado")}</p>
        <ul class="mb-itens">${(it.itens_resumo || []).map((x) => `<li>${escapeHtml(x)}</li>`).join("")}</ul>
        <div class="mb-total">${money(it.total)}</div>
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
    if (busy) return;
    try {
      const res = await fetch(cfg.ofertasUrl, { headers: { Accept: "application/json" }, credentials: "same-origin" });
      if (!res.ok) throw new Error("poll");
      const data = await res.json();
      render(data.items || []);
    } catch (_) {}
  }

  listEl?.addEventListener("click", async (ev) => {
    const aceitar = ev.target.closest("[data-aceitar]");
    const recusar = ev.target.closest("[data-recusar]");
    if (!aceitar && !recusar) return;
    if (busy) return;
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

  function isInstalled() {
    if (window.matchMedia("(display-mode: standalone)").matches) return true;
    if (window.matchMedia("(display-mode: fullscreen)").matches) return true;
    if (window.navigator.standalone === true) return true;
    return false;
  }

  function hideInstall() {
    if (installBtn) installBtn.hidden = true;
    deferredPrompt = null;
  }

  function showInstallIfNeeded() {
    if (!installBtn) return;
    if (isInstalled() || !deferredPrompt) {
      hideInstall();
      return;
    }
    installBtn.hidden = false;
  }

  window.addEventListener("beforeinstallprompt", (e) => {
    e.preventDefault();
    if (isInstalled()) {
      hideInstall();
      return;
    }
    deferredPrompt = e;
    showInstallIfNeeded();
  });

  window.addEventListener("appinstalled", () => {
    hideInstall();
  });

  installBtn?.addEventListener("click", async () => {
    if (!deferredPrompt || isInstalled()) {
      hideInstall();
      return;
    }
    deferredPrompt.prompt();
    await deferredPrompt.userChoice;
    hideInstall();
  });

  if (isInstalled()) hideInstall();

  poll();
  setInterval(poll, 8000);
})();
