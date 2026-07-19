/**
 * Fidelidade — administração do programa, cartões, extrato e recompensas.
 */
(function () {
  "use strict";

  const state = { cartoes: [], recompensas: [], cartaoId: null, unidades: [], unidadeId: "", catalogoConsultaSuportado: true, catalogoPickerDocClose: null };
  const $ = (id) => document.getElementById(id);
  const esc = (v) => String(v == null ? "" : v).replace(/&/g, "&amp;").replace(/</g, "&lt;")
    .replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#39;");
  const money = (v) => Number(v || 0).toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
  const toast = (m, t) => window.showToast?.(m, t || "info");
  const withUnidade = (path) => {
    if (!state.unidadeId) return path;
    const sep = path.includes("?") ? "&" : "?";
    return `${path}${sep}unidade_id=${encodeURIComponent(state.unidadeId)}`;
  };
  const api = (path, options) => {
    if (typeof window.fetchJSON !== "function") throw new Error("Conexão com a API indisponível.");
    const opts = options ? { ...options } : {};
    if (opts.body && typeof opts.body === "string" && state.unidadeId) {
      try {
        const parsed = JSON.parse(opts.body);
        if (parsed && typeof parsed === "object" && !Array.isArray(parsed) && parsed.unidade_id == null) {
          parsed.unidade_id = Number(state.unidadeId);
          opts.body = JSON.stringify(parsed);
        }
      } catch (_) { /* ignore */ }
    }
    return window.fetchJSON(`/fidelidade${withUnidade(path)}`, opts);
  };
  const uid = () => `web-${Date.now()}-${Math.random().toString(16).slice(2)}`;
  const shell = (content) => `<div class="orc-shell">${content}</div>`;
  const header = (title, subtitle, icon, action) => `<header class="orc-head"><div>
    <div class="orc-breadcrumb">Fidelidade / ${esc(title)}</div>
    <div class="orc-head__title"><span class="orc-head__icon">${icon}</span><div><h2>${esc(title)}</h2><p>${esc(subtitle)}</p></div></div>
    </div>${action || ""}</header>`;
  const empty = (text) => `<div class="orc-empty"><strong>Nenhum registro encontrado</strong><p>${esc(text)}</p></div>`;
  const badge = (status) => `<span class="orc-badge orc-badge--${status === "ativo" || status === "entregue" ? "success" : status === "pendente" ? "warning" : "neutral"}">${esc(status)}</span>`;
  const value = (form, name) => form.elements[name]?.value ?? "";
  const checked = (form, name) => !!form.elements[name]?.checked;

  function formatCpf(raw) {
    const d = String(raw || "").replace(/\D/g, "");
    if (d.length !== 11) return raw ? esc(raw) : "—";
    return esc(`${d.slice(0, 3)}.${d.slice(3, 6)}.${d.slice(6, 9)}-${d.slice(9)}`);
  }

  function formatTel(raw) {
    const d = String(raw || "").replace(/\D/g, "");
    if (d.length === 11) return esc(`(${d.slice(0, 2)}) ${d.slice(2, 7)}-${d.slice(7)}`);
    if (d.length === 10) return esc(`(${d.slice(0, 2)}) ${d.slice(2, 6)}-${d.slice(6)}`);
    return raw ? esc(raw) : "—";
  }

  function clienteCell(c) {
    const nome = esc(c.nome || "Cliente sem nome");
    const cpf = formatCpf(c.cpf_normalizado);
    const tel = formatTel(c.telefone_normalizado);
    const email = c.email ? esc(c.email) : "—";
    return `<td class="fid-cliente-cell"><div class="fid-cliente-info">
      <strong class="fid-cliente-info__nome">${nome}</strong>
      <dl class="fid-cliente-info__grid">
        <div><dt>CPF</dt><dd>${cpf}</dd></div>
        <div><dt>Telefone</dt><dd>${tel}</dd></div>
        <div><dt>E-mail</dt><dd class="fid-cliente-info__email">${email}</dd></div>
      </dl>
    </div></td>`;
  }

  async function ensureUnidades() {
    if (state.unidades.length) return state.unidades;
    try {
      const list = typeof window.fetchJSON === "function" ? await window.fetchJSON("/unidades") : [];
      state.unidades = Array.isArray(list) ? list : [];
    } catch (_) {
      state.unidades = [];
    }
    if (!state.unidadeId && state.unidades.length) {
      let userU = "";
      if (typeof window.getUser === "function") {
        userU = String(window.getUser()?.unidade_id || "");
      } else if (window.currentUser?.unidade_id != null) {
        userU = String(window.currentUser.unidade_id);
      }
      const match = userU && state.unidades.find((u) => String(u.id) === userU);
      state.unidadeId = match ? String(match.id) : String(state.unidades[0].id);
    }
    return state.unidades;
  }

  function unidadeBar(reloadFnName) {
    const opts = state.unidades.map((u) =>
      `<option value="${esc(u.id)}" ${String(u.id) === String(state.unidadeId) ? "selected" : ""}>${esc(u.nome || ("Unidade " + u.id))}</option>`
    ).join("");
    return `<div class="orc-filterbar fid-unidade-bar">
      <label>Unidade
        <select id="fidUnidadeSelect">${opts || '<option value="">Nenhuma unidade</option>'}</select>
      </label>
      <span class="subtle-text">Cada unidade tem o próprio programa e cartões.</span>
    </div>`;
  }

  function bindUnidadeSelect(reload) {
    const sel = $("fidUnidadeSelect");
    if (!sel) return;
    sel.onchange = async () => {
      state.unidadeId = sel.value || "";
      await reload();
    };
  }

  async function refreshCartoes() {
    state.cartoes = (await api("/cartoes")).items || [];
    if (!state.cartaoId && state.cartoes.length) state.cartaoId = Number(state.cartoes[0].id);
  }

  async function loadPrograma() {
    const root = $("fidelidadeProgramaRoot");
    if (!root) return;
    await ensureUnidades();
    if (!state.unidadeId) {
      root.innerHTML = shell(header("Programa", "Selecione uma unidade para configurar o programa.", "🎁")
        + `<div class="orc-card">${empty("Nenhuma unidade disponível.")}</div>`);
      return;
    }
    let data;
    try {
      data = await api("/programa");
    } catch (e) {
      root.innerHTML = shell(header("Programa", "Defina como os clientes acumulam e trocam benefícios.", "🎁")
        + unidadeBar()
        + `<div class="orc-card"><p class="subtle-text">${esc(e.message || "Selecione a unidade e tente de novo.")}</p></div>`);
      bindUnidadeSelect(loadPrograma);
      return;
    }
    const p = data.programa || {};
    state.catalogoConsultaSuportado = data.catalogo_consulta_suportado !== false;
    let tipoRec = p.tipo_recompensa_padrao || "catalogo_consulta";
    if (tipoRec === "produto") tipoRec = "catalogo_consulta";
    const catalogoQtd = Number(p.catalogo_qtd_escolhas || 1);
    const catalogoSelecionados = Array.isArray(p.catalogo_produtos_ids) ? p.catalogo_produtos_ids.map(Number) : [];
    root.innerHTML = shell(header("Programa", "Defina como os clientes acumulam e trocam benefícios.", "🎁")
      + unidadeBar()
      + `<form id="fidProgramaForm" class="orc-card">
        <div class="orc-section-title"><div><h3>Regras do programa</h3><p>Configuração da unidade selecionada acima.</p></div></div>
        <div class="orc-form-grid">
          <label>Nome exibido<input name="nome_exibicao" value="${esc(p.nome_exibicao || "Cartão fidelidade")}" required></label>
          <label>Modo<select name="modo"><option value="selos" ${p.modo !== "pontos" ? "selected" : ""}>Selos</option><option value="pontos" ${p.modo === "pontos" ? "selected" : ""}>Pontos</option></select></label>
          <label>Meta de selos<input name="pedidos_meta" type="number" min="1" value="${esc(p.pedidos_meta || 10)}" required></label>
          <label>Pontos por selo<input name="pontos_por_selo" type="number" min="0" value="${esc(p.pontos_por_selo ?? 1)}"></label>
          <label>Recompensa padrão<select name="tipo_recompensa_padrao" id="fidTipoRecompensa">
            <option value="catalogo_consulta" ${tipoRec === "catalogo_consulta" ? "selected" : ""}>Catálogo (consulta)</option>
            <option value="brinde" ${tipoRec === "brinde" ? "selected" : ""}>Brinde (descrever)</option>
            <option value="desconto_valor" ${tipoRec === "desconto_valor" ? "selected" : ""}>Desconto em valor (R$)</option>
            <option value="desconto_percentual" ${tipoRec === "desconto_percentual" ? "selected" : ""}>Desconto percentual (%)</option>
            <option value="catalogo" ${tipoRec === "catalogo" ? "selected" : ""}>Catálogo de recompensas (lista manual)</option>
          </select></label>
          <label class="fid-rec-field fid-rec-field--catalogo-qtd" data-fid-rec-show="catalogo_consulta">Itens que o cliente escolhe no resgate<input name="catalogo_qtd_escolhas" id="fidCatalogoQtd" type="number" min="1" max="20" value="${esc(catalogoQtd)}" title="Quantos produtos o cliente leva ao resgatar (ex.: 1 entre 3 opções)"></label>
          <label class="fid-rec-field fid-rec-field--valor" data-fid-rec-show="desconto_valor">Valor do desconto (R$)<input name="valor_desconto" type="number" min="0" step="0.01" value="${esc(p.valor_desconto || "")}"></label>
          <label class="fid-rec-field fid-rec-field--pct" data-fid-rec-show="desconto_percentual">Desconto percentual (%)<input name="desconto_percentual" type="number" min="0" max="100" step="0.01" value="${esc(p.desconto_percentual || "")}"></label>
          <label class="fid-rec-field fid-rec-field--pct" data-fid-rec-show="desconto_percentual">Base do percentual<select name="base_desconto_percentual">
            <option value="gasto_acumulado_meta" ${(p.base_desconto_percentual || "gasto_acumulado_meta") === "gasto_acumulado_meta" ? "selected" : ""}>Total gasto nas reservas que geraram os selos do ciclo</option>
          </select></label>
          <label>Validade dos créditos (dias)<input name="dias_expiracao_credito" type="number" min="1" value="${esc(p.dias_expiracao_credito || "")}" placeholder="Sem expiração"></label>
          <label class="checkbox-label"><input name="ativo" type="checkbox" ${p.ativo ? "checked" : ""}> Programa ativo nesta unidade</label>
          <label class="checkbox-label"><input name="permite_ajuste_manual" type="checkbox" ${p.permite_ajuste_manual !== false && Number(p.permite_ajuste_manual) !== 0 ? "checked" : ""}> Permitir ajustes manuais</label>
        </div>
        <label class="fid-rec-field fid-rec-field--texto" data-fid-rec-show="brinde">Descrição da recompensa (brinde)<textarea name="texto_recompensa" rows="3" placeholder="Ex.: 1 porção de sobremesa da casa">${esc(p.texto_recompensa || "")}</textarea></label>
        <div class="orc-card fid-rec-field fid-catalogo-consulta-wrap" data-fid-rec-show="catalogo_consulta">
          <div class="orc-section-title"><div><h3>Opções no card da recompensa (Delivery)</h3><p>Selecione os produtos no campo abaixo — todos aparecem no card da recompensa. O campo acima define quantos o cliente escolhe ao completar a meta (ex.: 3 opções visíveis, escolhe 1).</p></div></div>
          ${state.catalogoConsultaSuportado ? "" : `<p class="vf-show-warn">Atualização do banco pendente: peça ao administrador para rodar <code>php artisan migrate</code> antes de salvar os produtos.</p>`}
          <p class="subtle-text" id="fidCatalogoConsultaMeta"></p>
          <p class="subtle-text" id="fidCatalogoConsultaHint"></p>
          <div id="fidCatalogoProdutosList" class="fid-catalogo-produtos-list"></div>
        </div>
        <p class="subtle-text fid-rec-hint fid-rec-field" data-fid-rec-show="desconto_percentual">O percentual incide sobre a soma do valor pago em cada conta das visitas que geraram os selos desde o último resgate (até a meta).</p>
        <p class="subtle-text fid-rec-hint fid-rec-field" data-fid-rec-show="desconto_valor">Desconto fixo em reais aplicado na conta ao resgatar os selos.</p>
        <div class="orc-actions"><button class="btn primary" type="submit">Salvar programa</button></div>
      </form>`);
    bindUnidadeSelect(loadPrograma);
    syncProgramaRecompensaFields($("fidProgramaForm"));
    $("fidTipoRecompensa")?.addEventListener("change", async () => {
      syncProgramaRecompensaFields($("fidProgramaForm"));
      if (value($("fidProgramaForm"), "tipo_recompensa_padrao") === "catalogo_consulta") {
        const selected = selectedCatalogoProdutoIds($("fidProgramaForm"));
        await renderCatalogoConsultaProdutos(selected.length ? selected : catalogoSelecionados, catalogoQtdMax($("fidProgramaForm")));
      }
    });
    $("fidCatalogoQtd")?.addEventListener("change", () => enforceCatalogoProdutosLimit($("fidProgramaForm")));
    $("fidCatalogoQtd")?.addEventListener("input", () => enforceCatalogoProdutosLimit($("fidProgramaForm")));
    await renderCatalogoConsultaProdutos(catalogoSelecionados, catalogoQtd);
    $("fidProgramaForm").addEventListener("submit", async (event) => {
      event.preventDefault();
      const f = event.currentTarget;
      const tipo = value(f, "tipo_recompensa_padrao");
      const catalogoIds = tipo === "catalogo_consulta" ? selectedCatalogoProdutoIds(f) : [];
      if (tipo === "catalogo_consulta" && !catalogoIds.length) {
        toast("Selecione pelo menos 1 produto do cardápio para a recompensa.", "warning");
        return;
      }
      await api("/programa", { method: "PUT", body: JSON.stringify({
        nome_exibicao: value(f, "nome_exibicao"), modo: value(f, "modo"),
        pedidos_meta: Number(value(f, "pedidos_meta")), pontos_por_selo: Number(value(f, "pontos_por_selo")),
        tipo_recompensa_padrao: tipo,
        catalogo_qtd_escolhas: tipo === "catalogo_consulta" ? Number(value(f, "catalogo_qtd_escolhas") || 1) : null,
        catalogo_produtos_ids: tipo === "catalogo_consulta" ? catalogoIds : [],
        valor_desconto: tipo === "desconto_valor" && value(f, "valor_desconto") !== "" ? Number(value(f, "valor_desconto")) : null,
        desconto_percentual: tipo === "desconto_percentual" && value(f, "desconto_percentual") !== "" ? Number(value(f, "desconto_percentual")) : null,
        base_desconto_percentual: tipo === "desconto_percentual" ? (value(f, "base_desconto_percentual") || "gasto_acumulado_meta") : null,
        dias_expiracao_credito: value(f, "dias_expiracao_credito") === "" ? null : Number(value(f, "dias_expiracao_credito")),
        texto_recompensa: tipo === "brinde" ? (value(f, "texto_recompensa") || null) : null,
        ativo: checked(f, "ativo"), permite_ajuste_manual: checked(f, "permite_ajuste_manual"),
        unidade_id: state.unidadeId ? Number(state.unidadeId) : undefined,
      }) });
      toast("Programa de fidelidade salvo.", "success");
      await loadPrograma();
    });
  }

  function selectedCatalogoProdutoIds(form) {
    if (!form) return [];
    return Array.from(form.querySelectorAll('input[name="catalogo_produtos_ids[]"]:checked'))
      .map((el) => Number(el.value))
      .filter((id) => id > 0);
  }

  function closeCatalogoPicker() {
    $("fidCatalogoPickerPanel")?.classList.add("hidden");
    const toggle = $("fidCatalogoPickerToggle");
    toggle?.setAttribute("aria-expanded", "false");
    toggle?.classList.remove("is-open");
  }

  function syncCatalogoPickerLabel(form) {
    const text = $("fidCatalogoPickerText");
    if (!text || !form) return;
    const ids = selectedCatalogoProdutoIds(form);
    if (!ids.length) {
      text.textContent = "Selecionar produtos…";
      return;
    }
    if (ids.length === 1) {
      const row = form.querySelector(`input[name="catalogo_produtos_ids[]"][value="${ids[0]}"]`);
      const nome = row?.closest("label")?.querySelector(".fid-catalogo-picker__nome")?.textContent?.trim();
      text.textContent = nome || "1 produto selecionado";
      return;
    }
    text.textContent = `${ids.length} produtos selecionados`;
  }

  function bindCatalogoPicker(form) {
    const picker = $("fidCatalogoPicker");
    const toggle = $("fidCatalogoPickerToggle");
    const panel = $("fidCatalogoPickerPanel");
    const done = $("fidCatalogoPickerDone");
    if (!picker || !toggle || !panel || !form) return;

    toggle.onclick = (event) => {
      event.preventDefault();
      event.stopPropagation();
      if (panel.classList.contains("hidden")) {
        panel.classList.remove("hidden");
        toggle.setAttribute("aria-expanded", "true");
        toggle.classList.add("is-open");
      } else {
        closeCatalogoPicker();
      }
    };

    done.onclick = (event) => {
      event.preventDefault();
      closeCatalogoPicker();
      enforceCatalogoProdutosLimit(form);
    };

    panel.querySelectorAll('input[name="catalogo_produtos_ids[]"]').forEach((el) => {
      el.addEventListener("change", () => {
        syncCatalogoPickerLabel(form);
        syncCatalogoConsultaHint(form);
      });
    });

    if (state.catalogoPickerDocClose) {
      document.removeEventListener("click", state.catalogoPickerDocClose);
    }
    state.catalogoPickerDocClose = (event) => {
      if (!picker.contains(event.target)) closeCatalogoPicker();
    };
    document.addEventListener("click", state.catalogoPickerDocClose);

    syncCatalogoPickerLabel(form);
  }

  function syncProgramaRecompensaFields(form) {
    if (!form) return;
    const tipo = value(form, "tipo_recompensa_padrao") || "catalogo_consulta";
    form.querySelectorAll("[data-fid-rec-show]").forEach((el) => {
      const tipos = String(el.dataset.fidRecShow || "").split(/\s+/).filter(Boolean);
      el.classList.toggle("hidden", !tipos.includes(tipo));
    });
    if (tipo === "catalogo_consulta") {
      syncCatalogoConsultaHint(form);
    }
  }

  function catalogoQtdMax(form) {
    const raw = Number(value(form, "catalogo_qtd_escolhas") || 1);
    return Math.max(1, Math.min(20, Number.isFinite(raw) ? raw : 1));
  }

  function syncCatalogoConsultaHint(form) {
    if (!form) return;
    const qtd = catalogoQtdMax(form);
    const total = selectedCatalogoProdutoIds(form).length;
    const hint = $("fidCatalogoConsultaHint");
    if (hint) {
      hint.textContent = total
        ? `${total} opção(ões) no card da recompensa · o cliente escolhe ${qtd} ao completar a meta.`
        : `Selecione as opções do cardápio · o cliente escolhe ${qtd} ao completar a meta.`;
    }
  }

  function enforceCatalogoProdutosLimit(form) {
    syncCatalogoPickerLabel(form);
    syncCatalogoConsultaHint(form);
  }

  function promptCatalogoEscolhasResgate(produtos, qtdLimite) {
    return new Promise((resolve) => {
      const limite = Math.max(1, Math.min(20, Number(qtdLimite) || 1));
      const overlay = document.createElement("div");
      overlay.className = "fid-modal-overlay";
      overlay.style.cssText = "position:fixed;inset:0;z-index:9999;background:rgba(15,23,42,.45);display:flex;align-items:center;justify-content:center;padding:16px;";
      overlay.innerHTML = `<div class="orc-card fid-modal" role="dialog" aria-modal="true" style="width:min(480px,100%);max-height:90vh;overflow:auto;">
        <div class="orc-section-title"><h3>Escolher produto(s) do resgate</h3>
        <p class="subtle-text">Selecione exatamente ${limite} item(ns) entre as opções configuradas.</p></div>
        <div class="fid-catalogo-produtos-grid fid-resgate-pick-grid">${produtos.map((item) => {
          const id = Number(item.id);
          const input = limite === 1
            ? `<input type="radio" name="fid_resgate_pick" value="${id}">`
            : `<input type="number" name="fid_resgate_qty_${id}" min="0" max="${limite}" value="0" inputmode="numeric">`;
          return `<label class="fid-catalogo-produto-item fid-resgate-pick-item">${input}
            <span><strong>${esc(item.nome)}</strong>${item.preco != null ? `<small>${money(item.preco)}</small>` : ""}</span></label>`;
        }).join("")}</div>
        <div class="orc-actions">
          <button type="button" class="btn primary" id="fidResgatePickOk">Confirmar</button>
          <button type="button" class="btn neutral" id="fidResgatePickCancel">Cancelar</button>
        </div>
      </div>`;
      const close = (value) => { overlay.remove(); resolve(value); };
      overlay.addEventListener("click", (e) => { if (e.target === overlay) close(null); });
      overlay.querySelector("#fidResgatePickCancel").onclick = () => close(null);
      overlay.querySelector("#fidResgatePickOk").onclick = () => {
        if (limite === 1) {
          const picked = overlay.querySelector('input[name="fid_resgate_pick"]:checked');
          if (!picked) { toast("Escolha 1 produto para o resgate.", "warning"); return; }
          close([Number(picked.value)]);
          return;
        }
        const escolhas = [];
        let total = 0;
        produtos.forEach((item) => {
          const id = Number(item.id);
          const qty = Math.max(0, Number(overlay.querySelector(`input[name="fid_resgate_qty_${id}"]`)?.value || 0));
          if (qty > 0) { escolhas.push({ produto_id: id, qtd: qty }); total += qty; }
        });
        if (total !== limite) {
          toast(`Escolha exatamente ${limite} item(ns) (total selecionado: ${total}).`, "warning");
          return;
        }
        close(escolhas);
      };
      document.body.appendChild(overlay);
      overlay.querySelector(".fid-modal")?.querySelector("input")?.focus();
    });
  }

  async function resgatarCartao(contaId) {
    const body = { idempotency_key: uid() };
    if (state.unidadeId) {
      try {
        const data = await api("/programa");
        const p = data?.programa || data;
        let tipo = String(p?.tipo_recompensa_padrao || "");
        if (tipo === "produto") tipo = "catalogo_consulta";
        const produtos = Array.isArray(p?.catalogo_produtos) ? p.catalogo_produtos : [];
        const qtd = Math.max(1, Number(p?.catalogo_qtd_escolhas || 1));
        if (tipo === "catalogo_consulta" && produtos.length) {
          const escolhas = await promptCatalogoEscolhasResgate(produtos, qtd);
          if (!escolhas) return false;
          body.catalogo_escolhas = escolhas;
        }
      } catch (_) { /* resgate padrão sem catálogo */ }
    }
    await api(`/cartoes/${contaId}/resgatar`, { method: "POST", body: JSON.stringify(body) });
    return true;
  }

  async function fetchDeliveryCatalogoFallback() {
    if (typeof window.fetchJSON !== "function" || !state.unidadeId) return null;
    try {
      const data = await window.fetchJSON(`/delivery/catalogo?unidade_id=${encodeURIComponent(state.unidadeId)}`);
      const items = (Array.isArray(data.produtos) ? data.produtos : []).map((item) => ({
        id: Number(item.id),
        nome: String(item.nome || ""),
        preco: Number(item.preco || 0),
        visivel_loja: Boolean(item.visivel_loja),
      }));
      if (!items.length) return null;
      return { items, delivery_disponivel: true, fallback: true };
    } catch (_) {
      return null;
    }
  }

  async function fetchCatalogoConsultaProdutos() {
    let data = await api("/catalogo-consulta/produtos");
    if ((!data.items || !data.items.length) && data.delivery_disponivel !== false) {
      const fallback = await fetchDeliveryCatalogoFallback();
      if (fallback?.items?.length) {
        data = { ...data, ...fallback };
      }
    }
    return data;
  }

  async function renderCatalogoConsultaProdutos(selectedIds, qtdMax) {
    const list = $("fidCatalogoProdutosList");
    const hint = $("fidCatalogoConsultaHint");
    const meta = $("fidCatalogoConsultaMeta");
    if (!list) return;
    list.innerHTML = `<p class="subtle-text">Carregando cardápio…</p>`;
    if (meta) meta.textContent = "";
    try {
      const data = await fetchCatalogoConsultaProdutos();
      state.catalogoConsultaSuportado = data.catalogo_consulta_suportado !== false;
      const items = data.items || [];
      if (meta) {
        const partes = [];
        if (data.loja_nome) partes.push(`Loja Delivery: ${data.loja_nome}`);
        if (data.unidade_delivery_id && data.unidade_fidelidade_id && Number(data.unidade_delivery_id) !== Number(data.unidade_fidelidade_id)) {
          partes.push(`cardápio da unidade ${data.unidade_delivery_id} vinculada à fidelidade ${data.unidade_fidelidade_id}`);
        }
        if (data.fallback) partes.push("lista carregada pelo catálogo Delivery");
        meta.textContent = partes.join(" · ");
      }
      if (!data.delivery_disponivel) {
        list.innerHTML = `<p class="subtle-text">Módulo Delivery não encontrado. Cadastre produtos em Delivery → Catálogo (consulta).</p>`;
        if (hint) hint.textContent = "";
        return;
      }
      if (!items.length) {
        list.innerHTML = `<p class="subtle-text">Nenhum produto ativo no Delivery para esta unidade. Cadastre em <strong>Delivery → Catálogo (consulta)</strong> e confira se a loja está vinculada à mesma unidade de fidelidade (Configurações → unidade fidelidade).</p>`;
        if (hint) hint.textContent = "";
        return;
      }
      const selected = new Set((selectedIds || []).map(Number));
      list.innerHTML = `<div class="fid-catalogo-picker" id="fidCatalogoPicker">
        <span class="fid-catalogo-picker__label">Produtos do cardápio</span>
        <button type="button" class="fid-catalogo-picker__toggle" id="fidCatalogoPickerToggle" aria-expanded="false" aria-controls="fidCatalogoPickerPanel">
          <span class="fid-catalogo-picker__text" id="fidCatalogoPickerText">Selecionar produtos…</span>
          <span class="fid-catalogo-picker__arrow" aria-hidden="true">▾</span>
        </button>
        <div class="fid-catalogo-picker__panel hidden" id="fidCatalogoPickerPanel">
          <div class="fid-catalogo-picker__list">
            ${items.map((item) => {
              const id = Number(item.id);
              const checkedAttr = selected.has(id) ? " checked" : "";
              const visivel = item.visivel_loja ? "" : " · só consulta";
              return `<label class="fid-catalogo-picker__item">
                <input type="checkbox" name="catalogo_produtos_ids[]" value="${id}"${checkedAttr}>
                <span class="fid-catalogo-picker__item-body">
                  <span class="fid-catalogo-picker__nome">${esc(item.nome)}</span>
                  <small>${money(item.preco)}${visivel}</small>
                </span>
              </label>`;
            }).join("")}
          </div>
          <button type="button" class="btn secondary fid-catalogo-picker__done" id="fidCatalogoPickerDone">Pronto</button>
        </div>
      </div>`;
      bindCatalogoPicker($("fidProgramaForm"));
      enforceCatalogoProdutosLimit($("fidProgramaForm"));
    } catch (error) {
      list.innerHTML = `<p class="subtle-text">${esc(error.message || "Não foi possível carregar o cardápio.")}</p>`;
      if (hint) hint.textContent = "";
    }
  }

  async function loadCartoes() {
    const root = $("fidelidadeCartoesRoot");
    if (!root) return;
    await ensureUnidades();
    await refreshCartoes();
    root.innerHTML = shell(header("Cartões", "Cadastre clientes, credite selos e controle saldos.", "▣",
      `<button class="btn primary" type="button" id="fidNovoCartao">+ Novo cartão</button>`)
      + unidadeBar()
      + `<form id="fidBuscaForm" class="orc-filterbar"><label>Buscar<input name="q" placeholder="Nome, telefone, CPF ou código"></label>
        <label>Status<select name="status"><option value="">Todos</option><option value="ativo">Ativos</option><option value="inativo">Inativos</option><option value="bloqueado">Bloqueados</option></select></label>
        <button class="btn secondary" type="submit">Filtrar</button></form>
      <div id="fidCadastroWrap"></div>
      <div class="orc-table-card"><div class="table-scroll"><table><thead><tr><th>Cliente</th><th>Código</th><th>Selos</th><th>Pontos</th><th>Status</th><th>Ações</th></tr></thead>
      <tbody id="fidCartoesBody">${cardsRows(state.cartoes)}</tbody></table></div></div>`);
    bindUnidadeSelect(loadCartoes);
    bindCartoes();
  }

  function cardsRows(items) {
    if (!items.length) return `<tr><td colspan="6">${empty("Cadastre o primeiro cartão para iniciar o programa.")}</td></tr>`;
    return items.map((c) => `<tr>${clienteCell(c)}<td><code class="fid-codigo">${esc(c.codigo_fidelidade)}</code></td>
      <td><strong>${Number(c.saldo_selos || 0)}</strong></td>
      <td>${Number(c.saldo_pontos || 0)}</td><td>${badge(c.status)}</td><td class="fid-cartoes-acoes-cell"><div class="fid-cartoes-actions">
      <button type="button" class="fid-act-btn fid-act-btn--primary" data-fid-action="selo" data-id="${c.id}">+ Selo</button>
      <button type="button" class="fid-act-btn fid-act-btn--secondary" data-fid-action="ajuste" data-id="${c.id}">Ajustar</button>
      <button type="button" class="fid-act-btn fid-act-btn--secondary" data-fid-action="resgatar" data-id="${c.id}">Resgatar</button>
      <button type="button" class="fid-act-btn fid-act-btn--neutral" data-fid-action="extrato" data-id="${c.id}">Extrato</button>
      <button type="button" class="fid-act-btn fid-act-btn--neutral" data-fid-action="status" data-id="${c.id}" data-status="${c.status}">${c.status === "ativo" ? "Bloquear" : "Ativar"}</button>
      <button type="button" class="fid-act-btn fid-act-btn--danger" data-fid-action="excluir" data-id="${c.id}" data-nome="${esc(c.nome || c.telefone_normalizado || "cliente")}" title="Apaga cartão, selos e extrato">Excluir</button>
      </div></td></tr>`).join("");
  }

  function bindCartoes() {
    $("fidNovoCartao").onclick = () => {
      $("fidCadastroWrap").innerHTML = `<form id="fidCadastroForm" class="orc-card"><div class="orc-section-title"><h3>Novo cartão</h3></div>
        <div class="orc-form-grid"><label>Nome<input name="nome" required></label><label>Telefone<input name="telefone" required></label>
        <label>CPF<input name="cpf"></label><label>E-mail<input name="email" type="email"></label></div>
        <div class="orc-actions"><button class="btn primary" type="submit">Cadastrar</button><button class="btn neutral" type="button" id="fidCancelarCadastro">Cancelar</button></div></form>`;
      $("fidCancelarCadastro").onclick = () => { $("fidCadastroWrap").innerHTML = ""; };
      $("fidCadastroForm").onsubmit = async (e) => {
        e.preventDefault(); const f = e.currentTarget;
        await api("/cartoes", { method: "POST", body: JSON.stringify({ nome: value(f, "nome"), telefone: value(f, "telefone"), cpf: value(f, "cpf") || null, email: value(f, "email") || null }) });
        toast("Cartão criado.", "success"); await loadCartoes();
      };
    };
    $("fidBuscaForm").onsubmit = async (e) => {
      e.preventDefault(); const f = e.currentTarget;
      state.cartoes = (await api(`/cartoes?q=${encodeURIComponent(value(f, "q"))}&status=${encodeURIComponent(value(f, "status"))}`)).items || [];
      $("fidCartoesBody").innerHTML = cardsRows(state.cartoes);
    };
    $("fidelidadeCartoesRoot").onclick = async (e) => {
      const b = e.target.closest("[data-fid-action]"); if (!b) return;
      const id = Number(b.dataset.id); const action = b.dataset.fidAction;
      if (action === "extrato") { state.cartaoId = id; window.navigateTo?.("fidelidadeHistorico"); return; }
      if (action === "excluir") {
        const nome = b.dataset.nome || "este cliente";
        if (!confirm(`Excluir o cartão de ${nome}?\n\nTodo o histórico (selos, pontos e extrato) será apagado. Se cadastrar de novo, começa do zero.`)) return;
        await api(`/cartoes/${id}`, { method: "DELETE" });
        if (Number(state.cartaoId) === id) state.cartaoId = null;
        toast("Cartão excluído. Novo cadastro começa do zero.", "success");
        await loadCartoes();
        return;
      }
      if (action === "selo") await api(`/cartoes/${id}/selo`, { method: "POST", body: JSON.stringify({ idempotency_key: uid(), descricao: "Selo lançado pelo painel" }) });
      if (action === "ajuste") {
        const delta = prompt("Informe o ajuste de selos (use número negativo para retirar):", "1"); if (delta === null) return;
        const descricao = prompt("Motivo do ajuste:", "Ajuste administrativo"); if (!Number.isFinite(Number(delta))) return;
        await api(`/cartoes/${id}/ajuste`, { method: "POST", body: JSON.stringify({ delta_selos: Number(delta), descricao, idempotency_key: uid() }) });
      }
      if (action === "resgatar") {
        const ok = await resgatarCartao(id);
        if (!ok) return;
        toast("Resgate registrado.", "success");
        await loadCartoes();
        return;
      }
      if (action === "status") await api(`/cartoes/${id}/status`, { method: "PATCH", body: JSON.stringify({ status: b.dataset.status === "ativo" ? "bloqueado" : "ativo", idempotency_key: uid() }) });
      toast("Cartão atualizado.", "success"); await loadCartoes();
    };
  }

  async function loadHistorico() {
    const root = $("fidelidadeHistoricoRoot"); if (!root) return;
    await ensureUnidades();
    await refreshCartoes();
    const options = state.cartoes.map((c) => `<option value="${c.id}" ${Number(c.id) === Number(state.cartaoId) ? "selected" : ""}>${esc(c.nome || c.codigo_fidelidade)} — ${esc(c.codigo_fidelidade)}</option>`).join("");
    if (!state.cartaoId) {
      root.innerHTML = shell(header("Extrato", "Histórico imutável de créditos, débitos e estornos.", "🕐") + unidadeBar() + empty("Cadastre um cartão primeiro."));
      bindUnidadeSelect(loadHistorico);
      return;
    }
    const data = await api(`/cartoes/${state.cartaoId}/extrato`);
    root.innerHTML = shell(header("Extrato", "Cada correção gera um novo movimento; o histórico original é preservado.", "🕐")
      + unidadeBar()
      + `<div class="orc-filterbar"><label>Cartão<select id="fidExtratoCartao">${options}</select></label>
        <div class="orc-kpi"><span>Selos</span><strong>${Number(data.saldo_selos || 0)}</strong></div>
        <div class="orc-kpi"><span>Pontos</span><strong>${Number(data.saldo_pontos || 0)}</strong></div></div>
      <div class="orc-table-card"><div class="table-scroll"><table><thead><tr><th>Data</th><th>Movimento</th><th>Descrição</th><th>Selos</th><th>Pontos</th><th>Saldo após</th><th></th></tr></thead><tbody>
      ${(data.items || []).map((m) => `<tr><td>${esc(new Date(m.created_at).toLocaleString("pt-BR"))}</td><td>${esc(m.tipo)}</td><td>${esc(m.descricao || "")}</td>
      <td>${Number(m.delta_selos || 0) > 0 ? "+" : ""}${Number(m.delta_selos || 0)}</td><td>${Number(m.delta_pontos || 0) > 0 ? "+" : ""}${Number(m.delta_pontos || 0)}</td>
      <td>${Number(m.saldo_selos_apos || 0)} selos</td><td>${["selo", "credito", "ajuste", "debito_resgate"].includes(m.tipo) && !m.reverso_de_id ? `<button class="btn small neutral" data-estornar="${m.id}">Estornar</button>` : ""}</td></tr>`).join("") || `<tr><td colspan="7">Sem movimentos.</td></tr>`}
      </tbody></table></div></div>`);
    bindUnidadeSelect(loadHistorico);
    $("fidExtratoCartao").onchange = async (e) => { state.cartaoId = Number(e.target.value); await loadHistorico(); };
    root.onclick = async (e) => {
      const b = e.target.closest("[data-estornar]"); if (!b || !confirm("Estornar este movimento?")) return;
      await api(`/cartoes/${state.cartaoId}/estornar/${b.dataset.estornar}`, { method: "POST", body: JSON.stringify({ idempotency_key: uid() }) });
      toast("Movimento estornado.", "success"); await loadHistorico();
    };
  }

  async function loadRecompensas() {
    const root = $("fidelidadeRecompensasRoot"); if (!root) return;
    await ensureUnidades();
    const [r, q] = await Promise.all([api("/recompensas"), api("/resgates")]);
    state.recompensas = r.items || [];
    root.innerHTML = shell(header("Recompensas", "Monte o catálogo e acompanhe a entrega dos benefícios.", "★")
      + unidadeBar()
      + `<form id="fidRecompensaForm" class="orc-card"><div class="orc-section-title"><h3>Nova recompensa</h3></div><div class="orc-form-grid">
        <label>Título<input name="titulo" required></label><label>Tipo<select name="tipo" id="fidCatTipo"><option value="produto">Produto</option><option value="brinde">Brinde</option><option value="desconto_valor">Desconto em valor</option></select></label>
        <label>Custo em selos<input name="custo_selos" type="number" min="0" value="10"></label><label>Custo em pontos<input name="custo_pontos" type="number" min="0" value="0"></label>
        <label class="fid-rec-field fid-rec-field--valor hidden" data-fid-rec-show="desconto_valor">Valor do desconto (R$)<input name="valor_desconto" type="number" min="0" step="0.01"></label></div><button class="btn primary" type="submit">Adicionar recompensa</button></form>
      <div class="orc-grid orc-grid--2"><div class="orc-table-card"><div class="orc-section-title"><h3>Catálogo</h3></div><div class="table-scroll"><table><thead><tr><th>Recompensa</th><th>Tipo</th><th>Custo</th><th>Status</th></tr></thead><tbody>
      ${state.recompensas.map((x) => `<tr><td><strong>${esc(x.titulo)}</strong></td><td>${esc(x.tipo)}</td><td>${Number(x.custo_selos || 0)} selos / ${Number(x.custo_pontos || 0)} pts</td><td>${badge(Number(x.ativo) ? "ativo" : "inativo")}</td></tr>`).join("") || `<tr><td colspan="4">Sem recompensas.</td></tr>`}</tbody></table></div></div>
      <div class="orc-table-card"><div class="orc-section-title"><h3>Resgates</h3></div><div class="table-scroll"><table><thead><tr><th>#</th><th>Cartão</th><th>Status</th><th>Ação</th></tr></thead><tbody>
      ${(q.items || []).map((x) => `<tr><td>#${x.id}</td><td>${x.conta_id}</td><td>${badge(x.status)}</td><td>${x.status === "pendente" ? `<button class="btn small primary" data-entregar="${x.id}">Marcar entregue</button>` : ""}</td></tr>`).join("") || `<tr><td colspan="4">Sem resgates.</td></tr>`}</tbody></table></div></div></div>`);
    bindUnidadeSelect(loadRecompensas);
    syncProgramaRecompensaFields($("fidRecompensaForm"));
    $("fidCatTipo")?.addEventListener("change", () => syncProgramaRecompensaFields($("fidRecompensaForm")));
    $("fidRecompensaForm").onsubmit = async (e) => {
      e.preventDefault(); const f = e.currentTarget;
      const tipo = value(f, "tipo");
      await api("/recompensas", { method: "POST", body: JSON.stringify({ titulo: value(f, "titulo"), tipo, custo_selos: Number(value(f, "custo_selos")), custo_pontos: Number(value(f, "custo_pontos")), valor_desconto: tipo === "desconto_valor" && value(f, "valor_desconto") !== "" ? Number(value(f, "valor_desconto")) : null, ativo: true }) });
      toast("Recompensa cadastrada.", "success"); await loadRecompensas();
    };
    root.onclick = async (e) => {
      const b = e.target.closest("[data-entregar]"); if (!b) return;
      await api(`/resgates/${b.dataset.entregar}`, { method: "PATCH", body: JSON.stringify({ status: "entregue" }) });
      toast("Entrega confirmada.", "success"); await loadRecompensas();
    };
  }

  async function loadRelatorios() {
    const root = $("fidelidadeRelatoriosRoot"); if (!root) return;
    await ensureUnidades();
    const r = (await api("/relatorios/resumo")).resumo || {};
    const cards = [
      ["Cartões ativos", r.cartoes_ativos, "▣"], ["Selos no mês", r.selos_mes, "★"],
      ["Resgates pendentes", r.resgates_pendentes, "🕐"], ["Taxa de conversão", `${Number(r.taxa_conversao_percentual || 0).toFixed(1)}%`, "↗"],
      ["Saldo de selos", r.saldo_selos, "●"], ["Saldo de pontos", r.saldo_pontos, "◆"],
    ];
    root.innerHTML = shell(header("Relatórios", "Indicadores operacionais do programa de fidelidade.", "📊")
      + unidadeBar()
      + `<div class="orc-kpis">${cards.map((c) => `<article class="orc-kpi"><span>${c[2]} ${esc(c[0])}</span><strong>${esc(c[1] ?? 0)}</strong></article>`).join("")}</div>
      <div class="orc-card"><h3>Leitura do programa</h3><p>Há <strong>${Number(r.cartoes_total || 0)}</strong> cartões cadastrados e <strong>${Number(r.resgates_total || 0)}</strong> resgates acumulados. Os saldos são calculados pelo livro-razão imutável.</p></div>`);
    bindUnidadeSelect(loadRelatorios);
  }

  window.loadFidelidadePrograma = loadPrograma;
  window.loadFidelidadeCartoes = loadCartoes;
  window.loadFidelidadeHistorico = loadHistorico;
  window.loadFidelidadeRecompensas = loadRecompensas;
  window.loadFidelidadeRelatorios = loadRelatorios;
})();
