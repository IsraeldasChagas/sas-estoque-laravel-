const { getDb, withTransaction } = require("./db");
const { loadConfig, saveConfig } = require("./paths");
const { sasFetch } = require("./sas-client");

function upsertConfigJson(chave, valor) {
  const db = getDb();
  db.prepare(
    `INSERT INTO config_json (chave, valor) VALUES (?, ?)
     ON CONFLICT(chave) DO UPDATE SET valor = excluded.valor`
  ).run(chave, JSON.stringify(valor));
}

function getConfigJson(chave, fallback = null) {
  const row = getDb().prepare("SELECT valor FROM config_json WHERE chave = ?").get(chave);
  if (!row) return fallback;
  try {
    return JSON.parse(row.valor);
  } catch {
    return fallback;
  }
}

async function pullCatalogo() {
  const cfg = loadConfig();
  if (!cfg.unidade_id) throw new Error("Configure unidade_id antes de puxar o catálogo.");

  const meta = await sasFetch(`/pdv/meta?unidade_id=${cfg.unidade_id}`);
  const produtos = await sasFetch(`/pdv/produtos?unidade_id=${cfg.unidade_id}`);

  withTransaction((db) => {
    db.prepare("DELETE FROM produtos").run();
    const ins = db.prepare(`
      INSERT INTO produtos (
        id, cardapio_produto_id, estoque_produto_id, nome, categoria, preco,
        disponivel, estoque_ok, aviso, fonte, updated_at
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    `);
    const now = new Date().toISOString();
    for (const p of produtos || []) {
      const cardapioId = p.cardapio_produto_id != null ? Number(p.cardapio_produto_id) : Number(p.id);
      const estoqueId = p.estoque_produto_id != null ? Number(p.estoque_produto_id) : Number(p.produto_id || p.id);
      ins.run(
        Number(p.id),
        cardapioId || null,
        estoqueId || null,
        String(p.nome || "Produto"),
        p.categoria ? String(p.categoria) : null,
        Number(p.preco || 0),
        p.disponivel === false ? 0 : 1,
        p.estoqueOk === false || p.estoque_ok === false ? 0 : 1,
        p.aviso ? String(p.aviso) : null,
        p.fonte ? String(p.fonte) : "cardapio",
        now
      );
    }

    upsertConfigJson("meta", meta);
    upsertConfigJson("encargos_pdv", meta.encargos_pdv || {});
    upsertConfigJson("chaves_pix", meta.chaves_pix || meta.seguranca_pagamento?.chaves_pix || []);
    upsertConfigJson("seguranca_pagamento", meta.seguranca_pagamento || {});
    db.prepare(
      `INSERT INTO meta (chave, valor) VALUES ('last_pull_at', ?)
       ON CONFLICT(chave) DO UPDATE SET valor = excluded.valor`
    ).run(now);
  });

  saveConfig({
    unidade_nome: cfg.unidade_nome || `Unidade #${cfg.unidade_id}`,
  });

  return {
    produtos: (produtos || []).length,
    pulled_at: new Date().toISOString(),
    unidade_id: cfg.unidade_id,
  };
}

function listarProdutos(q = "") {
  const termo = String(q || "").trim().toLowerCase();
  let rows;
  if (termo) {
    rows = getDb()
      .prepare(
        `SELECT * FROM produtos
         WHERE disponivel = 1 AND lower(nome) LIKE ?
         ORDER BY nome LIMIT 300`
      )
      .all(`%${termo}%`);
  } else {
    rows = getDb()
      .prepare("SELECT * FROM produtos WHERE disponivel = 1 ORDER BY nome LIMIT 500")
      .all();
  }
  return rows.map((r) => ({
    id: r.id,
    cardapio_produto_id: r.cardapio_produto_id,
    estoque_produto_id: r.estoque_produto_id,
    nome: r.nome,
    categoria: r.categoria,
    preco: r.preco,
    disponivel: !!r.disponivel,
    estoqueOk: !!r.estoque_ok,
    aviso: r.aviso,
    fonte: r.fonte,
  }));
}

module.exports = {
  pullCatalogo,
  listarProdutos,
  getConfigJson,
  upsertConfigJson,
};
