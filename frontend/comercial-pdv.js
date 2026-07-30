/**
 * Comercial / PDV — integrado ao estoque e venda fiscal (API /pdv, /fiscal/vendas).
 */
(function () {
  "use strict";

  async function cpdvFetch(path, opts = {}) {
    if (typeof window.fetchJSON === "function") {
      return window.fetchJSON(path, opts);
    }
    const base =
      window.API_URL ||
      (window.APP_CONFIG && window.APP_CONFIG.API_URL) ||
      "https://api.gruposaborparaense.com.br/api";
    const headers = { "Content-Type": "application/json", ...(opts.headers || {}) };
    const uid = window.getUser?.()?.id || window.currentUser?.id;
    if (uid) headers["X-Usuario-Id"] = String(uid);
    if (window.currentUser?.token) headers.Authorization = `Bearer ${window.currentUser.token}`;
    const res = await fetch(`${base.replace(/\/$/, "")}${path.startsWith("/") ? path : `/${path}`}`, {
      ...opts,
      headers,
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.error || data.message || `HTTP ${res.status}`);
    return data;
  }

  const CPDV_OFFLINE_QUEUE_KEY = "cpdv_offline_vendas_v1";
  let cpdvOfflineSyncing = false;
  let cpdvOfflineAvisoFechado = false;

  function cpdvMostrarAvisoOfflinePersistente(forcar = false) {
    if (cpdvOfflineAvisoFechado || document.getElementById("cpdvOfflineAlert")) return;
    if (!forcar && cpdvIsOnline()) return;

    const alerta = document.createElement("div");
    alerta.id = "cpdvOfflineAlert";
    alerta.className = "cpdv-offline-alert";
    alerta.setAttribute("role", "alert");
    alerta.innerHTML = `
      <div class="cpdv-offline-alert__icon" aria-hidden="true">!</div>
      <div class="cpdv-offline-alert__texto">
        <strong>PDV sem internet</strong>
        <span>As vendas serão guardadas neste aparelho e sincronizadas quando a conexão voltar.</span>
      </div>
      <button type="button" class="cpdv-offline-alert__fechar" aria-label="Fechar aviso de falta de internet" title="Fechar">×</button>`;
    alerta.querySelector(".cpdv-offline-alert__fechar")?.addEventListener("click", () => {
      cpdvOfflineAvisoFechado = true;
      alerta.remove();
    });
    document.body.appendChild(alerta);
  }

  function cpdvUuid() {
    if (typeof crypto !== "undefined" && crypto.randomUUID) return crypto.randomUUID();
    return `off-${Date.now()}-${Math.random().toString(16).slice(2, 10)}`;
  }

  function cpdvIsOnline() {
    return typeof navigator === "undefined" || navigator.onLine !== false;
  }

  function cpdvIsNetworkError(err) {
    if (!cpdvIsOnline()) return true;
    const msg = String(err?.message || err || "").toLowerCase();
    const status = Number(err?.status || 0);
    if (status === 0) return true;
    return /failed to fetch|networkerror|network request failed|load failed|conex[aã]o|offline|timeout|timed out|err_internet|err_network|servidor indispon/i.test(msg);
  }

  function cpdvLerFilaOffline() {
    try {
      const raw = localStorage.getItem(CPDV_OFFLINE_QUEUE_KEY);
      const list = raw ? JSON.parse(raw) : [];
      return Array.isArray(list) ? list : [];
    } catch {
      return [];
    }
  }

  function cpdvSalvarFilaOffline(list) {
    localStorage.setItem(CPDV_OFFLINE_QUEUE_KEY, JSON.stringify(list || []));
    cpdvAtualizarBadgeOffline();
  }

  function cpdvContarPendentesOffline() {
    return cpdvLerFilaOffline().filter((x) => x.status === "pendente" || x.status === "erro").length;
  }

  function cpdvEnfileirarVendaOffline(entry) {
    const list = cpdvLerFilaOffline();
    list.push({
      ...entry,
      id: entry.id || cpdvUuid(),
      status: "pendente",
      created_at: entry.created_at || new Date().toISOString(),
      tentativas: entry.tentativas || 0,
      ultimo_erro: null,
    });
    cpdvSalvarFilaOffline(list);
    cpdvMostrarAvisoOfflinePersistente(true);
    return list[list.length - 1];
  }

  function cpdvAtualizarBadgeOffline() {
    const n = cpdvContarPendentesOffline();
    document.querySelectorAll("[data-cpdv-offline-badge]").forEach((el) => {
      el.hidden = n <= 0;
      el.textContent = n <= 0 ? "" : `${n} venda(s) offline pendente(s)`;
    });
    const btn = document.getElementById("cpdvSyncOfflineBtn");
    if (btn) {
      btn.hidden = n <= 0;
      btn.textContent = n <= 0 ? "Sincronizar" : `Sincronizar offline (${n})`;
    }
  }

  async function cpdvSincronizarFilaOffline(opts = {}) {
    if (cpdvOfflineSyncing) return { ok: 0, fail: 0, skipped: true };
    if (!cpdvIsOnline()) {
      if (!opts.silencioso) toast("Sem internet — sincronização depois.", "warning");
      return { ok: 0, fail: 0 };
    }
    const list = cpdvLerFilaOffline();
    const pendentes = list.filter((x) => x.status === "pendente" || x.status === "erro");
    if (!pendentes.length) {
      cpdvAtualizarBadgeOffline();
      return { ok: 0, fail: 0 };
    }
    cpdvOfflineSyncing = true;
    let ok = 0;
    let fail = 0;
    try {
      for (const item of list) {
        if (item.status !== "pendente" && item.status !== "erro") continue;
        item.tentativas = (item.tentativas || 0) + 1;
        try {
          const r = await cpdvFetch(item.path, {
            method: "POST",
            body: JSON.stringify(item.payload),
          });
          item.status = "sincronizada";
          item.synced_at = new Date().toISOString();
          item.venda_id = r.venda_id;
          item.ultimo_erro = null;
          item.resultado = {
            venda_id: r.venda_id,
            valor_liquido: r.valor_liquido,
            emissao: r.emissao || null,
          };
          ok += 1;
          if (!opts.silencioso) {
            toast(
              `Offline sync: venda #${r.venda_id}${msgEmissao(r.emissao)}`,
              r.emissao?.emitida ? "success" : "info"
            );
          }
        } catch (e) {
          if (cpdvIsNetworkError(e)) {
            item.status = "pendente";
            item.ultimo_erro = e.message || "Sem conexão";
            fail += 1;
            break;
          }
          // Comanda já fechada / replay: considera sincronizada
          const msg = String(e.message || "");
          if (/já fechada|idempotenc|replay/i.test(msg) && item.payload?.idempotency_key) {
            item.status = "sincronizada";
            item.synced_at = new Date().toISOString();
            item.ultimo_erro = msg;
            ok += 1;
            continue;
          }
          item.status = "erro";
          item.ultimo_erro = msg || "Falha ao sincronizar";
          fail += 1;
          if (!opts.silencioso) toast(`Falha ao sincronizar venda offline: ${item.ultimo_erro}`, "error");
        }
      }
      // Mantém últimas sincronizadas por auditoria local (máx 30) + todas pendentes/erro
      const limpas = [];
      let syncCount = 0;
      for (let i = list.length - 1; i >= 0; i -= 1) {
        const it = list[i];
        if (it.status === "sincronizada") {
          if (syncCount >= 30) continue;
          syncCount += 1;
        }
        limpas.unshift(it);
      }
      cpdvSalvarFilaOffline(limpas);
    } finally {
      cpdvOfflineSyncing = false;
      cpdvAtualizarBadgeOffline();
    }
    return { ok, fail };
  }

  function cpdvBindOfflineListeners() {
    if (window.__cpdvOfflineBound) return;
    window.__cpdvOfflineBound = true;
    window.addEventListener("online", () => {
      toast("Internet voltou — sincronizando vendas offline…", "info");
      cpdvSincronizarFilaOffline({ silencioso: false }).catch(() => {});
    });
    window.addEventListener("offline", () => {
      cpdvOfflineAvisoFechado = false;
      cpdvMostrarAvisoOfflinePersistente(true);
      toast("Modo offline: vendas do caixa serão guardadas neste aparelho.", "warning");
      cpdvAtualizarBadgeOffline();
    });
    document.addEventListener("visibilitychange", () => {
      if (document.visibilityState === "visible" && cpdvIsOnline()) {
        cpdvSincronizarFilaOffline({ silencioso: true }).catch(() => {});
      }
    });
    cpdvMostrarAvisoOfflinePersistente();
  }

  const DADOS_DEMONSTRACAO_PDV = {
    unidades: [
      { id: 1, nome: "Matriz — Centro" },
      { id: 2, nome: "Filial — Batista Campos" },
      { id: 3, nome: "Filial — Marco" },
    ],
    mesas: [
      { id: 1, numero: 1, unidadeId: 1, capacidade: 4, status: "livre", pessoas: 0, garcomId: null, abertoEm: null, totalParcial: 0, pedidoId: null },
      { id: 2, numero: 2, unidadeId: 1, capacidade: 4, status: "ocupada", pessoas: 3, garcomId: 1, abertoEm: "18:42", totalParcial: 156.4, pedidoId: 101 },
      { id: 3, numero: 3, unidadeId: 1, capacidade: 6, status: "reservada", pessoas: 0, garcomId: null, abertoEm: null, totalParcial: 0, pedidoId: null, reserva: "19:30 — Silva" },
      { id: 4, numero: 4, unidadeId: 2, capacidade: 2, status: "aguardando_pedido", pessoas: 2, garcomId: 2, abertoEm: "19:05", totalParcial: 48.0, pedidoId: 102 },
      { id: 5, numero: 5, unidadeId: 1, capacidade: 4, status: "em_producao", pessoas: 4, garcomId: 1, abertoEm: "18:55", totalParcial: 210.5, pedidoId: 103 },
      { id: 6, numero: 6, unidadeId: 2, capacidade: 8, status: "aguardando_pagamento", pessoas: 6, garcomId: 3, abertoEm: "17:30", totalParcial: 389.9, pedidoId: 104 },
      { id: 7, numero: 7, unidadeId: 1, capacidade: 4, status: "limpeza", pessoas: 0, garcomId: null, abertoEm: null, totalParcial: 0, pedidoId: null },
      { id: 8, numero: 8, unidadeId: 3, capacidade: 4, status: "bloqueada", pessoas: 0, garcomId: null, abertoEm: null, totalParcial: 0, pedidoId: null },
      { id: 9, numero: 9, unidadeId: 1, capacidade: 6, status: "ocupada", pessoas: 5, garcomId: 2, abertoEm: "19:18", totalParcial: 92.0, pedidoId: 105 },
      { id: 10, numero: 10, unidadeId: 2, capacidade: 2, status: "livre", pessoas: 0, garcomId: null, abertoEm: null, totalParcial: 0, pedidoId: null },
    ],
    categorias: [
      { id: "todos", nome: "Todos" },
      { id: "pratos", nome: "Pratos" },
      { id: "porcoes", nome: "Porções" },
      { id: "bebidas", nome: "Bebidas" },
      { id: "sobremesas", nome: "Sobremesas" },
    ],
    produtos: [
      { id: 1, nome: "Tacacá", categoria: "pratos", preco: 28.9, favorito: true, disponivel: true },
      { id: 2, nome: "Maniçoba", categoria: "pratos", preco: 42.5, favorito: true, disponivel: true },
      { id: 3, nome: "Filé ao molho", categoria: "pratos", preco: 58.0, favorito: false, disponivel: true },
      { id: 4, nome: "Caldo de piranha", categoria: "pratos", preco: 35.0, favorito: false, disponivel: false },
      { id: 5, nome: "Porção de camarão", categoria: "porcoes", preco: 65.0, favorito: true, disponivel: true },
      { id: 6, nome: "Porção de mandioca", categoria: "porcoes", preco: 18.0, favorito: false, disponivel: true },
      { id: 7, nome: "Suco de cupuaçu", categoria: "bebidas", preco: 12.0, favorito: true, disponivel: true },
      { id: 8, nome: "Cerveja 600ml", categoria: "bebidas", preco: 14.0, favorito: false, disponivel: true },
      { id: 9, nome: "Água mineral", categoria: "bebidas", preco: 5.0, favorito: false, disponivel: true },
      { id: 10, nome: "Mousse de cupuaçu", categoria: "sobremesas", preco: 16.0, favorito: true, disponivel: true },
      { id: 11, nome: "Pudim", categoria: "sobremesas", preco: 14.0, favorito: false, disponivel: true },
      { id: 12, nome: "Café expresso", categoria: "bebidas", preco: 6.0, favorito: false, disponivel: true },
    ],
    garcons: [
      { id: 1, nome: "Ana Paula" },
      { id: 2, nome: "Carlos Mendes" },
      { id: 3, nome: "Juliana Rocha" },
      { id: 4, nome: "Pedro Alves" },
    ],
    clientes: [
      { id: 1, nome: "Maria Silva", telefone: "(91) 98888-1111", whatsapp: "(91) 98888-1111", cpf: "", nasc: "1990-05-12", obs: "Cliente frequente", preferencia: "Sem pimenta", restricao: "Lactose", visitas: 12, ultima: "2026-07-10", totalGasto: 1840.5 },
      { id: 2, nome: "João Santos", telefone: "(91) 97777-2222", whatsapp: "(91) 97777-2222", cpf: "123.456.789-00", nasc: "1985-11-02", obs: "", preferencia: "Mesa janela", restricao: "", visitas: 5, ultima: "2026-07-12", totalGasto: 620.0 },
      { id: 3, nome: "Empresa Norte Ltda", telefone: "(91) 3333-4444", whatsapp: "(91) 98888-0000", cpf: "", nasc: "", obs: "CNPJ faturamento", preferencia: "Nota fiscal", restricao: "", visitas: 28, ultima: "2026-07-14", totalGasto: 15290.0 },
      { id: 4, nome: "Fernanda Costa", telefone: "(91) 96666-3333", whatsapp: "(91) 96666-3333", cpf: "", nasc: "1998-03-21", obs: "", preferencia: "Sobremesa cupuaçu", restricao: "Glúten", visitas: 3, ultima: "2026-07-08", totalGasto: 210.0 },
    ],
    pedidos: [
      { id: 101, mesa: 2, garcom: "Ana Paula", status: "aberto", total: 156.4, itens: 4, hora: "18:42" },
      { id: 102, mesa: 4, garcom: "Carlos Mendes", status: "aguardando", total: 48.0, itens: 2, hora: "19:05" },
      { id: 103, mesa: 5, garcom: "Ana Paula", status: "producao", total: 210.5, itens: 6, hora: "18:55" },
      { id: 104, mesa: 6, garcom: "Juliana Rocha", status: "fechamento", total: 389.9, itens: 11, hora: "17:30" },
      { id: 105, mesa: 9, garcom: "Carlos Mendes", status: "aberto", total: 92.0, itens: 3, hora: "19:18" },
    ],
    vendas: [
      { id: 501, data: "2026-07-15", hora: "14:22", unidade: "Matriz — Centro", mesa: 12, cliente: "Maria Silva", operador: "Caixa 01", total: 245.8, forma: "Pix", status: "finalizada", garcom: "Ana Paula" },
      { id: 502, data: "2026-07-15", hora: "15:10", unidade: "Matriz — Centro", mesa: 4, cliente: "Balcão", operador: "Caixa 01", total: 89.5, forma: "Débito", status: "finalizada", garcom: "Pedro Alves" },
      { id: 503, data: "2026-07-14", hora: "20:45", unidade: "Filial — Batista Campos", mesa: 6, cliente: "Empresa Norte Ltda", operador: "Caixa 02", total: 512.0, forma: "Crédito", status: "finalizada", garcom: "Juliana Rocha" },
      { id: 504, data: "2026-07-14", hora: "21:30", unidade: "Matriz — Centro", mesa: 9, cliente: "João Santos", operador: "Caixa 01", total: 178.0, forma: "Dinheiro", status: "cancelada", garcom: "Carlos Mendes" },
    ],
    kds: [
      { id: "k1", pedidoId: 103, mesa: 5, item: "Maniçoba", qtd: 2, setor: "cozinha", col: "novo", prio: true, tempo: "4 min" },
      { id: "k2", pedidoId: 103, mesa: 5, item: "Filé ao molho", qtd: 1, setor: "cozinha", col: "preparo", prio: false, tempo: "12 min" },
      { id: "k3", pedidoId: 101, mesa: 2, item: "Suco de cupuaçu", qtd: 2, setor: "bar", col: "novo", prio: false, tempo: "2 min" },
      { id: "k4", pedidoId: 101, mesa: 2, item: "Cerveja 600ml", qtd: 3, setor: "bar", col: "pronto", prio: false, tempo: "—" },
      { id: "k5", pedidoId: 104, mesa: 6, item: "Mousse de cupuaçu", qtd: 4, setor: "sobremesa", col: "entregue", prio: false, tempo: "—" },
      { id: "k6", pedidoId: 105, mesa: 9, item: "Porção de camarão", qtd: 1, setor: "cozinha", col: "novo", prio: true, tempo: "1 min" },
    ],
    itensCarrinho: [],
  };

  const CPDV_MESA_LABEL = {
    livre: "Livre",
    ocupada: "Ocupada",
    reservada: "Reservada",
    aguardando_pedido: "Aguardando pedido",
    em_producao: "Em produção",
    aguardando_pagamento: "Aguardando pagamento",
    limpeza: "Limpeza",
    bloqueada: "Bloqueada",
  };

  const CPDV_KDS_COLS = [
    { id: "novo", nome: "Novos" },
    { id: "preparo", nome: "Em preparo" },
    { id: "pronto", nome: "Prontos" },
    { id: "entregue", nome: "Entregues" },
  ];

  const cpdvState = {
    cat: "todos",
    cart: [],
    desconto: 0,
    acrescimo: 0,
    pgtoAplicarTaxa: false,
    pgtoAplicarCantor: false,
    cliente: null,
    charts: {},
    mesaSel: null,
    garcomId: null,
    suspensas: [],
    busca: "",
    unidadeId: null,
    apiReady: false,
    produtosApi: [],
    salaoMesas: [],
    comandaAtual: null,
    mesaCardAtual: null,
    mesaBusca: "",
    apiMeta: null,
    apiError: null,
    emissaoPdv: null,
  };

  let cpdvModalBound = false;

  function escHtml(s) {
    return (s ?? "").toString()
      .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
  }

  function moeda(n) {
    const v = Number(n);
    if (!Number.isFinite(v)) return "—";
    return v.toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
  }

  function msgEmissao(em) {
    if (!em) return "";
    if (em.emitida) {
      const ch = em.chave ? ` Chave: ${String(em.chave).slice(0, 12)}…` : "";
      return ` NFC-e autorizada.${ch} PDF e XML gerados pelo sistema.`;
    }
    if (em.skipped && em.motivo_skip) {
      if (/sem emissão/i.test(em.motivo_skip)) return " Venda registrada sem nota (pode emitir depois no histórico).";
      return ` (Sem NFC-e: ${em.motivo_skip})`;
    }
    if (em.mensagem) return ` NFC-e não emitida: ${em.mensagem}`;
    return "";
  }

  async function cpdvRefreshEmissaoOpcoes() {
    const uid = cpdvState.unidadeId;
    if (!uid || !cpdvState.apiReady) {
      cpdvState.emissaoPdv = null;
      return;
    }
    try {
      const meta = await cpdvFetch(`/pdv/meta?unidade_id=${uid}`);
      cpdvState.emissaoPdv = meta.emissao_pdv || null;
    } catch {
      cpdvState.emissaoPdv = null;
    }
  }

  function cpdvMarkupEmitirNota(inputId, wrapCard) {
    const o = cpdvState.emissaoPdv;
    if (!o?.mostrar_opcao_nota) return "";
    const checked = o.emitir_nota_padrao ? " checked" : "";
    const aviso = o.pode_emitir_agora
      ? "Marque para gerar NFC-e, PDF e XML ao confirmar."
      : "Checklist fiscal incompleto — venda entra sem nota; emita depois no histórico.";
    const inner = `<label class="cpdv-pgto-nota__label">
      <input type="checkbox" id="${inputId}"${checked} />
      <span><strong>Emitir NFC-e (cupom fiscal)</strong><small>${aviso}</small></span>
    </label>`;
    if (wrapCard === false) return inner;
    return `<div class="cpdv-pgto-nota">${inner}</div>`;
  }

  function cpdvUnidadeLabel() {
    const sel = document.getElementById("cpdvUnidadeFiscal") || document.getElementById("cpdvMesasUnidade");
    if (sel?.selectedOptions?.[0]?.textContent) return sel.selectedOptions[0].textContent.trim();
    if (cpdvState.unidadeId) return `Unidade #${cpdvState.unidadeId}`;
    return "— selecione a unidade —";
  }

  function cpdvHtmlResumoItensPagamento(maxItems) {
    const lim = maxItems || 5;
    const items = cpdvState.cart;
    if (!items.length) return '<p class="subtle-text">Carrinho vazio.</p>';
    const rows = items.slice(0, lim).map(
      (i) =>
        `<li><span>${escHtml(i.nome)}</span><span>${i.qtd} × ${moeda(i.preco)}</span></li>`
    );
    const extra = items.length > lim ? `<li class="cpdv-pgto-resumo__mais">+ ${items.length - lim} item(ns)</li>` : "";
    return `<ul class="cpdv-pgto-resumo__lista">${rows.join("")}${extra}</ul>`;
  }

  function cpdvParseMoedaInput(raw) {
    const s = String(raw ?? "")
      .replace(/\s/g, "")
      .replace(/\./g, "")
      .replace(",", ".");
    const n = Number(s);
    return Number.isFinite(n) ? n : 0;
  }

  function cpdvFormatMoedaInput(n) {
    return Number(n).toFixed(2).replace(".", ",");
  }

  function cpdvSegPag() {
    return cpdvState.apiMeta?.seguranca_pagamento || {};
  }

  function cpdvEncargosCfg() {
    return cpdvState.apiMeta?.encargos_pdv || cpdvSegPag().encargos_pdv || {};
  }

  function cpdvRotuloEncargoCfg(item) {
    if (!item) return "—";
    return item.modo === "fixo" ? moeda(item.valor) : `${Number(item.valor).toLocaleString("pt-BR", { maximumFractionDigits: 2 })}%`;
  }

  function cpdvEncargoMarcadoPadrao(item, scope) {
    if (!item) return false;
    const campo = scope === "mesa" ? "padrao_mesa" : "padrao_balcao";
    return item[campo] !== false;
  }

  function cpdvCalcValorEncargo(modo, valor, base) {
    const v = Number(valor) || 0;
    const b = Number(base) || 0;
    if (modo === "fixo") return Math.max(0, v);
    return Math.max(0, Math.round((b * v) / 100 * 100) / 100);
  }

  function cpdvBaseEncargos(scope) {
    if (scope === "mesa") {
      const com = cpdvState.comandaAtual?.comanda;
      if (!com) return 0;
      return Math.max(
        0,
        Number(com.valor_subtotal || 0) - Number(com.desconto || 0) + Number(com.acrescimo || 0)
      );
    }
    return Math.max(0, cpdvSubtotal() - cpdvState.desconto + cpdvState.acrescimo);
  }

  function cpdvLerFlagsEncargos(scope) {
    const pre = scope === "mesa" ? "cpdvMesa" : "cpdvPgto";
    return {
      aplicar_taxa_servico: !!document.getElementById(`${pre}AplicarTaxa`)?.checked,
      aplicar_pagamento_cantor: !!document.getElementById(`${pre}AplicarCantor`)?.checked,
    };
  }

  function cpdvCalcEncargosValores(scope, flagsOverride) {
    const cfg = cpdvEncargosCfg();
    const base = cpdvBaseEncargos(scope);
    const flags = flagsOverride || cpdvLerFlagsEncargos(scope);
    let taxa = 0;
    let cantor = 0;
    if (flags.aplicar_taxa_servico && cfg.taxa_servico?.ativa) {
      taxa = cpdvCalcValorEncargo(cfg.taxa_servico.modo, cfg.taxa_servico.valor, base);
    }
    if (flags.aplicar_pagamento_cantor && cfg.pagamento_cantor?.ativo) {
      cantor = cpdvCalcValorEncargo(cfg.pagamento_cantor.modo, cfg.pagamento_cantor.valor, base);
    }
    return { base, taxa, cantor, total: base + taxa + cantor };
  }

  function cpdvHtmlBlocoEncargos(scope) {
    const cfg = cpdvEncargosCfg();
    const pre = scope === "mesa" ? "cpdvMesa" : "cpdvPgto";
    const parts = [];
    if (cfg.taxa_servico?.ativa) {
      const marcada = cpdvEncargoMarcadoPadrao(cfg.taxa_servico, scope);
      parts.push(`<label class="cpdv-pgto-encargo cpdv-pgto-encargo--on">
        <input type="checkbox" id="${pre}AplicarTaxa" ${marcada ? "checked" : ""} />
        <span>Taxa de serviço (${cpdvRotuloEncargoCfg(cfg.taxa_servico)})</span>
        <span class="cpdv-pgto-encargo__val" id="${pre}ValTaxa">—</span>
      </label>`);
    }
    if (cfg.pagamento_cantor?.ativo) {
      const marcado = cpdvEncargoMarcadoPadrao(cfg.pagamento_cantor, scope);
      parts.push(`<label class="cpdv-pgto-encargo cpdv-pgto-encargo--on">
        <input type="checkbox" id="${pre}AplicarCantor" ${marcado ? "checked" : ""} />
        <span>Valor do cantor (${cpdvRotuloEncargoCfg(cfg.pagamento_cantor)})</span>
        <span class="cpdv-pgto-encargo__val" id="${pre}ValCantor">—</span>
      </label>`);
    }
    if (!parts.length) return "";
    return `<div class="cpdv-pgto-encargos" id="${pre}Encargos">
      <p class="cpdv-pgto-encargos__titulo">Encargos incluídos na conta</p>
      ${parts.join("")}
      <p class="cpdv-pgto-hint">Desmarque o item para <strong>retirar da compra</strong>. O total é recalculado na hora.</p>
    </div>`;
  }

  function cpdvAtualizarEncargosPagamento(scope) {
    const pre = scope === "mesa" ? "cpdvMesa" : "cpdvPgto";
    const vals = cpdvCalcEncargosValores(scope);
    const elTaxa = document.getElementById(`${pre}ValTaxa`);
    const elCantor = document.getElementById(`${pre}ValCantor`);
    const elTotal = document.getElementById(`${pre}TotalEncargos`);
    const elHero = document.getElementById("cpdvPgtoHeroValor");
    if (elTaxa) elTaxa.textContent = vals.taxa > 0 ? moeda(vals.taxa) : "—";
    if (elCantor) elCantor.textContent = vals.cantor > 0 ? moeda(vals.cantor) : "—";
    if (elTotal) elTotal.textContent = moeda(vals.total);
    if (elHero && scope === "balcao") elHero.textContent = moeda(vals.total);
    const elMesaTotal = document.getElementById("cpdvMesaTotalFinal");
    if (elMesaTotal && scope === "mesa") elMesaTotal.textContent = moeda(vals.total);
    const formaEl = document.getElementById(scope === "mesa" ? "cpdvMesaFormaPgto" : "cpdvPgtoForma");
    if ((formaEl?.value || "") === "PIX") cpdvAtualizarQrPix(scope);
  }

  function cpdvBindEncargosPagamento(scope) {
    const pre = scope === "mesa" ? "cpdvMesa" : "cpdvPgto";
    ["AplicarTaxa", "AplicarCantor"].forEach((suf) => {
      document.getElementById(`${pre}${suf}`)?.addEventListener("change", () => cpdvAtualizarEncargosPagamento(scope));
    });
    cpdvAtualizarEncargosPagamento(scope);
  }

  function cpdvPayloadEncargos(scope) {
    const flags = cpdvLerFlagsEncargos(scope);
    return {
      aplicar_taxa_servico: flags.aplicar_taxa_servico,
      aplicar_pagamento_cantor: flags.aplicar_pagamento_cantor,
    };
  }

  function cpdvIsCartao(forma) {
    return forma === "Crédito" || forma === "Débito";
  }

  function cpdvCampoObrigatorio(exigir) {
    return exigir ? ' <span class="cpdv-req" title="Obrigatório">*</span>' : "";
  }

  function cpdvListaBandeiras() {
    return cpdvSegPag().bandeiras_cartao || [];
  }

  function cpdvHtmlSelectBandeiras(selectId, exigir) {
    const list = cpdvListaBandeiras();
    if (!list.length) {
      return `<select id="${selectId}" disabled><option value="">Cadastre bandeiras em Configurações do PDV</option></select>`;
    }
    const opts = list.map((b) => `<option value="${escHtml(b.nome)}">${escHtml(b.nome)}</option>`).join("");
    return `<select id="${selectId}"><option value="">${exigir ? "Selecione…" : "Opcional"}</option>${opts}</select>`;
  }

  function cpdvPaintCfgBandeirasList(root, podeEditar) {
    const ul = root?.querySelector("#cpdvCfgBandeirasList");
    if (!ul) return;
    const bandeirasHtml = (cpdvState.cfgBandeirasDraft || []).length
      ? (cpdvState.cfgBandeirasDraft || []).map(
          (nome, idx) =>
            `<li class="cpdv-cfg-bandeira-item"><span>${escHtml(nome)}</span>
            ${podeEditar ? `<button type="button" class="btn danger btn-sm" data-cpdv-rm-bandeira="${idx}">Remover</button>` : ""}</li>`
        ).join("")
      : `<li class="subtle-text">Nenhuma bandeira cadastrada.</li>`;
    ul.innerHTML = bandeirasHtml;
    ul.querySelectorAll("[data-cpdv-rm-bandeira]").forEach((btn) => {
      btn.addEventListener("click", () => {
        const idx = Number(btn.dataset.cpdvRmBandeira);
        cpdvState.cfgBandeirasDraft = (cpdvState.cfgBandeirasDraft || []).filter((_, i) => i !== idx);
        cpdvPaintCfgBandeirasList(root, podeEditar);
      });
    });
  }

  function cpdvHtmlCfgChavesPixList(podeEditar) {
    const list = cpdvState.cfgPixDraft || [];
    if (!list.length) return `<li class="subtle-text">Nenhuma chave PIX cadastrada.</li>`;
    return list.map((c, idx) => {
      const pessoa = String(c.tipo_pessoa || "pj").toUpperCase();
      const tipo = String(c.tipo_chave || "").toUpperCase();
      const titulo = escHtml(c.apelido || c.beneficiario || "PIX");
      const chave = escHtml(c.chave || "");
      return `<li class="cpdv-cfg-pix-item">
        <div>
          <strong>${titulo}</strong>
          <small>${pessoa} · ${tipo}${c.padrao ? " · padrão" : ""}</small>
          <small>${chave}</small>
        </div>
        ${podeEditar ? `<button type="button" class="btn danger btn-sm" data-cpdv-rm-pix="${idx}">Remover</button>` : ""}
      </li>`;
    }).join("");
  }

  function cpdvPaintCfgPixList(root, podeEditar) {
    const ul = root?.querySelector("#cpdvCfgPixList");
    if (!ul) return;
    ul.innerHTML = cpdvHtmlCfgChavesPixList(podeEditar);
    ul.querySelectorAll("[data-cpdv-rm-pix]").forEach((btn) => {
      btn.addEventListener("click", () => {
        const idx = Number(btn.dataset.cpdvRmPix);
        cpdvState.cfgPixDraft = (cpdvState.cfgPixDraft || []).filter((_, i) => i !== idx);
        cpdvPaintCfgPixList(root, podeEditar);
      });
    });
  }

  function cpdvHintManualCartao() {
    return '<p class="cpdv-pgto-hint">NSU e autorização são digitados manualmente pelo operador (comprovante da maquininha). Não vêm automaticamente do TEF ainda.</p>';
  }

  function cpdvListaChavesPix() {
    return cpdvState.apiMeta?.chaves_pix
      || cpdvSegPag().chaves_pix
      || [];
  }

  function cpdvHtmlSelectChavesPix(selectId) {
    const list = cpdvListaChavesPix();
    if (!list.length) {
      return `<select id="${selectId}" disabled><option value="">Cadastre chaves PIX em Configurações do PDV</option></select>`;
    }
    const opts = list.map((c) => {
      const sel = c.padrao ? " selected" : "";
      return `<option value="${c.id}"${sel}>${escHtml(c.rotulo || c.beneficiario || c.chave)}</option>`;
    }).join("");
    return `<select id="${selectId}">${opts}</select>`;
  }

  function cpdvHtmlBlocoPix(scope, exigirId) {
    const pre = scope === "mesa" ? "cpdvMesa" : "cpdvPgto";
    return `<div id="${pre}BlocoPix" class="cpdv-pgto-grid" hidden>
      <label class="cpdv-pgto-field cpdv-pgto-field--full">Chave PIX (PF / PJ)
        ${cpdvHtmlSelectChavesPix(`${pre}PixChave`)}
      </label>
      <div class="cpdv-pgto-field cpdv-pgto-field--full cpdv-pix-qr" id="${pre}PixQrWrap">
        <div class="cpdv-pix-qr__box" id="${pre}PixQr"></div>
        <div class="cpdv-pix-qr__meta">
          <p class="cpdv-pix-qr__titulo">QR Code PIX</p>
          <p class="cpdv-pix-qr__valor" id="${pre}PixQrValor">—</p>
          <p class="cpdv-pix-qr__benef" id="${pre}PixQrBenef">Selecione a chave para gerar</p>
          <button type="button" class="btn neutral btn-sm" id="${pre}PixCopiar">Copiar código PIX</button>
        </div>
      </div>
      <input type="hidden" id="${pre}PixCopia" />
      <label class="cpdv-pgto-field cpdv-pgto-field--full">ID transação PIX${cpdvCampoObrigatorio(exigirId)}
        <input type="text" id="${pre}PixId" placeholder="${exigirId ? "Obrigatório (end-to-end do comprovante)" : "Opcional — informe após o cliente pagar"}" autocomplete="off" />
      </label>
      <p class="cpdv-pgto-hint">Mostre o QR ao cliente. Depois confirme o pagamento e, se a regra exigir, digite o ID da transação.</p>
    </div>`;
  }

  async function cpdvAtualizarQrPix(scope) {
    const pre = scope === "mesa" ? "cpdvMesa" : "cpdvPgto";
    const formaEl = document.getElementById(scope === "mesa" ? "cpdvMesaFormaPgto" : "cpdvPgtoForma");
    if ((formaEl?.value || "") !== "PIX") return;

    const sel = document.getElementById(`${pre}PixChave`);
    const qrBox = document.getElementById(`${pre}PixQr`);
    const copia = document.getElementById(`${pre}PixCopia`);
    const elValor = document.getElementById(`${pre}PixQrValor`);
    const elBenef = document.getElementById(`${pre}PixQrBenef`);
    const chaveId = Number(sel?.value || 0);
    const valor = cpdvCalcEncargosValores(scope).total;

    if (!chaveId || valor <= 0) {
      if (qrBox) qrBox.innerHTML = '<span class="subtle-text">Informe o total e a chave.</span>';
      if (copia) copia.value = "";
      if (elValor) elValor.textContent = moeda(valor);
      if (elBenef) elBenef.textContent = "Cadastre/selecione uma chave PIX";
      return;
    }

    if (qrBox) qrBox.innerHTML = '<span class="subtle-text">Gerando QR…</span>';
    try {
      const r = await cpdvFetch("/pdv/pix/qrcode", {
        method: "POST",
        body: JSON.stringify({ chave_id: chaveId, valor }),
      });
      if (copia) copia.value = r.payload || "";
      if (elValor) elValor.textContent = moeda(r.valor ?? valor);
      if (elBenef) elBenef.textContent = r.chave?.rotulo || r.chave?.beneficiario || "PIX";
      if (qrBox) {
        qrBox.innerHTML = "";
        if (r.qr_data_uri) {
          const img = document.createElement("img");
          img.src = r.qr_data_uri;
          img.alt = "QR Code PIX";
          img.width = 180;
          img.height = 180;
          qrBox.appendChild(img);
        } else if (typeof QRCode !== "undefined" && r.payload) {
          // eslint-disable-next-line no-new
          new QRCode(qrBox, { text: r.payload, width: 180, height: 180 });
        } else {
          qrBox.innerHTML = '<span class="subtle-text">Use o copia e cola abaixo.</span>';
        }
      }
    } catch (e) {
      if (qrBox) qrBox.innerHTML = `<span class="subtle-text">${escHtml(e.message || "Falha ao gerar QR")}</span>`;
      if (copia) copia.value = "";
    }
  }

  function cpdvBindPixPagamento(scope) {
    const pre = scope === "mesa" ? "cpdvMesa" : "cpdvPgto";
    document.getElementById(`${pre}PixChave`)?.addEventListener("change", () => cpdvAtualizarQrPix(scope));
    document.getElementById(`${pre}PixCopiar`)?.addEventListener("click", async () => {
      const txt = document.getElementById(`${pre}PixCopia`)?.value || "";
      if (!txt) {
        toast("Gere o QR antes de copiar.", "warning");
        return;
      }
      try {
        await navigator.clipboard.writeText(txt);
        toast("Código PIX copiado.", "success");
      } catch {
        toast("Não foi possível copiar automaticamente. Use o QR Code.", "warning");
      }
    });
  }

  function cpdvLerDadosPagamento(scope) {
    const pre = scope === "mesa" ? "cpdvMesa" : "cpdvPgto";
    const formaEl = document.getElementById(scope === "mesa" ? "cpdvMesaFormaPgto" : "cpdvPgtoForma");
    const forma = formaEl?.value || "";
    const out = {};
    if (cpdvIsCartao(forma)) {
      out.pagamento_nsu = document.getElementById(`${pre}Nsu`)?.value?.trim() || undefined;
      out.pagamento_autorizacao = document.getElementById(`${pre}Autorizacao`)?.value?.trim() || undefined;
      out.pagamento_bandeira = document.getElementById(`${pre}Bandeira`)?.value?.trim() || undefined;
      const parc = Number(document.getElementById(`${pre}Parcelas`)?.value || 1);
      if (parc > 1) out.pagamento_parcelas = parc;
    }
    if (forma === "PIX") {
      out.pagamento_pix_id = document.getElementById(`${pre}PixId`)?.value?.trim() || undefined;
      const chaveId = Number(document.getElementById(`${pre}PixChave`)?.value || 0);
      if (chaveId > 0) out.pagamento_pix_chave_id = chaveId;
    }
    return out;
  }

  function cpdvValidarPagamentoLocal(forma, scope) {
    const cfg = cpdvSegPag();
    const dados = cpdvLerDadosPagamento(scope);
    if (cpdvIsCartao(forma)) {
      if (cfg.exigir_nsu_cartao && !dados.pagamento_nsu) {
        return "Informe o NSU do cartão.";
      }
      if (cfg.exigir_autorizacao_cartao && !dados.pagamento_autorizacao) {
        return "Informe o código de autorização do cartão.";
      }
      if (cfg.exigir_bandeira_cartao && !dados.pagamento_bandeira) {
        return cpdvListaBandeiras().length
          ? "Selecione a bandeira do cartão."
          : "Cadastre bandeiras em Configurações do PDV e selecione uma.";
      }
      if (dados.pagamento_bandeira && cpdvListaBandeiras().length) {
        const ok = cpdvListaBandeiras().some((b) => b.nome === dados.pagamento_bandeira);
        if (!ok) return "Bandeira inválida. Atualize a lista em Configurações do PDV.";
      }
    }
    if (forma === "PIX") {
      if (!cpdvListaChavesPix().length) {
        return "Cadastre ao menos uma chave PIX (PF ou PJ) em Configurações do PDV.";
      }
      if (!dados.pagamento_pix_chave_id) {
        return "Selecione a chave PIX para gerar o QR Code.";
      }
      if (cfg.exigir_identificador_pix && !dados.pagamento_pix_id) {
        return "Informe o identificador da transação PIX.";
      }
    }
    return null;
  }

  function cpdvSetCampoPagamentoAtivo(el, ativo) {
    if (!el) return;
    el.disabled = !ativo;
    const wrap = el.closest(".cpdv-pgto-field");
    if (wrap) wrap.classList.toggle("is-inactive", !ativo);
  }

  function cpdvSincronizarCamposCartao(scope, forma) {
    const pre = scope === "mesa" ? "cpdvMesa" : "cpdvPgto";
    const ehCartao = cpdvIsCartao(forma);
    const bandeira = document.getElementById(`${pre}Bandeira`);
    const parcelas = document.getElementById(`${pre}Parcelas`);
    const nsu = document.getElementById(`${pre}Nsu`);
    const aut = document.getElementById(`${pre}Autorizacao`);
    // PIX / Dinheiro / demais: bandeira e parcelas ficam inativas.
    cpdvSetCampoPagamentoAtivo(bandeira, ehCartao);
    cpdvSetCampoPagamentoAtivo(nsu, ehCartao);
    cpdvSetCampoPagamentoAtivo(aut, ehCartao);
    cpdvSetCampoPagamentoAtivo(parcelas, ehCartao);
    if (!ehCartao) {
      if (bandeira) bandeira.value = "";
      if (nsu) nsu.value = "";
      if (aut) aut.value = "";
    }
    if (parcelas && !ehCartao) parcelas.value = "1";
  }

  function cpdvAtualizarBlocosPagamento(scope) {
    const formaEl = document.getElementById(scope === "mesa" ? "cpdvMesaFormaPgto" : "cpdvPgtoForma");
    const forma = formaEl?.value || "";
    const pre = scope === "mesa" ? "cpdvMesa" : "cpdvPgto";
    const blocoCart = document.getElementById(`${pre}BlocoCartao`);
    const blocoPix = document.getElementById(`${pre}BlocoPix`);
    if (blocoCart) blocoCart.hidden = !cpdvIsCartao(forma);
    if (blocoPix) blocoPix.hidden = forma !== "PIX";
    cpdvSincronizarCamposCartao(scope, forma);
    if (forma === "PIX") cpdvAtualizarQrPix(scope);
    if (scope !== "mesa") cpdvAtualizarTrocoPagamento();
  }

  function cpdvAtualizarTrocoPagamento() {
    const forma = document.getElementById("cpdvPgtoForma")?.value || "";
    const blocoDin = document.getElementById("cpdvPgtoBlocoDinheiro");
    const blocoCart = document.getElementById("cpdvPgtoBlocoCartao");
    const blocoPix = document.getElementById("cpdvPgtoBlocoPix");
    if (blocoDin) blocoDin.hidden = forma !== "Dinheiro";
    if (blocoCart) blocoCart.hidden = !cpdvIsCartao(forma);
    if (blocoPix) blocoPix.hidden = forma !== "PIX";
    cpdvSincronizarCamposCartao("balcao", forma);
    const recebido = cpdvParseMoedaInput(document.getElementById("cpdvPgtoValor")?.value);
    const trocoEl = document.getElementById("cpdvPgtoTroco");
    if (trocoEl && forma === "Dinheiro") {
      const totalPagar = cpdvCalcEncargosValores("balcao").total;
      const troco = Math.max(0, recebido - totalPagar);
      trocoEl.value = cpdvFormatMoedaInput(troco);
    }
  }

  function cpdvBindPagamentoModal() {
    const forma = document.getElementById("cpdvPgtoForma");
    forma?.addEventListener("change", () => cpdvAtualizarBlocosPagamento("balcao"));
    document.getElementById("cpdvPgtoValor")?.addEventListener("input", cpdvAtualizarTrocoPagamento);
    cpdvAtualizarBlocosPagamento("balcao");
  }

  async function cpdvConfirmarPagamentoBalcao() {
    const unidadeId = cpdvState.unidadeId || Number(document.getElementById("cpdvUnidadeFiscal")?.value || 0);
    const forma = document.getElementById("cpdvPgtoForma")?.value || "PDV";
    const total = cpdvTotal();
    if (!unidadeId) {
      toast("Selecione a unidade antes de pagar.", "warning");
      return;
    }
    if (!cpdvState.cart.length) {
      toast("Carrinho vazio.", "warning");
      return;
    }
    if (forma === "Dinheiro") {
      const recebido = cpdvParseMoedaInput(document.getElementById("cpdvPgtoValor")?.value);
      const totalPagar = cpdvCalcEncargosValores("balcao").total;
      if (recebido + 0.009 < totalPagar) {
        toast("Valor recebido menor que o total.", "warning");
        return;
      }
    }
    const errPg = cpdvValidarPagamentoLocal(forma, "balcao");
    if (errPg) {
      toast(errPg, "warning");
      return;
    }
    const btn = document.getElementById("cpdvPgtoConfirm");
    if (btn) {
      btn.disabled = true;
      btn.textContent = "Processando…";
    }
    const obs = document.getElementById("cpdvPgtoObs")?.value?.trim() || "";
    const idempotencyKey = cpdvUuid();
    const payloadBase = {
      unidade_id: unidadeId,
      forma_pagamento: forma,
      pdv_terminal: "PDV-WEB",
      idempotency_key: idempotencyKey,
      observacao: obs || undefined,
      ...cpdvLerDadosPagamento("balcao"),
      ...cpdvPayloadEncargos("balcao"),
      ...cpdvPayloadEmitirNota("cpdvPgtoEmitirNota"),
    };
    const itens = cpdvState.cart.map((i) => ({
      cardapio_produto_id: i.fonte === "cardapio" || i.cardapioProdutoId ? (i.cardapioProdutoId || i.produtoId) : undefined,
      produto_id: i.estoqueProdutoId || i.produtoId,
      quantidade: i.qtd,
      preco_unitario: i.preco,
      desconto: 0,
    }));
    const payload = { ...payloadBase, itens };
    const totalPagar = cpdvCalcEncargosValores("balcao").total;

    const finalizarLocalOffline = () => {
      cpdvEnfileirarVendaOffline({
        id: idempotencyKey,
        tipo: "balcao",
        path: "/pdv/vendas/balcao",
        payload,
        meta: {
          total: totalPagar,
          forma,
          unidade_id: unidadeId,
          emitir_nota: !!payload.emitir_nota,
        },
      });
      toast(
        `Venda offline guardada (${moeda(totalPagar)}). Sincroniza e emite nota quando a internet voltar.`,
        "warning"
      );
      cpdvState.cart = [];
      cpdvSyncCarrinhoMemoria();
      closeCpdvModal();
      loadComercialPdv?.();
    };

    try {
      if (!cpdvIsOnline()) {
        finalizarLocalOffline();
        return;
      }
      if (cpdvState.apiReady) {
        const r = await cpdvFetch("/pdv/vendas/balcao", {
          method: "POST",
          body: JSON.stringify(payload),
        });
        toast(`Venda #${r.venda_id} registrada (${moeda(r.valor_liquido)}).${msgEmissao(r.emissao)}`, r.emissao?.emitida ? "success" : "info");
        await cpdvPosEmissao(r);
      } else if (typeof window.fiscalPdvConfirmarPagamento === "function") {
        const r = await window.fiscalPdvConfirmarPagamento({
          unidadeId,
          formaPagamento: forma,
          itens,
          ...cpdvPayloadEmitirNota("cpdvPgtoEmitirNota"),
        });
        toast(`Venda fiscal #${r.venda_id} registrada.${msgEmissao(r.emissao)}`, r.emissao?.emitida ? "success" : r.emissao?.skipped ? "info" : "warning");
        await cpdvPosEmissao(r);
      } else {
        finalizarLocalOffline();
        return;
      }
      cpdvState.cart = [];
      cpdvSyncCarrinhoMemoria();
      closeCpdvModal();
      loadComercialPdv?.();
    } catch (e) {
      if (cpdvIsNetworkError(e)) {
        finalizarLocalOffline();
        return;
      }
      toast(e.message || "Venda bloqueada.", "error");
      if (btn) {
        btn.disabled = false;
        btn.textContent = "Confirmar pagamento";
      }
    }
  }

  function cpdvPayloadEmitirNota(inputId) {
    const o = cpdvState.emissaoPdv;
    if (!o?.mostrar_opcao_nota) return {};
    const el = document.getElementById(inputId || "cpdvPgtoEmitirNota");
    return { emitir_nota: !!el?.checked };
  }

  function cpdvBadgeNota(v) {
    const st = String(v.status_documento || "").toLowerCase();
    if (st === "autorizado" || st === "autorizada") return '<span class="fiscal-badge fiscal-badge--ok">Com nota</span>';
    if (st === "rejeitado") return '<span class="fiscal-badge fiscal-badge--warn">Rejeitada</span>';
    return '<span class="fiscal-badge fiscal-badge--pend">Sem nota</span>';
  }

  function cpdvAcoesNota(v) {
    const st = String(v.status_documento || "").toLowerCase();
    const id = v.id;
    if (st === "autorizado" || st === "autorizada") {
      return `<button type="button" class="btn neutral btn-sm" data-cpdv-pdf="${id}">PDF</button>
        <button type="button" class="btn neutral btn-sm" data-cpdv-xml="${id}">XML</button>`;
    }
    return `<button type="button" class="btn secondary btn-sm" data-cpdv-emitir="${id}">Emitir nota</button>`;
  }

  async function cpdvEmitirNotaDepois(vendaId) {
    try {
      toast("Emitindo NFC-e…", "info");
      const r = await cpdvFetch(`/fiscal/emissao/vendas/${vendaId}/nfce`, { method: "POST" });
      if (r.emitida || r.skipped) {
        toast(r.mensagem || r.motivo_skip || "NFC-e processada.", r.emitida ? "success" : "info");
        if (r.emitida && window.fiscalEntregarDocumentosVenda) await window.fiscalEntregarDocumentosVenda(vendaId);
        cpdvRenderHistorico();
      } else {
        toast(r.mensagem || "Não foi possível emitir.", "warning");
      }
    } catch (e) {
      toast(e.message || "Falha ao emitir.", "error");
    }
  }

  function cpdvBindHistoricoNotaActions(root) {
    root.querySelectorAll("[data-cpdv-emitir]").forEach((btn) => {
      btn.addEventListener("click", () => cpdvEmitirNotaDepois(Number(btn.dataset.cpdvEmitir)));
    });
    root.querySelectorAll("[data-cpdv-pdf]").forEach((btn) => {
      btn.addEventListener("click", () => window.fiscalBaixarDanfePdf?.(Number(btn.dataset.cpdvPdf)));
    });
    root.querySelectorAll("[data-cpdv-xml]").forEach((btn) => {
      btn.addEventListener("click", () => window.fiscalBaixarXmlNota?.(Number(btn.dataset.cpdvXml)));
    });
  }

  async function cpdvPosEmissao(r) {
    if (r?.emissao?.emitida && r?.venda_id && typeof window.fiscalEntregarDocumentosVenda === "function") {
      await window.fiscalEntregarDocumentosVenda(r.venda_id);
    }
  }

  function toast(msg, type) {
    const fn = typeof showToast === "function" ? showToast : window.showToast;
    if (typeof fn === "function") fn(msg, type || "info");
    else alert(msg);
  }

  function protoToast(acao) {
    toast(`Protótipo: ${acao} — sem persistência.`, "info");
  }

  function cpdvRoot(rootId) {
    let el = document.getElementById(rootId);
    if (el) return el;
    const secId = rootId.replace(/Root$/, "Section");
    const sec = document.getElementById(secId);
    if (!sec) return null;
    el = document.createElement("div");
    el.id = rootId;
    el.className = "cpdv-page-wrap";
    sec.appendChild(el);
    return el;
  }

  function cpdvAvisoProto() {
    const offlineBadge = `<span class="cpdv-offline-badge" data-cpdv-offline-badge hidden></span>
      <button type="button" class="btn neutral btn-sm" id="cpdvSyncOfflineBtn" hidden>Sincronizar offline</button>`;
    if (cpdvState.apiReady) {
      const m = cpdvState.apiMeta || {};
      const parts = [];
      if (m.modulo_venda_fiscal) parts.push("venda + estoque");
      if (m.modulo_comandas) parts.push("mesas/comandas");
      const net = cpdvIsOnline() ? "" : " · <strong>OFFLINE</strong>";
      return `<div class="cpdv-aviso-bar"><p class="cpdv-aviso-proto cpdv-aviso-proto--ok">PDV operacional (${parts.join(" · ") || "API OK"}). Unidade: ${cpdvState.unidadeId ? `#${cpdvState.unidadeId}` : "selecione abaixo"}${net}.</p>${offlineBadge}</div>`;
    }
    const err = cpdvState.apiError ? escHtml(cpdvState.apiError) : "verifique login e conexão com a API";
    return `<div class="cpdv-aviso-bar"><p class="cpdv-aviso-proto">API PDV indisponível — ${err}. Vendas do caixa podem ser guardadas offline.</p>${offlineBadge}</div>`;
  }

  function cpdvBindAvisoOffline(root) {
    cpdvAtualizarBadgeOffline();
    root?.querySelector("#cpdvSyncOfflineBtn")?.addEventListener("click", async () => {
      const r = await cpdvSincronizarFilaOffline({ silencioso: false });
      if (!r.ok && !r.fail) toast("Nenhuma venda offline pendente.", "info");
      else if (r.ok && !r.fail) toast(`${r.ok} venda(s) sincronizada(s).`, "success");
    });
  }

  async function cpdvInitApi() {
    cpdvBindOfflineListeners();
    cpdvState.apiError = null;
    try {
      const meta = await cpdvFetch("/pdv/meta");
      cpdvState.apiMeta = meta;
      cpdvState.apiReady = !!(meta.modulo_venda_fiscal || meta.modulo_comandas);
      cpdvSincronizarFilaOffline({ silencioso: true }).catch(() => {});
    } catch (e) {
      cpdvState.apiReady = false;
      cpdvState.apiMeta = null;
      cpdvState.apiError = e?.message || String(e);
    }
    if (!cpdvState.unidadeId) {
      const u =
        window.getUser?.()?.unidade_id ||
        window.currentUser?.unidade_id ||
        window.state?.unidadeAtual?.id;
      if (u) cpdvState.unidadeId = Number(u);
      try {
        const saved = localStorage.getItem("cpdv_unidade_id");
        if (saved) cpdvState.unidadeId = Number(saved);
      } catch { /* ignore */ }
    }
    await cpdvRefreshEmissaoOpcoes();
  }

  async function cpdvLoadUnidadesOptions(selectedId) {
    let unis = window.state?.unidades;
    if (!unis?.length) {
      try {
        unis = await cpdvFetch("/unidades");
      } catch {
        unis = DADOS_DEMONSTRACAO_PDV.unidades.map((u) => ({ id: u.id, nome: u.nome }));
      }
    }
    const seen = new Set();
    const unique = (unis || []).filter((u) => {
      const id = Number(u.id);
      if (!id || seen.has(id)) return false;
      seen.add(id);
      return true;
    });
    return unique
      .map((u) => `<option value="${escHtml(u.id)}"${String(selectedId) === String(u.id) ? " selected" : ""}>${escHtml(u.nome)}</option>`)
      .join("");
  }

  async function cpdvLoadProdutosApi() {
    if (!cpdvState.unidadeId || !cpdvState.apiReady) {
      cpdvState.produtosApi = [];
      return;
    }
    try {
      const q = cpdvState.busca ? `&search=${encodeURIComponent(cpdvState.busca)}` : "";
      cpdvState.produtosApi = await cpdvFetch(`/pdv/produtos?unidade_id=${cpdvState.unidadeId}${q}`);
    } catch {
      cpdvState.produtosApi = [];
    }
  }

  function cpdvProdutosAtivos() {
    if (cpdvState.apiReady && cpdvState.produtosApi.length) {
        return cpdvState.produtosApi.map((p) => {
        const cardapioId = p.cardapio_produto_id != null ? Number(p.cardapio_produto_id) : Number(p.id);
        const estoqueId = p.estoque_produto_id != null ? Number(p.estoque_produto_id) : Number(p.id);
        const isCardapio = p.fonte === "cardapio";
        const estoqueOk = p.estoque_ok !== false && (p.saldo == null || p.saldo > 0);
        return {
          id: cardapioId,
          cardapioProdutoId: cardapioId,
          nome: p.nome,
          categoria: (p.categoria || "geral").toLowerCase(),
          preco: Number(p.preco) || 0,
          favorito: false,
          disponivel: isCardapio ? p.disponivel !== false : p.disponivel !== false && (p.saldo == null || p.saldo > 0),
          estoqueOk: isCardapio ? !!p.estoque_ok : estoqueOk,
          aviso: p.aviso || null,
          estoqueProdutoId: estoqueId,
          fonte: p.fonte || "estoque",
        };
      });
    }
    return DADOS_DEMONSTRACAO_PDV.produtos;
  }

  function cpdvCategoriasAtivas() {
    if (cpdvState.apiReady && cpdvState.produtosApi.length) {
      const set = new Set(cpdvState.produtosApi.map((p) => (p.categoria || "geral").toLowerCase()));
      return [{ id: "todos", nome: "Todos" }].concat([...set].sort().map((c) => ({ id: c, nome: c.charAt(0).toUpperCase() + c.slice(1) })));
    }
    return DADOS_DEMONSTRACAO_PDV.categorias;
  }

  function cpdvDestroyCharts() {
    Object.keys(cpdvState.charts).forEach((k) => {
      if (cpdvState.charts[k]) {
        cpdvState.charts[k].destroy();
        cpdvState.charts[k] = null;
      }
    });
  }

  function ensureCpdvModal() {
    let el = document.getElementById("cpdvModal");
    if (el) return el;
    el = document.createElement("div");
    el.id = "cpdvModal";
    el.className = "modal-backdrop";
    el.innerHTML = `
      <div class="modal">
        <header>
          <h2 id="cpdvModalTitle">Comercial / PDV</h2>
          <button type="button" class="close-btn" id="cpdvModalClose" aria-label="Fechar">×</button>
        </header>
        <div class="modal-body" id="cpdvModalBody"></div>
        <div class="cpdv-modal-actions" id="cpdvModalActions"></div>
      </div>`;
    document.body.appendChild(el);
    return el;
  }

  function openCpdvModal(title, bodyHtml, actionsHtml, opts) {
    ensureCpdvModal();
    const modal = document.getElementById("cpdvModal");
    document.getElementById("cpdvModalTitle").textContent = title;
    document.getElementById("cpdvModalBody").innerHTML = bodyHtml;
    document.getElementById("cpdvModalActions").innerHTML = actionsHtml || "";
    modal?.classList.toggle("cpdv-modal--pgto", !!(opts && opts.pagamento));
    modal?.classList.add("active");
  }

  function closeCpdvModal() {
    document.getElementById("cpdvModal")?.classList.remove("active");
  }

  function cpdvSubtotal() {
    return cpdvState.cart.reduce((s, i) => s + i.preco * i.qtd, 0);
  }

  function cpdvTotal() {
    return Math.max(0, cpdvSubtotal() - cpdvState.desconto + cpdvState.acrescimo);
  }

  function cpdvSyncCarrinhoMemoria() {
    DADOS_DEMONSTRACAO_PDV.itensCarrinho = cpdvState.cart.map((i) => ({ ...i }));
  }

  function cpdvAddCart(prodId) {
    const p = cpdvProdutosAtivos().find((x) => x.id === prodId);
    if (!p || !p.disponivel) return;
    const ex = cpdvState.cart.find((c) => c.produtoId === prodId);
    if (ex) ex.qtd += 1;
    else cpdvState.cart.push({
      produtoId: p.id,
      cardapioProdutoId: p.cardapioProdutoId || p.id,
      estoqueProdutoId: p.estoqueProdutoId || p.id,
      nome: p.nome,
      preco: p.preco,
      qtd: 1,
      fonte: p.fonte || "estoque",
    });
    cpdvSyncCarrinhoMemoria();
    loadComercialPdv();
  }

  function cpdvCartQty(prodId, delta) {
    const item = cpdvState.cart.find((c) => c.produtoId === prodId);
    if (!item) return;
    item.qtd += delta;
    if (item.qtd <= 0) cpdvState.cart = cpdvState.cart.filter((c) => c.produtoId !== prodId);
    cpdvSyncCarrinhoMemoria();
    loadComercialPdv();
  }

  function cpdvRemoveCart(prodId) {
    cpdvState.cart = cpdvState.cart.filter((c) => c.produtoId !== prodId);
    cpdvSyncCarrinhoMemoria();
    loadComercialPdv();
  }

  function cpdvFiltrarProdutos() {
    const q = (cpdvState.busca || "").toLowerCase().trim();
    return cpdvProdutosAtivos().filter((p) => {
      if (cpdvState.cat !== "todos" && p.categoria !== cpdvState.cat) return false;
      if (q && !p.nome.toLowerCase().includes(q)) return false;
      return true;
    });
  }

  function cpdvBindPdvEvents(root) {
    root.querySelectorAll("[data-cpdv-cat]").forEach((btn) => {
      btn.addEventListener("click", () => {
        cpdvState.cat = btn.dataset.cpdvCat;
        loadComercialPdv();
      });
    });
    const busca = root.querySelector("#cpdvBuscaProd");
    if (busca) {
      busca.value = cpdvState.busca;
      busca.oninput = () => { cpdvState.busca = busca.value; loadComercialPdv(); };
    }
    root.querySelectorAll("[data-cpdv-add]").forEach((el) => {
      el.addEventListener("click", () => cpdvAddCart(Number(el.dataset.cpdvAdd)));
    });
    root.querySelectorAll("[data-cpdv-qty]").forEach((el) => {
      el.addEventListener("click", () => cpdvCartQty(Number(el.dataset.cpdvProd), Number(el.dataset.cpdvQty)));
    });
    root.querySelectorAll("[data-cpdv-rm]").forEach((el) => {
      el.addEventListener("click", () => cpdvRemoveCart(Number(el.dataset.cpdvRm)));
    });
    root.querySelectorAll("[data-cpdv-proto]").forEach((el) => {
      el.addEventListener("click", () => protoToast(el.dataset.cpdvProto));
    });
    const cliSel = root.querySelector("#cpdvClienteSel");
    if (cliSel) {
      cliSel.value = cpdvState.cliente || "";
      cliSel.onchange = () => { cpdvState.cliente = cliSel.value || null; };
    }
    const telaBtn = root.querySelector("#cpdvVoltarSistema");
    if (telaBtn) {
      const atualizarBotaoTela = () => {
        const fullscreen = document.body.classList.contains("cpdv-pdv-fullscreen");
        telaBtn.textContent = fullscreen ? "Voltar ao sistema" : "Tela cheia";
        telaBtn.title = fullscreen
          ? "Mostrar menu lateral e barra superior"
          : "Ocultar menu lateral e barra superior";
        telaBtn.setAttribute("aria-label", telaBtn.title);
      };
      telaBtn.addEventListener("click", () => {
        const fullscreen = !document.body.classList.contains("cpdv-pdv-fullscreen");
        document.body.classList.toggle("cpdv-focus-mode", fullscreen);
        document.body.classList.toggle("cpdv-pdv-fullscreen", fullscreen);
        document.body.classList.remove("cpdv-topbar-expanded");
        if (fullscreen) {
          document.body.classList.remove("sidebar-open");
          document.getElementById("sidebar")?.classList.remove("is-open");
          document.querySelector(".sidebar-backdrop")?.classList.remove("is-active");
        }
        atualizarBotaoTela();
      });
      atualizarBotaoTela();
    }
  }

  async function cpdvRenderPdv() {
    const root = cpdvRoot("comercialPdvRoot");
    if (!root) return;
    await cpdvInitApi();
    await cpdvLoadProdutosApi();
    const prods = cpdvFiltrarProdutos();
    const cats = cpdvCategoriasAtivas().map((c) =>
      `<button type="button" class="cpdv-chip${cpdvState.cat === c.id ? " active" : ""}" data-cpdv-cat="${escHtml(c.id)}">${escHtml(c.nome)}</button>`
    ).join("");
    const grid = prods.map((p) => {
      const off = !p.disponivel ? " cpdv-prod--off" : "";
      const warn = p.disponivel && p.estoqueOk === false ? " cpdv-prod--warn" : "";
      const fav = p.favorito ? '<span class="cpdv-prod__fav">★ Favorito</span>' : "";
      const click = p.disponivel ? `data-cpdv-add="${p.id}"` : "";
      const aviso = p.disponivel && p.aviso ? `<small class="cpdv-prod__aviso">${escHtml(p.aviso)}</small>` : "";
      return `<button type="button" class="cpdv-prod${off}${warn}" ${click} ${p.disponivel ? "" : "disabled"}>
        <p class="cpdv-prod__nome">${escHtml(p.nome)}</p>
        <span class="cpdv-prod__preco">${moeda(p.preco)}</span>${fav}${aviso}
        ${!p.disponivel ? "<small>Indisponível</small>" : ""}
      </button>`;
    }).join("") || '<p class="subtle-text">Nenhum produto nesta categoria.</p>';
    const cart = cpdvState.cart.length
      ? cpdvState.cart.map((i) => `
        <div class="cpdv-cart-item">
          <div><strong>${escHtml(i.nome)}</strong><br><span>${moeda(i.preco)} × ${i.qtd} = ${moeda(i.preco * i.qtd)}</span></div>
          <div>
            <button type="button" class="btn neutral btn-sm" data-cpdv-qty data-cpdv-prod="${i.produtoId}" data-cpdv-qty="-1">−</button>
            <button type="button" class="btn neutral btn-sm" data-cpdv-qty data-cpdv-prod="${i.produtoId}" data-cpdv-qty="1">+</button>
            <button type="button" class="btn danger btn-sm" data-cpdv-rm="${i.produtoId}">✕</button>
          </div>
        </div>`).join("")
      : '<p class="subtle-text">Carrinho vazio — toque em um produto.</p>';
    const cliOpts = ['<option value="">Consumidor final</option>']
      .concat(DADOS_DEMONSTRACAO_PDV.clientes.map((c) =>
        `<option value="${c.id}"${String(cpdvState.cliente) === String(c.id) ? " selected" : ""}>${escHtml(c.nome)}</option>`
      )).join("");
    const uniOpts = await cpdvLoadUnidadesOptions(cpdvState.unidadeId);
    root.innerHTML = `
      <div class="cpdv-local-shell">
        <div class="cpdv-local-toolbar">
          <div>
            <strong id="cpdvUnidadeTitulo">Caixa · Selecione a unidade</strong>
            <small>Produtos, pagamentos e estoque da unidade</small>
          </div>
          <div class="cpdv-local-toolbar-actions">
            <label class="cpdv-local-unidade">Unidade / estoque
              <select id="cpdvUnidadeFiscal"><option value="">— Selecione —</option>${uniOpts}</select>
            </label>
            <button type="button" class="btn neutral" id="cpdvVoltarSistema">Voltar ao sistema</button>
          </div>
        </div>
        ${cpdvAvisoProto()}
        <div class="cpdv-pdv-layout">
          <section class="table-card cpdv-local-panel cpdv-local-products">
            <div class="cpdv-form-body">
              <div class="cpdv-local-search">
                <input type="search" id="cpdvBuscaProd" class="full-width" placeholder="Buscar produto…" />
              </div>
              <div class="cpdv-pdv-cats">${cats}</div>
              <div class="cpdv-prod-grid">${grid}</div>
            </div>
          </section>
          <section class="table-card cpdv-local-panel cpdv-local-cart">
            <header><h3>Carrinho</h3></header>
            <div class="cpdv-form-body">
              <label>Cliente
                <select id="cpdvClienteSel">${cliOpts}</select>
              </label>
              <p class="cpdv-local-hint">O pagamento baixa o estoque e registra a venda.</p>
              <div class="cpdv-local-cart-list">${cart}</div>
              <div class="cpdv-cart-totais">
                <div>Subtotal <strong>${moeda(cpdvSubtotal())}</strong></div>
                <div>Desconto <span>${moeda(cpdvState.desconto)}</span></div>
                <div>Acréscimo <span>${moeda(cpdvState.acrescimo)}</span></div>
                <div class="total"><span>Total</span><strong>${moeda(cpdvTotal())}</strong></div>
              </div>
              <div class="cpdv-actions">
                <button type="button" class="btn neutral" data-cpdv-proto="Desconto aplicado">Desconto</button>
                <button type="button" class="btn neutral" data-cpdv-proto="Acréscimo aplicado">Acréscimo</button>
                <button type="button" class="btn neutral" data-cpdv-proto="Venda suspensa">Suspender</button>
                <button type="button" class="btn primary" id="cpdvPagarBtn" ${cpdvState.cart.length ? "" : "disabled"}>Confirmar venda</button>
                <button type="button" class="btn danger" data-cpdv-proto="Carrinho limpo" id="cpdvLimparCart">Limpar</button>
              </div>
            </div>
          </section>
        </div>
      </div>`;
    root.querySelector("#cpdvLimparCart")?.addEventListener("click", () => {
      cpdvState.cart = [];
      cpdvSyncCarrinhoMemoria();
      loadComercialPdv();
    });
    const uniEl = root.querySelector("#cpdvUnidadeFiscal");
    if (uniEl) {
      if (cpdvState.unidadeId) uniEl.value = String(cpdvState.unidadeId);
      const atualizarTituloUnidade = () => {
        const titulo = root.querySelector("#cpdvUnidadeTitulo");
        const nome = uniEl.value ? uniEl.selectedOptions[0]?.textContent?.trim() : "";
        if (titulo) titulo.textContent = `Caixa · ${nome || "Selecione a unidade"}`;
      };
      atualizarTituloUnidade();
      uniEl.onchange = async () => {
        cpdvState.unidadeId = uniEl.value ? Number(uniEl.value) : null;
        atualizarTituloUnidade();
        await cpdvRefreshEmissaoOpcoes();
        await cpdvLoadProdutosApi();
        loadComercialPdv();
      };
    }
    root.querySelector("#cpdvPagarBtn")?.addEventListener("click", cpdvAbrirModalPagamento);
    cpdvBindPdvEvents(root);
    cpdvBindAvisoOffline(root);
  }

  const CPDV_MESA_LABEL_API = {
    livre: "Livre",
    ocupada: "Ocupada",
    reservada: "Reservada",
    aguardando_pagamento: "Aguardando pagamento",
    bloqueada: "Bloqueada",
  };

  async function cpdvEnsureMesaComanda(mesaCard) {
    if (mesaCard.status_operacional === "bloqueada") {
      toast("Mesa bloqueada.", "error");
      return null;
    }
    if (!cpdvState.unidadeId) {
      toast("Selecione a unidade.", "error");
      return null;
    }
    await cpdvLoadProdutosApi();
    try {
      if (!mesaCard.comanda_id) {
        cpdvState.comandaAtual = await cpdvFetch("/pdv/comandas/abrir", {
          method: "POST",
          body: JSON.stringify({
            unidade_id: cpdvState.unidadeId,
            mesa_id: mesaCard.mesa_id,
            reserva_mesa_id: mesaCard.reserva_id || null,
            pessoas: 2,
          }),
        });
      } else {
        cpdvState.comandaAtual = await cpdvFetch(`/pdv/comandas/${mesaCard.comanda_id}`);
      }
      cpdvState.mesaCardAtual = {
        ...mesaCard,
        comanda_id: cpdvState.comandaAtual.comanda?.id,
      };
      return cpdvState.comandaAtual;
    } catch (e) {
      toast(e.message, "error");
      return null;
    }
  }

  async function cpdvMesaFinalizarPagamento(comandaId) {
    const forma = document.getElementById("cpdvMesaFormaPgto")?.value || "PIX";
    const errPg = cpdvValidarPagamentoLocal(forma, "mesa");
    if (errPg) {
      toast(errPg, "warning");
      return;
    }
    const btn = document.getElementById("cpdvMesaPagar");
    if (btn) {
      btn.disabled = true;
      btn.textContent = "Processando…";
    }
    const idempotencyKey = cpdvUuid();
    const payload = {
      forma_pagamento: forma,
      pdv_terminal: "PDV-MESA",
      idempotency_key: idempotencyKey,
      ...cpdvLerDadosPagamento("mesa"),
      ...cpdvPayloadEncargos("mesa"),
      ...cpdvPayloadEmitirNota("cpdvMesaEmitirNota"),
    };
    try {
      if (!cpdvIsOnline()) {
        cpdvEnfileirarVendaOffline({
          id: idempotencyKey,
          tipo: "mesa_finalizar",
          path: `/pdv/comandas/${comandaId}/finalizar`,
          payload,
          meta: { comanda_id: comandaId, forma, emitir_nota: !!payload.emitir_nota },
        });
        toast("Fechamento da mesa guardado offline. Sincroniza quando a internet voltar.", "warning");
        cpdvState.comandaAtual = null;
        cpdvState.mesaCardAtual = null;
        await loadComercialMesas();
        return;
      }
      const r = await cpdvFetch(`/pdv/comandas/${comandaId}/finalizar`, {
        method: "POST",
        body: JSON.stringify(payload),
      });
      toast(`Conta fechada — venda #${r.venda_id} (${moeda(r.valor_liquido)}).${msgEmissao(r.emissao)}`, r.emissao?.emitida ? "success" : "success");
      await cpdvPosEmissao(r);
      cpdvState.comandaAtual = null;
      cpdvState.mesaCardAtual = null;
      await loadComercialMesas();
    } catch (e) {
      if (cpdvIsNetworkError(e)) {
        cpdvEnfileirarVendaOffline({
          id: idempotencyKey,
          tipo: "mesa_finalizar",
          path: `/pdv/comandas/${comandaId}/finalizar`,
          payload,
          meta: { comanda_id: comandaId, forma, emitir_nota: !!payload.emitir_nota },
        });
        toast("Sem conexão — fechamento guardado offline para sincronizar depois.", "warning");
        cpdvState.comandaAtual = null;
        cpdvState.mesaCardAtual = null;
        await loadComercialMesas();
        return;
      }
      toast(e.message, "error");
    } finally {
      if (btn) {
        btn.disabled = false;
        btn.textContent = "Confirmar pagamento";
      }
    }
  }

  function cpdvFiltrarProdutosMesa() {
    const q = (cpdvState.mesaBusca || "").toLowerCase().trim();
    return cpdvProdutosAtivos().filter((p) => !q || p.nome.toLowerCase().includes(q));
  }

  function cpdvAtualizarCardMesaNoGrid(mesaId, total) {
    const card = document.querySelector(`[data-cpdv-mesa-api="${mesaId}"]`);
    if (card) {
      card.classList.add("cpdv-mesa--ocupada");
      const items = card.querySelectorAll("small");
      const sm = items[items.length - 1];
      if (sm) sm.textContent = `Total: ${moeda(total || 0)}`;
    }
    const m = cpdvState.salaoMesas?.find((x) => x.mesa_id === mesaId);
    if (m) {
      m.total_parcial = total;
      m.status_operacional = "ocupada";
      m.comanda_id = cpdvState.comandaAtual?.comanda?.id;
    }
  }

  async function cpdvRenderMesaPainel() {
    const host = document.getElementById("cpdvMesaPainel");
    if (!host) return;
    await cpdvRefreshEmissaoOpcoes();
    const mesa = cpdvState.mesaCardAtual;
    const data = cpdvState.comandaAtual;
    if (!mesa || !data?.comanda) {
      host.innerHTML = '<div class="cpdv-mesa-painel-empty"><p class="subtle-text">Selecione uma mesa para abrir comanda, lançar itens e fechar conta.</p></div>';
      return;
    }
    const com = data.comanda;
    const itens = data.itens || [];
    const prods = cpdvFiltrarProdutosMesa();
    const seg = cpdvSegPag();
    const grid = prods.length
      ? prods.map((p) => {
          const off = !p.disponivel ? " cpdv-prod--off" : "";
          const warn = p.disponivel && p.estoqueOk === false ? " cpdv-prod--warn" : "";
          return `<button type="button" class="cpdv-prod cpdv-prod--sm${off}${warn}" ${p.disponivel ? `data-cpdv-mesa-add="${p.id}" data-preco="${p.preco}"` : "disabled"}>
            <span>${escHtml(p.nome)}</span><small>${moeda(p.preco)}${p.aviso && p.disponivel ? ` · ${escHtml(p.aviso)}` : ""}</small></button>`;
        }).join("")
      : '<p class="subtle-text">Cadastre itens no cardápio (Delivery → Produtos) com estoque vinculado.</p>';
    const lista = itens.length
      ? itens.map((i) =>
          `<div class="cpdv-cart-item"><div><strong>${escHtml(i.produto_nome)}</strong><br>${i.quantidade} × ${moeda(i.preco_unitario)} = ${moeda(i.valor_total)}</div>
          <button type="button" class="btn danger btn-sm" data-cpdv-mesa-rm="${i.id}">✕</button></div>`
        ).join("")
      : '<p class="subtle-text">Toque nos produtos abaixo para lançar.</p>';
    host.innerHTML = `
      <header class="cpdv-mesa-painel-head">
        <h3>Mesa ${escHtml(String(mesa.numero))} · Comanda #${com.id}</h3>
        ${mesa.reserva_cliente ? `<p class="subtle-text">Reserva: ${escHtml(mesa.reserva_cliente)}</p>` : ""}
      </header>
      <div class="cpdv-mesa-painel-meta">
        <label>Pessoas <input type="number" id="cpdvMesaPessoas" min="1" value="${com.pessoas || 2}" /></label>
        <button type="button" class="btn neutral btn-sm" id="cpdvMesaSavePessoas">OK</button>
      </div>
      <div class="cpdv-mesa-itens">${lista}</div>
      <div class="cpdv-cart-totais"><div class="total">Subtotal: ${moeda(com.valor_total)}</div>
        <div id="cpdvMesaTotalFinal" class="total">Total: ${moeda(com.valor_total)}</div></div>
      <div class="cpdv-pgto-mesa">
        <h4 class="cpdv-pgto-mesa__title">Fechar conta</h4>
        ${cpdvHtmlBlocoEncargos("mesa")}
        <label class="cpdv-pgto-field">Forma de pagamento
          <select id="cpdvMesaFormaPgto">
            <option value="PIX">PIX</option>
            <option value="Dinheiro">Dinheiro</option>
            <option value="Débito">Cartão débito</option>
            <option value="Crédito">Cartão crédito</option>
          </select>
        </label>
        <div id="cpdvMesaBlocoCartao" class="cpdv-pgto-grid cpdv-pgto-grid--2" hidden>
          <label class="cpdv-pgto-field">Bandeira${cpdvCampoObrigatorio(seg.exigir_bandeira_cartao)}
            ${cpdvHtmlSelectBandeiras("cpdvMesaBandeira", seg.exigir_bandeira_cartao)}
          </label>
          <label class="cpdv-pgto-field">Parcelas
            <input type="number" id="cpdvMesaParcelas" min="1" max="12" value="1" />
          </label>
          <label class="cpdv-pgto-field">NSU${cpdvCampoObrigatorio(seg.exigir_nsu_cartao)}
            <input type="text" id="cpdvMesaNsu" placeholder="${seg.exigir_nsu_cartao ? "Digite o NSU do comprovante" : "Opcional"}" autocomplete="off" />
          </label>
          <label class="cpdv-pgto-field">Autorização${cpdvCampoObrigatorio(seg.exigir_autorizacao_cartao)}
            <input type="text" id="cpdvMesaAutorizacao" placeholder="${seg.exigir_autorizacao_cartao ? "Digite o código do comprovante" : "Opcional"}" autocomplete="off" />
          </label>
          <div class="cpdv-pgto-field cpdv-pgto-field--full">${cpdvHintManualCartao()}</div>
        </div>
        ${cpdvHtmlBlocoPix("mesa", seg.exigir_identificador_pix)}
        ${cpdvMarkupEmitirNota("cpdvMesaEmitirNota")}
        <div class="cpdv-pgto-mesa__actions">
          <button type="button" class="btn neutral" id="cpdvMesaPreConta">Pré-conta</button>
          <button type="button" class="btn primary" id="cpdvMesaPagar">Confirmar pagamento</button>
        </div>
      </div>
      <input type="search" id="cpdvMesaBusca" placeholder="Buscar produto…" value="${escHtml(cpdvState.mesaBusca || "")}" class="full-width" />
      <div class="cpdv-prod-grid cpdv-prod-grid--mesa">${grid}</div>`;
    host.querySelector("#cpdvMesaSavePessoas")?.addEventListener("click", async () => {
      try {
        cpdvState.comandaAtual = await cpdvFetch(`/pdv/comandas/${com.id}`, {
          method: "PATCH",
          body: JSON.stringify({ pessoas: Number(host.querySelector("#cpdvMesaPessoas")?.value || 2) }),
        });
        cpdvRenderMesaPainel();
      } catch (e) {
        toast(e.message, "error");
      }
    });
    host.querySelector("#cpdvMesaPreConta")?.addEventListener("click", async () => {
      try {
        const r = await cpdvFetch(`/pdv/comandas/${com.id}/pre-conta`);
        const w = window.open("", "_blank", "width=420,height=640");
        if (w) {
          w.document.write(`<html><body style="font-family:sans-serif;padding:1rem">${r.html}</body></html>`);
          w.document.close();
          w.print();
        }
      } catch (e) {
        toast(e.message, "error");
      }
    });
    host.querySelector("#cpdvMesaPagar")?.addEventListener("click", () => cpdvMesaFinalizarPagamento(com.id));
    host.querySelector("#cpdvMesaFormaPgto")?.addEventListener("change", () => cpdvAtualizarBlocosPagamento("mesa"));
    cpdvBindEncargosPagamento("mesa");
    cpdvBindPixPagamento("mesa");
    cpdvAtualizarBlocosPagamento("mesa");
    host.querySelector("#cpdvMesaBusca")?.addEventListener("input", (e) => {
      cpdvState.mesaBusca = e.target.value;
      cpdvRenderMesaPainel();
    });
    host.querySelectorAll("[data-cpdv-mesa-add]").forEach((btn) => {
      btn.addEventListener("click", async () => {
        try {
          cpdvState.comandaAtual = await cpdvFetch(`/pdv/comandas/${com.id}/itens`, {
            method: "POST",
            body: JSON.stringify({
              cardapio_produto_id: Number(btn.dataset.cpdvMesaAdd),
              quantidade: 1,
              preco_unitario: Number(btn.dataset.preco || 0),
            }),
          });
          cpdvRenderMesaPainel();
          cpdvAtualizarCardMesaNoGrid(mesa.mesa_id, cpdvState.comandaAtual.comanda?.valor_total);
        } catch (e) {
          toast(e.message, "error");
        }
      });
    });
    host.querySelectorAll("[data-cpdv-mesa-rm]").forEach((btn) => {
      btn.addEventListener("click", async () => {
        try {
          cpdvState.comandaAtual = await cpdvFetch(`/pdv/comandas/${com.id}/itens/${btn.dataset.cpdvMesaRm}`, { method: "DELETE" });
          cpdvRenderMesaPainel();
          cpdvAtualizarCardMesaNoGrid(mesa.mesa_id, cpdvState.comandaAtual.comanda?.valor_total);
        } catch (e) {
          toast(e.message, "error");
        }
      });
    });
  }

  async function cpdvAbrirComandaMesa(mesaCard) {
    await cpdvEnsureMesaComanda(mesaCard);
    document.querySelectorAll(".cpdv-mesa--selected").forEach((c) => c.classList.remove("cpdv-mesa--selected"));
    const el = document.querySelector(`[data-cpdv-mesa-api="${mesaCard.mesa_id}"]`);
    el?.classList.add("cpdv-mesa--selected");
    cpdvRenderMesaPainel();
  }

  async function cpdvRenderMesas() {
    const root = cpdvRoot("comercialMesasRoot");
    if (!root) return;
    await cpdvInitApi();
    try {
      const saved = localStorage.getItem("cpdv_unidade_id");
      if (saved && !cpdvState.unidadeId) cpdvState.unidadeId = Number(saved);
    } catch { /* ignore */ }
    const uniOpts = await cpdvLoadUnidadesOptions(cpdvState.unidadeId);
    let cardsHtml = "";
    if (!cpdvState.apiReady) {
      cardsHtml = `<p class="subtle-text">${escHtml(cpdvState.apiError || "API indisponível")}</p>`;
    } else if (!cpdvState.unidadeId) {
      cardsHtml = '<p class="subtle-text">Selecione a unidade.</p>';
    } else {
      await cpdvLoadProdutosApi();
      try {
        const salao = await cpdvFetch(`/pdv/salao?unidade_id=${cpdvState.unidadeId}`);
        cpdvState.salaoMesas = salao.mesas || [];
        cardsHtml = cpdvState.salaoMesas.map((m) => {
          const st = CPDV_MESA_LABEL_API[m.status_operacional] || m.status_operacional;
          const sel = cpdvState.mesaCardAtual?.mesa_id === m.mesa_id ? " cpdv-mesa--selected" : "";
          return `<button type="button" class="cpdv-mesa cpdv-mesa--${escHtml(m.status_operacional)}${sel}" data-cpdv-mesa-api="${escHtml(m.mesa_id)}">
            <h4>Mesa ${escHtml(String(m.numero))}</h4>
        <span class="cpdv-badge">${escHtml(st)}</span>
            <small>Cap. ${m.capacidade || "—"}</small>
            ${m.reserva_cliente ? `<small>Reserva: ${escHtml(m.reserva_cliente)}</small>` : ""}
            <small>Total: ${moeda(m.total_parcial || 0)}</small>
      </button>`;
        }).join("") || '<p class="subtle-text">Nenhuma mesa — use Reservas → Mesas.</p>';
      } catch (e) {
        cardsHtml = `<p class="subtle-text">${escHtml(e.message)}</p>`;
      }
    }
    root.innerHTML = `
      ${cpdvAvisoProto()}
      <div class="cpdv-garcom-bar table-card cpdv-form-body">
        <label>Unidade
          <select id="cpdvMesasUnidade"><option value="">—</option>${uniOpts}</select>
        </label>
        <button type="button" class="btn primary" id="cpdvMesasReload">Atualizar salão</button>
        <a href="#" class="btn neutral" id="cpdvMesasCadastro">Cadastrar mesas</a>
      </div>
      <div class="cpdv-mesas-layout">
        <div class="cpdv-mesas-grid" id="cpdvMesasGrid">${cardsHtml}</div>
        <aside class="cpdv-mesa-painel-wrap"><div id="cpdvMesaPainel"></div></aside>
      </div>`;
    const uSel = root.querySelector("#cpdvMesasUnidade");
    if (uSel && cpdvState.unidadeId) uSel.value = String(cpdvState.unidadeId);
    uSel?.addEventListener("change", async () => {
      cpdvState.unidadeId = uSel.value ? Number(uSel.value) : null;
      cpdvState.mesaCardAtual = null;
      cpdvState.comandaAtual = null;
      try {
        localStorage.setItem("cpdv_unidade_id", String(cpdvState.unidadeId || ""));
      } catch { /* ignore */ }
      await cpdvRefreshEmissaoOpcoes();
      loadComercialMesas();
    });
    root.querySelector("#cpdvMesasReload")?.addEventListener("click", () => loadComercialMesas());
    root.querySelector("#cpdvMesasCadastro")?.addEventListener("click", (e) => {
      e.preventDefault();
      if (typeof navigateTo === "function") navigateTo("reservaMesa");
    });
    root.querySelectorAll("[data-cpdv-mesa-api]").forEach((el) => {
      el.addEventListener("click", () => {
        const mid = Number(el.dataset.cpdvMesaApi);
        const card = cpdvState.salaoMesas.find((x) => x.mesa_id === mid);
        if (card) cpdvAbrirComandaMesa(card);
      });
    });
    cpdvRenderMesaPainel();
    cpdvBindAvisoOffline(root);
  }

  function cpdvKdsMover(cardId, novaCol) {
    const card = DADOS_DEMONSTRACAO_PDV.kds.find((k) => k.id === cardId);
    if (card) card.col = novaCol;
    loadComercialCozinha();
  }

  function cpdvRenderKdsCard(k) {
    const setorCls = k.setor === "bar" ? "bar" : k.setor === "sobremesa" ? "sobremesa" : "cozinha";
    const prio = k.prio ? '<span class="cpdv-prio">URGENTE</span> ' : "";
    const btns = {
      novo: '<button type="button" class="btn primary btn-sm" data-cpdv-kds-act="preparo" data-cpdv-kds-id="' + k.id + '">Iniciar</button>',
      preparo: '<button type="button" class="btn primary btn-sm" data-cpdv-kds-act="pronto" data-cpdv-kds-id="' + k.id + '">Pronto</button>',
      pronto: '<button type="button" class="btn neutral btn-sm" data-cpdv-kds-act="chamar" data-cpdv-kds-id="' + k.id + '">Chamar</button>',
      entregue: "",
    };
    const extra = k.col === "pronto"
      ? '<button type="button" class="btn primary btn-sm" data-cpdv-kds-act="entregue" data-cpdv-kds-id="' + k.id + '">Entregue</button>'
      : (btns[k.col] || "");
    return `<div class="cpdv-kds-card cpdv-kds-card--${setorCls}">
      ${prio}<strong>Mesa ${k.mesa} · #${k.pedidoId}</strong>
      <div>${k.qtd}× ${escHtml(k.item)}</div>
      <small>${escHtml(k.setor)} · ${escHtml(k.tempo)}</small>
      <div class="cpdv-actions">${extra}</div>
    </div>`;
  }

  function cpdvRenderCozinha() {
    const root = cpdvRoot("comercialCozinhaRoot");
    if (!root) return;
    const cols = CPDV_KDS_COLS.map((col) => {
      const cards = DADOS_DEMONSTRACAO_PDV.kds.filter((k) => k.col === col.id).map(cpdvRenderKdsCard).join("")
        || '<p class="subtle-text">Vazio</p>';
      return `<div class="cpdv-kds-col"><h3>${escHtml(col.nome)}</h3>${cards}</div>`;
    }).join("");
    root.innerHTML = `${cpdvAvisoProto()}<div class="cpdv-kds">${cols}</div>`;
    root.querySelectorAll("[data-cpdv-kds-act]").forEach((btn) => {
      btn.addEventListener("click", () => {
        const act = btn.dataset.cpdvKdsAct;
        const id = btn.dataset.cpdvKdsId;
        if (act === "chamar") protoToast("Garçom chamado");
        else if (act === "preparo" || act === "pronto" || act === "entregue") {
          protoToast(`Item ${act}`);
          cpdvKdsMover(id, act);
        }
      });
    });
  }

  function cpdvRenderDashboardCharts() {
    if (typeof Chart === "undefined") return;
    cpdvDestroyCharts();
    const vendasDia = [4200, 5100, 3800, 6200, 5900, 7100, 4850];
    const labels = ["Seg", "Ter", "Qua", "Qui", "Sex", "Sáb", "Dom"];
    const mk = (id, cfg) => {
      const cv = document.getElementById(id);
      if (!cv) return;
      cpdvState.charts[id] = new Chart(cv, cfg);
    };
    mk("cpdvChartVendas", {
      type: "line",
      data: {
        labels,
        datasets: [{ label: "Vendas (R$)", data: vendasDia, borderColor: "#1565c0", backgroundColor: "rgba(21,101,192,0.15)", fill: true }],
      },
      options: { responsive: true, plugins: { legend: { display: false } } },
    });
    const porCat = DADOS_DEMONSTRACAO_PDV.categorias.filter((c) => c.id !== "todos");
    mk("cpdvChartCategorias", {
      type: "doughnut",
      data: {
        labels: porCat.map((c) => c.nome),
        datasets: [{ data: [35, 22, 28, 15], backgroundColor: ["#3949ab", "#ef6c00", "#00897b", "#8e24aa"] }],
      },
      options: { responsive: true },
    });
  }

  function cpdvRenderDashboard() {
    const root = cpdvRoot("comercialDashboardRoot");
    if (!root) return;
    const vendasHoje = DADOS_DEMONSTRACAO_PDV.vendas.filter((v) => v.data === "2026-07-15");
    const totalHoje = vendasHoje.reduce((s, v) => s + v.total, 0);
    const mesasOcup = DADOS_DEMONSTRACAO_PDV.mesas.filter((m) => !["livre", "bloqueada", "limpeza"].includes(m.status)).length;
    root.innerHTML = `
      ${cpdvAvisoProto()}
      <div class="cpdv-cards">
        <div class="cpdv-card"><span>Vendas hoje</span><strong>${moeda(totalHoje)}</strong></div>
        <div class="cpdv-card"><span>Pedidos abertos</span><strong>${DADOS_DEMONSTRACAO_PDV.pedidos.length}</strong></div>
        <div class="cpdv-card"><span>Mesas ocupadas</span><strong>${mesasOcup}/${DADOS_DEMONSTRACAO_PDV.mesas.length}</strong></div>
        <div class="cpdv-card"><span>Ticket médio</span><strong>${moeda(totalHoje / (vendasHoje.length || 1))}</strong></div>
      </div>
      <div class="cpdv-charts">
        <div class="cpdv-chart-box"><h4>Vendas da semana</h4><canvas id="cpdvChartVendas"></canvas></div>
        <div class="cpdv-chart-box"><h4>Mix por categoria</h4><canvas id="cpdvChartCategorias"></canvas></div>
      </div>
      <div class="table-card">
        <header><h3>Últimas vendas</h3></header>
        <div class="table-wrap"><table class="data-table">
          <thead><tr><th>Hora</th><th>Total</th><th>Forma</th><th>Garçom</th></tr></thead>
          <tbody>${DADOS_DEMONSTRACAO_PDV.vendas.slice(0, 5).map((v) =>
            `<tr><td>${escHtml(v.hora)}</td><td>${moeda(v.total)}</td><td>${escHtml(v.forma)}</td><td>${escHtml(v.garcom)}</td></tr>`
          ).join("")}</tbody>
        </table></div>
      </div>`;
    cpdvRenderDashboardCharts();
  }

  function cpdvStatusBadge(st) {
    const map = { aberto: "ok", aguardando: "warn", producao: "warn", fechamento: "danger" };
    const lbl = { aberto: "Aberto", aguardando: "Aguardando", producao: "Em produção", fechamento: "Fechamento" };
    return `<span class="cpdv-badge cpdv-badge--${map[st] || "muted"}">${escHtml(lbl[st] || st)}</span>`;
  }

  function cpdvRenderPedidos() {
    const root = cpdvRoot("comercialPedidosRoot");
    if (!root) return;
    const rows = DADOS_DEMONSTRACAO_PDV.pedidos.map((p) =>
      `<tr>
        <td>#${p.id}</td><td>Mesa ${p.mesa}</td><td>${escHtml(p.garcom)}</td>
        <td>${cpdvStatusBadge(p.status)}</td><td>${p.itens}</td><td>${moeda(p.total)}</td><td>${escHtml(p.hora)}</td>
        <td><button type="button" class="btn neutral btn-sm" data-cpdv-proto="Pedido #${p.id} visualizado">Ver</button></td>
      </tr>`
    ).join("");
    root.innerHTML = `
      ${cpdvAvisoProto()}
      <div class="table-card">
        <header><h3>Pedidos em andamento</h3></header>
        <div class="table-wrap"><table class="data-table">
          <thead><tr><th>#</th><th>Mesa</th><th>Garçom</th><th>Status</th><th>Itens</th><th>Total</th><th>Hora</th><th></th></tr></thead>
          <tbody>${rows}</tbody>
        </table></div>
      </div>`;
    root.querySelectorAll("[data-cpdv-proto]").forEach((el) => {
      el.addEventListener("click", () => protoToast(el.dataset.cpdvProto));
    });
  }

  function cpdvAbrirModalPagamento() {
    cpdvRefreshEmissaoOpcoes().then(() => {
      cpdvAbrirModalPagamentoInner();
    });
  }

  function cpdvAbrirModalPagamentoInner() {
    const total = cpdvTotal();
    const qtdItens = cpdvState.cart.reduce((s, i) => s + i.qtd, 0);
    const valorInicial = total > 0 ? total.toFixed(2).replace(".", ",") : "0,00";
    const semUnidade = !(cpdvState.unidadeId || document.getElementById("cpdvUnidadeFiscal")?.value);
    const seg = cpdvSegPag();
    const encargosCfg = cpdvEncargosCfg();
    const encVals = cpdvCalcEncargosValores("balcao", {
      aplicar_taxa_servico: !!encargosCfg.taxa_servico?.ativa
        && cpdvEncargoMarcadoPadrao(encargosCfg.taxa_servico, "balcao"),
      aplicar_pagamento_cantor: !!encargosCfg.pagamento_cantor?.ativo
        && cpdvEncargoMarcadoPadrao(encargosCfg.pagamento_cantor, "balcao"),
    });
    const body = `
      <div class="cpdv-pgto">
        ${semUnidade ? '<p class="cpdv-pgto-alerta">Selecione a <strong>unidade</strong> no PDV antes de confirmar.</p>' : ""}
        <div class="cpdv-pgto-hero">
          <span class="cpdv-pgto-hero__label">Total a receber</span>
          <strong class="cpdv-pgto-hero__valor" id="cpdvPgtoHeroValor">${moeda(encVals.total)}</strong>
          <span class="cpdv-pgto-hero__meta">${qtdItens} item(ns) · ${escHtml(cpdvUnidadeLabel())}</span>
      </div>
        ${cpdvHtmlBlocoEncargos("balcao")}
        <div class="cpdv-pgto-resumo">
          <h4>Resumo do pedido</h4>
          ${cpdvHtmlResumoItensPagamento(6)}
          ${cpdvState.desconto > 0 || cpdvState.acrescimo > 0 ? `<p class="cpdv-pgto-resumo__ajustes">Desconto ${moeda(cpdvState.desconto)} · Acréscimo ${moeda(cpdvState.acrescimo)}</p>` : ""}
        </div>
        <div class="cpdv-pgto-form">
          <label class="cpdv-pgto-field cpdv-pgto-field--full">Forma de pagamento
            <select id="cpdvPgtoForma">
              <option value="PIX">PIX</option>
              <option value="Dinheiro">Dinheiro</option>
              <option value="Débito">Cartão débito</option>
              <option value="Crédito">Cartão crédito</option>
              <option value="Voucher">Voucher</option>
              <option value="Vale-consumo">Vale-consumo</option>
              <option value="Cortesia">Cortesia</option>
            </select>
          </label>
          <div id="cpdvPgtoBlocoDinheiro" class="cpdv-pgto-grid cpdv-pgto-grid--2" hidden>
            <label class="cpdv-pgto-field">Valor recebido
              <input type="text" id="cpdvPgtoValor" inputmode="decimal" value="${valorInicial}" autocomplete="off" />
            </label>
            <label class="cpdv-pgto-field">Troco
              <input type="text" id="cpdvPgtoTroco" value="0,00" readonly />
            </label>
          </div>
          <div id="cpdvPgtoBlocoCartao" class="cpdv-pgto-grid cpdv-pgto-grid--2" hidden>
            <label class="cpdv-pgto-field">Bandeira${cpdvCampoObrigatorio(seg.exigir_bandeira_cartao)}
              ${cpdvHtmlSelectBandeiras("cpdvPgtoBandeira", seg.exigir_bandeira_cartao)}
            </label>
            <label class="cpdv-pgto-field">Parcelas
              <input type="number" id="cpdvPgtoParcelas" min="1" max="12" value="1" />
            </label>
            <label class="cpdv-pgto-field">NSU${cpdvCampoObrigatorio(seg.exigir_nsu_cartao)}
              <input type="text" id="cpdvPgtoNsu" placeholder="${seg.exigir_nsu_cartao ? "Digite o NSU do comprovante" : "Opcional"}" autocomplete="off" />
            </label>
            <label class="cpdv-pgto-field">Autorização${cpdvCampoObrigatorio(seg.exigir_autorizacao_cartao)}
              <input type="text" id="cpdvPgtoAutorizacao" placeholder="${seg.exigir_autorizacao_cartao ? "Digite o código do comprovante" : "Opcional"}" autocomplete="off" />
            </label>
            <div class="cpdv-pgto-field cpdv-pgto-field--full">${cpdvHintManualCartao()}</div>
          </div>
          ${cpdvHtmlBlocoPix("balcao", seg.exigir_identificador_pix)}
          <label class="cpdv-pgto-field cpdv-pgto-field--full">Observação
            <input type="text" id="cpdvPgtoObs" placeholder="Ex.: cliente VIP, mesa externa…" maxlength="200" />
          </label>
        </div>
        ${cpdvMarkupEmitirNota("cpdvPgtoEmitirNota")}
      </div>`;
    const acts = `<button type="button" class="btn primary" id="cpdvPgtoConfirm">Confirmar pagamento</button>
      <button type="button" class="btn neutral" id="cpdvPgtoCancel">Cancelar</button>`;
    openCpdvModal("Pagamento", body, acts, { pagamento: true });
    cpdvBindPagamentoModal();
    cpdvBindEncargosPagamento("balcao");
    cpdvBindPixPagamento("balcao");
    document.getElementById("cpdvPgtoConfirm")?.addEventListener("click", () => cpdvConfirmarPagamentoBalcao());
    document.getElementById("cpdvPgtoCancel")?.addEventListener("click", closeCpdvModal);
  }

  function cpdvRenderPagamentos() {
    const root = cpdvRoot("comercialPagamentosRoot");
    if (!root) return;
    root.innerHTML = `
      ${cpdvAvisoProto()}
      <div class="cpdv-cards">
        <div class="cpdv-card"><span>Recebido hoje</span><strong>${moeda(335.3)}</strong></div>
        <div class="cpdv-card"><span>Pendente</span><strong>${moeda(389.9)}</strong></div>
        <div class="cpdv-card"><span>PIX</span><strong>${moeda(245.8)}</strong></div>
        <div class="cpdv-card"><span>Cartão</span><strong>${moeda(89.5)}</strong></div>
      </div>
      <div class="cpdv-actions">
        <button type="button" class="btn primary" id="cpdvSimPgto">Simular pagamento</button>
        <button type="button" class="btn neutral" data-cpdv-proto="Estorno solicitado">Estornar</button>
        <button type="button" class="btn neutral" data-cpdv-proto="Divisão de conta">Dividir conta</button>
        <button type="button" class="btn neutral" data-cpdv-proto="Múltiplas formas">Múltiplas formas</button>
      </div>`;
    root.querySelector("#cpdvSimPgto")?.addEventListener("click", cpdvAbrirModalPagamento);
    root.querySelectorAll("[data-cpdv-proto]").forEach((el) => {
      el.addEventListener("click", () => protoToast(el.dataset.cpdvProto));
    });
  }

  function cpdvRenderFechamento() {
    const root = cpdvRoot("comercialFechamentoRoot");
    if (!root) return;
    root.innerHTML = `
      ${cpdvAvisoProto()}
      <div class="table-card cpdv-form-body">
        <h3>1. Abertura de caixa</h3>
        <div class="filters-grid">
          <label>Operador<input value="Caixa 01" /></label>
          <label>Unidade<select><option>Matriz — Centro</option><option>Filial — Batista Campos</option></select></label>
          <label>Terminal<input value="PDV-01" /></label>
          <label>Valor inicial<input value="200,00" /></label>
          <label>Data/hora<input value="15/07/2026 11:00" readonly /></label>
          <label><button type="button" class="btn primary" data-cpdv-proto="Caixa aberto">Abrir caixa</button></label>
        </div>
      </div>
      <div class="table-card cpdv-form-body">
        <h3>2. Durante o caixa</h3>
        <div class="cpdv-cards">
          <div class="cpdv-card"><span>Vendas</span><strong>${moeda(4820.5)}</strong></div>
          <div class="cpdv-card"><span>Entradas</span><strong>${moeda(120)}</strong></div>
          <div class="cpdv-card"><span>Sangrias</span><strong>${moeda(350)}</strong></div>
          <div class="cpdv-card"><span>Suprimentos</span><strong>${moeda(100)}</strong></div>
          <div class="cpdv-card"><span>Cancelamentos</span><strong>${moeda(45)}</strong></div>
          <div class="cpdv-card"><span>Descontos</span><strong>${moeda(88)}</strong></div>
        </div>
      </div>
      <div class="table-card cpdv-form-body">
        <h3>3. Fechamento</h3>
        <div class="filters-grid">
          <label>Valor esperado<input value="4.820,50" readonly /></label>
          <label>Valor contado<input value="4.795,00" /></label>
          <label>Diferença<input value="-25,50" readonly /></label>
          <label>Justificativa<input placeholder="Informar se houver diferença" /></label>
          <label>Observações<input /></label>
        </div>
        <p class="subtle-text">Resumo: Dinheiro ${moeda(890)} · PIX ${moeda(2100)} · Débito ${moeda(980)} · Crédito ${moeda(850.5)}</p>
        <div class="cpdv-actions">
          <button type="button" class="btn primary" data-cpdv-proto="Fechamento de caixa registrado">Fechar caixa</button>
          <button type="button" class="btn neutral" data-cpdv-proto="Conferência impressa">Imprimir conferência</button>
        </div>
      </div>`;
    root.querySelectorAll("[data-cpdv-proto]").forEach((el) => {
      el.addEventListener("click", () => protoToast(el.dataset.cpdvProto));
    });
  }

  function cpdvRenderClientes() {
    const root = cpdvRoot("comercialClientesRoot");
    if (!root) return;
    const rows = DADOS_DEMONSTRACAO_PDV.clientes.map((c) =>
      `<tr>
        <td>${escHtml(c.nome)}</td><td>${escHtml(c.telefone)}</td><td>${escHtml(c.whatsapp || "—")}</td>
        <td>${escHtml(c.cpf || "—")}</td><td>${escHtml(c.restricao || "—")}</td>
        <td>${escHtml(c.ultima)}</td><td>${moeda(c.totalGasto || 0)}</td><td>${c.visitas}</td>
        <td><button type="button" class="btn neutral btn-sm" data-cpdv-proto="Cliente ${escHtml(c.nome)}">Ver</button></td>
      </tr>`
    ).join("");
    root.innerHTML = `
      ${cpdvAvisoProto()}
      <div class="table-card cpdv-form-body">
        <h3>Novo cliente (demo)</h3>
        <div class="filters-grid">
          <label>Nome<input placeholder="Nome completo" /></label>
          <label>Telefone<input /></label>
          <label>WhatsApp<input /></label>
          <label>CPF (opcional)<input /></label>
          <label>Nascimento<input type="date" /></label>
          <label>Preferência<input /></label>
          <label>Restrição alimentar<input /></label>
          <label>Observações<input /></label>
          <label><button type="button" class="btn primary" data-cpdv-proto="Cliente salvo (simulado)">Salvar</button></label>
        </div>
      </div>
      <div class="table-card"><div class="table-wrap"><table class="data-table">
        <thead><tr><th>Nome</th><th>Telefone</th><th>WhatsApp</th><th>CPF</th><th>Restrição</th><th>Última compra</th><th>Total gasto</th><th>Visitas</th><th></th></tr></thead>
        <tbody>${rows}</tbody>
      </table></div></div>`;
    root.querySelectorAll("[data-cpdv-proto]").forEach((el) => {
      el.addEventListener("click", () => protoToast(el.dataset.cpdvProto));
    });
  }

  async function cpdvRenderHistorico() {
    const root = cpdvRoot("comercialHistoricoRoot");
    if (!root) return;
    await cpdvInitApi();
    let rows = "";
    if (cpdvState.apiReady) {
      try {
        const vendas = await cpdvFetch("/pdv/vendas?limit=50");
        rows = (vendas || []).map((v) =>
          `<tr>
            <td>#${v.id}</td><td>${escHtml(String(v.data_venda || "").slice(0, 16))}</td><td>${escHtml(v.unidade_nome || "")}</td>
            <td>${escHtml(v.numero_mesa || v.nome_mesa || "—")}</td><td>${escHtml(v.origem_venda || "pdv")}</td>
            <td>${cpdvBadgeNota(v)}</td>
            <td>${moeda(v.valor_liquido)}</td><td>${escHtml(v.forma_pagamento || "")}</td>
            <td>${cpdvStatusBadge(v.status === "cancelada" ? "fechamento" : "aberto")}</td>
            <td class="cpdv-actions" style="margin:0;flex-wrap:nowrap">${cpdvAcoesNota(v)}</td>
          </tr>`
        ).join("") || '<tr><td colspan="10">Nenhuma venda registrada.</td></tr>';
      } catch {
        rows = "";
      }
    }
    if (!rows) {
      rows = DADOS_DEMONSTRACAO_PDV.vendas.map((v) =>
      `<tr>
        <td>#${v.id}</td><td>${escHtml(v.data)} ${escHtml(v.hora)}</td><td>${escHtml(v.unidade)}</td>
        <td>${v.mesa}</td><td>${escHtml(v.cliente)}</td><td>${escHtml(v.operador)}</td>
        <td>${moeda(v.total)}</td><td>${escHtml(v.forma)}</td>
        <td>${cpdvStatusBadge(v.status)}</td>
          <td class="cpdv-actions" style="margin:0"><button type="button" class="btn neutral btn-sm" data-cpdv-proto="Ver venda #${v.id}">Ver</button></td>
      </tr>`
    ).join("");
    }
    root.innerHTML = `
      ${cpdvAvisoProto()}
      <div class="table-card">
        <header><h3>Histórico de vendas (PDV / mesa)</h3></header>
        <div class="table-wrap"><table class="data-table">
          <thead><tr><th>Nº</th><th>Data/hora</th><th>Unidade</th><th>Mesa</th><th>Origem</th><th>Nota</th><th>Total</th><th>Pagamento</th><th>Status</th><th>Ações</th></tr></thead>
          <tbody>${rows}</tbody>
        </table></div>
      </div>`;
    cpdvBindHistoricoNotaActions(root);
    root.querySelectorAll("[data-cpdv-proto]").forEach((el) => {
      el.addEventListener("click", () => protoToast(el.dataset.cpdvProto));
    });
  }

  function cpdvRenderRelatorios() {
    const root = cpdvRoot("comercialRelatoriosRoot");
    if (!root) return;
    const rels = [
      ["Vendas por período", "Totais e comparativo diário"],
      ["Vendas por unidade", "Consolidado multi-loja"],
      ["Vendas por garçom", "Performance de atendimento"],
      ["Produtos mais vendidos", "Ranking e participação"],
      ["Produtos menos vendidos", "Oportunidade de cardápio"],
      ["Ticket médio", "Por período e unidade"],
      ["Formas de pagamento", "Mix de recebimentos"],
      ["Cancelamentos", "Itens e motivos"],
      ["Descontos", "Volume e autorização"],
      ["Mesas mais utilizadas", "Ocupação do salão"],
      ["Horários de maior movimento", "Pico por hora"],
      ["Vendas por cliente", "Recorrência"],
      ["Margem estimada", "Estimativa futura com CMV"],
    ];
    const cards = rels.map(([t, d]) =>
      `<div class="cpdv-rel-card"><h4>${escHtml(t)}</h4><p>${escHtml(d)}</p>
      <button type="button" class="btn primary" data-cpdv-proto="PDF: ${escHtml(t)}">PDF</button>
      <button type="button" class="btn neutral" data-cpdv-proto="Excel: ${escHtml(t)}">Excel</button></div>`
    ).join("");
    root.innerHTML = `${cpdvAvisoProto()}<div class="cpdv-rel-grid">${cards}</div>`;
    root.querySelectorAll("[data-cpdv-proto]").forEach((el) => {
      el.addEventListener("click", () => protoToast(el.dataset.cpdvProto));
    });
  }

  function cpdvRenderConfiguracoes() {
    const root = cpdvRoot("comercialConfiguracoesRoot");
    if (!root) return;
    cpdvInitApi().then(async () => {
      const uniOpts = await cpdvLoadUnidadesOptions(cpdvState.unidadeId);
      const meta = cpdvState.apiMeta || {};
      const seg = meta.seguranca_pagamento || {};
      const podeEditarSeg = !!seg.pode_editar;
      cpdvState.cfgBandeirasDraft = (seg.bandeiras_cartao || []).map((b) => b.nome);
      cpdvState.cfgPixDraft = (meta.chaves_pix || seg.chaves_pix || []).map((c) => ({ ...c }));
      const bandeirasHtml = cpdvState.cfgBandeirasDraft.length
        ? cpdvState.cfgBandeirasDraft.map(
            (nome, idx) =>
              `<li class="cpdv-cfg-bandeira-item"><span>${escHtml(nome)}</span>
              ${podeEditarSeg ? `<button type="button" class="btn danger btn-sm" data-cpdv-rm-bandeira="${idx}">Remover</button>` : ""}</li>`
          ).join("")
        : `<li class="subtle-text">Nenhuma bandeira cadastrada.</li>`;
      const pixHtml = cpdvHtmlCfgChavesPixList(podeEditarSeg);
      const enc = meta.encargos_pdv || cpdvEncargosCfg();
      const taxa = enc.taxa_servico || {};
      const cantor = enc.pagamento_cantor || {};
      const selModo = (id, val) =>
        `<select id="${id}"><option value="percentual" ${val === "percentual" ? "selected" : ""}>% percentual</option><option value="fixo" ${val === "fixo" ? "selected" : ""}>R$ valor fixo</option></select>`;
      root.innerHTML = `
        ${cpdvAvisoProto()}
        <div class="table-card cpdv-form-body">
          <h3>Unidade padrão do PDV / mesas</h3>
          <label>Unidade <select id="cpdvCfgUnidade"><option value="">—</option>${uniOpts}</select></label>
          <p class="subtle-text">Usada no Caixa, Mesas e Comandas. Deve ser a mesma unidade do estoque (CNPJ).</p>
          <button type="button" class="btn primary" id="cpdvCfgSave">Salvar preferência</button>
        </div>
        <div class="table-card cpdv-form-body">
          <h3>Segurança no pagamento (anti-fraude / auditoria)</h3>
          <p class="subtle-text">NSU, autorização e ID PIX <strong>não são automáticos</strong>: o caixa digita conforme o comprovante. Marque abaixo o que será <strong>obrigatório</strong> para confirmar a venda. Os dados ficam gravados na venda para conciliação.</p>
          ${podeEditarSeg ? `
          <div class="cpdv-cfg-checks">
            <label><input type="checkbox" id="cpdvCfgExigirNsu" ${seg.exigir_nsu_cartao ? "checked" : ""} /> Exigir NSU em cartão (crédito/débito)</label>
            <label><input type="checkbox" id="cpdvCfgExigirAut" ${seg.exigir_autorizacao_cartao ? "checked" : ""} /> Exigir código de autorização do cartão</label>
            <label><input type="checkbox" id="cpdvCfgExigirBandeira" ${seg.exigir_bandeira_cartao ? "checked" : ""} /> Exigir bandeira do cartão (select)</label>
            <label><input type="checkbox" id="cpdvCfgExigirPix" ${seg.exigir_identificador_pix ? "checked" : ""} /> Exigir identificador da transação PIX</label>
          </div>
          <h4>Bandeiras de cartão (select no pagamento)</h4>
          <p class="subtle-text">Cadastre as bandeiras aceitas no caixa. No PDV, o operador escolhe em uma lista — não digita texto livre.</p>
          <ul id="cpdvCfgBandeirasList" class="cpdv-cfg-bandeiras">${bandeirasHtml}</ul>
          <div class="cpdv-cfg-bandeira-add">
            <input type="text" id="cpdvCfgNovaBandeira" placeholder="Ex.: Visa, Mastercard, Elo…" maxlength="40" />
            <button type="button" class="btn neutral" id="cpdvCfgAddBandeira">Adicionar bandeira</button>
          </div>
          <button type="button" class="btn primary" id="cpdvCfgSaveSeg">Salvar regras e bandeiras</button>
          ` : `<p class="subtle-text">Somente administrador ou gerente pode alterar estas regras.</p>
          <ul class="subtle-text">
            <li>NSU cartão: ${seg.exigir_nsu_cartao ? "obrigatório (manual)" : "opcional"}</li>
            <li>Autorização cartão: ${seg.exigir_autorizacao_cartao ? "obrigatória (manual)" : "opcional"}</li>
            <li>Bandeira: ${seg.exigir_bandeira_cartao ? "obrigatória (select)" : "opcional"} — ${(seg.bandeiras_cartao || []).map((b) => escHtml(b.nome)).join(", ") || "nenhuma cadastrada"}</li>
            <li>ID PIX: ${seg.exigir_identificador_pix ? "obrigatório (manual)" : "opcional"}</li>
          </ul>`}
        </div>
        <div class="table-card cpdv-form-body">
          <h3>Chaves PIX (pessoa física e jurídica)</h3>
          <p class="subtle-text">Cadastre as chaves usadas no caixa. No pagamento PIX o PDV gera o <strong>QR Code</strong> e o <strong>copia e cola</strong> com o valor da compra.</p>
          ${podeEditarSeg ? `
          <ul id="cpdvCfgPixList" class="cpdv-cfg-pix">${pixHtml}</ul>
          <div class="cpdv-cfg-pix-form">
            <label>Apelido <input type="text" id="cpdvCfgPixApelido" maxlength="80" placeholder="Ex.: Caixa matriz PJ" /></label>
            <label>Tipo pessoa
              <select id="cpdvCfgPixPessoa">
                <option value="pj">Pessoa jurídica</option>
                <option value="pf">Pessoa física</option>
              </select>
            </label>
            <label>Tipo da chave
              <select id="cpdvCfgPixTipo">
                <option value="cnpj">CNPJ</option>
                <option value="cpf">CPF</option>
                <option value="email">E-mail</option>
                <option value="telefone">Telefone</option>
                <option value="aleatoria">Aleatória</option>
              </select>
            </label>
            <label>Chave PIX <input type="text" id="cpdvCfgPixChave" maxlength="180" placeholder="CNPJ, CPF, e-mail, telefone ou aleatória" /></label>
            <label>Nome do beneficiário <input type="text" id="cpdvCfgPixBenef" maxlength="160" placeholder="Como aparece no app do cliente" /></label>
            <label>Cidade <input type="text" id="cpdvCfgPixCidade" maxlength="40" value="BELEM" /></label>
            <label>CPF/CNPJ do recebedor (opcional) <input type="text" id="cpdvCfgPixDoc" maxlength="20" /></label>
            <label class="cpdv-cfg-pix-padrao"><input type="checkbox" id="cpdvCfgPixPadrao" /> Usar como padrão no pagamento</label>
          </div>
          <div class="cpdv-actions" style="margin:0.75rem 0 0">
            <button type="button" class="btn neutral" id="cpdvCfgAddPix">Adicionar chave</button>
            <button type="button" class="btn primary" id="cpdvCfgSavePix">Salvar chaves PIX</button>
          </div>
          ` : `<ul class="subtle-text">${(meta.chaves_pix || seg.chaves_pix || []).map((c) => `<li>${escHtml(c.rotulo || c.chave)}</li>`).join("") || "<li>Nenhuma chave cadastrada.</li>"}</ul>`}
        </div>
        <div class="table-card cpdv-form-body">
          <h3>Taxa de serviço e pagamento do cantor</h3>
          <p class="subtle-text">Quando ativas, aparecem <strong>marcadas</strong> no pagamento (caixa e mesa). O operador só desmarca se o cliente pedir para retirar.</p>
          ${podeEditarSeg ? `
          <div class="cpdv-cfg-encargos-grid">
            <fieldset class="cpdv-cfg-encargo">
              <legend>Taxa de serviço</legend>
              <label><input type="checkbox" id="cpdvCfgTaxaAtiva" ${taxa.ativa ? "checked" : ""} /> Ativa no PDV</label>
              <label>Modo ${selModo("cpdvCfgTaxaModo", taxa.modo || "percentual")}</label>
              <label>Valor <input type="text" id="cpdvCfgTaxaValor" inputmode="decimal" value="${String(taxa.valor ?? 10).replace(".", ",")}" /></label>
            </fieldset>
            <fieldset class="cpdv-cfg-encargo">
              <legend>Pagamento do cantor</legend>
              <label><input type="checkbox" id="cpdvCfgCantorAtivo" ${cantor.ativo ? "checked" : ""} /> Ativo no PDV</label>
              <label>Modo ${selModo("cpdvCfgCantorModo", cantor.modo || "percentual")}</label>
              <label>Valor <input type="text" id="cpdvCfgCantorValor" inputmode="decimal" value="${String(cantor.valor ?? 0).replace(".", ",")}" /></label>
            </fieldset>
          </div>
          <button type="button" class="btn primary" id="cpdvCfgSaveEnc">Salvar taxas e cantor</button>
          ` : `<ul class="subtle-text">
            <li>Taxa de serviço: ${taxa.ativa ? `${cpdvRotuloEncargoCfg(taxa)} · incluída por padrão no pagamento` : "desligada"}</li>
            <li>Pagamento cantor: ${cantor.ativo ? `${cpdvRotuloEncargoCfg(cantor)} · incluído por padrão no pagamento` : "desligado"}</li>
          </ul>`}
        </div>
        <div class="cpdv-cards">
          <div class="cpdv-card"><span>Venda fiscal</span><strong>${meta.modulo_venda_fiscal ? "Ativo" : "Indisponível"}</strong></div>
          <div class="cpdv-card"><span>Comandas / mesa</span><strong>${meta.modulo_comandas ? "Ativo" : "Migrar backend"}</strong></div>
          <div class="cpdv-card"><span>Produtos carregados</span><strong id="cpdvCfgProdCount">—</strong></div>
        </div>
        <div class="cpdv-actions" style="margin:1rem 0">
          <button type="button" class="btn primary" data-cpdv-goto="comercialPdv">Abrir PDV / Caixa</button>
          <button type="button" class="btn primary" data-cpdv-goto="comercialMesas">Mesas e Comandas</button>
          <button type="button" class="btn neutral" data-cpdv-goto="comercialHistorico">Histórico de vendas</button>
          <button type="button" class="btn neutral" data-cpdv-goto="fiscalPainelModulo07">Painel fiscal (M7)</button>
          <button type="button" class="btn neutral" data-cpdv-goto="reservaMesa">Cadastro de mesas (Reservas)</button>
        </div>
        <div class="table-card cpdv-form-body">
          <h3>Operação</h3>
          <ul class="subtle-text">
            <li>Caixa: selecione unidade → produtos com estoque → Pagar (baixa FIFO + venda).</li>
            <li>Mesa: mesma unidade → toque na mesa → lançar itens → fechar conta.</li>
            <li>Preço: ficha técnica ou cadastro do produto; informe na mesa se vier zerado.</li>
            <li>Caixa aberto/fechado, TEF e KDS persistido: próxima fase (ver docs/pdv-operacional.md).</li>
          </ul>
        </div>`;
      const sel = root.querySelector("#cpdvCfgUnidade");
      if (sel && cpdvState.unidadeId) sel.value = String(cpdvState.unidadeId);
      sel?.addEventListener("change", () => {
        cpdvState.unidadeId = sel.value ? Number(sel.value) : null;
      });
      root.querySelector("#cpdvCfgSave")?.addEventListener("click", async () => {
        try {
          localStorage.setItem("cpdv_unidade_id", String(cpdvState.unidadeId || ""));
          toast("Unidade PDV salva neste navegador.", "success");
        } catch {
          toast("Unidade atualizada na sessão.", "info");
        }
        await cpdvLoadProdutosApi();
        const el = root.querySelector("#cpdvCfgProdCount");
        if (el) el.textContent = String(cpdvState.produtosApi.length);
      });
      root.querySelector("#cpdvCfgSaveSeg")?.addEventListener("click", async () => {
        const btn = root.querySelector("#cpdvCfgSaveSeg");
        if (btn) {
          btn.disabled = true;
          btn.textContent = "Salvando…";
        }
        try {
          const cfg = await cpdvFetch("/pdv/config", {
            method: "PUT",
            body: JSON.stringify({
              exigir_nsu_cartao: !!root.querySelector("#cpdvCfgExigirNsu")?.checked,
              exigir_autorizacao_cartao: !!root.querySelector("#cpdvCfgExigirAut")?.checked,
              exigir_bandeira_cartao: !!root.querySelector("#cpdvCfgExigirBandeira")?.checked,
              exigir_identificador_pix: !!root.querySelector("#cpdvCfgExigirPix")?.checked,
              bandeiras_cartao: cpdvState.cfgBandeirasDraft || [],
            }),
          });
          if (cpdvState.apiMeta) {
            cpdvState.apiMeta.seguranca_pagamento = cfg;
            cpdvState.apiMeta.encargos_pdv = cfg.encargos_pdv || cpdvEncargosCfg();
            cpdvState.apiMeta.chaves_pix = cfg.chaves_pix || [];
          }
          cpdvState.cfgBandeirasDraft = (cfg.bandeiras_cartao || []).map((b) => b.nome);
          toast("Regras e bandeiras salvas.", "success");
        } catch (e) {
          toast(e.message || "Não foi possível salvar.", "error");
        } finally {
          if (btn) {
            btn.disabled = false;
            btn.textContent = "Salvar regras e bandeiras";
          }
        }
      });
      root.querySelector("#cpdvCfgAddBandeira")?.addEventListener("click", () => {
        const inp = root.querySelector("#cpdvCfgNovaBandeira");
        const nome = inp?.value?.trim();
        if (!nome) {
          toast("Informe o nome da bandeira.", "warning");
          return;
        }
        const key = nome.toLowerCase();
        if ((cpdvState.cfgBandeirasDraft || []).some((n) => n.toLowerCase() === key)) {
          toast("Esta bandeira já está na lista.", "warning");
          return;
        }
        cpdvState.cfgBandeirasDraft = [...(cpdvState.cfgBandeirasDraft || []), nome];
        if (inp) inp.value = "";
        cpdvPaintCfgBandeirasList(root, podeEditarSeg);
      });
      cpdvPaintCfgPixList(root, podeEditarSeg);
      root.querySelector("#cpdvCfgAddPix")?.addEventListener("click", () => {
        const tipoPessoa = root.querySelector("#cpdvCfgPixPessoa")?.value || "pj";
        const tipoChave = root.querySelector("#cpdvCfgPixTipo")?.value || "cnpj";
        const chave = root.querySelector("#cpdvCfgPixChave")?.value?.trim() || "";
        const beneficiario = root.querySelector("#cpdvCfgPixBenef")?.value?.trim() || "";
        if (!chave || !beneficiario) {
          toast("Informe a chave PIX e o nome do beneficiário.", "warning");
          return;
        }
        const item = {
          apelido: root.querySelector("#cpdvCfgPixApelido")?.value?.trim() || "",
          tipo_pessoa: tipoPessoa,
          tipo_chave: tipoChave,
          chave,
          beneficiario,
          cidade: root.querySelector("#cpdvCfgPixCidade")?.value?.trim() || "BELEM",
          documento: root.querySelector("#cpdvCfgPixDoc")?.value?.trim() || "",
          padrao: !!root.querySelector("#cpdvCfgPixPadrao")?.checked,
        };
        if (item.padrao) {
          cpdvState.cfgPixDraft = (cpdvState.cfgPixDraft || []).map((c) => ({ ...c, padrao: false }));
        }
        cpdvState.cfgPixDraft = [...(cpdvState.cfgPixDraft || []), item];
        ["#cpdvCfgPixApelido", "#cpdvCfgPixChave", "#cpdvCfgPixBenef", "#cpdvCfgPixDoc"].forEach((id) => {
          const el = root.querySelector(id);
          if (el) el.value = "";
        });
        const padrao = root.querySelector("#cpdvCfgPixPadrao");
        if (padrao) padrao.checked = false;
        cpdvPaintCfgPixList(root, podeEditarSeg);
        toast("Chave adicionada na lista. Clique em Salvar chaves PIX.", "info");
      });
      root.querySelector("#cpdvCfgSavePix")?.addEventListener("click", async () => {
        const btn = root.querySelector("#cpdvCfgSavePix");
        if (btn) {
          btn.disabled = true;
          btn.textContent = "Salvando…";
        }
        try {
          const cfg = await cpdvFetch("/pdv/config", {
            method: "PUT",
            body: JSON.stringify({ chaves_pix: cpdvState.cfgPixDraft || [] }),
          });
          if (cpdvState.apiMeta) {
            cpdvState.apiMeta.seguranca_pagamento = cfg;
            cpdvState.apiMeta.chaves_pix = cfg.chaves_pix || [];
            cpdvState.apiMeta.encargos_pdv = cfg.encargos_pdv || cpdvEncargosCfg();
          }
          cpdvState.cfgPixDraft = (cfg.chaves_pix || []).map((c) => ({ ...c }));
          cpdvPaintCfgPixList(root, podeEditarSeg);
          toast("Chaves PIX salvas.", "success");
        } catch (e) {
          toast(e.message || "Não foi possível salvar as chaves PIX.", "error");
        } finally {
          if (btn) {
            btn.disabled = false;
            btn.textContent = "Salvar chaves PIX";
          }
        }
      });
      root.querySelector("#cpdvCfgSaveEnc")?.addEventListener("click", async () => {
        const btn = root.querySelector("#cpdvCfgSaveEnc");
        if (btn) {
          btn.disabled = true;
          btn.textContent = "Salvando…";
        }
        try {
          const cfg = await cpdvFetch("/pdv/config", {
            method: "PUT",
            body: JSON.stringify({
              taxa_servico_ativa: !!root.querySelector("#cpdvCfgTaxaAtiva")?.checked,
              taxa_servico_modo: root.querySelector("#cpdvCfgTaxaModo")?.value || "percentual",
              taxa_servico_valor: cpdvParseMoedaInput(root.querySelector("#cpdvCfgTaxaValor")?.value),
              taxa_servico_padrao_mesa: !!root.querySelector("#cpdvCfgTaxaAtiva")?.checked,
              taxa_servico_padrao_balcao: !!root.querySelector("#cpdvCfgTaxaAtiva")?.checked,
              pagamento_cantor_ativo: !!root.querySelector("#cpdvCfgCantorAtivo")?.checked,
              pagamento_cantor_modo: root.querySelector("#cpdvCfgCantorModo")?.value || "percentual",
              pagamento_cantor_valor: cpdvParseMoedaInput(root.querySelector("#cpdvCfgCantorValor")?.value),
              pagamento_cantor_padrao_mesa: !!root.querySelector("#cpdvCfgCantorAtivo")?.checked,
              pagamento_cantor_padrao_balcao: !!root.querySelector("#cpdvCfgCantorAtivo")?.checked,
            }),
          });
          if (cpdvState.apiMeta) {
            cpdvState.apiMeta.seguranca_pagamento = cfg;
            cpdvState.apiMeta.encargos_pdv = cfg.encargos_pdv || cpdvEncargosCfg();
          }
          toast("Taxas salvas.", "success");
        } catch (e) {
          toast(e.message || "Não foi possível salvar.", "error");
        } finally {
          if (btn) {
            btn.disabled = false;
            btn.textContent = "Salvar taxas e cantor";
          }
        }
      });
      root.querySelectorAll("[data-cpdv-goto]").forEach((btn) => {
        btn.addEventListener("click", () => {
          const sec = btn.dataset.cpdvGoto;
          if (typeof navigateTo === "function") navigateTo(sec);
        });
      });
      try {
        const saved = localStorage.getItem("cpdv_unidade_id");
        if (saved && !cpdvState.unidadeId) cpdvState.unidadeId = Number(saved);
        if (sel && cpdvState.unidadeId) sel.value = String(cpdvState.unidadeId);
        await cpdvLoadProdutosApi();
        const el = root.querySelector("#cpdvCfgProdCount");
        if (el) el.textContent = String(cpdvState.produtosApi.length);
      } catch { /* ignore */ }
    });
  }

  function cpdvRenderFiscal() {
    const root = cpdvRoot("comercialFiscalRoot");
    if (!root) return;
    root.innerHTML = `
      <div class="cpdv-aviso-proto cpdv-aviso-proto--ok">
        Consolidação fiscal, vendas PDV com baixa no estoque, emissão de <strong>NFC-e (Focus)</strong> após configurar token/CSC, e pacote mensal para o contador.
      </div>
      <nav class="cpdv-fiscal-tabs" role="tablist" aria-label="Fiscal comercial">
        <button type="button" class="btn primary" data-cpdv-fiscal-tab="visao" role="tab">Visão geral</button>
        <button type="button" class="btn neutral" data-cpdv-fiscal-tab="m7" role="tab">Consolidação (M7)</button>
        <button type="button" class="btn neutral" data-cpdv-fiscal-tab="pdv" role="tab">Vendas PDV (fiscal)</button>
        <button type="button" class="btn neutral" data-cpdv-fiscal-tab="emissao" role="tab">Emissão (NF)</button>
        <button type="button" class="btn neutral" data-cpdv-fiscal-tab="emp" role="tab">Empresas / CNPJ</button>
      </nav>
      <div id="comercialFiscalPanel" class="cpdv-fiscal-panel"></div>`;
    root.querySelectorAll("[data-cpdv-fiscal-tab]").forEach((btn) => {
      btn.addEventListener("click", () => {
        cpdvMountFiscalTab(root, btn.dataset.cpdvFiscalTab).catch((e) => {
          toast(e?.message || "Não foi possível abrir esta aba fiscal.", "error");
        });
      });
    });
    cpdvMountFiscalTab(root, "visao").catch(() => {});
  }

  async function cpdvRenderFiscalVisao(root) {
    const panel = root.querySelector("#comercialFiscalPanel");
    if (!panel) return;
    panel.innerHTML = `<p class="subtle-text">Carregando resumo fiscal…</p>`;
    const brl = (n) => {
      const v = Number(n);
      return Number.isFinite(v) ? v.toLocaleString("pt-BR", { style: "currency", currency: "BRL" }) : "—";
    };
    let visao = null;
    let painel = null;
    let empresas = [];
    try {
      [visao, painel, empresas] = await Promise.all([
        cpdvFetch("/fiscal/consolidacao/visao-geral").catch(() => null),
        cpdvFetch("/fiscal/painel/vendas").catch(() => null),
        cpdvFetch("/fiscal/empresas").catch(() => []),
      ]);
    } catch {
      /* ignore */
    }
    const cards = visao?.cards || {};
    const pend = visao?.pendencias?.length || 0;
    panel.innerHTML = `
      <div class="cpdv-fiscal-visao">
        <div class="cpdv-fiscal-cards">
          <div class="cpdv-fiscal-card"><span>Saídas / receita (período)</span><strong>${brl(cards.saidas)}</strong></div>
          <div class="cpdv-fiscal-card"><span>Entradas</span><strong>${brl(cards.entradas)}</strong></div>
          <div class="cpdv-fiscal-card"><span>Vendas PDV (painel)</span><strong>${brl(painel?.totais?.receita_liquida)}</strong></div>
          <div class="cpdv-fiscal-card"><span>Empresas cadastradas</span><strong>${Array.isArray(empresas) ? empresas.length : 0}</strong></div>
          <div class="cpdv-fiscal-card"><span>Pendências</span><strong>${pend}</strong></div>
        </div>
        <ul class="cpdv-fiscal-checklist">
          <li>Cardápio e PDV registram venda; estoque baixa conforme revenda ou ficha técnica.</li>
          <li>Use <strong>Consolidação (M7)</strong> para entradas, saídas, créditos e apuração estimada.</li>
          <li>Use <strong>Vendas PDV (fiscal)</strong> para conferir vendas com trava de CNPJ/unidade.</li>
          <li>Cadastre credenciais em <strong>Emissão (NF)</strong> ou Configurações → Emissão NF-e / NFC-e.</li>
          <li>Cadastre CNPJs em <strong>Empresas / CNPJ</strong> antes de operar.</li>
        </ul>
        ${visao?.disclaimer ? `<p class="subtle-text cpdv-fiscal-disclaimer">${visao.disclaimer}</p>` : ""}
        ${pend ? `<p class="cpdv-fiscal-warn">Há ${pend} pendência(s) — abra Consolidação (M7) para detalhes.</p>` : ""}
      </div>`;
  }

  async function cpdvMountFiscalTab(root, tab) {
    root.querySelectorAll("[data-cpdv-fiscal-tab]").forEach((b) => {
      const on = b.dataset.cpdvFiscalTab === tab;
      b.classList.toggle("primary", on);
      b.classList.toggle("neutral", !on);
    });
    if (tab === "visao") {
      await cpdvRenderFiscalVisao(root);
      return;
    }
    const embedId = { m7: "fiscalPainelModulo07Root", pdv: "fiscalVendaPdvRoot", emp: "fiscalEmpresasRoot", emissao: "fiscalEmissaoConfigEmbedRoot" }[tab];
    const load = {
      m7: () => window.loadFiscalPainelModulo07?.(),
      pdv: () => window.loadFiscalVendaPdv?.(),
      emp: () => window.loadFiscalEmpresas?.(),
      emissao: () => window.loadFiscalEmissaoConfig?.({ rootId: "fiscalEmissaoConfigEmbedRoot" }),
    }[tab];
    const panel = root.querySelector("#comercialFiscalPanel");
    if (!panel || !embedId) return;
    panel.innerHTML = `<div id="${embedId}" class="cpdv-fiscal-embed"></div>`;
    if (typeof load === "function") {
      await load();
    } else {
      panel.innerHTML = `<p class="subtle-text">Módulo fiscal não carregado. Recarregue a página.</p>`;
    }
  }

  const CPDV_FOCUS_SECTIONS = new Set(["comercialPdv", "comercialMesas"]);

  function cpdvUpdateTopbarRevealPosition() {
    const btn = document.getElementById("cpdvTopbarRevealBtn");
    const sidebar = document.getElementById("sidebar");
    if (!btn) return;
    const collapsed = sidebar?.classList.contains("is-collapsed");
    btn.style.left = collapsed ? "88px" : "calc(var(--sidebar-width, 240px) + 8px)";
  }

  function cpdvEnsureTopbarRevealBtn() {
    let btn = document.getElementById("cpdvTopbarRevealBtn");
    if (!btn) {
      btn = document.createElement("button");
      btn.id = "cpdvTopbarRevealBtn";
      btn.type = "button";
      btn.className = "cpdv-topbar-reveal";
      btn.textContent = "▼";
      btn.title = "Mostrar barra superior";
      btn.setAttribute("aria-label", "Mostrar barra superior");
      btn.addEventListener("click", () => {
        const expanded = document.body.classList.toggle("cpdv-topbar-expanded");
        btn.textContent = expanded ? "▲" : "▼";
        btn.title = expanded ? "Ocultar barra superior" : "Mostrar barra superior";
        btn.setAttribute("aria-label", btn.title);
        cpdvUpdateTopbarRevealPosition();
      });
      document.body.appendChild(btn);
      const sidebar = document.getElementById("sidebar");
      if (sidebar && !sidebar.dataset.cpdvRevealObs) {
        sidebar.dataset.cpdvRevealObs = "1";
        new MutationObserver(() => cpdvUpdateTopbarRevealPosition()).observe(sidebar, {
          attributes: true,
          attributeFilter: ["class"],
        });
      }
    }
    document.body.classList.remove("cpdv-topbar-expanded");
    btn.textContent = "▼";
    cpdvUpdateTopbarRevealPosition();
  }

  function cpdvRemoveTopbarRevealBtn() {
    document.getElementById("cpdvTopbarRevealBtn")?.remove();
    document.body.classList.remove("cpdv-topbar-expanded");
  }

  window.cpdvSyncTopbarFocus = function cpdvSyncTopbarFocus(section) {
    const on = CPDV_FOCUS_SECTIONS.has(section);
    document.body.classList.toggle("cpdv-focus-mode", on);
    const pdvFullscreen = section === "comercialPdv";
    document.body.classList.toggle("cpdv-pdv-fullscreen", pdvFullscreen);
    if (on && !pdvFullscreen) cpdvEnsureTopbarRevealBtn();
    else cpdvRemoveTopbarRevealBtn();
  };

  async function loadComercialDashboard() { cpdvRenderDashboard(); }
  async function loadComercialPdv() { cpdvRenderPdv(); }
  async function loadComercialMesas() { cpdvRenderMesas(); }
  async function loadComercialPedidos() { cpdvRenderPedidos(); }
  async function loadComercialCozinha() { cpdvRenderCozinha(); }
  async function loadComercialPagamentos() { cpdvRenderPagamentos(); }
  async function loadComercialFechamento() { cpdvRenderFechamento(); }
  async function loadComercialClientes() { cpdvRenderClientes(); }
  async function loadComercialHistorico() { cpdvRenderHistorico(); }
  async function loadComercialRelatorios() { cpdvRenderRelatorios(); }
  async function loadComercialConfiguracoes() { cpdvRenderConfiguracoes(); }
  async function loadComercialFiscal() { cpdvRenderFiscal(); }

  window.loadComercialDashboard = loadComercialDashboard;
  window.loadComercialPdv = loadComercialPdv;
  window.loadComercialMesas = loadComercialMesas;
  window.loadComercialPedidos = loadComercialPedidos;
  window.loadComercialCozinha = loadComercialCozinha;
  window.loadComercialPagamentos = loadComercialPagamentos;
  window.loadComercialFechamento = loadComercialFechamento;
  window.loadComercialClientes = loadComercialClientes;
  window.loadComercialHistorico = loadComercialHistorico;
  window.loadComercialRelatorios = loadComercialRelatorios;
  window.loadComercialConfiguracoes = loadComercialConfiguracoes;
  window.loadComercialFiscal = loadComercialFiscal;

  window.loaders = window.loaders || {};
  Object.assign(window.loaders, {
    loadComercialDashboard,
    loadComercialPdv,
    loadComercialMesas,
    loadComercialPedidos,
    loadComercialCozinha,
    loadComercialPagamentos,
    loadComercialFechamento,
    loadComercialClientes,
    loadComercialHistorico,
    loadComercialRelatorios,
    loadComercialConfiguracoes,
    loadComercialFiscal,
  });

  window.setupComercialPdvModule = function setupComercialPdvModule() {
    if (cpdvModalBound) return;
    cpdvModalBound = true;
    ensureCpdvModal();
    document.getElementById("cpdvModalClose")?.addEventListener("click", closeCpdvModal);
    document.getElementById("cpdvModal")?.addEventListener("click", (ev) => {
      if (ev.target.id === "cpdvModal") closeCpdvModal();
    });
    document.addEventListener("keydown", (ev) => {
      if (ev.key === "Escape") closeCpdvModal();
    });
  };
})();
