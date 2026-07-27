/**
 * Comercial / PDV — PROTÓTIPO VISUAL APENAS.
 * Sem chamadas de API, sem persistência. Ações simuladas em memória.
 */
(function () {
  "use strict";

  const DADOS_DEMONSTRACAO_PDV = {
    unidades: [
      { id: 1, nome: "Matriz — Centro" },
      { id: 2, nome: "Filial — Batista Campos" },
      { id: 3, nome: "Filial — Marco" },
    ],
    mesas: [
      { id: 1, numero: 1, unidadeId: 1, capacidade: 4, status: "livre", pessoas: 0, garcomId: null, abertoEm: null, totalParcial: 0, pedidoId: null },
      { id: 2, numero: 2, unidadeId: 1, capacidade: 4, status: "ocupada", pessoas: 3, garcomId: 1, abertoEm: "18:42", totalParcial: 156.4, pedidoId: 101 },
      { id: 3, numero: 3, unidadeId: 1, capacidade: 6, status: "reservada", pessoas: 0, garcomId: null, abertoEm: null, totalParcial: 0, pedidoId: null, reserva: "19:30 — Silva" },
      { id: 4, numero: 4, unidadeId: 2, capacidade: 2, status: "aguardando_pedido", pessoas: 2, garcomId: 2, abertoEm: "19:05", totalParcial: 48.0, pedidoId: 102 },
      { id: 5, numero: 5, unidadeId: 1, capacidade: 4, status: "em_producao", pessoas: 4, garcomId: 1, abertoEm: "18:55", totalParcial: 210.5, pedidoId: 103 },
      { id: 6, numero: 6, unidadeId: 2, capacidade: 8, status: "aguardando_pagamento", pessoas: 6, garcomId: 3, abertoEm: "17:30", totalParcial: 389.9, pedidoId: 104 },
      { id: 7, numero: 7, unidadeId: 1, capacidade: 4, status: "limpeza", pessoas: 0, garcomId: null, abertoEm: null, totalParcial: 0, pedidoId: null },
      { id: 8, numero: 8, unidadeId: 3, capacidade: 4, status: "bloqueada", pessoas: 0, garcomId: null, abertoEm: null, totalParcial: 0, pedidoId: null },
      { id: 9, numero: 9, unidadeId: 1, capacidade: 6, status: "ocupada", pessoas: 5, garcomId: 2, abertoEm: "19:18", totalParcial: 92.0, pedidoId: 105 },
      { id: 10, numero: 10, unidadeId: 2, capacidade: 2, status: "livre", pessoas: 0, garcomId: null, abertoEm: null, totalParcial: 0, pedidoId: null },
    ],
    categorias: [
      { id: "todos", nome: "Todos" },
      { id: "pratos", nome: "Pratos" },
      { id: "porcoes", nome: "Porções" },
      { id: "bebidas", nome: "Bebidas" },
      { id: "sobremesas", nome: "Sobremesas" },
    ],
    produtos: [
      { id: 1, nome: "Tacacá", categoria: "pratos", preco: 28.9, favorito: true, disponivel: true },
      { id: 2, nome: "Maniçoba", categoria: "pratos", preco: 42.5, favorito: true, disponivel: true },
      { id: 3, nome: "Filé ao molho", categoria: "pratos", preco: 58.0, favorito: false, disponivel: true },
      { id: 4, nome: "Caldo de piranha", categoria: "pratos", preco: 35.0, favorito: false, disponivel: false },
      { id: 5, nome: "Porção de camarão", categoria: "porcoes", preco: 65.0, favorito: true, disponivel: true },
      { id: 6, nome: "Porção de mandioca", categoria: "porcoes", preco: 18.0, favorito: false, disponivel: true },
      { id: 7, nome: "Suco de cupuaçu", categoria: "bebidas", preco: 12.0, favorito: true, disponivel: true },
      { id: 8, nome: "Cerveja 600ml", categoria: "bebidas", preco: 14.0, favorito: false, disponivel: true },
      { id: 9, nome: "Água mineral", categoria: "bebidas", preco: 5.0, favorito: false, disponivel: true },
      { id: 10, nome: "Mousse de cupuaçu", categoria: "sobremesas", preco: 16.0, favorito: true, disponivel: true },
      { id: 11, nome: "Pudim", categoria: "sobremesas", preco: 14.0, favorito: false, disponivel: true },
      { id: 12, nome: "Café expresso", categoria: "bebidas", preco: 6.0, favorito: false, disponivel: true },
    ],
    garcons: [
      { id: 1, nome: "Ana Paula" },
      { id: 2, nome: "Carlos Mendes" },
      { id: 3, nome: "Juliana Rocha" },
      { id: 4, nome: "Pedro Alves" },
    ],
    clientes: [
      { id: 1, nome: "Maria Silva", telefone: "(91) 98888-1111", whatsapp: "(91) 98888-1111", cpf: "", nasc: "1990-05-12", obs: "Cliente frequente", preferencia: "Sem pimenta", restricao: "Lactose", visitas: 12, ultima: "2026-07-10", totalGasto: 1840.5 },
      { id: 2, nome: "João Santos", telefone: "(91) 97777-2222", whatsapp: "(91) 97777-2222", cpf: "123.456.789-00", nasc: "1985-11-02", obs: "", preferencia: "Mesa janela", restricao: "", visitas: 5, ultima: "2026-07-12", totalGasto: 620.0 },
      { id: 3, nome: "Empresa Norte Ltda", telefone: "(91) 3333-4444", whatsapp: "(91) 98888-0000", cpf: "", nasc: "", obs: "CNPJ faturamento", preferencia: "Nota fiscal", restricao: "", visitas: 28, ultima: "2026-07-14", totalGasto: 15290.0 },
      { id: 4, nome: "Fernanda Costa", telefone: "(91) 96666-3333", whatsapp: "(91) 96666-3333", cpf: "", nasc: "1998-03-21", obs: "", preferencia: "Sobremesa cupuaçu", restricao: "Glúten", visitas: 3, ultima: "2026-07-08", totalGasto: 210.0 },
    ],
    pedidos: [
      { id: 101, mesa: 2, garcom: "Ana Paula", status: "aberto", total: 156.4, itens: 4, hora: "18:42" },
      { id: 102, mesa: 4, garcom: "Carlos Mendes", status: "aguardando", total: 48.0, itens: 2, hora: "19:05" },
      { id: 103, mesa: 5, garcom: "Ana Paula", status: "producao", total: 210.5, itens: 6, hora: "18:55" },
      { id: 104, mesa: 6, garcom: "Juliana Rocha", status: "fechamento", total: 389.9, itens: 11, hora: "17:30" },
      { id: 105, mesa: 9, garcom: "Carlos Mendes", status: "aberto", total: 92.0, itens: 3, hora: "19:18" },
    ],
    vendas: [
      { id: 501, data: "2026-07-15", hora: "14:22", unidade: "Matriz — Centro", mesa: 12, cliente: "Maria Silva", operador: "Caixa 01", total: 245.8, forma: "Pix", status: "finalizada", garcom: "Ana Paula" },
      { id: 502, data: "2026-07-15", hora: "15:10", unidade: "Matriz — Centro", mesa: 4, cliente: "Balcão", operador: "Caixa 01", total: 89.5, forma: "Débito", status: "finalizada", garcom: "Pedro Alves" },
      { id: 503, data: "2026-07-14", hora: "20:45", unidade: "Filial — Batista Campos", mesa: 6, cliente: "Empresa Norte Ltda", operador: "Caixa 02", total: 512.0, forma: "Crédito", status: "finalizada", garcom: "Juliana Rocha" },
      { id: 504, data: "2026-07-14", hora: "21:30", unidade: "Matriz — Centro", mesa: 9, cliente: "João Santos", operador: "Caixa 01", total: 178.0, forma: "Dinheiro", status: "cancelada", garcom: "Carlos Mendes" },
    ],
    kds: [
      { id: "k1", pedidoId: 103, mesa: 5, item: "Maniçoba", qtd: 2, setor: "cozinha", col: "novo", prio: true, tempo: "4 min" },
      { id: "k2", pedidoId: 103, mesa: 5, item: "Filé ao molho", qtd: 1, setor: "cozinha", col: "preparo", prio: false, tempo: "12 min" },
      { id: "k3", pedidoId: 101, mesa: 2, item: "Suco de cupuaçu", qtd: 2, setor: "bar", col: "novo", prio: false, tempo: "2 min" },
      { id: "k4", pedidoId: 101, mesa: 2, item: "Cerveja 600ml", qtd: 3, setor: "bar", col: "pronto", prio: false, tempo: "—" },
      { id: "k5", pedidoId: 104, mesa: 6, item: "Mousse de cupuaçu", qtd: 4, setor: "sobremesa", col: "entregue", prio: false, tempo: "—" },
      { id: "k6", pedidoId: 105, mesa: 9, item: "Porção de camarão", qtd: 1, setor: "cozinha", col: "novo", prio: true, tempo: "1 min" },
    ],
    itensCarrinho: [],
  };

  const CPDV_MESA_LABEL = {
    livre: "Livre",
    ocupada: "Ocupada",
    reservada: "Reservada",
    aguardando_pedido: "Aguardando pedido",
    em_producao: "Em produção",
    aguardando_pagamento: "Aguardando pagamento",
    limpeza: "Limpeza",
    bloqueada: "Bloqueada",
  };

  const CPDV_KDS_COLS = [
    { id: "novo", nome: "Novos" },
    { id: "preparo", nome: "Em preparo" },
    { id: "pronto", nome: "Prontos" },
    { id: "entregue", nome: "Entregues" },
  ];

  const cpdvState = {
    cat: "todos",
    cart: [],
    desconto: 0,
    acrescimo: 0,
    cliente: null,
    charts: {},
    mesaSel: null,
    garcomId: null,
    suspensas: [],
    busca: "",
  };

  let cpdvModalBound = false;

  function escHtml(s) {
    return (s ?? "").toString()
      .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
  }

  function moeda(n) {
    const v = Number(n);
    if (!Number.isFinite(v)) return "—";
    return v.toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
  }

  function toast(msg, type) {
    const fn = typeof showToast === "function" ? showToast : window.showToast;
    if (typeof fn === "function") fn(msg, type || "info");
    else alert(msg);
  }

  function protoToast(acao) {
    toast(`Protótipo: ${acao} — sem persistência.`, "info");
  }

  function cpdvRoot(rootId) {
    let el = document.getElementById(rootId);
    if (el) return el;
    const secId = rootId.replace(/Root$/, "Section");
    const sec = document.getElementById(secId);
    if (!sec) return null;
    el = document.createElement("div");
    el.id = rootId;
    el.className = "cpdv-page-wrap";
    sec.appendChild(el);
    return el;
  }

  function cpdvAvisoProto() {
    return '<p class="cpdv-aviso-proto">Protótipo visual — dados fictícios em memória. Nenhuma alteração é salva no servidor.</p>';
  }

  function cpdvDestroyCharts() {
    Object.keys(cpdvState.charts).forEach((k) => {
      if (cpdvState.charts[k]) {
        cpdvState.charts[k].destroy();
        cpdvState.charts[k] = null;
      }
    });
  }

  function ensureCpdvModal() {
    let el = document.getElementById("cpdvModal");
    if (el) return el;
    el = document.createElement("div");
    el.id = "cpdvModal";
    el.className = "modal-backdrop";
    el.innerHTML = `
      <div class="modal">
        <header>
          <h2 id="cpdvModalTitle">Comercial / PDV</h2>
          <button type="button" class="close-btn" id="cpdvModalClose" aria-label="Fechar">×</button>
        </header>
        <div class="modal-body" id="cpdvModalBody"></div>
        <div class="cpdv-modal-actions" id="cpdvModalActions"></div>
      </div>`;
    document.body.appendChild(el);
    return el;
  }

  function openCpdvModal(title, bodyHtml, actionsHtml) {
    ensureCpdvModal();
    document.getElementById("cpdvModalTitle").textContent = title;
    document.getElementById("cpdvModalBody").innerHTML = bodyHtml;
    document.getElementById("cpdvModalActions").innerHTML = actionsHtml || "";
    document.getElementById("cpdvModal").classList.add("active");
  }

  function closeCpdvModal() {
    document.getElementById("cpdvModal")?.classList.remove("active");
  }

  function cpdvSubtotal() {
    return cpdvState.cart.reduce((s, i) => s + i.preco * i.qtd, 0);
  }

  function cpdvTotal() {
    return Math.max(0, cpdvSubtotal() - cpdvState.desconto + cpdvState.acrescimo);
  }

  function cpdvSyncCarrinhoMemoria() {
    DADOS_DEMONSTRACAO_PDV.itensCarrinho = cpdvState.cart.map((i) => ({ ...i }));
  }

  function cpdvAddCart(prodId) {
    const p = DADOS_DEMONSTRACAO_PDV.produtos.find((x) => x.id === prodId);
    if (!p || !p.disponivel) return;
    const ex = cpdvState.cart.find((c) => c.produtoId === prodId);
    if (ex) ex.qtd += 1;
    else cpdvState.cart.push({ produtoId: p.id, nome: p.nome, preco: p.preco, qtd: 1 });
    cpdvSyncCarrinhoMemoria();
    loadComercialPdv();
  }

  function cpdvCartQty(prodId, delta) {
    const item = cpdvState.cart.find((c) => c.produtoId === prodId);
    if (!item) return;
    item.qtd += delta;
    if (item.qtd <= 0) cpdvState.cart = cpdvState.cart.filter((c) => c.produtoId !== prodId);
    cpdvSyncCarrinhoMemoria();
    loadComercialPdv();
  }

  function cpdvRemoveCart(prodId) {
    cpdvState.cart = cpdvState.cart.filter((c) => c.produtoId !== prodId);
    cpdvSyncCarrinhoMemoria();
    loadComercialPdv();
  }

  function cpdvFiltrarProdutos() {
    const q = (cpdvState.busca || "").toLowerCase().trim();
    return DADOS_DEMONSTRACAO_PDV.produtos.filter((p) => {
      if (cpdvState.cat !== "todos" && p.categoria !== cpdvState.cat) return false;
      if (q && !p.nome.toLowerCase().includes(q)) return false;
      return true;
    });
  }

  function cpdvBindPdvEvents(root) {
    root.querySelectorAll("[data-cpdv-cat]").forEach((btn) => {
      btn.addEventListener("click", () => {
        cpdvState.cat = btn.dataset.cpdvCat;
        loadComercialPdv();
      });
    });
    const busca = root.querySelector("#cpdvBuscaProd");
    if (busca) {
      busca.value = cpdvState.busca;
      busca.oninput = () => { cpdvState.busca = busca.value; loadComercialPdv(); };
    }
    root.querySelectorAll("[data-cpdv-add]").forEach((el) => {
      el.addEventListener("click", () => cpdvAddCart(Number(el.dataset.cpdvAdd)));
    });
    root.querySelectorAll("[data-cpdv-qty]").forEach((el) => {
      el.addEventListener("click", () => cpdvCartQty(Number(el.dataset.cpdvProd), Number(el.dataset.cpdvQty)));
    });
    root.querySelectorAll("[data-cpdv-rm]").forEach((el) => {
      el.addEventListener("click", () => cpdvRemoveCart(Number(el.dataset.cpdvRm)));
    });
    root.querySelectorAll("[data-cpdv-proto]").forEach((el) => {
      el.addEventListener("click", () => protoToast(el.dataset.cpdvProto));
    });
    const cliSel = root.querySelector("#cpdvClienteSel");
    if (cliSel) {
      cliSel.value = cpdvState.cliente || "";
      cliSel.onchange = () => { cpdvState.cliente = cliSel.value || null; };
    }
    const uniFiscal = root.querySelector("#cpdvUnidadeFiscal");
    if (uniFiscal && !uniFiscal.dataset.loaded) {
      uniFiscal.dataset.loaded = "1";
      const unis = window.state?.unidades || [];
      unis.forEach((u) => {
        const o = document.createElement("option");
        o.value = String(u.id);
        o.textContent = u.nome || `Unidade ${u.id}`;
        uniFiscal.appendChild(o);
      });
    }
  }

  function cpdvRenderPdv() {
    const root = cpdvRoot("comercialPdvRoot");
    if (!root) return;
    const prods = cpdvFiltrarProdutos();
    const cats = DADOS_DEMONSTRACAO_PDV.categorias.map((c) =>
      `<button type="button" class="cpdv-chip${cpdvState.cat === c.id ? " active" : ""}" data-cpdv-cat="${escHtml(c.id)}">${escHtml(c.nome)}</button>`
    ).join("");
    const grid = prods.map((p) => {
      const off = !p.disponivel ? " cpdv-prod--off" : "";
      const fav = p.favorito ? '<span class="cpdv-prod__fav">★ Favorito</span>' : "";
      const click = p.disponivel ? `data-cpdv-add="${p.id}"` : "";
      return `<button type="button" class="cpdv-prod${off}" ${click} ${p.disponivel ? "" : "disabled"}>
        <p class="cpdv-prod__nome">${escHtml(p.nome)}</p>
        <span class="cpdv-prod__preco">${moeda(p.preco)}</span>${fav}
        ${!p.disponivel ? '<small>Indisponível</small>' : ""}
      </button>`;
    }).join("") || '<p class="subtle-text">Nenhum produto nesta categoria.</p>';
    const cart = cpdvState.cart.length
      ? cpdvState.cart.map((i) => `
        <div class="cpdv-cart-item">
          <div><strong>${escHtml(i.nome)}</strong><br><span>${moeda(i.preco)} × ${i.qtd} = ${moeda(i.preco * i.qtd)}</span></div>
          <div>
            <button type="button" class="btn neutral btn-sm" data-cpdv-qty data-cpdv-prod="${i.produtoId}" data-cpdv-qty="-1">−</button>
            <button type="button" class="btn neutral btn-sm" data-cpdv-qty data-cpdv-prod="${i.produtoId}" data-cpdv-qty="1">+</button>
            <button type="button" class="btn danger btn-sm" data-cpdv-rm="${i.produtoId}">✕</button>
          </div>
        </div>`).join("")
      : '<p class="subtle-text">Carrinho vazio — toque em um produto.</p>';
    const cliOpts = ['<option value="">Consumidor final</option>']
      .concat(DADOS_DEMONSTRACAO_PDV.clientes.map((c) =>
        `<option value="${c.id}"${String(cpdvState.cliente) === String(c.id) ? " selected" : ""}>${escHtml(c.nome)}</option>`
      )).join("");
    root.innerHTML = `
      ${cpdvAvisoProto()}
      <div class="cpdv-pdv-layout">
        <div class="table-card">
          <header><h3>Produtos</h3></header>
          <div class="cpdv-form-body">
            <input type="search" id="cpdvBuscaProd" class="full-width" placeholder="Buscar produto…" />
            <div class="cpdv-pdv-cats">${cats}</div>
            <div class="cpdv-prod-grid">${grid}</div>
          </div>
        </div>
        <div class="table-card">
          <header><h3>Carrinho / PDV</h3></header>
          <div class="cpdv-form-body">
            <label>Cliente
              <select id="cpdvClienteSel">${cliOpts}</select>
            </label>
            <label>Unidade fiscal (estoque real)
              <select id="cpdvUnidadeFiscal"><option value="">— Simulação demo —</option></select>
            </label>
            <p class="form-hint" style="font-size:0.8rem;margin:0 0 0.5rem">Com unidade selecionada, o pagamento usa API fiscal (mesmo CNPJ). Produtos do carrinho devem ser IDs reais do estoque.</p>
            ${cart}
            <div class="cpdv-cart-totais">
              <div>Subtotal: <strong>${moeda(cpdvSubtotal())}</strong></div>
              <div>Desconto: ${moeda(cpdvState.desconto)}</div>
              <div>Acréscimo: ${moeda(cpdvState.acrescimo)}</div>
              <div class="total">Total: ${moeda(cpdvTotal())}</div>
            </div>
            <div class="cpdv-actions">
              <button type="button" class="btn neutral" data-cpdv-proto="Desconto aplicado">Desconto</button>
              <button type="button" class="btn neutral" data-cpdv-proto="Acréscimo aplicado">Acréscimo</button>
              <button type="button" class="btn neutral" data-cpdv-proto="Venda suspensa">Suspender</button>
              <button type="button" class="btn primary" data-cpdv-proto="Pagamento iniciado">Pagar</button>
              <button type="button" class="btn danger" data-cpdv-proto="Carrinho limpo" id="cpdvLimparCart">Limpar</button>
            </div>
          </div>
        </div>
      </div>`;
    root.querySelector("#cpdvLimparCart")?.addEventListener("click", () => {
      cpdvState.cart = [];
      cpdvSyncCarrinhoMemoria();
      loadComercialPdv();
      protoToast("Carrinho limpo");
    });
    cpdvBindPdvEvents(root);
  }

  function cpdvAbrirModalMesa(mesaId) {
    const m = DADOS_DEMONSTRACAO_PDV.mesas.find((x) => x.id === mesaId);
    if (!m) return;
    cpdvState.mesaSel = m.id;
    const garcom = DADOS_DEMONSTRACAO_PDV.garcons.find((g) => g.id === m.garcomId);
    const body = `
      <p><strong>Mesa ${escHtml(m.numero)}</strong> — ${escHtml(CPDV_MESA_LABEL[m.status] || m.status)}</p>
      <p>Capacidade: ${m.capacidade} · Pessoas: ${m.pessoas}</p>
      ${garcom ? `<p>Garçom: ${escHtml(garcom.nome)}</p>` : ""}
      ${m.reserva ? `<p>Reserva: ${escHtml(m.reserva)}</p>` : ""}
      ${m.pedidoId ? `<p>Pedido #${m.pedidoId}</p>` : ""}`;
    const acts = [
      ["Abrir mesa", "abrir"],
      ["+ Pessoas", "+pessoas"],
      ["Lançar pedido", "lançar pedido"],
      ["Comanda", "comanda"],
      ["Transferir", "transferir"],
      ["Juntar", "juntar"],
      ["Dividir", "dividir"],
      ["Pré-conta", "pré-conta"],
      ["Fechar mesa", "fechar mesa"],
    ].map(([lbl, act]) =>
      `<button type="button" class="btn primary" data-cpdv-mesa-act="${escHtml(act)}">${escHtml(lbl)}</button>`
    ).join("");
    openCpdvModal(`Mesa ${m.numero}`, body, acts);
    document.getElementById("cpdvModalActions").querySelectorAll("[data-cpdv-mesa-act]").forEach((btn) => {
      btn.addEventListener("click", () => protoToast(`${btn.dataset.cpdvMesaAct} — mesa ${m.numero}`));
    });
  }

  function cpdvRenderMesas() {
    const root = cpdvRoot("comercialMesasRoot");
    if (!root) return;
    const garOpts = ['<option value="">— Selecione —</option>']
      .concat(DADOS_DEMONSTRACAO_PDV.garcons.map((g) =>
        `<option value="${g.id}"${String(cpdvState.garcomId) === String(g.id) ? " selected" : ""}>${escHtml(g.nome)}</option>`
      )).join("");
    const cards = DADOS_DEMONSTRACAO_PDV.mesas.map((m) => {
      const st = CPDV_MESA_LABEL[m.status] || m.status;
      const gar = DADOS_DEMONSTRACAO_PDV.garcons.find((g) => g.id === m.garcomId);
      const und = DADOS_DEMONSTRACAO_PDV.unidades.find((u) => u.id === m.unidadeId);
      return `<button type="button" class="cpdv-mesa cpdv-mesa--${escHtml(m.status)}" data-cpdv-mesa="${m.id}">
        <h4>Mesa ${escHtml(m.numero)}</h4>
        <span class="cpdv-badge">${escHtml(st)}</span>
        <small>${escHtml(und ? und.nome : "—")}</small>
        <small>Pessoas: ${m.pessoas}</small>
        ${gar ? `<small>Garçom: ${escHtml(gar.nome)}</small>` : ""}
        <small>Abertura: ${escHtml(m.abertoEm || "—")}</small>
        <small>Total parcial: ${moeda(m.totalParcial || 0)}</small>
      </button>`;
    }).join("");
    root.innerHTML = `
      ${cpdvAvisoProto()}
      <div class="cpdv-garcom-bar table-card cpdv-form-body">
        <label>Modo garçom
          <select id="cpdvGarcomSel">${garOpts}</select>
        </label>
        <button type="button" class="btn primary" id="cpdvGarcomAtivar">Ativar modo garçom</button>
      </div>
      <div class="cpdv-mesas-grid">${cards}</div>`;
    root.querySelector("#cpdvGarcomSel").onchange = (e) => { cpdvState.garcomId = e.target.value || null; };
    root.querySelector("#cpdvGarcomAtivar").addEventListener("click", () => {
      const g = DADOS_DEMONSTRACAO_PDV.garcons.find((x) => String(x.id) === String(cpdvState.garcomId));
      protoToast(g ? `Modo garçom: ${g.nome}` : "Selecione um garçom");
    });
    root.querySelectorAll("[data-cpdv-mesa]").forEach((el) => {
      el.addEventListener("click", () => cpdvAbrirModalMesa(Number(el.dataset.cpdvMesa)));
    });
  }

  function cpdvKdsMover(cardId, novaCol) {
    const card = DADOS_DEMONSTRACAO_PDV.kds.find((k) => k.id === cardId);
    if (card) card.col = novaCol;
    loadComercialCozinha();
  }

  function cpdvRenderKdsCard(k) {
    const setorCls = k.setor === "bar" ? "bar" : k.setor === "sobremesa" ? "sobremesa" : "cozinha";
    const prio = k.prio ? '<span class="cpdv-prio">URGENTE</span> ' : "";
    const btns = {
      novo: '<button type="button" class="btn primary btn-sm" data-cpdv-kds-act="preparo" data-cpdv-kds-id="' + k.id + '">Iniciar</button>',
      preparo: '<button type="button" class="btn primary btn-sm" data-cpdv-kds-act="pronto" data-cpdv-kds-id="' + k.id + '">Pronto</button>',
      pronto: '<button type="button" class="btn neutral btn-sm" data-cpdv-kds-act="chamar" data-cpdv-kds-id="' + k.id + '">Chamar</button>',
      entregue: "",
    };
    const extra = k.col === "pronto"
      ? '<button type="button" class="btn primary btn-sm" data-cpdv-kds-act="entregue" data-cpdv-kds-id="' + k.id + '">Entregue</button>'
      : (btns[k.col] || "");
    return `<div class="cpdv-kds-card cpdv-kds-card--${setorCls}">
      ${prio}<strong>Mesa ${k.mesa} · #${k.pedidoId}</strong>
      <div>${k.qtd}× ${escHtml(k.item)}</div>
      <small>${escHtml(k.setor)} · ${escHtml(k.tempo)}</small>
      <div class="cpdv-actions">${extra}</div>
    </div>`;
  }

  function cpdvRenderCozinha() {
    const root = cpdvRoot("comercialCozinhaRoot");
    if (!root) return;
    const cols = CPDV_KDS_COLS.map((col) => {
      const cards = DADOS_DEMONSTRACAO_PDV.kds.filter((k) => k.col === col.id).map(cpdvRenderKdsCard).join("")
        || '<p class="subtle-text">Vazio</p>';
      return `<div class="cpdv-kds-col"><h3>${escHtml(col.nome)}</h3>${cards}</div>`;
    }).join("");
    root.innerHTML = `${cpdvAvisoProto()}<div class="cpdv-kds">${cols}</div>`;
    root.querySelectorAll("[data-cpdv-kds-act]").forEach((btn) => {
      btn.addEventListener("click", () => {
        const act = btn.dataset.cpdvKdsAct;
        const id = btn.dataset.cpdvKdsId;
        if (act === "chamar") protoToast("Garçom chamado");
        else if (act === "preparo" || act === "pronto" || act === "entregue") {
          protoToast(`Item ${act}`);
          cpdvKdsMover(id, act);
        }
      });
    });
  }

  function cpdvRenderDashboardCharts() {
    if (typeof Chart === "undefined") return;
    cpdvDestroyCharts();
    const vendasDia = [4200, 5100, 3800, 6200, 5900, 7100, 4850];
    const labels = ["Seg", "Ter", "Qua", "Qui", "Sex", "Sáb", "Dom"];
    const mk = (id, cfg) => {
      const cv = document.getElementById(id);
      if (!cv) return;
      cpdvState.charts[id] = new Chart(cv, cfg);
    };
    mk("cpdvChartVendas", {
      type: "line",
      data: {
        labels,
        datasets: [{ label: "Vendas (R$)", data: vendasDia, borderColor: "#1565c0", backgroundColor: "rgba(21,101,192,0.15)", fill: true }],
      },
      options: { responsive: true, plugins: { legend: { display: false } } },
    });
    const porCat = DADOS_DEMONSTRACAO_PDV.categorias.filter((c) => c.id !== "todos");
    mk("cpdvChartCategorias", {
      type: "doughnut",
      data: {
        labels: porCat.map((c) => c.nome),
        datasets: [{ data: [35, 22, 28, 15], backgroundColor: ["#3949ab", "#ef6c00", "#00897b", "#8e24aa"] }],
      },
      options: { responsive: true },
    });
  }

  function cpdvRenderDashboard() {
    const root = cpdvRoot("comercialDashboardRoot");
    if (!root) return;
    const vendasHoje = DADOS_DEMONSTRACAO_PDV.vendas.filter((v) => v.data === "2026-07-15");
    const totalHoje = vendasHoje.reduce((s, v) => s + v.total, 0);
    const mesasOcup = DADOS_DEMONSTRACAO_PDV.mesas.filter((m) => !["livre", "bloqueada", "limpeza"].includes(m.status)).length;
    root.innerHTML = `
      ${cpdvAvisoProto()}
      <div class="cpdv-cards">
        <div class="cpdv-card"><span>Vendas hoje</span><strong>${moeda(totalHoje)}</strong></div>
        <div class="cpdv-card"><span>Pedidos abertos</span><strong>${DADOS_DEMONSTRACAO_PDV.pedidos.length}</strong></div>
        <div class="cpdv-card"><span>Mesas ocupadas</span><strong>${mesasOcup}/${DADOS_DEMONSTRACAO_PDV.mesas.length}</strong></div>
        <div class="cpdv-card"><span>Ticket médio</span><strong>${moeda(totalHoje / (vendasHoje.length || 1))}</strong></div>
      </div>
      <div class="cpdv-charts">
        <div class="cpdv-chart-box"><h4>Vendas da semana</h4><canvas id="cpdvChartVendas"></canvas></div>
        <div class="cpdv-chart-box"><h4>Mix por categoria</h4><canvas id="cpdvChartCategorias"></canvas></div>
      </div>
      <div class="table-card">
        <header><h3>Últimas vendas</h3></header>
        <div class="table-wrap"><table class="data-table">
          <thead><tr><th>Hora</th><th>Total</th><th>Forma</th><th>Garçom</th></tr></thead>
          <tbody>${DADOS_DEMONSTRACAO_PDV.vendas.slice(0, 5).map((v) =>
            `<tr><td>${escHtml(v.hora)}</td><td>${moeda(v.total)}</td><td>${escHtml(v.forma)}</td><td>${escHtml(v.garcom)}</td></tr>`
          ).join("")}</tbody>
        </table></div>
      </div>`;
    cpdvRenderDashboardCharts();
  }

  function cpdvStatusBadge(st) {
    const map = { aberto: "ok", aguardando: "warn", producao: "warn", fechamento: "danger" };
    const lbl = { aberto: "Aberto", aguardando: "Aguardando", producao: "Em produção", fechamento: "Fechamento" };
    return `<span class="cpdv-badge cpdv-badge--${map[st] || "muted"}">${escHtml(lbl[st] || st)}</span>`;
  }

  function cpdvRenderPedidos() {
    const root = cpdvRoot("comercialPedidosRoot");
    if (!root) return;
    const rows = DADOS_DEMONSTRACAO_PDV.pedidos.map((p) =>
      `<tr>
        <td>#${p.id}</td><td>Mesa ${p.mesa}</td><td>${escHtml(p.garcom)}</td>
        <td>${cpdvStatusBadge(p.status)}</td><td>${p.itens}</td><td>${moeda(p.total)}</td><td>${escHtml(p.hora)}</td>
        <td><button type="button" class="btn neutral btn-sm" data-cpdv-proto="Pedido #${p.id} visualizado">Ver</button></td>
      </tr>`
    ).join("");
    root.innerHTML = `
      ${cpdvAvisoProto()}
      <div class="table-card">
        <header><h3>Pedidos em andamento</h3></header>
        <div class="table-wrap"><table class="data-table">
          <thead><tr><th>#</th><th>Mesa</th><th>Garçom</th><th>Status</th><th>Itens</th><th>Total</th><th>Hora</th><th></th></tr></thead>
          <tbody>${rows}</tbody>
        </table></div>
      </div>`;
    root.querySelectorAll("[data-cpdv-proto]").forEach((el) => {
      el.addEventListener("click", () => protoToast(el.dataset.cpdvProto));
    });
  }

  function cpdvAbrirModalPagamento() {
    const total = cpdvTotal() || 189.5;
    const body = `
      <p>Total a receber: <strong>${moeda(total)}</strong></p>
      <div class="filters-grid">
        <label>Forma<select id="cpdvPgtoForma">
          <option>Dinheiro</option><option>PIX</option><option>Débito</option><option>Crédito</option>
          <option>Voucher</option><option>Vale-consumo</option><option>Cortesia</option><option>Múltiplas formas</option>
        </select></label>
        <label>Valor recebido<input type="text" id="cpdvPgtoValor" value="${total.toFixed(2)}" /></label>
        <label>Troco<input type="text" value="0,00" readonly /></label>
        <label>Bandeira<input type="text" placeholder="Visa / Master" /></label>
        <label>Operadora<input type="text" placeholder="Rede / Cielo" /></label>
        <label>Parcelas<input type="number" min="1" value="1" /></label>
        <label>NSU<input type="text" placeholder="000000" /></label>
        <label>Autorização<input type="text" /></label>
        <label>Observação<input type="text" /></label>
      </div>
      <div class="cpdv-actions" style="margin-top:.5rem">
        <button type="button" class="btn neutral" data-cpdv-proto="Pagamento parcial">Parcial</button>
        <button type="button" class="btn neutral" data-cpdv-proto="Divisão por pessoa">Dividir pessoas</button>
        <button type="button" class="btn neutral" data-cpdv-proto="Divisão por item">Dividir itens</button>
      </div>`;
    const acts = `<button type="button" class="btn primary" id="cpdvPgtoConfirm">Confirmar pagamento</button>
      <button type="button" class="btn neutral" id="cpdvPgtoCancel">Cancelar</button>`;
    openCpdvModal("Simular pagamento", body, acts);
    document.getElementById("cpdvPgtoConfirm")?.addEventListener("click", async () => {
      const unidadeEl = document.getElementById("cpdvUnidadeFiscal");
      const unidadeId = unidadeEl ? Number(unidadeEl.value) : 0;
      const forma = document.getElementById("cpdvPgtoForma")?.value || "PDV";
      if (unidadeId > 0 && cpdvState.cart.length && typeof window.fiscalPdvConfirmarPagamento === "function") {
        try {
          const itens = cpdvState.cart.map((i) => ({
            produto_id: i.estoqueProdutoId || i.produtoId,
            quantidade: i.qtd,
            preco_unitario: i.preco,
          }));
          const r = await window.fiscalPdvConfirmarPagamento({ unidadeId, formaPagamento: forma, itens });
          toast(`Venda fiscal #${r.venda_id} registrada.`, "success");
          cpdvState.cart = [];
          cpdvSyncCarrinhoMemoria();
          closeCpdvModal();
          loadComercialPdv?.();
          return;
        } catch (e) {
          toast(e.message || "Venda fiscal bloqueada.", "error");
          return;
        }
      }
      protoToast("Pagamento confirmado (simulado)");
      closeCpdvModal();
    });
    document.getElementById("cpdvPgtoCancel")?.addEventListener("click", closeCpdvModal);
    document.getElementById("cpdvModalBody")?.querySelectorAll("[data-cpdv-proto]").forEach((el) => {
      el.addEventListener("click", () => protoToast(el.dataset.cpdvProto));
    });
  }

  function cpdvRenderPagamentos() {
    const root = cpdvRoot("comercialPagamentosRoot");
    if (!root) return;
    root.innerHTML = `
      ${cpdvAvisoProto()}
      <div class="cpdv-cards">
        <div class="cpdv-card"><span>Recebido hoje</span><strong>${moeda(335.3)}</strong></div>
        <div class="cpdv-card"><span>Pendente</span><strong>${moeda(389.9)}</strong></div>
        <div class="cpdv-card"><span>PIX</span><strong>${moeda(245.8)}</strong></div>
        <div class="cpdv-card"><span>Cartão</span><strong>${moeda(89.5)}</strong></div>
      </div>
      <div class="cpdv-actions">
        <button type="button" class="btn primary" id="cpdvSimPgto">Simular pagamento</button>
        <button type="button" class="btn neutral" data-cpdv-proto="Estorno solicitado">Estornar</button>
        <button type="button" class="btn neutral" data-cpdv-proto="Divisão de conta">Dividir conta</button>
        <button type="button" class="btn neutral" data-cpdv-proto="Múltiplas formas">Múltiplas formas</button>
      </div>`;
    root.querySelector("#cpdvSimPgto")?.addEventListener("click", cpdvAbrirModalPagamento);
    root.querySelectorAll("[data-cpdv-proto]").forEach((el) => {
      el.addEventListener("click", () => protoToast(el.dataset.cpdvProto));
    });
  }

  function cpdvRenderFechamento() {
    const root = cpdvRoot("comercialFechamentoRoot");
    if (!root) return;
    root.innerHTML = `
      ${cpdvAvisoProto()}
      <div class="table-card cpdv-form-body">
        <h3>1. Abertura de caixa</h3>
        <div class="filters-grid">
          <label>Operador<input value="Caixa 01" /></label>
          <label>Unidade<select><option>Matriz — Centro</option><option>Filial — Batista Campos</option></select></label>
          <label>Terminal<input value="PDV-01" /></label>
          <label>Valor inicial<input value="200,00" /></label>
          <label>Data/hora<input value="15/07/2026 11:00" readonly /></label>
          <label><button type="button" class="btn primary" data-cpdv-proto="Caixa aberto">Abrir caixa</button></label>
        </div>
      </div>
      <div class="table-card cpdv-form-body">
        <h3>2. Durante o caixa</h3>
        <div class="cpdv-cards">
          <div class="cpdv-card"><span>Vendas</span><strong>${moeda(4820.5)}</strong></div>
          <div class="cpdv-card"><span>Entradas</span><strong>${moeda(120)}</strong></div>
          <div class="cpdv-card"><span>Sangrias</span><strong>${moeda(350)}</strong></div>
          <div class="cpdv-card"><span>Suprimentos</span><strong>${moeda(100)}</strong></div>
          <div class="cpdv-card"><span>Cancelamentos</span><strong>${moeda(45)}</strong></div>
          <div class="cpdv-card"><span>Descontos</span><strong>${moeda(88)}</strong></div>
        </div>
      </div>
      <div class="table-card cpdv-form-body">
        <h3>3. Fechamento</h3>
        <div class="filters-grid">
          <label>Valor esperado<input value="4.820,50" readonly /></label>
          <label>Valor contado<input value="4.795,00" /></label>
          <label>Diferença<input value="-25,50" readonly /></label>
          <label>Justificativa<input placeholder="Informar se houver diferença" /></label>
          <label>Observações<input /></label>
        </div>
        <p class="subtle-text">Resumo: Dinheiro ${moeda(890)} · PIX ${moeda(2100)} · Débito ${moeda(980)} · Crédito ${moeda(850.5)}</p>
        <div class="cpdv-actions">
          <button type="button" class="btn primary" data-cpdv-proto="Fechamento de caixa registrado">Fechar caixa</button>
          <button type="button" class="btn neutral" data-cpdv-proto="Conferência impressa">Imprimir conferência</button>
        </div>
      </div>`;
    root.querySelectorAll("[data-cpdv-proto]").forEach((el) => {
      el.addEventListener("click", () => protoToast(el.dataset.cpdvProto));
    });
  }

  function cpdvRenderClientes() {
    const root = cpdvRoot("comercialClientesRoot");
    if (!root) return;
    const rows = DADOS_DEMONSTRACAO_PDV.clientes.map((c) =>
      `<tr>
        <td>${escHtml(c.nome)}</td><td>${escHtml(c.telefone)}</td><td>${escHtml(c.whatsapp || "—")}</td>
        <td>${escHtml(c.cpf || "—")}</td><td>${escHtml(c.restricao || "—")}</td>
        <td>${escHtml(c.ultima)}</td><td>${moeda(c.totalGasto || 0)}</td><td>${c.visitas}</td>
        <td><button type="button" class="btn neutral btn-sm" data-cpdv-proto="Cliente ${escHtml(c.nome)}">Ver</button></td>
      </tr>`
    ).join("");
    root.innerHTML = `
      ${cpdvAvisoProto()}
      <div class="table-card cpdv-form-body">
        <h3>Novo cliente (demo)</h3>
        <div class="filters-grid">
          <label>Nome<input placeholder="Nome completo" /></label>
          <label>Telefone<input /></label>
          <label>WhatsApp<input /></label>
          <label>CPF (opcional)<input /></label>
          <label>Nascimento<input type="date" /></label>
          <label>Preferência<input /></label>
          <label>Restrição alimentar<input /></label>
          <label>Observações<input /></label>
          <label><button type="button" class="btn primary" data-cpdv-proto="Cliente salvo (simulado)">Salvar</button></label>
        </div>
      </div>
      <div class="table-card"><div class="table-wrap"><table class="data-table">
        <thead><tr><th>Nome</th><th>Telefone</th><th>WhatsApp</th><th>CPF</th><th>Restrição</th><th>Última compra</th><th>Total gasto</th><th>Visitas</th><th></th></tr></thead>
        <tbody>${rows}</tbody>
      </table></div></div>`;
    root.querySelectorAll("[data-cpdv-proto]").forEach((el) => {
      el.addEventListener("click", () => protoToast(el.dataset.cpdvProto));
    });
  }

  function cpdvRenderHistorico() {
    const root = cpdvRoot("comercialHistoricoRoot");
    if (!root) return;
    const rows = DADOS_DEMONSTRACAO_PDV.vendas.map((v) =>
      `<tr>
        <td>#${v.id}</td><td>${escHtml(v.data)} ${escHtml(v.hora)}</td><td>${escHtml(v.unidade)}</td>
        <td>${v.mesa}</td><td>${escHtml(v.cliente)}</td><td>${escHtml(v.operador)}</td>
        <td>${moeda(v.total)}</td><td>${escHtml(v.forma)}</td>
        <td>${cpdvStatusBadge(v.status)}</td>
        <td class="cpdv-actions" style="margin:0">
          <button type="button" class="btn neutral btn-sm" data-cpdv-proto="Ver venda #${v.id}">Ver</button>
          <button type="button" class="btn neutral btn-sm" data-cpdv-proto="Imprimir #${v.id}">Imprimir</button>
          <button type="button" class="btn neutral btn-sm" data-cpdv-proto="Reabrir #${v.id}">Reabrir</button>
          <button type="button" class="btn danger btn-sm" data-cpdv-proto="Cancelar #${v.id}">Cancelar</button>
          <button type="button" class="btn secondary btn-sm" disabled title="Futuro">Fiscal</button>
        </td>
      </tr>`
    ).join("");
    root.innerHTML = `
      ${cpdvAvisoProto()}
      <div class="table-card cpdv-form-body">
        <div class="filters-grid">
          <label>Período<input type="date" value="2026-07-01" /></label>
          <label>até<input type="date" value="2026-07-15" /></label>
          <label>Unidade<select><option>Todas</option><option>Matriz — Centro</option></select></label>
          <label>Operador<input /></label>
          <label>Garçom<input /></label>
          <label>Mesa<input /></label>
          <label>Cliente<input /></label>
          <label>Produto<input /></label>
          <label>Pagamento<select><option>Todas</option><option>PIX</option><option>Dinheiro</option></select></label>
          <label>Status<select><option>Todos</option><option>finalizada</option><option>cancelada</option></select></label>
          <label><button type="button" class="btn primary" data-cpdv-proto="Filtro aplicado">Filtrar</button></label>
        </div>
      </div>
      <div class="table-card">
        <header><h3>Histórico de vendas</h3></header>
        <div class="table-wrap"><table class="data-table">
          <thead><tr><th>Nº</th><th>Data/hora</th><th>Unidade</th><th>Mesa</th><th>Cliente</th><th>Operador</th><th>Total</th><th>Pagamento</th><th>Status</th><th>Ações</th></tr></thead>
          <tbody>${rows}</tbody>
        </table></div>
      </div>`;
    root.querySelectorAll("[data-cpdv-proto]").forEach((el) => {
      el.addEventListener("click", () => protoToast(el.dataset.cpdvProto));
    });
  }

  function cpdvRenderRelatorios() {
    const root = cpdvRoot("comercialRelatoriosRoot");
    if (!root) return;
    const rels = [
      ["Vendas por período", "Totais e comparativo diário"],
      ["Vendas por unidade", "Consolidado multi-loja"],
      ["Vendas por garçom", "Performance de atendimento"],
      ["Produtos mais vendidos", "Ranking e participação"],
      ["Produtos menos vendidos", "Oportunidade de cardápio"],
      ["Ticket médio", "Por período e unidade"],
      ["Formas de pagamento", "Mix de recebimentos"],
      ["Cancelamentos", "Itens e motivos"],
      ["Descontos", "Volume e autorização"],
      ["Mesas mais utilizadas", "Ocupação do salão"],
      ["Horários de maior movimento", "Pico por hora"],
      ["Vendas por cliente", "Recorrência"],
      ["Margem estimada", "Estimativa futura com CMV"],
    ];
    const cards = rels.map(([t, d]) =>
      `<div class="cpdv-rel-card"><h4>${escHtml(t)}</h4><p>${escHtml(d)}</p>
      <button type="button" class="btn primary" data-cpdv-proto="PDF: ${escHtml(t)}">PDF</button>
      <button type="button" class="btn neutral" data-cpdv-proto="Excel: ${escHtml(t)}">Excel</button></div>`
    ).join("");
    root.innerHTML = `${cpdvAvisoProto()}<div class="cpdv-rel-grid">${cards}</div>`;
    root.querySelectorAll("[data-cpdv-proto]").forEach((el) => {
      el.addEventListener("click", () => protoToast(el.dataset.cpdvProto));
    });
  }

  function cpdvRenderConfiguracoes() {
    const root = cpdvRoot("comercialConfiguracoesRoot");
    if (!root) return;
    const cfgs = [
      ["Geral", ["Fuso", "Moeda", "Idioma", "Tempo de mesa"]],
      ["Unidades", ["Lojas ativas", "Salões"]],
      ["Terminais", ["PDV-01", "PDV-02", "Tablet garçom"]],
      ["Impressoras", ["Cozinha", "Bar", "Caixa", "Pré-conta"]],
      ["Setores de produção", ["Cozinha", "Bar", "Sobremesas"]],
      ["Cozinha", ["KDS", "Alertas de atraso"]],
      ["Bar", ["Itens rápidos", "Impressão"]],
      ["Comandas", ["Numeração", "Pré-conta automática"]],
      ["Mesas", ["Mapa", "Capacidade", "Junção"]],
      ["Pagamentos", ["Formas", "TEF", "Troco"]],
      ["Descontos", ["Limites", "Autorização"]],
      ["Permissões", ["Perfis PDV"]],
      ["Impressão", ["Layout cupom"]],
      ["Fiscal", ["Futuro — NFC-e/NF-e"]],
      ["Integração com estoque", ["Baixa automática (futuro)"]],
      ["Integração com Ayla", ["Pedidos por voz (futuro)"]],
    ];
    const cards = cfgs.map(([t, items]) =>
      `<div class="cpdv-cfg-card"><h4>${escHtml(t)}</h4><ul>${items.map((i) => `<li>${escHtml(i)}</li>`).join("")}</ul></div>`
    ).join("");
    root.innerHTML = `${cpdvAvisoProto()}<div class="cpdv-cfg-grid">${cards}</div>`;
  }

  function cpdvRenderFiscal() {
    const root = cpdvRoot("comercialFiscalRoot");
    if (!root) return;
    const itens = [
      "Configuração fiscal", "Certificado digital", "CSC", "Ambiente de homologação",
      "Ambiente de produção", "NFC-e", "NF-e", "Contingência", "Cancelamento",
      "Inutilização", "Histórico fiscal", "IBS", "CBS",
    ];
    root.innerHTML = `
      <div class="cpdv-aviso-proto" style="background:#ffebee;border-color:#ef9a9a;color:#b71c1c;font-size:1rem;">
        <strong>Módulo fiscal ainda não implementado.</strong><br>
        Esta tela representa a futura integração com NFC-e, NF-e, contingência e regras tributárias.
      </div>
      <div class="cpdv-fiscal-grid">${itens.map((i) =>
        `<div class="cpdv-fiscal-card" aria-disabled="true"><strong>${escHtml(i)}</strong><span>Em desenvolvimento</span></div>`
      ).join("")}</div>`;
  }

  async function loadComercialDashboard() { cpdvRenderDashboard(); }
  async function loadComercialPdv() { cpdvRenderPdv(); }
  async function loadComercialMesas() { cpdvRenderMesas(); }
  async function loadComercialPedidos() { cpdvRenderPedidos(); }
  async function loadComercialCozinha() { cpdvRenderCozinha(); }
  async function loadComercialPagamentos() { cpdvRenderPagamentos(); }
  async function loadComercialFechamento() { cpdvRenderFechamento(); }
  async function loadComercialClientes() { cpdvRenderClientes(); }
  async function loadComercialHistorico() { cpdvRenderHistorico(); }
  async function loadComercialRelatorios() { cpdvRenderRelatorios(); }
  async function loadComercialConfiguracoes() { cpdvRenderConfiguracoes(); }
  async function loadComercialFiscal() { cpdvRenderFiscal(); }

  window.loadComercialDashboard = loadComercialDashboard;
  window.loadComercialPdv = loadComercialPdv;
  window.loadComercialMesas = loadComercialMesas;
  window.loadComercialPedidos = loadComercialPedidos;
  window.loadComercialCozinha = loadComercialCozinha;
  window.loadComercialPagamentos = loadComercialPagamentos;
  window.loadComercialFechamento = loadComercialFechamento;
  window.loadComercialClientes = loadComercialClientes;
  window.loadComercialHistorico = loadComercialHistorico;
  window.loadComercialRelatorios = loadComercialRelatorios;
  window.loadComercialConfiguracoes = loadComercialConfiguracoes;
  window.loadComercialFiscal = loadComercialFiscal;

  window.loaders = window.loaders || {};
  Object.assign(window.loaders, {
    loadComercialDashboard,
    loadComercialPdv,
    loadComercialMesas,
    loadComercialPedidos,
    loadComercialCozinha,
    loadComercialPagamentos,
    loadComercialFechamento,
    loadComercialClientes,
    loadComercialHistorico,
    loadComercialRelatorios,
    loadComercialConfiguracoes,
    loadComercialFiscal,
  });

  window.setupComercialPdvModule = function setupComercialPdvModule() {
    if (cpdvModalBound) return;
    cpdvModalBound = true;
    ensureCpdvModal();
    document.getElementById("cpdvModalClose")?.addEventListener("click", closeCpdvModal);
    document.getElementById("cpdvModal")?.addEventListener("click", (ev) => {
      if (ev.target.id === "cpdvModal") closeCpdvModal();
    });
    document.addEventListener("keydown", (ev) => {
      if (ev.key === "Escape") closeCpdvModal();
    });
  };
})();
