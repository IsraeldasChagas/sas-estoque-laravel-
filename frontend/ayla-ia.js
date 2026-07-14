/**
 * Ayla IA — módulo administrativo do SAS-Estoque.
 * Integra com a API Ayla (/api/ayla/v1) e o painel (/api/ayla-admin).
 * Todas as permissões são validadas no backend; aqui só melhoramos a UX.
 */
(function () {
  "use strict";

  const AYLA_MODULOS_FALLBACK = {
    dashboard: "Dashboard", unidades: "Unidades", produtos: "Produtos", estoque: "Estoque",
    movimentacoes: "Movimentações", lotes: "Lotes", compras: "Compras", fornecedores: "Fornecedores",
    relatorios: "Relatórios", financeiro: "Financeiro", rh: "RH", reservas: "Reservas",
    energia: "Energia", patrimonio: "Patrimônio", investimentos: "Investimentos", logs: "Logs",
  };

  const state = { opcoes: null, usuarios: [], logPagina: 1 };

  // ---- helpers -----------------------------------------------------------
  function toast(msg, type) {
    if (typeof window.showToast === "function") window.showToast(msg, type || "info");
  }

  function perfilAtual() {
    if (!window.currentUser && typeof getUser === "function") {
      try { window.currentUser = getUser(); } catch (_) {}
    }
    return (window.currentUser?.perfil || "").toString().trim().toUpperCase();
  }

  function ehAdmin() {
    const p = perfilAtual();
    return p === "ADMIN" || p === "ADMINISTRADOR";
  }

  async function ayFetch(path, opts) {
    if (typeof window.fetchJSON !== "function") throw new Error("fetchJSON indisponível");
    return window.fetchJSON(path, opts);
  }

  function esc(v) {
    return String(v == null ? "" : v)
      .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;").replace(/'/g, "&#39;");
  }

  function statusBadge(status) {
    const map = { ativo: "ayla-badge--ok", pendente: "ayla-badge--warn", bloqueado: "ayla-badge--danger", revogado: "ayla-badge--muted" };
    return `<span class="ayla-badge ${map[status] || "ayla-badge--muted"}">${esc(status || "-")}</span>`;
  }

  function simNao(v) { return v ? "✔️" : "—"; }

  function fmtData(v) {
    if (!v) return "—";
    try { return new Date(v.replace(" ", "T")).toLocaleString("pt-BR"); } catch (_) { return esc(v); }
  }

  function avisoNaoAdmin(elId) {
    const el = document.getElementById(elId);
    if (!el) return;
    if (ehAdmin()) { el.classList.add("hidden"); el.innerHTML = ""; return; }
    el.classList.remove("hidden");
    el.innerHTML = "🔒 Apenas administradores podem alterar estas configurações. Você está no modo somente leitura.";
  }

  async function carregarOpcoes(force) {
    if (state.opcoes && !force) return state.opcoes;
    const r = await ayFetch("/ayla-admin/opcoes");
    state.opcoes = r;
    return r;
  }

  function modulosDisponiveis() {
    return (state.opcoes && state.opcoes.modulos_disponiveis) || AYLA_MODULOS_FALLBACK;
  }

  // ---- 1. Dashboard ------------------------------------------------------
  async function loadAylaDashboard() {
    const root = document.getElementById("aylaDashboardRoot");
    if (!root) return;
    root.innerHTML = `<div class="ayla-loading">Carregando…</div>`;
    try {
      const d = await ayFetch("/ayla-admin/dashboard");
      const integ = d.integracao || {};
      const tg = d.telegram || {};
      const us = d.usuarios || {};
      const cons = d.consultas || {};
      const teste = d.ultimo_teste || {};
      const ultimas = d.ultimas_atividades || [];

      root.innerHTML = `
        <div class="cfg-grid ayla-cards">
          ${card("Status da integração", integ.ativa ? "🟢 Online" : "🔴 Offline", integ.ativa ? "A Ayla está ativa." : "Integração desativada.")}
          ${card("API SAS-Estoque", integ.read_only ? "Somente leitura" : "Leitura/escrita", `Versão ${esc(integ.versao || "-")} · ${integ.token_configurado ? "token configurado" : "sem token"}`)}
          ${card("Telegram", tg.ativo ? "Configurado" : "Não configurado", tg.bot_username ? "@" + esc(tg.bot_username) : "Bot não informado")}
          ${card("Usuários autorizados", String(us.total || 0), `${us.ativos || 0} ativos · ${us.pendentes || 0} pendentes · ${us.bloqueados || 0} bloqueados`)}
          ${card("Consultas hoje", String(cons.hoje || 0), `${cons.sucesso || 0} ok · ${cons.erros || 0} erros · ${cons.tempo_medio_ms || 0} ms médio`)}
          ${card("Último teste", teste.status ? esc(teste.status) : "—", teste.em ? fmtData(teste.em) : "Nunca testado")}
        </div>
        <div class="table-card form-card--wide ayla-block">
          <header><h3>Últimas atividades</h3></header>
          <div class="table-wrapper">
            <table class="data-table">
              <thead><tr><th>Data</th><th>Usuário</th><th>Ação</th><th>Status</th><th>Duração</th></tr></thead>
              <tbody>${ultimas.length ? ultimas.map(a => `
                <tr>
                  <td>${fmtData(a.quando)}</td>
                  <td>${esc(a.usuario || "—")}</td>
                  <td>${esc(a.acao || "—")}</td>
                  <td>${esc(a.status || "—")} (${esc(a.http_status || "-")})</td>
                  <td>${a.duracao_ms != null ? esc(a.duracao_ms) + " ms" : "—"}</td>
                </tr>`).join("") : `<tr><td colspan="5" class="ayla-empty">Nenhuma atividade registrada ainda.</td></tr>`}
              </tbody>
            </table>
          </div>
        </div>`;
    } catch (e) {
      root.innerHTML = `<div class="ayla-empty">Não foi possível carregar o dashboard: ${esc(e.message)}</div>`;
    }
  }

  function card(titulo, valor, texto) {
    return `<div class="cfg-card ayla-card">
      <div class="cfg-card__title">${esc(titulo)}</div>
      <div class="ayla-card__value">${valor}</div>
      <div class="cfg-card__text">${esc(texto || "")}</div>
    </div>`;
  }

  async function testarConexao() {
    const btn = document.getElementById("aylaBtnTestarConexao");
    if (btn) { btn.disabled = true; btn.textContent = "Testando…"; }
    try {
      const r = await ayFetch("/ayla-admin/testar-conexao", { method: "POST", body: JSON.stringify({}) });
      toast(r.message || (r.ok ? "Conexão OK." : "Falha na conexão."), r.ok ? "success" : "error");
    } catch (e) {
      toast(e.message || "Falha ao testar conexão.", "error");
    } finally {
      if (btn) { btn.disabled = false; btn.textContent = "Testar conexão"; }
      loadAylaDashboard();
    }
  }

  // ---- 2. Usuários autorizados ------------------------------------------
  async function loadAylaUsuarios() {
    const root = document.getElementById("aylaUsuariosRoot");
    if (!root) return;
    if (!ehAdmin()) { root.innerHTML = `<div class="ayla-empty">🔒 Apenas administradores podem gerenciar usuários da Ayla.</div>`; return; }
    root.innerHTML = `<div class="ayla-loading">Carregando…</div>`;
    try {
      await carregarOpcoes();
      const r = await ayFetch("/ayla-admin/usuarios");
      state.usuarios = r.usuarios || [];
      renderUsuariosTabela(root);
    } catch (e) {
      root.innerHTML = `<div class="ayla-empty">Erro ao carregar: ${esc(e.message)}</div>`;
    }
  }

  function renderUsuariosTabela(root) {
    const rows = state.usuarios;
    root.innerHTML = `
      <div class="table-card form-card--wide ayla-block">
        <div class="table-wrapper">
          <table class="data-table">
            <thead><tr>
              <th>Usuário</th><th>Perfil</th><th>Cargo</th><th>Unidade</th>
              <th>Telegram</th><th>Texto</th><th>Áudio</th><th>Consulta</th><th>Ações IA</th>
              <th>Status</th><th>Último acesso</th><th>Ações</th>
            </tr></thead>
            <tbody>${rows.length ? rows.map(u => `
              <tr>
                <td>${esc(u.usuario_nome || "#" + u.usuario_id)}</td>
                <td>${esc(u.usuario_perfil || "—")}</td>
                <td>${esc(u.cargo || "—")}</td>
                <td>${esc(u.unidade_nome || "—")}</td>
                <td>${esc(u.telegram_user_id || "—")}${u.telegram_username ? " (@" + esc(u.telegram_username) + ")" : ""}</td>
                <td>${simNao(u.pode_usar_texto)}</td>
                <td>${simNao(u.pode_usar_audio)}</td>
                <td>${simNao(u.pode_consultar_dados)}</td>
                <td>${simNao(u.pode_executar_acoes)}</td>
                <td>${statusBadge(u.status)}</td>
                <td>${fmtData(u.ultimo_acesso_em)}</td>
                <td class="ayla-row-actions">
                  <button class="btn neutral btn-sm" data-ayla-edit="${u.id}">Editar</button>
                  ${u.status !== "ativo" ? `<button class="btn secondary btn-sm" data-ayla-status="${u.id}" data-novo="ativo">Ativar</button>` : ""}
                  ${u.status !== "bloqueado" ? `<button class="btn neutral btn-sm" data-ayla-status="${u.id}" data-novo="bloqueado">Bloquear</button>` : ""}
                  ${u.status !== "revogado" ? `<button class="btn danger btn-sm" data-ayla-revogar="${u.id}">Revogar</button>` : ""}
                </td>
              </tr>`).join("") : `<tr><td colspan="12" class="ayla-empty">Nenhum acesso cadastrado. Clique em “Novo acesso”.</td></tr>`}
            </tbody>
          </table>
        </div>
      </div>
      <div id="aylaUsuarioFormWrap"></div>`;

    root.querySelectorAll("[data-ayla-edit]").forEach(b => b.addEventListener("click", () => abrirFormUsuario(b.getAttribute("data-ayla-edit"))));
    root.querySelectorAll("[data-ayla-status]").forEach(b => b.addEventListener("click", () => mudarStatus(b.getAttribute("data-ayla-status"), b.getAttribute("data-novo"))));
    root.querySelectorAll("[data-ayla-revogar]").forEach(b => b.addEventListener("click", () => revogar(b.getAttribute("data-ayla-revogar"))));
  }

  function abrirFormUsuario(id) {
    const wrap = document.getElementById("aylaUsuarioFormWrap");
    if (!wrap) return;
    const editar = id ? state.usuarios.find(u => String(u.id) === String(id)) : null;
    const usuarios = (state.opcoes?.usuarios) || [];
    const unidades = (state.opcoes?.unidades) || [];
    const modulos = modulosDisponiveis();
    const modSel = editar?.modulos_permitidos || Object.keys(modulos);
    const uniSel = (editar?.unidades_permitidas || []).map(String);

    wrap.innerHTML = `
      <div class="table-card form-card--wide ayla-block ayla-form-card">
        <header><h3>${editar ? "Editar acesso" : "Novo acesso"}</h3></header>
        <div class="cfg-form-grid">
          <label class="cfg-full">Usuário do SAS
            <select id="aylaFUsuario" ${editar ? "disabled" : ""}>
              <option value="">Selecione…</option>
              ${usuarios.map(u => `<option value="${u.id}" ${editar && String(editar.usuario_id) === String(u.id) ? "selected" : ""}>${esc(u.nome)} — ${esc(u.perfil || "")}</option>`).join("")}
            </select>
          </label>
          <label>Cargo <input type="text" id="aylaFCargo" maxlength="120" value="${esc(editar?.cargo || "")}"></label>
          <label>Telegram User ID <input type="text" id="aylaFTgId" maxlength="32" inputmode="numeric" value="${esc(editar?.telegram_user_id || "")}"></label>
          <label>Username Telegram <input type="text" id="aylaFTgUser" maxlength="120" value="${esc(editar?.telegram_username || "")}"></label>
          <label>Status
            <select id="aylaFStatus">
              ${(state.opcoes?.status_disponiveis || ["pendente","ativo","bloqueado","revogado"]).map(s => `<option value="${s}" ${editar && editar.status === s ? "selected" : (!editar && s === "pendente" ? "selected" : "")}>${s}</option>`).join("")}
            </select>
          </label>
          <div class="cfg-full ayla-form-block">
            <strong>Unidades permitidas</strong>
            <p class="ayla-hint">Vazio = todas que o usuário já enxerga no SAS.</p>
            <div class="oc-check-grid">${unidades.map(u => `<label class="checkbox-label"><input type="checkbox" class="aylaFUnidade" value="${u.id}" ${uniSel.includes(String(u.id)) ? "checked" : ""}> ${esc(u.nome)}</label>`).join("") || "<span class='ayla-hint'>Sem unidades cadastradas.</span>"}</div>
          </div>
          <div class="cfg-full ayla-form-block">
            <strong>Módulos permitidos</strong>
            <div class="oc-check-grid">${Object.entries(modulos).map(([k, label]) => `<label class="checkbox-label"><input type="checkbox" class="aylaFModulo" value="${k}" ${modSel.includes(k) ? "checked" : ""}> ${esc(label)}</label>`).join("")}</div>
          </div>
          <div class="cfg-full ayla-form-block">
            <strong>Capacidades</strong>
            <div class="oc-check-grid">
              <label class="checkbox-label"><input type="checkbox" id="aylaFTexto" ${editar ? (editar.pode_usar_texto ? "checked" : "") : "checked"}> Permitir texto</label>
              <label class="checkbox-label"><input type="checkbox" id="aylaFAudio" ${editar ? (editar.pode_usar_audio ? "checked" : "") : "checked"}> Permitir áudio</label>
              <label class="checkbox-label"><input type="checkbox" id="aylaFConsulta" ${editar ? (editar.pode_consultar_dados ? "checked" : "") : "checked"}> Permitir consultas</label>
              <label class="checkbox-label ayla-disabled" title="A API Ayla está em modo somente leitura"><input type="checkbox" disabled> Solicitar ações (bloqueado)</label>
            </div>
          </div>
          <label class="cfg-full">Observações <textarea id="aylaFObs" maxlength="1000" rows="2">${esc(editar?.observacoes || "")}</textarea></label>
        </div>
        <div class="oc-form-actions">
          <button class="btn primary" id="aylaFSalvar">${editar ? "Salvar alterações" : "Cadastrar acesso"}</button>
          <button class="btn neutral" id="aylaFCancelar">Cancelar</button>
        </div>
      </div>`;

    wrap.scrollIntoView({ behavior: "smooth", block: "nearest" });
    document.getElementById("aylaFCancelar").addEventListener("click", () => { wrap.innerHTML = ""; });
    document.getElementById("aylaFSalvar").addEventListener("click", () => salvarUsuario(editar ? editar.id : null));
  }

  function coletarFormUsuario() {
    return {
      usuario_id: parseInt(document.getElementById("aylaFUsuario").value || "0", 10),
      cargo: document.getElementById("aylaFCargo").value.trim(),
      telegram_user_id: document.getElementById("aylaFTgId").value.trim(),
      telegram_username: document.getElementById("aylaFTgUser").value.trim(),
      status: document.getElementById("aylaFStatus").value,
      unidades_permitidas: Array.from(document.querySelectorAll(".aylaFUnidade:checked")).map(c => parseInt(c.value, 10)),
      modulos_permitidos: Array.from(document.querySelectorAll(".aylaFModulo:checked")).map(c => c.value),
      pode_usar_texto: document.getElementById("aylaFTexto").checked,
      pode_usar_audio: document.getElementById("aylaFAudio").checked,
      pode_consultar_dados: document.getElementById("aylaFConsulta").checked,
      observacoes: document.getElementById("aylaFObs").value.trim(),
    };
  }

  async function salvarUsuario(id) {
    const dados = coletarFormUsuario();
    if (!dados.usuario_id) { toast("Selecione um usuário do SAS.", "error"); return; }
    try {
      if (id) await ayFetch(`/ayla-admin/usuarios/${id}`, { method: "PUT", body: JSON.stringify(dados) });
      else await ayFetch("/ayla-admin/usuarios", { method: "POST", body: JSON.stringify(dados) });
      toast("Acesso salvo.", "success");
      document.getElementById("aylaUsuarioFormWrap").innerHTML = "";
      loadAylaUsuarios();
    } catch (e) {
      toast(e.message || "Erro ao salvar.", "error");
    }
  }

  async function mudarStatus(id, novo) {
    if (novo === "bloqueado" && !confirm("Bloquear este acesso? O usuário não poderá usar a Ayla até ser reativado.")) return;
    try {
      await ayFetch(`/ayla-admin/usuarios/${id}/status`, { method: "PATCH", body: JSON.stringify({ status: novo }) });
      toast("Status atualizado.", "success");
      loadAylaUsuarios();
    } catch (e) { toast(e.message || "Erro ao atualizar status.", "error"); }
  }

  async function revogar(id) {
    if (!confirm("Revogar definitivamente este acesso? O registro é mantido para auditoria, mas o acesso é encerrado.")) return;
    try {
      await ayFetch(`/ayla-admin/usuarios/${id}`, { method: "DELETE" });
      toast("Acesso revogado.", "success");
      loadAylaUsuarios();
    } catch (e) { toast(e.message || "Erro ao revogar.", "error"); }
  }

  // ---- 3. Permissões -----------------------------------------------------
  async function loadAylaPermissoes() {
    const root = document.getElementById("aylaPermissoesRoot");
    if (!root) return;
    if (!ehAdmin()) { root.innerHTML = `<div class="ayla-empty">🔒 Apenas administradores podem editar permissões.</div>`; return; }
    root.innerHTML = `<div class="ayla-loading">Carregando…</div>`;
    try {
      await carregarOpcoes();
      const r = await ayFetch("/ayla-admin/usuarios");
      state.usuarios = r.usuarios || [];
      root.innerHTML = `
        <div class="ia-aviso">ℹ️ A API Ayla está em <strong>modo somente leitura</strong>. Ações de escrita permanecem bloqueadas nesta versão.</div>
        <div class="table-card form-card--wide ayla-block">
          <div class="cfg-form-grid">
            <label class="cfg-full">Usuário autorizado
              <select id="aylaPermUser"><option value="">Selecione…</option>${state.usuarios.map(u => `<option value="${u.id}">${esc(u.usuario_nome || "#" + u.usuario_id)} — ${esc(u.status)}</option>`).join("")}</select>
            </label>
          </div>
          <div id="aylaPermDetalhe"></div>
        </div>`;
      document.getElementById("aylaPermUser").addEventListener("change", (e) => renderPermDetalhe(e.target.value));
    } catch (e) {
      root.innerHTML = `<div class="ayla-empty">Erro ao carregar: ${esc(e.message)}</div>`;
    }
  }

  function renderPermDetalhe(id) {
    const box = document.getElementById("aylaPermDetalhe");
    if (!box) return;
    const u = state.usuarios.find(x => String(x.id) === String(id));
    if (!u) { box.innerHTML = ""; return; }
    const modulos = modulosDisponiveis();
    const modSel = u.modulos_permitidos || [];
    box.innerHTML = `
      <div class="ayla-form-block">
        <strong>Módulos de leitura</strong>
        <p class="ayla-hint">A permissão efetiva é a interseção com o que ${esc(u.usuario_nome || "o usuário")} já possui no SAS.</p>
        <div class="oc-check-grid">${Object.entries(modulos).map(([k, label]) => `<label class="checkbox-label"><input type="checkbox" class="aylaPermMod" value="${k}" ${modSel.includes(k) ? "checked" : ""}> ${esc(label)}</label>`).join("")}</div>
      </div>
      <div class="ayla-form-block">
        <strong>Ações</strong>
        <div class="oc-check-grid">
          <label class="checkbox-label"><input type="checkbox" id="aylaPermConsulta" ${u.pode_consultar_dados ? "checked" : ""}> Consultar</label>
          <label class="checkbox-label"><input type="checkbox" id="aylaPermTexto" ${u.pode_usar_texto ? "checked" : ""}> Usar texto</label>
          <label class="checkbox-label"><input type="checkbox" id="aylaPermAudio" ${u.pode_usar_audio ? "checked" : ""}> Usar áudio</label>
          <label class="checkbox-label ayla-disabled" title="Somente leitura"><input type="checkbox" disabled> Gerar relatório (leitura)</label>
          <label class="checkbox-label ayla-disabled" title="Somente leitura"><input type="checkbox" disabled> Solicitar ação (bloqueado)</label>
          <label class="checkbox-label ayla-disabled" title="Somente leitura"><input type="checkbox" disabled> Confirmar ação (bloqueado)</label>
        </div>
      </div>
      <div class="oc-form-actions"><button class="btn primary" id="aylaPermSalvar">Salvar permissões</button></div>`;
    document.getElementById("aylaPermSalvar").addEventListener("click", () => salvarPermissoes(u));
  }

  async function salvarPermissoes(u) {
    const dados = {
      usuario_id: u.usuario_id,
      status: u.status,
      cargo: u.cargo,
      telegram_user_id: u.telegram_user_id || "",
      telegram_username: u.telegram_username || "",
      unidades_permitidas: u.unidades_permitidas || [],
      modulos_permitidos: Array.from(document.querySelectorAll(".aylaPermMod:checked")).map(c => c.value),
      pode_consultar_dados: document.getElementById("aylaPermConsulta").checked,
      pode_usar_texto: document.getElementById("aylaPermTexto").checked,
      pode_usar_audio: document.getElementById("aylaPermAudio").checked,
      observacoes: u.observacoes || "",
    };
    try {
      await ayFetch(`/ayla-admin/usuarios/${u.id}`, { method: "PUT", body: JSON.stringify(dados) });
      toast("Permissões salvas.", "success");
      loadAylaPermissoes();
    } catch (e) { toast(e.message || "Erro ao salvar.", "error"); }
  }

  // ---- 4. Canais e voz ---------------------------------------------------
  async function loadAylaCanaisVoz() {
    const root = document.getElementById("aylaCanaisVozRoot");
    if (!root) return;
    root.innerHTML = `<div class="ayla-loading">Carregando…</div>`;
    try {
      const r = await ayFetch("/ayla-admin/config");
      const c = r.config || {};
      const podeEditar = ehAdmin();
      root.innerHTML = `
        <div class="cfg-grid ayla-cards">
          <div class="cfg-card ayla-card">
            <div class="cfg-card__title">📨 Telegram</div>
            <div class="ayla-card__value">${c.telegram_ativo ? "🟢 Ativo" : "⚪ Inativo"}</div>
            <div class="cfg-card__text">${c.telegram_bot_username ? "@" + esc(c.telegram_bot_username) : "Bot não informado"}<br>Modo: recebimento contínuo</div>
            <button class="btn secondary btn-sm" id="aylaTgTestar">Testar</button>
          </div>
          <div class="cfg-card ayla-card ayla-card--muted">
            <div class="cfg-card__title">💬 WhatsApp</div>
            <div class="ayla-card__value">Desativado</div>
            <div class="cfg-card__text">Não há conexão de WhatsApp nesta versão.</div>
          </div>
          <div class="cfg-card ayla-card">
            <div class="cfg-card__title">🎙️ Voz</div>
            <div class="ayla-card__value">${c.audio_ativo ? "Ativa" : "Desativada"}</div>
            <div class="cfg-card__text">Provedor: ${esc(c.audio_provider || "-")} · Voz: ${esc(c.audio_voice || "-")}</div>
          </div>
        </div>
        <div class="table-card form-card--wide ayla-block">
          <header><h3>Configuração de canais e voz</h3></header>
          <div class="cfg-form-grid">
            <label class="checkbox-label cfg-full"><input type="checkbox" id="aylaCVTgAtivo" ${c.telegram_ativo ? "checked" : ""} ${podeEditar ? "" : "disabled"}> Telegram ativo</label>
            <label>Nome do bot (username) <input type="text" id="aylaCVTgUser" value="${esc(c.telegram_bot_username || "")}" ${podeEditar ? "" : "disabled"}></label>
            <label class="checkbox-label cfg-full"><input type="checkbox" id="aylaCVAudio" ${c.audio_ativo ? "checked" : ""} ${podeEditar ? "" : "disabled"}> Áudio ativo</label>
            <label>Provedor de voz
              <select id="aylaCVProvider" ${podeEditar ? "" : "disabled"}>
                <option value="openai" ${c.audio_provider === "openai" ? "selected" : ""}>OpenAI</option>
                <option value="microsoft" ${c.audio_provider === "microsoft" ? "selected" : ""}>Microsoft</option>
              </select>
            </label>
            <label>Voz <input type="text" id="aylaCVVoice" value="${esc(c.audio_voice || "")}" ${podeEditar ? "" : "disabled"}></label>
            <label class="checkbox-label cfg-full"><input type="checkbox" id="aylaCVInbound" ${c.audio_inbound_only ? "checked" : ""} ${podeEditar ? "" : "disabled"}> Responder em áudio apenas quando receber áudio</label>
          </div>
          ${podeEditar ? `<div class="oc-form-actions"><button class="btn primary" id="aylaCVSalvar">Salvar canais e voz</button></div>` : `<div class="ia-aviso">🔒 Somente administradores podem alterar.</div>`}
        </div>`;
      document.getElementById("aylaTgTestar")?.addEventListener("click", testarConexao);
      document.getElementById("aylaCVSalvar")?.addEventListener("click", salvarCanaisVoz);
    } catch (e) {
      root.innerHTML = `<div class="ayla-empty">Erro ao carregar: ${esc(e.message)}</div>`;
    }
  }

  async function salvarCanaisVoz() {
    const dados = {
      telegram_ativo: document.getElementById("aylaCVTgAtivo").checked,
      telegram_bot_username: document.getElementById("aylaCVTgUser").value.trim(),
      audio_ativo: document.getElementById("aylaCVAudio").checked,
      audio_provider: document.getElementById("aylaCVProvider").value,
      audio_voice: document.getElementById("aylaCVVoice").value.trim(),
      audio_inbound_only: document.getElementById("aylaCVInbound").checked,
    };
    try {
      // Reaproveita o endpoint de config, preservando os demais campos no backend.
      const atual = await ayFetch("/ayla-admin/config");
      const c = atual.config || {};
      await ayFetch("/ayla-admin/config", { method: "PUT", body: JSON.stringify(Object.assign({
        ativa: c.ativa, read_only: c.read_only, api_url: c.api_url, gateway_url: c.gateway_url,
        rate_limit: c.rate_limit, unidades_globais: c.unidades_globais,
        msg_nao_autorizado: c.msg_nao_autorizado, msg_boas_vindas: c.msg_boas_vindas,
      }, dados)) });
      toast("Canais e voz salvos.", "success");
      loadAylaCanaisVoz();
    } catch (e) { toast(e.message || "Erro ao salvar.", "error"); }
  }

  // ---- 5. Logs -----------------------------------------------------------
  async function loadAylaLogs() {
    const root = document.getElementById("aylaLogsRoot");
    if (!root) return;
    root.innerHTML = `
      <div class="table-card form-card--wide ayla-block">
        <div class="cfg-form-grid ayla-filtros">
          <label>De <input type="date" id="aylaLogDe"></label>
          <label>Até <input type="date" id="aylaLogAte"></label>
          <label>Status
            <select id="aylaLogStatus"><option value="">Todos</option><option value="ok">OK</option><option value="erro">Erro</option><option value="negado">Negado</option></select>
          </label>
          <label>Ação <input type="text" id="aylaLogAcao" placeholder="ex.: ayla.produtos"></label>
          <label>Rota <input type="text" id="aylaLogRota" placeholder="ex.: ayla/v1/estoque"></label>
          <div class="ayla-filtros-actions"><button class="btn primary" id="aylaLogFiltrar">Filtrar</button></div>
        </div>
      </div>
      <div id="aylaLogsTabela"></div>`;
    document.getElementById("aylaLogFiltrar").addEventListener("click", () => { state.logPagina = 1; buscarLogs(); });
    state.logPagina = 1;
    buscarLogs();
  }

  async function buscarLogs() {
    const box = document.getElementById("aylaLogsTabela");
    if (!box) return;
    box.innerHTML = `<div class="ayla-loading">Carregando…</div>`;
    const params = new URLSearchParams();
    const de = document.getElementById("aylaLogDe")?.value;
    const ate = document.getElementById("aylaLogAte")?.value;
    const st = document.getElementById("aylaLogStatus")?.value;
    const ac = document.getElementById("aylaLogAcao")?.value;
    const ro = document.getElementById("aylaLogRota")?.value;
    if (de) params.set("de", de);
    if (ate) params.set("ate", ate);
    if (st) params.set("status", st);
    if (ac) params.set("acao", ac);
    if (ro) params.set("rota", ro);
    params.set("pagina", String(state.logPagina));
    params.set("por_pagina", "20");
    try {
      const r = await ayFetch("/ayla-admin/logs?" + params.toString());
      const logs = r.logs || [];
      const totalPag = Math.max(1, Math.ceil((r.total || 0) / (r.por_pagina || 20)));
      box.innerHTML = `
        <div class="table-card form-card--wide ayla-block">
          <div class="table-wrapper">
            <table class="data-table">
              <thead><tr><th>Data/hora</th><th>Usuário</th><th>Ação</th><th>Rota</th><th>HTTP</th><th>Resultado</th><th>Duração</th></tr></thead>
              <tbody>${logs.length ? logs.map(l => `
                <tr>
                  <td>${fmtData(l.quando)}</td>
                  <td>${esc(l.usuario || "—")}</td>
                  <td>${esc(l.acao || "—")}</td>
                  <td>${esc(l.rota || "—")}</td>
                  <td>${esc(l.http_status || "-")}</td>
                  <td>${statusBadge(l.status)}</td>
                  <td>${l.duracao_ms != null ? esc(l.duracao_ms) + " ms" : "—"}</td>
                </tr>`).join("") : `<tr><td colspan="7" class="ayla-empty">Nenhum log encontrado.</td></tr>`}
              </tbody>
            </table>
          </div>
          <div class="ayla-paginacao">
            <button class="btn neutral btn-sm" id="aylaLogPrev" ${state.logPagina <= 1 ? "disabled" : ""}>‹ Anterior</button>
            <span>Página ${state.logPagina} de ${totalPag} · ${r.total || 0} registros</span>
            <button class="btn neutral btn-sm" id="aylaLogNext" ${state.logPagina >= totalPag ? "disabled" : ""}>Próxima ›</button>
          </div>
        </div>`;
      document.getElementById("aylaLogPrev")?.addEventListener("click", () => { if (state.logPagina > 1) { state.logPagina--; buscarLogs(); } });
      document.getElementById("aylaLogNext")?.addEventListener("click", () => { state.logPagina++; buscarLogs(); });
    } catch (e) {
      box.innerHTML = `<div class="ayla-empty">Erro ao carregar logs: ${esc(e.message)}</div>`;
    }
  }

  // ---- 6. Configurações --------------------------------------------------
  async function loadAylaConfiguracoes() {
    const root = document.getElementById("aylaConfiguracoesRoot");
    if (!root) return;
    avisoNaoAdmin("aylaConfigAvisoAdmin");
    if (!ehAdmin()) { root.innerHTML = `<div class="ayla-empty">🔒 Apenas administradores podem acessar as configurações.</div>`; return; }
    root.innerHTML = `<div class="ayla-loading">Carregando…</div>`;
    try {
      await carregarOpcoes();
      const r = await ayFetch("/ayla-admin/config");
      const c = r.config || {};
      const unidades = r.unidades || [];
      const uniSel = (c.unidades_globais || []).map(String);
      const usuarios = (state.opcoes?.usuarios) || [];
      root.innerHTML = `
        <div class="ayla-config-wrap">
        <div class="table-card form-card--wide ayla-block">
          <header><h3>Configurações gerais</h3></header>
          <div class="ayla-form-body">
            <div class="ayla-toggle-row">
              <label class="oc-toggle"><input type="checkbox" id="aylaCfgAtiva" ${c.ativa ? "checked" : ""}> Ayla ativa</label>
              <label class="oc-toggle"><input type="checkbox" id="aylaCfgReadOnly" ${c.read_only ? "checked" : ""}> Modo somente leitura</label>
            </div>
            <div class="cfg-form-grid ayla-cfg-grid">
              <label>URL pública da API <input type="text" id="aylaCfgApiUrl" value="${esc(c.api_url || "")}"></label>
              <label>URL do gateway (OpenClaw) <input type="text" id="aylaCfgGateway" value="${esc(c.gateway_url || "")}"></label>
              <label>Limite de requisições/min <input type="number" id="aylaCfgRate" min="1" max="1000" value="${esc(c.rate_limit || 60)}"></label>
              <div class="cfg-full ayla-form-block">
                <strong>Unidades globais permitidas</strong>
                <p class="ayla-hint">Vazio = todas. Aplica-se quando não há usuário identificado.</p>
                <div class="oc-check-grid">${unidades.map(u => `<label class="checkbox-label"><input type="checkbox" class="aylaCfgUnidade" value="${u.id}" ${uniSel.includes(String(u.id)) ? "checked" : ""}> ${esc(u.nome)}</label>`).join("") || "<span class='ayla-hint'>Sem unidades.</span>"}</div>
              </div>
              <label class="cfg-full">Mensagem para acesso não autorizado <textarea id="aylaCfgMsgNeg" rows="3" maxlength="500">${esc(c.msg_nao_autorizado || "")}</textarea></label>
              <label class="cfg-full">Mensagem de boas-vindas <textarea id="aylaCfgMsgBv" rows="3" maxlength="500">${esc(c.msg_boas_vindas || "")}</textarea></label>
            </div>
            <div class="oc-form-actions"><button class="btn primary" id="aylaCfgSalvar">Salvar configurações</button></div>
          </div>
        </div>

        <div class="table-card form-card--wide ayla-block">
          <header><h3>Token de acesso</h3></header>
          <div class="ayla-form-body">
            <div class="oc-field">
              <div class="oc-field__label">Token atual</div>
              <div class="ayla-token-line"><code id="aylaCfgTokenMasc">${esc(c.token_mascarado || "não configurado")}</code>
                <span class="ayla-hint">${c.token_origem === "env" ? "Definido no .env (prioritário)" : "Definido pelo painel"}</span>
              </div>
              <p class="ayla-hint">O token nunca é exibido por completo. Gere um novo apenas se necessário.</p>
              <div class="ayla-token-actions">
                <button type="button" class="btn secondary" id="aylaCfgGerarToken">Gerar novo token</button>
              </div>
            </div>
            <div id="aylaCfgTokenNovo"></div>
          </div>
        </div>

        <div class="table-card form-card--wide ayla-block">
          <header><h3>Administrador principal da Ayla</h3></header>
          <div class="ayla-form-body">
            <div class="cfg-form-grid ayla-cfg-grid">
              <label>Usuário (ADMIN)
                <select id="aylaCfgAdminUser">${usuarios.map(u => `<option value="${u.id}" ${String(u.id) === String(window.currentUser?.id) ? "selected" : ""}>${esc(u.nome)} — ${esc(u.perfil || "")}</option>`).join("")}</select>
              </label>
              <label>Telegram User ID <input type="text" id="aylaCfgAdminTg" inputmode="numeric" maxlength="32"></label>
              <div class="ayla-filtros-actions"><button class="btn primary" id="aylaCfgAdminSalvar">Definir administrador</button></div>
            </div>
            <p class="ayla-hint ayla-hint--block">Vincula seu usuário SAS ao Telegram com acesso total à Ayla (somente leitura nesta versão).</p>
          </div>
        </div>
        </div>`;
      document.getElementById("aylaCfgSalvar").addEventListener("click", salvarConfig);
      document.getElementById("aylaCfgGerarToken").addEventListener("click", gerarToken);
      document.getElementById("aylaCfgAdminSalvar").addEventListener("click", definirAdminPrincipal);
    } catch (e) {
      root.innerHTML = `<div class="ayla-empty">Erro ao carregar: ${esc(e.message)}</div>`;
    }
  }

  async function salvarConfig() {
    const dados = {
      ativa: document.getElementById("aylaCfgAtiva").checked,
      read_only: document.getElementById("aylaCfgReadOnly").checked,
      api_url: document.getElementById("aylaCfgApiUrl").value.trim(),
      gateway_url: document.getElementById("aylaCfgGateway").value.trim(),
      rate_limit: parseInt(document.getElementById("aylaCfgRate").value || "60", 10),
      unidades_globais: Array.from(document.querySelectorAll(".aylaCfgUnidade:checked")).map(c => parseInt(c.value, 10)),
      msg_nao_autorizado: document.getElementById("aylaCfgMsgNeg").value.trim(),
      msg_boas_vindas: document.getElementById("aylaCfgMsgBv").value.trim(),
    };
    try {
      await ayFetch("/ayla-admin/config", { method: "PUT", body: JSON.stringify(dados) });
      toast("Configurações salvas.", "success");
    } catch (e) { toast(e.message || "Erro ao salvar.", "error"); }
  }

  async function gerarToken() {
    if (!confirm("Gerar um novo token invalida o token anterior. O token antigo deixará de funcionar. Continuar?")) return;
    try {
      const r = await ayFetch("/ayla-admin/gerar-token", { method: "POST", body: JSON.stringify({}) });
      const box = document.getElementById("aylaCfgTokenNovo");
      if (box) {
        box.innerHTML = `<div class="ia-aviso ayla-token-novo">
          <strong>Novo token (copie agora, não será mostrado novamente):</strong>
          <div class="ayla-token-line"><code>${esc(r.token)}</code><button class="btn neutral btn-sm" id="aylaCopyToken">Copiar</button></div>
          <p class="ayla-hint">${esc(r.aviso || "")}</p>
        </div>`;
        document.getElementById("aylaCopyToken")?.addEventListener("click", () => {
          navigator.clipboard?.writeText(r.token).then(() => toast("Token copiado.", "success"));
        });
      }
      const masc = document.getElementById("aylaCfgTokenMasc");
      if (masc) masc.textContent = r.token_mascarado || "";
    } catch (e) { toast(e.message || "Erro ao gerar token.", "error"); }
  }

  async function definirAdminPrincipal() {
    const usuario_id = parseInt(document.getElementById("aylaCfgAdminUser").value || "0", 10);
    const telegram_user_id = document.getElementById("aylaCfgAdminTg").value.trim();
    try {
      await ayFetch("/ayla-admin/admin-principal", { method: "POST", body: JSON.stringify({ usuario_id, telegram_user_id }) });
      toast("Administrador principal definido.", "success");
    } catch (e) { toast(e.message || "Erro ao definir administrador.", "error"); }
  }

  // ---- bind global -------------------------------------------------------
  function bindOnce() {
    document.getElementById("aylaBtnTestarConexao")?.addEventListener("click", testarConexao);
    document.getElementById("aylaBtnNovoUsuario")?.addEventListener("click", () => abrirFormUsuario(null));
  }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", bindOnce);
  else bindOnce();

  window.loadAylaDashboard = loadAylaDashboard;
  window.loadAylaUsuarios = loadAylaUsuarios;
  window.loadAylaPermissoes = loadAylaPermissoes;
  window.loadAylaCanaisVoz = loadAylaCanaisVoz;
  window.loadAylaLogs = loadAylaLogs;
  window.loadAylaConfiguracoes = loadAylaConfiguracoes;
})();
