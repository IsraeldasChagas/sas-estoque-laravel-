/**
 * PDF (DANFE) e XML da NFC-e — download via API SAS (proxy Focus).
 */
(function () {
  "use strict";

  function apiBase() {
    return (
      window.API_URL ||
      (window.APP_CONFIG && window.APP_CONFIG.API_URL) ||
      "https://api.gruposaborparaense.com.br/api"
    ).replace(/\/$/, "");
  }

  function authHeaders() {
    const headers = {};
    const uid = window.getUser?.()?.id || window.currentUser?.id;
    if (uid) headers["X-Usuario-Id"] = String(uid);
    if (window.currentUser?.token) headers.Authorization = `Bearer ${window.currentUser.token}`;
    return headers;
  }

  function toast(msg, type) {
    const fn = typeof showToast === "function" ? showToast : window.showToast;
    if (typeof fn === "function") fn(msg, type || "info");
    else alert(msg);
  }

  async function fiscalDocFetch(vendaId, tipo) {
    const path =
      tipo === "xml"
        ? `/fiscal/emissao/vendas/${vendaId}/xml`
        : `/fiscal/emissao/vendas/${vendaId}/danfe.pdf`;
    const res = await fetch(`${apiBase()}${path}`, {
      method: "GET",
      headers: authHeaders(),
      cache: "no-store",
    });
    if (!res.ok) {
      let msg = `HTTP ${res.status}`;
      try {
        const err = await res.json();
        msg = err.error || err.message || msg;
      } catch {
        /* binário ou vazio */
      }
      throw new Error(msg);
    }
    return { blob: await res.blob(), res };
  }

  /**
   * Após emissão autorizada: abre PDF em nova aba e baixa XML automaticamente.
   * @param {number|string} vendaId
   * @param {{ autoPdf?: boolean, autoXml?: boolean }} [opts]
   */
  async function fiscalEntregarDocumentosVenda(vendaId, opts) {
    const id = Number(vendaId);
    if (!id) return;
    const autoPdf = opts?.autoPdf !== false;
    const autoXml = opts?.autoXml !== false;
    try {
      if (autoPdf) {
        const { blob } = await fiscalDocFetch(id, "pdf");
        const url = URL.createObjectURL(blob);
        const w = window.open(url, "_blank", "noopener,noreferrer");
        if (!w) toast("Permita pop-ups para abrir o PDF da nota.", "warning");
        setTimeout(() => URL.revokeObjectURL(url), 120000);
      }
      if (autoXml) {
        const { blob } = await fiscalDocFetch(id, "xml");
        const url = URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = `nfce-venda-${id}.xml`;
        a.rel = "noopener";
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(() => URL.revokeObjectURL(url), 10000);
      }
    } catch (e) {
      toast(e?.message || "Não foi possível obter PDF/XML da nota.", "warning");
    }
  }

  window.fiscalEntregarDocumentosVenda = fiscalEntregarDocumentosVenda;
  window.fiscalBaixarDanfePdf = (vendaId) => fiscalEntregarDocumentosVenda(vendaId, { autoPdf: true, autoXml: false });
  window.fiscalBaixarXmlNota = (vendaId) => fiscalEntregarDocumentosVenda(vendaId, { autoPdf: false, autoXml: true });
})();
