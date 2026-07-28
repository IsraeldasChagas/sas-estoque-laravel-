/* Módulo 6 — Venda fiscal / trava CNPJ (API real; PDV protótipo pode integrar) */
(function () {
  const esc = (s) => (window.escapeHtml ? window.escapeHtml(String(s ?? "")) : String(s ?? ""));

  async function fFetch(path, opts = {}) {
    const base = window.API_BASE || "/api";
    const headers = { "Content-Type": "application/json", ...(opts.headers || {}) };
    const uid = window.getUser?.()?.id;
    if (uid) headers["X-Usuario-Id"] = String(uid);
    const res = await fetch(`${base}${path}`, { ...opts, headers });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.error || data.message || `HTTP ${res.status}`);
    return data;
  }

  let cart = [];
  let unidades = [];
  let produtos = [];

  async function loadAux() {
    try {
      unidades = await fFetch("/unidades");
    } catch {
      unidades = window.state?.unidades || [];
    }
    try {
      produtos = await fFetch("/produtos");
    } catch {
      produtos = window.state?.produtos || [];
    }
  }

  window.fiscalPdvConfirmarPagamento = async function ({ unidadeId, formaPagamento, itens }) {
    if (!unidadeId || !itens?.length) {
      throw new Error("Informe unidade e itens com produto_id do estoque.");
    }
    return fFetch("/fiscal/vendas", {
      method: "POST",
      body: JSON.stringify({
        unidade_id: Number(unidadeId),
        forma_pagamento: formaPagamento || "PDV",
        pdv_terminal: "PDV-WEB",
        itens: itens.map((i) => ({
          produto_id: Number(i.produto_id || i.produtoId),
          quantidade: Number(i.quantidade || i.qtd),
          preco_unitario: Number(i.preco_unitario || i.preco),
          desconto: Number(i.desconto || 0),
        })),
      }),
    });
  };

  async function renderPage() {
    const root = document.getElementById("fiscalVendaPdvRoot");
    if (!root) return;
    await loadAux();
    root.innerHTML = `
      <div class="fiscal-venda-wrap">
        <div class="fiscal-venda-panel">
          <h3>Venda com estoque fiscal (mesmo CNPJ do PDV/unidade)</h3>
          <div class="fiscal-venda-grid">
            <label>Unidade (PDV) <select id="fvUnidade"><option value="">—</option>${unidades.map((u) => `<option value="${esc(u.id)}">${esc(u.nome)}</option>`).join("")}</select></label>
            <label>Produto <select id="fvProduto"><option value="">—</option>${produtos.filter((p) => p.ativo !== 0).map((p) => `<option value="${esc(p.id)}" data-preco="${esc(p.preco_venda || 0)}">${esc(p.nome)}</option>`).join("")}</select></label>
            <label>Qtd <input type="number" id="fvQtd" step="0.001" min="0.001" value="1" /></label>
            <label>Preço <input type="number" id="fvPreco" step="0.01" min="0" /></label>
          </div>
          <button type="button" class="btn" id="fvAdd">Adicionar</button>
          <ul id="fvCart"></ul>
          <button type="button" class="btn primary" id="fvFinalizar">Finalizar venda</button>
          <p id="fvMsg" class="subtle-text"></p>
        </div>
        <div class="table-card" id="fvPainel"></div>
      </div>`;

    root.querySelector("#fvProduto")?.addEventListener("change", (e) => {
      const opt = e.target.selectedOptions[0];
      const preco = opt?.dataset?.preco;
      if (preco) root.querySelector("#fvPreco").value = preco;
    });

    root.querySelector("#fvAdd")?.addEventListener("click", async () => {
      const msg = root.querySelector("#fvMsg");
      msg.textContent = "";
      const unidadeId = Number(root.querySelector("#fvUnidade").value);
      const produtoId = Number(root.querySelector("#fvProduto").value);
      const qtd = Number(root.querySelector("#fvQtd").value);
      const preco = Number(root.querySelector("#fvPreco").value);
      if (!unidadeId || !produtoId || qtd <= 0) {
        msg.textContent = "Preencha unidade, produto e quantidade.";
        return;
      }
      try {
        await fFetch("/fiscal/vendas/validar-item", {
          method: "POST",
          body: JSON.stringify({ unidade_id: unidadeId, produto_id: produtoId, quantidade: qtd }),
        });
        const nome = produtos.find((p) => String(p.id) === String(produtoId))?.nome || `#${produtoId}`;
        cart.push({ produto_id: produtoId, nome, quantidade: qtd, preco_unitario: preco || 0 });
        renderCart();
      } catch (e) {
        msg.className = "fiscal-venda-alert";
        msg.textContent = e.message;
      }
    });

    root.querySelector("#fvFinalizar")?.addEventListener("click", async () => {
      const msg = root.querySelector("#fvMsg");
      const unidadeId = Number(root.querySelector("#fvUnidade").value);
      if (!cart.length || !unidadeId) {
        msg.textContent = "Carrinho vazio ou unidade não selecionada.";
        return;
      }
      try {
        const r = await window.fiscalPdvConfirmarPagamento({ unidadeId, formaPagamento: "PDV", itens: cart });
        msg.className = "";
        msg.textContent = `Venda #${r.venda_id} — ${r.valor_liquido}${r.emissao?.emitida ? " · NFC-e OK" : r.emissao?.mensagem ? " · " + r.emissao.mensagem : r.emissao?.motivo_skip ? " · " + r.emissao.motivo_skip : ""}`;
        cart = [];
        renderCart();
        loadPainel();
      } catch (e) {
        msg.className = "fiscal-venda-alert";
        msg.textContent = e.message;
      }
    });

    function renderCart() {
      const ul = root.querySelector("#fvCart");
      if (!ul) return;
      ul.innerHTML = cart.map((c, i) => `<li>${esc(c.nome)} × ${esc(c.quantidade)} — ${esc(c.preco_unitario)} <button type="button" data-i="${i}">✕</button></li>`).join("") || "<li class=\"subtle-text\">Carrinho vazio</li>";
      ul.querySelectorAll("button[data-i]").forEach((b) => {
        b.addEventListener("click", () => {
          cart.splice(Number(b.dataset.i), 1);
          renderCart();
        });
      });
    }

    async function loadPainel() {
      const el = root.querySelector("#fvPainel");
      if (!el) return;
      const data = await fFetch("/fiscal/painel/vendas");
      el.innerHTML = `<header><h3>Painel fiscal de vendas</h3></header>
        <p>Receita líquida: <strong>${esc(data.totais?.receita_liquida)}</strong> · Custo: ${esc(data.totais?.custo)} · Vendas: ${esc(data.totais?.quantidade)}</p>`;
    }
    loadPainel();
  }

  window.loadFiscalVendaPdv = renderPage;
})();
