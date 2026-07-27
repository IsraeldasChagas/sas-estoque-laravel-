/* Módulo 5 — Produção fiscal */
(function () {
  const esc = (s) => (window.escapeHtml ? window.escapeHtml(String(s ?? "")) : String(s ?? ""));

  async function fFetch(path, opts = {}) {
    const base = window.API_BASE || "/api";
    const headers = { "Content-Type": "application/json", ...(opts.headers || {}) };
    const uid = window.getUser?.()?.id;
    if (uid) headers["X-Usuario-Id"] = String(uid);
    const res = await fetch(`${base}${path}`, { ...opts, headers });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.error || data.message || `HTTP ${res.status}`);
    return data;
  }

  let unidadesCache = [];
  let produtosCache = [];
  let fichasCache = [];

  async function loadAux() {
    if (!unidadesCache.length) {
      try {
        unidadesCache = await fFetch("/unidades");
      } catch {
        unidadesCache = window.state?.unidades || [];
      }
    }
    if (!produtosCache.length) {
      try {
        produtosCache = await fFetch("/produtos");
      } catch {
        produtosCache = window.state?.produtos || [];
      }
    }
    if (!fichasCache.length) {
      try {
        fichasCache = await fFetch("/fichas-tecnicas");
      } catch {
        fichasCache = [];
      }
    }
  }

  function optsUnidades() {
    return (unidadesCache || [])
      .map((u) => `<option value="${esc(u.id)}">${esc(u.nome)}</option>`)
      .join("");
  }

  function optsProdutos() {
    return (produtosCache || [])
      .map((p) => `<option value="${esc(p.id)}">${esc(p.nome)}</option>`)
      .join("");
  }

  function optsFichas() {
    return (fichasCache || [])
      .map((f) => `<option value="${esc(f.id)}">${esc(f.nome_prato)} (v${esc(f.versao || 1)})</option>`)
      .join("");
  }

  async function renderFiscalProducaoPage() {
    const root = document.getElementById("fiscalProducaoRoot");
    if (!root) return;
    root.innerHTML = "<p class=\"subtle-text\">Carregando…</p>";
    await loadAux();
    const lista = await fFetch("/fiscal/producoes").catch(() => []);

    root.innerHTML = `
      <div class="fiscal-producao-wrap">
        <div class="fiscal-producao-panel">
          <h3>Nova produção</h3>
          <div class="fiscal-producao-grid">
            <label>Unidade <select id="fpUnidade"><option value="">—</option>${optsUnidades()}</select></label>
            <label>Ficha técnica <select id="fpFicha"><option value="">—</option>${optsFichas()}</select></label>
            <label>Produto final <select id="fpProdutoFinal"><option value="">—</option>${optsProdutos()}</select></label>
            <label>Qtd a produzir <input type="number" id="fpQtd" step="0.001" min="0.001" /></label>
          </div>
          <p class="form-hint">Ingredientes da ficha precisam ter <strong>produto_id</strong> (vincule na ficha ou use ID de produto existente).</p>
          <button type="button" class="btn primary" id="fpCriarBtn">Criar produção</button>
          <p id="fpCriarMsg" class="subtle-text"></p>
        </div>
        <div class="table-card">
          <header>Produções recentes</header>
          <div class="table-wrapper">
            <table><thead><tr><th>ID</th><th>Produto</th><th>Unidade</th><th>Qtd</th><th>Status</th><th>Custo un.</th><th></th></tr></thead>
            <tbody id="fpListaBody">${(lista || [])
              .map(
                (p) => `<tr>
                  <td>${esc(p.id)}</td>
                  <td>${esc(p.produto_final_nome)}</td>
                  <td>${esc(p.unidade_nome)}</td>
                  <td>${esc(p.quantidade_produzida ?? p.quantidade_planejada)}</td>
                  <td>${esc(p.status)}</td>
                  <td>${esc(p.custo_unitario ?? "—")}</td>
                  <td>${p.status !== "finalizada" ? `<button type="button" class="btn btn-sm" data-fp-finalizar="${esc(p.id)}">Finalizar</button>` : "—"}</td>
                </tr>`
              )
              .join("") || "<tr><td colspan=\"7\">Nenhuma produção</td></tr>"}
            </tbody></table>
          </div>
        </div>
        <div id="fpSimPanel" class="fiscal-producao-panel hidden"></div>
      </div>`;

    root.querySelector("#fpFicha")?.addEventListener("change", () => {
      const f = fichasCache.find((x) => String(x.id) === String(root.querySelector("#fpFicha").value));
      if (f?.produto_final_id) root.querySelector("#fpProdutoFinal").value = String(f.produto_final_id);
    });

    root.querySelector("#fpCriarBtn")?.addEventListener("click", async () => {
      const msg = root.querySelector("#fpCriarMsg");
      msg.textContent = "";
      try {
        const body = {
          unidade_id: Number(root.querySelector("#fpUnidade").value),
          ficha_tecnica_id: Number(root.querySelector("#fpFicha").value),
          produto_final_id: Number(root.querySelector("#fpProdutoFinal").value) || undefined,
          quantidade_planejada: Number(root.querySelector("#fpQtd").value),
        };
        const r = await fFetch("/fiscal/producoes", { method: "POST", body: JSON.stringify(body) });
        msg.textContent = `Produção #${r.id} criada.`;
        await renderFiscalProducaoPage();
      } catch (e) {
        msg.textContent = e.message;
        msg.className = "fiscal-producao-alert";
      }
    });

    root.querySelectorAll("[data-fp-finalizar]").forEach((btn) => {
      btn.addEventListener("click", () => finalizarProducao(Number(btn.getAttribute("data-fp-finalizar"))));
    });
  }

  async function finalizarProducao(id) {
    const panel = document.getElementById("fpSimPanel");
    if (!panel) return;
    panel.classList.remove("hidden");
    panel.innerHTML = "<p>Simulando…</p>";
    try {
      const sim = await fFetch(`/fiscal/producoes/${id}/simular`, { method: "POST", body: "{}" });
      const rows = (sim.insumos || [])
        .map(
          (i) => `<tr>
            <td>${esc(i.produto_nome)}</td>
            <td>${esc(i.quantidade_prevista)}</td>
            <td>${esc(i.quantidade_real)}</td>
            <td>${esc(i.disponivel)}</td>
            <td>${esc(i.faltante)}</td>
          </tr>`
        )
        .join("");
      panel.innerHTML = `
        <h3>Produção #${esc(id)} — conferência</h3>
        <table class="fiscal-producao-sim-table"><thead><tr><th>Insumo</th><th>Previsto</th><th>Real</th><th>Saldo</th><th>Faltante</th></tr></thead><tbody>${rows}</tbody></table>
        <p class="${sim.pode_finalizar ? "fiscal-producao-ok" : "fiscal-producao-alert"}">${sim.pode_finalizar ? "Saldo OK — pode finalizar." : "Saldo insuficiente: " + esc((sim.faltas || []).join(", "))}</p>
        <button type="button" class="btn primary" id="fpConfirmFinalizar" ${sim.pode_finalizar ? "" : "disabled"}>Confirmar e baixar insumos</button>`;
      panel.querySelector("#fpConfirmFinalizar")?.addEventListener("click", async () => {
        try {
          await fFetch(`/fiscal/producoes/${id}/finalizar`, { method: "POST", body: "{}" });
          window.showToast?.("Produção finalizada.", "success");
          await renderFiscalProducaoPage();
          panel.classList.add("hidden");
        } catch (e) {
          window.showToast?.(e.message, "error");
        }
      });
    } catch (e) {
      panel.innerHTML = `<p class="fiscal-producao-alert">${esc(e.message)}</p>`;
    }
  }

  window.loadFiscalProducao = renderFiscalProducaoPage;

  /** Campos de produção no formulário de ficha (painel opcional) */
  function injectFichaProducaoFields() {
    const formView = document.getElementById("fichaTecnicaFormView");
    if (!formView || document.getElementById("fichaProducaoMeta")) return;
    const box = document.createElement("div");
    box.id = "fichaProducaoMeta";
    box.className = "fiscal-producao-panel";
    box.innerHTML = `
      <h3>Dados para produção (fiscal)</h3>
      <div class="fiscal-producao-grid">
        <label>Produto final (estoque) <select id="fichaProdutoFinalId"><option value="">—</option>${optsProdutos()}</select></label>
        <label>Rendimento (qtd) <input type="number" id="fichaRendimentoQtd" step="0.001" min="0.001" placeholder="10" /></label>
        <label>Unidade rend. <input type="text" id="fichaRendimentoUn" maxlength="20" placeholder="UND" /></label>
      </div>
      <button type="button" class="btn" id="fichaSalvarProducaoMeta">Salvar vínculo produção</button>
      <p id="fichaProducaoMetaMsg" class="subtle-text"></p>`;
    formView.querySelector("form")?.prepend(box);
    box.querySelector("#fichaSalvarProducaoMeta")?.addEventListener("click", async () => {
      const editId = document.getElementById("fichaTecnicaEditId")?.value;
      if (!editId) {
        box.querySelector("#fichaProducaoMetaMsg").textContent = "Salve a ficha antes de vincular produção.";
        return;
      }
      try {
        await fFetch(`/fichas-tecnicas/${editId}/producao`, {
          method: "PATCH",
          body: JSON.stringify({
            produto_final_id: Number(box.querySelector("#fichaProdutoFinalId").value) || null,
            rendimento_quantidade: Number(box.querySelector("#fichaRendimentoQtd").value) || null,
            rendimento_unidade: box.querySelector("#fichaRendimentoUn").value || null,
          }),
        });
        box.querySelector("#fichaProducaoMetaMsg").textContent = "Vínculo salvo.";
      } catch (e) {
        box.querySelector("#fichaProducaoMetaMsg").textContent = e.message;
      }
    });
  }

  document.addEventListener("DOMContentLoaded", () => {
    loadAux().then(injectFichaProducaoFields);
  });
})();
