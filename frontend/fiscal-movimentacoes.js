/* Módulo 3 — movimentações e eventos fiscais */
(function () {
  const esc = (s) => (window.escapeHtml ? window.escapeHtml(String(s ?? "")) : String(s ?? ""));

  let metaCache = null;

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

  async function loadMeta() {
    if (metaCache) return metaCache;
    try {
      metaCache = await fFetch("/fiscal/movimentacoes/meta");
    } catch {
      metaCache = { modulo_ativo: false, labels: {} };
    }
    return metaCache;
  }

  window.fiscalMovLabel = function (item) {
    const t = item?.tipo_movimentacao;
    if (!t) return "";
    const labels = metaCache?.labels || {};
    return labels[t] || t;
  };

  window.fiscalMovMotivoHtml = function (item) {
    const base = item.motivo || item.observacao || "--";
    const fiscal = window.fiscalMovLabel(item);
    if (!fiscal) return esc(base);
    let html = esc(base);
    html += ` <span class="fiscal-mov-pill" title="Classificação fiscal">${esc(fiscal)}</span>`;
    if (item.evento_fiscal_status) {
      html += ` <span class="fiscal-mov-pill fiscal-mov-pill--muted">${esc(item.evento_fiscal_status)}</span>`;
    }
    return html;
  };

  function injectSaidaMotivos() {
    const sel = document.getElementById("saidaMotivo");
    if (!sel || sel.dataset.fiscalM3) return;
    const extras = [
      ["AVARIA", "Avaria"],
      ["VENCIMENTO", "Vencimento"],
      ["EXTRAVIO", "Extravio"],
      ["FURTO", "Furto"],
    ];
    extras.forEach(([val, label]) => {
      if (sel.querySelector(`option[value="${val}"]`)) return;
      const opt = document.createElement("option");
      opt.value = val;
      opt.textContent = label;
      sel.appendChild(opt);
    });
    sel.dataset.fiscalM3 = "1";
  }

  function ensureSaidaFiscalPanel() {
    const form = document.getElementById("saidaForm");
    if (!form || document.getElementById("saidaFiscalExtras")) return;

    const wrap = document.createElement("div");
    wrap.id = "saidaFiscalExtras";
    wrap.className = "fiscal-saida-extras hidden";
    wrap.innerHTML = `
      <label>
        Justificativa / motivo detalhado
        <textarea name="motivo_detalhe" id="saidaMotivoDetalhe" rows="2" maxlength="2000" placeholder="Obrigatório para consumo, perdas, avaria, extravio e furto"></textarea>
      </label>
      <label class="fiscal-saida-consumo hidden">
        Setor destino
        <input type="text" name="setor_destino" id="saidaSetorDestino" maxlength="120" />
      </label>
      <div id="saidaFiscalDocWrapper" class="hidden fiscal-saida-doc">
        <p class="form-hint">Unidades de empresas (CNPJs) diferentes — informe documento fiscal ou justificativa detalhada.</p>
        <label>Modelo <input type="text" name="modelo_documento" maxlength="4" placeholder="55" /></label>
        <label>Número documento <input type="text" name="numero_documento" maxlength="60" /></label>
        <label>Chave de acesso <input type="text" name="chave_acesso_documento" maxlength="44" /></label>
      </div>
      <label class="fiscal-saida-furto hidden">
        Nº ocorrência (opcional)
        <input type="text" name="numero_ocorrencia" maxlength="60" />
      </label>
    `;
    const qtdLabel = form.querySelector('input[name="qtd"]')?.closest("label");
    if (qtdLabel) {
      qtdLabel.before(wrap);
    } else {
      form.appendChild(wrap);
    }
  }

  async function updateSaidaFiscalVisibility() {
    const motivo = (document.getElementById("saidaMotivo")?.value || "").toUpperCase();
    const panel = document.getElementById("saidaFiscalExtras");
    if (!panel) return;

    const needJust = ["CONSUMO", "PERDA", "AVARIA", "EXTRAVIO", "FURTO"].includes(motivo);
    panel.classList.toggle("hidden", motivo === "" || motivo === "PRODUCAO");
    panel.querySelector(".fiscal-saida-consumo")?.classList.toggle("hidden", motivo !== "CONSUMO");
    panel.querySelector(".fiscal-saida-furto")?.classList.toggle("hidden", motivo !== "FURTO");

    const docWrap = document.getElementById("saidaFiscalDocWrapper");
    if (motivo === "TRANSFERENCIA" && docWrap) {
      const orig = document.getElementById("saidaOrigemUnidade");
      const dest = document.getElementById("saidaDestinoUnidade");
      let showDoc = false;
      try {
        const unidades = window.state?.unidades || [];
        const mapEmp = {};
        unidades.forEach((u) => {
          if (u.id) mapEmp[u.id] = u.empresa_id ?? u.empresaId ?? null;
        });
        const eO = mapEmp[orig?.value];
        const eD = mapEmp[dest?.value];
        showDoc = eO && eD && String(eO) !== String(eD);
      } catch {
        showDoc = false;
      }
      docWrap.classList.toggle("hidden", !showDoc);
    } else {
      docWrap?.classList.add("hidden");
    }

    const det = document.getElementById("saidaMotivoDetalhe");
    if (det) det.required = needJust;
  }

  window.collectSaidaFiscalExtras = function () {
    const panel = document.getElementById("saidaFiscalExtras");
    if (!panel || panel.classList.contains("hidden")) return {};
    const out = {};
    ["motivo_detalhe", "setor_destino", "numero_documento", "chave_acesso_documento", "modelo_documento", "numero_ocorrencia"].forEach((name) => {
      const el = panel.querySelector(`[name="${name}"]`);
      if (el && el.value && String(el.value).trim()) out[name] = String(el.value).trim();
    });
    return out;
  };

  function injectMovFiltroTipoFiscal() {
    const form = document.getElementById("movFilterForm");
    if (!form || document.getElementById("movFiltroTipoMovimentacao")) return;
    const grid = form.querySelector(".filters-grid");
    if (!grid) return;
    const label = document.createElement("label");
    label.innerHTML = `Tipo fiscal <select id="movFiltroTipoMovimentacao" name="tipo_movimentacao"><option value="">Todos</option></select>`;
    grid.appendChild(label);
    loadMeta().then((meta) => {
      const sel = label.querySelector("select");
      if (!sel || !meta?.tipos_movimentacao) return;
      meta.tipos_movimentacao.forEach((t) => {
        const opt = document.createElement("option");
        opt.value = t;
        opt.textContent = meta.labels?.[t] || t;
        sel.appendChild(opt);
      });
    });
  }

  async function renderFiscalMovimentacoesRelatorioPage() {
    const root = document.getElementById("fiscalMovimentacoesRelatorioRoot");
    if (!root) return;
    root.innerHTML = "<p class=\"subtle-text\">Carregando…</p>";
    try {
      const [perdas, trans] = await Promise.all([
        fFetch("/fiscal/relatorio/perdas"),
        fFetch("/fiscal/relatorio/transferencias"),
      ]);
      const rowPerda = (r) =>
        `<tr><td>${esc(r.data_mov)}</td><td>${esc(r.tipo_movimentacao)}</td><td>${esc(r.produto_nome)}</td><td>${esc(r.unidade_nome)}</td><td>${esc(r.qtd)}</td><td>${esc(r.custo_total ?? "—")}</td></tr>`;
      root.innerHTML = `
        <div class="fiscal-mov-relatorio-grid">
          <div class="table-card">
            <header>Perdas e baixas (${esc(perdas.totais?.quantidade ?? 0)} un — ${esc(perdas.totais?.custo ?? 0)})</header>
            <div class="table-wrapper"><table><thead><tr><th>Data</th><th>Tipo</th><th>Produto</th><th>Unidade</th><th>Qtd</th><th>Custo</th></tr></thead>
            <tbody>${(perdas.itens || []).slice(0, 100).map(rowPerda).join("") || "<tr><td colspan=\"6\">Sem registros</td></tr>"}</tbody></table></div>
          </div>
          <div class="table-card">
            <header>Transferências internas (${(trans.internas || []).length})</header>
            <div class="table-wrapper"><table><thead><tr><th>Data</th><th>Produto</th><th>Origem</th><th>Destino</th><th>Qtd</th></tr></thead>
            <tbody>${(trans.internas || []).slice(0, 50).map((r) => `<tr><td>${esc(r.data_mov)}</td><td>${esc(r.produto_nome)}</td><td>${esc(r.unidade_origem_nome)}</td><td>${esc(r.unidade_destino_nome)}</td><td>${esc(r.qtd)}</td></tr>`).join("") || "<tr><td colspan=\"5\">Sem registros</td></tr>"}</tbody></table></div>
          </div>
          <div class="table-card">
            <header>Operações entre CNPJs (${(trans.entre_cnpjs || []).length})</header>
            <div class="table-wrapper"><table><thead><tr><th>Data</th><th>Produto</th><th>Doc</th><th>Status doc</th><th>Qtd</th></tr></thead>
            <tbody>${(trans.entre_cnpjs || []).slice(0, 50).map((r) => `<tr><td>${esc(r.data_mov)}</td><td>${esc(r.produto_nome)}</td><td>${esc(r.numero_documento || r.chave_acesso_documento || "—")}</td><td>${esc(r.status_documental || "—")}</td><td>${esc(r.qtd)}</td></tr>`).join("") || "<tr><td colspan=\"5\">Sem registros</td></tr>"}</tbody></table></div>
          </div>
        </div>`;
    } catch (e) {
      root.innerHTML = `<p class="subtle-text">${esc(e.message)}</p>`;
    }
  }

  window.loadFiscalMovimentacoesRelatorio = renderFiscalMovimentacoesRelatorioPage;

  function boot() {
    injectSaidaMotivos();
    ensureSaidaFiscalPanel();
    injectMovFiltroTipoFiscal();
    loadMeta();

    document.getElementById("saidaMotivo")?.addEventListener("change", updateSaidaFiscalVisibility);
    document.getElementById("saidaDestinoUnidade")?.addEventListener("change", updateSaidaFiscalVisibility);
    document.getElementById("saidaOrigemUnidade")?.addEventListener("change", updateSaidaFiscalVisibility);
    updateSaidaFiscalVisibility();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
