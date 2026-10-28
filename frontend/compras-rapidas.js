/**
 * Lista de compras rápida (simples): nome, produtos com quantidade/tipo/observação,
 * cabeçalho com logo da unidade, usuário, data e hora. PDF e envio pelo WhatsApp
 * com o mesmo cabeçalho e logo (também usado pela Lista de Compras completa).
 */
(function () {
  "use strict";

  const TIPOS = {
    KG: ["Quilo", "kg"],
    G: ["Grama", "g"],
    UN: ["Unidade", "und"],
    CAIXA: ["Caixa", "caixa"],
    PACOTE: ["Pacote", "pacote"],
    LITRO: ["Litro", "litro"],
    ML: ["Mililitro", "ml"],
    SACHE: ["Sachê", "sachê"],
    FARDO: ["Fardo", "fardo"],
    SACO: ["Saco", "saco"],
  };
  const TIPO_POR_UNIDADE_BASE = { KG: "KG", K: "KG", G: "G", L: "LITRO", LITRO: "LITRO", ML: "ML", UND: "UN", UN: "UN", UNID: "UN", CX: "CAIXA", PCT: "PACOTE" };
  const WA_NUMERO_KEY = "sas-compras-whatsapp-numero";

  const st = {
    meta: null,
    listas: [],
    filtro: "todas",
    unidadeId: "",
    nome: "",
    rascunho: [],
    editando: null,
    novo: { produto: "", produtoId: null, quantidade: "", tipo: "UN", observacao: "" },
    logos: new Map(),
  };

  const $ = (id) => document.getElementById(id);
  const esc = (v) =>
    typeof window.escapeHtml === "function"
      ? window.escapeHtml(v == null ? "" : String(v))
      : String(v == null ? "" : v).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
  const toast = (m, t) => window.showToast?.(m, t || "info");
  const api = (path, options) => window.fetchJSON(`/compras-rapidas${path}`, options);
  const send = (path, method, body) => api(path, { method, body: JSON.stringify(body || {}) });

  const tipoSigla = (t) => (TIPOS[t] ? TIPOS[t][1] : String(t || "").toLowerCase());
  const qtdLabel = (q) => Number(q || 0).toLocaleString("pt-BR", { maximumFractionDigits: 3 });
  const itemLinha = (it) => {
    const base = `${it.produto_nome} — ${qtdLabel(it.quantidade)}${it.tipo ? ` ${tipoSigla(it.tipo)}` : ""}`;
    return it.observacao && String(it.observacao).trim() ? `${base} (Obs.: ${String(it.observacao).trim()})` : base;
  };
  const quando = (iso) => {
    const d = iso ? new Date(String(iso).replace(" ", "T")) : null;
    if (!d || Number.isNaN(d.getTime())) return { data: "—", hora: "—" };
    return { data: d.toLocaleDateString("pt-BR"), hora: d.toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" }) };
  };
  const nomeArquivo = (lista, ext) => {
    const limpo = String(lista.nome || "lista-compras")
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .replace(/[^a-zA-Z0-9]+/g, "-")
      .replace(/^-|-$/g, "")
      .slice(0, 40);
    return `${limpo || "lista-compras"}.${ext}`;
  };

  // ——— Logo (da unidade; se não houver, a do grupo) ———

  async function arquivoParaDataUrl(caminho) {
    try {
      const r = await fetch(caminho);
      if (!r.ok || !(r.headers.get("Content-Type") || "").includes("image/")) return null;
      const blob = await r.blob();
      return await new Promise((resolve) => {
        const fr = new FileReader();
        fr.onloadend = () => resolve(fr.result);
        fr.onerror = () => resolve(null);
        fr.readAsDataURL(blob);
      });
    } catch (_) {
      return null;
    }
  }

  function logoDataUrl(unidadeId) {
    const chave = String(unidadeId || "grupo");
    if (!st.logos.has(chave)) {
      st.logos.set(chave, (async () => {
        let url = null;
        if (unidadeId) {
          try {
            url = (await api(`/logo?unidade_id=${encodeURIComponent(unidadeId)}`))?.data_url || null;
          } catch (_) {
            url = null;
          }
        }
        return url || (await arquivoParaDataUrl("imagens/logosemfundo.png")) || (await arquivoParaDataUrl("imagens/logo.png"));
      })());
    }
    return st.logos.get(chave);
  }

  window.sasLimparCacheLogoUnidade = (unidadeId) => st.logos.delete(String(unidadeId || "grupo"));

  function carregarImagem(src) {
    return new Promise((resolve) => {
      if (!src) return resolve(null);
      const img = new Image();
      img.onload = () => resolve(img);
      img.onerror = () => resolve(null);
      img.src = src;
    });
  }

  // ——— Imagem (WhatsApp) ———

  function quebrar(ctx, texto, largura) {
    const palavras = String(texto).split(/\s+/);
    const linhas = [];
    let atual = "";
    palavras.forEach((p) => {
      const teste = atual ? `${atual} ${p}` : p;
      if (ctx.measureText(teste).width > largura && atual) {
        linhas.push(atual);
        atual = p;
      } else atual = teste;
    });
    if (atual) linhas.push(atual);
    return linhas;
  }

  async function gerarImagem(lista) {
    const W = 1080;
    const pad = 56;
    const logo = await carregarImagem(await logoDataUrl(lista.unidade_id));
    const q = quando(lista.created_at);
    const fonte = "Arial, Helvetica, sans-serif";
    const cabecalho = [`Unidade: ${lista.unidade_nome || "—"}`, `Usuário: ${lista.usuario_nome || "—"}`, `Data: ${q.data}`, `Hora: ${q.hora}`];
    const logoBox = logo ? 190 : 0;
    const textoX = pad + (logo ? logoBox + 32 : 0);

    const medir = document.createElement("canvas").getContext("2d");
    medir.font = `bold 46px ${fonte}`;
    const tituloLinhas = quebrar(medir, lista.nome || "Lista de compras", W - textoX - pad);
    medir.font = `30px ${fonte}`;
    const itens = (lista.itens || []).map((it, i) => quebrar(medir, `${i + 1}. ${itemLinha(it)}`, W - pad * 2 - 20));
    const alturaTexto = tituloLinhas.length * 56 + cabecalho.length * 42;
    const alturaCab = Math.max(logoBox, alturaTexto);
    const H = pad + alturaCab + 40 + 56 + itens.reduce((t, l) => t + l.length * 40 + 12, 0) + 90;

    const canvas = document.createElement("canvas");
    canvas.width = W;
    canvas.height = H;
    const ctx = canvas.getContext("2d");
    ctx.fillStyle = "#ffffff";
    ctx.fillRect(0, 0, W, H);

    if (logo) {
      const escala = Math.min(logoBox / logo.width, logoBox / logo.height);
      const lw = logo.width * escala;
      const lh = logo.height * escala;
      ctx.drawImage(logo, pad + (logoBox - lw) / 2, pad + (alturaCab - lh) / 2, lw, lh);
    }
    let y = pad + Math.max(0, (alturaCab - alturaTexto) / 2) + 44;
    ctx.fillStyle = "#111111";
    ctx.font = `bold 46px ${fonte}`;
    tituloLinhas.forEach((l) => {
      ctx.fillText(l, textoX, y);
      y += 56;
    });
    ctx.font = `30px ${fonte}`;
    ctx.fillStyle = "#333333";
    cabecalho.forEach((l) => {
      ctx.fillText(l, textoX, y);
      y += 42;
    });

    y = pad + alturaCab + 24;
    ctx.fillStyle = "#e2e8f0";
    ctx.fillRect(pad, y, W - pad * 2, 3);
    y += 56;
    ctx.fillStyle = "#111111";
    ctx.font = `bold 34px ${fonte}`;
    ctx.fillText("Produtos", pad, y);
    y += 50;
    ctx.font = `30px ${fonte}`;
    ctx.fillStyle = "#1f2937";
    itens.forEach((linhas) => {
      linhas.forEach((l, i) => {
        ctx.fillText(l, pad + (i ? 36 : 0), y);
        y += 40;
      });
      y += 12;
    });

    ctx.fillStyle = "#6b7280";
    ctx.font = `bold 24px ${fonte}`;
    ctx.textAlign = "center";
    ctx.fillText("Grupo Sabor Paraense", W / 2, H - 40);
    return new Promise((resolve) => canvas.toBlob((b) => resolve(b), "image/png"));
  }

  function textoWhatsapp(lista) {
    const q = quando(lista.created_at);
    return [
      `*${lista.nome || "Lista de compras"}*`,
      `Unidade: ${lista.unidade_nome || "—"}`,
      `Usuário: ${lista.usuario_nome || "—"}`,
      `Data: ${q.data}`,
      `Hora: ${q.hora}`,
      "",
      "*Produtos*",
      ...(lista.itens || []).map((it, i) => `${i + 1}. ${itemLinha(it)}`),
    ].join("\n");
  }

  function linkWhatsapp(numero, texto) {
    let digitos = String(numero || "").replace(/\D/g, "");
    if (digitos && digitos.length <= 11) digitos = `55${digitos}`;
    return `https://wa.me/${digitos}?text=${encodeURIComponent(texto)}`;
  }

  function baixar(blob, nome) {
    const a = document.createElement("a");
    a.href = URL.createObjectURL(blob);
    a.download = nome;
    document.body.appendChild(a);
    a.click();
    setTimeout(() => {
      URL.revokeObjectURL(a.href);
      a.remove();
    }, 1000);
  }

  async function enviarWhatsapp(lista, numero) {
    const texto = textoWhatsapp(lista);
    const podeCompartilharArquivo = !!navigator.canShare?.({ files: [new File([""], "lista.png", { type: "image/png" })] });
    const janela = podeCompartilharArquivo ? null : window.open("about:blank", "_blank");
    try {
      const blob = await gerarImagem(lista);
      if (!blob) throw new Error("Não foi possível montar a imagem da lista.");
      const arquivo = new File([blob], nomeArquivo(lista, "png"), { type: "image/png" });
      if (podeCompartilharArquivo) {
        await navigator.share({ files: [arquivo], title: lista.nome || "Lista de compras", text: texto });
        toast("Escolha o WhatsApp e o contato para enviar a lista.", "success");
        return;
      }
      let copiou = false;
      try {
        if (navigator.clipboard?.write && typeof window.ClipboardItem === "function") {
          await navigator.clipboard.write([new window.ClipboardItem({ "image/png": blob })]);
          copiou = true;
        }
      } catch (_) {
        copiou = false;
      }
      if (!copiou) baixar(blob, nomeArquivo(lista, "png"));
      const url = linkWhatsapp(numero, texto);
      if (janela) janela.location.href = url;
      else window.open(url, "_blank");
      toast(
        copiou
          ? "A mensagem já foi para o WhatsApp. A imagem com logo está copiada: na conversa, cole com Ctrl+V."
          : "A mensagem já foi para o WhatsApp. A imagem com logo foi baixada: anexe na conversa.",
        "success"
      );
    } catch (e) {
      if (janela) janela.close();
      if (e?.name === "AbortError") return;
      toast(e?.message || "Não foi possível enviar no WhatsApp.", "error");
    }
  }

  function pedirNumeroEEnviar(lista) {
    let modal = $("crWhatsModal");
    if (!modal) {
      modal = document.createElement("div");
      modal.className = "modal-backdrop";
      modal.id = "crWhatsModal";
      modal.innerHTML = `<div class="modal cr-whats-modal" role="dialog" aria-modal="true" aria-labelledby="crWhatsTitulo">
        <header><h2 id="crWhatsTitulo">Enviar pelo WhatsApp</h2><button type="button" class="close-btn" data-cr-whats="fechar" aria-label="Fechar">×</button></header>
        <p class="subtle-text">Vai a imagem da lista com o mesmo cabeçalho e logo do PDF, junto com o texto.</p>
        <label>Número do WhatsApp (opcional)<input type="tel" id="crWhatsNumero" placeholder="(91) 99999-9999" inputmode="tel" /></label>
        <p class="subtle-text">No celular, você escolhe o contato. No computador, abre a conversa com este número (ou para escolher o contato, se ficar vazio).</p>
        <footer>
          <button type="button" class="btn neutral" data-cr-whats="fechar">Cancelar</button>
          <button type="button" class="btn rh-wa-btn rh-wa-btn--mini" data-cr-whats="enviar">Enviar</button>
        </footer>
      </div>`;
      document.body.appendChild(modal);
      modal.addEventListener("click", (ev) => {
        const acao = ev.target.closest("[data-cr-whats]")?.dataset.crWhats;
        if (ev.target === modal || acao === "fechar") modal.classList.remove("active");
        else if (acao === "enviar") {
          const numero = $("crWhatsNumero").value.trim();
          try {
            localStorage.setItem(WA_NUMERO_KEY, numero);
          } catch (_) {}
          modal.classList.remove("active");
          enviarWhatsapp(modal._lista, numero);
        }
      });
    }
    modal._lista = lista;
    try {
      $("crWhatsNumero").value = localStorage.getItem(WA_NUMERO_KEY) || "";
    } catch (_) {}
    modal.classList.add("active");
  }

  // ——— PDF ———

  async function imprimirPdf(lista) {
    const logo = await logoDataUrl(lista.unidade_id);
    const q = quando(lista.created_at);
    const html = `<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8" /><title>${esc(lista.nome || "Lista de compras")}</title>
      <style>
        body { font-family: Arial, sans-serif; color: #111; padding: 28px; }
        .cab { display: flex; align-items: center; gap: 20px; padding-bottom: 16px; border-bottom: 2px solid #e2e8f0; margin-bottom: 18px; }
        .cab img { width: 110px; height: 110px; object-fit: contain; }
        .cab h1 { margin: 0 0 6px; font-size: 24px; }
        .cab p { margin: 2px 0; font-size: 14px; color: #333; }
        h2 { font-size: 17px; margin: 0 0 10px; }
        ol { margin: 0; padding-left: 22px; }
        li { font-size: 14px; padding: 5px 0; border-bottom: 1px solid #f0f0f0; }
        footer { margin-top: 28px; text-align: center; font-size: 12px; color: #555; font-weight: 700; }
      </style></head><body>
      <div class="cab">${logo ? `<img src="${logo}" alt="Logo" />` : ""}<div>
        <h1>${esc(lista.nome || "Lista de compras")}</h1>
        <p><strong>Unidade:</strong> ${esc(lista.unidade_nome || "—")}</p>
        <p><strong>Usuário:</strong> ${esc(lista.usuario_nome || "—")}</p>
        <p><strong>Data:</strong> ${esc(q.data)}</p>
        <p><strong>Hora:</strong> ${esc(q.hora)}</p>
      </div></div>
      <h2>Produtos</h2>
      <ol>${(lista.itens || []).map((it) => `<li>${esc(itemLinha(it))}</li>`).join("")}</ol>
      <footer>Grupo Sabor Paraense</footer>
      </body></html>`;
    const iframe = document.createElement("iframe");
    iframe.style.cssText = "position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden;";
    document.body.appendChild(iframe);
    const limpar = () => iframe.parentNode && iframe.parentNode.removeChild(iframe);
    iframe.onload = () => {
      const win = iframe.contentWindow;
      if (!win || typeof win.print !== "function") {
        limpar();
        toast("Seu navegador não suportou a impressão.", "error");
        return;
      }
      win.onafterprint = limpar;
      win.focus();
      win.print();
      setTimeout(limpar, 15000);
    };
    const doc = iframe.contentDocument || iframe.contentWindow?.document;
    doc.open();
    doc.write(html);
    doc.close();
  }

  window.sasListaCompraCompartilhar = { pdf: imprimirPdf, whatsapp: pedirNumeroEEnviar, imagem: gerarImagem };

  // ——— Tela ———

  const usuarioNome = () => st.meta?.usuario?.nome || "Usuário";
  const unidadeNome = (id) => (st.meta?.unidades || []).find((u) => String(u.id) === String(id))?.nome || "Sem unidade";

  function listaPreview(lista) {
    const q = quando(lista.created_at);
    return `<article class="cr-folha">
      <div class="cr-cabecalho">
        <div class="cr-logo" data-logo-unidade="${esc(lista.unidade_id || "")}"></div>
        <div>
          <h3>${esc(lista.nome || "Lista de compras")}</h3>
          <p><strong>Unidade:</strong> ${esc(lista.unidade_nome)}</p>
          <p><strong>Usuário:</strong> ${esc(lista.usuario_nome)}</p>
          <p><strong>Data:</strong> ${esc(q.data)}</p>
          <p><strong>Hora:</strong> ${esc(q.hora)}</p>
        </div>
      </div>
      <ol>${(lista.itens || []).map((it) => `<li>${esc(itemLinha(it))}</li>`).join("")}</ol>
    </article>`;
  }

  async function preencherLogos(raiz) {
    const alvos = [...(raiz || document).querySelectorAll("[data-logo-unidade]")];
    for (const el of alvos) {
      const url = await logoDataUrl(el.dataset.logoUnidade || null);
      el.innerHTML = url ? `<img src="${url}" alt="Logo" />` : "";
    }
  }

  function abrirPreview(lista) {
    let modal = $("crPreviewModal");
    if (!modal) {
      modal = document.createElement("div");
      modal.className = "modal-backdrop";
      modal.id = "crPreviewModal";
      modal.innerHTML = `<div class="modal cr-preview-modal" role="dialog" aria-modal="true" aria-labelledby="crPreviewTitulo">
        <header><h2 id="crPreviewTitulo">Lista de compras</h2><button type="button" class="close-btn" data-cr-prev="fechar" aria-label="Fechar">×</button></header>
        <div id="crPreviewBody"></div>
        <footer>
          <button type="button" class="btn neutral" data-cr-prev="fechar">Fechar</button>
          <button type="button" class="btn neutral" data-cr-prev="pdf">PDF</button>
          <button type="button" class="btn rh-wa-btn rh-wa-btn--mini" data-cr-prev="whatsapp">WhatsApp</button>
        </footer>
      </div>`;
      document.body.appendChild(modal);
      modal.addEventListener("click", (ev) => {
        const acao = ev.target.closest("[data-cr-prev]")?.dataset.crPrev;
        if (ev.target === modal || acao === "fechar") modal.classList.remove("active");
        else if (acao === "pdf") imprimirPdf(modal._lista);
        else if (acao === "whatsapp") pedirNumeroEEnviar(modal._lista);
      });
    }
    modal._lista = lista;
    $("crPreviewBody").innerHTML = listaPreview(lista);
    preencherLogos($("crPreviewBody"));
    modal.classList.add("active");
  }

  function limparFormulario() {
    st.editando = null;
    st.nome = "";
    st.rascunho = [];
    st.novo = { produto: "", produtoId: null, quantidade: "", tipo: "UN", observacao: "" };
    st.unidadeId = String(st.meta?.usuario?.unidade_id || (st.meta?.unidades || [])[0]?.id || "");
  }

  function htmlRascunho() {
    if (!st.rascunho.length) return '<p class="rh-cand-empty">Nenhum produto nesta lista.</p>';
    return st.rascunho.map((it, i) => `<article class="rh-cand-card">
      <div class="rh-cand-card__meta">
        <div class="rh-cand-card__row"><span>Produto</span><strong>${esc(it.produto_nome)}</strong></div>
        <div class="rh-cand-card__row"><span>Quantidade</span><strong>${esc(qtdLabel(it.quantidade))} ${esc(tipoSigla(it.tipo))}</strong></div>
        ${it.observacao ? `<div class="rh-cand-card__row"><span>Observação</span><strong>${esc(it.observacao)}</strong></div>` : ""}
      </div>
      <div class="rh-cand-card__actions"><button type="button" class="btn" style="background:#b71c1c;color:#fff;" data-cr-tirar="${i}">Tirar</button></div>
    </article>`).join("");
  }

  function htmlListas() {
    if (!st.listas.length) return '<p class="rh-cand-empty">Nenhuma lista neste filtro.</p>';
    return st.listas.map((l) => {
      const q = quando(l.created_at);
      const feita = l.status === "feita";
      return `<article class="rh-cand-card" data-cr-id="${l.id}">
        <div class="rh-cand-card__top">
          <div class="usuarios-foto usuarios-foto--placeholder rh-cand-card__photo pat-card-icone cr-logo-mini" data-logo-unidade="${esc(l.unidade_id || "")}" aria-hidden="true">🛒</div>
          <div class="rh-cand-card__main">
            <div class="rh-cand-card__head">
              <div><h3 class="rh-cand-card__name">${esc(l.nome || "Sem nome")}</h3><p class="rh-cand-card__sub">${esc(l.unidade_nome)}</p></div>
              <span class="rh-cand-pill rh-cand-pill--${feita ? "ok" : "info"}">${feita ? "Feita" : "Aberta"}</span>
            </div>
            <div class="rh-cand-card__meta">
              <div class="rh-cand-card__row"><span>Usuário</span><strong>${esc(l.usuario_nome)}</strong></div>
              <div class="rh-cand-card__row"><span>Data</span><strong>${esc(q.data)}</strong></div>
              <div class="rh-cand-card__row"><span>Hora</span><strong>${esc(q.hora)}</strong></div>
              <div class="rh-cand-card__row"><span>Itens</span><strong>${l.itens.length}</strong></div>
            </div>
          </div>
        </div>
        <div class="rh-cand-card__actions">
          <button type="button" class="btn btn-rh-toggle" data-cr-acao="abrir">Abrir</button>
          ${l.pode_gerir ? '<button type="button" class="btn primary" data-cr-acao="editar">Editar</button>' : ""}
          ${l.pode_gerir ? `<button type="button" class="btn neutral" data-cr-acao="status">${feita ? "Reabrir" : "Feita"}</button>` : ""}
          <button type="button" class="btn neutral" data-cr-acao="pdf">PDF</button>
          <button type="button" class="btn rh-wa-btn rh-wa-btn--mini" data-cr-acao="whatsapp">WhatsApp</button>
          ${l.pode_gerir ? '<button type="button" class="btn" style="background:#b71c1c;color:#fff;" data-cr-acao="excluir">Excluir</button>' : ""}
        </div>
      </article>`;
    }).join("");
  }

  function render() {
    const root = $("comprasRapidasRoot");
    if (!root) return;
    const q = st.editando ? quando(st.editando.created_at) : null;
    const unidades = st.meta.unidades || [];
    const tiposOpts = Object.entries(TIPOS).map(([k, [nome, sigla]]) => `<option value="${k}"${st.novo.tipo === k ? " selected" : ""}>${esc(nome)} (${esc(sigla)})</option>`).join("");
    root.innerHTML = `<div class="caixa-page-wrap cr-wrap">
      <div class="cr-titulo"><h2>Lista de compras rápida</h2><p class="subtle-text">Monte a lista, salve e depois veja, edite, exclua ou envie em PDF ou pelo WhatsApp.</p></div>
      <article class="rh-cand-card cr-cabecalho-card">
        <div class="cr-cabecalho">
          <div class="cr-logo" data-logo-unidade="${esc(st.unidadeId || "")}"></div>
          <div class="rh-cand-card__meta">
            <div class="rh-cand-card__row"><span>Usuário</span><strong>${esc(st.editando ? st.editando.usuario_nome : usuarioNome())}</strong></div>
            <div class="rh-cand-card__row"><span>Unidade</span><strong>${unidades.length > 1
              ? `<select id="crUnidade">${unidades.map((u) => `<option value="${u.id}"${String(u.id) === String(st.unidadeId) ? " selected" : ""}>${esc(u.nome)}</option>`).join("")}</select>`
              : esc(unidadeNome(st.unidadeId))}</strong></div>
            <div class="rh-cand-card__row"><span>Data</span><strong>${esc(q?.data || "Entra ao salvar")}</strong></div>
            <div class="rh-cand-card__row"><span>Hora</span><strong>${esc(q?.hora || "Entra ao salvar")}</strong></div>
          </div>
        </div>
      </article>
      <article class="rh-cand-card cr-form">
        <h3 class="rh-cand-card__name">${st.editando ? "Editar lista" : "Nova lista"}</h3>
        <label>Nome da lista<input type="text" id="crNome" maxlength="160" value="${esc(st.nome)}" placeholder="Ex.: Feira de sábado" /></label>
        <div class="cr-grid">
          <div class="cr-produto-campo">
            <label for="crProduto">Produto</label>
            <input type="text" id="crProduto" autocomplete="off" role="combobox" aria-autocomplete="list" aria-controls="crSugestoes" aria-expanded="false" value="${esc(st.novo.produto)}" placeholder="Digite para buscar ou escreva um novo" />
            <ul class="cr-sugestoes hidden" id="crSugestoes" role="listbox"></ul>
          </div>
          <label>Quantidade<input type="text" id="crQuantidade" inputmode="decimal" value="${esc(st.novo.quantidade)}" /></label>
          <label>Tipo<select id="crTipo">${tiposOpts}</select></label>
          <label>Observação<input type="text" id="crObservacao" maxlength="255" value="${esc(st.novo.observacao)}" placeholder="Opcional" /></label>
        </div>
        <div class="rh-cand-card__actions"><button type="button" class="btn primary" id="crAdicionar">Adicionar produto</button></div>
        <div class="rh-cand-list cr-rascunho" id="crRascunho">${htmlRascunho()}</div>
        <div class="rh-cand-card__actions">
          <button type="button" class="btn primary" id="crSalvar">${st.editando ? "Salvar alterações" : "Salvar lista"}</button>
          ${st.editando ? '<button type="button" class="btn neutral" id="crCancelar">Cancelar</button>' : ""}
        </div>
      </article>
      <div class="cr-listas-head">
        <h3>Listas</h3>
        <div class="cr-filtros">${[["aberta", "Abertas"], ["feita", "Feitas"], ["todas", "Todas"]].map(([k, l]) => `<button type="button" class="btn ${st.filtro === k ? "primary" : "neutral"}" data-cr-filtro="${k}">${l}</button>`).join("")}</div>
      </div>
      <div class="rh-cand-list" id="crListas">${htmlListas()}</div>
    </div>`;
    preencherLogos(root);
    ligarEventos(root.firstElementChild);
  }

  function adicionarItem() {
    const nome = st.novo.produto.trim();
    const qtd = Number(String(st.novo.quantidade).replace(",", "."));
    if (!nome) return toast("Informe o produto.", "warning");
    if (!Number.isFinite(qtd) || qtd <= 0) return toast("Informe a quantidade.", "warning");
    const cadastrado = (st.meta.produtos || []).find((p) => semAcento(p.nome) === semAcento(nome));
    st.rascunho.push({ produto_id: st.novo.produtoId || cadastrado?.id || null, produto_nome: nome, quantidade: qtd, tipo: st.novo.tipo, observacao: st.novo.observacao.trim() });
    st.novo = { produto: "", produtoId: null, quantidade: "", tipo: "UN", observacao: "" };
    render();
    $("crProduto")?.focus();
  }

  async function salvar() {
    if (!st.nome.trim()) return toast("Informe o nome da lista.", "warning");
    if (!st.rascunho.length) return toast("Informe pelo menos um produto.", "warning");
    const corpo = { nome: st.nome.trim(), unidade_id: st.unidadeId ? Number(st.unidadeId) : null, itens: st.rascunho };
    try {
      const r = st.editando ? await send(`/${st.editando.id}`, "PUT", corpo) : await send("", "POST", corpo);
      toast(r.message || "Lista salva.", "success");
      limparFormulario();
      await recarregarListas();
    } catch (e) {
      toast(e?.message || "Não foi possível salvar.", "error");
    }
  }

  async function recarregarListas() {
    st.listas = await api(`?status=${encodeURIComponent(st.filtro)}`);
    render();
  }

  const semAcento = (s) => String(s || "").normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().trim();
  const sugestoes = { itens: [], ativo: -1 };

  function buscarProdutos(termo) {
    const t = semAcento(termo);
    if (!t) return [];
    const palavras = t.split(/\s+/);
    return (st.meta.produtos || [])
      .map((p) => ({ p, nome: semAcento(p.nome) }))
      .filter(({ nome }) => palavras.every((w) => nome.includes(w)))
      .sort((a, b) => Number(!a.nome.startsWith(t)) - Number(!b.nome.startsWith(t)) || a.nome.localeCompare(b.nome))
      .slice(0, 8)
      .map(({ p }) => p);
  }

  function destacar(nome, termo) {
    const t = semAcento(termo);
    const i = t ? semAcento(nome).indexOf(t) : -1;
    if (i < 0) return esc(nome);
    return `${esc(nome.slice(0, i))}<strong>${esc(nome.slice(i, i + t.length))}</strong>${esc(nome.slice(i + t.length))}`;
  }

  function mostrarSugestoes(termo) {
    const ul = $("crSugestoes");
    const input = $("crProduto");
    if (!ul || !input) return;
    sugestoes.itens = buscarProdutos(termo);
    sugestoes.ativo = -1;
    if (!sugestoes.itens.length) {
      esconderSugestoes();
      return;
    }
    ul.innerHTML = sugestoes.itens
      .map((p, i) => `<li role="option" id="crSug${i}" data-cr-sug="${i}">${destacar(p.nome, termo)}${p.unidade_base ? ` <small>${esc(tipoSigla(TIPO_POR_UNIDADE_BASE[String(p.unidade_base).toUpperCase()] || p.unidade_base))}</small>` : ""}</li>`)
      .join("");
    ul.classList.remove("hidden");
    input.setAttribute("aria-expanded", "true");
  }

  function esconderSugestoes() {
    const ul = $("crSugestoes");
    if (ul) {
      ul.classList.add("hidden");
      ul.innerHTML = "";
    }
    $("crProduto")?.setAttribute("aria-expanded", "false");
    sugestoes.itens = [];
    sugestoes.ativo = -1;
  }

  function marcarSugestao(indice) {
    const ul = $("crSugestoes");
    if (!ul || !sugestoes.itens.length) return;
    sugestoes.ativo = (indice + sugestoes.itens.length) % sugestoes.itens.length;
    ul.querySelectorAll("[data-cr-sug]").forEach((li, i) => li.classList.toggle("ativo", i === sugestoes.ativo));
    ul.querySelector(".ativo")?.scrollIntoView({ block: "nearest" });
    $("crProduto")?.setAttribute("aria-activedescendant", `crSug${sugestoes.ativo}`);
  }

  function aplicarTipoDoProduto(p) {
    const tipo = p ? TIPO_POR_UNIDADE_BASE[String(p.unidade_base || "").toUpperCase()] : null;
    if (!tipo) return;
    st.novo.tipo = tipo;
    const sel = $("crTipo");
    if (sel) sel.value = tipo;
  }

  function escolherSugestao(indice) {
    const p = sugestoes.itens[indice];
    if (!p) return;
    st.novo.produto = p.nome;
    st.novo.produtoId = p.id;
    const input = $("crProduto");
    if (input) input.value = p.nome;
    aplicarTipoDoProduto(p);
    esconderSugestoes();
    $("crQuantidade")?.focus();
  }

  function ligarEventos(wrap) {
    wrap.addEventListener("input", (ev) => {
      const el = ev.target;
      if (el.id === "crNome") st.nome = el.value;
      else if (el.id === "crProduto") {
        st.novo.produto = el.value;
        st.novo.produtoId = null;
        aplicarTipoDoProduto((st.meta.produtos || []).find((x) => semAcento(x.nome) === semAcento(el.value)));
        mostrarSugestoes(el.value);
      } else if (el.id === "crQuantidade") st.novo.quantidade = el.value;
      else if (el.id === "crObservacao") st.novo.observacao = el.value;
    });
    wrap.addEventListener("change", (ev) => {
      const el = ev.target;
      if (el.id === "crTipo") st.novo.tipo = el.value;
      else if (el.id === "crUnidade") {
        st.unidadeId = el.value;
        const logo = wrap.querySelector(".cr-cabecalho-card [data-logo-unidade]");
        if (logo) {
          logo.dataset.logoUnidade = el.value;
          preencherLogos(wrap.querySelector(".cr-cabecalho-card"));
        }
      }
    });
    wrap.addEventListener("keydown", (ev) => {
      const abertas = ev.target.id === "crProduto" && sugestoes.itens.length > 0;
      if (abertas && (ev.key === "ArrowDown" || ev.key === "ArrowUp")) {
        ev.preventDefault();
        marcarSugestao(sugestoes.ativo + (ev.key === "ArrowDown" ? 1 : -1));
        return;
      }
      if (abertas && ev.key === "Escape") {
        esconderSugestoes();
        return;
      }
      if (abertas && (ev.key === "Enter" || ev.key === "Tab") && sugestoes.ativo >= 0) {
        ev.preventDefault();
        escolherSugestao(sugestoes.ativo);
        return;
      }
      if (ev.key === "Enter" && ["crProduto", "crQuantidade", "crObservacao"].includes(ev.target.id)) {
        ev.preventDefault();
        esconderSugestoes();
        adicionarItem();
      }
    });
    wrap.addEventListener("pointerdown", (ev) => {
      const li = ev.target.closest("[data-cr-sug]");
      if (!li) return;
      ev.preventDefault();
      escolherSugestao(Number(li.dataset.crSug));
    });
    wrap.addEventListener("focusout", (ev) => {
      if (ev.target.id === "crProduto") setTimeout(esconderSugestoes, 120);
    });
    wrap.addEventListener("focusin", (ev) => {
      if (ev.target.id === "crProduto" && ev.target.value.trim()) mostrarSugestoes(ev.target.value);
    });
    wrap.addEventListener("click", async (ev) => {
      const t = ev.target;
      if (t.closest("#crAdicionar")) return adicionarItem();
      if (t.closest("#crSalvar")) return salvar();
      if (t.closest("#crCancelar")) {
        limparFormulario();
        return render();
      }
      const tirar = t.closest("[data-cr-tirar]")?.dataset.crTirar;
      if (tirar != null) {
        st.rascunho.splice(Number(tirar), 1);
        $("crRascunho").innerHTML = htmlRascunho();
        return;
      }
      const filtro = t.closest("[data-cr-filtro]")?.dataset.crFiltro;
      if (filtro) {
        st.filtro = filtro;
        try {
          await recarregarListas();
        } catch (e) {
          toast(e?.message || "Falha ao carregar.", "error");
        }
        return;
      }
      const btn = t.closest("[data-cr-acao]");
      if (!btn) return;
      const lista = st.listas.find((l) => String(l.id) === btn.closest("[data-cr-id]")?.dataset.crId);
      if (!lista) return;
      const acao = btn.dataset.crAcao;
      try {
        if (acao === "abrir") abrirPreview(lista);
        else if (acao === "pdf") imprimirPdf(lista);
        else if (acao === "whatsapp") pedirNumeroEEnviar(lista);
        else if (acao === "editar") {
          st.editando = lista;
          st.nome = lista.nome || "";
          st.unidadeId = String(lista.unidade_id || "");
          st.rascunho = lista.itens.map((it) => ({ produto_id: it.produto_id, produto_nome: it.produto_nome, quantidade: it.quantidade, tipo: it.tipo, observacao: it.observacao || "" }));
          render();
          $("crNome")?.scrollIntoView({ behavior: "smooth", block: "center" });
        } else if (acao === "status") {
          const r = await send(`/${lista.id}`, "PUT", { status: lista.status === "feita" ? "aberta" : "feita" });
          toast(lista.status === "feita" ? "Lista aberta de novo." : "Lista marcada como feita.", "success");
          if (st.editando?.id === lista.id) st.editando = r.lista;
          await recarregarListas();
        } else if (acao === "excluir") {
          if (!confirm(`Excluir a lista "${lista.nome}" de ${quando(lista.created_at).data}?`)) return;
          const r = await api(`/${lista.id}`, { method: "DELETE" });
          toast(r.message || "Lista excluída.", "success");
          if (st.editando?.id === lista.id) limparFormulario();
          await recarregarListas();
        }
      } catch (e) {
        toast(e?.message || "Falha na operação.", "error");
      }
    });
  }

  async function loadComprasRapidas() {
    const root = $("comprasRapidasRoot");
    if (!root) return;
    if (!st.meta) root.innerHTML = '<div class="caixa-page-wrap cr-wrap"><p class="rh-cand-empty">Carregando...</p></div>';
    try {
      st.meta = await api("/meta");
      if (!st.editando && !st.rascunho.length && !st.nome) limparFormulario();
      st.listas = await api(`?status=${encodeURIComponent(st.filtro)}`);
      render();
    } catch (e) {
      root.innerHTML = `<div class="caixa-page-wrap cr-wrap"><p class="rh-cand-empty">${esc(e?.message || "Falha ao carregar.")}</p></div>`;
    }
  }

  window.loadComprasRapidas = loadComprasRapidas;
})();
