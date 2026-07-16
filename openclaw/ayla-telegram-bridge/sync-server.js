"use strict";

/**
 * API interna na VPS — sincronização da allowlist OpenClaw.
 * Protegida por Bearer AYLA_VPS_SYNC_TOKEN.
 *
 * Endpoints:
 *   POST /internal/allowlist/adicionar   { telegram_user_id }
 *   POST /internal/allowlist/remover     { telegram_user_id }
 *   POST /internal/allowlist/sincronizar
 *   GET  /health
 */

const http = require("http");
const { spawn } = require("child_process");
const crypto = require("crypto");

const CFG = {
  host: process.env.SYNC_HOST || "127.0.0.1",
  port: parseInt(process.env.SYNC_PORT || "8091", 10),
  token: (process.env.AYLA_VPS_SYNC_TOKEN || "").trim(),
  script: process.env.SYNC_SCRIPT || "/opt/ayla-telegram-bridge/bin/sync-allowlist.sh",
  rateLimitPerMin: parseInt(process.env.SYNC_RATE_LIMIT || "30", 10),
};

const hits = new Map();

function log(msg) {
  console.log(`[${new Date().toISOString()}] ${msg}`);
}

function safeEqual(a, b) {
  const ba = Buffer.from(String(a));
  const bb = Buffer.from(String(b));
  if (ba.length !== bb.length) return false;
  return crypto.timingSafeEqual(ba, bb);
}

function rateLimit(ip) {
  const now = Date.now();
  const window = 60000;
  const row = hits.get(ip) || { count: 0, start: now };
  if (now - row.start > window) {
    row.count = 0;
    row.start = now;
  }
  row.count += 1;
  hits.set(ip, row);
  return row.count <= CFG.rateLimitPerMin;
}

function readBody(req) {
  return new Promise((resolve, reject) => {
    const chunks = [];
    req.on("data", (c) => chunks.push(c));
    req.on("end", () => resolve(Buffer.concat(chunks).toString("utf8")));
    req.on("error", reject);
  });
}

function runScript(action, telegramId) {
  return new Promise((resolve) => {
    const args = [CFG.script, action];
    if (telegramId) args.push(telegramId);
    const child = spawn("bash", args, { env: process.env });
    let out = "";
    let err = "";
    child.stdout.on("data", (d) => (out += d.toString()));
    child.stderr.on("data", (d) => (err += d.toString()));
    child.on("close", (code) => resolve({ code, out, err }));
  });
}

const server = http.createServer(async (req, res) => {
  const ip = req.socket.remoteAddress || "unknown";

  if (req.method === "GET" && req.url === "/health") {
    res.writeHead(200, { "Content-Type": "application/json" });
    res.end(JSON.stringify({ ok: true }));
    return;
  }

  if (!CFG.token) {
    res.writeHead(503, { "Content-Type": "application/json" });
    res.end(JSON.stringify({ success: false, message: "Token não configurado." }));
    return;
  }

  const auth = req.headers.authorization || "";
  const token = auth.startsWith("Bearer ") ? auth.slice(7).trim() : "";
  if (!safeEqual(token, CFG.token)) {
    res.writeHead(401, { "Content-Type": "application/json" });
    res.end(JSON.stringify({ success: false, message: "Não autorizado." }));
    return;
  }

  if (!rateLimit(ip)) {
    res.writeHead(429, { "Content-Type": "application/json" });
    res.end(JSON.stringify({ success: false, message: "Limite de requisições excedido." }));
    return;
  }

  const path = (req.url || "").split("?")[0];
  let body = {};
  try {
    const raw = await readBody(req);
    body = raw ? JSON.parse(raw) : {};
  } catch (_) {
    res.writeHead(400, { "Content-Type": "application/json" });
    res.end(JSON.stringify({ success: false, message: "JSON inválido." }));
    return;
  }

  let action = null;
  if (path === "/internal/allowlist/adicionar" && req.method === "POST") action = "adicionar";
  if (path === "/internal/allowlist/remover" && req.method === "POST") action = "remover";
  if (path === "/internal/allowlist/sincronizar" && req.method === "POST") action = "sincronizar";

  if (!action) {
    res.writeHead(404, { "Content-Type": "application/json" });
    res.end(JSON.stringify({ success: false, message: "Rota não encontrada." }));
    return;
  }

  const tg = String(body.telegram_user_id || "").trim();
  if ((action === "adicionar" || action === "remover") && !/^[0-9]{3,32}$/.test(tg)) {
    res.writeHead(422, { "Content-Type": "application/json" });
    res.end(JSON.stringify({ success: false, message: "telegram_user_id inválido." }));
    return;
  }

  const result = await runScript(action, tg || undefined);
  const ok = result.code === 0;
  if (!ok) log(`sync ${action} falhou: ${result.err || result.out}`);

  res.writeHead(ok ? 200 : 500, { "Content-Type": "application/json" });
  res.end(JSON.stringify({ success: ok, ok, message: ok ? "Allowlist atualizada." : "Falha na sincronização." }));
});

server.listen(CFG.port, CFG.host, () => {
  log(`Ayla allowlist sync API em http://${CFG.host}:${CFG.port}`);
});
