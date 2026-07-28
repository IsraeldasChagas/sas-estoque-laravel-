/* Módulo 7 — consolidação, apuração e planejamento tributário */
(function () {
  const esc = (s) => (window.escapeHtml ? window.escapeHtml(String(s ?? "")) : String(s ?? ""));

  const TABS = [
    { id: "visao", label: "Visão geral" },
    { id: "entradas", label: "Entradas" },
    { id: "saidas", label: "Saídas" },
    { id: "creditos", label: "Créditos" },
    { id: "estornos", label: "Estornos" },
    { id: "tributos", label: "Tributos a recolher" },
    { id: "estoque", label: "Estoque potencial" },
    { id: "cnpj", label: "Por CNPJ" },
    { id: "apuracao", label: "Apuração" },
    { id: "planejamento", label: "Planejamento" },
  ];

  let empresas = [];
  let activeTab = "visao";
  let lastSim = null;

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

  function brl(n) {
    const v = Number(n);
    if (Number.isNaN(v)) return "—";
    return v.toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
  }

  function filtersFromRoot(root) {
    const emp = root.querySelector("#fm7Empresa")?.value;
    const ini = root.querySelector("#fm7Ini")?.value;
    const fim = root.querySelector("#fm7Fim")?.value;
    const q = new URLSearchParams();
    if (emp) q.set("empresa_id", emp);
    if (ini) q.set("data_ini", ini);
    if (fim) q.set("data_fim", fim);
    const s = q.toString();
    return s ? `?${s}` : "";
  }

  function cardGrid(cards) {
    if (!cards) return "<p class=\"subtle-text\">Sem dados.</p>";
    const entries = [
      ["Entradas", cards.entradas],
      ["Saídas / receita", cards.saidas],
      ["Créditos potenciais", cards.creditos_potenciais],
      ["Créditos validados", cards.creditos_validados],
      ["Estornos potenciais", cards.estornos_potenciais],
      ["Trib. estimado a recolher", cards.tributos_estimados_recolher],
      ["Valor estoque", cards.valor_estoque],
      ["Trib. potencial estoque", cards.tributacao_potencial_estoque],
      ["Pendências", cards.pendencias_count],
    ];
    return `<div class="fiscal-m7-cards fiscal-kpi-row cfg-stats-row">${entries
      .map(([l, v]) => `<div class="fiscal-m7-card fiscal-kpi-card"><span class="fiscal-kpi-card__l">${esc(l)}</span><strong class="fiscal-kpi-card__n">${typeof v === "number" ? brl(v) : esc(v)}</strong></div>`)
      .join("")}</div>`;
  }

  function tableFromRows(rows, cols) {
    if (!rows?.length) return "<p class=\"subtle-text\">Nenhum registro.</p>";
    return `<table class="fiscal-m7-table"><thead><tr>${cols.map((c) => `<th>${esc(c.label)}</th>`).join("")}</tr></thead><tbody>${rows
      .map(
        (r) =>
          `<tr>${cols.map((c) => `<td>${esc(typeof c.key === "function" ? c.key(r) : r[c.key])}</td>`).join("")}</tr>`
      )
      .join("")}</tbody></table>`;
  }

  async function loadTabContent(root) {
    const host = root.querySelector("#fm7Content");
    if (!host) return;
    host.innerHTML = "<p class=\"subtle-text\">Carregando…</p>";
    const fq = filtersFromRoot(root);
    try {
      if (activeTab === "visao") {
        const data = await fFetch(`/fiscal/consolidacao/visao-geral${fq}`);
        host.innerHTML = `${cardGrid(data.cards)}<p class="fiscal-m7-disclaimer">${esc(data.disclaimer)}</p>`;
        if (data.pendencias?.length) {
          host.innerHTML += `<ul>${data.pendencias.map((p) => `<li>${esc(p.mensagem)}</li>`).join("")}</ul>`;
        }
      } else if (activeTab === "entradas") {
        const rows = await fFetch(`/fiscal/consolidacao/entradas${fq}`);
        host.innerHTML = tableFromRows(rows, [
          { label: "Nº", key: "numero" },
          { label: "Data", key: "data_entrada" },
          { label: "Valor", key: (r) => brl(r.valor_total) },
          { label: "Status", key: "status" },
        ]);
      } else if (activeTab === "saidas") {
        const rows = await fFetch(`/fiscal/consolidacao/saidas${fq}`);
        host.innerHTML = tableFromRows(rows, [
          { label: "ID", key: "id" },
          { label: "Data", key: "data_venda" },
          { label: "Líquido", key: (r) => brl(r.valor_liquido) },
          { label: "Status", key: "status" },
        ]);
      } else if (activeTab === "creditos") {
        const rows = await fFetch(`/fiscal/consolidacao/creditos${fq}`);
        host.innerHTML = tableFromRows(rows, [
          { label: "Tributo", key: "tipo_tributo" },
          { label: "Potencial", key: (r) => brl(r.valor_potencial) },
          { label: "Status", key: "status" },
        ]);
      } else if (activeTab === "estornos") {
        const rows = await fFetch(`/fiscal/consolidacao/estornos${fq}`);
        host.innerHTML = tableFromRows(rows, [
          { label: "Tipo", key: (r) => r.tipo_evento || r.tipo || "—" },
          { label: "Valor", key: (r) => brl(r.valor_potencial) },
          { label: "Status", key: "status" },
        ]);
      } else if (activeTab === "tributos") {
        const data = await fFetch(`/fiscal/consolidacao/tributos-recolher${fq}`);
        host.innerHTML = `${cardGrid(data.resumo)}${tableFromRows(data.por_tributo, [
          { label: "Tributo", key: "tributo" },
          { label: "Registrado", key: (r) => brl(r.valor_registrado) },
        ])}`;
      } else if (activeTab === "estoque") {
        const rows = await fFetch(`/fiscal/consolidacao/estoque-potencial${fq}`);
        host.innerHTML = tableFromRows(rows, [
          { label: "Produto", key: "produto_nome" },
          { label: "Qtd", key: "quantidade" },
          { label: "Valor", key: (r) => brl(r.valor_estoque) },
          { label: "Trib. pot.", key: (r) => brl(r.tributacao_potencial_estimada) },
        ]);
      } else if (activeTab === "cnpj") {
        const rows = await fFetch(`/fiscal/consolidacao/por-cnpj${fq}`);
        host.innerHTML = rows
          .map(
            (r) =>
              `<div class="fiscal-m7-cenario"><h4>${esc(r.razao_social)} (${esc(r.cnpj)})</h4>${cardGrid(r.resumo)}</div>`
          )
          .join("");
      } else if (activeTab === "apuracao") {
        host.innerHTML = `
          <div class="fiscal-m7-filters">
            <label>Empresa<select id="fm7ApEmp">${empresas.map((e) => `<option value="${esc(e.id)}">${esc(e.razao_social)}</option>`).join("")}</select></label>
            <label>Início<input type="date" id="fm7ApIni" /></label>
            <label>Fim<input type="date" id="fm7ApFim" /></label>
            <button type="button" class="btn primary" id="fm7ApCalc">Calcular apuração</button>
          </div>
          <div id="fm7ApRes"></div>
          <div id="fm7ApList"></div>`;
        const list = await fFetch(`/fiscal/apuracao${root.querySelector("#fm7Empresa")?.value ? `?empresa_id=${root.querySelector("#fm7Empresa").value}` : ""}`);
        root.querySelector("#fm7ApList").innerHTML = tableFromRows(list, [
          { label: "ID", key: "id" },
          { label: "Período", key: (r) => `${r.periodo_inicio} → ${r.periodo_fim}` },
          { label: "Estimado", key: (r) => brl(r.total_estimado) },
          { label: "Status", key: "status" },
        ]);
        root.querySelector("#fm7ApCalc")?.addEventListener("click", async () => {
          const msg = root.querySelector("#fm7ApRes");
          try {
            const res = await fFetch("/fiscal/apuracao/calcular", {
              method: "POST",
              body: JSON.stringify({
                empresa_id: Number(root.querySelector("#fm7ApEmp").value),
                periodo_inicio: root.querySelector("#fm7ApIni").value,
                periodo_fim: root.querySelector("#fm7ApFim").value,
              }),
            });
            msg.textContent = `Apuração #${res.apuracao_id} — estimado ${brl(res.totais?.estimado)}`;
            await loadTabContent(root);
          } catch (e) {
            msg.textContent = e.message;
          }
        });
      } else if (activeTab === "planejamento") {
        host.innerHTML = `
          <p class="fiscal-m7-disclaimer">Simulador — não altera estoque, compras ou vendas.</p>
          <div class="fiscal-m7-sim-grid">
            <label>Empresa C (compra)<select id="fm7SimC">${empresas.map((e) => `<option value="${esc(e.id)}">${esc(e.razao_social)}</option>`).join("")}</select></label>
            <label>Empresa B<select id="fm7SimB">${empresas.map((e) => `<option value="${esc(e.id)}">${esc(e.razao_social)}</option>`).join("")}</select></label>
            <label>Qtd<input type="number" id="fm7SimQ" step="0.001" value="1" /></label>
            <label>Preço compra<input type="number" id="fm7SimPc" step="0.01" /></label>
            <label>Preço venda<input type="number" id="fm7SimPv" step="0.01" /></label>
            <label>Custos extras<input type="number" id="fm7SimEx" step="0.01" value="0" /></label>
          </div>
          <button type="button" class="btn primary" id="fm7SimRun">Simular 3 cenários</button>
          <div id="fm7SimOut"></div>`;
        root.querySelector("#fm7SimRun")?.addEventListener("click", async () => {
          const out = root.querySelector("#fm7SimOut");
          out.innerHTML = "Calculando…";
          try {
            lastSim = await fFetch("/fiscal/planejamento/simular", {
              method: "POST",
              body: JSON.stringify({
                empresa_c_id: Number(root.querySelector("#fm7SimC").value),
                empresa_b_id: Number(root.querySelector("#fm7SimB").value),
                quantidade: Number(root.querySelector("#fm7SimQ").value),
                preco_compra: Number(root.querySelector("#fm7SimPc").value),
                preco_venda: Number(root.querySelector("#fm7SimPv").value),
                custos_adicionais: Number(root.querySelector("#fm7SimEx").value),
              }),
            });
            const best = lastSim.comparacao?.menor_carga_estimada_cenario_id;
            out.innerHTML =
              (lastSim.cenarios || [])
                .map((c) => {
                  const cls = c.id === best ? "fiscal-m7-cenario fiscal-m7-cenario--best" : "fiscal-m7-cenario";
                  if (c.erro) return `<div class="${cls}"><h4>${esc(c.nome)}</h4><p>${esc(c.erro)}</p></div>`;
                  return `<div class="${cls}"><h4>${esc(c.nome)}</h4>
                    <p>Receita ${brl(c.receita_estimada)} · Carga ${brl(c.carga_tributaria_total)} · Margem ${brl(c.margem_estimada)}</p>
                    <p class="subtle-text">Entrada ${brl(c.tributos_entrada)} · Venda ${brl(c.tributos_venda)} · Op. entre CNPJs ${brl(c.tributos_intermediarios)}</p></div>`;
                })
                .join("") + `<p class="fiscal-m7-disclaimer">${esc(lastSim.comparacao?.disclaimer)}</p>`;
          } catch (e) {
            out.textContent = e.message;
          }
        });
      }
    } catch (e) {
      host.innerHTML = `<p class="subtle-text">${esc(e.message)}</p>`;
    }
  }

  async function renderPage() {
    const root = document.getElementById("fiscalPainelModulo07Root");
    if (!root) return;
    try {
      empresas = await fFetch("/fiscal/empresas");
    } catch {
      empresas = [];
    }
    const today = new Date();
    const ini = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().slice(0, 10);
    const fim = today.toISOString().slice(0, 10);

    root.innerHTML = `
      <div class="fiscal-page fiscal-m7-wrap">
        <div class="fiscal-m7-filters fiscal-toolbar">
          <label>Empresa (filtro)<select id="fm7Empresa"><option value="">Todas</option>${empresas.map((e) => `<option value="${esc(e.id)}">${esc(e.razao_social)}</option>`).join("")}</select></label>
          <label>De<input type="date" id="fm7Ini" value="${esc(ini)}" /></label>
          <label>Até<input type="date" id="fm7Fim" value="${esc(fim)}" /></label>
          <div class="fiscal-toolbar__actions"><button type="button" class="btn primary" id="fm7Refresh">Atualizar</button></div>
        </div>
        <nav class="fiscal-m7-tabs" id="fm7Tabs">${TABS.map((t) => `<button type="button" data-tab="${t.id}" class="${t.id === activeTab ? "active" : ""}">${esc(t.label)}</button>`).join("")}</nav>
        <div id="fm7Content"></div>
      </div>`;

    root.querySelector("#fm7Refresh")?.addEventListener("click", () => loadTabContent(root));
    root.querySelectorAll("#fm7Tabs button").forEach((btn) => {
      btn.addEventListener("click", () => {
        activeTab = btn.dataset.tab;
        root.querySelectorAll("#fm7Tabs button").forEach((b) => b.classList.toggle("active", b.dataset.tab === activeTab));
        loadTabContent(root);
      });
    });
    await loadTabContent(root);
  }

  window.loadFiscalPainelModulo07 = renderPage;
})();
