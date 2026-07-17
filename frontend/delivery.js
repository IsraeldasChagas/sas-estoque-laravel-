/**
 * Delivery — catálogo comercial, vitrine, pedidos, frete e operação.
 * O vínculo com produtos do estoque é apenas uma referência opcional.
 */
(function () {
  "use strict";

  const state = { categorias: [], produtos: [], adicionais: [] };
  const $ = (id) => document.getElementById(id);
  const esc = (v) => String(v == null ? "" : v).replace(/&/g, "&amp;").replace(/</g, "&lt;")
    .replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#39;");
  const money = (v) => Number(v || 0).toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
  const toast = (m, t) => window.showToast?.(m, t || "info");
  const api = (path, options) => {
    if (typeof window.fetchJSON !== "function") throw new Error("Conexão com a API indisponível.");
    return window.fetchJSON(`/delivery${path}`, options || {});
  };
  const val = (form, name) => form.elements[name]?.value ?? "";
  const bool = (form, name) => !!form.elements[name]?.checked;
  const shell = (content) => `<div class="orc-shell">${content}</div>`;
  const header = (title, subtitle, icon, action) => `<header class="orc-head"><div>
    <div class="orc-breadcrumb">Delivery / ${esc(title)}</div>
    <div class="orc-head__title"><span class="orc-head__icon">${icon}</span><div><h2>${esc(title)}</h2><p>${esc(subtitle)}</p></div></div>
    </div>${action || ""}</header>`;
  const emptyRow = (cols, text) => `<tr><td colspan="${cols}"><div class="orc-empty"><strong>Nenhum registro</strong><p>${esc(text)}</p></div></td></tr>`;
  const badge = (status) => {
    const good = ["ativo", "aberta", "entregue", "pronto"].includes(String(status));
    const warn = ["pendente_loja", "recebido", "preparo", "rota"].includes(String(status));
    return `<span class="orc-badge orc-badge--${good ? "success" : warn ? "warning" : "neutral"}">${esc(String(status).replaceAll("_", " "))}</span>`;
  };
  const formJson = (form, names) => Object.fromEntries(names.map((n) => [n, val(form, n)]));
  const statusNext = {
    pendente_loja: ["recebido", "cancelado"], recebido: ["preparo", "cancelado"],
    preparo: ["pronto", "cancelado"], pronto: ["rota", "entregue", "endereco_nao_encontrado", "cancelado"],
    rota: ["entregue", "endereco_nao_encontrado", "cancelado"],
  };

  async function hydrate() {
    const [c, p, a] = await Promise.all([api("/categorias"), api("/produtos"), api("/adicionais")]);
    state.categorias = c.items || []; state.produtos = p.items || []; state.adicionais = a.items || [];
  }

  async function loadDashboard() {
    const root = $("deliveryDashboardRoot"); if (!root) return;
    const d = await api("/dashboard"); const r = d.resumo || {};
    const cards = [
      ["Pedidos", r.total_pedidos, "📋"], ["Em aberto", r.abertos, "🕐"], ["Pendentes", r.pendente_loja, "⚠"],
      ["Entregues", r.entregues, "✓"], ["Faturamento", money(r.faturamento), "R$"], ["Ticket médio", money(r.ticket_medio), "↗"],
    ];
    root.innerHTML = shell(header("Dashboard", "Visão da operação, faturamento e fila de pedidos.", "🛵",
      `<button class="btn primary" type="button" data-go="deliveryPedidos">Ver pedidos</button>`)
      + `<div class="orc-kpis">${cards.map((c) => `<article class="orc-kpi"><span>${c[2]} ${esc(c[0])}</span><strong>${esc(c[1] ?? 0)}</strong></article>`).join("")}</div>
      <div class="orc-table-card"><div class="orc-section-title"><div><h3>Últimos pedidos</h3><p>Acompanhamento rápido da operação.</p></div></div>
      <div class="table-scroll"><table><thead><tr><th>Pedido</th><th>Cliente</th><th>Status</th><th>Total</th><th>Data</th></tr></thead><tbody>
      ${(d.ultimos || []).map((x) => `<tr><td><strong>${esc(x.codigo_publico)}</strong></td><td>${esc(x.cliente_nome)}</td><td>${badge(x.status)}</td><td>${money(x.total)}</td><td>${new Date(x.created_at).toLocaleString("pt-BR")}</td></tr>`).join("") || emptyRow(5, "Os pedidos aparecerão aqui.")}</tbody></table></div></div>`);
    root.querySelector("[data-go]")?.addEventListener("click", () => window.navigateTo?.("deliveryPedidos"));
  }

  async function loadCatalogo() {
    const root = $("deliveryCatalogoRoot"); if (!root) return;
    const data = await api("/catalogo");
    root.innerHTML = shell(header("Catálogo (Consulta)", "Visualize exatamente os itens ativos e publicados na vitrine.", "🔎",
      `<button class="btn secondary" type="button" data-go="deliveryProdutos">Gerenciar produtos</button>`)
      + `<form id="dlvCatalogoBusca" class="orc-filterbar"><label>Buscar no catálogo<input name="busca" placeholder="Produto, SKU ou descrição"></label><button class="btn primary">Buscar</button><span class="orc-badge orc-badge--neutral">${Number(data.total_produtos || 0)} produtos publicados</span></form>
      <div id="dlvCatalogoGrupos">${renderCatalogo(data.categorias || [])}</div>`);
    root.querySelector("[data-go]").onclick = () => window.navigateTo?.("deliveryProdutos");
    $("dlvCatalogoBusca").onsubmit = async (e) => {
      e.preventDefault(); const result = await api(`/catalogo?busca=${encodeURIComponent(val(e.currentTarget, "busca"))}`);
      $("dlvCatalogoGrupos").innerHTML = renderCatalogo(result.categorias || []);
    };
  }

  function renderCatalogo(grupos) {
    if (!grupos.length) return `<div class="orc-empty"><strong>Catálogo vazio</strong><p>Cadastre categorias e produtos visíveis na loja.</p></div>`;
    return grupos.map((g) => `<section class="orc-card"><div class="orc-section-title"><div><h3>${esc(g.nome)}</h3><p>${g.produtos.length} item(ns)</p></div></div>
      <div class="orc-choice-grid">${g.produtos.map((p) => `<article class="orc-choice"><div><span class="orc-badge orc-badge--neutral">${esc(p.sku || "sem SKU")}</span>
      <h3>${esc(p.nome)}</h3><p>${esc(p.descricao || "Sem descrição")}</p></div><strong>${money(p.preco)}</strong></article>`).join("")}</div></section>`).join("");
  }

  async function loadCategorias() {
    const root = $("deliveryCategoriasRoot"); if (!root) return;
    const data = await api("/categorias"); state.categorias = data.items || [];
    root.innerHTML = shell(header("Categorias", "Organize a ordem de apresentação do catálogo.", "☷")
      + `<form id="dlvCategoriaForm" class="orc-card"><div class="orc-section-title"><h3>Nova categoria</h3></div><div class="orc-form-grid">
      <label>Nome<input name="nome" required></label><label>Ordem<input name="ordem" type="number" min="0" value="0"></label>
      <label class="checkbox-label"><input name="ativo" type="checkbox" checked> Categoria ativa</label></div><button class="btn primary">Cadastrar</button></form>
      <div class="orc-table-card"><div class="table-scroll"><table><thead><tr><th>Ordem</th><th>Categoria</th><th>Status</th><th>Ações</th></tr></thead><tbody>
      ${state.categorias.map((x) => `<tr><td>${x.ordem}</td><td><strong>${esc(x.nome)}</strong></td><td>${badge(Number(x.ativo) ? "ativo" : "inativo")}</td><td>
      <button class="btn small secondary" data-cat-edit="${x.id}">Editar</button> <button class="btn small danger" data-cat-del="${x.id}">Excluir</button></td></tr>`).join("") || emptyRow(4, "Crie a primeira categoria.")}</tbody></table></div></div>`);
    $("dlvCategoriaForm").onsubmit = async (e) => {
      e.preventDefault(); const f = e.currentTarget;
      await api("/categorias", { method: "POST", body: JSON.stringify({ nome: val(f, "nome"), ordem: Number(val(f, "ordem")), ativo: bool(f, "ativo") }) });
      toast("Categoria criada.", "success"); await loadCategorias();
    };
    root.onclick = async (e) => {
      const edit = e.target.closest("[data-cat-edit]"), del = e.target.closest("[data-cat-del]");
      if (edit) {
        const row = state.categorias.find((x) => Number(x.id) === Number(edit.dataset.catEdit));
        const nome = prompt("Nome da categoria:", row.nome); if (!nome) return;
        await api(`/categorias/${row.id}`, { method: "PUT", body: JSON.stringify({ nome }) });
      }
      if (del && confirm("Excluir esta categoria? Os produtos ficarão sem categoria.")) await api(`/categorias/${del.dataset.catDel}`, { method: "DELETE" });
      if (edit || del) { toast("Categorias atualizadas.", "success"); await loadCategorias(); }
    };
  }

  async function loadProdutos() {
    const root = $("deliveryProdutosRoot"); if (!root) return;
    await hydrate();
    const cats = `<option value="">Sem categoria</option>${state.categorias.map((c) => `<option value="${c.id}">${esc(c.nome)}</option>`).join("")}`;
    root.innerHTML = shell(header("Produtos Delivery", "Catálogo comercial independente, com vínculo opcional ao estoque.", "◆")
      + `<form id="dlvProdutoForm" class="orc-card"><div class="orc-section-title"><div><h3>Novo produto</h3><p>Preço e publicação pertencem ao Delivery; nenhum saldo de estoque será alterado.</p></div></div>
      <div class="orc-form-grid"><label>Nome<input name="nome" required></label><label>Categoria<select name="categoria_id">${cats}</select></label>
      <label>SKU<input name="sku"></label><label>Preço<input name="preco" type="number" min="0" step="0.01" required></label>
      <label>Referência do produto no estoque<input name="estoque_produto_id" type="number" min="1" placeholder="Opcional"></label>
      <label>Apresentação<input name="apresentacao" placeholder="unidade, porção, 500 ml..."></label>
      <label class="checkbox-label"><input name="visivel_loja" type="checkbox" checked> Visível na vitrine</label>
      <label class="checkbox-label"><input name="permite_adicionais" type="checkbox"> Permite adicionais</label></div>
      <label>Descrição<textarea name="descricao" rows="2"></textarea></label><button class="btn primary">Cadastrar produto</button></form>
      <div class="orc-table-card"><div class="table-scroll"><table><thead><tr><th>Produto</th><th>Categoria</th><th>Preço</th><th>Vitrine</th><th>Estoque ref.</th><th>Ações</th></tr></thead><tbody>
      ${state.produtos.map((p) => `<tr><td><strong>${esc(p.nome)}</strong><small>${esc(p.sku || "")}</small></td><td>${esc(state.categorias.find((c) => Number(c.id) === Number(p.categoria_id))?.nome || "Sem categoria")}</td>
      <td>${money(p.preco)}</td><td>${badge(Number(p.visivel_loja) ? "ativo" : "inativo")}</td><td>${esc(p.estoque_produto_id || "—")}</td><td>
      <button class="btn small secondary" data-prod-edit="${p.id}">Editar preço</button> <button class="btn small neutral" data-prod-add="${p.id}">Adicionais</button> <button class="btn small danger" data-prod-del="${p.id}">Excluir</button></td></tr>`).join("") || emptyRow(6, "Cadastre o primeiro produto comercial.")}</tbody></table></div></div>`);
    $("dlvProdutoForm").onsubmit = async (e) => {
      e.preventDefault(); const f = e.currentTarget;
      await api("/produtos", { method: "POST", body: JSON.stringify({
        nome: val(f, "nome"), categoria_id: val(f, "categoria_id") ? Number(val(f, "categoria_id")) : null,
        sku: val(f, "sku") || null, preco: Number(val(f, "preco")), descricao: val(f, "descricao") || null,
        estoque_produto_id: val(f, "estoque_produto_id") ? Number(val(f, "estoque_produto_id")) : null,
        apresentacao: val(f, "apresentacao") || null, ativo: true, visivel_loja: bool(f, "visivel_loja"), permite_adicionais: bool(f, "permite_adicionais"),
      }) });
      toast("Produto Delivery criado.", "success"); await loadProdutos();
    };
    root.onclick = async (e) => {
      const edit = e.target.closest("[data-prod-edit]"), adds = e.target.closest("[data-prod-add]"), del = e.target.closest("[data-prod-del]");
      if (edit) {
        const row = state.produtos.find((x) => Number(x.id) === Number(edit.dataset.prodEdit));
        const preco = prompt(`Novo preço de ${row.nome}:`, row.preco); if (preco === null) return;
        await api(`/produtos/${row.id}`, { method: "PUT", body: JSON.stringify({ preco: Number(String(preco).replace(",", ".")) }) });
      }
      if (adds) {
        const detail = await api(`/produtos/${adds.dataset.prodAdd}`);
        const atuais = new Set((detail.adicionais || []).map((x) => Number(x.id)));
        const nomes = state.adicionais.map((a) => `${a.id}: ${a.nome}${atuais.has(Number(a.id)) ? " [vinculado]" : ""}`).join("\n");
        const ids = prompt(`Informe IDs dos adicionais separados por vírgula:\n${nomes}`, [...atuais].join(",")); if (ids === null) return;
        await api(`/produtos/${adds.dataset.prodAdd}/adicionais`, { method: "POST", body: JSON.stringify({ adicional_ids: ids.split(",").map(Number).filter(Boolean) }) });
      }
      if (del && confirm("Excluir este produto do catálogo Delivery?")) await api(`/produtos/${del.dataset.prodDel}`, { method: "DELETE" });
      if (edit || adds || del) { toast("Produto atualizado.", "success"); await loadProdutos(); }
    };
  }

  async function loadAdicionais() {
    const root = $("deliveryAdicionaisRoot"); if (!root) return;
    state.adicionais = (await api("/adicionais")).items || [];
    root.innerHTML = shell(header("Adicionais", "Cadastre acréscimos pagos e opções de retirada.", "＋")
      + `<form id="dlvAdicionalForm" class="orc-card"><div class="orc-form-grid"><label>Nome<input name="nome" required></label>
      <label>Tipo<select name="tipo"><option value="acrescentar">Acrescentar</option><option value="retirar">Retirar</option></select></label>
      <label>Preço<input name="preco" type="number" min="0" step="0.01" value="0"></label><label>Ordem<input name="ordem" type="number" min="0" value="0"></label></div>
      <button class="btn primary">Cadastrar adicional</button></form>
      <div class="orc-table-card"><div class="table-scroll"><table><thead><tr><th>Adicional</th><th>Tipo</th><th>Preço</th><th>Status</th><th></th></tr></thead><tbody>
      ${state.adicionais.map((x) => `<tr><td><strong>${esc(x.nome)}</strong></td><td>${esc(x.tipo)}</td><td>${money(x.preco)}</td><td>${badge(Number(x.ativo) ? "ativo" : "inativo")}</td>
      <td><button class="btn small danger" data-add-del="${x.id}">Excluir</button></td></tr>`).join("") || emptyRow(5, "Crie adicionais para personalizar produtos.")}</tbody></table></div></div>`);
    $("dlvAdicionalForm").onsubmit = async (e) => {
      e.preventDefault(); const f = e.currentTarget;
      await api("/adicionais", { method: "POST", body: JSON.stringify({ nome: val(f, "nome"), tipo: val(f, "tipo"), preco: Number(val(f, "preco")), ordem: Number(val(f, "ordem")), ativo: true }) });
      toast("Adicional criado.", "success"); await loadAdicionais();
    };
    root.onclick = async (e) => {
      const b = e.target.closest("[data-add-del]"); if (!b || !confirm("Excluir adicional e seus vínculos?")) return;
      await api(`/adicionais/${b.dataset.addDel}`, { method: "DELETE" }); toast("Adicional excluído.", "success"); await loadAdicionais();
    };
  }

  async function loadVitrine() {
    const root = $("deliveryVitrineRoot"); if (!root) return;
    const c = await api("/vitrine");
    root.innerHTML = shell(header("Vitrine", "Personalize a identidade e a disponibilidade da loja.", "▤")
      + `<div class="orc-grid orc-grid--2"><form id="dlvVitrineForm" class="orc-card"><div class="orc-section-title"><h3>Identidade da loja</h3></div>
      <div class="orc-form-grid"><label>Nome da loja<input name="nome_loja" value="${esc(c.nome_loja || "")}"></label><label>Slug público<input name="slug" value="${esc(c.slug || "")}"></label>
      <label>Cor principal<input name="cor_primaria" type="color" value="${esc(c.cor_primaria || "#e85d24")}"></label><label>WhatsApp<input name="whatsapp" value="${esc(c.whatsapp || "")}"></label>
      <label class="checkbox-label"><input name="ativo" type="checkbox" ${c.ativo ? "checked" : ""}> Vitrine ativa</label><label class="checkbox-label"><input name="aberta" type="checkbox" ${c.aberta ? "checked" : ""}> Loja aberta</label></div>
      <label>Descrição<textarea name="descricao" rows="3">${esc(c.descricao || "")}</textarea></label><label>Endereço exibido<textarea name="endereco_texto" rows="2">${esc(c.endereco_texto || "")}</textarea></label>
      <button class="btn primary">Salvar vitrine</button></form>
      <article class="orc-card" style="border-top:5px solid ${esc(c.cor_primaria || "#e85d24")}"><span class="orc-badge orc-badge--${c.aberta ? "success" : "neutral"}">${c.aberta ? "Loja aberta" : "Loja fechada"}</span>
      <h2>${esc(c.nome_loja || "Sua loja")}</h2><p>${esc(c.descricao || "A descrição da vitrine aparecerá aqui.")}</p><div class="orc-note"><strong>Endereço público planejado</strong><code>${esc(c.preview_path)}</code></div>
      <button class="btn secondary" type="button" data-go="deliveryCatalogo">Consultar catálogo publicado</button></article></div>`);
    $("dlvVitrineForm").onsubmit = async (e) => {
      e.preventDefault(); const f = e.currentTarget;
      await api("/vitrine", { method: "PUT", body: JSON.stringify({ ...formJson(f, ["nome_loja", "slug", "cor_primaria", "whatsapp", "descricao", "endereco_texto"]), ativo: bool(f, "ativo"), aberta: bool(f, "aberta") }) });
      toast("Vitrine atualizada.", "success"); await loadVitrine();
    };
    root.querySelector("[data-go]").onclick = () => window.navigateTo?.("deliveryCatalogo");
  }

  async function loadPedidos() {
    const root = $("deliveryPedidosRoot"); if (!root) return;
    const [list, catalogo] = await Promise.all([api("/pedidos"), api("/catalogo")]);
    const produtos = catalogo.produtos || [];
    root.innerHTML = shell(header("Pedidos", "Crie pedidos administrativos e acompanhe cada etapa.", "📋")
      + `<form id="dlvPedidoForm" class="orc-card"><div class="orc-section-title"><h3>Novo pedido</h3></div><div class="orc-form-grid">
      <label>Cliente<input name="cliente_nome" required></label><label>Telefone<input name="cliente_telefone"></label>
      <label>Tipo<select name="fulfillment"><option value="entrega">Entrega</option><option value="retirada">Retirada</option></select></label>
      <label>CEP<input name="endereco_cep"></label><label>Produto<select name="produto_id" required><option value="">Selecione</option>${produtos.map((p) => `<option value="${p.id}">${esc(p.nome)} — ${money(p.preco)}</option>`).join("")}</select></label>
      <label>Quantidade<input name="quantidade" type="number" min="1" value="1"></label><label>Pagamento<select name="pagamento_forma"><option value="pix">PIX</option><option value="cartao">Cartão</option><option value="dinheiro">Dinheiro</option></select></label></div>
      <button class="btn primary" ${produtos.length ? "" : "disabled"}>Criar pedido</button></form>
      <form id="dlvPedidosBusca" class="orc-filterbar"><label>Status<select name="status"><option value="">Todos</option>${["pendente_loja","recebido","preparo","pronto","rota","entregue","cancelado"].map((s) => `<option value="${s}">${s.replaceAll("_"," ")}</option>`).join("")}</select></label><button class="btn secondary">Filtrar</button></form>
      <div class="orc-table-card"><div class="table-scroll"><table><thead><tr><th>Pedido</th><th>Cliente</th><th>Entrega</th><th>Status</th><th>Total</th><th>Próxima etapa</th></tr></thead><tbody id="dlvPedidosBody">${pedidoRows(list.items || [])}</tbody></table></div></div>`);
    $("dlvPedidoForm").onsubmit = async (e) => {
      e.preventDefault(); const f = e.currentTarget;
      await api("/pedidos", { method: "POST", body: JSON.stringify({
        cliente_nome: val(f, "cliente_nome"), cliente_telefone: val(f, "cliente_telefone") || null,
        fulfillment: val(f, "fulfillment"), endereco_cep: val(f, "endereco_cep") || null,
        pagamento_forma: val(f, "pagamento_forma"), canal: "admin",
        itens: [{ produto_id: Number(val(f, "produto_id")), quantidade: Number(val(f, "quantidade")) }],
      }) });
      toast("Pedido criado.", "success"); await loadPedidos();
    };
    $("dlvPedidosBusca").onsubmit = async (e) => {
      e.preventDefault(); const data = await api(`/pedidos?status=${encodeURIComponent(val(e.currentTarget, "status"))}`);
      $("dlvPedidosBody").innerHTML = pedidoRows(data.items || []);
    };
    root.onclick = async (e) => {
      const b = e.target.closest("[data-pedido-status]"); if (!b) return;
      await api(`/pedidos/${b.dataset.id}/status`, { method: "PATCH", body: JSON.stringify({ status: b.dataset.pedidoStatus }) });
      toast("Status do pedido atualizado.", "success"); await loadPedidos();
    };
  }

  function pedidoRows(items) {
    return items.map((p) => `<tr><td><strong>${esc(p.codigo_publico)}</strong><small>${new Date(p.created_at).toLocaleString("pt-BR")}</small></td>
      <td>${esc(p.cliente_nome)}</td><td>${esc(p.fulfillment)}</td><td>${badge(p.status)}</td><td>${money(p.total)}</td><td>
      <div class="orc-row-actions">${(statusNext[p.status] || []).map((s) => `<button class="btn small ${s === "cancelado" ? "danger" : "primary"}" data-pedido-status="${s}" data-id="${p.id}">${s.replaceAll("_", " ")}</button>`).join("") || "Concluído"}</div></td></tr>`).join("") || emptyRow(6, "Crie ou receba o primeiro pedido.");
  }

  async function loadFretes() {
    const root = $("deliveryFretesRoot"); if (!root) return;
    const [faixas, config] = await Promise.all([api("/fretes/faixas"), api("/configuracoes")]);
    root.innerHTML = shell(header("Fretes", "Defina taxas fixas ou por faixa de CEP e teste o cálculo.", "📍")
      + `<div class="orc-grid orc-grid--2"><form id="dlvFaixaForm" class="orc-card"><div class="orc-section-title"><h3>Nova faixa de CEP</h3></div><div class="orc-form-grid">
      <label>Identificação<input name="label" placeholder="Centro"></label><label>CEP inicial<input name="cep_inicio" required></label><label>CEP final<input name="cep_fim" required></label>
      <label>Taxa<input name="taxa" type="number" min="0" step="0.01" required></label></div><button class="btn primary">Adicionar faixa</button></form>
      <form id="dlvFreteTeste" class="orc-card"><div class="orc-section-title"><h3>Simular frete</h3></div><p>Modo atual: <strong>${esc(config.frete_modo)}</strong></p>
      <div class="orc-form-grid"><label>CEP<input name="cep"></label><label>Subtotal<input name="subtotal" type="number" min="0" step="0.01" value="50"></label>
      <label class="checkbox-label"><input name="chuva" type="checkbox"> Aplicar chuva</label></div><button class="btn secondary">Calcular</button><div id="dlvFreteResultado"></div></form></div>
      <div class="orc-table-card"><div class="table-scroll"><table><thead><tr><th>Faixa</th><th>CEP inicial</th><th>CEP final</th><th>Taxa</th><th></th></tr></thead><tbody>
      ${(faixas.items || []).map((x) => `<tr><td>${esc(x.label || "Faixa")}</td><td>${esc(x.cep_inicio)}</td><td>${esc(x.cep_fim)}</td><td>${money(x.taxa)}</td><td><button class="btn small danger" data-faixa-del="${x.id}">Excluir</button></td></tr>`).join("") || emptyRow(5, "No modo taxa fixa, faixas não são necessárias.")}</tbody></table></div></div>`);
    $("dlvFaixaForm").onsubmit = async (e) => {
      e.preventDefault(); const f = e.currentTarget;
      await api("/fretes/faixas", { method: "POST", body: JSON.stringify({ ...formJson(f, ["label", "cep_inicio", "cep_fim"]), taxa: Number(val(f, "taxa")), ativo: true }) });
      toast("Faixa de frete criada.", "success"); await loadFretes();
    };
    $("dlvFreteTeste").onsubmit = async (e) => {
      e.preventDefault(); const f = e.currentTarget;
      const r = await api("/fretes/calcular", { method: "POST", body: JSON.stringify({ cep: val(f, "cep"), subtotal: Number(val(f, "subtotal")), chuva: bool(f, "chuva"), fulfillment: "entrega" }) });
      $("dlvFreteResultado").innerHTML = `<div class="orc-note"><strong>${r.bloqueado ? "Entrega indisponível" : money(r.frete_valor)}</strong><p>${esc(r.mensagem || r.origem || "")}</p></div>`;
    };
    root.onclick = async (e) => {
      const b = e.target.closest("[data-faixa-del]"); if (!b || !confirm("Excluir faixa?")) return;
      await api(`/fretes/faixas/${b.dataset.faixaDel}`, { method: "DELETE" }); await loadFretes();
    };
  }

  async function loadEntregadores() {
    const root = $("deliveryEntregadoresRoot"); if (!root) return;
    const items = (await api("/entregadores")).items || [];
    root.innerHTML = shell(header("Entregadores", "Gerencie a equipe e os veículos usados nas entregas.", "◎")
      + `<form id="dlvEntregadorForm" class="orc-card"><div class="orc-form-grid"><label>Nome<input name="nome" required></label><label>WhatsApp<input name="whatsapp"></label>
      <label>Placa<input name="moto_placa"></label><label>Modelo da moto<input name="moto_modelo"></label></div><button class="btn primary">Cadastrar entregador</button></form>
      <div class="orc-choice-grid">${items.map((x) => `<article class="orc-choice"><div><span>${badge(Number(x.ativo) ? "ativo" : "inativo")}</span><h3>${esc(x.nome)}</h3>
      <p>${esc(x.whatsapp || x.telefone || "Sem telefone")} · ${esc(x.moto_modelo || "Veículo não informado")} ${esc(x.moto_placa || "")}</p></div><button class="btn small danger" data-ent-del="${x.id}">Excluir</button></article>`).join("") || `<div class="orc-empty">Nenhum entregador cadastrado.</div>`}</div>`);
    $("dlvEntregadorForm").onsubmit = async (e) => {
      e.preventDefault(); const f = e.currentTarget;
      await api("/entregadores", { method: "POST", body: JSON.stringify({ ...formJson(f, ["nome", "whatsapp", "moto_placa", "moto_modelo"]), ativo: true }) });
      toast("Entregador cadastrado.", "success"); await loadEntregadores();
    };
    root.onclick = async (e) => {
      const b = e.target.closest("[data-ent-del]"); if (!b || !confirm("Excluir entregador?")) return;
      await api(`/entregadores/${b.dataset.entDel}`, { method: "DELETE" }); await loadEntregadores();
    };
  }

  async function loadConfiguracoes() {
    const root = $("deliveryConfiguracoesRoot"); if (!root) return;
    const c = await api("/configuracoes");
    root.innerHTML = shell(header("Configurações", "Operação, pagamentos e regras de entrega.", "⚙")
      + `<form id="dlvConfigForm" class="orc-card"><div class="orc-section-title"><div><h3>Operação da loja</h3><p>As alterações passam a valer nos próximos pedidos.</p></div></div>
      <div class="orc-form-grid"><label>Modo do frete<select name="frete_modo"><option value="fixed" ${c.frete_modo === "fixed" ? "selected" : ""}>Taxa fixa</option><option value="cep_band" ${c.frete_modo === "cep_band" ? "selected" : ""}>Faixas de CEP</option></select></label>
      <label>Taxa fixa<input name="frete_taxa_fixa" type="number" min="0" step="0.01" value="${esc(c.frete_taxa_fixa)}"></label>
      <label>Frete grátis acima de<input name="frete_gratis_acima" type="number" min="0" step="0.01" value="${esc(c.frete_gratis_acima || "")}"></label>
      <label>Acréscimo por chuva (%)<input name="frete_acrescimo_chuva_percent" type="number" min="0" step="0.01" value="${esc(c.frete_acrescimo_chuva_percent || 0)}"></label>
      <label>Chave PIX<input name="pix_chave" value="${esc(c.pix_chave || "")}"></label><label>Beneficiário PIX<input name="pix_beneficiario" value="${esc(c.pix_beneficiario || "")}"></label>
      <label>Formas de pagamento<input name="formas_pagamento" value="${esc(c.formas_pagamento || "pix,cartao,dinheiro")}"></label>
      <label class="checkbox-label"><input name="confirmar_pedidos" type="checkbox" ${c.confirmar_pedidos ? "checked" : ""}> Pedidos precisam de confirmação</label>
      <label class="checkbox-label"><input name="permite_retirada" type="checkbox" ${c.permite_retirada ? "checked" : ""}> Permitir retirada</label>
      <label class="checkbox-label"><input name="frete_chuva_ativa" type="checkbox" ${c.frete_chuva_ativa ? "checked" : ""}> Acréscimo de chuva ativo</label></div>
      <button class="btn primary">Salvar configurações</button></form>
      <div class="orc-note"><strong>Integração segura com estoque</strong><p>O Delivery mantém preços, publicação e pedidos em tabelas próprias. Nesta etapa, pedidos não dão baixa automática no estoque.</p></div>`);
    $("dlvConfigForm").onsubmit = async (e) => {
      e.preventDefault(); const f = e.currentTarget;
      await api("/configuracoes", { method: "PUT", body: JSON.stringify({
        frete_modo: val(f, "frete_modo"), frete_taxa_fixa: Number(val(f, "frete_taxa_fixa")),
        frete_gratis_acima: val(f, "frete_gratis_acima") === "" ? null : Number(val(f, "frete_gratis_acima")),
        frete_acrescimo_chuva_percent: Number(val(f, "frete_acrescimo_chuva_percent")),
        pix_chave: val(f, "pix_chave") || null, pix_beneficiario: val(f, "pix_beneficiario") || null,
        formas_pagamento: val(f, "formas_pagamento"), confirmar_pedidos: bool(f, "confirmar_pedidos"),
        permite_retirada: bool(f, "permite_retirada"), frete_chuva_ativa: bool(f, "frete_chuva_ativa"),
      }) });
      toast("Configurações salvas.", "success"); await loadConfiguracoes();
    };
  }

  window.loadDeliveryDashboard = loadDashboard;
  window.loadDeliveryCatalogo = loadCatalogo;
  window.loadDeliveryCategorias = loadCategorias;
  window.loadDeliveryProdutos = loadProdutos;
  window.loadDeliveryAdicionais = loadAdicionais;
  window.loadDeliveryVitrine = loadVitrine;
  window.loadDeliveryPedidos = loadPedidos;
  window.loadDeliveryFretes = loadFretes;
  window.loadDeliveryEntregadores = loadEntregadores;
  window.loadDeliveryConfiguracoes = loadConfiguracoes;
})();
