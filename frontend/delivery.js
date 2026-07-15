/**
 * Delivery — PROTÓTIPO VISUAL (referência VendaFácil / C:\vendaffacil).
 * Sem API, sem persistência. Fluxo: Cardápio → Carrinho → Checkout → Pedido.
 */
(function () {
  "use strict";

  const DADOS_DEMONSTRACAO_DELIVERY = {
    config: {
      nomeLoja: "Grupo Sabor Paraense",
      slogan: "Sabores da Amazônia na sua mesa",
      whatsapp: "5591988887777",
      pedidoMinimo: 25,
      corPrimaria: "#c62828",
      corSecundaria: "#ff8f00",
    },
    unidades: [
      { id: 1, nome: "Matriz — Centro", endereco: "Av. Presidente Vargas, 1000" },
      { id: 2, nome: "Filial — Batista Campos", endereco: "Trav. Mauriti, 200" },
      { id: 3, nome: "Filial — Marco", endereco: "Av. Almirante Barroso, 500" },
    ],
    categorias: [
      { id: 1, nome: "Pratos Típicos", emoji: "🍲", ordem: 1, ativo: true, destaque: true },
      { id: 2, nome: "Porções", emoji: "🦐", ordem: 2, ativo: true, destaque: false },
      { id: 3, nome: "Bebidas", emoji: "🥤", ordem: 3, ativo: true, destaque: false },
      { id: 4, nome: "Sobremesas", emoji: "🍮", ordem: 4, ativo: true, destaque: true },
    ],
    banners: [
      { id: 1, titulo: "Combo Maniçoba", subtitulo: "Tradição paraense com 15% off", ordem: 1, ativo: true, emoji: "🌿" },
      { id: 2, titulo: "Frete grátis", subtitulo: "Pedidos acima de R$ 80 no Centro", ordem: 2, ativo: true, emoji: "🛵" },
    ],
    adicionaisCatalogo: [
      { id: 1, nome: "Queijo coalho", grupo: "Queijos", preco: 5, min: 0, max: 3, obrigatorio: false, ativo: true },
      { id: 2, nome: "Bacon", grupo: "Carnes", preco: 6, min: 0, max: 2, obrigatorio: false, ativo: true },
      { id: 3, nome: "Molho especial", grupo: "Molhos", preco: 3, min: 0, max: 2, obrigatorio: false, ativo: true },
      { id: 4, nome: "Porção extra de mandioca", grupo: "Acompanhamentos", preco: 8, min: 0, max: 1, obrigatorio: false, ativo: true },
      { id: 5, nome: "Suco de cupuaçu 500ml", grupo: "Bebidas", preco: 10, min: 0, max: 4, obrigatorio: false, ativo: true },
    ],
    produtos: [
      {
        id: 1, nome: "Tacacá", descricao: "Caldo amazônico com jambu, tucupi e camarão seco.",
        categoriaId: 1, emoji: "🍲", preco: 28.9, precoPromo: null, unidadeId: 1, disponivel: true, destaque: true,
        tempoPreparo: 25, setor: "cozinha",
        adicionais: [1, 3], variacoes: [{ id: "v1", nome: "Tradicional", preco: 0 }, { id: "v2", nome: "Com camarão extra", preco: 12 }],
        retiradas: ["sem jambu", "sem pimenta", "sem tucupi"],
      },
      {
        id: 2, nome: "Maniçoba", descricao: "Prato paraense cozido por dias com folhas de maniva.",
        categoriaId: 1, emoji: "🌿", preco: 42.5, precoPromo: 38.9, unidadeId: 1, disponivel: true, destaque: true,
        tempoPreparo: 35, setor: "cozinha",
        adicionais: [1, 2, 4], variacoes: [{ id: "v1", nome: "Individual", preco: 0 }, { id: "v2", nome: "Família (2 pessoas)", preco: 35 }],
        retiradas: ["sem toucinho", "sem farinha"],
      },
      {
        id: 3, nome: "Porção de camarão", descricao: "Camarões médios fritos com alho e limão.",
        categoriaId: 2, emoji: "🦐", preco: 65, precoPromo: null, unidadeId: 1, disponivel: true, destaque: false,
        tempoPreparo: 20, setor: "cozinha", adicionais: [3, 4], variacoes: [], retiradas: ["sem cebola"],
      },
      {
        id: 4, nome: "Suco de cupuaçu", descricao: "Natural, sem conservantes.",
        categoriaId: 3, emoji: "🥤", preco: 12, precoPromo: null, unidadeId: 1, disponivel: true, destaque: false,
        tempoPreparo: 5, setor: "bar", adicionais: [], variacoes: [{ id: "v1", nome: "300ml", preco: 0 }, { id: "v2", nome: "500ml", preco: 4 }], retiradas: [],
      },
      {
        id: 5, nome: "Mousse de cupuaçu", descricao: "Sobremesa cremosa da fruta amazônica.",
        categoriaId: 4, emoji: "🍮", preco: 16, precoPromo: 14, unidadeId: 1, disponivel: true, destaque: true,
        tempoPreparo: 5, setor: "sobremesa", adicionais: [], variacoes: [], retiradas: [],
      },
      {
        id: 6, nome: "Caldo de piranha", descricao: "Tradicional caldo de pescado.",
        categoriaId: 1, emoji: "🐟", preco: 35, precoPromo: null, unidadeId: 1, disponivel: false, destaque: false,
        tempoPreparo: 30, setor: "cozinha", adicionais: [], variacoes: [], retiradas: [],
      },
    ],
    cupons: [
      { id: 1, codigo: "SABOR10", tipo: "percentual", valor: 10, validade: "2026-12-31", pedidoMin: 50, limite: 100, ativo: true },
      { id: 2, codigo: "FRETEGRATIS", tipo: "frete", valor: 0, validade: "2026-08-31", pedidoMin: 80, limite: 50, ativo: true },
    ],
    taxas: [
      { id: 1, bairro: "Centro", cep: "66010-000", valor: 8, prazo: "35-45 min", unidadeId: 1, ativo: true },
      { id: 2, bairro: "Nazaré", cep: "66035-000", valor: 12, prazo: "45-55 min", unidadeId: 1, ativo: true },
      { id: 3, bairro: "Marco", cep: "66093-000", valor: 15, prazo: "50-60 min", unidadeId: 3, ativo: true },
    ],
    horarios: [
      { id: 1, dia: "Segunda", abertura: "11:00", fechamento: "22:00", aceita: true, unidadeId: 1 },
      { id: 2, dia: "Terça", abertura: "11:00", fechamento: "22:00", aceita: true, unidadeId: 1 },
      { id: 3, dia: "Domingo", abertura: "11:00", fechamento: "16:00", aceita: true, unidadeId: 1 },
    ],
    formasPagamento: [
      { id: 1, nome: "PIX", icone: "📱", entrega: true, retirada: true, online: true, ativo: true },
      { id: 2, nome: "Dinheiro", icone: "💵", entrega: true, retirada: true, online: false, ativo: true },
      { id: 3, nome: "Cartão na entrega", icone: "💳", entrega: true, retirada: false, online: false, ativo: true },
      { id: 4, nome: "Crédito", icone: "💳", entrega: false, retirada: true, online: true, ativo: true },
      { id: 5, nome: "Débito", icone: "💳", entrega: true, retirada: true, online: false, ativo: true },
      { id: 6, nome: "Voucher", icone: "🎫", entrega: true, retirada: true, online: false, ativo: true },
    ],
    clientes: [
      { id: 1, nome: "Maria Silva", telefone: "(91) 98888-1111", whatsapp: "(91) 98888-1111", cpf: "", visitas: 18, ultima: "2026-07-14" },
      { id: 2, nome: "João Santos", telefone: "(91) 97777-2222", whatsapp: "(91) 97777-2222", cpf: "123.456.789-00", visitas: 7, ultima: "2026-07-12" },
    ],
    enderecos: [
      { id: 1, clienteId: 1, cep: "66010-100", rua: "Rua dos Mundurucus", numero: "120", complemento: "Apto 302", bairro: "Centro", cidade: "Belém", ref: "Próximo ao mercado" },
      { id: 2, clienteId: 2, cep: "66035-200", rua: "Travessa Dom Romualdo", numero: "45", complemento: "", bairro: "Nazaré", cidade: "Belém", ref: "Casa azul" },
    ],
    pedidos: [
      { id: 9001, codigo: "DLV-9001", hora: "12:45", cliente: "Maria Silva", telefone: "(91) 98888-1111", unidade: "Matriz — Centro", tipo: "entrega", bairro: "Centro", total: 78.4, pagamento: "PIX", status: "preparo", previsao: "13:25" },
      { id: 9002, codigo: "DLV-9002", hora: "13:10", cliente: "João Santos", telefone: "(91) 97777-2222", unidade: "Matriz — Centro", tipo: "retirada", bairro: "—", total: 52, pagamento: "Dinheiro", status: "recebido", previsao: "13:40" },
      { id: 9003, codigo: "DLV-9003", hora: "11:20", cliente: "Ana Costa", telefone: "(91) 96666-3333", unidade: "Filial — Marco", tipo: "entrega", bairro: "Marco", total: 124.9, pagamento: "Cartão na entrega", status: "rota", previsao: "12:10" },
    ],
  };

  const DLV_STATUS = {
    recebido: "Pedido recebido", confirmado: "Confirmado", preparo: "Em preparação",
    pronto: "Pronto", rota: "Saiu para entrega", entregue: "Entregue", cancelado: "Cancelado",
  };
  const DLV_FLUXO = ["loja", "carrinho", "checkout", "pedido"];

  const dlvState = {
    cat: "todos", busca: "", cart: [], checkoutStep: 0, fluxo: "loja",
    cliente: {}, entrega: { tipo: "entrega" }, pagamento: { forma: "PIX", troco: "" },
    cupom: null, desconto: 0, pedidoAtual: null, prodModal: null, charts: {},
  };

  let dlvModalBound = false;

  function escHtml(s) {
    return (s ?? "").toString().replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
  }
  function moeda(v) {
    return Number(v || 0).toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
  }
  function protoToast(msg) {
    if (typeof showToast === "function") showToast(`Protótipo: ${msg} — sem persistência.`, "info");
    else alert(`Protótipo: ${msg}`);
  }
  function dlvRoot(id) {
    return document.getElementById(id);
  }
  function dlvAviso() {
    return `<div class="dlv-aviso-proto"><strong>Dados demonstrativos do protótipo.</strong> Referência visual ao VendaFácil (C:\\vendaffacil), adaptado ao Grupo Sabor Paraense. Não integrado ao banco.</div>`;
  }

  function ensureDlvModal() {
    if (document.getElementById("dlvModal")) return;
    const el = document.createElement("div");
    el.id = "dlvModal";
    el.className = "modal-backdrop";
    el.innerHTML = `<div class="modal" role="dialog" aria-modal="true">
      <header><h2 id="dlvModalTitle">—</h2><button type="button" class="close-btn" id="dlvModalClose" aria-label="Fechar">×</button></header>
      <div id="dlvModalBody"></div>
      <footer id="dlvModalActions" class="dlv-modal-actions"></footer>
    </div>`;
    document.body.appendChild(el);
  }
  function openDlvModal(title, body, actions) {
    ensureDlvModal();
    const m = document.getElementById("dlvModal");
    document.getElementById("dlvModalTitle").textContent = title;
    document.getElementById("dlvModalBody").innerHTML = body;
    document.getElementById("dlvModalActions").innerHTML = actions || "";
    m.classList.add("active");
  }
  function closeDlvModal() {
    document.getElementById("dlvModal")?.classList.remove("active");
  }

  function dlvSubtotalCart() {
    return dlvState.cart.reduce((s, i) => s + i.subtotal, 0);
  }
  function dlvTaxaDemo() {
    if (dlvState.entrega.tipo !== "entrega") return 0;
    const b = dlvState.entrega.bairro || "Centro";
    const t = DADOS_DEMONSTRACAO_DELIVERY.taxas.find((x) => x.bairro === b);
    return t ? t.valor : 10;
  }
  function dlvTotalCart() {
    return Math.max(0, dlvSubtotalCart() + dlvTaxaDemo() - dlvState.desconto);
  }

  function dlvFluxoHtml(atual) {
    const labels = { loja: "Cardápio", carrinho: "Carrinho", checkout: "Checkout", pedido: "Pedido" };
    const idx = DLV_FLUXO.indexOf(atual);
    return `<nav class="dlv-wizard-steps" aria-label="Etapas da compra">${DLV_FLUXO.map((f, i) => {
      const cls = i === idx ? "active" : i < idx ? "done" : "";
      return `<span class="dlv-wizard-step ${cls}">${escHtml(labels[f])}</span>`;
    }).join("")}</nav>`;
  }

  function dlvProdutoPreco(p) {
    return p.precoPromo != null && p.precoPromo < p.preco ? p.precoPromo : p.preco;
  }

  function dlvRenderProdutoModal(prodId) {
    const p = DADOS_DEMONSTRACAO_DELIVERY.produtos.find((x) => x.id === prodId);
    if (!p || !p.disponivel) return;
    dlvState.prodModal = { prodId, qtd: 1, adicionais: [], variacao: p.variacoes[0]?.id || null, retiradas: [], obs: "" };
    dlvAtualizarProdutoModal();
  }

  function dlvAtualizarProdutoModal() {
    const st = dlvState.prodModal;
    const p = DADOS_DEMONSTRACAO_DELIVERY.produtos.find((x) => x.id === st.prodId);
    if (!p) return;
    const adds = p.adicionais.map((aid) => DADOS_DEMONSTRACAO_DELIVERY.adicionaisCatalogo.find((a) => a.id === aid)).filter(Boolean);
    const addHtml = adds.length ? `<div class="dlv-add-group"><h5>Adicionais</h5>${adds.map((a) =>
      `<label class="dlv-add-opt"><span><input type="checkbox" data-dlv-add="${a.id}" ${st.adicionais.includes(a.id) ? "checked" : ""}/> ${escHtml(a.nome)} (+${moeda(a.preco)})</span></label>`
    ).join("")}</div>` : "";
    const varHtml = p.variacoes.length ? `<div class="dlv-add-group"><h5>Variação</h5>${p.variacoes.map((v) =>
      `<label class="dlv-add-opt"><span><input type="radio" name="dlvVar" value="${v.id}" ${st.variacao === v.id ? "checked" : ""}/> ${escHtml(v.nome)} ${v.preco ? `(+${moeda(v.preco)})` : ""}</span></label>`
    ).join("")}</div>` : "";
    const retHtml = p.retiradas.length ? `<div class="dlv-add-group"><h5>Retirar ingrediente</h5>${p.retiradas.map((r) =>
      `<label class="dlv-add-opt"><span><input type="checkbox" data-dlv-ret="${escHtml(r)}" ${st.retiradas.includes(r) ? "checked" : ""}/> ${escHtml(r)}</span></label>`
    ).join("")}</div>` : "";
    let sub = dlvProdutoPreco(p);
    const varSel = p.variacoes.find((v) => v.id === st.variacao);
    if (varSel) sub += varSel.preco;
    st.adicionais.forEach((aid) => {
      const a = DADOS_DEMONSTRACAO_DELIVERY.adicionaisCatalogo.find((x) => x.id === aid);
      if (a) sub += a.preco;
    });
    sub *= st.qtd;
    const body = `
      ${dlvFluxoHtml("loja")}
      <div style="text-align:center;font-size:3rem;margin-bottom:.5rem">${p.emoji}</div>
      <h3 style="margin:0 0 .35rem">${escHtml(p.nome)}</h3>
      <p class="subtle-text">${escHtml(p.descricao)}</p>
      <p><strong>${moeda(dlvProdutoPreco(p))}</strong>${p.precoPromo ? ` <s class="subtle-text">${moeda(p.preco)}</s>` : ""}</p>
      <label>Quantidade <input type="number" id="dlvProdQtd" min="1" max="20" value="${st.qtd}" style="width:4rem"/></label>
      ${varHtml}${addHtml}${retHtml}
      <label>Observação <input type="text" id="dlvProdObs" value="${escHtml(st.obs)}" placeholder="Ex.: bem passado"/></label>
      <p style="margin-top:.75rem"><strong>Subtotal: ${moeda(sub)}</strong></p>`;
    openDlvModal(p.nome, body, `<button type="button" class="btn primary" id="dlvProdAdd">Adicionar ao carrinho</button>
      <button type="button" class="btn neutral" id="dlvProdClose">Continuar comprando</button>`);
    const bind = () => {
      document.getElementById("dlvProdQtd")?.addEventListener("change", (e) => { st.qtd = Math.max(1, +e.target.value || 1); dlvAtualizarProdutoModal(); });
      document.getElementById("dlvProdObs")?.addEventListener("input", (e) => { st.obs = e.target.value; });
      document.querySelectorAll("[data-dlv-add]").forEach((el) => {
        el.onchange = () => {
          const id = +el.dataset.dlvAdd;
          if (el.checked) st.adicionais.push(id); else st.adicionais = st.adicionais.filter((x) => x !== id);
          dlvAtualizarProdutoModal();
        };
      });
      document.querySelectorAll("[data-dlv-ret]").forEach((el) => {
        el.onchange = () => {
          const r = el.dataset.dlvRet;
          if (el.checked) st.retiradas.push(r); else st.retiradas = st.retiradas.filter((x) => x !== r);
          dlvAtualizarProdutoModal();
        };
      });
      document.querySelectorAll("[name=dlvVar]").forEach((el) => {
        el.onchange = () => { st.variacao = el.value; dlvAtualizarProdutoModal(); };
      });
      document.getElementById("dlvProdAdd")?.addEventListener("click", () => {
        let unit = dlvProdutoPreco(p);
        const vs = p.variacoes.find((v) => v.id === st.variacao);
        if (vs) unit += vs.preco;
        const addNomes = st.adicionais.map((aid) => DADOS_DEMONSTRACAO_DELIVERY.adicionaisCatalogo.find((a) => a.id === aid)).filter(Boolean);
        addNomes.forEach((a) => { unit += a.preco; });
        dlvState.cart.push({
          id: Date.now(), produtoId: p.id, nome: p.nome, emoji: p.emoji, qtd: st.qtd, unit, subtotal: unit * st.qtd,
          adicionais: addNomes.map((a) => a.nome), variacao: vs?.nome || "", retiradas: [...st.retiradas], obs: st.obs,
        });
        closeDlvModal();
        loadDeliveryVitrine();
        protoToast("Item adicionado ao carrinho");
      });
      document.getElementById("dlvProdClose")?.addEventListener("click", closeDlvModal);
    };
    bind();
  }

  function dlvRenderCarrinhoModal() {
    dlvState.fluxo = "carrinho";
    if (!dlvState.cart.length) {
      openDlvModal("Carrinho", `${dlvFluxoHtml("carrinho")}<p>Seu carrinho está vazio.</p>`,
        `<button type="button" class="btn primary" id="dlvCartVoltar">Ver cardápio</button>`);
      document.getElementById("dlvCartVoltar")?.addEventListener("click", closeDlvModal);
      return;
    }
    const itens = dlvState.cart.map((i, idx) =>
      `<div class="dlv-cart-item"><div><strong>${i.emoji} ${escHtml(i.nome)}</strong> × ${i.qtd}
        ${i.adicionais.length ? `<br><small>+ ${escHtml(i.adicionais.join(", "))}</small>` : ""}
        ${i.retiradas.length ? `<br><small>Sem: ${escHtml(i.retiradas.join(", "))}</small>` : ""}
        ${i.obs ? `<br><small>Obs: ${escHtml(i.obs)}</small>` : ""}</div>
        <div>${moeda(i.subtotal)} <button type="button" class="btn danger btn-sm" data-dlv-rm="${idx}">×</button></div></div>`
    ).join("");
    const body = `${dlvFluxoHtml("carrinho")}${itens}
      <div class="dlv-cart-totais">
        <div>Subtotal: ${moeda(dlvSubtotalCart())}</div>
        <div>Taxa entrega (demo): ${moeda(dlvTaxaDemo())}</div>
        <label>Cupom <input type="text" id="dlvCupom" placeholder="SABOR10" style="max-width:8rem"/> <button type="button" class="btn neutral btn-sm" id="dlvAplicarCupom">Aplicar</button></label>
        ${dlvState.desconto ? `<div>Desconto: -${moeda(dlvState.desconto)}</div>` : ""}
        <div class="total">Total: ${moeda(dlvTotalCart())}</div>
      </div>`;
    openDlvModal("Carrinho", body, `
      <button type="button" class="btn primary" id="dlvIrCheckout">Finalizar pedido</button>
      <button type="button" class="btn neutral" id="dlvLimparCart">Limpar</button>
      <button type="button" class="btn neutral" id="dlvCartFechar">Continuar comprando</button>`);
    document.querySelectorAll("[data-dlv-rm]").forEach((b) => {
      b.onclick = () => { dlvState.cart.splice(+b.dataset.dlvRm, 1); dlvRenderCarrinhoModal(); loadDeliveryVitrine(); };
    });
    document.getElementById("dlvAplicarCupom")?.addEventListener("click", () => {
      const c = document.getElementById("dlvCupom")?.value?.trim().toUpperCase();
      const cup = DADOS_DEMONSTRACAO_DELIVERY.cupons.find((x) => x.codigo === c && x.ativo);
      if (!cup) { protoToast("Cupom inválido"); return; }
      dlvState.desconto = cup.tipo === "percentual" ? dlvSubtotalCart() * (cup.valor / 100) : cup.valor;
      dlvState.cupom = cup.codigo;
      dlvRenderCarrinhoModal();
      protoToast(`Cupom ${cup.codigo} aplicado`);
    });
    document.getElementById("dlvLimparCart")?.addEventListener("click", () => { dlvState.cart = []; dlvState.desconto = 0; closeDlvModal(); loadDeliveryVitrine(); });
    document.getElementById("dlvCartFechar")?.addEventListener("click", closeDlvModal);
    document.getElementById("dlvIrCheckout")?.addEventListener("click", () => { closeDlvModal(); dlvRenderCheckout(0); });
  }

  function dlvRenderCheckout(step) {
    dlvState.fluxo = "checkout";
    dlvState.checkoutStep = step;
    const steps = ["Cliente", "Entrega / Retirada", "Pagamento", "Confirmação"];
    if (step === 0) {
      const body = `${dlvFluxoHtml("checkout")}<h4>${steps[0]}</h4>
        <div class="filters-grid">
          <label>Nome<input id="dlvCliNome" value="${escHtml(dlvState.cliente.nome || "")}"/></label>
          <label>Telefone<input id="dlvCliTel" value="${escHtml(dlvState.cliente.telefone || "")}"/></label>
          <label>WhatsApp<input id="dlvCliWa" value="${escHtml(dlvState.cliente.whatsapp || "")}"/></label>
          <label>CPF (opcional)<input id="dlvCliCpf"/></label>
          <label>Observação<input id="dlvCliObs"/></label>
        </div>
        <p class="subtle-text">Cliente novo ou existente — apenas visual.</p>`;
      openDlvModal("Finalizar pedido", body, `<button type="button" class="btn primary" id="dlvChkNext">Próximo</button>`);
      document.getElementById("dlvChkNext")?.addEventListener("click", () => {
        dlvState.cliente = { nome: document.getElementById("dlvCliNome")?.value, telefone: document.getElementById("dlvCliTel")?.value, whatsapp: document.getElementById("dlvCliWa")?.value };
        dlvRenderCheckout(1);
      });
    } else if (step === 1) {
      const undOpts = DADOS_DEMONSTRACAO_DELIVERY.unidades.map((u) => `<option value="${u.id}">${escHtml(u.nome)}</option>`).join("");
      const body = `${dlvFluxoHtml("checkout")}<h4>${steps[1]}</h4>
        <label><input type="radio" name="dlvTipo" value="entrega" ${dlvState.entrega.tipo === "entrega" ? "checked" : ""}/> Entrega</label>
        <label><input type="radio" name="dlvTipo" value="retirada" ${dlvState.entrega.tipo === "retirada" ? "checked" : ""}/> Retirada no local</label>
        <div id="dlvEntregaFields" class="filters-grid" style="margin-top:.75rem">
          <label>CEP<input id="dlvCep" value="66010-100"/></label>
          <label>Rua<input id="dlvRua"/></label>
          <label>Número<input id="dlvNum"/></label>
          <label>Complemento<input id="dlvComp"/></label>
          <label>Bairro<input id="dlvBairro" value="Centro"/></label>
          <label>Cidade<input id="dlvCidade" value="Belém"/></label>
          <label>Referência<input id="dlvRef"/></label>
          <label>Taxa estimada<input readonly value="${moeda(dlvTaxaDemo())}"/></label>
          <label>Prazo estimado<input readonly value="35-45 min"/></label>
        </div>
        <div id="dlvRetiradaFields" class="filters-grid" style="display:none;margin-top:.75rem">
          <label>Unidade<select id="dlvUnd">${undOpts}</select></label>
          <label>Horário estimado<input value="40 min"/></label>
          <label>Observação<input/></label>
        </div>`;
      openDlvModal("Finalizar pedido", body, `<button type="button" class="btn primary" id="dlvChkNext2">Próximo</button>
        <button type="button" class="btn neutral" id="dlvChkBack1">Voltar</button>`);
      const toggle = () => {
        const t = document.querySelector("[name=dlvTipo]:checked")?.value || "entrega";
        dlvState.entrega.tipo = t;
        document.getElementById("dlvEntregaFields").style.display = t === "entrega" ? "" : "none";
        document.getElementById("dlvRetiradaFields").style.display = t === "retirada" ? "" : "none";
      };
      document.querySelectorAll("[name=dlvTipo]").forEach((r) => r.onchange = toggle);
      toggle();
      document.getElementById("dlvChkBack1")?.addEventListener("click", () => dlvRenderCheckout(0));
      document.getElementById("dlvChkNext2")?.addEventListener("click", () => {
        if (dlvState.entrega.tipo === "entrega") dlvState.entrega.bairro = document.getElementById("dlvBairro")?.value || "Centro";
        dlvRenderCheckout(2);
      });
    } else if (step === 2) {
      const formas = DADOS_DEMONSTRACAO_DELIVERY.formasPagamento.filter((f) => f.ativo).map((f) =>
        `<label><input type="radio" name="dlvPgto" value="${escHtml(f.nome)}" ${f.nome === "PIX" ? "checked" : ""}/> ${f.icone} ${escHtml(f.nome)}</label>`
      ).join("");
      const body = `${dlvFluxoHtml("checkout")}<h4>${steps[2]}</h4><div>${formas}</div>
        <div id="dlvPixBox" class="dlv-pix-box"><div class="dlv-pix-qr">QR Code<br>demonstrativo</div>
        <p class="small">PIX Copia e Cola (demo)</p><code>00020126580014BR.GOV.BCB.PIX...</code>
        <button type="button" class="btn neutral btn-sm" id="dlvCopiarPix">Copiar código</button></div>
        <label id="dlvTrocoWrap" style="display:none">Troco para quanto? <input id="dlvTroco" placeholder="R$ 100,00"/></label>`;
      openDlvModal("Finalizar pedido", body, `<button type="button" class="btn primary" id="dlvChkNext3">Confirmar pedido</button>
        <button type="button" class="btn neutral" id="dlvChkBack2">Voltar</button>`);
      const updPgto = () => {
        const f = document.querySelector("[name=dlvPgto]:checked")?.value || "PIX";
        dlvState.pagamento.forma = f;
        document.getElementById("dlvPixBox").style.display = f === "PIX" ? "" : "none";
        document.getElementById("dlvTrocoWrap").style.display = f === "Dinheiro" ? "" : "none";
      };
      document.querySelectorAll("[name=dlvPgto]").forEach((r) => r.onchange = updPgto);
      updPgto();
      document.getElementById("dlvCopiarPix")?.addEventListener("click", () => protoToast("Código PIX copiado (demo)"));
      document.getElementById("dlvChkBack2")?.addEventListener("click", () => dlvRenderCheckout(1));
      document.getElementById("dlvChkNext3")?.addEventListener("click", () => {
        dlvState.pedidoAtual = {
          codigo: "DLV-" + Math.floor(1000 + Math.random() * 9000),
          status: "recebido", total: dlvTotalCart(), itens: [...dlvState.cart],
          cliente: { ...dlvState.cliente }, entrega: { ...dlvState.entrega }, pagamento: { ...dlvState.pagamento },
          previsao: "40-50 min",
        };
        dlvState.cart = [];
        dlvState.desconto = 0;
        dlvRenderCheckout(3);
      });
    } else {
      const ped = dlvState.pedidoAtual;
      const itens = ped.itens.map((i) => `<li>${i.emoji} ${escHtml(i.nome)} × ${i.qtd} — ${moeda(i.subtotal)}</li>`).join("");
      const waText = encodeURIComponent(`Pedido ${ped.codigo} — ${moeda(ped.total)} — Grupo Sabor Paraense`);
      const waLink = `https://wa.me/${DADOS_DEMONSTRACAO_DELIVERY.config.whatsapp}?text=${waText}`;
      const timeline = ["recebido", "confirmado", "preparo", "pronto", "rota", "entregue"].map((s, i) =>
        `<div class="dlv-timeline-item ${i === 0 ? "active" : ""}"><span class="dlv-timeline-dot"></span>${escHtml(DLV_STATUS[s])}</div>`
      ).join("");
      const body = `${dlvFluxoHtml("pedido")}
        <h3>Pedido ${escHtml(ped.codigo)}</h3>
        <p><strong>Cliente:</strong> ${escHtml(ped.cliente.nome)} · ${escHtml(ped.cliente.telefone)}</p>
        <p><strong>${ped.entrega.tipo === "entrega" ? "Entrega" : "Retirada"}</strong> · <strong>Pagamento:</strong> ${escHtml(ped.pagamento.forma)}</p>
        <ul>${itens}</ul>
        <p><strong>Total: ${moeda(ped.total)}</strong> · Previsão: ${escHtml(ped.previsao)}</p>
        <div class="dlv-timeline">${timeline}</div>`;
      openDlvModal("Pedido confirmado", body, `
        <a href="${waLink}" target="_blank" rel="noopener" class="btn primary">Enviar resumo WhatsApp</a>
        <button type="button" class="btn neutral" id="dlvAcompanhar">Acompanhar pedido</button>
        <button type="button" class="btn neutral" id="dlvNovoPedido">Novo pedido</button>`);
      document.getElementById("dlvAcompanhar")?.addEventListener("click", () => { closeDlvModal(); protoToast(`Acompanhando ${ped.codigo}`); });
      document.getElementById("dlvNovoPedido")?.addEventListener("click", () => { closeDlvModal(); loadDeliveryVitrine(); });
    }
  }

  function dlvFiltrarProdutos() {
    return DADOS_DEMONSTRACAO_DELIVERY.produtos.filter((p) => {
      if (dlvState.cat !== "todos" && String(p.categoriaId) !== String(dlvState.cat)) return false;
      if (dlvState.busca) {
        const q = dlvState.busca.toLowerCase();
        if (!p.nome.toLowerCase().includes(q) && !(p.descricao || "").toLowerCase().includes(q)) return false;
      }
      return true;
    });
  }

  function loadDeliveryVitrine() {
    const root = dlvRoot("deliveryVitrineRoot");
    if (!root) return;
    const cfg = DADOS_DEMONSTRACAO_DELIVERY.config;
    const banner = DADOS_DEMONSTRACAO_DELIVERY.banners[0];
    const cats = DADOS_DEMONSTRACAO_DELIVERY.categorias.filter((c) => c.ativo);
    const catChips = `<button type="button" class="dlv-cat-chip ${dlvState.cat === "todos" ? "active" : ""}" data-dlv-cat="todos">Todos</button>` +
      cats.map((c) => `<button type="button" class="dlv-cat-chip ${String(dlvState.cat) === String(c.id) ? "active" : ""}" data-dlv-cat="${c.id}">${c.emoji} ${escHtml(c.nome)}</button>`).join("");
    const prods = dlvFiltrarProdutos().map((p) => {
      const preco = dlvProdutoPreco(p);
      const cat = cats.find((c) => c.id === p.categoriaId);
      return `<button type="button" class="dlv-prod-card ${p.disponivel ? "" : "dlv-prod-card--off"}" data-dlv-prod="${p.id}" ${p.disponivel ? "" : "disabled"}>
        ${p.destaque ? '<span class="dlv-prod-selo">Destaque</span>' : ""}
        ${!p.disponivel ? '<span class="dlv-prod-selo dlv-prod-selo--off">Indisponível</span>' : ""}
        <div class="dlv-prod-img">${p.emoji}</div>
        <div class="dlv-prod-body"><h4>${escHtml(p.nome)}</h4>
        <p>${escHtml((p.descricao || "").slice(0, 60))}…</p>
        <div class="dlv-prod-preco">${p.precoPromo ? `<s>${moeda(p.preco)}</s>` : ""}${moeda(preco)}</div>
        ${cat ? `<small class="subtle-text">${escHtml(cat.nome)}</small>` : ""}
        </div></button>`;
    }).join("");
    const qtd = dlvState.cart.reduce((s, i) => s + i.qtd, 0);
    const fab = qtd ? `<button type="button" class="dlv-cart-fab" id="dlvCartFab">🛒 Carrinho <span class="dlv-cart-fab__count">${qtd}</span> ${moeda(dlvSubtotalCart())}</button>` : "";
    root.innerHTML = `<div class="dlv-vitrine">
      <div class="dlv-vitrine-header">
        <div class="dlv-vitrine-brand"><div class="dlv-vitrine-logo">GSP</div><div><h1>${escHtml(cfg.nomeLoja)}</h1><p>${escHtml(cfg.slogan)}</p></div></div>
        <div class="dlv-vitrine-banner"><strong>${banner.emoji} ${escHtml(banner.titulo)}</strong><span>${escHtml(banner.subtitulo)}</span></div>
        <div class="dlv-vitrine-search"><input type="search" id="dlvBusca" placeholder="Buscar no cardápio…" value="${escHtml(dlvState.busca)}"/></div>
      </div>
      <div class="dlv-vitrine-body">
        <div class="dlv-vitrine-cats">${catChips}</div>
        <div class="dlv-prod-grid">${prods || '<p class="subtle-text">Nenhum produto encontrado.</p>'}</div>
      </div>${fab}</div>`;
    root.querySelector("#dlvBusca")?.addEventListener("input", (e) => { dlvState.busca = e.target.value; loadDeliveryVitrine(); });
    root.querySelectorAll("[data-dlv-cat]").forEach((el) => {
      el.addEventListener("click", () => { dlvState.cat = el.dataset.dlvCat; loadDeliveryVitrine(); });
    });
    root.querySelectorAll("[data-dlv-prod]").forEach((el) => {
      el.addEventListener("click", () => dlvRenderProdutoModal(+el.dataset.dlvProd));
    });
    root.querySelector("#dlvCartFab")?.addEventListener("click", dlvRenderCarrinhoModal);
  }

  function dlvAdminTable(rootId, title, cols, rows, extra) {
    const root = dlvRoot(rootId);
    if (!root) return;
    const th = cols.map((c) => `<th>${escHtml(c)}</th>`).join("");
    const tr = rows.map((r) => `<tr>${r.map((c) => `<td>${c}</td>`).join("")}</tr>`).join("");
    root.innerHTML = `${dlvAviso()}${extra || ""}<div class="table-card"><header><h3>${escHtml(title)}</h3></header>
      <div class="table-wrap"><table class="data-table"><thead><tr>${th}</tr></thead><tbody>${tr}</tbody></table></div></div>`;
    root.querySelectorAll("[data-dlv-proto]").forEach((el) => {
      el.addEventListener("click", () => protoToast(el.dataset.dlvProto));
    });
  }

  async function loadDeliveryDashboard() {
    const root = dlvRoot("deliveryDashboardRoot");
    if (!root) return;
    root.innerHTML = `${dlvAviso()}
      <div class="dlv-cards">
        <div class="dlv-card"><span>Pedidos hoje</span><strong>47</strong></div>
        <div class="dlv-card"><span>Faturamento</span><strong>${moeda(3280)}</strong></div>
        <div class="dlv-card"><span>Ticket médio</span><strong>${moeda(69.8)}</strong></div>
        <div class="dlv-card"><span>Em preparação</span><strong>8</strong></div>
        <div class="dlv-card"><span>Atrasados</span><strong>2</strong></div>
        <div class="dlv-card"><span>Cancelados</span><strong>1</strong></div>
        <div class="dlv-card"><span>Tempo médio</span><strong>42 min</strong></div>
        <div class="dlv-card"><span>Taxa média</span><strong>${moeda(10.5)}</strong></div>
      </div>
      <div class="dlv-cards">
        <div class="dlv-card"><span>Mais vendido</span><strong>Tacacá</strong></div>
        <div class="dlv-card"><span>Top bairro</span><strong>Centro</strong></div>
        <div class="dlv-card"><span>PIX</span><strong>58%</strong></div>
        <div class="dlv-card"><span>Matriz</span><strong>${moeda(2100)}</strong></div>
      </div>`;
  }

  async function loadDeliveryCategorias() {
    const rows = DADOS_DEMONSTRACAO_DELIVERY.categorias.map((c) => [
      `${c.emoji} ${escHtml(c.nome)}`, c.ordem, c.ativo ? "Sim" : "Não", c.destaque ? "Sim" : "Não",
      `<button type="button" class="btn neutral btn-sm" data-dlv-proto="Editar ${escHtml(c.nome)}">Editar</button>`,
    ]);
    dlvAdminTable("deliveryCategoriasRoot", "Categorias", ["Nome", "Ordem", "Ativo", "Destaque", ""], rows,
      `<div class="table-card dlv-form-body"><div class="filters-grid">
        <label>Nome<input placeholder="Nova categoria"/></label><label>Ordem<input type="number" value="5"/></label>
        <label><button type="button" class="btn primary" data-dlv-proto="Categoria salva">Salvar</button></label></div></div>`);
  }

  async function loadDeliveryProdutos() {
    const rows = DADOS_DEMONSTRACAO_DELIVERY.produtos.map((p) => {
      const cat = DADOS_DEMONSTRACAO_DELIVERY.categorias.find((c) => c.id === p.categoriaId);
      return [`${p.emoji} ${escHtml(p.nome)}`, escHtml(cat?.nome || "—"), moeda(dlvProdutoPreco(p)),
        p.disponivel ? '<span class="dlv-badge">Disponível</span>' : '<span class="dlv-badge dlv-badge--muted">Indisponível</span>',
        p.destaque ? "★" : "—", `${p.tempoPreparo} min`,
        `<button type="button" class="btn neutral btn-sm" data-dlv-proto="Editar ${escHtml(p.nome)}">Editar</button>`];
    });
    dlvAdminTable("deliveryProdutosRoot", "Produtos do Delivery", ["Produto", "Categoria", "Preço", "Status", "Destaque", "Preparo", ""], rows);
  }

  async function loadDeliveryAdicionais() {
    const rows = DADOS_DEMONSTRACAO_DELIVERY.adicionaisCatalogo.map((a) => [
      escHtml(a.nome), escHtml(a.grupo), moeda(a.preco), `${a.min}/${a.max}`, a.obrigatorio ? "Sim" : "Não", a.ativo ? "Sim" : "Não",
      `<button type="button" class="btn neutral btn-sm" data-dlv-proto="Editar ${escHtml(a.nome)}">Editar</button>`,
    ]);
    dlvAdminTable("deliveryAdicionaisRoot", "Adicionais e Variações", ["Nome", "Grupo", "Preço", "Mín/Máx", "Obrig.", "Ativo", ""], rows);
  }

  async function loadDeliveryBanners() {
    const root = dlvRoot("deliveryBannersRoot");
    if (!root) return;
    const cards = DADOS_DEMONSTRACAO_DELIVERY.banners.map((b) =>
      `<div class="dlv-rel-card"><div style="font-size:2rem">${b.emoji}</div><h4>${escHtml(b.titulo)}</h4><p>${escHtml(b.subtitulo)}</p>
      <p class="subtle-text">Ordem ${b.ordem} · ${b.ativo ? "Ativo" : "Inativo"}</p>
      <button type="button" class="btn neutral btn-sm" data-dlv-proto="Editar banner">Editar</button></div>`
    ).join("");
    root.innerHTML = `${dlvAviso()}<div class="dlv-rel-grid">${cards}</div>`;
    root.querySelectorAll("[data-dlv-proto]").forEach((el) => el.addEventListener("click", () => protoToast(el.dataset.dlvProto)));
  }

  async function loadDeliveryPedidos() {
    const rows = DADOS_DEMONSTRACAO_DELIVERY.pedidos.map((p) => [
      escHtml(p.codigo), escHtml(p.hora), escHtml(p.cliente), escHtml(p.telefone), escHtml(p.unidade),
      escHtml(p.tipo), moeda(p.total), escHtml(p.pagamento), `<span class="dlv-badge">${escHtml(DLV_STATUS[p.status] || p.status)}</span>`, escHtml(p.previsao),
      `<div class="dlv-actions" style="margin:0"><button class="btn neutral btn-sm" data-dlv-proto="Abrir ${p.codigo}">Abrir</button>
      <button class="btn primary btn-sm" data-dlv-proto="Confirmar ${p.codigo}">Confirmar</button>
      <button class="btn neutral btn-sm" data-dlv-proto="WhatsApp ${p.codigo}">WhatsApp</button></div>`,
    ]);
    dlvAdminTable("deliveryPedidosRoot", "Pedidos Delivery", ["Código", "Hora", "Cliente", "Tel.", "Unidade", "Tipo", "Total", "Pag.", "Status", "Prev.", "Ações"], rows,
      `<div class="table-card dlv-form-body"><div class="filters-grid">
        <label>Período<input type="date"/></label><label>Unidade<select><option>Todas</option></select></label>
        <label>Status<select><option>Todos</option></select></label>
        <label><button class="btn primary" data-dlv-proto="Filtro aplicado">Filtrar</button></label></div></div>`);
  }

  async function loadDeliveryClientes() {
    const rows = DADOS_DEMONSTRACAO_DELIVERY.clientes.map((c) => [
      escHtml(c.nome), escHtml(c.telefone), escHtml(c.whatsapp), c.visitas, escHtml(c.ultima),
      `<button class="btn neutral btn-sm" data-dlv-proto="Ver ${escHtml(c.nome)}">Ver</button>`,
    ]);
    dlvAdminTable("deliveryClientesRoot", "Clientes", ["Nome", "Telefone", "WhatsApp", "Visitas", "Última", ""], rows);
  }

  async function loadDeliveryEnderecos() {
    const rows = DADOS_DEMONSTRACAO_DELIVERY.enderecos.map((e) => {
      const cli = DADOS_DEMONSTRACAO_DELIVERY.clientes.find((c) => c.id === e.clienteId);
      return [escHtml(cli?.nome || "—"), escHtml(e.cep), `${escHtml(e.rua)}, ${escHtml(e.numero)}`, escHtml(e.bairro), escHtml(e.cidade), escHtml(e.ref || "—")];
    });
    dlvAdminTable("deliveryEnderecosRoot", "Endereços", ["Cliente", "CEP", "Endereço", "Bairro", "Cidade", "Referência"], rows);
  }

  async function loadDeliveryTaxas() {
    const rows = DADOS_DEMONSTRACAO_DELIVERY.taxas.map((t) => {
      const u = DADOS_DEMONSTRACAO_DELIVERY.unidades.find((x) => x.id === t.unidadeId);
      return [escHtml(t.bairro), escHtml(t.cep), moeda(t.valor), escHtml(t.prazo), escHtml(u?.nome || "—"), t.ativo ? "Sim" : "Não"];
    });
    dlvAdminTable("deliveryTaxasRoot", "Taxas de Entrega", ["Bairro", "CEP", "Valor", "Prazo", "Unidade", "Ativo"], rows);
  }

  async function loadDeliveryCupons() {
    const rows = DADOS_DEMONSTRACAO_DELIVERY.cupons.map((c) => [
      escHtml(c.codigo), escHtml(c.tipo), c.tipo === "percentual" ? `${c.valor}%` : moeda(c.valor),
      escHtml(c.validade), moeda(c.pedidoMin), c.limite, c.ativo ? "Sim" : "Não",
    ]);
    dlvAdminTable("deliveryCuponsRoot", "Cupons e Promoções", ["Código", "Tipo", "Valor", "Validade", "Ped. mín.", "Limite", "Ativo"], rows);
  }

  async function loadDeliveryPagamentos() {
    const rows = DADOS_DEMONSTRACAO_DELIVERY.formasPagamento.map((f) => [
      `${f.icone} ${escHtml(f.nome)}`, f.entrega ? "✓" : "—", f.retirada ? "✓" : "—", f.online ? "Futuro" : "—", f.ativo ? "Sim" : "Não",
    ]);
    dlvAdminTable("deliveryPagamentosRoot", "Formas de Pagamento", ["Forma", "Entrega", "Retirada", "Online", "Ativo"], rows);
  }

  async function loadDeliveryHorarios() {
    const rows = DADOS_DEMONSTRACAO_DELIVERY.horarios.map((h) => {
      const u = DADOS_DEMONSTRACAO_DELIVERY.unidades.find((x) => x.id === h.unidadeId);
      return [escHtml(h.dia), escHtml(h.abertura), escHtml(h.fechamento), h.aceita ? "Sim" : "Não", escHtml(u?.nome || "—")];
    });
    dlvAdminTable("deliveryHorariosRoot", "Horários de Funcionamento", ["Dia", "Abertura", "Fechamento", "Aceita pedidos", "Unidade"], rows);
  }

  async function loadDeliveryConfiguracoes() {
    const root = dlvRoot("deliveryConfiguracoesRoot");
    if (!root) return;
    const cfg = DADOS_DEMONSTRACAO_DELIVERY.config;
    root.innerHTML = `${dlvAviso()}<div class="table-card dlv-form-body"><h3>Configurações da Vitrine</h3>
      <div class="filters-grid">
        <label>Nome da loja<input value="${escHtml(cfg.nomeLoja)}"/></label>
        <label>Slogan<input value="${escHtml(cfg.slogan)}"/></label>
        <label>WhatsApp<input value="${escHtml(cfg.whatsapp)}"/></label>
        <label>Pedido mínimo<input value="${cfg.pedidoMinimo}"/></label>
        <label>Cor primária<input value="${cfg.corPrimaria}"/></label>
        <label>Cor secundária<input value="${cfg.corSecundaria}"/></label>
        <label><button class="btn primary" data-dlv-proto="Configurações salvas">Salvar</button></label>
      </div></div>`;
    root.querySelectorAll("[data-dlv-proto]").forEach((el) => el.addEventListener("click", () => protoToast(el.dataset.dlvProto)));
  }

  async function loadDeliveryRelatorios() {
    const root = dlvRoot("deliveryRelatoriosRoot");
    if (!root) return;
    const rels = ["Vendas por período", "Vendas por unidade", "Vendas por produto", "Vendas por categoria",
      "Clientes recorrentes", "Ticket médio", "Cancelamentos", "Cupons", "Bairros", "Taxas", "Tempo de entrega", "Formas de pagamento"];
    const cards = rels.map((t) =>
      `<div class="dlv-rel-card"><h4>${escHtml(t)}</h4><p>Relatório demonstrativo</p>
      <button class="btn primary btn-sm" data-dlv-proto="PDF: ${escHtml(t)}">PDF</button>
      <button class="btn neutral btn-sm" data-dlv-proto="Excel: ${escHtml(t)}">Excel</button></div>`
    ).join("");
    root.innerHTML = `${dlvAviso()}<div class="dlv-rel-grid">${cards}</div>`;
    root.querySelectorAll("[data-dlv-proto]").forEach((el) => el.addEventListener("click", () => protoToast(el.dataset.dlvProto)));
  }

  window.loadDeliveryDashboard = loadDeliveryDashboard;
  window.loadDeliveryVitrine = loadDeliveryVitrine;
  window.loadDeliveryCategorias = loadDeliveryCategorias;
  window.loadDeliveryProdutos = loadDeliveryProdutos;
  window.loadDeliveryAdicionais = loadDeliveryAdicionais;
  window.loadDeliveryBanners = loadDeliveryBanners;
  window.loadDeliveryPedidos = loadDeliveryPedidos;
  window.loadDeliveryClientes = loadDeliveryClientes;
  window.loadDeliveryEnderecos = loadDeliveryEnderecos;
  window.loadDeliveryTaxas = loadDeliveryTaxas;
  window.loadDeliveryCupons = loadDeliveryCupons;
  window.loadDeliveryPagamentos = loadDeliveryPagamentos;
  window.loadDeliveryHorarios = loadDeliveryHorarios;
  window.loadDeliveryConfiguracoes = loadDeliveryConfiguracoes;
  window.loadDeliveryRelatorios = loadDeliveryRelatorios;

  window.setupDeliveryModule = function () {
    if (dlvModalBound) return;
    dlvModalBound = true;
    ensureDlvModal();
    document.getElementById("dlvModalClose")?.addEventListener("click", closeDlvModal);
    document.getElementById("dlvModal")?.addEventListener("click", (ev) => { if (ev.target.id === "dlvModal") closeDlvModal(); });
    document.addEventListener("keydown", (ev) => { if (ev.key === "Escape") closeDlvModal(); });
  };
})();
