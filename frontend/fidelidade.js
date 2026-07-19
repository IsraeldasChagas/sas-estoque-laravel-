/**
 * Fidelidade — administração do programa, cartões, extrato e recompensas.
 */
(function () {
  "use strict";

  const state = { cartoes: [], recompensas: [], cartaoId: null, unidades: [], unidadeId: "" };
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

  async function ensureUnidades() {
    if (state.unidades.length) return state.unidades;
    try {
      const list = typeof window.fetchJSON === "function" ? await window.fetchJSON("/unidades") : [];
      state.unidades = Array.isArray(list) ? list : [];
    } catch (_) {
      state.unidades = [];
    }
    if (!state.unidadeId && state.unidades.length) {
      const userU = window.currentUser?.unidade_id ? String(window.currentUser.unidade_id) : "";
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
    root.innerHTML = shell(header("Programa", "Defina como os clientes acumulam e trocam benefícios.", "🎁")
      + unidadeBar()
      + `<form id="fidProgramaForm" class="orc-card">
        <div class="orc-section-title"><div><h3>Regras do programa</h3><p>Configuração da unidade selecionada acima.</p></div></div>
        <div class="orc-form-grid">
          <label>Nome exibido<input name="nome_exibicao" value="${esc(p.nome_exibicao || "Cartão fidelidade")}" required></label>
          <label>Modo<select name="modo"><option value="selos" ${p.modo !== "pontos" ? "selected" : ""}>Selos</option><option value="pontos" ${p.modo === "pontos" ? "selected" : ""}>Pontos</option></select></label>
          <label>Meta de selos<input name="pedidos_meta" type="number" min="1" value="${esc(p.pedidos_meta || 10)}" required></label>
          <label>Pontos por selo<input name="pontos_por_selo" type="number" min="0" value="${esc(p.pontos_por_selo ?? 1)}"></label>
          <label>Recompensa padrão<select name="tipo_recompensa_padrao">
            <option value="produto" ${p.tipo_recompensa_padrao === "produto" ? "selected" : ""}>Produto</option>
            <option value="desconto_valor" ${p.tipo_recompensa_padrao === "desconto_valor" ? "selected" : ""}>Desconto em valor</option>
            <option value="catalogo" ${p.tipo_recompensa_padrao === "catalogo" ? "selected" : ""}>Catálogo de recompensas</option>
          </select></label>
          <label>Valor do desconto<input name="valor_desconto" type="number" min="0" step="0.01" value="${esc(p.valor_desconto || "")}"></label>
          <label>Validade dos créditos (dias)<input name="dias_expiracao_credito" type="number" min="1" value="${esc(p.dias_expiracao_credito || "")}" placeholder="Sem expiração"></label>
          <label class="checkbox-label"><input name="ativo" type="checkbox" ${p.ativo ? "checked" : ""}> Programa ativo nesta unidade</label>
          <label class="checkbox-label"><input name="permite_ajuste_manual" type="checkbox" ${p.permite_ajuste_manual !== false && Number(p.permite_ajuste_manual) !== 0 ? "checked" : ""}> Permitir ajustes manuais</label>
        </div>
        <label>Mensagem da recompensa<textarea name="texto_recompensa" rows="3">${esc(p.texto_recompensa || "")}</textarea></label>
        <div class="orc-actions"><button class="btn primary" type="submit">Salvar programa</button></div>
      </form>`);
    bindUnidadeSelect(loadPrograma);
    $("fidProgramaForm").addEventListener("submit", async (event) => {
      event.preventDefault();
      const f = event.currentTarget;
      await api("/programa", { method: "PUT", body: JSON.stringify({
        nome_exibicao: value(f, "nome_exibicao"), modo: value(f, "modo"),
        pedidos_meta: Number(value(f, "pedidos_meta")), pontos_por_selo: Number(value(f, "pontos_por_selo")),
        tipo_recompensa_padrao: value(f, "tipo_recompensa_padrao"),
        valor_desconto: value(f, "valor_desconto") === "" ? null : Number(value(f, "valor_desconto")),
        dias_expiracao_credito: value(f, "dias_expiracao_credito") === "" ? null : Number(value(f, "dias_expiracao_credito")),
        texto_recompensa: value(f, "texto_recompensa") || null,
        ativo: checked(f, "ativo"), permite_ajuste_manual: checked(f, "permite_ajuste_manual"),
        unidade_id: state.unidadeId ? Number(state.unidadeId) : undefined,
      }) });
      toast("Programa de fidelidade salvo.", "success");
      await loadPrograma();
    });
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
      <div class="orc-table-card"><div class="table-scroll"><table><thead><tr><th>Cliente</th><th>Código</th><th>Contato</th><th>Selos</th><th>Pontos</th><th>Status</th><th>Ações</th></tr></thead>
      <tbody id="fidCartoesBody">${cardsRows(state.cartoes)}</tbody></table></div></div>`);
    bindUnidadeSelect(loadCartoes);
    bindCartoes();
  }

  function cardsRows(items) {
    if (!items.length) return `<tr><td colspan="7">${empty("Cadastre o primeiro cartão para iniciar o programa.")}</td></tr>`;
    return items.map((c) => `<tr><td><strong>${esc(c.nome || "Cliente sem nome")}</strong></td><td>${esc(c.codigo_fidelidade)}</td>
      <td>${esc(c.telefone_normalizado)}<small>${esc(c.email || "")}</small></td><td><strong>${Number(c.saldo_selos || 0)}</strong></td>
      <td>${Number(c.saldo_pontos || 0)}</td><td>${badge(c.status)}</td><td><div class="fid-cartoes-actions">
      <button type="button" class="btn small primary" data-fid-action="selo" data-id="${c.id}">+ Selo</button>
      <button type="button" class="btn small secondary" data-fid-action="ajuste" data-id="${c.id}">Ajustar</button>
      <button type="button" class="btn small secondary" data-fid-action="resgatar" data-id="${c.id}">Resgatar</button>
      <button type="button" class="btn small neutral" data-fid-action="extrato" data-id="${c.id}">Extrato</button>
      <button type="button" class="btn small neutral" data-fid-action="status" data-id="${c.id}" data-status="${c.status}">${c.status === "ativo" ? "Bloquear" : "Ativar"}</button>
      <button type="button" class="btn small danger" data-fid-action="excluir" data-id="${c.id}" data-nome="${esc(c.nome || c.telefone_normalizado || "cliente")}" title="Apaga cartão, selos e extrato">Excluir</button>
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
      if (action === "resgatar") await api(`/cartoes/${id}/resgatar`, { method: "POST", body: JSON.stringify({ idempotency_key: uid() }) });
      if (action === "status") await api(`/cartoes/${id}/status`, { method: "PATCH", body: JSON.stringify({ status: b.dataset.status === "ativo" ? "bloqueado" : "ativo", idempotency_key: uid() }) });
      toast(action === "resgatar" ? "Resgate registrado." : "Cartão atualizado.", "success"); await loadCartoes();
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
        <label>Título<input name="titulo" required></label><label>Tipo<select name="tipo"><option value="produto">Produto</option><option value="desconto_valor">Desconto</option><option value="brinde">Brinde</option></select></label>
        <label>Custo em selos<input name="custo_selos" type="number" min="0" value="10"></label><label>Custo em pontos<input name="custo_pontos" type="number" min="0" value="0"></label>
        <label>Valor do desconto<input name="valor_desconto" type="number" min="0" step="0.01"></label></div><button class="btn primary" type="submit">Adicionar recompensa</button></form>
      <div class="orc-grid orc-grid--2"><div class="orc-table-card"><div class="orc-section-title"><h3>Catálogo</h3></div><div class="table-scroll"><table><thead><tr><th>Recompensa</th><th>Tipo</th><th>Custo</th><th>Status</th></tr></thead><tbody>
      ${state.recompensas.map((x) => `<tr><td><strong>${esc(x.titulo)}</strong></td><td>${esc(x.tipo)}</td><td>${Number(x.custo_selos || 0)} selos / ${Number(x.custo_pontos || 0)} pts</td><td>${badge(Number(x.ativo) ? "ativo" : "inativo")}</td></tr>`).join("") || `<tr><td colspan="4">Sem recompensas.</td></tr>`}</tbody></table></div></div>
      <div class="orc-table-card"><div class="orc-section-title"><h3>Resgates</h3></div><div class="table-scroll"><table><thead><tr><th>#</th><th>Cartão</th><th>Status</th><th>Ação</th></tr></thead><tbody>
      ${(q.items || []).map((x) => `<tr><td>#${x.id}</td><td>${x.conta_id}</td><td>${badge(x.status)}</td><td>${x.status === "pendente" ? `<button class="btn small primary" data-entregar="${x.id}">Marcar entregue</button>` : ""}</td></tr>`).join("") || `<tr><td colspan="4">Sem resgates.</td></tr>`}</tbody></table></div></div></div>`);
    bindUnidadeSelect(loadRecompensas);
    $("fidRecompensaForm").onsubmit = async (e) => {
      e.preventDefault(); const f = e.currentTarget;
      await api("/recompensas", { method: "POST", body: JSON.stringify({ titulo: value(f, "titulo"), tipo: value(f, "tipo"), custo_selos: Number(value(f, "custo_selos")), custo_pontos: Number(value(f, "custo_pontos")), valor_desconto: value(f, "valor_desconto") === "" ? null : Number(value(f, "valor_desconto")), ativo: true }) });
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
