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
    const form = document.getElementById("fichaTecnicaForm");
    if (!formView || !form || document.getElementById("fichaProducaoMeta")) return;
    const anchor = form.querySelector(".ficha-tecnica-form__block:nth-of-type(2)");
    const box = document.createElement("div");
    box.id = "fichaProducaoMeta";
    box.className = "fiscal-producao-panel ficha-vinculo-estoque";
    box.innerHTML = `
      <h3 class="ficha-tecnica-form__section-title">Ligação com estoque e cardápio</h3>
      <ol class="ficha-vinculo-passos">
        <li>Cadastre os <strong>insumos</strong> em <strong>Produtos</strong> (arroz, carne, tomate… o que você compra).</li>
        <li>Aqui monte a <strong>ficha técnica</strong>: ingredientes + quantidades (vincule ao produto do estoque quando puder).</li>
        <li>No <strong>Cardápio → Itens</strong>, tipo prato, escolha <strong>esta ficha</strong> — não precisa cadastrar o prato como produto de estoque.</li>
      </ol>
      <div class="fiscal-producao-grid">
        <label>Prato no estoque <em class="form-hint">(opcional)</em>
          <select id="fichaProdutoFinalId"><option value="">— Só se guarda prato pronto —</option>${optsProdutos()}</select>
          <small class="form-hint">Use só se produz e estoca o prato pronto. Restaurante comum pode deixar em branco.</small>
        </label>
        <label>Rendimento (quantas porções esta receita rende)
          <input type="number" id="fichaRendimentoQtd" step="0.001" min="0.001" placeholder="Ex.: 10" value="1" />
        </label>
        <label>Unidade do rendimento
          <input type="text" id="fichaRendimentoUn" maxlength="20" placeholder="UND, porção…" value="porção" />
        </label>
      </div>
      <p class="form-hint">Salve tudo de uma vez com o botão <strong>Salvar ficha técnica</strong> (não precisa de outro botão aqui).</p>
      <p id="fichaProducaoMetaMsg" class="subtle-text" role="status"></p>`;
    if (anchor) anchor.after(box);
    else form.prepend(box);

    const nomePrato = document.getElementById("fichaTecnicaNomePrato");
    const sel = box.querySelector("#fichaProdutoFinalId");
    nomePrato?.addEventListener("change", () => tentarSugerirProdutoFinal());
    nomePrato?.addEventListener("blur", () => tentarSugerirProdutoFinal());

    function tentarSugerirProdutoFinal() {
      if (!sel || sel.value) return;
      const nome = (nomePrato?.value || "").trim().toLowerCase();
      if (nome.length < 3) return;
      const hit = (produtosCache || []).find((p) => String(p.nome || "").trim().toLowerCase() === nome);
      if (hit) {
        sel.value = String(hit.id);
        const msg = box.querySelector("#fichaProducaoMetaMsg");
        if (msg) msg.textContent = `Sugerimos o produto “${hit.nome}” pelo nome igual ao prato. Confira e salve.`;
      }
    }
  }

  window.preencherFichaProducaoVinculo = function (ficha) {
    const sel = document.getElementById("fichaProdutoFinalId");
    const rq = document.getElementById("fichaRendimentoQtd");
    const ru = document.getElementById("fichaRendimentoUn");
    if (sel && ficha?.produto_final_id) sel.value = String(ficha.produto_final_id);
    if (rq && ficha?.rendimento_quantidade != null) rq.value = String(ficha.rendimento_quantidade);
    if (ru && ficha?.rendimento_unidade) ru.value = String(ficha.rendimento_unidade);
  };

  window.lerFichaProducaoVinculoPayload = function () {
    const sel = document.getElementById("fichaProdutoFinalId");
    const rq = document.getElementById("fichaRendimentoQtd");
    const ru = document.getElementById("fichaRendimentoUn");
    const produto_final_id = Number(sel?.value || 0) || null;
    const rendimento_quantidade = rq?.value !== "" && rq?.value != null ? Number(rq.value) : null;
    const rendimento_unidade = (ru?.value || "").trim() || null;
    return { produto_final_id, rendimento_quantidade, rendimento_unidade };
  };

  window.limparFichaProducaoVinculo = function () {
    const sel = document.getElementById("fichaProdutoFinalId");
    const rq = document.getElementById("fichaRendimentoQtd");
    const ru = document.getElementById("fichaRendimentoUn");
    if (sel) sel.value = "";
    if (rq) rq.value = "1";
    if (ru) ru.value = "porção";
    const msg = document.getElementById("fichaProducaoMetaMsg");
    if (msg) msg.textContent = "";
  };

  window.ensureFichaProducaoFields = function () {
    return loadAux().then(injectFichaProducaoFields);
  };

  document.addEventListener("DOMContentLoaded", () => {
    window.ensureFichaProducaoFields();
  });
})();
