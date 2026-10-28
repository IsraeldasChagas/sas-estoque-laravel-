/**
 * Contagem de estoque — dashboard, nova contagem, realizar contagem, conferência,
 * aprovação de ajustes, histórico/relatórios e configurações.
 */
(function () {
  "use strict";

  const STATUS_INV = { rascunho: "Rascunho", em_contagem: "Em andamento", em_conferencia: "Em conferência", encerrado: "Encerrado" };
  const TIPO_INV = { completa: "Completa", parcial: "Parcial", rotativa: "Rotativa" };
  const STATUS_ITEM = {
    pendente: "Não contado",
    sem_divergencia: "Sem divergência",
    falta: "Falta de estoque",
    sobra: "Sobra de estoque",
    aguardando_recontagem: "Aguardando recontagem",
    aprovado: "Ajuste aprovado",
    rejeitado: "Ajuste rejeitado",
  };
  const RELATORIOS = [
    ["completo", "Inventário completo"],
    ["divergencias", "Produtos com divergências"],
    ["valores", "Perdas e sobras"],
    ["ajustes", "Histórico de ajustes"],
    ["acuracidade", "Acuracidade por unidade"],
    ["funcionarios", "Contagens por funcionário"],
    ["comparativo", "Comparativo entre inventários"],
  ];

  const st = {
    meta: null,
    metaPromise: null,
    charts: [],
    dash: { unidade_id: "", de: "", ate: "", categoria: "", usuario_id: "", status: "" },
    nova: null,
    realizarId: null,
    realizarInv: null,
    realizarBusca: "",
    realizarFiltro: "todos",
    amounts: {},
    notes: {},
    confInventario: "",
    aprovNotes: {},
    hist: { relatorio: "completo", unidade_id: "", inventario_id: "" },
    histRows: [],
  };

  const $ = (id) => document.getElementById(id);
  const esc = (v) =>
    typeof window.escapeHtml === "function"
      ? window.escapeHtml(v == null ? "" : String(v))
      : String(v == null ? "" : v).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
  const toast = (m, t) => window.showToast?.(m, t || "info");
  const api = (path, options) => {
    if (typeof window.fetchJSON !== "function") throw new Error("Conexão com a API indisponível.");
    return window.fetchJSON(`/contagem${path}`, options);
  };
  const post = (path, body) => api(path, { method: "POST", body: JSON.stringify(body || {}) });
  const qs = (obj) => {
    const p = new URLSearchParams();
    Object.entries(obj).forEach(([k, v]) => {
      if (v !== "" && v != null) p.set(k, v);
    });
    const s = p.toString();
    return s ? `?${s}` : "";
  };

  const numero = (v, casas = 3) => Number(v || 0).toLocaleString("pt-BR", { maximumFractionDigits: casas });
  const money = (v) => Number(v || 0).toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
  const unidadeMedida = (u) => (typeof window.normalizarUnidadeBase === "function" ? window.normalizarUnidadeBase(u) : u) || "";
  const qtd = (v, u) => (v == null ? "—" : `${numero(v)} ${unidadeMedida(u)}`.trim());
  const dataHora = (s) => {
    if (!s) return "—";
    const d = new Date(String(s).replace(" ", "T"));
    return Number.isNaN(d.getTime()) ? String(s) : d.toLocaleString("pt-BR");
  };
  const dia = (s) => (s ? String(s).slice(0, 10) : "");
  const diaBr = (s) => {
    const d = dia(s);
    return d ? d.split("-").reverse().join("/") : "—";
  };
  const pct = (a, b) => (b ? Math.round((a / b) * 1000) / 10 : null);

  function categoriaLabel(codigo) {
    if (!codigo) return "Sem categoria";
    const opt = [...(document.querySelector('#produtoForm select[name="categoria"]')?.options || [])].find(
      (o) => o.value && o.value.toUpperCase() === String(codigo).toUpperCase()
    );
    return opt ? opt.textContent.trim() : String(codigo);
  }

  function tom(status) {
    if (status === "sem_divergencia" || status === "aprovado") return "ok";
    if (status === "falta") return "danger";
    if (status === "sobra" || status === "aguardando_recontagem") return "warn";
    return "info";
  }
  const pillItem = (status) => `<span class="rh-cand-pill rh-cand-pill--${tom(status)}">${esc(STATUS_ITEM[status] || status)}</span>`;
  const pillInv = (status) => {
    const t = status === "encerrado" ? "ok" : status === "em_conferencia" ? "warn" : "info";
    return `<span class="rh-cand-pill rh-cand-pill--${t}">${esc(STATUS_INV[status] || status)}</span>`;
  };
  const linha = (rotulo, valor) => `<div class="rh-cand-card__row"><span>${esc(rotulo)}</span><strong>${valor}</strong></div>`;
  const vazio = (texto) => `<p class="rh-cand-empty">${esc(texto)}</p>`;
  const aviso = (texto) => `<div class="contagem-aviso">${esc(texto)}</div>`;

  function foto(item) {
    const url = item.foto && typeof window.getUsuarioFotoUrl === "function" ? window.getUsuarioFotoUrl(item.foto) : null;
    return url
      ? `<img src="${esc(url)}" alt="" class="usuarios-foto rh-cand-card__photo" loading="lazy" />`
      : '<div class="usuarios-foto usuarios-foto--placeholder rh-cand-card__photo pat-card-icone" aria-hidden="true">📦</div>';
  }

  function pagina(titulo, subtitulo, corpo) {
    return `<div class="caixa-page-wrap contagem-wrap">
      <div class="contagem-head"><h2>${esc(titulo)}</h2><p class="subtle-text">${esc(subtitulo)}</p></div>
      ${corpo}
    </div>`;
  }

  function carregando(rootId) {
    const root = $(rootId);
    if (root) root.innerHTML = `<div class="caixa-page-wrap contagem-wrap">${vazio("Carregando...")}</div>`;
    return root;
  }

  function erro(root, e) {
    if (root) root.innerHTML = `<div class="caixa-page-wrap contagem-wrap">${vazio(e?.message || "Falha ao carregar.")}</div>`;
  }

  async function meta(force = false) {
    if (st.meta && !force) return st.meta;
    if (!st.metaPromise || force) {
      st.metaPromise = api("/meta").then((m) => {
        st.meta = m;
        return m;
      }).finally(() => {
        st.metaPromise = null;
      });
    }
    return st.metaPromise;
  }

  const caps = () => st.meta?.capacidades || {};
  const opcoesUnidades = (sel, todas = "Todas") =>
    `<option value="">${esc(todas)}</option>` +
    (st.meta?.unidades || []).map((u) => `<option value="${u.id}"${String(sel) === String(u.id) ? " selected" : ""}>${esc(u.nome)}</option>`).join("");
  const opcoesCategorias = (sel, todas = "Todas") =>
    `<option value="">${esc(todas)}</option>` +
    (st.meta?.categorias || []).map((c) => `<option value="${esc(c)}"${String(sel).toUpperCase() === String(c).toUpperCase() ? " selected" : ""}>${esc(categoriaLabel(c))}</option>`).join("");

  function inventariosAbertosParaContar(lista) {
    return lista.filter((i) => i.status === "em_contagem" || (i.status === "em_conferencia" && i.aguardando_recontagem > 0));
  }

  // ——— Modal de justificativa ———

  function pedirJustificativa(titulo, subtitulo) {
    return new Promise((resolve) => {
      let modal = $("contagemJustModal");
      if (!modal) {
        modal = document.createElement("div");
        modal.className = "modal-backdrop";
        modal.id = "contagemJustModal";
        modal.innerHTML = `<div class="modal contagem-just-modal" role="dialog" aria-modal="true" aria-labelledby="contagemJustTitulo">
          <header><h2 id="contagemJustTitulo"></h2><button type="button" class="close-btn" data-just="cancelar" aria-label="Fechar">×</button></header>
          <p class="subtle-text" id="contagemJustSub"></p>
          <label>Justificativa<textarea id="contagemJustTexto" rows="3"></textarea></label>
          <footer>
            <button type="button" class="btn neutral" data-just="cancelar">Cancelar</button>
            <button type="button" class="btn primary" data-just="ok">Solicitar</button>
          </footer>
        </div>`;
        document.body.appendChild(modal);
      }
      $("contagemJustTitulo").textContent = titulo;
      $("contagemJustSub").textContent = subtitulo || "";
      const texto = $("contagemJustTexto");
      texto.value = "";
      const fechar = (valor) => {
        modal.classList.remove("active");
        modal.removeEventListener("click", onClick);
        resolve(valor);
      };
      const onClick = (ev) => {
        const acao = ev.target.closest("[data-just]")?.dataset.just;
        if (ev.target === modal || acao === "cancelar") fechar(null);
        else if (acao === "ok") {
          const v = texto.value.trim();
          if (!v) {
            toast("Informe a justificativa.", "warning");
            return;
          }
          fechar(v);
        }
      };
      modal.addEventListener("click", onClick);
      modal.classList.add("active");
      setTimeout(() => texto.focus(), 50);
    });
  }

  // ——— 1. Dashboard ———

  function destruirGraficos() {
    st.charts.forEach((c) => {
      try {
        c.destroy();
      } catch (_) {}
    });
    st.charts = [];
  }

  function grafico(canvasId, config) {
    const el = $(canvasId);
    if (!el || typeof window.Chart !== "function") return;
    st.charts.push(new window.Chart(el, config));
  }

  function montarPainel(lista) {
    const f = st.dash;
    const cat = f.categoria ? f.categoria.toUpperCase() : "";
    const uid = f.usuario_id ? Number(f.usuario_id) : null;
    const invs = lista.filter((inv) => {
      if (uid && inv.aberto_por !== uid && !inv.itens.some((it) => it.lancamentos.some((l) => l.usuario_id === uid))) return false;
      if (cat && String(inv.categoria || "").toUpperCase() !== cat && !inv.itens.some((it) => String(it.categoria || "").toUpperCase() === cat)) return false;
      return true;
    });
    const rows = invs.flatMap((inv) =>
      inv.itens.filter((it) => it.contado && (!cat || String(it.categoria || "").toUpperCase() === cat)).map((it) => ({ inv, it }))
    );
    const matching = rows.filter((r) => r.it.status === "sem_divergencia").length;
    const divergent = rows.filter((r) => r.it.status !== "sem_divergencia" && r.it.status !== "pendente").length;
    let perda = 0;
    let sobra = 0;
    const porCategoria = new Map();
    rows.forEach(({ it }) => {
      if (it.status === "rejeitado" || it.status === "aguardando_recontagem" || it.valor == null) return;
      const b = porCategoria.get(categoriaLabel(it.categoria)) || { perda: 0, sobra: 0 };
      if (it.valor < 0) {
        perda += Math.abs(it.valor);
        b.perda += Math.abs(it.valor);
      } else if (it.valor > 0) {
        sobra += it.valor;
        b.sobra += it.valor;
      }
      porCategoria.set(categoriaLabel(it.categoria), b);
    });
    const porUnidade = new Map();
    rows.forEach(({ inv, it }) => {
      if (it.status === "sem_divergencia" || it.status === "pendente") return;
      porUnidade.set(inv.unidade_nome, (porUnidade.get(inv.unidade_nome) || 0) + 1);
    });
    const acuracidade = invs
      .filter((inv) => inv.itens.some((it) => it.contado))
      .slice()
      .sort((a, b) => String(a.aberto_em).localeCompare(String(b.aberto_em)))
      .map((inv) => {
        const c = inv.itens.filter((it) => it.contado);
        return { label: inv.numero, valor: pct(c.filter((it) => it.status === "sem_divergencia").length, c.length) || 0 };
      });
    return {
      inventarios: invs.length,
      abertos: invs.filter((i) => i.status !== "encerrado").length,
      contados: rows.length,
      divergent,
      matching,
      acuracidade: pct(matching, rows.length),
      perda,
      sobra,
      aguardando: rows.filter((r) => r.it.status === "falta" || r.it.status === "sobra").length,
      porCategoria: [...porCategoria.entries()],
      porUnidade: [...porUnidade.entries()],
      linhaAcuracidade: acuracidade,
    };
  }

  async function loadDashboard() {
    const root = carregando("contagemDashboardRoot");
    try {
      await meta();
      const f = st.dash;
      const lista = await api(`/inventarios${qs({ detalhe: "1", unidade_id: f.unidade_id, de: f.de, ate: f.ate, status: f.status })}`);
      const b = montarPainel(lista);
      const usuarios = st.meta.usuarios || [];
      const cards = [
        ["Inventários", String(b.inventarios), "No filtro"],
        ["Em andamento", String(b.abertos), "Rascunho, contagem ou conferência"],
        ["Produtos contados", String(b.contados), "Com quantidade lançada"],
        ["Com divergência", String(b.divergent), "Contados e diferentes do saldo"],
        ["Sem divergência", String(b.matching), "Contados e iguais ao saldo"],
        ["Acuracidade", b.acuracidade == null ? "—" : `${numero(b.acuracidade, 1)}%`, "Sem divergência ÷ itens contados"],
        ["Perdas", money(b.perda), "Falta pelo custo da referência"],
        ["Sobras", money(b.sobra), "Sobra pelo custo da referência"],
        ["Aguardando aprovação", String(b.aguardando), "Faltas e sobras sem decisão"],
      ];
      root.innerHTML = pagina(
        "Dashboard de inventário",
        "Indicadores das contagens já registradas. Item sem quantidade não entra na conta.",
        `<form class="table-card contagem-filtros" id="contagemDashFiltros">
          <header>Filtros</header>
          <div class="filters-grid">
            <label>Unidade<select name="unidade_id">${opcoesUnidades(f.unidade_id)}</select></label>
            <label>De<input type="date" name="de" value="${esc(f.de)}" /></label>
            <label>Até<input type="date" name="ate" value="${esc(f.ate)}" /></label>
            <label>Categoria<select name="categoria">${opcoesCategorias(f.categoria)}</select></label>
            <label>Responsável<select name="usuario_id"><option value="">Todos</option>${usuarios.map((u) => `<option value="${u.id}"${String(f.usuario_id) === String(u.id) ? " selected" : ""}>${esc(u.nome)}</option>`).join("")}</select></label>
            <label>Situação<select name="status"><option value="">Todas</option>${Object.entries(STATUS_INV).map(([k, v]) => `<option value="${k}"${f.status === k ? " selected" : ""}>${esc(v)}</option>`).join("")}</select></label>
          </div>
        </form>
        <div class="contagem-stats">${cards.map(([l, v, h]) => `<article class="contagem-stat"><span>${esc(l)}</span><strong>${esc(v)}</strong><em>${esc(h)}</em></article>`).join("")}</div>
        <div class="contagem-graficos">
          <article class="contagem-grafico"><h3>Com e sem divergência</h3>${b.contados ? '<div class="contagem-grafico__box"><canvas id="contGrafPizza"></canvas></div>' : vazio("Nenhum produto contado no filtro.")}</article>
          <article class="contagem-grafico"><h3>Perdas e sobras por categoria</h3>${b.porCategoria.length ? '<div class="contagem-grafico__box"><canvas id="contGrafCategoria"></canvas></div>' : vazio("Sem divergência financeira no filtro.")}</article>
          <article class="contagem-grafico"><h3>Evolução da acuracidade</h3>${b.linhaAcuracidade.length ? '<div class="contagem-grafico__box"><canvas id="contGrafAcuracidade"></canvas></div>' : vazio("Ainda não há contagem com item lançado.")}</article>
          <article class="contagem-grafico"><h3>Divergências por unidade</h3>${b.porUnidade.length ? '<div class="contagem-grafico__box"><canvas id="contGrafUnidade"></canvas></div>' : vazio("Nenhuma divergência no filtro.")}</article>
        </div>`
      );
      $("contagemDashFiltros")?.addEventListener("change", (ev) => {
        const el = ev.target;
        if (!el.name) return;
        st.dash[el.name] = el.value;
        loadDashboard();
      });
      destruirGraficos();
      const opts = { responsive: true, maintainAspectRatio: false };
      grafico("contGrafPizza", {
        type: "doughnut",
        data: { labels: ["Sem divergência", "Com divergência"], datasets: [{ data: [b.matching, b.divergent], backgroundColor: ["#16a34a", "#dc2626"] }] },
        options: opts,
      });
      grafico("contGrafCategoria", {
        type: "bar",
        data: {
          labels: b.porCategoria.map(([c]) => c),
          datasets: [
            { label: "Perda", data: b.porCategoria.map(([, v]) => Math.round(v.perda * 100) / 100), backgroundColor: "#dc2626" },
            { label: "Sobra", data: b.porCategoria.map(([, v]) => Math.round(v.sobra * 100) / 100), backgroundColor: "#16a34a" },
          ],
        },
        options: opts,
      });
      grafico("contGrafAcuracidade", {
        type: "line",
        data: { labels: b.linhaAcuracidade.map((x) => x.label), datasets: [{ label: "Acuracidade %", data: b.linhaAcuracidade.map((x) => x.valor), borderColor: "#0f766e", tension: 0.25 }] },
        options: { ...opts, scales: { y: { min: 0, max: 100 } } },
      });
      grafico("contGrafUnidade", {
        type: "bar",
        data: { labels: b.porUnidade.map(([n]) => n), datasets: [{ label: "Divergências", data: b.porUnidade.map(([, v]) => v), backgroundColor: "#1e3a8a" }] },
        options: { ...opts, scales: { y: { ticks: { precision: 0 } } } },
      });
    } catch (e) {
      erro(root, e);
    }
  }

  // ——— 2. Nova contagem ———

  function novaVazia() {
    return {
      id: null,
      numero: "",
      unidade_id: (st.meta?.unidades || []).length === 1 ? String(st.meta.unidades[0].id) : "",
      tipo: "completa",
      categoria: "",
      produtos: new Set(),
      equipe: new Set(),
      cega: !!st.meta?.config?.cega_padrao,
      observacoes: "",
      listaProdutos: [],
      listaProdutosUnidade: "",
      buscaProduto: "",
      buscaEquipe: "",
    };
  }

  async function carregarProdutosNova() {
    const n = st.nova;
    if (n.tipo !== "parcial" || !n.unidade_id || n.listaProdutosUnidade === n.unidade_id) return;
    n.listaProdutos = await api(`/produtos${qs({ unidade_id: n.unidade_id })}`);
    n.listaProdutosUnidade = n.unidade_id;
  }

  function htmlProdutosNova() {
    const n = st.nova;
    if (!n.unidade_id) return vazio("Escolha a unidade para listar os produtos.");
    const termo = n.buscaProduto.trim().toLowerCase();
    const lista = n.listaProdutos.filter((p) => !termo || `${p.nome} ${p.codigo || ""}`.toLowerCase().includes(termo));
    lista.sort((a, b) => Number(b.na_unidade) - Number(a.na_unidade) || String(a.nome).localeCompare(String(b.nome), "pt-BR"));
    return `<p class="subtle-text contagem-selecionados">${n.produtos.size} produto(s) selecionado(s)</p>
      <div class="contagem-checklist">${lista.length ? lista.map((p) => `<label><input type="checkbox" data-nova-prod="${p.id}"${n.produtos.has(p.id) ? " checked" : ""} /> ${esc(p.nome)} <small>${esc([p.codigo, categoriaLabel(p.categoria), unidadeMedida(p.unidade_base), p.na_unidade ? "" : "outra unidade"].filter(Boolean).join(" · "))}</small></label>`).join("") : vazio("Nenhum produto encontrado.")}</div>`;
  }

  function funcaoLabel(perfil) {
    const chave = String(perfil || "").trim().toUpperCase();
    const mapa = typeof PERFIL_LABELS !== "undefined" ? PERFIL_LABELS : {};
    return mapa[chave] || (chave ? chave.charAt(0) + chave.slice(1).toLowerCase().replace(/_/g, " ") : "Sem função");
  }

  function htmlEquipeNova() {
    const n = st.nova;
    const termo = n.buscaEquipe.trim().toLowerCase();
    const eu = st.meta?.usuario_id;
    const grupos = new Map();
    (st.meta?.usuarios || [])
      .filter((u) => !termo || `${u.nome} ${funcaoLabel(u.perfil)}`.toLowerCase().includes(termo))
      .forEach((u) => {
        const funcao = funcaoLabel(u.perfil);
        if (!grupos.has(funcao)) grupos.set(funcao, []);
        grupos.get(funcao).push(u);
      });
    if (!grupos.size) return vazio("Nenhum usuário encontrado.");
    return `<div class="contagem-equipe">${[...grupos.entries()]
      .sort((a, b) => a[0].localeCompare(b[0], "pt-BR"))
      .map(([funcao, usuarios]) => `<div class="contagem-equipe__grupo">
        <p class="contagem-equipe__funcao">${esc(funcao)} <small>(${usuarios.length})</small></p>
        ${usuarios
          .sort((a, b) => String(a.nome).localeCompare(String(b.nome), "pt-BR"))
          .map((u) => `<label class="contagem-equipe__usuario"><input type="checkbox" data-nova-equipe="${u.id}"${u.id === eu || n.equipe.has(u.id) ? " checked" : ""}${u.id === eu ? " disabled" : ""} /> ${esc(u.nome)}${u.id === eu ? " <small>· você</small>" : ""}</label>`)
          .join("")}
      </div>`)
      .join("")}</div>`;
  }

  function renderNovaDinamico() {
    const n = st.nova;
    const catWrap = $("contagemNovaCatWrap");
    if (catWrap) catWrap.classList.toggle("hidden", n.tipo !== "rotativa");
    const prodWrap = $("contagemNovaProdWrap");
    if (prodWrap) prodWrap.classList.toggle("hidden", n.tipo !== "parcial");
    const prodList = $("contagemNovaProdList");
    if (prodList && n.tipo === "parcial") prodList.innerHTML = htmlProdutosNova();
    const eq = $("contagemNovaEquipeList");
    if (eq) eq.innerHTML = htmlEquipeNova();
  }

  function payloadNova() {
    const n = st.nova;
    return {
      id: n.id || undefined,
      unidade_id: n.unidade_id ? Number(n.unidade_id) : null,
      tipo: n.tipo,
      categoria: n.categoria,
      produto_ids: [...n.produtos],
      equipe_ids: [...n.equipe],
      cega: n.cega,
      observacoes: n.observacoes,
    };
  }

  async function salvarNova(iniciar) {
    try {
      const r = await post("/inventarios", payloadNova());
      const inv = r.inventario;
      st.nova.id = inv.id;
      st.nova.numero = inv.numero;
      if (!iniciar) {
        toast(r.message || "Rascunho salvo.", "success");
        await loadNova(true);
        return;
      }
      const ini = await post(`/inventarios/${inv.id}/iniciar`);
      toast(ini.message || "Contagem iniciada.", "success");
      st.nova = null;
      abrirRealizar(inv.id);
    } catch (e) {
      toast(e?.message || "Não foi possível salvar.", "error");
    }
  }

  function editarRascunho(inv) {
    st.nova = {
      ...novaVazia(),
      id: inv.id,
      numero: inv.numero,
      unidade_id: String(inv.unidade_id),
      tipo: inv.tipo,
      categoria: inv.categoria || "",
      produtos: new Set(inv.produto_ids || []),
      equipe: new Set((inv.equipe_ids || []).filter((id) => id !== st.meta?.usuario_id)),
      cega: !!inv.cega,
      observacoes: inv.observacoes || "",
    };
    loadNova(true);
  }

  async function loadNova(manter = false) {
    const root = carregando("contagemNovaRoot");
    try {
      await meta();
      if (!st.nova || !manter) st.nova = st.nova && st.nova.id ? st.nova : novaVazia();
      await carregarProdutosNova();
      const rascunhos = await api(`/inventarios${qs({ status: "rascunho" })}`);
      const n = st.nova;
      const pode = !!caps().abrir;
      root.innerHTML = pagina(
        "Nova contagem",
        "O número é gerado ao salvar. O saldo de referência só é gravado quando a contagem inicia.",
        `${pode ? "" : aviso("Seu acesso pode consultar, mas não abrir contagem.")}
        <form class="table-card contagem-form" id="contagemNovaForm" autocomplete="off">
          <header>${n.id ? `Rascunho ${esc(n.numero)}` : "Dados da contagem"}</header>
          <div class="filters-grid">
            <label>Número<input type="text" value="${esc(n.numero || "A definir ao salvar")}" readonly /></label>
            <label>Unidade<select name="unidade_id">${opcoesUnidades(n.unidade_id, "Selecione")}</select></label>
            <label>Tipo<select name="tipo">
              <option value="completa"${n.tipo === "completa" ? " selected" : ""}>Completa — todos os produtos da unidade</option>
              <option value="parcial"${n.tipo === "parcial" ? " selected" : ""}>Parcial — produtos escolhidos</option>
              <option value="rotativa"${n.tipo === "rotativa" ? " selected" : ""}>Rotativa — uma categoria</option>
            </select></label>
            <label id="contagemNovaCatWrap" class="${n.tipo === "rotativa" ? "" : "hidden"}">Categoria<select name="categoria">${opcoesCategorias(n.categoria, "Selecione")}</select></label>
          </div>
          <div id="contagemNovaProdWrap" class="contagem-bloco ${n.tipo === "parcial" ? "" : "hidden"}">
            <p class="contagem-bloco__titulo">Produtos</p>
            <input type="search" name="busca_produto" placeholder="Pesquisar produto por nome ou código" value="${esc(n.buscaProduto)}" />
            <div id="contagemNovaProdList"></div>
          </div>
          <div class="contagem-bloco">
            <p class="contagem-bloco__titulo">Equipe</p>
            <p class="subtle-text">Quem pode lançar a quantidade. Você entra automaticamente.</p>
            <input type="search" name="busca_equipe" placeholder="Pesquisar usuário" value="${esc(n.buscaEquipe)}" />
            <div id="contagemNovaEquipeList"></div>
          </div>
          <label class="contagem-check"><input type="checkbox" name="cega"${n.cega ? " checked" : ""} /> Contagem cega — quem conta não vê o saldo do sistema</label>
          <label class="contagem-obs">Observações<textarea name="observacoes" rows="3">${esc(n.observacoes)}</textarea></label>
          <div class="rh-cand-card__actions">
            <button type="button" class="btn neutral" data-nova="salvar"${pode ? "" : " disabled"}>Salvar rascunho</button>
            <button type="button" class="btn primary" data-nova="iniciar"${pode ? "" : " disabled"}>Iniciar contagem</button>
            ${n.id ? '<button type="button" class="btn neutral" data-nova="limpar">Nova em branco</button>' : ""}
          </div>
        </form>
        <div class="table-card contagem-lista">
          <header>Rascunhos</header>
          <div class="rh-cand-list">${rascunhos.length ? rascunhos.map((inv) => `<article class="rh-cand-card">
            <div class="rh-cand-card__head">
              <div><h3 class="rh-cand-card__name">${esc(inv.numero)}</h3><p class="rh-cand-card__sub">${esc(inv.unidade_nome)} · ${esc(TIPO_INV[inv.tipo] || inv.tipo)}${inv.cega ? " · cega" : ""}</p></div>
              ${pillInv(inv.status)}
            </div>
            <div class="rh-cand-card__meta">
              ${linha("Aberto por", esc(inv.aberto_por_nome || "—"))}
              ${linha("Aberto em", esc(dataHora(inv.aberto_em)))}
              ${linha("Equipe", esc(inv.equipe.join(", ") || "—"))}
              ${inv.categoria ? linha("Categoria", esc(categoriaLabel(inv.categoria))) : ""}
              ${inv.tipo === "parcial" ? linha("Produtos", String(inv.produto_ids.length)) : ""}
            </div>
            <div class="rh-cand-card__actions">
              <button type="button" class="btn primary" data-rasc="editar" data-id="${inv.id}">Editar</button>
              <button type="button" class="btn btn-rh-toggle" data-rasc="iniciar" data-id="${inv.id}"${pode ? "" : " disabled"}>Iniciar</button>
              <button type="button" class="btn" style="background:#b71c1c;color:#fff;" data-rasc="excluir" data-id="${inv.id}">Excluir</button>
            </div>
          </article>`).join("") : vazio("Nenhum rascunho salvo.")}</div>
        </div>`
      );
      renderNovaDinamico();

      const form = $("contagemNovaForm");
      form.addEventListener("change", async (ev) => {
        const el = ev.target;
        if (el.dataset.novaProd) {
          const id = Number(el.dataset.novaProd);
          el.checked ? st.nova.produtos.add(id) : st.nova.produtos.delete(id);
          const sel = root.querySelector(".contagem-selecionados");
          if (sel) sel.textContent = `${st.nova.produtos.size} produto(s) selecionado(s)`;
          return;
        }
        if (el.dataset.novaEquipe) {
          const id = Number(el.dataset.novaEquipe);
          el.checked ? st.nova.equipe.add(id) : st.nova.equipe.delete(id);
          return;
        }
        if (el.name === "cega") st.nova.cega = el.checked;
        else if (el.name === "unidade_id" || el.name === "tipo" || el.name === "categoria") {
          st.nova[el.name] = el.value;
          if (el.name === "unidade_id") st.nova.produtos.clear();
          try {
            await carregarProdutosNova();
          } catch (e) {
            toast(e?.message || "Falha ao listar produtos.", "error");
          }
          renderNovaDinamico();
        }
      });
      form.addEventListener("input", (ev) => {
        const el = ev.target;
        if (el.name === "observacoes") st.nova.observacoes = el.value;
        else if (el.name === "busca_produto") {
          st.nova.buscaProduto = el.value;
          $("contagemNovaProdList").innerHTML = htmlProdutosNova();
        } else if (el.name === "busca_equipe") {
          st.nova.buscaEquipe = el.value;
          $("contagemNovaEquipeList").innerHTML = htmlEquipeNova();
        }
      });
      form.addEventListener("click", (ev) => {
        const acao = ev.target.closest("[data-nova]")?.dataset.nova;
        if (acao === "salvar") salvarNova(false);
        else if (acao === "iniciar") salvarNova(true);
        else if (acao === "limpar") {
          st.nova = novaVazia();
          loadNova(true);
        }
      });
      root.querySelector(".contagem-lista")?.addEventListener("click", async (ev) => {
        const btn = ev.target.closest("[data-rasc]");
        if (!btn) return;
        const inv = rascunhos.find((x) => String(x.id) === btn.dataset.id);
        if (!inv) return;
        try {
          if (btn.dataset.rasc === "editar") editarRascunho(inv);
          else if (btn.dataset.rasc === "iniciar") {
            const r = await post(`/inventarios/${inv.id}/iniciar`);
            toast(r.message, "success");
            abrirRealizar(inv.id);
          } else if (btn.dataset.rasc === "excluir") {
            if (!confirm(`Excluir o rascunho ${inv.numero}?`)) return;
            const r = await api(`/inventarios/${inv.id}`, { method: "DELETE" });
            toast(r.message, "success");
            if (st.nova?.id === inv.id) st.nova = novaVazia();
            loadNova(true);
          }
        } catch (e) {
          toast(e?.message || "Falha na operação.", "error");
        }
      });
    } catch (e) {
      erro(root, e);
    }
  }

  // ——— 3. Realizar contagem ———

  function abrirRealizar(id) {
    st.realizarId = id;
    st.realizarInv = null;
    st.amounts = {};
    st.notes = {};
    st.realizarBusca = "";
    st.realizarFiltro = "todos";
    if (typeof window.navigateTo === "function") window.navigateTo("contagemRealizar");
    else loadRealizar();
  }

  function podeLancar(inv, it) {
    const c = caps();
    if (!(c.contar || c.conferir) || !(inv.na_equipe || c.conferir)) return false;
    if (inv.status === "em_contagem") return true;
    return inv.status === "em_conferencia" && it.status === "aguardando_recontagem";
  }

  function htmlItemRealizar(inv, it) {
    const recontagem = it.status === "aguardando_recontagem";
    const pode = podeLancar(inv, it);
    const info = [];
    if (inv.revelar && it.contado) info.push(linha("Última quantidade", esc(qtd(it.fisico, it.unidade_base))));
    if (inv.revelar) info.push(linha("Saldo do sistema", esc(qtd(it.sistema, it.unidade_base))));
    if (!inv.revelar && it.minha_quantidade != null) info.push(linha("Sua última quantidade", esc(qtd(it.minha_quantidade, it.unidade_base))));
    if (it.ultimo_usuario) info.push(linha("Contado por", esc(it.ultimo_usuario)));
    return `<article class="rh-cand-card" data-item="${it.produto_id}">
      <div class="rh-cand-card__top">
        ${foto(it)}
        <div class="rh-cand-card__main">
          <div class="rh-cand-card__head">
            <div><h3 class="rh-cand-card__name">${esc(it.produto_nome)}</h3><p class="rh-cand-card__sub">${esc([it.codigo, categoriaLabel(it.categoria), unidadeMedida(it.unidade_base)].filter(Boolean).join(" · "))}</p></div>
            ${it.contado && it.status === "pendente" ? '<span class="rh-cand-pill rh-cand-pill--info">Contado</span>' : pillItem(it.status)}
          </div>
          <div class="rh-cand-card__meta">${info.join("")}</div>
          ${recontagem ? '<p class="contagem-hint">Recontagem pedida. A quantidade anterior continua no histórico.</p>' : ""}
        </div>
      </div>
      ${pode ? `<div class="contagem-lancar">
        <input type="text" inputmode="decimal" data-qtd="${it.produto_id}" placeholder="Quantidade encontrada${it.aceita_decimal ? "" : " (inteira)"}" value="${esc(st.amounts[it.produto_id] || "")}" />
        <input type="text" data-nota="${it.produto_id}" placeholder="${recontagem ? "Justificativa da recontagem" : "Observação"}" value="${esc(st.notes[it.produto_id] || "")}" />
      </div>
      <div class="rh-cand-card__actions"><button type="button" class="btn primary" data-salvar="${it.produto_id}">Salvar este produto</button></div>` : ""}
    </article>`;
  }

  function renderItensRealizar() {
    const inv = st.realizarInv;
    const lista = $("contagemRealizarItens");
    if (!inv || !lista) return;
    const termo = st.realizarBusca.trim().toLowerCase();
    const itens = inv.itens.filter((it) => {
      if (termo && !`${it.produto_nome} ${it.codigo || ""}`.toLowerCase().includes(termo)) return false;
      if (st.realizarFiltro === "pendentes") return !it.contado;
      if (st.realizarFiltro === "contados") return it.contado;
      if (st.realizarFiltro === "recontagem") return it.status === "aguardando_recontagem";
      return true;
    });
    lista.innerHTML = itens.length ? itens.map((it) => htmlItemRealizar(inv, it)).join("") : vazio("Nenhum produto neste filtro.");
    const prog = $("contagemRealizarProgresso");
    if (prog) prog.textContent = `${inv.contados} de ${inv.total_itens} produtos contados`;
  }

  async function salvarItem(produtoId) {
    const inv = st.realizarInv;
    const it = inv?.itens.find((x) => x.produto_id === produtoId);
    if (!it) return;
    const raw = String(st.amounts[produtoId] || "").trim();
    if (!raw) {
      toast("Informe a quantidade encontrada. Campo vazio não vale zero.", "warning");
      return;
    }
    const nota = String(st.notes[produtoId] || "").trim();
    const recontagem = it.status === "aguardando_recontagem";
    try {
      const r = await post(`/inventarios/${inv.id}/lancar`, {
        produto_id: produtoId,
        quantidade: raw,
        observacao: recontagem ? "" : nota,
        justificativa: recontagem ? nota : "",
      });
      st.realizarInv = r.inventario;
      delete st.amounts[produtoId];
      delete st.notes[produtoId];
      toast(r.message || "Quantidade registrada.", "success");
      renderItensRealizar();
    } catch (e) {
      toast(e?.message || "Não foi possível salvar.", "error");
    }
  }

  async function loadRealizar() {
    const root = carregando("contagemRealizarRoot");
    try {
      await meta();
      const c = caps();
      if (!st.realizarId) {
        const lista = inventariosAbertosParaContar(await api(`/inventarios${qs({ status: "em_contagem,em_conferencia" })}`));
        root.innerHTML = pagina(
          "Realizar contagem",
          "Escolha uma contagem aberta para continuar de onde parou.",
          `${c.contar || c.conferir ? "" : aviso("Seu acesso não lança quantidade.")}
          <div class="rh-cand-list">${lista.length ? lista.map((inv) => `<article class="rh-cand-card">
            <div class="rh-cand-card__head">
              <div><h3 class="rh-cand-card__name">${esc(inv.numero)}</h3><p class="rh-cand-card__sub">${esc(inv.unidade_nome)} · ${esc(TIPO_INV[inv.tipo] || inv.tipo)}${inv.cega ? " · contagem cega" : ""}</p></div>
              ${pillInv(inv.status)}
            </div>
            <div class="rh-cand-card__meta">
              ${linha("Progresso", `${inv.contados} de ${inv.total_itens} produtos contados`)}
              ${inv.aguardando_recontagem ? linha("Recontagens pedidas", String(inv.aguardando_recontagem)) : ""}
              ${linha("Equipe", esc(inv.equipe.join(", ") || "—"))}
              ${linha("Iniciada em", esc(dataHora(inv.referencia_em)))}
            </div>
            <div class="rh-cand-card__actions"><button type="button" class="btn btn-rh-toggle" data-abrir="${inv.id}">Abrir contagem</button></div>
          </article>`).join("") : vazio("Nenhuma contagem em andamento.")}</div>`
        );
        root.querySelector(".rh-cand-list")?.addEventListener("click", (ev) => {
          const id = ev.target.closest("[data-abrir]")?.dataset.abrir;
          if (id) abrirRealizar(Number(id));
        });
        return;
      }

      st.realizarInv = await api(`/inventarios/${st.realizarId}`);
      const inv = st.realizarInv;
      const filtros = [["todos", "Todos"], ["pendentes", "Não contados"], ["contados", "Contados"], ["recontagem", "Recontagem"]];
      root.innerHTML = pagina(
        `${inv.numero} — ${inv.unidade_nome}`,
        inv.cega ? "Contagem cega: o saldo do sistema fica oculto para quem só conta." : "Saldo visível para a equipe.",
        `${inv.na_equipe || c.conferir ? "" : aviso("Você não está na equipe desta contagem. Pode consultar, mas não lançar.")}
        <div class="table-card contagem-barra">
          <div class="contagem-barra__info"><strong id="contagemRealizarProgresso"></strong> ${pillInv(inv.status)}</div>
          <input type="search" id="contagemRealizarBusca" placeholder="Pesquisar por nome ou código" value="${esc(st.realizarBusca)}" />
          <div class="contagem-chips">${filtros.map(([k, l]) => `<button type="button" class="btn ${st.realizarFiltro === k ? "primary" : "neutral"}" data-filtro="${k}">${l}</button>`).join("")}</div>
        </div>
        <div class="rh-cand-list" id="contagemRealizarItens"></div>
        <div class="rh-cand-card__actions contagem-rodape">
          <button type="button" class="btn neutral" data-realizar="voltar">Voltar à lista</button>
          ${inv.status === "em_contagem" && c.contar ? '<button type="button" class="btn primary" data-realizar="finalizar">Finalizar contagem</button>' : ""}
        </div>`
      );
      renderItensRealizar();

      const wrap = root.firstElementChild;
      wrap.addEventListener("input", (ev) => {
        const el = ev.target;
        if (el.id === "contagemRealizarBusca") {
          st.realizarBusca = el.value;
          renderItensRealizar();
        } else if (el.dataset.qtd) st.amounts[el.dataset.qtd] = el.value;
        else if (el.dataset.nota) st.notes[el.dataset.nota] = el.value;
      });
      wrap.addEventListener("keydown", (ev) => {
        if (ev.key === "Enter" && ev.target.dataset?.qtd) {
          ev.preventDefault();
          salvarItem(Number(ev.target.dataset.qtd));
        }
      });
      wrap.addEventListener("click", async (ev) => {
        const salvar = ev.target.closest("[data-salvar]")?.dataset.salvar;
        if (salvar) return salvarItem(Number(salvar));
        const filtro = ev.target.closest("[data-filtro]")?.dataset.filtro;
        if (filtro) {
          st.realizarFiltro = filtro;
          wrap.querySelectorAll("[data-filtro]").forEach((b) => {
            b.classList.toggle("primary", b.dataset.filtro === filtro);
            b.classList.toggle("neutral", b.dataset.filtro !== filtro);
          });
          return renderItensRealizar();
        }
        const acao = ev.target.closest("[data-realizar]")?.dataset.realizar;
        if (acao === "voltar") {
          st.realizarId = null;
          loadRealizar();
        } else if (acao === "finalizar") {
          const pendentes = st.realizarInv.total_itens - st.realizarInv.contados;
          const msg = pendentes
            ? `${pendentes} produto(s) ainda sem quantidade. Eles ficam como não contados e o estoque deles não muda. Finalizar?`
            : "Finalizar a contagem e enviar para conferência?";
          if (!confirm(msg)) return;
          try {
            const r = await post(`/inventarios/${st.realizarInv.id}/finalizar`);
            toast(r.message, "success");
            st.realizarId = null;
            if (caps().conferir && typeof window.navigateTo === "function") window.navigateTo("contagemConferencia");
            else loadRealizar();
          } catch (e) {
            toast(e?.message || "Não foi possível finalizar.", "error");
          }
        }
      });
    } catch (e) {
      st.realizarId = null;
      erro(root, e);
    }
  }

  // ——— 4. Conferência e divergências ———

  function htmlComparativo(inv, it) {
    const oculto = "Oculto";
    return [
      linha("Inventário", `${esc(inv.numero)} · ${esc(inv.unidade_nome)}`),
      linha("Sistema", inv.revelar ? esc(qtd(it.sistema, it.unidade_base)) : oculto),
      linha("Físico", inv.revelar ? esc(qtd(it.fisico, it.unidade_base)) : it.contado ? "Lançado" : "—"),
      linha("Diferença", inv.revelar ? esc(qtd(it.diferenca, it.unidade_base)) : oculto),
      linha("Valor", inv.revelar ? esc(money(it.valor)) : oculto),
      linha("Quem contou", esc(it.ultimo_usuario || "—")),
      linha("Contado em", esc(dataHora(it.contado_em))),
    ].join("");
  }

  async function loadConferencia() {
    const root = carregando("contagemConferenciaRoot");
    try {
      await meta();
      const c = caps();
      const invs = await api(`/inventarios${qs({ status: "em_conferencia,encerrado", detalhe: "1" })}`);
      const filtradas = invs.filter((inv) => !st.confInventario || String(inv.id) === st.confInventario);
      const rows = filtradas.flatMap((inv) => inv.itens.filter((it) => it.contado).map((it) => ({ inv, it })));
      rows.sort((a, b) => Number(["falta", "sobra", "aguardando_recontagem"].includes(b.it.status)) - Number(["falta", "sobra", "aguardando_recontagem"].includes(a.it.status)));
      root.innerHTML = pagina(
        "Conferência e divergências",
        "A diferença é a quantidade física menos o saldo do sistema no momento em que ela foi lançada.",
        `${c.conferir ? "" : aviso("Seu acesso consulta, mas não pede recontagem.")}
        <div class="table-card contagem-filtros">
          <header>Filtro</header>
          <div class="filters-grid">
            <label>Inventário<select id="contagemConfInv"><option value="">Todos</option>${invs.map((inv) => `<option value="${inv.id}"${st.confInventario === String(inv.id) ? " selected" : ""}>${esc(inv.numero)} — ${esc(inv.unidade_nome)} (${esc(STATUS_INV[inv.status])})</option>`).join("")}</select></label>
          </div>
        </div>
        <div class="rh-cand-list">${rows.length ? rows.map(({ inv, it }) => `<article class="rh-cand-card">
          <div class="rh-cand-card__top">
            ${foto(it)}
            <div class="rh-cand-card__main">
              <div class="rh-cand-card__head">
                <div><h3 class="rh-cand-card__name">${esc(it.produto_nome)}</h3><p class="rh-cand-card__sub">${esc([it.codigo, categoriaLabel(it.categoria)].filter(Boolean).join(" · "))}</p></div>
                ${pillItem(it.status)}
              </div>
              <div class="rh-cand-card__meta">${htmlComparativo(inv, it)}</div>
            </div>
          </div>
          ${(it.status === "falta" || it.status === "sobra") && inv.status !== "encerrado" && c.conferir ? `<div class="rh-cand-card__actions"><button type="button" class="btn neutral" data-recontar="${inv.id}:${it.produto_id}">Pedir recontagem</button></div>` : ""}
        </article>`).join("") : vazio("Nenhuma contagem finalizada.")}</div>`
      );
      $("contagemConfInv")?.addEventListener("change", (ev) => {
        st.confInventario = ev.target.value;
        loadConferencia();
      });
      root.querySelector(".rh-cand-list")?.addEventListener("click", async (ev) => {
        const alvo = ev.target.closest("[data-recontar]")?.dataset.recontar;
        if (!alvo) return;
        const [invId, produtoId] = alvo.split(":").map(Number);
        const item = rows.find((r) => r.inv.id === invId && r.it.produto_id === produtoId);
        const just = await pedirJustificativa("Pedir recontagem", item?.it.produto_nome || "");
        if (!just) return;
        try {
          const r = await post(`/inventarios/${invId}/decidir`, { produto_id: produtoId, decisao: "recontagem", justificativa: just });
          toast(r.message, "success");
          loadConferencia();
        } catch (e) {
          toast(e?.message || "Não foi possível pedir recontagem.", "error");
        }
      });
    } catch (e) {
      erro(root, e);
    }
  }

  // ——— 5. Aprovação de ajustes ———

  async function loadAprovacao() {
    const root = carregando("contagemAprovacaoRoot");
    try {
      await meta();
      const c = caps();
      const invs = await api(`/inventarios${qs({ status: "em_conferencia", detalhe: "1" })}`);
      const rows = invs.flatMap((inv) =>
        inv.itens.filter((it) => ["falta", "sobra", "aguardando_recontagem"].includes(it.status)).map((it) => ({ inv, it }))
      );
      root.innerHTML = pagina(
        "Aprovação de ajustes",
        "Nenhuma divergência muda o estoque sozinha. O ajuste aprovado gera uma movimentação ligada ao inventário.",
        `${c.aprovar ? "" : aviso("Só quem tem acesso à Aprovação de ajustes aprova, rejeita e encerra o inventário.")}
        ${invs.length ? `<div class="rh-cand-list contagem-encerrar">${invs.map((inv) => {
          const pendentes = inv.aguardando_decisao + inv.aguardando_recontagem;
          return `<article class="rh-cand-card">
            <div class="rh-cand-card__head">
              <div><h3 class="rh-cand-card__name">${esc(inv.numero)}</h3><p class="rh-cand-card__sub">${esc(inv.unidade_nome)} · ${esc(TIPO_INV[inv.tipo] || inv.tipo)}</p></div>
              ${pillInv(inv.status)}
            </div>
            <div class="rh-cand-card__meta">
              ${linha("Produtos contados", `${inv.contados} de ${inv.total_itens}`)}
              ${linha("Aguardando decisão", String(inv.aguardando_decisao))}
              ${linha("Aguardando recontagem", String(inv.aguardando_recontagem))}
            </div>
            <div class="rh-cand-card__actions"><button type="button" class="btn neutral" data-encerrar="${inv.id}"${c.aprovar && !pendentes ? "" : " disabled"}>Encerrar ${esc(inv.numero)}</button></div>
          </article>`;
        }).join("")}</div>` : ""}
        <div class="table-card contagem-lista">
          <header>Ajustes aguardando decisão</header>
          <div class="rh-cand-list">${rows.length ? rows.map(({ inv, it }) => {
            const key = `${inv.id}:${it.produto_id}`;
            const recontagem = it.status === "aguardando_recontagem";
            return `<article class="rh-cand-card">
              <div class="rh-cand-card__top">
                ${foto(it)}
                <div class="rh-cand-card__main">
                  <div class="rh-cand-card__head">
                    <div><h3 class="rh-cand-card__name">${esc(it.produto_nome)}</h3><p class="rh-cand-card__sub">${esc(inv.numero)} · ${esc(inv.unidade_nome)}</p></div>
                    ${pillItem(it.status)}
                  </div>
                  <div class="rh-cand-card__meta">
                    ${linha("Sistema", esc(qtd(it.sistema, it.unidade_base)))}
                    ${linha("Físico", esc(qtd(it.fisico, it.unidade_base)))}
                    ${linha("Diferença", esc(qtd(it.diferenca, it.unidade_base)))}
                    ${linha("Valor do ajuste", esc(money(it.valor)))}
                    ${linha("Contagens", esc(it.lancamentos.map((l) => `${l.rodada}ª ${qtd(l.quantidade, it.unidade_base)} (${l.usuario || "—"})`).join(" · ")))}
                  </div>
                </div>
              </div>
              <label class="contagem-obs">Justificativa<textarea rows="2" data-aprov-nota="${key}">${esc(st.aprovNotes[key] || "")}</textarea></label>
              <div class="rh-cand-card__actions">
                <button type="button" class="btn primary" data-decidir="aprovado" data-key="${key}"${c.aprovar && !recontagem ? "" : " disabled"}>Aprovar ajuste</button>
                <button type="button" class="btn" style="background:#b71c1c;color:#fff;" data-decidir="rejeitado" data-key="${key}"${c.aprovar && !recontagem ? "" : " disabled"}>Rejeitar</button>
                <button type="button" class="btn neutral" data-decidir="recontagem" data-key="${key}"${c.conferir && !recontagem ? "" : " disabled"}>Pedir nova contagem</button>
              </div>
            </article>`;
          }).join("") : vazio("Nenhum ajuste aguardando decisão.")}</div>
        </div>`
      );
      const wrap = root.firstElementChild;
      wrap.addEventListener("input", (ev) => {
        const key = ev.target.dataset?.aprovNota;
        if (key) st.aprovNotes[key] = ev.target.value;
      });
      wrap.addEventListener("click", async (ev) => {
        const enc = ev.target.closest("[data-encerrar]")?.dataset.encerrar;
        const btn = ev.target.closest("[data-decidir]");
        try {
          if (enc) {
            if (!confirm("Encerrar o inventário? Depois disso ele fica só no histórico.")) return;
            const r = await post(`/inventarios/${enc}/encerrar`);
            toast(r.message, "success");
            loadAprovacao();
          } else if (btn) {
            const [invId, produtoId] = btn.dataset.key.split(":").map(Number);
            const just = String(st.aprovNotes[btn.dataset.key] || "").trim();
            if (!just) {
              toast("Informe a justificativa.", "warning");
              return;
            }
            if (btn.dataset.decidir === "aprovado" && !confirm("Aprovar e lançar o ajuste no estoque?")) return;
            const r = await post(`/inventarios/${invId}/decidir`, { produto_id: produtoId, decisao: btn.dataset.decidir, justificativa: just });
            delete st.aprovNotes[btn.dataset.key];
            toast(r.message, "success");
            loadAprovacao();
          }
        } catch (e) {
          toast(e?.message || "Falha na operação.", "error");
        }
      });
    } catch (e) {
      erro(root, e);
    }
  }

  // ——— 6. Histórico e relatórios ———

  function linhasRelatorio(todos) {
    const r = st.hist.relatorio;
    const invs = todos.filter((i) => i.status !== "rascunho");
    if (r === "acuracidade") {
      return [["Unidade", "Itens contados", "Sem divergência", "Acuracidade"], ...(st.meta.unidades || []).map((u) => {
        const itens = invs.filter((i) => i.unidade_id === u.id).flatMap((i) => i.itens.filter((it) => it.contado));
        const ok = itens.filter((it) => it.status === "sem_divergencia").length;
        const p = pct(ok, itens.length);
        return [u.nome, String(itens.length), String(ok), p == null ? "—" : `${numero(p, 1)}%`];
      })];
    }
    if (r === "funcionarios") {
      const bag = new Map();
      invs.forEach((i) => i.itens.forEach((it) => it.lancamentos.forEach((l) => bag.set(l.usuario || `#${l.usuario_id}`, (bag.get(l.usuario || `#${l.usuario_id}`) || 0) + 1))));
      return [["Funcionário", "Lançamentos"], ...[...bag.entries()].sort((a, b) => b[1] - a[1]).map(([n, t]) => [n, String(t)])];
    }
    if (r === "comparativo") {
      return [["Inventário", "Unidade", "Abertura", "Tipo", "Situação", "Acuracidade", "Perda", "Sobra"], ...invs.map((i) => {
        const itens = i.itens.filter((it) => it.contado);
        const ok = itens.filter((it) => it.status === "sem_divergencia").length;
        const perda = itens.reduce((t, it) => (it.valor < 0 ? t + Math.abs(it.valor) : t), 0);
        const sobra = itens.reduce((t, it) => (it.valor > 0 ? t + it.valor : t), 0);
        const p = pct(ok, itens.length);
        return [i.numero, i.unidade_nome, diaBr(i.aberto_em), TIPO_INV[i.tipo] || i.tipo, STATUS_INV[i.status] || i.status, p == null ? "—" : `${numero(p, 1)}%`, i.revelar ? money(perda) : "Oculto", i.revelar ? money(sobra) : "Oculto"];
      })];
    }
    const sel = invs.find((i) => String(i.id) === String(st.hist.inventario_id)) || invs[0];
    const fonte = r === "completo" ? (sel ? [sel] : []) : invs;
    const corpo = fonte.flatMap((i) => i.itens.filter((it) => {
      if (r === "divergencias") return ["falta", "sobra", "aguardando_recontagem", "aprovado", "rejeitado"].includes(it.status);
      if (r === "valores") return ["falta", "sobra", "aprovado"].includes(it.status);
      if (r === "ajustes") return it.decisoes.some((d) => d.decisao === "aprovado");
      return true;
    }).map((it) => {
      const aprov = it.decisoes.filter((d) => d.decisao === "aprovado").pop();
      return [
        i.numero,
        i.unidade_nome,
        it.produto_nome,
        STATUS_ITEM[it.status] || it.status,
        it.contado ? (i.revelar || it.minha_quantidade != null ? qtd(i.revelar ? it.fisico : it.minha_quantidade, it.unidade_base) : "Lançado") : "Não contado",
        i.revelar ? qtd(it.diferenca, it.unidade_base) : "Oculto",
        i.revelar ? money(it.valor) : "Oculto",
        it.ultimo_usuario || "—",
        ...(r === "ajustes" ? [aprov ? `${aprov.usuario || "—"} em ${dataHora(aprov.decidido_em)}` : "—", aprov?.movimentacao_id ? `#${aprov.movimentacao_id}` : "—"] : []),
      ];
    }));
    const cab = ["Inventário", "Unidade", "Produto", "Situação", "Físico", "Diferença", "Valor", "Quem contou"];
    if (r === "ajustes") cab.push("Aprovado por", "Movimentação");
    return [cab, ...corpo];
  }

  function baixarCsv(nome, rows) {
    const corpo = rows.map((row) => row.map((c) => `"${String(c).replace(/"/g, '""')}"`).join(";")).join("\n");
    const blob = new Blob([`\uFEFF${corpo}`], { type: "text/csv;charset=utf-8" });
    const a = document.createElement("a");
    a.href = URL.createObjectURL(blob);
    a.download = nome;
    document.body.appendChild(a);
    a.click();
    setTimeout(() => {
      URL.revokeObjectURL(a.href);
      a.remove();
    }, 500);
  }

  async function imprimirPdf(titulo, filtros, rows) {
    const logo = typeof window.getReportLogoMarkup === "function" ? await window.getReportLogoMarkup() : "";
    const [cab, ...corpo] = rows;
    const html = `<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8" /><title>${esc(titulo)}</title>
      <style>
        body { font-family: Arial, sans-serif; color: #111; padding: 24px; }
        .report-header { display: flex; align-items: center; gap: 16px; margin-bottom: 16px; }
        .report-header img, .report-header object { height: 64px; width: auto; max-width: 120px; border: none; }
        .report-header h1 { margin: 0; font-size: 22px; }
        .meta { margin-bottom: 20px; line-height: 1.55; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; font-size: 11px; text-align: left; vertical-align: top; }
        th { background: #f0f4f8; font-weight: bold; }
        .report-footer { margin-top: 32px; padding-top: 12px; border-top: 1px solid #ddd; text-align: center; font-size: 12px; color: #333; font-weight: 600; }
      </style></head><body>
      <div class="report-header">${logo}<h1>${esc(titulo)}</h1></div>
      <div class="meta"><strong>Gerado em:</strong> ${esc(new Date().toLocaleString("pt-BR"))}<br /><strong>Filtros:</strong> ${esc(filtros)}<br /><strong>Linhas:</strong> ${corpo.length}</div>
      <table><thead><tr>${cab.map((c) => `<th>${esc(c)}</th>`).join("")}</tr></thead>
      <tbody>${corpo.map((row) => `<tr>${row.map((c) => `<td>${esc(c)}</td>`).join("")}</tr>`).join("")}</tbody></table>
      <footer class="report-footer">Grupo Sabor Paraense</footer></body></html>`;
    const iframe = document.createElement("iframe");
    iframe.style.cssText = "position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden;";
    document.body.appendChild(iframe);
    const limpar = () => iframe.parentNode && iframe.parentNode.removeChild(iframe);
    iframe.onload = () => {
      const win = iframe.contentWindow;
      if (!win || typeof win.print !== "function") {
        limpar();
        toast("Seu navegador não suportou a impressão.", "error");
        return;
      }
      win.onafterprint = limpar;
      win.focus();
      win.print();
      setTimeout(limpar, 15000);
    };
    const doc = iframe.contentDocument || iframe.contentWindow?.document;
    doc.open();
    doc.write(html);
    doc.close();
  }

  async function loadHistorico() {
    const root = carregando("contagemHistoricoRoot");
    try {
      await meta();
      const h = st.hist;
      const invs = (await api(`/inventarios${qs({ detalhe: "1", unidade_id: h.unidade_id })}`)).filter((i) => i.status !== "rascunho");
      const rows = linhasRelatorio(invs);
      st.histRows = rows;
      const [cab, ...corpo] = rows;
      const nomeRel = (RELATORIOS.find(([k]) => k === h.relatorio) || [])[1] || "Inventário";
      const porItem = ["completo", "divergencias", "valores", "ajustes"].includes(h.relatorio);
      root.innerHTML = pagina(
        "Histórico e relatórios",
        "Os valores usam o custo que estava no saldo quando a contagem começou.",
        `<form class="table-card contagem-filtros" id="contagemHistFiltros">
          <header>Relatório</header>
          <div class="filters-grid">
            <label>Relatório<select name="relatorio">${RELATORIOS.map(([k, l]) => `<option value="${k}"${h.relatorio === k ? " selected" : ""}>${esc(l)}</option>`).join("")}</select></label>
            <label>Unidade<select name="unidade_id">${opcoesUnidades(h.unidade_id)}</select></label>
            ${h.relatorio === "completo" ? `<label>Inventário<select name="inventario_id">${invs.map((i) => `<option value="${i.id}"${String(h.inventario_id) === String(i.id) ? " selected" : ""}>${esc(i.numero)} — ${esc(i.unidade_nome)} (${esc(STATUS_INV[i.status])})</option>`).join("") || '<option value="">Nenhum inventário</option>'}</select></label>` : ""}
          </div>
          <div class="rh-cand-card__actions">
            <button type="button" class="btn neutral" data-hist="csv"${corpo.length ? "" : " disabled"}>Exportar Excel</button>
            <button type="button" class="btn neutral" data-hist="pdf"${corpo.length ? "" : " disabled"}>Exportar PDF</button>
          </div>
        </form>
        <p class="subtle-text contagem-total">${corpo.length} linha(s)</p>
        <div class="rh-cand-list">${corpo.length ? corpo.slice(0, 300).map((row) => `<article class="rh-cand-card">
          <div class="rh-cand-card__head"><div><h3 class="rh-cand-card__name">${esc(porItem ? row[2] : row[0])}</h3>
          <p class="rh-cand-card__sub">${esc(porItem ? `${row[0]} · ${row[1]}` : cab[0])}</p></div></div>
          <div class="rh-cand-card__meta">${cab.map((c, i) => ((porItem ? i <= 2 : i === 0) ? "" : linha(c, esc(row[i])))).join("")}</div>
        </article>`).join("") : vazio("Nenhum dado para este relatório.")}</div>
        ${corpo.length > 300 ? `<p class="subtle-text">Mostrando 300 de ${corpo.length}. A exportação traz todas as linhas.</p>` : ""}`
      );
      $("contagemHistFiltros")?.addEventListener("change", (ev) => {
        const el = ev.target;
        if (!el.name) return;
        st.hist[el.name] = el.value;
        if (el.name !== "inventario_id") st.hist.inventario_id = "";
        loadHistorico();
      });
      $("contagemHistFiltros")?.addEventListener("click", (ev) => {
        const acao = ev.target.closest("[data-hist]")?.dataset.hist;
        if (!acao) return;
        const unidade = (st.meta.unidades || []).find((u) => String(u.id) === String(h.unidade_id))?.nome || "Todas";
        if (acao === "csv") baixarCsv(`contagem-${h.relatorio}.csv`, st.histRows);
        else imprimirPdf(nomeRel, `Unidade: ${unidade}`, st.histRows);
      });
    } catch (e) {
      erro(root, e);
    }
  }

  // ——— 7. Configurações ———

  async function loadConfiguracoes() {
    const root = carregando("contagemConfiguracoesRoot");
    try {
      await meta(true);
      const pode = !!caps().configurar;
      root.innerHTML = pagina(
        "Configurações da contagem",
        "A contagem cega esconde saldo, diferença e valor de quem só conta. Quem confere e aprova continua vendo o comparativo.",
        `${pode ? "" : aviso("Só quem tem acesso às Configurações da contagem altera isto.")}
        <div class="table-card contagem-form">
          <header>Padrão para novas contagens</header>
          <label class="contagem-check"><input type="checkbox" id="contagemCfgCega"${st.meta.config?.cega_padrao ? " checked" : ""}${pode ? "" : " disabled"} /> Abrir novas contagens como contagem cega</label>
          <p class="subtle-text">Cada contagem ainda pode ligar ou desligar a contagem cega na abertura. Inventário encerrado não é apagado.</p>
          <div class="rh-cand-card__actions"><button type="button" class="btn primary" id="contagemCfgSalvar"${pode ? "" : " disabled"}>Salvar</button></div>
        </div>`
      );
      $("contagemCfgSalvar")?.addEventListener("click", async () => {
        try {
          const r = await api("/config", { method: "PUT", body: JSON.stringify({ cega_padrao: !!$("contagemCfgCega")?.checked }) });
          st.meta.config = r.config;
          toast(r.message, "success");
        } catch (e) {
          toast(e?.message || "Não foi possível salvar.", "error");
        }
      });
    } catch (e) {
      erro(root, e);
    }
  }

  window.loadContagemDashboard = loadDashboard;
  window.loadContagemNova = () => loadNova(true);
  window.loadContagemRealizar = loadRealizar;
  window.loadContagemConferencia = loadConferencia;
  window.loadContagemAprovacao = loadAprovacao;
  window.loadContagemHistorico = loadHistorico;
  window.loadContagemConfiguracoes = loadConfiguracoes;
})();
