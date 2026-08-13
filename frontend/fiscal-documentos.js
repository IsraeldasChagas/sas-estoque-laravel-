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

  function filenameFromDisposition(header, fallback) {
    if (!header) return fallback;
    const m = /filename\*?=(?:UTF-8''|")?([^";]+)"?/i.exec(header);
    if (!m) return fallback;
    try {
      return decodeURIComponent(m[1].trim());
    } catch {
      return m[1].trim() || fallback;
    }
  }

  function baixarBlob(blob, filename) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = filename;
    a.rel = "noopener";
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 15000);
  }

  async function fiscalDocFetch(vendaId, tipo) {
    const path =
      tipo === "xml"
        ? `/fiscal/emissao/vendas/${vendaId}/xml`
        : tipo === "html"
          ? `/fiscal/emissao/vendas/${vendaId}/danfe.html`
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
    const ct = (res.headers.get("content-type") || "").toLowerCase();
    const blob = await res.blob();
    if (ct.includes("json") || (blob.type && blob.type.includes("json"))) {
      throw new Error("A Focus não retornou o arquivo da nota. Tente de novo.");
    }
    if (tipo === "pdf" && !ct.includes("pdf")) {
      // Ainda assim tenta baixar se o corpo for PDF (alguns proxies omitem header).
      const head = await blob.slice(0, 4).text();
      if (!head.startsWith("%PDF")) {
        throw new Error("Não foi possível gerar o PDF da nota.");
      }
    }
    const fallback =
      tipo === "xml" ? `nfce-venda-${vendaId}.xml` : tipo === "html" ? `nfce-venda-${vendaId}.html` : `nfce-venda-${vendaId}.pdf`;
    const filename = filenameFromDisposition(res.headers.get("content-disposition"), fallback);
    return { blob, contentType: ct, filename };
  }

  /**
   * Baixa PDF e/ou XML da NFC-e autorizada.
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
        const { blob, filename } = await fiscalDocFetch(id, "pdf");
        baixarBlob(blob, filename.endsWith(".pdf") ? filename : `nfce-venda-${id}.pdf`);
        toast("PDF da nota baixado.", "success");
      }
      if (autoXml) {
        const { blob, filename } = await fiscalDocFetch(id, "xml");
        baixarBlob(blob, filename.endsWith(".xml") ? filename : `nfce-venda-${id}.xml`);
        toast("XML da nota baixado.", "success");
      }
    } catch (e) {
      toast(e?.message || "Não foi possível baixar PDF/XML da nota.", "warning");
    }
  }

  window.fiscalEntregarDocumentosVenda = fiscalEntregarDocumentosVenda;
  window.fiscalBaixarDanfePdf = (vendaId) => fiscalEntregarDocumentosVenda(vendaId, { autoPdf: true, autoXml: false });
  window.fiscalBaixarXmlNota = (vendaId) => fiscalEntregarDocumentosVenda(vendaId, { autoPdf: false, autoXml: true });

  /** Abre o cupom HTML oficial da Focus (QR Code válido). */
  window.fiscalAbrirDanfeHtml = async (vendaId) => {
    const id = Number(vendaId);
    if (!id) return;
    try {
      const { blob, contentType } = await fiscalDocFetch(id, "html");
      const url = URL.createObjectURL(blob);
      const w = window.open(url, "_blank", "noopener,noreferrer");
      if (!w) toast("Permita pop-ups para abrir o cupom.", "warning");
      else if (!contentType.includes("html") && !contentType.includes("pdf")) {
        toast("Cupom aberto.", "info");
      }
      setTimeout(() => URL.revokeObjectURL(url), 180000);
    } catch (e) {
      toast(e?.message || "Não foi possível abrir o cupom.", "warning");
    }
  };

  /** Abre a URL oficial de consulta SEFAZ (qrcode_url completo com hash). */
  window.fiscalAbrirConsultaSefaz = async (vendaId) => {
    const id = Number(vendaId);
    if (!id) return;
    try {
      const res = await fetch(`${apiBase()}/fiscal/emissao/vendas/${id}/documentos`, {
        method: "GET",
        headers: { ...authHeaders(), Accept: "application/json" },
        cache: "no-store",
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(data.error || data.message || `HTTP ${res.status}`);
      const url = data.qrcode_url || data.danfe_focus_url;
      if (!url) {
        throw new Error("Link de consulta ainda não disponível. Abra o Cupom ou baixe o PDF novo.");
      }
      if (data.chave_acesso && String(data.chave_acesso).length !== 44) {
        toast("Atenção: chave incompleta no SAS (" + String(data.chave_acesso).length + " dígitos).", "warning");
      }
      window.open(url, "_blank", "noopener,noreferrer");
    } catch (e) {
      toast(e?.message || "Não foi possível abrir a consulta SEFAZ.", "warning");
    }
  };
})();
