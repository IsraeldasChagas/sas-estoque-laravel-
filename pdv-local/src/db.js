const { DatabaseSync } = require("node:sqlite");
const { ensureDataDir, DB_PATH } = require("./paths");

let db;

function getDb() {
  if (db) return db;
  ensureDataDir();
  db = new DatabaseSync(DB_PATH);
  db.exec("PRAGMA journal_mode = WAL;");
  db.exec("PRAGMA foreign_keys = ON;");
  migrate(db);
  return db;
}

function migrate(database) {
  database.exec(`
    CREATE TABLE IF NOT EXISTS meta (
      chave TEXT PRIMARY KEY,
      valor TEXT
    );

    CREATE TABLE IF NOT EXISTS produtos (
      id INTEGER PRIMARY KEY,
      cardapio_produto_id INTEGER,
      estoque_produto_id INTEGER,
      nome TEXT NOT NULL,
      categoria TEXT,
      preco REAL NOT NULL DEFAULT 0,
      disponivel INTEGER NOT NULL DEFAULT 1,
      estoque_ok INTEGER NOT NULL DEFAULT 1,
      aviso TEXT,
      fonte TEXT,
      updated_at TEXT
    );

    CREATE TABLE IF NOT EXISTS config_json (
      chave TEXT PRIMARY KEY,
      valor TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS pedidos (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      mesa_label TEXT,
      atendente TEXT,
      status TEXT NOT NULL DEFAULT 'aberto',
      observacao TEXT,
      total REAL NOT NULL DEFAULT 0,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS pedido_itens (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      pedido_id INTEGER NOT NULL,
      produto_id INTEGER NOT NULL,
      cardapio_produto_id INTEGER,
      estoque_produto_id INTEGER,
      nome TEXT NOT NULL,
      quantidade REAL NOT NULL,
      preco_unitario REAL NOT NULL,
      total REAL NOT NULL,
      FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS vendas (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      origem TEXT NOT NULL DEFAULT 'balcao',
      pedido_local_id INTEGER,
      forma_pagamento TEXT,
      total REAL NOT NULL DEFAULT 0,
      payload_json TEXT NOT NULL,
      emitir_nota INTEGER NOT NULL DEFAULT 0,
      status_sync TEXT NOT NULL DEFAULT 'pendente',
      venda_sas_id INTEGER,
      ultimo_erro TEXT,
      tentativas INTEGER NOT NULL DEFAULT 0,
      created_at TEXT NOT NULL,
      synced_at TEXT
    );

    CREATE INDEX IF NOT EXISTS idx_vendas_sync ON vendas(status_sync, created_at);
    CREATE INDEX IF NOT EXISTS idx_pedidos_status ON pedidos(status, created_at);
  `);
}

function withTransaction(fn) {
  const database = getDb();
  database.exec("BEGIN");
  try {
    const result = fn(database);
    database.exec("COMMIT");
    return result;
  } catch (e) {
    try { database.exec("ROLLBACK"); } catch { /* ignore */ }
    throw e;
  }
}

module.exports = { getDb, withTransaction };
