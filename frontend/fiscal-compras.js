/* Módulo 2 — camada fiscal em listas de compras */
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

  function fmtMoney(v) {
    const n = Number(v);
    if (!Number.isFinite(n)) return "—";
    return n.toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
  }

  function fmtCnpj(d) {
    const dgt = String(d || "").replace(/\D/g, "");
    if (dgt.length !== 14) return d || "—";
    return dgt.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, "$1.$2.$3/$4-$5");
  }

  let empresasCache = [];

  async function loadEmpresas() {
    if (empresasCache.length) return empresasCache;
    try {
      empresasCache = await fFetch("/fiscal/empresas");
    } catch {
      empresasCache = [];
    }
    return empresasCache;
  }

  function statusFiscalLabel(s) {
    const map = {
      pendente: "Pendente",
      com_alerta: "Com alerta",
      validada: "Validada",
      processada: "Processada",
    };
    return map[s] || s || "—";
  }

  function tributosResumoHtml(t) {
    if (!t) return "<p class=\"subtle-text\">Sem tributos informados na NF.</p>";
    const lines = [
      ["ICMS", t.icms],
      ["ICMS-ST", t.icms_st],
      ["PIS", t.pis],
      ["COFINS", t.cofins],
      ["IPI", t.ipi],
    ].filter(([, v]) => Number(v) > 0);
    if (!lines.length) return "<p class=\"subtle-text\">Sem tributos destacados.</p>";
    return `<ul class="fiscal-compra-tributos">${lines.map(([l, v]) => `<li><span>${l}</span><strong>${fmtMoney(v)}</strong></li>`).join("")}</ul>`;
  }

  function buildItensFromLista(lista, bundle) {
    const existentes = bundle?.itens_nota || [];
    const byProd = {};
    existentes.forEach((i) => {
      if (i.produto_id) byProd[i.produto_id] = i;
    });
    const comprados = (lista?.itens || []).filter((it) => Number(it.quantidade_comprada) > 0);
    return comprados.map((it) => {
      const ex = byProd[it.produto_id] || {};
      return {
        produto_id: it.produto_id,
        lista_item_id: it.id,
        quantidade: ex.quantidade ?? it.quantidade_comprada,
        unidade_medida: ex.unidade_medida ?? it.unidade,
        valor_unitario: ex.valor_unitario ?? it.valor_unitario ?? it.preco_unitario,
        valor_total_item: ex.valor_total_item ?? it.valor_total,
        ncm: ex.ncm ?? "",
        cfop: ex.cfop ?? "",
        cst_icms: ex.cst_icms ?? "",
        csosn: ex.csosn ?? "",
        valor_icms: ex.valor_icms ?? "",
        valor_pis: ex.valor_pis ?? "",
        valor_cofins: ex.valor_cofins ?? "",
        valor_ipi: ex.valor_ipi ?? "",
        produto_nome: it.produto_nome,
      };
    });
  }

  async function renderFiscalCompraPanel(lista) {
    const root = document.getElementById("fiscalCompraPanel");
    if (!root) return;
    if (!lista?.id) {
      root.innerHTML = "";
      root.classList.add("hidden");
      return;
    }
    root.classList.remove("hidden");
    await loadEmpresas();
    let bundle;
    try {
      bundle = await fFetch(`/fiscal/compras/listas/${lista.id}`);
    } catch (e) {
      root.innerHTML = `<div class="fiscal-compra-card"><p class="subtle-text">Fiscal: ${esc(e.message)}</p></div>`;
      return;
    }
    const emp = bundle.empresa;
    const nota = bundle.nota;
    const podeEditar = ["ADMIN", "ADMINISTRADOR", "GERENTE"].includes(
      String(window.getUser?.()?.perfil || "").toUpperCase()
    );
    const empresaOpts = empresasCache
      .map(
        (e) =>
          `<option value="${e.id}" ${String(bundle.empresa_id || bundle.empresa_resolvida_id) === String(e.id) ? "selected" : ""}>${esc(e.razao_social)} — ${esc(fmtCnpj(e.cnpj))}</option>`
      )
      .join("");

    const itensRows = buildItensFromLista(lista, bundle)
      .map(
        (it, idx) => `<tr data-idx="${idx}">
        <td>${esc(it.produto_nome || it.produto_id)}</td>
        <td><input class="fiscal-compra-inp" data-f="ncm" value="${esc(it.ncm)}" ${podeEditar ? "" : "readonly"} /></td>
        <td><input class="fiscal-compra-inp" data-f="cfop" value="${esc(it.cfop)}" ${podeEditar ? "" : "readonly"} /></td>
        <td><input class="fiscal-compra-inp fiscal-compra-inp--num" data-f="valor_icms" value="${esc(it.valor_icms)}" ${podeEditar ? "" : "readonly"} /></td>
        <td><input class="fiscal-compra-inp fiscal-compra-inp--num" data-f="valor_pis" value="${esc(it.valor_pis)}" ${podeEditar ? "" : "readonly"} /></td>
        <td><input class="fiscal-compra-inp fiscal-compra-inp--num" data-f="valor_cofins" value="${esc(it.valor_cofins)}" ${podeEditar ? "" : "readonly"} /></td>
      </tr>`
      )
      .join("");

    root.innerHTML = `
      <div class="fiscal-compra-card table-card">
        <header><h3>Dados fiscais da compra</h3>
          <span class="fiscal-badge fiscal-badge--${bundle.status_fiscal === "com_alerta" ? "warn" : bundle.status_fiscal === "processada" ? "ok" : "pend"}">${esc(statusFiscalLabel(bundle.status_fiscal))}</span>
        </header>
        <div class="fiscal-compra-card__body">
          <div class="fiscal-compra-grid">
            <label>Empresa / CNPJ comprador
              <select id="fiscalCompraEmpresaSelect" ${podeEditar ? "" : "disabled"}>
                <option value="">— Herdar da unidade —</option>${empresaOpts}
              </select>
            </label>
            <div class="fiscal-compra-readonly">
              <span class="fiscal-compra-readonly__label">Regime / IE</span>
              <span>${esc(emp?.regime_tributario || "—")} · IE ${esc(emp?.inscricao_estadual || "—")}</span>
            </div>
          </div>
          <div class="fiscal-compra-grid fiscal-compra-grid--3">
            <label>Nº NF<input id="fiscalCompraNumeroNf" value="${esc(nota?.numero || "")}" ${podeEditar ? "" : "readonly"} /></label>
            <label>Chave NF-e<input id="fiscalCompraChaveNf" maxlength="44" value="${esc(nota?.chave_acesso || "")}" ${podeEditar ? "" : "readonly"} /></label>
            <label>Total NF<input id="fiscalCompraValorTotal" type="number" step="0.01" value="${esc(nota?.valor_total ?? "")}" ${podeEditar ? "" : "readonly"} /></label>
          </div>
          <div class="fiscal-compra-tributos-wrap">
            <h4>Tributos destacados (documento)</h4>
            ${tributosResumoHtml(nota?.tributos_destacados)}
            <h4>Créditos potenciais</h4>
            ${tributosResumoHtml(nota?.creditos_potenciais)}
          </div>
          ${
            itensRows
              ? `<div class="table-wrapper fiscal-compra-itens-table"><table><thead><tr><th>Produto</th><th>NCM</th><th>CFOP</th><th>ICMS</th><th>PIS</th><th>COFINS</th></tr></thead><tbody id="fiscalCompraItensBody">${itensRows}</tbody></table></div>`
              : "<p class=\"subtle-text\">Adicione itens comprados para informar dados fiscais por produto.</p>"
          }
          ${podeEditar ? '<button type="button" class="btn primary" id="fiscalCompraSalvarBtn">Salvar dados fiscais</button>' : ""}
        </div>
      </div>`;

    root.dataset.listaId = String(lista.id);
    root._fiscalItensBase = buildItensFromLista(lista, bundle);

    root.querySelector("#fiscalCompraEmpresaSelect")?.addEventListener("change", async (ev) => {
      const v = ev.target.value;
      try {
        await fFetch(`/fiscal/compras/listas/${lista.id}`, {
          method: "PUT",
          body: JSON.stringify({ empresa_id: v ? Number(v) : null }),
        });
        window.toast?.("Empresa da compra atualizada.", "success");
        await renderFiscalCompraPanel(lista);
      } catch (err) {
        window.toast?.(err.message, "error");
      }
    });

    root.querySelector("#fiscalCompraSalvarBtn")?.addEventListener("click", () => salvarFiscalCompra(lista));
  }

  async function salvarFiscalCompra(lista) {
    const root = document.getElementById("fiscalCompraPanel");
    if (!root) return;
    const base = root._fiscalItensBase || [];
    const rows = root.querySelectorAll("#fiscalCompraItensBody tr");
    rows.forEach((tr) => {
      const idx = Number(tr.dataset.idx);
      const item = base[idx];
      if (!item) return;
      tr.querySelectorAll(".fiscal-compra-inp").forEach((inp) => {
        const f = inp.dataset.f;
        if (f) item[f] = inp.value;
      });
    });
    const payload = {
      numero: root.querySelector("#fiscalCompraNumeroNf")?.value?.trim() || null,
      chave_acesso: root.querySelector("#fiscalCompraChaveNf")?.value?.trim() || null,
      valor_total: root.querySelector("#fiscalCompraValorTotal")?.value || null,
      empresa_id: root.querySelector("#fiscalCompraEmpresaSelect")?.value || null,
      status: "validada",
      itens: base,
    };
    try {
      await fFetch(`/fiscal/compras/listas/${lista.id}/nota`, { method: "PUT", body: JSON.stringify(payload) });
      window.toast?.("Nota fiscal de entrada salva.", "success");
      await renderFiscalCompraPanel(lista);
    } catch (e) {
      window.toast?.(e.message || "Erro ao salvar NF.", "error");
    }
  }

  window.renderFiscalCompraPanel = renderFiscalCompraPanel;

  /** Filtra unidades do formulário nova lista pela empresa selecionada (Módulo 1). */
  window.fiscalCompraFiltrarUnidadesPorEmpresa = function (empresaId, unidadeSelect, allUnidades) {
    if (!unidadeSelect || !Array.isArray(allUnidades)) return;
    const eid = empresaId ? String(empresaId) : "";
    const cur = unidadeSelect.value;
    unidadeSelect.innerHTML = '<option value="">Selecione</option>';
    allUnidades.forEach((u) => {
      if (eid && u.empresa_id && String(u.empresa_id) !== eid) return;
      const opt = document.createElement("option");
      opt.value = u.id;
      opt.textContent = u.nome || `Unidade ${u.id}`;
      unidadeSelect.appendChild(opt);
    });
    if (cur && [...unidadeSelect.options].some((o) => o.value === cur)) unidadeSelect.value = cur;
  };

  async function renderFiscalComprasRelatorioPage() {
    const root = document.getElementById("fiscalComprasRelatorioRoot");
    if (!root) return;
    root.innerHTML = `<div class="fiscal-compra-card table-card"><p class="subtle-text">Carregando relatórios…</p></div>`;
    await loadEmpresas();
    let entradas = { rows: [] };
    let creditos = { rows: [] };
    try {
      entradas = await fFetch("/fiscal/compras/relatorio-entradas");
      creditos = await fFetch("/fiscal/compras/creditos-potenciais");
    } catch (e) {
      root.innerHTML = `<div class="fiscal-compra-card"><p class="subtle-text">${esc(e.message)}</p></div>`;
      return;
    }
    const entRows = (entradas.rows || [])
      .slice(0, 200)
      .map(
        (r) => `<tr>
        <td>${esc(r.data_emissao || "—")}</td>
        <td>${esc(r.nf_numero || "—")}</td>
        <td>${esc(r.empresa_nome || "—")}</td>
        <td>${esc(r.produto_nome || r.produto_id || "—")}</td>
        <td>${esc(r.quantidade)}</td>
        <td>${fmtMoney(r.valor_total_item)}</td>
        <td>${fmtMoney(r.valor_icms)}</td>
        <td>${fmtMoney(r.valor_pis)}</td>
      </tr>`
      )
      .join("");
    const credRows = (creditos.rows || [])
      .slice(0, 200)
      .map(
        (r) => `<tr>
        <td>${esc(r.empresa_nome || "—")}</td>
        <td>${esc(r.produto_nome || "—")}</td>
        <td>${esc(r.nf_numero || "—")}</td>
        <td>${esc(r.tipo_tributo)}</td>
        <td>${fmtMoney(r.valor_destacado)}</td>
        <td>${fmtMoney(r.valor_potencial)}</td>
        <td>${esc(r.status)}</td>
      </tr>`
      )
      .join("");
    root.innerHTML = `
      <div class="fiscal-page">
        <div class="fiscal-toolbar">
          <div class="fiscal-toolbar__info">
            <p class="fiscal-toolbar__label">Relatórios</p>
            <p class="fiscal-toolbar__hint">Entradas por item de NF e créditos classificados como potenciais (não é apuração final).</p>
          </div>
        </div>
        <div class="fiscal-panel-card">
          <header class="fiscal-panel-card__head"><h3>Entradas fiscais</h3></header>
          <div class="fiscal-panel-card__body table-wrapper">
            <table><thead><tr><th>Data</th><th>NF</th><th>Empresa</th><th>Produto</th><th>Qtd</th><th>Valor</th><th>ICMS</th><th>PIS</th></tr></thead>
            <tbody>${entRows || '<tr><td colspan="8">Nenhuma entrada registrada.</td></tr>'}</tbody></table>
          </div>
        </div>
        <div class="fiscal-panel-card">
          <header class="fiscal-panel-card__head"><h3>Créditos potenciais</h3></header>
          <div class="fiscal-panel-card__body table-wrapper">
            <table><thead><tr><th>Empresa</th><th>Produto</th><th>NF</th><th>Tributo</th><th>Destacado</th><th>Potencial</th><th>Status</th></tr></thead>
            <tbody>${credRows || '<tr><td colspan="7">Nenhum crédito registrado.</td></tr>'}</tbody></table>
          </div>
        </div>
      </div>`;
  }

  window.loadFiscalComprasRelatorio = renderFiscalComprasRelatorioPage;

  window.populateListaCompraFiltroEmpresas = async function (selectEl) {
    if (!selectEl) return;
    await loadEmpresas();
    const cur = selectEl.value;
    selectEl.innerHTML = '<option value="">Todas empresas</option>';
    empresasCache.forEach((e) => {
      const opt = document.createElement("option");
      opt.value = String(e.id);
      opt.textContent = `${e.razao_social || "Empresa"}${e.cnpj ? " — " + fmtCnpj(e.cnpj) : ""}`;
      selectEl.appendChild(opt);
    });
    if (cur) selectEl.value = cur;
  };
})();
