/**
 * Módulo Patrimônio — SAS-Estoque (completo)
 */
(function () {
  "use strict";

  const PAT_CHARTS = {};
  const patState = { categorias: [], setores: [], patrimonios: [], inventarioId: null, unidadesOk: false, lastRelatorio: null };
  const PAT_SIT_LABEL = {
    ativo: "Ativo",
    manutencao: "Em manutenção",
    baixado: "Baixado",
    vendido: "Vendido",
    quebrado: "Quebrado",
  };

  function esc(s) {
    return (s ?? "").toString()
      .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
  }

  function fmtMoeda(n) {
    if (typeof formatCurrencyBRL === "function") return formatCurrencyBRL(n);
    const v = Number(n);
    if (!Number.isFinite(v)) return "—";
    return v.toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
  }

  function patLerMoeda(el) {
    if (!el) return null;
    if (typeof parseCurrencyInput === "function") {
      const n = parseCurrencyInput(el);
      return Number.isFinite(n) ? n : null;
    }
    const n = Number(String(el.value || el.dataset.value || "").replace(/\./g, "").replace(",", "."));
    return Number.isFinite(n) ? n : null;
  }

  function patEscreverMoeda(el, valor) {
    if (!el) return;
    const n = Number(valor);
    const num = Number.isFinite(n) ? n : 0;
    if (typeof applyFechamentoValorInput === "function") {
      applyFechamentoValorInput(el, num);
      return;
    }
    el.dataset.value = String(num);
    el.value = num > 0 ? fmtMoeda(num) : "";
  }

  function patSetupMoedaInputs(root) {
    const scope = root || document.getElementById("patrimonioModal");
    if (!scope) return;
    scope.querySelectorAll("[data-pat-moeda]").forEach((inp) => {
      if (inp.dataset.patMoedaBound === "1") return;
      inp.dataset.patMoedaBound = "1";
      if (typeof attachCurrencyMask === "function") attachCurrencyMask(inp);
    });
  }

  function patAplicarMoedasNoFormData(form, fd) {
    ["valor_compra", "valor_atual", "depreciacao"].forEach((campo) => {
      const el = form.elements[campo];
      if (!el) return;
      const v = patLerMoeda(el);
      fd.set(campo, v != null && v > 0 ? String(v) : "");
    });
  }

  function fmtData(d) {
    if (!d) return "—";
    const s = String(d).slice(0, 10);
    if (!/^\d{4}-\d{2}-\d{2}/.test(s)) return s;
    const [y, m, day] = s.split("-");
    return `${day}/${m}/${y}`;
  }

  function patApiUrl() {
    return (window.APP_CONFIG && window.APP_CONFIG.API_URL) || "";
  }

  /** ID do usuário logado (mesma fonte que fetchJSON em app.js). */
  function patUsuarioId() {
    const u =
      (typeof window.getUser === "function" ? window.getUser() : null) ||
      window.currentUser ||
      null;
    const id = u?.id;
    return id != null && id !== "" ? String(id) : null;
  }

  function patToast(message, type = "info") {
    const fn = typeof showToast === "function" ? showToast : window.showToast;
    if (typeof fn === "function") fn(message, type);
  }

  function patFormFeedback(message, type = "error") {
    const box = document.getElementById("patFormFeedback");
    if (!box) return;
    if (!message) {
      box.textContent = "";
      box.className = "pat-form-feedback hidden";
      return;
    }
    box.textContent = message;
    box.className = `pat-form-feedback pat-form-feedback--${type === "success" ? "success" : type === "info" ? "info" : "error"}`;
    box.scrollIntoView({ block: "nearest", behavior: "smooth" });
  }

  function patParseApiError(text, status) {
    if (!text) return status >= 500 ? "Erro no servidor. Tente novamente." : "Erro ao salvar.";
    try {
      const j = JSON.parse(text);
      if (j?.message) return j.message;
      if (j?.error) return j.error;
    } catch (_) {
      /* não é JSON */
    }
    if (text.length > 500 || text.trim().startsWith("<")) {
      return status >= 500 ? "Erro no servidor. Tente novamente." : `Erro ${status}`;
    }
    return text;
  }

  async function patFetch(path, opts = {}) {
    if (typeof window.fetchJSON === "function") {
      return window.fetchJSON(path, opts);
    }
    const headers = { "Content-Type": "application/json", ...(opts.headers || {}) };
    const uid = patUsuarioId();
    if (uid) headers["X-Usuario-Id"] = uid;
    const res = await fetch(`${patApiUrl()}${path}`, { ...opts, headers });
    if (!res.ok) throw new Error(patParseApiError(await res.text(), res.status));
    const ct = res.headers.get("content-type") || "";
    if (ct.includes("application/json")) return res.json();
    return res.text();
  }

  async function patFetchForm(path, fd, method = "POST") {
    const headers = {};
    const uid = patUsuarioId();
    if (uid) headers["X-Usuario-Id"] = uid;
    const res = await fetch(`${patApiUrl()}${path}`, { method, headers, body: fd });
    if (!res.ok) throw new Error(patParseApiError(await res.text(), res.status));
    return res.json();
  }

  async function patDownloadRelatorio(relatorio, formato, extra = {}) {
    const qs = new URLSearchParams(extra);
    let path;
    if (relatorio === "resumo") path = "/patrimonio/relatorios/resumo";
    else if (relatorio.includes("/")) path = `/patrimonio/relatorios/${relatorio}.${formato}`;
    else path = `/patrimonio/relatorios/${relatorio}.${formato}`;
    const url = `${patApiUrl()}${path}${qs.toString() ? `?${qs}` : ""}`;
    const headers = {};
    const uid = patUsuarioId();
    if (uid) headers["X-Usuario-Id"] = uid;
    const res = await fetch(url, { headers });
    if (!res.ok) throw new Error(await res.text() || "Falha no relatório");
    const blob = await res.blob();
    const a = document.createElement("a");
    a.href = URL.createObjectURL(blob);
    a.download = `patrimonio-${relatorio}.${formato === "pdf" ? "pdf" : "csv"}`;
    if (formato === "pdf") {
      window.open(a.href, "_blank");
    } else {
      a.click();
    }
  }

  function calcDepreciacaoLocal() {
    const compra = patLerMoeda(document.getElementById("patFormValorCompra")) || 0;
    const meses = parseInt(document.getElementById("patFormVidaUtil")?.value || "0", 10);
    const dataCompra = document.getElementById("patFormDataCompra")?.value || "";
    if (!compra || !meses) {
      patToast("Informe valor de compra e vida útil (meses).", "info");
      return;
    }
    let mesesUso = 0;
    if (dataCompra) {
      const ini = new Date(dataCompra + "T12:00:00");
      const hoje = new Date();
      mesesUso = Math.max(0, (hoje.getFullYear() - ini.getFullYear()) * 12 + (hoje.getMonth() - ini.getMonth()));
    }
    const depMensal = compra / meses;
    const depAcum = Math.min(compra, depMensal * mesesUso);
    const atual = Math.max(0, Math.round((compra - depAcum) * 100) / 100);
    patEscreverMoeda(document.getElementById("patFormDepreciacao"), Math.round(depAcum * 100) / 100);
    patEscreverMoeda(document.getElementById("patFormValorAtual"), atual);
    patToast("Depreciação calculada.", "success");
  }

  async function populatePatUnidades(extraIds = []) {
    const ids = ["patDashFiltroUnidade", "patFiltroUnidade", "patFormUnidade", "patMovUnidadeDest", "patInvUnidade", ...extraIds];
    let unidades = window.state?.unidades || [];
    if (!unidades.length && !patState.unidadesOk) {
      if (typeof window.loadUnidades === "function") {
        await window.loadUnidades(false).catch(() => {});
        unidades = window.state?.unidades || [];
      }
      if (!unidades.length) {
        try {
          unidades = await patFetch("/unidades?todas=1");
          if (window.state) window.state.unidades = unidades;
        } catch (_) {
          unidades = [];
        }
      }
      patState.unidadesOk = unidades.length > 0;
    } else if (!unidades.length) {
      unidades = window.state?.unidades || [];
    }
    const opts = unidades.map((u) => `<option value="${u.id}">${esc(u.nome)}</option>`).join("");
    ids.forEach((id) => {
      const el = document.getElementById(id);
      if (!el) return;
      const first = id === "patFormUnidade" || id === "patMovUnidadeDest"
        ? '<option value="">Selecione</option>'
        : '<option value="">Todas</option>';
      const prev = el.value;
      el.innerHTML = first + opts;
      if (prev && [...el.options].some((o) => o.value === prev)) el.value = prev;
    });
  }

  async function populatePatSelectPatrimonios(selectIds) {
    if (!patState.patrimonios.length) {
      patState.patrimonios = await patFetch("/patrimonio/patrimonios");
    }
    const opts = patState.patrimonios
      .map((p) => `<option value="${p.id}">${esc(p.codigo)} — ${esc(p.nome)}</option>`)
      .join("");
    selectIds.forEach((id) => {
      const el = document.getElementById(id);
      if (el) el.innerHTML = '<option value="">Selecione</option>' + opts;
    });
  }

  async function loadPatCategorias(force = false) {
    if (!force && patState.categorias.length) return patState.categorias;
    patState.categorias = await patFetch("/patrimonio/categorias");
    const sel = document.getElementById("patFiltroCategoria");
    const formSel = document.getElementById("patFormCategoria");
    const relSel = document.getElementById("patRelFiltroCategoria");
    const opts = patState.categorias
      .filter((c) => c.ativo !== 0 && c.ativo !== false)
      .map((c) => `<option value="${c.id}" data-tipo="${esc(c.tipo_campos || "geral")}">${esc(c.icone || "")} ${esc(c.nome)}</option>`)
      .join("");
    if (sel) sel.innerHTML = '<option value="">Todas</option>' + opts;
    if (relSel) relSel.innerHTML = '<option value="">Todas</option>' + opts;
    if (formSel) formSel.innerHTML = '<option value="">Selecione</option>' + opts;
    return patState.categorias;
  }

  async function loadPatSetores(force = false, todos = false) {
    if (!force && patState.setores.length && !todos) return patState.setores;
    const q = todos ? "?todos=1" : "";
    try {
      patState.setores = await patFetch(`/patrimonio/setores${q}`);
    } catch (e) {
      if (!todos) {
        try {
          const raw = await patFetch("/patrimonio/relatorios/setores");
          patState.setores = (raw || []).map((s) => (
            typeof s === "object" && s != null && s.id != null
              ? s
              : { id: null, nome: typeof s === "string" ? s : String(s?.nome || ""), ativo: true }
          )).filter((s) => s.nome);
        } catch (_) {
          patState.setores = [];
          throw e;
        }
      } else {
        throw e;
      }
    }
    return patState.setores;
  }

  function populatePatSetorSelects() {
    const opts = patState.setores
      .filter((s) => s.id && s.ativo !== false && s.ativo !== 0)
      .map((s) => `<option value="${s.id}">${esc(s.nome)}</option>`)
      .join("");
    const html = `<option value="">Todos</option>${opts}`;
    const formHtml = `<option value="">Selecione</option>${opts}`;
    ["patFiltroSetor", "patRelFiltroSetor"].forEach((id) => {
      const el = document.getElementById(id);
      if (!el) return;
      const cur = el.value;
      el.innerHTML = html;
      if (cur) el.value = cur;
    });
    const formSel = document.getElementById("patFormSetor");
    if (formSel) {
      const cur = formSel.value;
      formSel.innerHTML = formHtml;
      if (cur) formSel.value = cur;
    }
  }

  function situacaoBadge(s) {
    const map = { ativo: "Ativo", manutencao: "Em manutenção", baixado: "Baixado", vendido: "Vendido", quebrado: "Quebrado" };
    const cls = ["ativo", "manutencao"].includes(s) ? s : "baixado";
    return `<span class="pat-situacao-badge pat-situacao-badge--${cls}">${esc(map[s] || s)}</span>`;
  }

  function destroyPatCharts() {
    Object.keys(PAT_CHARTS).forEach((k) => {
      if (PAT_CHARTS[k]) { PAT_CHARTS[k].destroy(); PAT_CHARTS[k] = null; }
    });
  }

  function renderAnexosLista(fotos, documentos) {
    const box = document.getElementById("patFormAnexosLista");
    if (!box) return;
    const api = patApiUrl().replace(/\/api\/?$/, "");
    let html = "";
    (fotos || []).forEach((f) => {
      html += `<div class="pat-anexo-item"><a href="${api}/storage/${esc(f.path)}" target="_blank">📷 ${esc(f.nome || "Foto")}</a>
        <button type="button" class="btn-icon danger pat-del-arquivo" data-tipo="foto" data-id="${f.id}">✕</button></div>`;
    });
    (documentos || []).forEach((d) => {
      html += `<div class="pat-anexo-item"><a href="${patApiUrl()}/patrimonio/arquivos/documento/${d.id}" target="_blank">📎 ${esc(d.tipo)} — ${esc(d.nome)}</a>
        <button type="button" class="btn-icon danger pat-del-arquivo" data-tipo="documento" data-id="${d.id}">✕</button></div>`;
    });
    box.innerHTML = html || '<p class="subtle-text">Nenhum anexo cadastrado ainda.</p>';
  }

  window.loadPatrimonioDashboard = async function loadPatrimonioDashboard() {
    const u = document.getElementById("patDashFiltroUnidade")?.value;
    const qs = u ? `?unidade_id=${encodeURIComponent(u)}` : "";
    const [, data] = await Promise.all([
      populatePatUnidades(),
      patFetch(`/patrimonio/dashboard${qs}`),
    ]);
    const cards = document.getElementById("patDashCards");
    if (cards) {
      cards.innerHTML = `
        <div class="patrimonio-card"><span class="patrimonio-card__label">Total patrimônios</span><span class="patrimonio-card__value">${data.total_patrimonios ?? 0}</span></div>
        <div class="patrimonio-card"><span class="patrimonio-card__label">Valor total ativos</span><span class="patrimonio-card__value">${fmtMoeda(data.valor_total_ativos)}</span></div>
        <div class="patrimonio-card"><span class="patrimonio-card__label">Em manutenção</span><span class="patrimonio-card__value">${data.em_manutencao ?? 0}</span></div>
        <div class="patrimonio-card"><span class="patrimonio-card__label">Baixados / inativos</span><span class="patrimonio-card__value">${data.baixados ?? 0}</span></div>`;
    }
    if (typeof Chart !== "undefined") {
      destroyPatCharts();
      const mk = (id, labels, values) => {
        const cv = document.getElementById(id);
        if (!cv || !labels.length) return;
        PAT_CHARTS[id] = new Chart(cv, {
          type: "bar",
          data: { labels, datasets: [{ label: "Qtd", data: values, backgroundColor: "rgba(21,101,192,0.55)" }] },
          options: { responsive: true, plugins: { legend: { display: false } } },
        });
      };
      const pc = data.por_categoria || [];
      mk("patChartCategoria", pc.map((x) => x.label || "?"), pc.map((x) => x.total));
      const pu = data.por_unidade || [];
      mk("patChartUnidade", pu.map((x) => x.label || "?"), pu.map((x) => x.total));
    }
    const movTb = document.getElementById("patDashMovTbody");
    if (movTb) {
      const movs = data.ultimas_movimentacoes || [];
      movTb.innerHTML = movs.length
        ? movs.map((m) => `<tr><td>${fmtData(m.created_at)}</td><td>${esc(m.patrimonio_nome || m.codigo)}</td><td>${esc(m.tipo)}</td><td>${esc((m.observacao || "").slice(0, 80))}</td></tr>`).join("")
        : '<tr><td colspan="4" style="text-align:center;color:#90a4ae">Nenhuma movimentação recente</td></tr>';
    }
    const al = document.getElementById("patDashAlertas");
    if (al) {
      const alertas = data.alertas_manutencao || [];
      al.innerHTML = alertas.length
        ? alertas.map((a) => `<div class="pat-alerta-item"><strong>${esc(a.patrimonio_nome)}</strong> (${esc(a.codigo)}) — manutenção até ${fmtData(a.proxima_manutencao)}</div>`).join("")
        : '<p class="subtle-text">Nenhum alerta de manutenção nos próximos 30 dias.</p>';
    }
    const gar = document.getElementById("patDashGarantias");
    if (gar) {
      const g = data.garantias_vencendo || [];
      gar.innerHTML = g.length
        ? g.map((x) => `<div class="pat-alerta-item"><strong>${esc(x.nome)}</strong> (${esc(x.codigo)}) — ${esc(x.tipo_alerta)} vence em ${fmtData(x.data_vencimento)}</div>`).join("")
        : '<p class="subtle-text">Nenhum vencimento de garantia/IPVA nos próximos 60 dias.</p>';
    }
  };

  window.loadPatrimonios = async function loadPatrimonios() {
    const tb = document.getElementById("patListaTbody");
    if (tb) {
      tb.innerHTML = '<tr><td colspan="8" style="text-align:center;color:#90a4ae">Carregando patrimônios…</td></tr>';
    }
    const qs = new URLSearchParams();
    const busca = document.getElementById("patFiltroBusca")?.value?.trim();
    const un = document.getElementById("patFiltroUnidade")?.value;
    const cat = document.getElementById("patFiltroCategoria")?.value;
    const sit = document.getElementById("patFiltroSituacao")?.value;
    const setorId = document.getElementById("patFiltroSetor")?.value;
    if (busca) qs.set("busca", busca);
    if (setorId) qs.set("setor_id", setorId);
    if (un) qs.set("unidade_id", un);
    if (cat) qs.set("categoria_id", cat);
    if (sit) qs.set("situacao", sit);
    const q = qs.toString() ? `?${qs}` : "";
    const [, , , lista] = await Promise.all([
      populatePatUnidades(),
      loadPatCategorias(),
      loadPatSetores().then(() => populatePatSetorSelects()),
      patFetch(`/patrimonio/patrimonios${q}`),
    ]);
    patState.patrimonios = lista;
    if (!tb) return;
    if (!patState.patrimonios.length) {
      tb.innerHTML = '<tr><td colspan="8" style="text-align:center;color:#90a4ae">Nenhum patrimônio encontrado</td></tr>';
      return;
    }
    tb.innerHTML = patState.patrimonios.map((p) => `
      <tr>
        <td data-label="Código">${esc(p.codigo)}</td>
        <td data-label="Nome">${esc(p.nome)}</td>
        <td data-label="Categoria">${esc(p.categoria_nome || "—")}</td>
        <td data-label="Unidade">${esc(p.unidade_nome || "—")}</td>
        <td data-label="Situação">${situacaoBadge(p.situacao)}</td>
        <td data-label="Valor">${fmtMoeda(p.valor_atual)}</td>
        <td data-label="QR"><button type="button" class="btn-icon pat-btn-qr" data-token="${esc(p.qr_token)}" title="QR Code">📱</button></td>
        <td data-label="Ações">
          <button type="button" class="btn-icon pat-btn-ver" data-id="${p.id}" title="Ficha">👁</button>
          <button type="button" class="btn-icon pat-btn-editar" data-id="${p.id}" title="Editar">✎</button>
          <button type="button" class="btn-icon danger pat-btn-excluir" data-id="${p.id}" title="Excluir">✕</button>
        </td>
      </tr>`).join("");
  };

  async function abrirFichaPatrimonio(id) {
    const modal = document.getElementById("patFichaModal");
    const content = document.getElementById("patFichaContent");
    const titulo = document.getElementById("patFichaTitulo");
    if (!modal || !content) return;
    patState.fichaId = id;
    content.innerHTML = '<p>Carregando...</p>';
    modal.classList.add("active");
    try {
      const res = await patFetch(`/patrimonio/patrimonios/${id}`);
      const p = res.patrimonio;
      if (titulo) titulo.textContent = `${p.codigo} — ${p.nome}`;
      const mov = await patFetch(`/patrimonio/patrimonios/${id}/movimentacoes`).catch(() => []);
      const man = await patFetch(`/patrimonio/manutencoes?patrimonio_id=${id}`).catch(() => []);
      let espec = "";
      if (p.dados_especificos && typeof p.dados_especificos === "object") {
        espec = Object.entries(p.dados_especificos).filter(([, v]) => v).map(([k, v]) => `<tr><td>${esc(k)}</td><td>${esc(v)}</td></tr>`).join("");
      }
      content.innerHTML = `
        <div class="pat-ficha-grid">
          <div><strong>Categoria:</strong> ${esc(p.categoria_nome)}</div>
          <div><strong>Unidade:</strong> ${esc(p.unidade_nome)}</div>
          <div><strong>Setor:</strong> ${esc(p.setor || "—")}</div>
          <div><strong>Responsável:</strong> ${esc(p.responsavel)}</div>
          <div><strong>Situação:</strong> ${esc(p.situacao)}</div>
          <div><strong>Valor compra:</strong> ${fmtMoeda(p.valor_compra)}</div>
          <div><strong>Valor atual:</strong> ${fmtMoeda(p.valor_atual)}</div>
          <div><strong>Depreciação:</strong> ${fmtMoeda(p.depreciacao)}</div>
          <div><strong>Serial:</strong> ${esc(p.numero_serial || "—")}</div>
        </div>
        ${espec ? `<h4>Dados específicos</h4><table><tbody>${espec}</tbody></table>` : ""}
        ${p.observacoes ? `<h4>Observações</h4><p class="pat-ficha-obs">${esc(p.observacoes)}</p>` : ""}
        <h4>Movimentações</h4><ul>${(mov || []).slice(0, 8).map((m) => `<li>${fmtData(m.created_at)} — ${esc(m.tipo)}</li>`).join("") || "<li>Nenhuma</li>"}</ul>
        <h4>Manutenções</h4><ul>${(man || []).slice(0, 8).map((m) => `<li>${fmtData(m.data_manutencao)} — ${esc(m.tipo_manutencao)}</li>`).join("") || "<li>Nenhuma</li>"}</ul>
        <h4>Anexos</h4><div id="patFichaAnexos"></div>`;
      renderAnexosLista(res.fotos, res.documentos);
      const anexosClone = document.getElementById("patFormAnexosLista");
      const dest = document.getElementById("patFichaAnexos");
      if (dest && anexosClone) dest.innerHTML = anexosClone.innerHTML;
    } catch (e) {
      content.innerHTML = `<p style="color:#c62828">${esc(e.message)}</p>`;
    }
  }

  window.loadPatrimonioCategorias = async function loadPatrimonioCategorias() {
    const cats = await loadPatCategorias();
    const tb = document.getElementById("patCatTbody");
    if (!tb) return;
    tb.innerHTML = cats.map((c) => `
      <tr>
        <td>${esc(c.icone || "—")}</td>
        <td>${esc(c.nome)}</td>
        <td>${esc(c.tipo_campos || "geral")}</td>
        <td>${c.ordem ?? 0}</td>
        <td>${c.ativo ? "Sim" : "Não"}</td>
        <td>
          <button type="button" class="btn-icon pat-cat-editar" data-id="${c.id}" data-nome="${esc(c.nome)}" data-icone="${esc(c.icone || "")}" data-tipo="${esc(c.tipo_campos)}" data-ordem="${c.ordem}">✎</button>
          <button type="button" class="btn-icon danger pat-cat-excluir" data-id="${c.id}">✕</button>
        </td>
      </tr>`).join("");
  };

  window.loadPatrimonioMovimentacoes = async function loadPatrimonioMovimentacoes() {
    const rows = await patFetch("/patrimonio/movimentacoes");
    const tb = document.getElementById("patMovTbody");
    if (!tb) return;
    tb.innerHTML = rows.length
      ? rows.map((m) => `<tr>
          <td>${fmtData(m.created_at)}</td>
          <td>${esc(m.patrimonio_nome || m.codigo)}</td>
          <td>${esc(m.tipo)}</td>
          <td>${esc(m.unidade_origem_nome || "—")} → ${esc(m.unidade_destino_nome || "—")}</td>
          <td>${esc(m.responsavel_novo || m.responsavel_anterior || "—")}</td>
          <td>${esc(m.observacao || "")}</td>
        </tr>`).join("")
      : '<tr><td colspan="6" style="text-align:center">Nenhuma movimentação</td></tr>';
  };

  window.loadPatrimonioManutencoes = async function loadPatrimonioManutencoes() {
    const rows = await patFetch("/patrimonio/manutencoes");
    const tb = document.getElementById("patManTbody");
    if (!tb) return;
    tb.innerHTML = rows.length
      ? rows.map((m) => `<tr>
          <td>${fmtData(m.data_manutencao)}</td>
          <td>${esc(m.patrimonio_nome)} (${esc(m.codigo)})</td>
          <td>${esc(m.tipo_manutencao)}</td>
          <td>${esc(m.tecnico || "—")}</td>
          <td>${fmtMoeda(m.custo)}</td>
          <td>${fmtData(m.proxima_manutencao)}</td>
        </tr>`).join("")
      : '<tr><td colspan="6" style="text-align:center">Nenhuma manutenção</td></tr>';
  };

  function patRelDescricaoFiltros(f) {
    const parts = [];
    const unEl = document.getElementById("patRelFiltroUnidade");
    const catEl = document.getElementById("patRelFiltroCategoria");
    parts.push(f.unidade_id
      ? `Unidade: ${unEl?.selectedOptions?.[0]?.textContent?.trim() || f.unidade_id}`
      : "Unidade: Todas");
    parts.push(f.categoria_id
      ? `Categoria: ${catEl?.selectedOptions?.[0]?.textContent?.trim() || f.categoria_id}`
      : "Categoria: Todas");
    parts.push(f.situacao ? `Situação: ${PAT_SIT_LABEL[f.situacao] || f.situacao}` : "Situação: Todas");
    const setorEl = document.getElementById("patRelFiltroSetor");
    parts.push(f.setor_id
      ? `Setor: ${setorEl?.selectedOptions?.[0]?.textContent?.trim() || f.setor_id}`
      : "Setor: Todos");
    if (f.busca) parts.push(`Busca: ${f.busca}`);
    return parts;
  }

  function patRelAgrupar(itens, campo, labelFn) {
    const map = new Map();
    itens.forEach((p) => {
      const label = labelFn ? labelFn(p) : String(p[campo] || "—");
      const cur = map.get(label) || { label, quantidade: 0, valor_atual: 0 };
      cur.quantidade += 1;
      cur.valor_atual += Number(p.valor_atual) || 0;
      map.set(label, cur);
    });
    return [...map.values()].sort((a, b) => b.quantidade - a.quantidade);
  }

  function patRelMontarRelatorioLocal(itens, filtrosObj) {
    const arr = Array.isArray(itens) ? itens : [];
    return {
      filtros: patRelDescricaoFiltros(filtrosObj),
      emitido_em: new Date().toLocaleString("pt-BR"),
      totais: {
        quantidade: arr.length,
        valor_compra: Math.round(arr.reduce((s, p) => s + (Number(p.valor_compra) || 0), 0) * 100) / 100,
        valor_atual: Math.round(arr.reduce((s, p) => s + (Number(p.valor_atual) || 0), 0) * 100) / 100,
        depreciacao: Math.round(arr.reduce((s, p) => s + (Number(p.depreciacao) || 0), 0) * 100) / 100,
      },
      resumo_por_categoria: patRelAgrupar(arr, "categoria_nome"),
      resumo_por_unidade: patRelAgrupar(arr, "unidade_nome"),
      resumo_por_situacao: patRelAgrupar(arr, "situacao", (p) => PAT_SIT_LABEL[p.situacao] || p.situacao || "—"),
      resumo_por_setor: patRelAgrupar(arr, "setor", (p) => (p.setor && String(p.setor).trim()) || "Sem setor"),
      itens: arr,
    };
  }

  function patRelExportarCsvLocal() {
    const data = patState.lastRelatorio;
    if (!data?.itens?.length) {
      patToast("Gere o relatório antes de exportar CSV.", "error");
      return;
    }
    const header = ["Código", "Nome", "Categoria", "Unidade", "Setor", "Situação", "Responsável", "Valor compra", "Valor atual", "Depreciação"];
    const lines = [header.join(";")];
    data.itens.forEach((p) => {
      lines.push([
        p.codigo, p.nome, p.categoria_nome || "", p.unidade_nome || "", p.setor || "",
        PAT_SIT_LABEL[p.situacao] || p.situacao || "",
        p.responsavel || "", p.valor_compra ?? "", p.valor_atual ?? "", p.depreciacao ?? "",
      ].map((c) => String(c ?? "").replace(/;/g, ",").replace(/\n/g, " ")).join(";"));
    });
    const blob = new Blob(["\uFEFF" + lines.join("\n")], { type: "text/csv;charset=utf-8" });
    const a = document.createElement("a");
    a.href = URL.createObjectURL(blob);
    a.download = "patrimonio-filtrado.csv";
    a.click();
    URL.revokeObjectURL(a.href);
  }

  function patRelColetarFiltros() {
    const f = {};
    const un = document.getElementById("patRelFiltroUnidade")?.value;
    const cat = document.getElementById("patRelFiltroCategoria")?.value;
    const sit = document.getElementById("patRelFiltroSituacao")?.value;
    const setorId = document.getElementById("patRelFiltroSetor")?.value;
    const busca = document.getElementById("patRelFiltroBusca")?.value?.trim();
    if (un) f.unidade_id = un;
    if (cat) f.categoria_id = cat;
    if (sit) f.situacao = sit;
    if (setorId) f.setor_id = setorId;
    if (busca) f.busca = busca;
    return f;
  }

  function patRelRenderGrupo(titulo, grupos) {
    if (!grupos?.length) return "";
    const rows = grupos.map((g) => `
      <tr><td>${esc(g.label)}</td><td>${g.quantidade ?? 0}</td><td>${fmtMoeda(g.valor_atual)}</td></tr>`).join("");
    return `<div class="pat-rel-grupo-box">
      <h4>${esc(titulo)}</h4>
      <table><thead><tr><th>Grupo</th><th>Qtd</th><th>Valor atual</th></tr></thead><tbody>${rows}</tbody></table>
    </div>`;
  }

  function patRelRenderResultado(data) {
    const tot = data.totais || {};
    const totaisEl = document.getElementById("patRelTotais");
    if (totaisEl) {
      totaisEl.classList.remove("hidden");
      totaisEl.innerHTML = `
        <div class="patrimonio-card"><span class="patrimonio-card__label">Itens</span><span class="patrimonio-card__value">${tot.quantidade ?? 0}</span></div>
        <div class="patrimonio-card"><span class="patrimonio-card__label">Valor compra</span><span class="patrimonio-card__value">${fmtMoeda(tot.valor_compra)}</span></div>
        <div class="patrimonio-card"><span class="patrimonio-card__label">Valor atual</span><span class="patrimonio-card__value">${fmtMoeda(tot.valor_atual)}</span></div>
        <div class="patrimonio-card"><span class="patrimonio-card__label">Depreciação</span><span class="patrimonio-card__value">${fmtMoeda(tot.depreciacao)}</span></div>`;
    }
    const gruposEl = document.getElementById("patRelGrupos");
    if (gruposEl) {
      gruposEl.classList.remove("hidden");
      gruposEl.innerHTML = `
        <div class="pat-rel-grupos-grid">
          ${patRelRenderGrupo("Por categoria", data.resumo_por_categoria)}
          ${patRelRenderGrupo("Por unidade", data.resumo_por_unidade)}
          ${patRelRenderGrupo("Por situação", data.resumo_por_situacao)}
          ${patRelRenderGrupo("Por setor", data.resumo_por_setor)}
        </div>`;
    }
    const meta = document.getElementById("patRelResultadoMeta");
    if (meta) {
      const filtrosTxt = Array.isArray(data.filtros) ? data.filtros.join(" · ") : String(data.filtros || "");
      meta.textContent = filtrosTxt + (data.emitido_em ? ` · Emitido ${data.emitido_em}` : "");
    }
    const tb = document.getElementById("patRelTbody");
    const card = document.getElementById("patRelResultadoCard");
    if (card) card.classList.remove("hidden");
    const itens = data.itens || [];
    if (!tb) return;
    if (!itens.length) {
      tb.innerHTML = '<tr><td colspan="9" style="text-align:center;color:#90a4ae">Nenhum patrimônio encontrado com os filtros informados.</td></tr>';
      return;
    }
    tb.innerHTML = itens.map((p) => `
      <tr>
        <td data-label="Código">${esc(p.codigo)}</td>
        <td data-label="Nome">${esc(p.nome)}</td>
        <td data-label="Categoria">${esc(p.categoria_nome || "—")}</td>
        <td data-label="Unidade">${esc(p.unidade_nome || "—")}</td>
        <td data-label="Setor">${esc(p.setor || "—")}</td>
        <td data-label="Situação">${situacaoBadge(p.situacao)}</td>
        <td data-label="Responsável">${esc(p.responsavel || "—")}</td>
        <td data-label="Valor compra">${fmtMoeda(p.valor_compra)}</td>
        <td data-label="Valor atual">${fmtMoeda(p.valor_atual)}</td>
      </tr>`).join("");
  }

  window.loadPatrimonioRelatorios = async function loadPatrimonioRelatorios() {
    await Promise.all([
      populatePatUnidades(["patRelFiltroUnidade"]),
      loadPatCategorias(),
      loadPatSetores().then(() => populatePatSetorSelects()),
    ]);
  };

  async function patRelGerarPreview() {
    const filtros = patRelColetarFiltros();
    const qs = new URLSearchParams(filtros);
    const tb = document.getElementById("patRelTbody");
    if (tb) tb.innerHTML = '<tr><td colspan="9" style="text-align:center">Gerando relatório…</td></tr>';
    document.getElementById("patRelResultadoCard")?.classList.remove("hidden");
    document.getElementById("patRelTotais")?.classList.add("hidden");
    document.getElementById("patRelGrupos")?.classList.add("hidden");

    let data = null;
    try {
      data = await patFetch(`/patrimonio/relatorios/filtrado${qs.toString() ? `?${qs}` : ""}`);
      if (!data || !Array.isArray(data.itens)) throw new Error("Resposta inválida do servidor");
    } catch (err) {
      console.warn("[Patrimônio] API filtrado indisponível, usando lista:", err.message);
      const itens = await patFetch(`/patrimonio/patrimonios${qs.toString() ? `?${qs}` : ""}`);
      data = patRelMontarRelatorioLocal(itens, filtros);
    }
    patState.lastRelatorio = data;
    patRelRenderResultado(data);
    patToast(`Relatório: ${data.totais?.quantidade ?? 0} item(ns).`, "success");
  }

  window.loadPatrimonioConfiguracoes = async function loadPatrimonioConfiguracoes() {
    try {
      const setores = await loadPatSetores(true, true);
      const tb = document.getElementById("patSetorTbody");
      if (!tb) return;
      if (!setores.length) {
        tb.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#90a4ae">Nenhum setor cadastrado. Clique em + Novo setor.</td></tr>';
        return;
      }
      tb.innerHTML = setores.map((s) => `
        <tr>
          <td>${esc(s.nome)}</td>
          <td>${esc(s.descricao || "—")}</td>
          <td>${s.ordem ?? 0}</td>
          <td>${s.ativo ? "Sim" : "Não"}</td>
          <td>
            <button type="button" class="btn-icon pat-setor-editar" data-id="${s.id}" data-nome="${esc(s.nome)}" data-descricao="${esc(s.descricao || "")}" data-ordem="${s.ordem ?? 50}" data-ativo="${s.ativo ? "1" : "0"}">✎</button>
            <button type="button" class="btn-icon danger pat-setor-excluir" data-id="${s.id}">✕</button>
          </td>
        </tr>`).join("");
    } catch (e) {
      const tb = document.getElementById("patSetorTbody");
      if (tb) {
        tb.innerHTML = `<tr><td colspan="5" style="text-align:center;color:#c62828">${esc(e.message)}</td></tr>`;
      }
    }
  };

  window.loadPatrimonioInventario = async function loadPatrimonioInventario() {
    await populatePatUnidades();
    const rows = await patFetch("/patrimonio/inventario");
    const tb = document.getElementById("patInvListaTbody");
    if (!tb) return;
    tb.innerHTML = rows.length
      ? rows.map((i) => `<tr>
          <td>${esc(i.titulo)}</td>
          <td>${esc(i.unidade_nome || "Todas")}</td>
          <td>${esc(i.status)}</td>
          <td>${fmtData(i.data_inicio)}</td>
          <td><button type="button" class="btn secondary pat-inv-abrir" data-id="${i.id}" data-titulo="${esc(i.titulo)}" data-status="${esc(i.status)}">Conferir</button></td>
        </tr>`).join("")
      : '<tr><td colspan="5" style="text-align:center">Nenhum inventário</td></tr>';
  };

  async function abrirInventarioItens(id, titulo, status) {
    patState.inventarioId = id;
    document.getElementById("patInvItensTitulo").textContent = titulo || "Inventário";
    document.getElementById("patInvItensCard")?.classList.remove("hidden");
    const fecharBtn = document.getElementById("patInvFechar");
    if (fecharBtn) fecharBtn.style.display = status === "fechado" ? "none" : "";
    const itens = await patFetch(`/patrimonio/inventario/${id}/itens`);
    const tb = document.getElementById("patInvItensTbody");
    tb.innerHTML = itens.map((it) => `
      <tr>
        <td>${esc(it.codigo)}</td>
        <td>${esc(it.patrimonio_nome)}</td>
        <td>${it.qtd_sistema}</td>
        <td><input type="number" min="0" class="pat-inv-qtd" data-item="${it.id}" value="${it.qtd_encontrada ?? ""}" style="width:4rem" ${status === "fechado" ? "disabled" : ""} /></td>
        <td class="pat-inv-diff" data-item="${it.id}">${it.diferenca ?? "—"}</td>
        <td><input type="text" class="pat-inv-obs" data-item="${it.id}" value="${esc(it.observacao || "")}" ${status === "fechado" ? "disabled" : ""} /></td>
        <td>
          ${status === "fechado" ? "" : `<button type="button" class="btn neutral pat-inv-salvar" data-item="${it.id}">OK</button>
          <label class="btn neutral pat-inv-foto-label" style="margin:0">📷<input type="file" accept="image/*" class="pat-inv-foto-input" data-item="${it.id}" hidden /></label>`}
        </td>
      </tr>`).join("");
  }

  function toggleCamposEspecificos() {
    const sel = document.getElementById("patFormCategoria");
    const tipo = sel?.selectedOptions?.[0]?.dataset?.tipo || "geral";
    document.querySelectorAll(".patrimonio-campos-especificos").forEach((el) => {
      el.classList.toggle("is-visible", el.dataset.tipo === tipo);
    });
  }

  function coletarDadosEspecificos() {
    const sel = document.getElementById("patFormCategoria");
    const tipo = sel?.selectedOptions?.[0]?.dataset?.tipo || "geral";
    const box = document.querySelector(`.patrimonio-campos-especificos[data-tipo="${tipo}"]`);
    const dados = {};
    if (box) {
      box.querySelectorAll("[data-campo]").forEach((inp) => {
        if (inp.dataset.campo) dados[inp.dataset.campo] = inp.value?.trim() || null;
      });
    }
    return Object.keys(dados).length ? dados : {};
  }

  function preencherDadosEspecificos(dados) {
    if (!dados || typeof dados !== "object") return;
    document.querySelectorAll("[data-campo]").forEach((inp) => {
      const k = inp.dataset.campo;
      if (k && dados[k] != null) inp.value = dados[k];
    });
    toggleCamposEspecificos();
  }

  function abrirModalPatrimonio(editId) {
    const modal = document.getElementById("patrimonioModal");
    const form = document.getElementById("patrimonioForm");
    if (!modal || !form) return;
    form.reset();
    ["patFormValorCompra", "patFormValorAtual", "patFormDepreciacao"].forEach((id) => patEscreverMoeda(document.getElementById(id), 0));
    document.getElementById("patFormId").value = editId ? String(editId) : "";
    document.getElementById("patModalTitle").textContent = editId ? "✏️ Editar patrimônio" : "📦 Novo patrimônio";
    renderAnexosLista([], []);
    patFormFeedback("");
    toggleCamposEspecificos();
    patSetupMoedaInputs(form);
    modal.classList.add("active");
    Promise.all([populatePatUnidades(), loadPatCategorias(), loadPatSetores().then(() => populatePatSetorSelects())]).catch(() => {});
    if (editId) {
      patFetch(`/patrimonio/patrimonios/${editId}`).then((res) => {
        const p = res.patrimonio;
        const f = form;
        if (f.nome) f.nome.value = p.nome || "";
        if (f.numero_serial) f.numero_serial.value = p.numero_serial || "";
        if (f.categoria_id) f.categoria_id.value = p.categoria_id || "";
        if (f.marca) f.marca.value = p.marca || "";
        if (f.modelo) f.modelo.value = p.modelo || "";
        if (f.cor) f.cor.value = p.cor || "";
        if (f.quantidade) f.quantidade.value = p.quantidade || 1;
        if (f.unidade_id) f.unidade_id.value = p.unidade_id || "";
        const setorSel = document.getElementById("patFormSetor");
        if (setorSel) {
          if (p.setor_id) setorSel.value = String(p.setor_id);
          else if (p.setor && patState.setores.length) {
            const match = patState.setores.find((s) => s.nome === p.setor);
            if (match) setorSel.value = String(match.id);
          }
        }
        const obs = document.getElementById("patFormObservacoes");
        if (obs) obs.value = p.observacoes || "";
        if (f.responsavel) f.responsavel.value = p.responsavel || "";
        if (f.situacao) f.situacao.value = p.situacao || "ativo";
        patEscreverMoeda(f.valor_compra, p.valor_compra);
        if (f.data_compra) f.data_compra.value = (p.data_compra || "").slice(0, 10);
        if (f.vida_util_meses) f.vida_util_meses.value = p.vida_util_meses ?? "";
        patEscreverMoeda(f.valor_atual, p.valor_atual);
        patEscreverMoeda(f.depreciacao, p.depreciacao);
        if (f.fornecedor) f.fornecedor.value = p.fornecedor || "";
        if (f.numero_nf) f.numero_nf.value = p.numero_nf || "";
        preencherDadosEspecificos(p.dados_especificos);
        renderAnexosLista(res.fotos, res.documentos);
      }).catch((e) => patToast(e.message, "error"));
    }
  }

  async function abrirQrPatrimonio(token) {
    const modal = document.getElementById("patQrModal");
    const host = document.getElementById("patQrHost");
    const ficha = document.getElementById("patQrFicha");
    if (!modal || !host) return;
    modal.classList.add("active");
    host.innerHTML = '<p style="text-align:center">Carregando QR...</p>';
    ficha.innerHTML = "";
    try {
      const data = await patFetch(`/patrimonio/patrimonios/qr/${encodeURIComponent(token)}`);
      const p = data.patrimonio;
      const url = `${window.location.origin}${window.location.pathname}#patrimonios&qr=${token}`;
      host.innerHTML = `<div id="patQrCanvasWrap"></div><p class="subtle-text" style="text-align:center;margin-top:0.5rem">${esc(p.codigo)}</p>`;
      if (typeof QRCode !== "undefined") {
        new QRCode(document.getElementById("patQrCanvasWrap"), { text: url, width: 200, height: 200 });
      }
      ficha.innerHTML = `
        <h3>${esc(p.nome)}</h3>
        <p><strong>Unidade:</strong> ${esc(p.unidade_nome || "—")} | <strong>Responsável:</strong> ${esc(p.responsavel || "—")}</p>
        <p><strong>Situação:</strong> ${esc(p.situacao)} | <strong>Valor:</strong> ${fmtMoeda(p.valor_atual)}</p>
        <h4>Últimas movimentações</h4>
        <ul>${(data.movimentacoes || []).slice(0, 5).map((m) => `<li>${fmtData(m.created_at)} — ${esc(m.tipo)}</li>`).join("") || "<li>Nenhuma</li>"}</ul>
        <h4>Manutenções</h4>
        <ul>${(data.manutencoes || []).slice(0, 5).map((m) => `<li>${fmtData(m.data_manutencao)} — ${esc(m.tipo_manutencao)}</li>`).join("") || "<li>Nenhuma</li>"}</ul>`;
      patState.qrToken = token;
    } catch (e) {
      host.innerHTML = `<p style="color:#c62828">${esc(e.message)}</p>`;
    }
  }

  function patrimonioBindOnce() {
    if (document.body.dataset.patrimonioBound === "1") return;
    document.body.dataset.patrimonioBound = "1";

    document.getElementById("patDashAtualizar")?.addEventListener("click", () => loadPatrimonioDashboard().catch((e) => patToast(e.message, "error")));
    document.getElementById("patFiltroAplicar")?.addEventListener("click", () => loadPatrimonios().catch((e) => patToast(e.message, "error")));
    document.getElementById("patBtnNovo")?.addEventListener("click", () => abrirModalPatrimonio(null));
    document.getElementById("patFormCategoria")?.addEventListener("change", toggleCamposEspecificos);
    document.getElementById("patFormCalcDeprec")?.addEventListener("click", calcDepreciacaoLocal);

    document.getElementById("patListaTbody")?.addEventListener("click", async (e) => {
      const qr = e.target.closest(".pat-btn-qr");
      if (qr) return abrirQrPatrimonio(qr.dataset.token);
      const ver = e.target.closest(".pat-btn-ver");
      if (ver) return abrirFichaPatrimonio(ver.dataset.id);
      const ed = e.target.closest(".pat-btn-editar");
      if (ed) return abrirModalPatrimonio(ed.dataset.id);
      const ex = e.target.closest(".pat-btn-excluir");
      if (ex && confirm("Excluir este patrimônio permanentemente?")) {
        try {
          await patFetch(`/patrimonio/patrimonios/${ex.dataset.id}`, { method: "DELETE" });
          patToast("Patrimônio excluído.", "success");
          loadPatrimonios();
        } catch (err) {
          patToast(err.message, "error");
        }
      }
    });

    document.getElementById("patFormAnexosLista")?.addEventListener("click", async (e) => {
      const btn = e.target.closest(".pat-del-arquivo");
      if (!btn || !confirm("Remover este arquivo?")) return;
      try {
        await patFetch(`/patrimonio/arquivos/${btn.dataset.tipo}/${btn.dataset.id}`, { method: "DELETE" });
        const id = document.getElementById("patFormId")?.value;
        if (id) {
          const res = await patFetch(`/patrimonio/patrimonios/${id}`);
          renderAnexosLista(res.fotos, res.documentos);
        }
      } catch (err) {
        patToast(err.message, "error");
      }
    });

    document.getElementById("patrimonioForm")?.addEventListener("submit", async (e) => {
      e.preventDefault();
      patFormFeedback("");
      if (!patUsuarioId()) {
        const msg = "Sessão expirada. Faça login novamente.";
        patFormFeedback(msg, "error");
        patToast(msg, "error");
        return;
      }
      const form = e.target;
      const nomeEl = document.getElementById("patFormNome") || form.nome;
      const nome = (nomeEl?.value || "").trim();
      if (!nome) {
        const msg = "Informe o nome do patrimônio (campo no topo do formulário).";
        patFormFeedback(msg, "error");
        patToast(msg, "error");
        nomeEl?.focus();
        return;
      }
      const submitBtn = document.getElementById("patFormSubmitBtn");
      const id = document.getElementById("patFormId")?.value;
      const fd = new FormData(form);
      fd.delete("patFormId");
      patAplicarMoedasNoFormData(form, fd);
      fd.set("dados_especificos", JSON.stringify(coletarDadosEspecificos()));
      const docs = form.querySelector('input[name="documentos"]');
      if (docs?.files?.length) {
        [...docs.files].forEach((file) => {
          fd.append("documentos[]", file);
        });
        fd.set("documento_tipo", document.getElementById("patFormDocTipo")?.value || "outro");
      }
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = "Salvando…";
      }
      try {
        if (id) await patFetchForm(`/patrimonio/patrimonios/${id}`, fd, "POST");
        else await patFetchForm("/patrimonio/patrimonios", fd, "POST");
        patFormFeedback("");
        patToast("Patrimônio salvo com sucesso.", "success");
        document.getElementById("patrimonioModal")?.classList.remove("active");
        loadPatrimonios();
      } catch (err) {
        const msg = err?.message || "Não foi possível salvar o patrimônio.";
        patFormFeedback(msg, "error");
        patToast(msg, "error");
        console.error("[Patrimônio] salvar:", err);
      } finally {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Salvar patrimônio";
        }
      }
    });

    const closePatModal = () => document.getElementById("patrimonioModal")?.classList.remove("active");
    document.getElementById("closePatrimonioModal")?.addEventListener("click", closePatModal);
    document.getElementById("patrimonioModal")?.addEventListener("click", (ev) => { if (ev.target.id === "patrimonioModal") closePatModal(); });

    document.getElementById("closePatQrModal")?.addEventListener("click", () => document.getElementById("patQrModal")?.classList.remove("active"));
    document.getElementById("patQrImprimir")?.addEventListener("click", () => window.print());

    document.getElementById("closePatFichaModal")?.addEventListener("click", () => document.getElementById("patFichaModal")?.classList.remove("active"));
    document.getElementById("closePatFichaModal2")?.addEventListener("click", () => document.getElementById("patFichaModal")?.classList.remove("active"));
    document.getElementById("patFichaPdf")?.addEventListener("click", () => {
      if (patState.fichaId) patDownloadRelatorio(`ficha/${patState.fichaId}`, "pdf").catch((e) => patToast(e.message, "error"));
    });

    document.getElementById("patCatBtnNova")?.addEventListener("click", () => {
      document.getElementById("patCatId").value = "";
      document.getElementById("patCatModalTitle").textContent = "Nova categoria";
      document.getElementById("patCatForm")?.reset();
      document.getElementById("patCatModal")?.classList.add("active");
    });
    document.getElementById("closePatCatModal")?.addEventListener("click", () => document.getElementById("patCatModal")?.classList.remove("active"));
    document.getElementById("patCatForm")?.addEventListener("submit", async (e) => {
      e.preventDefault();
      const id = document.getElementById("patCatId")?.value;
      const body = {
        nome: document.getElementById("patCatNome")?.value,
        icone: document.getElementById("patCatIcone")?.value,
        tipo_campos: document.getElementById("patCatTipo")?.value,
        ordem: parseInt(document.getElementById("patCatOrdem")?.value || "50", 10),
        ativo: true,
      };
      try {
        if (id) await patFetch(`/patrimonio/categorias/${id}`, { method: "PUT", body: JSON.stringify(body) });
        else await patFetch("/patrimonio/categorias", { method: "POST", body: JSON.stringify(body) });
        document.getElementById("patCatModal")?.classList.remove("active");
        loadPatrimonioCategorias();
        patToast("Categoria salva.", "success");
      } catch (err) {
        patToast(err.message, "error");
      }
    });
    document.getElementById("patSetorBtnNova")?.addEventListener("click", () => {
      document.getElementById("patSetorId").value = "";
      document.getElementById("patSetorModalTitle").textContent = "Novo setor";
      document.getElementById("patSetorForm")?.reset();
      const ativo = document.getElementById("patSetorAtivo");
      if (ativo) ativo.checked = true;
      document.getElementById("patSetorModal")?.classList.add("active");
    });
    document.getElementById("closePatSetorModal")?.addEventListener("click", () => document.getElementById("patSetorModal")?.classList.remove("active"));
    document.getElementById("patSetorForm")?.addEventListener("submit", async (e) => {
      e.preventDefault();
      const id = document.getElementById("patSetorId")?.value;
      const body = {
        nome: document.getElementById("patSetorNome")?.value?.trim(),
        descricao: document.getElementById("patSetorDescricao")?.value?.trim() || null,
        ordem: parseInt(document.getElementById("patSetorOrdem")?.value || "50", 10),
        ativo: document.getElementById("patSetorAtivo")?.checked ?? true,
      };
      try {
        if (id) await patFetch(`/patrimonio/setores/${id}`, { method: "PUT", body: JSON.stringify(body) });
        else await patFetch("/patrimonio/setores", { method: "POST", body: JSON.stringify(body) });
        document.getElementById("patSetorModal")?.classList.remove("active");
        patState.setores = [];
        await loadPatSetores(true, true);
        populatePatSetorSelects();
        loadPatrimonioConfiguracoes();
        patToast("Setor salvo.", "success");
      } catch (err) {
        patToast(err.message, "error");
      }
    });
    document.getElementById("patSetorTbody")?.addEventListener("click", async (e) => {
      const edit = e.target.closest(".pat-setor-editar");
      if (edit) {
        document.getElementById("patSetorId").value = edit.dataset.id;
        document.getElementById("patSetorNome").value = edit.dataset.nome || "";
        document.getElementById("patSetorDescricao").value = edit.dataset.descricao || "";
        document.getElementById("patSetorOrdem").value = edit.dataset.ordem || "50";
        const ativo = document.getElementById("patSetorAtivo");
        if (ativo) ativo.checked = edit.dataset.ativo !== "0";
        document.getElementById("patSetorModalTitle").textContent = "Editar setor";
        document.getElementById("patSetorModal").classList.add("active");
        return;
      }
      const del = e.target.closest(".pat-setor-excluir");
      if (del && confirm("Excluir este setor?")) {
        try {
          await patFetch(`/patrimonio/setores/${del.dataset.id}`, { method: "DELETE" });
          patState.setores = [];
          await loadPatSetores(true, true);
          populatePatSetorSelects();
          loadPatrimonioConfiguracoes();
          patToast("Setor excluído.", "success");
        } catch (err) {
          patToast(err.message, "error");
        }
      }
    });

    document.getElementById("patCatTbody")?.addEventListener("click", async (e) => {
      const edit = e.target.closest(".pat-cat-editar");
      if (edit) {
        document.getElementById("patCatId").value = edit.dataset.id;
        document.getElementById("patCatNome").value = edit.dataset.nome || "";
        document.getElementById("patCatIcone").value = edit.dataset.icone || "";
        document.getElementById("patCatTipo").value = edit.dataset.tipo || "geral";
        document.getElementById("patCatOrdem").value = edit.dataset.ordem || "50";
        document.getElementById("patCatModalTitle").textContent = "Editar categoria";
        document.getElementById("patCatModal").classList.add("active");
        return;
      }
      const btn = e.target.closest(".pat-cat-excluir");
      if (!btn || !confirm("Excluir categoria?")) return;
      try {
        await patFetch(`/patrimonio/categorias/${btn.dataset.id}`, { method: "DELETE" });
        loadPatrimonioCategorias();
      } catch (err) {
        patToast(err.message, "error");
      }
    });

    document.getElementById("patMovBtnNova")?.addEventListener("click", async () => {
      await Promise.all([
        populatePatUnidades(["patMovUnidadeDest"]),
        populatePatSelectPatrimonios(["patMovPatrimonio"]),
      ]);
      document.getElementById("patMovForm")?.reset();
      document.getElementById("patMovModal")?.classList.add("active");
    });
    document.getElementById("closePatMovModal")?.addEventListener("click", () => document.getElementById("patMovModal")?.classList.remove("active"));
    document.getElementById("patMovForm")?.addEventListener("submit", async (e) => {
      e.preventDefault();
      const pid = document.getElementById("patMovPatrimonio")?.value;
      if (!pid) return patToast("Selecione o patrimônio.", "error");
      try {
        await patFetch(`/patrimonio/patrimonios/${pid}/movimentacoes`, {
          method: "POST",
          body: JSON.stringify({
            tipo: document.getElementById("patMovTipo")?.value,
            unidade_destino_id: document.getElementById("patMovUnidadeDest")?.value || null,
            responsavel_anterior: document.getElementById("patMovRespAnt")?.value,
            responsavel_novo: document.getElementById("patMovRespNovo")?.value,
            observacao: document.getElementById("patMovObs")?.value,
          }),
        });
        document.getElementById("patMovModal")?.classList.remove("active");
        loadPatrimonioMovimentacoes();
        patToast("Movimentação registrada.", "success");
      } catch (err) {
        patToast(err.message, "error");
      }
    });

    document.getElementById("patManBtnNova")?.addEventListener("click", async () => {
      await populatePatSelectPatrimonios(["patManPatrimonio"]);
      const manForm = document.getElementById("patManForm");
      manForm?.reset();
      patSetupMoedaInputs(document.getElementById("patManModal"));
      const d = document.getElementById("patManData");
      if (d) d.value = new Date().toISOString().slice(0, 10);
      document.getElementById("patManModal")?.classList.add("active");
    });
    document.getElementById("closePatManModal")?.addEventListener("click", () => document.getElementById("patManModal")?.classList.remove("active"));
    document.getElementById("patManForm")?.addEventListener("submit", async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      fd.set("patrimonio_id", document.getElementById("patManPatrimonio")?.value || "");
      fd.set("tipo_manutencao", document.getElementById("patManTipo")?.value || "preventiva");
      fd.set("data_manutencao", document.getElementById("patManData")?.value || "");
      fd.set("proxima_manutencao", document.getElementById("patManProxima")?.value || "");
      fd.set("tecnico", document.getElementById("patManTecnico")?.value || "");
      const custoEl = document.getElementById("patManCusto");
      const custo = patLerMoeda(custoEl);
      fd.set("custo", custo != null && custo > 0 ? String(custo) : "");
      fd.set("descricao", document.getElementById("patManDesc")?.value || "");
      const anexo = document.getElementById("patManAnexo");
      if (anexo?.files?.[0]) fd.set("anexo", anexo.files[0]);
      try {
        await patFetchForm("/patrimonio/manutencoes", fd);
        document.getElementById("patManModal")?.classList.remove("active");
        loadPatrimonioManutencoes();
        patToast("Manutenção registrada.", "success");
      } catch (err) {
        patToast(err.message, "error");
      }
    });

    document.getElementById("patInvBtnNovo")?.addEventListener("click", async () => {
      await populatePatUnidades();
      document.getElementById("patInvTitulo").value = `Inventário ${new Date().toLocaleDateString("pt-BR")}`;
      document.getElementById("patInvModal")?.classList.add("active");
    });
    document.getElementById("closePatInvModal")?.addEventListener("click", () => document.getElementById("patInvModal")?.classList.remove("active"));
    document.getElementById("patInvForm")?.addEventListener("submit", async (e) => {
      e.preventDefault();
      try {
        const res = await patFetch("/patrimonio/inventario", {
          method: "POST",
          body: JSON.stringify({
            titulo: document.getElementById("patInvTitulo")?.value,
            unidade_id: document.getElementById("patInvUnidade")?.value || null,
          }),
        });
        document.getElementById("patInvModal")?.classList.remove("active");
        loadPatrimonioInventario();
        if (res.id) abrirInventarioItens(res.id, document.getElementById("patInvTitulo")?.value, "aberto");
        patToast("Inventário iniciado.", "success");
      } catch (err) {
        patToast(err.message, "error");
      }
    });

    document.getElementById("patInvListaTbody")?.addEventListener("click", async (e) => {
      const btn = e.target.closest(".pat-inv-abrir");
      if (!btn) return;
      await abrirInventarioItens(btn.dataset.id, btn.dataset.titulo, btn.dataset.status);
    });

    document.getElementById("patInvItensTbody")?.addEventListener("click", async (e) => {
      const btn = e.target.closest(".pat-inv-salvar");
      if (btn) {
        const itemId = btn.dataset.item;
        const qtd = document.querySelector(`.pat-inv-qtd[data-item="${itemId}"]`)?.value;
        const obs = document.querySelector(`.pat-inv-obs[data-item="${itemId}"]`)?.value;
        try {
          await patFetch(`/patrimonio/inventario/itens/${itemId}`, {
            method: "PUT",
            body: JSON.stringify({ qtd_encontrada: qtd, observacao: obs, localizacao: "" }),
          });
          const diffEl = document.querySelector(`.pat-inv-diff[data-item="${itemId}"]`);
          const qtdSis = parseInt(document.querySelector(`.pat-inv-qtd[data-item="${itemId}"]`)?.closest("tr")?.children[2]?.textContent || "0", 10);
          if (diffEl && qtd !== "") diffEl.textContent = String(parseInt(qtd, 10) - qtdSis);
          patToast("Item conferido.", "success");
        } catch (err) {
          patToast(err.message, "error");
        }
        return;
      }
    });

    document.getElementById("patInvItensTbody")?.addEventListener("change", async (e) => {
      const inp = e.target.closest(".pat-inv-foto-input");
      if (!inp?.files?.[0]) return;
      const fd = new FormData();
      fd.append("foto", inp.files[0]);
      try {
        await patFetchForm(`/patrimonio/inventario/itens/${inp.dataset.item}/foto`, fd);
        patToast("Foto do item salva.", "success");
      } catch (err) {
        patToast(err.message, "error");
      }
    });

    document.getElementById("patInvFechar")?.addEventListener("click", async () => {
      if (!patState.inventarioId || !confirm("Encerrar este inventário?")) return;
      try {
        await patFetch(`/patrimonio/inventario/${patState.inventarioId}/fechar`, { method: "POST" });
        loadPatrimonioInventario();
        document.getElementById("patInvItensCard")?.classList.add("hidden");
        patToast("Inventário encerrado.", "success");
      } catch (err) {
        patToast(err.message, "error");
      }
    });

    document.getElementById("patInvRelPdf")?.addEventListener("click", () => {
      if (!patState.inventarioId) return;
      patDownloadRelatorio(`inventario/${patState.inventarioId}`, "pdf").catch((e) => patToast(e.message, "error"));
    });
    document.getElementById("patInvRelCsv")?.addEventListener("click", () => {
      if (!patState.inventarioId) return;
      patDownloadRelatorio(`inventario/${patState.inventarioId}`, "csv").catch((e) => patToast(e.message, "error"));
    });

    document.getElementById("patRelGerar")?.addEventListener("click", () => patRelGerarPreview().catch((e) => patToast(e.message, "error")));
    document.getElementById("patRelPdf")?.addEventListener("click", async () => {
      try {
        await patDownloadRelatorio("filtrado", "pdf", patRelColetarFiltros());
      } catch (e) {
        if (!patState.lastRelatorio?.itens?.length) {
          patToast("Clique em Visualizar relatório antes, ou atualize o servidor (rota filtrado.pdf).", "error");
          return;
        }
        patToast(e.message || "PDF indisponível no servidor. Use CSV ou atualize o backend.", "error");
      }
    });
    document.getElementById("patRelCsv")?.addEventListener("click", async () => {
      try {
        await patDownloadRelatorio("filtrado", "csv", patRelColetarFiltros());
      } catch (e) {
        if (patState.lastRelatorio?.itens?.length) {
          patRelExportarCsvLocal();
          patToast("CSV gerado localmente.", "success");
        } else {
          patToast(e.message || "Gere o relatório antes de exportar.", "error");
        }
      }
    });
    document.getElementById("patRelLimpar")?.addEventListener("click", () => {
      ["patRelFiltroUnidade", "patRelFiltroCategoria", "patRelFiltroSituacao", "patRelFiltroSetor", "patRelFiltroBusca"].forEach((id) => {
        const el = document.getElementById(id);
        if (el) el.value = "";
      });
      document.getElementById("patRelTotais")?.classList.add("hidden");
      document.getElementById("patRelGrupos")?.classList.add("hidden");
      document.getElementById("patRelResultadoCard")?.classList.add("hidden");
    });

    document.getElementById("patrimonioConfiguracoesSection")?.addEventListener("click", (e) => {
      const btn = e.target.closest(".pat-config-go");
      if (!btn?.dataset?.patSection) return;
      const sec = btn.dataset.patSection;
      if (typeof navigateTo === "function") {
        navigateTo(sec);
        if (sec === "patrimonioDashboard") loadPatrimonioDashboard?.().catch(() => {});
        else if (sec === "patrimonios") loadPatrimonios?.().catch(() => {});
        else if (sec === "patrimonioCategorias") loadPatrimonioCategorias?.().catch(() => {});
      }
    });

    document.querySelectorAll(".pat-relatorio-btn").forEach((btn) => {
      btn.addEventListener("click", async () => {
        const rel = btn.dataset.relatorio;
        const fmt = btn.dataset.formato || "csv";
        try {
          if (rel === "ficha") {
            const id = prompt("ID do patrimônio para a ficha:");
            if (!id) return;
            await patDownloadRelatorio(`ficha/${id}`, fmt);
          } else if (rel === "inventario") {
            const id = patState.inventarioId || prompt("ID do inventário:");
            if (!id) return;
            await patDownloadRelatorio(`inventario/${id}`, fmt);
          } else if (rel === "resumo") {
            await patDownloadRelatorio("resumo", "csv");
          } else {
            await patDownloadRelatorio(rel, fmt);
          }
        } catch (e) {
          patToast(e.message, "error");
        }
      });
    });

    const hash = window.location.hash || "";
    const m = hash.match(/qr=([a-zA-Z0-9]+)/);
    if (m) setTimeout(() => abrirQrPatrimonio(m[1]), 1000);
  }

  window.setupPatrimonioModule = function setupPatrimonioModule() {
    patrimonioBindOnce();
    patSetupMoedaInputs(document.getElementById("patrimonioModal"));
    patSetupMoedaInputs(document.getElementById("patManModal"));
    const menu = document.getElementById("patrimonioMenu");
    if (menu && menu.dataset.sasSubmenuToggleBound !== "1") {
      menu.dataset.sasSubmenuToggleBound = "1";
      menu.addEventListener("click", (ev) => {
        ev.preventDefault();
        menu.closest(".nav-submenu")?.classList.toggle("open");
      });
    }
  };
})();
