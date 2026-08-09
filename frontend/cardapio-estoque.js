/**
 * Estoque do Cardápio (Estoque B) — abastecimento, saldos e movimentações.
 */
(function () {
  const ce = {
    unidadeId: null,
    itens: [],
    movs: [],
  };

  function apiBase() {
    return (typeof API_BASE !== "undefined" && API_BASE) || "/api";
  }

  function headers() {
    const h = { Accept: "application/json", "Content-Type": "application/json" };
    try {
      const u = typeof currentUser !== "undefined" ? currentUser : null;
      if (u?.id) h["X-Usuario-Id"] = String(u.id);
    } catch (_) {}
    return h;
  }

  async function api(path, opts = {}) {
    const res = await fetch(`${apiBase()}${path}`, {
      ...opts,
      headers: { ...headers(), ...(opts.headers || {}) },
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.error || data.message || `Erro ${res.status}`);
    return data;
  }

  function root() {
    return document.getElementById("cardapioEstoqueRoot");
  }

  function appState() {
    try {
      return typeof window.state !== "undefined" ? window.state : null;
    } catch (_) {
      return null;
    }
  }

  function listarUnidades() {
    const st = appState();
    if (st && Array.isArray(st.unidades) && st.unidades.length) {
      return st.unidades;
    }
    return [];
  }

  async function garantirUnidades() {
    let list = listarUnidades();
    if (list.length) return list;
    try {
      if (typeof loadUnidades === "function") {
        await loadUnidades(false);
        list = listarUnidades();
        if (list.length) return list;
      }
    } catch (_) {}
    try {
      const data = await api("/unidades?todas=1");
      const arr = Array.isArray(data) ? data : data?.data || [];
      const st = appState();
      if (st) st.unidades = arr;
      return arr;
    } catch (_) {
      return [];
    }
  }

  function unidadesOptionsHtml(selected) {
    return listarUnidades()
      .map(
        (u) =>
          `<option value="${u.id}" ${String(u.id) === String(selected) ? "selected" : ""}>${escapeHtml(
            u.nome || u.name || `#${u.id}`
          )}</option>`
      )
      .join("");
  }

  function escapeHtml(s) {
    return String(s ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function fmtQtd(n) {
    const v = Number(n) || 0;
    return v.toLocaleString("pt-BR", { maximumFractionDigits: 3 });
  }

  async function load() {
    const el = root();
    if (!el) return;
    el.innerHTML = `<div class="orc-root"><p>Carregando estoque do cardápio…</p></div>`;

    const lista = await garantirUnidades();
    if (!ce.unidadeId && lista[0]) {
      ce.unidadeId = lista[0].id;
    }
    if (!ce.unidadeId) {
      el.innerHTML = `<div class="orc-root">
        <h2>Estoque do Cardápio</h2>
        <p>Nenhuma unidade encontrada para o seu usuário.</p>
        <p>Vá em <strong>Unidades</strong> (menu lateral, perto de Produtos / Consulta Estoque) e cadastre ou ative uma unidade. Depois volte aqui.</p>
        <button type="button" class="btn primary" id="ceIrUnidades">Ir para Unidades</button>
      </div>`;
      el.querySelector("#ceIrUnidades")?.addEventListener("click", () => {
        if (typeof navigateTo === "function") navigateTo("unidades");
      });
      return;
    }

    try {
      const [saldo, mov] = await Promise.all([
        api(`/cardapio-estoque?unidade_id=${ce.unidadeId}`),
        api(`/cardapio-estoque/movimentacoes?unidade_id=${ce.unidadeId}&limit=80`),
      ]);
      ce.itens = saldo.itens || [];
      ce.movs = mov.movimentacoes || [];
      render();
    } catch (e) {
      el.innerHTML = `<div class="orc-root"><p style="color:#b91c1c">${escapeHtml(e.message)}</p>
        <p>Se o módulo estiver indisponível no servidor, rode: <code>php artisan migrate</code></p>
        <button type="button" class="btn" id="ceRefreshErr">Tentar de novo</button></div>`;
      el.querySelector("#ceRefreshErr")?.addEventListener("click", () => load());
    }
  }

  function render() {
    const el = root();
    if (!el) return;
    const sem = ce.itens.filter((i) => i.sem_estoque).length;
    const rows = ce.itens
      .map((i) => {
        const badge = i.sem_estoque
          ? `<span style="color:#b91c1c;font-weight:600">Zerado</span>`
          : i.abaixo_minimo
            ? `<span style="color:#a16207;font-weight:600">Baixo</span>`
            : `<span style="color:#15803d">OK</span>`;
        return `<tr>
          <td>${escapeHtml(i.nome)}</td>
          <td>${escapeHtml(i.categoria_nome || "—")}</td>
          <td>${escapeHtml(i.tipo_venda || "—")}</td>
          <td style="text-align:right;font-weight:700">${fmtQtd(i.quantidade)}</td>
          <td style="text-align:right">${fmtQtd(i.estoque_minimo)}</td>
          <td>${badge}</td>
          <td>
            <button type="button" class="btn primary" data-ce-abastecer="${i.dlv_produto_id}" data-ce-nome="${escapeHtml(i.nome)}">Abastecer</button>
            <button type="button" class="btn" data-ce-ajustar="${i.dlv_produto_id}" data-ce-qtd="${i.quantidade}" data-ce-min="${i.estoque_minimo}" data-ce-ctrl="${i.controla_estoque_cardapio ? 1 : 0}">Ajustar</button>
          </td>
        </tr>`;
      })
      .join("");

    const movRows = ce.movs
      .map(
        (m) => `<tr>
        <td>${escapeHtml((m.created_at || "").replace("T", " ").slice(0, 19))}</td>
        <td>${escapeHtml(m.produto_nome)}</td>
        <td>${escapeHtml(m.tipo)}</td>
        <td>${escapeHtml(m.origem)}</td>
        <td style="text-align:right">${fmtQtd(m.quantidade)}</td>
        <td style="text-align:right">${fmtQtd(m.saldo_apos)}</td>
        <td>${escapeHtml(m.motivo || "—")}</td>
      </tr>`
      )
      .join("");

    el.innerHTML = `
      <div class="actions" style="display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-end;justify-content:space-between">
        <div>
          <h2 style="margin:0 0 .35rem">Estoque do Cardápio</h2>
          <p style="margin:0;color:#64748b;max-width:42rem">Controle de porções/itens à venda. O estoque administrativo (compras/lotes) continua separado. Ao vender no PDV, mesa ou delivery, a baixa acontece aqui.</p>
        </div>
        <div style="display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap">
          <label style="display:flex;flex-direction:column;gap:.25rem;font-size:.85rem">
            Unidade
            <select id="ceUnidade" style="min-width:220px;padding:.55rem .7rem">${unidadesOptionsHtml(ce.unidadeId)}</select>
          </label>
          <button type="button" class="btn" id="ceRefresh">Atualizar</button>
        </div>
      </div>
      <div style="display:flex;gap:1rem;flex-wrap:wrap;margin:1rem 0">
        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:.85rem 1.1rem;min-width:140px">
          <div style="font-size:.8rem;color:#166534">Itens</div>
          <div style="font-size:1.4rem;font-weight:700">${ce.itens.length}</div>
        </div>
        <div style="background:${sem ? "#fef2f2" : "#f8fafc"};border:1px solid ${sem ? "#fecaca" : "#e2e8f0"};border-radius:8px;padding:.85rem 1.1rem;min-width:140px">
          <div style="font-size:.8rem;color:#64748b">Zerados</div>
          <div style="font-size:1.4rem;font-weight:700;color:${sem ? "#b91c1c" : "#0f172a"}">${sem}</div>
        </div>
      </div>
      <div class="table-card">
        <header>Saldos para venda</header>
        <div class="table-wrapper">
          <table>
            <thead><tr>
              <th>Item</th><th>Categoria</th><th>Tipo</th><th>Saldo</th><th>Mínimo</th><th>Status</th><th>Ações</th>
            </tr></thead>
            <tbody>${rows || `<tr><td colspan="7" style="text-align:center;color:#64748b">Nenhum item ativo no cardápio desta unidade. Cadastre em Cardápio → Itens.</td></tr>`}</tbody>
          </table>
        </div>
      </div>
      <div class="table-card" style="margin-top:1.25rem">
        <header>Últimas movimentações</header>
        <div class="table-wrapper">
          <table>
            <thead><tr>
              <th>Data</th><th>Item</th><th>Tipo</th><th>Origem</th><th>Qtd</th><th>Saldo após</th><th>Motivo</th>
            </tr></thead>
            <tbody>${movRows || `<tr><td colspan="7" style="text-align:center;color:#64748b">Sem movimentações ainda.</td></tr>`}</tbody>
          </table>
        </div>
      </div>
    `;

    el.querySelector("#ceUnidade")?.addEventListener("change", (e) => {
      ce.unidadeId = Number(e.target.value) || null;
      load();
    });
    el.querySelector("#ceRefresh")?.addEventListener("click", () => load());
    el.querySelectorAll("[data-ce-abastecer]").forEach((btn) => {
      btn.addEventListener("click", () => abastecer(Number(btn.dataset.ceAbastecer), btn.dataset.ceNome || ""));
    });
    el.querySelectorAll("[data-ce-ajustar]").forEach((btn) => {
      btn.addEventListener("click", () =>
        ajustar(
          Number(btn.dataset.ceAjustar),
          Number(btn.dataset.ceQtd || 0),
          Number(btn.dataset.ceMin || 0),
          btn.dataset.ceCtrl === "1"
        )
      );
    });
  }

  async function abastecer(dlvId, nome) {
    const qtdStr = prompt(`Abastecer "${nome}"\nQuantidade a entrar no estoque do cardápio:`, "10");
    if (qtdStr == null) return;
    const qtd = Number(String(qtdStr).replace(",", "."));
    if (!(qtd > 0)) {
      showToast?.("Quantidade inválida.", "error");
      return;
    }
    try {
      await api("/cardapio-estoque/entrada", {
        method: "POST",
        body: JSON.stringify({
          unidade_id: ce.unidadeId,
          dlv_produto_id: dlvId,
          quantidade: qtd,
          motivo: "Abastecimento manual",
        }),
      });
      showToast?.("Estoque do cardápio abastecido.", "success");
      load();
    } catch (e) {
      showToast?.(e.message, "error");
    }
  }

  async function ajustar(dlvId, qtdAtual, minAtual, controla) {
    const qtdStr = prompt("Novo saldo (inventário):", String(qtdAtual));
    if (qtdStr == null) return;
    const qtd = Number(String(qtdStr).replace(",", "."));
    if (Number.isNaN(qtd) || qtd < 0) {
      showToast?.("Saldo inválido.", "error");
      return;
    }
    const minStr = prompt("Estoque mínimo (alerta):", String(minAtual));
    if (minStr == null) return;
    const min = Number(String(minStr).replace(",", "."));
    try {
      await api("/cardapio-estoque/ajuste", {
        method: "POST",
        body: JSON.stringify({
          unidade_id: ce.unidadeId,
          dlv_produto_id: dlvId,
          quantidade: qtd,
          estoque_minimo: Number.isNaN(min) ? 0 : min,
          controla_estoque_cardapio: controla,
          motivo: "Ajuste inventário",
        }),
      });
      showToast?.("Saldo ajustado.", "success");
      load();
    } catch (e) {
      showToast?.(e.message, "error");
    }
  }

  window.loadCardapioEstoque = load;
})();
