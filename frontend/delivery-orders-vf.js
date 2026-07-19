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
  const API_URL = (() => {
    if (window.APP_CONFIG?.API_URL) return window.APP_CONFIG.API_URL;
    return `${location.origin.replace(/\/$/, "")}/api`;
  })();

  function authHeaders(extra) {
    const headers = { ...(extra || {}) };
    if (window.currentUser?.token) headers.Authorization = `Bearer ${window.currentUser.token}`;
    if (window.currentUser?.id != null) headers["X-Usuario-Id"] = String(window.currentUser.id);
    return headers;
  }

  async function openPrint(orderId, auto) {
    const path = `/delivery/pedidos/${orderId}/imprimir${auto ? "?auto=1" : ""}`;
    const res = await fetch(`${API_URL}${path}`, { headers: authHeaders(), cache: "no-store" });
    if (!res.ok) throw new Error("Não foi possível abrir o cupom para impressão.");
    const html = await res.text();
    const win = window.open("", "_blank");
    if (!win) throw new Error("Permita pop-ups para imprimir o cupom.");
    win.document.write(html);
    win.document.close();
  }

  function rotuloCanal(canal) {
    const map = { loja: "Loja online", admin: "Admin", whatsapp: "WhatsApp", pdv: "PDV" };
    return map[String(canal || "").toLowerCase()] || canal || "—";
  }

  function renderCupomActions(order) {
    const wa = order.cupom_whatsapp_url;
    const printEnabled = order.impressao_habilitada !== false;
    return `<div class="vf-show-card vf-show-card--sidebar">
      <h3 class="vf-show-card__title">Cupom do cliente</h3>
      <p class="vf-show-help">Cupom estilo comanda (80&nbsp;mm): loja, pedido, itens com extras, valores e link para acompanhar. Use na <strong>impressora térmica</strong> pelo navegador ou envie o <strong>mesmo texto</strong> pelo WhatsApp.</p>
      <div class="vf-show-stack">
        ${printEnabled ? `<button type="button" class="vf-show-btn vf-show-btn--outline" data-vf-print="0">Abrir cupom / imprimir</button>
          <button type="button" class="vf-show-btn vf-show-btn--outline-soft" data-vf-print="1">Abrir e pedir impressão</button>`
          : `<p class="vf-show-help">Impressão desativada em Configurações. Ainda pode enviar pelo WhatsApp.</p>`}
        ${wa
          ? `<a class="vf-show-btn vf-show-btn--success" href="${esc(wa)}" target="_blank" rel="noopener noreferrer">Enviar cupom no WhatsApp</a>`
          : `<p class="vf-show-warn">WhatsApp: confira o telefone do cliente (DDD + número).</p>`}
      </div>
    </div>`;
  }

  function renderEntregadores(order) {
    const list = order.entregadores || [];
    if (order.fulfillment !== "entrega" || !list.length) return "";
    return `<div class="vf-show-card vf-show-card--sidebar vf-show-card--entregadores">
      <div class="vf-show-entregadores-head"><h3>Seus entregadores — chame primeiro</h3></div>
      <div class="vf-show-entregadores-body">${list.map((ent) => `<div class="vf-show-entregador">
        ${ent.foto_url ? `<img src="${esc(ent.foto_url)}" alt="" class="vf-show-entregador__photo">` : `<span class="vf-show-entregador__photo vf-show-entregador__photo--empty">👤</span>`}
        <div class="vf-show-entregador__nome">${esc(ent.nome)}</div>
        ${ent.whatsapp_url
          ? `<a class="vf-show-btn vf-show-btn--success vf-show-btn--block" href="${esc(ent.whatsapp_url)}" target="_blank" rel="noopener noreferrer">Chamar no WhatsApp</a>`
          : `<p class="vf-show-warn">Ajuste o WhatsApp em editar entregador.</p>`}
      </div>`).join("")}</div>
    </div>`;
  }

  function renderEntregadorLink(order) {
    if (order.fulfillment !== "entrega" || !order.url_entregador) return "";
    return `<div class="vf-show-card vf-show-card--sidebar">
      <h3 class="vf-show-card__title">Link do entregador</h3>
      <p class="vf-show-help">Mostra endereço, itens, pagamento e o <strong>código do pedido</strong> para conferir com o cliente. O entregador pode marcar <strong>entregue</strong>, <strong>cancelado</strong> ou <strong>endereço não encontrado</strong>.</p>
      <div class="vf-show-input-group">
        <input readonly id="vf-url-entregador" class="vf-show-input-group__field" value="${esc(order.url_entregador)}">
        <button class="vf-show-btn vf-show-btn--outline" type="button" data-vf-copy-url>Copiar</button>
      </div>
      <a class="vf-show-btn vf-show-btn--outline vf-show-btn--block" href="${esc(order.url_entregador)}" target="_blank" rel="noopener noreferrer">Abrir página do entregador</a>
    </div>`;
  }

  function renderWhatsAppAlerts(order) {
    if (!order._whatsapp_aviso_url && !order._whatsapp_indisponivel) return "";
    let html = "";
    if (order._whatsapp_aviso_url) {
      html += `<div class="vf-show-alert vf-show-alert--success">
        <div class="vf-show-alert__text"><strong>Avisar o cliente no WhatsApp</strong> — ícone de loja, cliente, sacola com código, status e link para acompanhar.</div>
        <a class="vf-show-btn vf-show-btn--success" href="${esc(order._whatsapp_aviso_url)}" target="_blank" rel="noopener noreferrer">Abrir WhatsApp do cliente</a>
      </div>`;
    }
    if (order._whatsapp_indisponivel) {
      html += `<div class="vf-show-alert vf-show-alert--warn">${esc(order._whatsapp_indisponivel)}</div>`;
    }
    return html;
  }

  function renderPendingBanner(order, isPending) {
    if (!isPending) return "";
    return `<div class="vf-show-alert vf-show-alert--danger">
      <div class="vf-show-alert__text">
        <strong><span class="vf-show-bell">🔔</span> Novo pedido — precisa da sua confirmação</strong>
        <span>O cliente já finalizou na vitrine. Só avance o preparo depois que você <strong>aceitar</strong>. Se recusar, o pedido é cancelado e o estoque volta.</span>
      </div>
      <div class="vf-show-alert__actions">
        <button class="vf-show-btn vf-show-btn--success vf-show-btn--sm" type="button" data-vf-detail-status="recebido">Aceitar pedido</button>
        <button class="vf-show-btn vf-show-btn--danger-outline vf-show-btn--sm" type="button" data-vf-detail-status="cancelado">Recusar</button>
      </div>
    </div>`;
  }

  function renderStatusForm(order) {
    if (order.status === "pendente_loja") {
      return `<div class="vf-show-card vf-show-card--sidebar">
        <h3 class="vf-show-card__title">Status do pedido</h3>
        <p class="vf-show-help">Enquanto o pedido não for aceito, o status não pode ser alterado aqui. Use <strong>Aceitar pedido</strong> ou <strong>Recusar</strong> no aviso vermelho acima.</p>
      </div>`;
    }
    const options = Object.entries(labels).map(([value, label]) =>
      `<option value="${esc(value)}" ${order.status === value ? "selected" : ""}>${esc(label)}</option>`).join("");
    return `<div class="vf-show-card vf-show-card--sidebar">
      <h3 class="vf-show-card__title">Status do pedido</h3>
      <form id="vfStatusForm" class="vf-show-stack">
        <select name="status" class="vf-show-select" required>${options}</select>
        <button class="vf-show-btn vf-show-btn--primary vf-show-btn--block" type="submit">Atualizar status</button>
      </form>
    </div>`;
  }

  function renderClienteBlock(order, addressLine) {
    return `<div class="vf-show-card vf-show-card--sidebar">
      <h3 class="vf-show-card__title">Cliente</h3>
      <p class="vf-show-cliente-nome">${esc(order.cliente_nome)}</p>
      <p class="vf-show-help">${esc(order.cliente_telefone || "—")}</p>
      ${order.cliente_email ? `<p class="vf-show-help">${esc(order.cliente_email)}</p>` : ""}
      <p class="vf-show-help">${esc(addressLine || (order.fulfillment === "entrega" ? "" : "Retirada na loja"))}${order.endereco?.complemento ? `<br>${esc(order.endereco.complemento)}` : ""}</p>
      <p class="vf-show-help vf-show-help--tight"><strong>Pagamento:</strong> ${esc(order.pagamento_descricao || order.pagamento_forma || "—")}</p>
      ${order.observacoes ? `<p class="vf-show-help vf-show-help--tight"><strong>Obs.:</strong> ${esc(order.observacoes)}</p>` : ""}
    </div>`;
  }

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
        if (status === "cancelado" && !confirm("Recusar este pedido? O cliente verá como cancelado e o estoque volta.")) return;
        let result = null;
        if (status === "recebido") {
          pararAlarmePedido();
          result = await postDecisaoPendente(Number(id), "aceitar");
        } else if (status === "cancelado") {
          pararAlarmePedido();
          result = await postDecisaoPendente(Number(id), "recusar");
        } else {
          result = await changeStatus(Number(id), status);
        }
        if (result) {
          if (status === "recebido" || status === "cancelado") processarPollAposDecisao(result);
          else toast("Status do pedido atualizado.", "success");
          await (refresh || (() => loadOrders()))();
        }
      };
    });
  }

  async function changeStatus(id, status, after) {
    try {
      const result = await api(`/pedidos/${id}/status`, {
        method: "PATCH",
        body: JSON.stringify({ status }),
      });
      toast("Status do pedido atualizado.", "success");
      if (typeof after === "function") {
        await after(result);
      }
      return result;
    } catch (error) {
      toast(error?.message || "Não foi possível atualizar o pedido.", "error");
      return null;
    }
  }

  function optionsText(options) {
    const additions = (options?.adicionais || []).map((item) =>
      `${item.quantidade || 1}× ${item.nome}`).join(", ");
    const removals = (options?.retiradas || []).map((item) => `sem ${item.nome}`).join(", ");
    return [additions, removals].filter(Boolean).join(" · ") || "—";
  }

  async function openOrder(id, extras) {
    const root = $("deliveryPedidosRoot");
    if (!root) return;
    try {
      const order = await api(`/pedidos/${id}`);
      if (extras?.whatsapp_aviso_url) order._whatsapp_aviso_url = extras.whatsapp_aviso_url;
      if (extras?.whatsapp_indisponivel) order._whatsapp_indisponivel = extras.whatsapp_indisponivel;
      const address = order.endereco || {};
      const addressLine = address.texto || [
        address.rua, address.numero, address.bairro, address.cidade,
        address.uf,
      ].filter(Boolean).join(", ");
      const isPending = order.status === "pendente_loja";
      const freteLabel = order.fulfillment === "retirada" ? "Retirada" : "Taxa entrega";
      const tipoEntrega = order.fulfillment === "retirada" ? "Retirada no balcão" : "Entrega";
      const cepFmt = address.cep && String(address.cep).length >= 8
        ? ` · CEP ${String(address.cep).substring(0, 5)}-${String(address.cep).substring(5)}`
        : "";

      root.innerHTML = `<div class="vf-orders vf-order-show">
        ${breadcrumb(order.codigo_publico, true)}
        ${renderWhatsAppAlerts(order)}
        ${renderPendingBanner(order, isPending)}
        <div class="vf-order-show__grid">
          <div class="vf-order-show__main">
            <div class="vf-show-card vf-show-card--main">
              <div class="vf-show-main-head">
                <div>
                  <h2 class="vf-show-code">${esc(order.codigo_publico)}</h2>
                  <p class="vf-show-meta">${dateTime(order.created_at)} · Canal ${esc(rotuloCanal(order.canal))} · ${esc(tipoEntrega)}${cepFmt}</p>
                </div>
                ${badge(order.status)}
              </div>
              <div class="vf-show-table-wrap">
                <table class="vf-show-table">
                  <thead><tr><th>Item</th><th class="vf-show-table__qty">Qtd</th><th class="vf-show-table__money">Total</th></tr></thead>
                  <tbody>${(order.itens || []).map((item) => `<tr>
                    <td>${esc(item.nome_produto)}${optionsText(item.opcoes) !== "—" ? `<div class="vf-show-item-opt">${esc(optionsText(item.opcoes))}</div>` : ""}</td>
                    <td class="vf-show-table__qty">${number(item.quantidade)}</td>
                    <td class="vf-show-table__money">${money(item.subtotal)}</td>
                  </tr>`).join("")}</tbody>
                  <tfoot>
                    <tr><th colspan="2">Subtotal</th><th class="vf-show-table__money">${money(order.subtotal)}</th></tr>
                    <tr><th colspan="2">${esc(freteLabel)}</th><th class="vf-show-table__money">${money(order.frete_valor)}</th></tr>
                    <tr class="vf-show-table__total"><th colspan="2">Total</th><th class="vf-show-table__money">${money(order.total)}</th></tr>
                  </tfoot>
                </table>
              </div>
            </div>
          </div>
          <aside class="vf-order-show__aside">
            ${renderCupomActions(order)}
            ${renderEntregadores(order)}
            ${renderEntregadorLink(order)}
            ${renderClienteBlock(order, addressLine)}
            ${renderStatusForm(order)}
            <button class="vf-show-btn vf-show-btn--outline vf-show-btn--block" type="button" data-vf-orders>Voltar à lista</button>
          </aside>
        </div>
      </div>`;
      bindSharedNavigation(root);
      bindOrderDetailActions(root, order);
      if (isPending) {
        pollCurrent = pollCurrent?.id === order.id ? pollCurrent : {
          id: order.id,
          codigo_publico: order.codigo_publico,
        };
        iniciarAlarmePedido();
      }
    } catch (error) {
      toast(error?.message || "Não foi possível abrir o pedido.", "error");
    }
  }

  function bindOrderDetailActions(root, order) {
    root.querySelectorAll("[data-vf-detail-status]").forEach((button) => {
      button.onclick = async () => {
        const status = button.dataset.vfDetailStatus;
        if (status === "cancelado" && !confirm("Recusar este pedido? O cliente verá como cancelado e o estoque volta.")) return;
        if (status === "recebido" || status === "cancelado") pararAlarmePedido();
        const result = status === "recebido" || status === "cancelado"
          ? await postDecisaoPendente(order.id, status === "recebido" ? "aceitar" : "recusar")
          : await changeStatus(order.id, status);
        if (result) {
          processarPollAposDecisao(result);
          await openOrder(order.id, {
            whatsapp_aviso_url: result.whatsapp_aviso_url,
            whatsapp_indisponivel: result.whatsapp_indisponivel,
          });
        }
      };
    });
    const statusForm = root.querySelector("#vfStatusForm");
    if (statusForm) {
      statusForm.onsubmit = async (event) => {
        event.preventDefault();
        const status = statusForm.elements.status.value;
        if (status === order.status) {
          toast("O status já estava assim. Escolha outro para atualizar.", "warning");
          return;
        }
        const result = await changeStatus(order.id, status);
        if (result) await openOrder(order.id, {
          whatsapp_aviso_url: result.whatsapp_aviso_url,
          whatsapp_indisponivel: result.whatsapp_indisponivel,
        });
      };
    }
    root.querySelectorAll("[data-vf-print]").forEach((button) => {
      button.onclick = async () => {
        try { await openPrint(order.id, button.dataset.vfPrint === "1"); }
        catch (error) { toast(error?.message || "Não foi possível imprimir o cupom.", "error"); }
      };
    });
    const copyUrl = root.querySelector("[data-vf-copy-url]");
    if (copyUrl) copyUrl.onclick = async () => {
      const input = root.querySelector("#vf-url-entregador");
      try {
        await navigator.clipboard.writeText(input?.value || order.url_entregador);
        toast("Link copiado.", "success");
      } catch (_) {
        input?.select();
        document.execCommand("copy");
        toast("Link copiado.", "success");
      }
    };
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
            <label class="vf-field vf-col-6"><span>Pagamento</span><select name="pagamento_forma" id="vfPagamentoForma">
              <option value="pix">PIX</option><option value="cartao_credito">Cartão crédito (maquininha)</option>
              <option value="cartao_debito">Cartão débito (maquininha)</option><option value="dinheiro">Dinheiro</option>
            </select></label>
            <label class="vf-field vf-col-6 vf-troco-field" hidden><span>Troco para (R$)</span><input name="pagamento_troco_para" type="number" min="0" step="0.01" placeholder="Valor que o cliente vai pagar"></label>
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
    const toggleTroco = () => {
      const field = form.querySelector(".vf-troco-field");
      if (field) field.hidden = form.elements.pagamento_forma?.value !== "dinheiro";
    };
    form.elements.fulfillment.onchange = () => toggleAddress(form);
    form.elements.pagamento_forma.onchange = toggleTroco;
    toggleAddress(form);
    toggleTroco();
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
            pagamento_troco_para: value("pagamento_forma") === "dinheiro" ? (value("pagamento_troco_para") ? Number(value("pagamento_troco_para")) : null) : null,
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

  let pollTimer = null;
  let pollModalOpen = false;
  let pollSubmitting = false;
  let pollAlarmTimer = null;
  let pollCurrent = null;
  const INTERVALO_ALARM_SEG = 2.35;

  function pararAlarmePedido() {
    if (pollAlarmTimer) {
      clearInterval(pollAlarmTimer);
      pollAlarmTimer = null;
    }
  }

  function tocarSomAlarmePedido() {
    try {
      const ctx = new (window.AudioContext || window.webkitAudioContext)();
      const t = ctx.currentTime;
      [
        { f: 1046, d: 0.11, off: 0 },
        { f: 784, d: 0.11, off: 0.14 },
        { f: 1318, d: 0.14, off: 0.32 },
      ].forEach((s) => {
        const o = ctx.createOscillator();
        const g = ctx.createGain();
        o.type = "square";
        o.frequency.value = s.f;
        g.gain.value = 0.055;
        o.connect(g);
        g.connect(ctx.destination);
        const st = t + s.off;
        o.start(st);
        o.stop(st + s.d);
      });
      setTimeout(() => ctx.close(), 900);
    } catch (_) {}
  }

  function iniciarAlarmePedido() {
    pararAlarmePedido();
    const tick = () => {
      if (pollCurrent) tocarSomAlarmePedido();
    };
    tick();
    pollAlarmTimer = setInterval(tick, INTERVALO_ALARM_SEG * 1000);
  }

  async function postDecisaoPendente(id, decisao) {
    return api(`/pedidos/${id}/pendente`, {
      method: "POST",
      body: JSON.stringify({ decisao }),
    });
  }

  function processarPollAposDecisao(result) {
    toast(result.mensagem || "Pedido atualizado.", "success");
    if (result.proximo) {
      renderModalCorpo(result.proximo);
      showPendingModalShell();
      iniciarAlarmePedido();
    } else {
      hidePendingModal();
    }
    if ($("deliveryPedidosRoot")) loadOrders("");
    if ($("deliveryDashboardRoot")) loadDashboard();
  }

  function ensurePollModal() {
    if (document.getElementById("vfModalPedidoPendente")) return;
    document.body.insertAdjacentHTML("beforeend", `<div class="vf-pending-modal" id="vfModalPedidoPendente" hidden>
      <div class="vf-pending-modal__backdrop"></div>
      <div class="vf-pending-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="vfModalPedidoPendenteTitulo">
        <header class="vf-pending-modal__head">
          <h2 id="vfModalPedidoPendenteTitulo"><span class="vf-show-bell">🔔</span> <span id="vf-modal-pendente-titulo-texto">Pedido aguardando confirmação</span></h2>
        </header>
        <div class="vf-pending-modal__body" id="vf-modal-pendente-corpo"><p class="vf-show-help">Carregando…</p></div>
        <footer class="vf-pending-modal__foot">
          <button type="button" class="vf-show-btn vf-show-btn--outline vf-show-btn--sm" id="vf-modal-pendente-abrir" style="display:none">Abrir pedido completo</button>
          <div class="vf-pending-modal__actions">
            <button type="button" class="vf-show-btn vf-show-btn--danger-outline" id="vf-modal-btn-recusar" disabled>Recusar pedido</button>
            <button type="button" class="vf-show-btn vf-show-btn--success" id="vf-modal-btn-aceitar" disabled>Aceitar pedido</button>
          </div>
        </footer>
      </div>
    </div>`);
    document.getElementById("vf-modal-btn-aceitar").onclick = () => decidePending("aceitar");
    document.getElementById("vf-modal-btn-recusar").onclick = () => {
      if (!confirm("Recusar este pedido? O cliente verá como cancelado e o estoque volta.")) return;
      decidePending("recusar");
    };
    document.getElementById("vf-modal-pendente-abrir").onclick = () => {
      if (!pollCurrent) return;
      const id = pollCurrent.id;
      document.getElementById("vfModalPedidoPendente").hidden = true;
      window.navigateTo?.("deliveryPedidos");
      setTimeout(() => openOrder(id), 0);
    };
  }

  function renderModalCorpo(p) {
    const body = document.getElementById("vf-modal-pendente-corpo");
    const titulo = document.getElementById("vf-modal-pendente-titulo-texto");
    if (!body || !titulo) return;
    titulo.textContent = `Confirmar: ${p.codigo_publico || ""}`;
    body.innerHTML = `<p class="vf-show-help">${esc(String(p.created_at || ""))} · ${esc(String(p.tipo_entrega || p.fulfillment_rotulo || ""))}</p>
      <p class="vf-show-modal-code">${esc(String(p.codigo_publico || ""))}</p>
      <p class="vf-show-help"><strong>Cliente:</strong> ${esc(String(p.cliente_nome || ""))}</p>
      <p class="vf-show-help vf-show-help--total"><strong>Total:</strong> <span>${esc(String(p.total_fmt || money(p.total || 0)))}</span></p>
      <h3 class="vf-show-card__title">Itens</h3>
      <ul class="vf-show-modal-itens">${(p.itens || []).map((it) =>
        `<li><span>${esc(it.nome)}</span><span>× ${esc(String(it.qtd))}</span></li>`).join("")}</ul>`;
    document.getElementById("vf-modal-btn-aceitar").disabled = false;
    document.getElementById("vf-modal-btn-recusar").disabled = false;
    const abrir = document.getElementById("vf-modal-pendente-abrir");
    abrir.style.display = "";
    pollCurrent = p;
  }

  function showPendingModalShell() {
    ensurePollModal();
    pollModalOpen = true;
    document.getElementById("vfModalPedidoPendente").hidden = false;
    iniciarAlarmePedido();
  }

  function hidePendingModal() {
    pararAlarmePedido();
    pollModalOpen = false;
    pollCurrent = null;
    const modal = document.getElementById("vfModalPedidoPendente");
    if (modal) modal.hidden = true;
  }

  async function decidePending(decisao) {
    if (!pollCurrent || pollSubmitting) return;
    pararAlarmePedido();
    pollSubmitting = true;
    document.getElementById("vf-modal-btn-aceitar").disabled = true;
    document.getElementById("vf-modal-btn-recusar").disabled = true;
    try {
      const result = await postDecisaoPendente(pollCurrent.id, decisao);
      processarPollAposDecisao(result);
    } catch (error) {
      toast(error?.message || "Não foi possível atualizar o pedido.", "error");
      if (pollCurrent) renderModalCorpo(pollCurrent);
      if (pollModalOpen && pollCurrent) iniciarAlarmePedido();
    } finally {
      pollSubmitting = false;
    }
  }

  function processarPoll(data) {
    const lista = data.pedidos || [];
    if (!data.enabled || lista.length === 0) {
      pararAlarmePedido();
      if (pollModalOpen) hidePendingModal();
      return;
    }
    const primeiro = lista[0];
    if (!pollModalOpen) {
      renderModalCorpo(primeiro);
      showPendingModalShell();
      return;
    }
    if (!pollCurrent || pollCurrent.id !== primeiro.id) {
      renderModalCorpo(primeiro);
      iniciarAlarmePedido();
    }
  }

  async function pollPendentes() {
    if (pollSubmitting) return;
    try {
      const data = await api("/pedidos/pendentes-poll");
      processarPoll(data);
    } catch (_) {}
  }

  function startPendingPoll() {
    if (pollTimer) return;
    ensurePollModal();
    pollPendentes();
    pollTimer = setInterval(pollPendentes, 10000);
  }

  window.loadDeliveryDashboard = loadDashboard;
  window.loadDeliveryPedidos = loadOrders;
  startPendingPoll();
})();
