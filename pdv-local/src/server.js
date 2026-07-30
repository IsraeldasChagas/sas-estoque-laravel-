const path = require("path");
const os = require("os");
const express = require("express");
const cors = require("cors");
const { loadConfig, saveConfig } = require("./paths");
const { getDb } = require("./db");
const { pullCatalogo, listarProdutos, getConfigJson } = require("./catalog");
const {
  criarPedido,
  listarPedidos,
  getPedido,
  criarVendaBalcao,
  criarVendaDePedido,
  listarVendas,
  syncPendentes,
} = require("./sales");

function localIps() {
  const nets = os.networkInterfaces();
  const out = [];
  for (const name of Object.keys(nets)) {
    for (const net of nets[name] || []) {
      if (net.family === "IPv4" && !net.internal) out.push(net.address);
    }
  }
  return out;
}

function createApp() {
  getDb();
  const app = express();
  app.use(cors());
  app.use(express.json({ limit: "2mb" }));
  app.use(express.static(path.join(__dirname, "..", "public")));

  app.get("/api/status", (_req, res) => {
    const cfg = loadConfig();
    const pendentes = listarVendas("pendente").length + listarVendas("erro").length;
    const produtos = getDb().prepare("SELECT COUNT(*) AS c FROM produtos").get().c;
    const lastPull = getDb().prepare("SELECT valor FROM meta WHERE chave = 'last_pull_at'").get()?.valor || null;
    res.json({
      online: true,
      loja_nome: cfg.loja_nome || cfg.unidade_nome || "",
      unidade_id: cfg.unidade_id,
      unidade_nome: cfg.unidade_nome,
      usuario_id: cfg.usuario_id,
      produtos,
      vendas_pendentes: pendentes,
      last_pull_at: lastPull,
      ips: localIps(),
      porta: cfg.porta,
    });
  });

  app.get("/api/config", (_req, res) => {
    const cfg = loadConfig();
    res.json({
      ...cfg,
      token: cfg.token ? "***" : "",
      has_token: !!cfg.token,
    });
  });

  app.put("/api/config", (req, res) => {
    try {
      const body = req.body || {};
      const current = loadConfig();
      const next = saveConfig({
        api_url: body.api_url != null ? String(body.api_url).trim() : current.api_url,
        unidade_id: body.unidade_id != null && body.unidade_id !== "" ? Number(body.unidade_id) : current.unidade_id,
        unidade_nome: body.unidade_nome != null ? String(body.unidade_nome) : current.unidade_nome,
        usuario_id: body.usuario_id != null && body.usuario_id !== "" ? Number(body.usuario_id) : current.usuario_id,
        token: body.token && body.token !== "***" ? String(body.token) : current.token,
        loja_nome: body.loja_nome != null ? String(body.loja_nome) : current.loja_nome,
        porta: body.porta ? Number(body.porta) : current.porta,
      });
      res.json({ ok: true, config: { ...next, token: next.token ? "***" : "", has_token: !!next.token } });
    } catch (e) {
      res.status(422).json({ error: e.message });
    }
  });

  app.post("/api/pull", async (_req, res) => {
    try {
      const r = await pullCatalogo();
      res.json(r);
    } catch (e) {
      res.status(422).json({ error: e.message });
    }
  });

  app.get("/api/produtos", (req, res) => {
    res.json(listarProdutos(req.query.q || ""));
  });

  app.get("/api/meta-local", (_req, res) => {
    res.json({
      encargos_pdv: getConfigJson("encargos_pdv", {}),
      chaves_pix: getConfigJson("chaves_pix", []),
      seguranca_pagamento: getConfigJson("seguranca_pagamento", {}),
      meta: getConfigJson("meta", {}),
    });
  });

  app.get("/api/pedidos", (req, res) => {
    res.json(listarPedidos(req.query.status === "todos" ? null : req.query.status || "aberto"));
  });

  app.get("/api/pedidos/:id", (req, res) => {
    const p = getPedido(Number(req.params.id));
    if (!p) return res.status(404).json({ error: "Pedido não encontrado" });
    res.json(p);
  });

  app.post("/api/pedidos", (req, res) => {
    try {
      res.status(201).json(criarPedido(req.body || {}));
    } catch (e) {
      res.status(422).json({ error: e.message });
    }
  });

  app.post("/api/vendas/balcao", (req, res) => {
    try {
      res.status(201).json(criarVendaBalcao(req.body || {}));
    } catch (e) {
      res.status(422).json({ error: e.message });
    }
  });

  app.post("/api/pedidos/:id/pagar", (req, res) => {
    try {
      res.status(201).json(criarVendaDePedido(Number(req.params.id), req.body || {}));
    } catch (e) {
      res.status(422).json({ error: e.message });
    }
  });

  app.get("/api/vendas", (req, res) => {
    res.json(listarVendas(req.query.status || null));
  });

  app.post("/api/sync", async (_req, res) => {
    try {
      res.json(await syncPendentes());
    } catch (e) {
      res.status(422).json({ error: e.message });
    }
  });

  app.get("/", (_req, res) => res.redirect("/caixa"));
  app.get("/caixa", (_req, res) => res.sendFile(path.join(__dirname, "..", "public", "caixa.html")));
  app.get("/atendente", (_req, res) => res.sendFile(path.join(__dirname, "..", "public", "atendente.html")));
  app.get("/setup", (_req, res) => res.sendFile(path.join(__dirname, "..", "public", "setup.html")));
  return app;
}

function start() {
  const cfg = loadConfig();
  const app = createApp();
  const porta = Number(cfg.porta || 8787);
  app.listen(porta, cfg.host || "0.0.0.0", () => {
    const ips = localIps();
    console.log("");
    console.log("========================================");
    console.log("  SAS PDV Local — loja / unidade");
    console.log("========================================");
    console.log(`  Unidade: ${cfg.unidade_id || "(não configurada)"} ${cfg.unidade_nome || ""}`);
    console.log(`  Local:   http://127.0.0.1:${porta}/caixa`);
    for (const ip of ips) {
      console.log(`  Intranet caixa:     http://${ip}:${porta}/caixa`);
      console.log(`  Intranet atendente: http://${ip}:${porta}/atendente`);
    }
    console.log(`  Setup:   http://127.0.0.1:${porta}/setup`);
    console.log("========================================");
    console.log("");
  });

  const interval = Number(cfg.sync_intervalo_ms || 30000);
  setInterval(() => {
    syncPendentes().catch(() => {});
  }, interval);
}

if (require.main === module) start();

module.exports = { createApp, start };
