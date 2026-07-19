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
    renderProdutosList(root, state.produtos, "", "");
  }

  function deliveryImageUrl(url) {
    if (!url) return "";
    if (/^https?:\/\//i.test(url) || /^data:/i.test(url)) return url;
    const apiBase = String(window.APP_CONFIG?.API_URL || "").replace(/\/api\/?$/, "");
    return `${apiBase}${String(url).startsWith("/") ? "" : "/"}${url}`;
  }

  function produtoThumb(produto) {
    const url = deliveryImageUrl(produto.foto_url);
    return `<span class="vf-product-thumb">${url ? `<img src="${esc(url)}" alt="">` : "▧"}</span>`;
  }

  function renderProdutosList(root, items, q, ativo) {
    root.innerHTML = `<div class="vf-products">
      <div class="vf-products__breadcrumb"><button type="button" data-vf-go-dashboard>Dashboard</button> / Produtos</div>
      <div class="vf-products__toolbar">
        <h2>Catálogo</h2>
        <div class="vf-products__actions">
          <button class="vf-btn" type="button" data-vf-go-categorias>Categorias</button>
          <button class="vf-btn vf-btn--primary" type="button" data-vf-new-product>＋ Novo produto</button>
        </div>
      </div>
      <form class="vf-filter-bar" id="vfProdutosFiltro">
        <label class="vf-field"><span>Buscar</span><input type="search" name="q" value="${esc(q)}" placeholder="Nome, código interno, categoria..."></label>
        <label class="vf-field"><span>Status</span><select name="ativo"><option value="">Todos</option><option value="1" ${ativo === "1" ? "selected" : ""}>Ativo</option><option value="0" ${ativo === "0" ? "selected" : ""}>Inativo</option></select></label>
        <div class="vf-filter-bar__buttons"><button class="vf-btn" type="submit">Filtrar</button><button class="vf-btn" type="button" data-vf-clear-filter>Limpar</button></div>
      </form>
      <div class="vf-card vf-table-card"><div class="vf-table-wrap"><table class="vf-table">
        <thead><tr><th style="width:3.5rem"></th><th>Cód. interno</th><th>Nome</th><th>Categoria</th><th>Preço</th><th>Estoque</th><th>Status</th><th class="vf-table__right">Ações</th></tr></thead>
        <tbody id="vfProdutosBody">${renderProdutosRows(items)}</tbody>
      </table></div></div>
    </div>`;

    root.querySelector("[data-vf-go-dashboard]").onclick = () => window.navigateTo?.("deliveryDashboard");
    root.querySelector("[data-vf-go-categorias]").onclick = () => window.navigateTo?.("deliveryCategorias");
    root.querySelector("[data-vf-clear-filter]").onclick = () => renderProdutosList(root, state.produtos, "", "");
    $("vfProdutosFiltro").onsubmit = async (e) => {
      e.preventDefault();
      const form = e.currentTarget;
      const busca = val(form, "q");
      const status = val(form, "ativo");
      const query = new URLSearchParams();
      if (busca) query.set("q", busca);
      if (status !== "") query.set("ativo", status);
      const data = await api(`/produtos${query.toString() ? `?${query}` : ""}`);
      renderProdutosList(root, data.items || [], busca, status);
    };
    root.onclick = (e) => {
      if (e.target.closest("[data-vf-new-product]")) {
        openProdutoEditor(null);
        return;
      }
      const edit = e.target.closest("[data-vf-edit-product]");
      if (edit) openProdutoEditor(Number(edit.dataset.vfEditProduct));
    };
  }

  function renderProdutosRows(items) {
    if (!items.length) {
      return `<tr><td colspan="8" class="vf-empty">Nenhum produto cadastrado. <button type="button" class="vf-btn vf-btn--link" data-vf-new-product>Criar primeiro</button></td></tr>`;
    }
    return items.map((p) => `<tr>
      <td>${produtoThumb(p)}</td>
      <td><span class="vf-code">${esc(p.sku || "—")}</span></td>
      <td><strong>${esc(p.nome)}</strong></td>
      <td>${esc(p.categoria_nome || "—")}</td>
      <td>${money(p.preco)}</td>
      <td>${Number(p.estoque || 0)}</td>
      <td><span class="vf-badge ${Number(p.ativo) ? "vf-badge--active" : "vf-badge--inactive"}">${Number(p.ativo) ? "Ativo" : "Inativo"}</span>${Number(p.visivel_loja) ? "" : `<span class="vf-badge vf-badge--hidden">Oculto</span>`}</td>
      <td class="vf-table__right"><button class="vf-btn" type="button" data-vf-edit-product="${p.id}">Editar</button></td>
    </tr>`).join("");
  }

  async function openProdutoEditor(id) {
    const root = $("deliveryProdutosRoot"); if (!root) return;
    await hydrate();
    const produto = id ? await api(`/produtos/${id}`) : null;
    renderProdutoEditor(root, produto);
  }

  function categoriaOptions(selected) {
    return `<option value="">— Sem categoria —</option>${state.categorias.map((c) =>
      `<option value="${c.id}" ${Number(selected) === Number(c.id) ? "selected" : ""}>${esc(c.nome)}</option>`).join("")}`;
  }

  function renderProdutoEditor(root, produto) {
    const editando = !!produto;
    const adicionaisSelecionados = new Set((produto?.adicionais || []).map((a) => Number(a.id)));
    const ingredientes = produto?.ingredientes || [];
    const fotoUrl = deliveryImageUrl(produto?.foto_url);
    root.innerHTML = `<div class="vf-products vf-product-editor ${editando ? "vf-product-editor--edit" : ""}">
      <div class="vf-products__breadcrumb"><button type="button" data-vf-back-products>Produtos</button> / ${editando ? `Editar #${produto.id}` : "Novo"}</div>
      <div class="vf-card vf-product-form-card">
        ${editando ? `<div class="vf-editor-head"><div><h2>${esc(produto.nome)}</h2><div class="vf-help">Código interno: <code class="vf-code">${esc(produto.sku)}</code></div></div>
          <span class="vf-badge ${Number(produto.ativo) ? "vf-badge--active" : "vf-badge--inactive"}">${Number(produto.ativo) ? "Ativo" : "Inativo"}</span></div>` : `<h2>Dados do produto</h2>`}
        <form id="vfProdutoEditorForm">
          <div class="vf-form-grid">
            <label class="vf-field vf-col-12"><span>Foto do produto</span>
              <img id="vfProdutoFotoPreview" class="vf-photo-preview ${fotoUrl ? "is-visible" : ""}" src="${esc(fotoUrl)}" alt="Prévia da foto">
              <input type="file" name="foto" accept="image/jpeg,image/png,image/webp,image/gif">
              <small>Opcional. JPG, PNG, WebP ou GIF, até 3 MB. Aparece no cardápio online.</small>
            </label>
            <label class="vf-field vf-col-12"><span>Nome</span><input name="nome" value="${esc(produto?.nome || "")}" placeholder="Ex.: X-Burger" maxlength="180" required>
              ${editando ? "" : `<small>O <strong>código interno</strong> será gerado automaticamente ao salvar.</small>`}
            </label>
            <label class="vf-field vf-col-6"><span>Categoria</span><select name="categoria_id">${categoriaOptions(produto?.categoria_id)}</select><small><button class="vf-btn vf-btn--link" type="button" data-vf-go-categorias>Gerenciar categorias</button></small></label>
            <label class="vf-field vf-col-3"><span>Preço (R$)</span><input name="preco" type="number" min="0" step="0.01" value="${esc(produto?.preco ?? "")}" required></label>
            <label class="vf-field vf-col-3"><span>Estoque</span><input name="estoque" type="number" min="0" step="1" value="${esc(produto?.estoque ?? 0)}" required></label>
            <label class="vf-field vf-col-12"><span>Descrição</span><textarea name="descricao" rows="3">${esc(produto?.descricao || "")}</textarea></label>

            <fieldset class="vf-fieldset vf-col-12"><legend>Na vitrine da loja — retirar ingredientes</legend>
              <p class="vf-help">Como o cliente escolhe os ingredientes que deseja retirar do produto.</p>
              <div class="vf-choice-stack">
                <label class="vf-check"><input type="radio" name="ingredientes_retirar_ui" value="stepper" ${(produto?.ingredientes_retirar_ui || "stepper") === "stepper" ? "checked" : ""}> Botões − e +</label>
                <label class="vf-check"><input type="radio" name="ingredientes_retirar_ui" value="checkbox" ${produto?.ingredientes_retirar_ui === "checkbox" ? "checked" : ""}> Caixas de seleção</label>
              </div>
            </fieldset>

            <div class="vf-field vf-col-12">
              <span>Ingredientes do prato <em class="vf-help">(opcional)</em></span>
              <div id="vfIngredientesList" class="vf-ingredients-list">${ingredientes.map(renderIngredienteRow).join("")}</div>
              <div><button class="vf-btn" type="button" data-vf-add-ingredient>＋ Adicionar ingrediente</button></div>
              <small>Use os botões para incluir ou excluir linhas. Fotos por ingrediente são opcionais.</small>
            </div>

            <label class="vf-field vf-col-4"><span>Máx. ingredientes para retirar</span><input name="max_ingredientes_retirar" type="number" min="0" value="${esc(produto?.max_ingredientes_retirar ?? "")}" placeholder="Ex.: 2">
              <small>Quantos ingredientes o cliente pode pedir para retirar.</small>
            </label>
            <label class="vf-field vf-col-4"><span>Mín. acréscimos pagos</span><input name="acrescimo_escolhas_min" type="number" min="0" max="999" value="${esc(produto?.acrescimo_escolhas_min ?? "")}" placeholder="Sem mínimo"></label>
            <label class="vf-field vf-col-4"><span>Máx. acréscimos pagos</span><input name="acrescimo_escolhas_max" type="number" min="0" max="999" value="${esc(produto?.acrescimo_escolhas_max ?? "")}" placeholder="Sem máximo"></label>

            <div class="vf-paid-options vf-col-12 is-collapsed" id="vfPaidOptions">
              <div class="vf-paid-options__intro">
                <p class="vf-help">As opções pagas ficam ocultas para deixar o formulário mais limpo. Clique para ver ou alterar adicionais deste produto.</p>
                <button class="vf-btn" type="button" data-vf-show-additions>⊕ Ver adicionais disponíveis</button>
              </div>
              <div class="vf-paid-options__content">
                <div class="vf-paid-options__top"><strong>Adicionais / opções pagas</strong><button class="vf-btn vf-btn--link" type="button" data-vf-hide-additions>⌃ Recolher</button></div>
                <fieldset class="vf-fieldset"><legend>Na vitrine da loja</legend>
                  <p class="vf-help">Como o cliente escolhe os acréscimos pagos deste produto.</p>
                  <div class="vf-choice-stack">
                    <label class="vf-check"><input type="radio" name="acrescimos_loja_ui" value="stepper" ${(produto?.acrescimos_loja_ui || "stepper") === "stepper" ? "checked" : ""}> Botões − e + (quantidade por opção)</label>
                    <label class="vf-check"><input type="radio" name="acrescimos_loja_ui" value="checkbox" ${produto?.acrescimos_loja_ui === "checkbox" ? "checked" : ""}> Caixas — marcar só o que quer</label>
                  </div>
                </fieldset>
                <label class="vf-check"><input type="checkbox" name="permite_adicionais" ${Number(produto?.permite_adicionais) ? "checked" : ""}> Permitir acréscimos pagos na loja</label>
                <p class="vf-help">Marque só para este produto quais opções aparecem na vitrine.</p>
                <div class="vf-additions-list">${renderAdicionaisOptions(adicionaisSelecionados)}</div>
                <p class="vf-help"><strong>Recolher</strong> só esconde este bloco; as opções marcadas continuam sendo salvas.</p>
              </div>
            </div>

            <div class="vf-col-12 vf-choice-stack">
              <label class="vf-check"><input type="checkbox" name="visivel_loja" ${produto ? (Number(produto.visivel_loja) ? "checked" : "") : "checked"}> Visível na loja pública</label>
              <label class="vf-check"><input type="checkbox" name="ativo" ${produto ? (Number(produto.ativo) ? "checked" : "") : "checked"}> ${editando ? "Ativo" : "Ativo (disponível para venda)"}</label>
            </div>
            <div class="vf-form-actions vf-col-12">
              <button class="vf-btn vf-btn--primary" type="submit">${editando ? "Atualizar" : "Salvar"}</button>
              <button class="vf-btn" type="button" data-vf-back-products>${editando ? "Voltar" : "Cancelar"}</button>
            </div>
          </div>
        </form>
        ${editando ? `<div class="vf-delete-zone"><button class="vf-btn vf-btn--danger" type="button" data-vf-delete-product="${produto.id}">Excluir produto</button></div>` : ""}
      </div>
    </div>`;
    bindProdutoEditor(root, produto);
  }

  function renderIngredienteRow(ingrediente) {
    const fotoUrl = deliveryImageUrl(ingrediente?.foto_url);
    return `<div class="vf-ingredient-row" data-ing-id="${esc(ingrediente?.id || "")}" data-ing-photo="${esc(ingrediente?.foto_path || "")}">
      <span class="vf-ingredient-thumb">${fotoUrl ? `<img src="${esc(fotoUrl)}" alt="">` : "＋"}</span>
      <input type="text" class="vf-ing-name" value="${esc(ingrediente?.nome || "")}" maxlength="160" placeholder="Nome do ingrediente">
      <input type="file" class="vf-ingredient-file vf-ing-photo" accept="image/jpeg,image/png,image/webp,image/gif">
      <button class="vf-btn vf-btn--danger" type="button" data-vf-remove-ingredient title="Remover ingrediente">🗑</button>
    </div>`;
  }

  function renderAdicionaisOptions(selected) {
    const disponiveis = state.adicionais.filter((a) => a.tipo === "acrescentar" && Number(a.ativo));
    if (!disponiveis.length) return `<span class="vf-help">Nenhum adicional de acréscimo cadastrado.</span>`;
    return disponiveis.map((a) => {
      const foto = deliveryImageUrl(a.foto_url || a.foto_path);
      return `<label class="vf-addition-option"><input type="checkbox" name="adicional_ids" value="${a.id}" ${selected.has(Number(a.id)) ? "checked" : ""}>
        ${foto ? `<img src="${esc(foto)}" alt="">` : ""}<span>${esc(a.nome)} <span class="vf-help">(+ ${money(a.preco)})</span></span></label>`;
    }).join("");
  }

  function bindProdutoEditor(root, produto) {
    root.querySelectorAll("[data-vf-back-products]").forEach((b) => { b.onclick = () => loadProdutos(); });
    root.querySelector("[data-vf-go-categorias]").onclick = () => window.navigateTo?.("deliveryCategorias");
    root.querySelector("[data-vf-add-ingredient]").onclick = () => {
      $("vfIngredientesList").insertAdjacentHTML("beforeend", renderIngredienteRow(null));
    };
    root.querySelector("[data-vf-show-additions]").onclick = () => $("vfPaidOptions").classList.remove("is-collapsed");
    root.querySelector("[data-vf-hide-additions]").onclick = () => $("vfPaidOptions").classList.add("is-collapsed");
    root.onclick = async (e) => {
      const remove = e.target.closest("[data-vf-remove-ingredient]");
      if (remove) remove.closest(".vf-ingredient-row")?.remove();
      const del = e.target.closest("[data-vf-delete-product]");
      if (del && confirm("Excluir este produto?")) {
        await api(`/produtos/${del.dataset.vfDeleteProduct}`, { method: "DELETE" });
        toast("Produto excluído.", "success");
        await loadProdutos();
      }
    };
    root.onchange = async (e) => {
      if (e.target.matches('input[name="foto"]') && e.target.files?.[0]) {
        const preview = $("vfProdutoFotoPreview");
        preview.src = URL.createObjectURL(e.target.files[0]);
        preview.classList.add("is-visible");
      }
      if (e.target.matches(".vf-ing-photo") && e.target.files?.[0]) {
        const thumb = e.target.closest(".vf-ingredient-row")?.querySelector(".vf-ingredient-thumb");
        if (thumb) thumb.innerHTML = `<img src="${esc(URL.createObjectURL(e.target.files[0]))}" alt="">`;
      }
    };
    $("vfProdutoEditorForm").onsubmit = async (e) => {
      e.preventDefault();
      const form = e.currentTarget;
      const submit = form.querySelector('button[type="submit"]');
      submit.disabled = true;
      try {
        const ingredientes = await Promise.all([...form.querySelectorAll(".vf-ingredient-row")].map(async (row) => {
          const file = row.querySelector(".vf-ing-photo")?.files?.[0];
          return {
            id: row.dataset.ingId ? Number(row.dataset.ingId) : null,
            nome: row.querySelector(".vf-ing-name")?.value.trim() || "",
            foto_path: file ? null : (row.dataset.ingPhoto || null),
            foto_base64: file ? await fileToDataUrl(file, 2 * 1024 * 1024, "Foto do ingrediente") : null,
          };
        }));
        const ingredientesValidos = ingredientes.filter((x) => x.nome);
        if (ingredientesValidos.length && val(form, "max_ingredientes_retirar") === "") {
          throw new Error("Informe o máximo de ingredientes que o cliente pode retirar.");
        }
        const mainFile = form.elements.foto?.files?.[0];
        const payload = {
          nome: val(form, "nome").trim(),
          categoria_id: val(form, "categoria_id") ? Number(val(form, "categoria_id")) : null,
          preco: Number(val(form, "preco")),
          estoque: Number(val(form, "estoque")),
          descricao: val(form, "descricao") || null,
          foto_base64: mainFile ? await fileToDataUrl(mainFile, 3 * 1024 * 1024, "Foto do produto") : null,
          foto_path: produto?.foto_path || null,
          ingredientes_retirar_ui: form.elements.ingredientes_retirar_ui.value,
          max_ingredientes_retirar: val(form, "max_ingredientes_retirar") === "" ? null : Number(val(form, "max_ingredientes_retirar")),
          acrescimo_escolhas_min: val(form, "acrescimo_escolhas_min") === "" ? null : Number(val(form, "acrescimo_escolhas_min")),
          acrescimo_escolhas_max: val(form, "acrescimo_escolhas_max") === "" ? null : Number(val(form, "acrescimo_escolhas_max")),
          acrescimos_loja_ui: form.elements.acrescimos_loja_ui.value,
          permite_adicionais: bool(form, "permite_adicionais"),
          adicional_ids: [...form.querySelectorAll('input[name="adicional_ids"]:checked')].map((x) => Number(x.value)),
          ingredientes: ingredientesValidos,
          visivel_loja: bool(form, "visivel_loja"),
          ativo: bool(form, "ativo"),
        };
        await api(produto ? `/produtos/${produto.id}` : "/produtos", { method: produto ? "PUT" : "POST", body: JSON.stringify(payload) });
        toast(produto ? "Produto atualizado." : "Produto cadastrado.", "success");
        await loadProdutos();
      } catch (error) {
        toast(error?.message || "Não foi possível salvar o produto.", "error");
      } finally {
        submit.disabled = false;
      }
    };
  }

  function fileToDataUrl(file, maxBytes, label) {
    if (!file) return Promise.resolve(null);
    if (file.size > maxBytes) return Promise.reject(new Error(`${label} excede o tamanho permitido.`));
    if (!["image/jpeg", "image/png", "image/webp", "image/gif"].includes(file.type)) {
      return Promise.reject(new Error(`${label} deve ser JPG, PNG, WebP ou GIF.`));
    }
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = () => resolve(String(reader.result));
      reader.onerror = () => reject(new Error(`Não foi possível ler ${label.toLowerCase()}.`));
      reader.readAsDataURL(file);
    });
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
      <label>Instagram (URL)<input name="instagram_url" value="${esc(c.instagram_url || "")}" placeholder="https://instagram.com/sualoja"></label>
      <label>Facebook (URL)<input name="facebook_url" value="${esc(c.facebook_url || "")}" placeholder="https://facebook.com/sualoja"></label>
      <label class="checkbox-label"><input name="ativo" type="checkbox" ${c.ativo ? "checked" : ""}> Vitrine ativa</label><label class="checkbox-label"><input name="aberta" type="checkbox" ${c.aberta ? "checked" : ""}> Loja aberta</label></div>
      <label>Descrição<textarea name="descricao" rows="3">${esc(c.descricao || "")}</textarea></label><label>Endereço exibido<textarea name="endereco_texto" rows="2">${esc(c.endereco_texto || "")}</textarea></label>
      <button class="btn primary">Salvar vitrine</button></form>
      <article class="orc-card" style="border-top:5px solid ${esc(c.cor_primaria || "#e85d24")}"><span class="orc-badge orc-badge--${c.aberta ? "success" : "neutral"}">${c.aberta ? "Loja aberta" : "Loja fechada"}</span>
      <h2>${esc(c.nome_loja || "Sua loja")}</h2><p>${esc(c.descricao || "A descrição da vitrine aparecerá aqui.")}</p><div class="orc-note"><strong>Endereço público planejado</strong><code>${esc(c.preview_path)}</code></div>
      <button class="btn secondary" type="button" data-go="deliveryCatalogo">Consultar catálogo publicado</button></article></div>`);
    $("dlvVitrineForm").onsubmit = async (e) => {
      e.preventDefault(); const f = e.currentTarget;
      await api("/vitrine", { method: "PUT", body: JSON.stringify({ ...formJson(f, ["nome_loja", "slug", "cor_primaria", "whatsapp", "instagram_url", "facebook_url", "descricao", "endereco_texto"]), ativo: bool(f, "ativo"), aberta: bool(f, "aberta") }) });
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
  window.deliveryMediaUrl = deliveryImageUrl;
})();
