/**
 * VendaFácil parity for the Delivery dashboard and orders.
 * Loaded after delivery.js so these entry points intentionally replace its views.
 */
(function () {
  "use strict";

  const $ = (id) => document.getElementById(id);
  const esc = (value) => String(value == null ? "" : value)
    .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;").replace(/'/g, "&#39;");
  const money = (value) => Number(value || 0).toLocaleString("pt-BR", {
    style: "currency", currency: "BRL",
  });
  const number = (value) => Number(value || 0).toLocaleString("pt-BR", { maximumFractionDigits: 3 });
  const dateTime = (value) => value ? new Date(value).toLocaleString("pt-BR") : "—";
  const toast = (message, type) => window.showToast?.(message, type || "info");
  const api = (path, options) => {
    if (typeof window.fetchJSON !== "function") throw new Error("Conexão com a API indisponível.");
    return window.fetchJSON(`/delivery${path}`, options || {});
  };

  const labels = {
    pendente_loja: "Pendente da loja",
    recebido: "Aceito",
    preparo: "Em preparo",
    pronto: "Pronto",
    rota: "Em rota",
    entregue: "Entregue",
    cancelado: "Cancelado",
    endereco_nao_encontrado: "Endereço não encontrado",
  };
  const nextStatuses = {
    pendente_loja: ["recebido", "cancelado"],
    recebido: ["preparo", "cancelado"],
    preparo: ["pronto", "cancelado"],
    pronto: ["rota", "entregue", "endereco_nao_encontrado", "cancelado"],
    rota: ["entregue", "endereco_nao_encontrado", "cancelado"],
  };
  const actionLabels = {
    recebido: "Aceitar pedido",
    preparo: "Iniciar preparo",
    pronto: "Marcar como pronto",
    rota: "Saiu para entrega",
    entregue: "Marcar como entregue",
    cancelado: "Cancelar pedido",
    endereco_nao_encontrado: "Endereço não encontrado",
  };

  function badge(status) {
    return `<span class="vf-order-status vf-order-status--${esc(status)}">${esc(labels[status] || status)}</span>`;
  }

  function breadcrumb(current, detail) {
    return `<nav class="vf-orders__breadcrumb"><button type="button" data-vf-dashboard>Dashboard</button>
      <span>/</span>${detail ? `<button type="button" data-vf-orders>Pedidos</button><span>/</span>` : ""}
      <strong>${esc(current)}</strong></nav>`;
  }

  function orderRows(items) {
    if (!items.length) {
      return `<tr><td colspan="7" class="vf-orders-empty">Nenhum pedido encontrado.</td></tr>`;
    }
    return items.map((order) => {
      const pending = order.status === "pendente_loja";
      return `<tr class="${pending ? "is-pending" : ""}">
        <td><strong class="vf-order-code">${esc(order.codigo_publico)}</strong></td>
        <td>${esc(order.cliente_nome || "Consumidor")}</td>
        <td>${esc(order.canal || "—")}</td>
        <td>${dateTime(order.created_at)}</td>
        <td>${badge(order.status)}</td>
        <td class="vf-money">${money(order.total)}</td>
        <td class="vf-orders-actions">
          ${pending ? `<button class="vf-btn vf-btn--success" data-vf-status="${order.id}:recebido">Aceitar</button>
            <button class="vf-btn vf-btn--danger" data-vf-status="${order.id}:cancelado">Recusar</button>` : ""}
          <button class="vf-btn" data-vf-open="${order.id}">Abrir</button>
        </td>
      </tr>`;
    }).join("");
  }

  async function loadDashboard() {
    const root = $("deliveryDashboardRoot");
    if (!root) return;
    try {
      const data = await api("/dashboard");
      const metrics = data.metrics || {};
      const days = data.seven_days || [];
      const maximum = Math.max(1, ...days.map((day) => Number(day.total || 0)));
      const pending = Number(data.resumo?.pendente_loja || 0);
      const cards = [
        ["Vendas hoje", metrics.vendas_hoje, "🛒"],
        ["Produtos vendidos hoje", metrics.produtos_vendidos_hoje, "▦"],
        ["Unidades vendidas hoje", number(metrics.unidades_vendidas_hoje), "≡"],
        ["Produtos cadastrados", metrics.produtos_cadastrados, "◫"],
        ["Venda total", money(metrics.venda_total), "R$"],
      ];

      root.innerHTML = `<div class="vf-orders vf-dashboard">
        ${breadcrumb("Dashboard")}
        <div class="vf-orders__toolbar">
          <div><h2>Visão geral</h2><p>Acompanhe vendas e pedidos da operação.</p></div>
          <button class="vf-btn vf-btn--primary" type="button" data-vf-orders>Ver pedidos</button>
        </div>
        <section class="vf-dashboard-kpis">
          ${cards.map((card) => `<article class="vf-dashboard-kpi"><span class="vf-dashboard-kpi__icon">${card[2]}</span>
            <div><small>${esc(card[0])}</small><strong>${esc(card[1] ?? 0)}</strong></div></article>`).join("")}
        </section>
        <section class="vf-dashboard-grid">
          <article class="vf-card vf-sales-card">
            <div class="vf-card-title"><div><h3>Vendas nos últimos 7 dias</h3><p>Faturamento diário, sem pedidos cancelados.</p></div></div>
            <div class="vf-sales-chart">
              ${days.map((day) => `<div class="vf-sales-bar-column" title="${esc(day.date)}: ${money(day.total)}">
                <span>${money(day.total)}</span>
                <div class="vf-sales-bar-track"><i style="height:${Math.max(3, (Number(day.total || 0) / maximum) * 100)}%"></i></div>
                <strong>${esc(day.label || day.date)}</strong><small>${Number(day.sales || 0)} venda(s)</small>
              </div>`).join("")}
            </div>
          </article>
          <aside class="vf-dashboard-side">
            <article class="vf-card vf-pending-card ${pending ? "has-pending" : ""}">
              <span class="vf-dashboard-alert-icon">!</span>
              <div><h3>${pending ? `${pending} pedido(s) aguardando` : "Fila em dia"}</h3>
              <p>${pending ? "Há pedidos que precisam ser aceitos ou recusados." : "Nenhum pedido aguarda confirmação."}</p></div>
              <button class="vf-btn ${pending ? "vf-btn--primary" : ""}" data-vf-pending>Ver pendentes</button>
            </article>
            <article class="vf-card vf-shortcuts"><h3>Atalhos</h3>
              <button type="button" data-vf-new-order>＋ Novo pedido</button>
              <button type="button" data-vf-orders>☷ Todos os pedidos</button>
              <button type="button" data-vf-products>▦ Produtos</button>
            </article>
          </aside>
        </section>
        <section class="vf-card vf-orders-table-card">
          <div class="vf-card-title"><div><h3>Pedidos recentes</h3><p>Últimas movimentações da loja.</p></div>
            <button class="vf-btn" data-vf-orders>Ver todos</button></div>
          <div class="vf-table-wrap"><table class="vf-orders-table"><thead><tr>
            <th>Pedido</th><th>Cliente</th><th>Canal</th><th>Horário</th><th>Status</th><th>Valor</th><th>Ações</th>
          </tr></thead><tbody>${orderRows(data.ultimos || [])}</tbody></table></div>
        </section>
      </div>`;
      bindSharedNavigation(root);
      root.querySelector("[data-vf-pending]").onclick = () => {
        window.navigateTo?.("deliveryPedidos");
        setTimeout(() => window.loadDeliveryPedidos?.("pendente_loja"), 0);
      };
      root.querySelector("[data-vf-new-order]").onclick = () => {
        window.navigateTo?.("deliveryPedidos");
        setTimeout(() => openNewOrder(), 0);
      };
      root.querySelector("[data-vf-products]").onclick = () => window.navigateTo?.("deliveryProdutos");
      bindOrderTableActions(root, loadDashboard);
    } catch (error) {
      toast(error?.message || "Não foi possível carregar o dashboard.", "error");
    }
  }

  async function loadOrders(initialStatus) {
    const root = $("deliveryPedidosRoot");
    if (!root) return;
    const status = typeof initialStatus === "string" ? initialStatus : "";
    try {
      const query = status ? `?status=${encodeURIComponent(status)}` : "";
      const data = await api(`/pedidos${query}`);
      renderOrdersList(root, data.items || [], status);
    } catch (error) {
      toast(error?.message || "Não foi possível carregar os pedidos.", "error");
    }
  }

  function renderOrdersList(root, items, status) {
    root.innerHTML = `<div class="vf-orders">
      ${breadcrumb("Pedidos")}
      <div class="vf-orders__toolbar">
        <div><h2>Pedidos</h2><p>Gerencie a fila e acompanhe cada venda.</p></div>
        <button class="vf-btn vf-btn--primary" type="button" data-vf-new-order>＋ Novo pedido</button>
      </div>
      <form class="vf-orders-filter" id="vfOrdersFilter">
        <label><span>Status</span><select name="status">
          <option value="">Todos os status</option>
          ${Object.entries(labels).map(([value, label]) => `<option value="${value}" ${status === value ? "selected" : ""}>${esc(label)}</option>`).join("")}
        </select></label>
        <button class="vf-btn" type="button" data-vf-clear>Limpar</button>
      </form>
      <section class="vf-card vf-orders-table-card">
        <div class="vf-table-wrap"><table class="vf-orders-table"><thead><tr>
          <th>Pedido</th><th>Cliente</th><th>Canal</th><th>Horário</th><th>Status</th><th>Valor</th><th>Ações</th>
        </tr></thead><tbody>${orderRows(items)}</tbody></table></div>
      </section>
    </div>`;
    bindSharedNavigation(root);
    root.querySelector("[data-vf-new-order]").onclick = openNewOrder;
    root.querySelector('select[name="status"]').onchange = (event) => loadOrders(event.target.value);
    root.querySelector("[data-vf-clear]").onclick = () => loadOrders("");
    bindOrderTableActions(root);
  }

  function bindSharedNavigation(root) {
    root.querySelectorAll("[data-vf-dashboard]").forEach((button) => {
      button.onclick = () => window.navigateTo?.("deliveryDashboard");
    });
    root.querySelectorAll("[data-vf-orders]").forEach((button) => {
      button.onclick = () => window.navigateTo?.("deliveryPedidos");
    });
  }

  function bindOrderTableActions(root, refresh) {
    root.querySelectorAll("[data-vf-open]").forEach((button) => {
      button.onclick = () => openOrder(Number(button.dataset.vfOpen));
    });
    root.querySelectorAll("[data-vf-status]").forEach((button) => {
      button.onclick = async () => {
        const [id, status] = button.dataset.vfStatus.split(":");
        if (status === "cancelado" && !confirm("Recusar este pedido? Essa ação não poderá ser desfeita.")) return;
        await changeStatus(Number(id), status, refresh || (() => loadOrders()));
      };
    });
  }

  async function changeStatus(id, status, after) {
    try {
      await api(`/pedidos/${id}/status`, {
        method: "PATCH",
        body: JSON.stringify({ status }),
      });
      toast("Status do pedido atualizado.", "success");
      await after();
    } catch (error) {
      toast(error?.message || "Não foi possível atualizar o pedido.", "error");
    }
  }

  function optionsText(options) {
    const additions = (options?.adicionais || []).map((item) =>
      `${item.quantidade || 1}× ${item.nome}`).join(", ");
    const removals = (options?.retiradas || []).map((item) => `sem ${item.nome}`).join(", ");
    return [additions, removals].filter(Boolean).join(" · ") || "—";
  }

  async function openOrder(id) {
    const root = $("deliveryPedidosRoot");
    if (!root) return;
    try {
      const order = await api(`/pedidos/${id}`);
      const address = order.endereco || {};
      const addressLine = address.texto || [
        address.rua, address.numero, address.bairro, address.cidade,
        address.uf, address.complemento,
      ].filter(Boolean).join(", ");
      const transitions = nextStatuses[order.status] || [];
      const isPending = order.status === "pendente_loja";

      root.innerHTML = `<div class="vf-orders vf-order-detail">
        ${breadcrumb(order.codigo_publico, true)}
        <header class="vf-order-detail__head">
          <div><div class="vf-order-detail__title"><h2>${esc(order.codigo_publico)}</h2>${badge(order.status)}</div>
            <p>${dateTime(order.created_at)} · ${esc(order.canal || "admin")} · ${esc(order.fulfillment || "—")}
            ${address.cep ? ` · CEP ${esc(address.cep)}` : ""}</p></div>
          <button class="vf-btn" type="button" data-vf-orders>← Voltar</button>
        </header>
        ${isPending ? `<section class="vf-pending-warning"><div><strong>Este pedido aguarda confirmação</strong>
          <p>Confira os itens antes de aceitar ou recusar.</p></div>
          <div><button class="vf-btn vf-btn--success" data-vf-detail-status="recebido">Aceitar</button>
          <button class="vf-btn vf-btn--danger" data-vf-detail-status="cancelado">Recusar</button></div></section>` : ""}
        <div class="vf-order-detail__grid">
          <main>
            <section class="vf-card">
              <div class="vf-card-title"><h3>Itens do pedido</h3></div>
              <div class="vf-table-wrap"><table class="vf-orders-table vf-items-table"><thead><tr>
                <th>Produto / opções</th><th>Qtd.</th><th>Unitário</th><th>Adicionais</th><th>Subtotal</th>
              </tr></thead><tbody>${(order.itens || []).map((item) => `<tr>
                <td><strong>${esc(item.nome_produto)}</strong><small>${esc(optionsText(item.opcoes))}</small></td>
                <td>${number(item.quantidade)}</td><td>${money(item.preco_unitario)}</td>
                <td>${money(item.preco_adicionais)}</td><td class="vf-money">${money(item.subtotal)}</td>
              </tr>`).join("")}</tbody></table></div>
              <div class="vf-order-totals"><span>Subtotal <strong>${money(order.subtotal)}</strong></span>
                <span>Frete <strong>${money(order.frete_valor)}</strong></span>
                <span class="is-total">Total <strong>${money(order.total)}</strong></span></div>
            </section>
            <section class="vf-card vf-history"><div class="vf-card-title"><h3>Histórico completo</h3></div>
              <ol>${(order.historico || []).slice().reverse().map((event) => `<li>
                <i></i><div><strong>${esc(labels[event.status_novo] || event.status_novo)}</strong>
                <span>${dateTime(event.created_at)} · ${esc(event.acao || "alteração")}</span>
                ${event.detalhes?.detalhe ? `<p>${esc(event.detalhes.detalhe)}</p>` : ""}</div>
              </li>`).join("")}</ol>
            </section>
          </main>
          <aside>
            <section class="vf-card vf-order-info"><h3>Cliente e entrega</h3>
              <dl><dt>Cliente</dt><dd>${esc(order.cliente_nome)}</dd>
                <dt>Telefone</dt><dd>${esc(order.cliente_telefone || "—")}</dd>
                <dt>WhatsApp</dt><dd>${esc(order.cliente_whatsapp || "—")}</dd>
                <dt>Endereço</dt><dd>${esc(addressLine || (order.fulfillment === "entrega" ? "Não informado" : "Retirada na loja"))}</dd>
                <dt>CEP</dt><dd>${esc(address.cep || "—")}</dd>
                <dt>Pagamento</dt><dd>${esc(order.pagamento_forma || "—")} · ${esc(order.pagamento_status || "pendente")}</dd>
                <dt>Observações</dt><dd>${esc(order.observacoes || "Nenhuma")}</dd></dl>
              ${order.fulfillment === "entrega" && order.entregador_token ? `<label class="vf-token-field"><span>Token do entregador</span>
                <div><input readonly value="${esc(order.entregador_token)}"><button class="vf-btn" data-vf-copy-token>Copiar</button></div></label>` : ""}
            </section>
            ${transitions.length ? `<section class="vf-card vf-status-actions"><h3>Atualizar status</h3>
              ${transitions.filter((status) => !(isPending && ["recebido", "cancelado"].includes(status))).map((status) =>
                `<button class="vf-btn ${status === "cancelado" ? "vf-btn--danger" : "vf-btn--primary"}" data-vf-detail-status="${status}">${esc(actionLabels[status])}</button>`).join("")}
            </section>` : ""}
          </aside>
        </div>
      </div>`;
      bindSharedNavigation(root);
      root.querySelectorAll("[data-vf-detail-status]").forEach((button) => {
        button.onclick = async () => {
          const status = button.dataset.vfDetailStatus;
          if (status === "cancelado" && !confirm("Cancelar este pedido? Essa ação não poderá ser desfeita.")) return;
          await changeStatus(order.id, status, () => openOrder(order.id));
        };
      });
      const copy = root.querySelector("[data-vf-copy-token]");
      if (copy) copy.onclick = async () => {
        try {
          await navigator.clipboard.writeText(order.entregador_token);
          toast("Token copiado.", "success");
        } catch (_) {
          copy.previousElementSibling.select();
          document.execCommand("copy");
          toast("Token copiado.", "success");
        }
      };
    } catch (error) {
      toast(error?.message || "Não foi possível abrir o pedido.", "error");
    }
  }

  async function openNewOrder() {
    const root = $("deliveryPedidosRoot");
    if (!root) return;
    try {
      const products = (await api("/produtos?ativo=true")).items || [];
      const cart = [];
      renderNewOrder(root, products, cart, "");
    } catch (error) {
      toast(error?.message || "Não foi possível abrir o novo pedido.", "error");
    }
  }

  function renderNewOrder(root, products, cart, search) {
    const visible = products.filter((product) => {
      const haystack = `${product.nome || ""} ${product.sku || ""} ${product.categoria_nome || ""}`.toLowerCase();
      return haystack.includes(search.toLowerCase());
    });
    const subtotal = cart.reduce((sum, item) => sum + Number(item.product.preco) * item.quantity, 0);

    root.innerHTML = `<div class="vf-orders vf-order-create">
      ${breadcrumb("Novo pedido", true)}
      <div class="vf-orders__toolbar"><div><h2>Novo pedido</h2><p>Venda rápida com múltiplos produtos.</p></div>
        <button class="vf-btn" type="button" data-vf-orders>Cancelar</button></div>
      <div class="vf-order-create__grid">
        <section class="vf-card vf-product-picker">
          <div class="vf-card-title"><div><h3>Produtos</h3><p>Busque e adicione itens ao pedido.</p></div></div>
          <label class="vf-picker-search"><span>Buscar produto</span>
            <input id="vfProductSearch" type="search" value="${esc(search)}" placeholder="Nome, código ou categoria"></label>
          <div class="vf-picker-list">${visible.map((product) => `<button type="button" data-vf-add-product="${product.id}">
            <span><strong>${esc(product.nome)}</strong><small>${esc(product.sku || product.categoria_nome || "Produto")}</small></span>
            <b>${money(product.preco)}</b><i>＋</i></button>`).join("") || `<p class="vf-orders-empty">Nenhum produto encontrado.</p>`}</div>
        </section>
        <form class="vf-card vf-order-form" id="vfNewOrderForm">
          <div class="vf-card-title"><div><h3>Pedido</h3><p>Revise o carrinho e informe o cliente.</p></div></div>
          <div class="vf-cart">${cart.length ? cart.map((item, index) => `<div class="vf-cart-item">
            <div><strong>${esc(item.product.nome)}</strong><small>${money(item.product.preco)} cada</small></div>
            <div class="vf-cart-stepper"><button type="button" data-vf-cart-minus="${index}">−</button>
              <span>${item.quantity}</span><button type="button" data-vf-cart-plus="${index}">＋</button></div>
            <strong>${money(Number(item.product.preco) * item.quantity)}</strong>
            <button class="vf-cart-remove" type="button" data-vf-cart-remove="${index}" title="Remover">×</button>
          </div>`).join("") : `<p class="vf-cart-empty">Adicione ao menos um produto.</p>`}</div>
          <div class="vf-live-total"><span>Subtotal</span><strong>${money(subtotal)}</strong><small>Frete calculado ao salvar</small></div>
          <div class="vf-form-grid">
            <label class="vf-field vf-col-8"><span>Cliente</span><input name="cliente_nome" required maxlength="160"></label>
            <label class="vf-field vf-col-4"><span>Telefone</span><input name="cliente_telefone" maxlength="30"></label>
            <label class="vf-field vf-col-6"><span>WhatsApp</span><input name="cliente_whatsapp" maxlength="30"></label>
            <label class="vf-field vf-col-6"><span>Atendimento</span><select name="fulfillment">
              <option value="entrega">Entrega</option><option value="retirada">Retirada</option>
            </select></label>
            <div class="vf-address-fields vf-col-12">
              <label class="vf-field vf-col-4"><span>CEP</span><input name="endereco_cep" maxlength="9"></label>
              <label class="vf-field vf-col-8"><span>Rua</span><input name="endereco_rua" maxlength="180"></label>
              <label class="vf-field vf-col-3"><span>Número</span><input name="endereco_numero" maxlength="40"></label>
              <label class="vf-field vf-col-5"><span>Bairro</span><input name="endereco_bairro" maxlength="120"></label>
              <label class="vf-field vf-col-4"><span>Complemento</span><input name="endereco_complemento"></label>
              <label class="vf-field vf-col-7"><span>Cidade</span><input name="endereco_cidade" maxlength="120"></label>
              <label class="vf-field vf-col-2"><span>UF</span><input name="endereco_uf" maxlength="2"></label>
              <label class="vf-field vf-col-3"><span>Referência</span><input name="endereco_texto"></label>
            </div>
            <label class="vf-field vf-col-6"><span>Pagamento</span><select name="pagamento_forma">
              <option value="pix">PIX</option><option value="cartao">Cartão</option><option value="dinheiro">Dinheiro</option>
            </select></label>
            <label class="vf-field vf-col-6"><span>Status do pagamento</span><select name="pagamento_status">
              <option value="pendente">Pendente</option><option value="pago">Pago</option>
            </select></label>
            <label class="vf-field vf-col-12"><span>Observações</span><textarea name="observacoes" rows="3"></textarea></label>
          </div>
          <button class="vf-btn vf-btn--primary vf-create-submit" type="submit" ${cart.length ? "" : "disabled"}>Criar pedido</button>
        </form>
      </div>
    </div>`;
    bindSharedNavigation(root);
    const preserveValues = () => {
      const form = $("vfNewOrderForm");
      return form ? Object.fromEntries(new FormData(form).entries()) : {};
    };
    const rerender = (values) => {
      renderNewOrder(root, products, cart, search);
      const form = $("vfNewOrderForm");
      Object.entries(values || {}).forEach(([name, value]) => {
        if (form.elements[name]) form.elements[name].value = value;
      });
      toggleAddress(form);
    };
    root.querySelector("#vfProductSearch").oninput = (event) => {
      search = event.target.value;
      const values = preserveValues();
      renderNewOrder(root, products, cart, search);
      const form = $("vfNewOrderForm");
      Object.entries(values).forEach(([name, value]) => {
        if (form.elements[name]) form.elements[name].value = value;
      });
      toggleAddress(form);
      const input = root.querySelector("#vfProductSearch");
      input.focus();
      input.setSelectionRange(input.value.length, input.value.length);
    };
    root.querySelectorAll("[data-vf-add-product]").forEach((button) => {
      button.onclick = () => {
        const product = products.find((item) => Number(item.id) === Number(button.dataset.vfAddProduct));
        const existing = cart.find((item) => Number(item.product.id) === Number(product.id));
        if (existing) existing.quantity += 1;
        else cart.push({ product, quantity: 1 });
        rerender(preserveValues());
      };
    });
    root.querySelectorAll("[data-vf-cart-plus]").forEach((button) => {
      button.onclick = () => { cart[Number(button.dataset.vfCartPlus)].quantity += 1; rerender(preserveValues()); };
    });
    root.querySelectorAll("[data-vf-cart-minus]").forEach((button) => {
      button.onclick = () => {
        const index = Number(button.dataset.vfCartMinus);
        if (cart[index].quantity > 1) cart[index].quantity -= 1;
        else cart.splice(index, 1);
        rerender(preserveValues());
      };
    });
    root.querySelectorAll("[data-vf-cart-remove]").forEach((button) => {
      button.onclick = () => { cart.splice(Number(button.dataset.vfCartRemove), 1); rerender(preserveValues()); };
    });
    const form = $("vfNewOrderForm");
    form.elements.fulfillment.onchange = () => toggleAddress(form);
    toggleAddress(form);
    form.onsubmit = async (event) => {
      event.preventDefault();
      if (!cart.length) return;
      const value = (name) => form.elements[name]?.value.trim() || null;
      const submit = form.querySelector('[type="submit"]');
      submit.disabled = true;
      try {
        const order = await api("/pedidos", {
          method: "POST",
          body: JSON.stringify({
            canal: "admin",
            cliente_nome: value("cliente_nome"),
            cliente_telefone: value("cliente_telefone"),
            cliente_whatsapp: value("cliente_whatsapp"),
            fulfillment: value("fulfillment"),
            endereco_cep: value("endereco_cep"),
            endereco_rua: value("endereco_rua"),
            endereco_numero: value("endereco_numero"),
            endereco_bairro: value("endereco_bairro"),
            endereco_cidade: value("endereco_cidade"),
            endereco_uf: value("endereco_uf")?.toUpperCase() || null,
            endereco_complemento: value("endereco_complemento"),
            endereco_texto: value("endereco_texto"),
            pagamento_forma: value("pagamento_forma"),
            pagamento_status: value("pagamento_status"),
            observacoes: value("observacoes"),
            itens: cart.map((item) => ({ produto_id: Number(item.product.id), quantidade: item.quantity })),
          }),
        });
        toast("Pedido criado com sucesso.", "success");
        await openOrder(order.id);
      } catch (error) {
        toast(error?.message || "Não foi possível criar o pedido.", "error");
        submit.disabled = false;
      }
    };
  }

  function toggleAddress(form) {
    form.querySelector(".vf-address-fields")?.classList.toggle("is-hidden", form.elements.fulfillment.value !== "entrega");
  }

  window.loadDeliveryDashboard = loadDashboard;
  window.loadDeliveryPedidos = loadOrders;
})();
