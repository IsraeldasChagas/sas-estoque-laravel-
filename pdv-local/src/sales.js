const { v4: uuidv4 } = require("uuid");
const { getDb, withTransaction } = require("./db");
const { loadConfig } = require("./paths");
const { sasFetch } = require("./sas-client");

function nowIso() {
  return new Date().toISOString();
}

function criarPedido({ mesa_label, atendente, observacao, itens }) {
  if (!Array.isArray(itens) || !itens.length) {
    throw new Error("Informe ao menos 1 item.");
  }
  const uuid = uuidv4();
  const created = nowIso();
  let total = 0;
  const linhas = itens.map((i) => {
    const qtd = Math.max(0.001, Number(i.quantidade || 1));
    const preco = Math.max(0, Number(i.preco_unitario || i.preco || 0));
    const lineTotal = Math.round(qtd * preco * 100) / 100;
    total += lineTotal;
    return {
      produto_id: Number(i.produto_id || i.id),
      cardapio_produto_id: i.cardapio_produto_id != null ? Number(i.cardapio_produto_id) : null,
      estoque_produto_id: i.estoque_produto_id != null ? Number(i.estoque_produto_id) : Number(i.produto_id || i.id),
      nome: String(i.nome || "Item"),
      quantidade: qtd,
      preco_unitario: preco,
      total: lineTotal,
    };
  });
  total = Math.round(total * 100) / 100;

  const pedidoId = withTransaction((db) => {
    const info = db
      .prepare(
        `INSERT INTO pedidos (uuid, mesa_label, atendente, status, observacao, total, created_at, updated_at)
         VALUES (?, ?, ?, 'aberto', ?, ?, ?, ?)`
      )
      .run(
        uuid,
        mesa_label ? String(mesa_label).slice(0, 40) : null,
        atendente ? String(atendente).slice(0, 80) : null,
        observacao ? String(observacao).slice(0, 300) : null,
        total,
        created,
        created
      );
    const id = Number(info.lastInsertRowid);
    const ins = db.prepare(
      `INSERT INTO pedido_itens (
        pedido_id, produto_id, cardapio_produto_id, estoque_produto_id, nome, quantidade, preco_unitario, total
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)`
    );
    for (const l of linhas) {
      ins.run(
        id,
        l.produto_id,
        l.cardapio_produto_id,
        l.estoque_produto_id,
        l.nome,
        l.quantidade,
        l.preco_unitario,
        l.total
      );
    }
    return id;
  });

  return getPedido(pedidoId);
}

function getPedido(id) {
  const db = getDb();
  const pedido = db.prepare("SELECT * FROM pedidos WHERE id = ?").get(id);
  if (!pedido) return null;
  const itens = db.prepare("SELECT * FROM pedido_itens WHERE pedido_id = ? ORDER BY id").all(id);
  return { ...pedido, itens };
}

function listarPedidos(status = "aberto") {
  const db = getDb();
  const rows = status
    ? db.prepare("SELECT * FROM pedidos WHERE status = ? ORDER BY id DESC LIMIT 100").all(status)
    : db.prepare("SELECT * FROM pedidos ORDER BY id DESC LIMIT 100").all();
  return rows.map((p) => getPedido(p.id));
}

function criarVendaBalcao({ itens, forma_pagamento, observacao, emitir_nota, pagamento, encargos, pedido_local_id }) {
  if (!Array.isArray(itens) || !itens.length) throw new Error("Carrinho vazio.");
  const cfg = loadConfig();
  if (!cfg.unidade_id) throw new Error("Unidade não configurada.");

  const uuid = uuidv4();
  const created = nowIso();
  const linhas = itens.map((i) => {
    const qtd = Math.max(0.001, Number(i.quantidade || i.qtd || 1));
    const preco = Math.max(0, Number(i.preco_unitario || i.preco || 0));
    return {
      cardapio_produto_id: i.cardapio_produto_id != null ? Number(i.cardapio_produto_id) : undefined,
      produto_id: Number(i.estoque_produto_id || i.produto_id || i.id),
      quantidade: qtd,
      preco_unitario: preco,
      desconto: Number(i.desconto || 0),
      nome: i.nome,
    };
  });
  const total = Math.round(
    linhas.reduce((s, i) => s + i.quantidade * i.preco_unitario - (i.desconto || 0), 0) * 100
  ) / 100;

  const payload = {
    unidade_id: Number(cfg.unidade_id),
    forma_pagamento: forma_pagamento || "Dinheiro",
    pdv_terminal: "PDV-LOCAL",
    idempotency_key: uuid,
    observacao: observacao || undefined,
    emitir_nota: !!emitir_nota,
    ...(pagamento || {}),
    ...(encargos || {}),
    itens: linhas.map((i) => ({
      cardapio_produto_id: i.cardapio_produto_id,
      produto_id: i.produto_id,
      quantidade: i.quantidade,
      preco_unitario: i.preco_unitario,
      desconto: i.desconto || 0,
    })),
  };

  const id = withTransaction((db) => {
    const info = db
      .prepare(
        `INSERT INTO vendas (
          uuid, origem, pedido_local_id, forma_pagamento, total, payload_json,
          emitir_nota, status_sync, created_at
        ) VALUES (?, 'balcao', ?, ?, ?, ?, ?, 'pendente', ?)`
      )
      .run(
        uuid,
        pedido_local_id || null,
        payload.forma_pagamento,
        total,
        JSON.stringify(payload),
        emitir_nota ? 1 : 0,
        created
      );
    if (pedido_local_id) {
      db.prepare(
        `UPDATE pedidos SET status = 'pago', updated_at = ? WHERE id = ? AND status = 'aberto'`
      ).run(created, pedido_local_id);
    }
    return Number(info.lastInsertRowid);
  });

  return getVenda(id);
}

function criarVendaDePedido(pedidoId, extras = {}) {
  const pedido = getPedido(pedidoId);
  if (!pedido) throw new Error("Pedido não encontrado.");
  if (pedido.status !== "aberto") throw new Error("Pedido já foi pago/fechado.");
  return criarVendaBalcao({
    itens: pedido.itens.map((i) => ({
      produto_id: i.estoque_produto_id || i.produto_id,
      cardapio_produto_id: i.cardapio_produto_id,
      estoque_produto_id: i.estoque_produto_id,
      nome: i.nome,
      quantidade: i.quantidade,
      preco_unitario: i.preco_unitario,
    })),
    forma_pagamento: extras.forma_pagamento || "Dinheiro",
    observacao: extras.observacao || `Pedido local #${pedido.id}${pedido.mesa_label ? ` · ${pedido.mesa_label}` : ""}`,
    emitir_nota: !!extras.emitir_nota,
    pagamento: extras.pagamento || {},
    encargos: extras.encargos || {},
    pedido_local_id: pedido.id,
  });
}

function getVenda(id) {
  return getDb().prepare("SELECT * FROM vendas WHERE id = ?").get(id);
}

function listarVendas(status = null) {
  const db = getDb();
  if (status) {
    return db.prepare("SELECT * FROM vendas WHERE status_sync = ? ORDER BY id DESC LIMIT 100").all(status);
  }
  return db.prepare("SELECT * FROM vendas ORDER BY id DESC LIMIT 100").all();
}

async function syncPendentes() {
  const cfg = loadConfig();
  if (!cfg.unidade_id || !cfg.usuario_id) {
    throw new Error("Configure unidade_id e usuario_id para sincronizar.");
  }
  const db = getDb();
  const pendentes = db
    .prepare(
      `SELECT * FROM vendas
       WHERE status_sync IN ('pendente', 'erro')
       ORDER BY id ASC LIMIT 30`
    )
    .all();

  let ok = 0;
  let fail = 0;
  const detalhes = [];

  for (const venda of pendentes) {
    const payload = JSON.parse(venda.payload_json);
    db.prepare("UPDATE vendas SET tentativas = tentativas + 1 WHERE id = ?").run(venda.id);
    try {
      const r = await sasFetch("/pdv/vendas/balcao", {
        method: "POST",
        body: JSON.stringify(payload),
      });
      db.prepare(
        `UPDATE vendas
         SET status_sync = 'sincronizada', venda_sas_id = ?, ultimo_erro = NULL, synced_at = ?
         WHERE id = ?`
      ).run(r.venda_id || null, nowIso(), venda.id);
      ok += 1;
      detalhes.push({ id: venda.id, ok: true, venda_sas_id: r.venda_id, emissao: r.emissao || null });
    } catch (e) {
      const msg = String(e.message || e);
      if (/já fechada|idempotenc|replay/i.test(msg)) {
        db.prepare(
          `UPDATE vendas
           SET status_sync = 'sincronizada', ultimo_erro = ?, synced_at = ?
           WHERE id = ?`
        ).run(msg, nowIso(), venda.id);
        ok += 1;
        detalhes.push({ id: venda.id, ok: true, replayed: true });
        continue;
      }
      db.prepare(`UPDATE vendas SET status_sync = 'erro', ultimo_erro = ? WHERE id = ?`).run(msg, venda.id);
      fail += 1;
      detalhes.push({ id: venda.id, ok: false, erro: msg });
      if (e.status === 0 || /fetch|network|ECONN|ENOTFOUND|offline/i.test(msg)) break;
    }
  }

  return { ok, fail, detalhes, pendentes: listarVendas("pendente").length + listarVendas("erro").length };
}

module.exports = {
  criarPedido,
  getPedido,
  listarPedidos,
  criarVendaBalcao,
  criarVendaDePedido,
  getVenda,
  listarVendas,
  syncPendentes,
};
