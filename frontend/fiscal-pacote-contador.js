/**
 * Fiscal — Pacote mensal para contador (ZIP).
 */
(function () {
  "use strict";

  function toast(msg, type) {
    const fn = typeof showToast === "function" ? showToast : window.showToast;
    if (typeof fn === "function") fn(msg, type || "info");
  }

  async function fFetch(path, opts) {
    if (typeof window.fetchJSON === "function" && !(opts && opts.blob)) {
      return window.fetchJSON(path, opts);
    }
    const base = window.API_URL || (window.APP_CONFIG && window.APP_CONFIG.API_URL) || "/api";
    const headers = { ...(opts?.headers || {}) };
    const uid = window.getUser?.()?.id || window.currentUser?.id;
    if (uid) headers["X-Usuario-Id"] = String(uid);
    const res = await fetch(`${String(base).replace(/\/$/, "")}${path.startsWith("/") ? path : `/${path}`}`, {
      ...opts,
      headers,
    });
    if (opts?.blob) {
      if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        throw new Error(err.error || err.message || `HTTP ${res.status}`);
      }
      return res.blob();
    }
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.error || data.message || `HTTP ${res.status}`);
    return data;
  }

  function esc(s) {
    if (typeof escapeHtml === "function") return escapeHtml(s);
    const d = document.createElement("div");
    d.textContent = s == null ? "" : String(s);
    return d.innerHTML;
  }

  function mesAtual() {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}`;
  }

  async function loadEmpresas() {
    return fFetch("/fiscal/empresas");
  }

  async function renderPage() {
    const root = document.getElementById("fiscalPacoteContadorRoot");
    if (!root) return;
    root.innerHTML = `<p class="subtle-text">Carregando…</p>`;
    let empresas = [];
    let meta = {};
    try {
      [meta, empresas] = await Promise.all([
        fFetch("/fiscal/pacote-contador/meta"),
        loadEmpresas(),
      ]);
    } catch (e) {
      root.innerHTML = `<div class="int-card"><p>${esc(e.message)}</p></div>`;
      return;
    }

    const opts = (Array.isArray(empresas) ? empresas : [])
      .map((e) => `<option value="${e.id}">${esc(e.nome_fantasia || e.razao_social)}</option>`)
      .join("");

    root.innerHTML = `
      <div class="fpc-page">
        <div class="int-card">
          <h3>${esc(meta.titulo || "Pacote contador")}</h3>
          <p class="subtle-text">${esc(meta.descricao || "")}</p>
          <div class="fem-empresa-bar" style="margin-top:1rem">
            <label>Empresa (CNPJ)
              <select id="fpcEmpresa">${opts || "<option value=\"\">— Cadastre empresa —</option>"}</select>
            </label>
            <label>Mês (YYYY-MM)
              <input type="month" id="fpcMes" value="${mesAtual()}" />
            </label>
          </div>
          <div class="fpc-actions fem-actions" style="margin-top:1rem">
            <button type="button" class="btn secondary" id="fpcPreview">Ver resumo</button>
            <button type="button" class="btn primary" id="fpcDownload">Baixar ZIP</button>
          </div>
          <div id="fpcPreviewBox"></div>
        </div>
      </div>`;

    root.querySelector("#fpcPreview")?.addEventListener("click", () => preview(root));
    root.querySelector("#fpcDownload")?.addEventListener("click", () => download(root));
  }

  function getParams(root) {
    const empresaId = Number(root.querySelector("#fpcEmpresa")?.value);
    const mesRaw = root.querySelector("#fpcMes")?.value || "";
    const mes = mesRaw.length >= 7 ? mesRaw.slice(0, 7) : mesAtual();
    return { empresaId, mes };
  }

  async function preview(root) {
    const { empresaId, mes } = getParams(root);
    if (!empresaId) {
      toast("Selecione a empresa.", "warning");
      return;
    }
    const box = root.querySelector("#fpcPreviewBox");
    if (box) box.innerHTML = `<p class="subtle-text">Carregando resumo…</p>`;
    try {
      const p = await fFetch(`/fiscal/pacote-contador/preview?empresa_id=${empresaId}&mes=${encodeURIComponent(mes)}`);
      const c = p.contagens || {};
      const cards = p.visao_gerencial?.cards || {};
      if (box) {
        box.innerHTML = `
          <div class="fpc-preview">
            <div><span>Vendas</span><strong>${esc(c.vendas ?? 0)}</strong></div>
            <div><span>NFC-e autorizadas</span><strong>${esc(c.nfce_autorizadas ?? 0)}</strong></div>
            <div><span>Notas entrada</span><strong>${esc(c.notas_entrada ?? 0)}</strong></div>
            <div><span>Receita (M7)</span><strong>${Number(cards.receita || cards.saidas || 0).toLocaleString("pt-BR", { style: "currency", currency: "BRL" })}</strong></div>
          </div>
          <p class="subtle-text" style="margin-top:0.75rem">${esc(p.disclaimer || "")}</p>`;
      }
    } catch (e) {
      if (box) box.innerHTML = `<p class="subtle-text">${esc(e.message)}</p>`;
    }
  }

  async function download(root) {
    const { empresaId, mes } = getParams(root);
    if (!empresaId) {
      toast("Selecione a empresa.", "warning");
      return;
    }
    try {
      toast("Gerando pacote…", "info");
      const blob = await fFetch(`/fiscal/pacote-contador/download?empresa_id=${empresaId}&mes=${encodeURIComponent(mes)}`, { blob: true });
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = `pacote-contador-${mes}.zip`;
      a.click();
      URL.revokeObjectURL(url);
      toast("Download iniciado.", "success");
    } catch (e) {
      toast(e.message || "Falha ao gerar ZIP.", "error");
    }
  }

  window.loadFiscalPacoteContador = renderPage;
})();
