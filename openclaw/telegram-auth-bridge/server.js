"use strict";

/**
 * Ayla — Bridge de autenticação do Telegram.
 *
 * Substitui o pairing manual do OpenClaw por autenticação externa via API SAS.
 *
 * Fluxo:
 *   Telegram  →  ESTE bridge (webhook público)
 *             →  POST /api/ayla/v1/acesso/validar   (autoriza pelo usuário SAS)
 *             →  se autorizado: encaminha o update ao webhook local do OpenClaw
 *             →  se recusado:   responde a mensagem de acesso negado e descarta
 *
 * Com isto o OpenClaw nunca pede Pairing Code nem exige `openclaw pairing approve`.
 * Configure o OpenClaw em webhook mode, escutando em 127.0.0.1, com:
 *   channels.telegram.dmPolicy = "open"   e   allowFrom = ["*"]
 * (seguro porque só ESTE bridge — o gatekeeper — alcança o webhook local).
 *
 * Requer Node.js >= 18 (usa fetch/AbortController nativos). Sem dependências.
 */

const http = require("http");
const crypto = require("crypto");

// ---- Configuração (via variáveis de ambiente) --------------------------
const CFG = {
  botToken: (process.env.TELEGRAM_BOT_TOKEN || "").trim(),
  telegramApiRoot: (process.env.TELEGRAM_API_ROOT || "https://api.telegram.org").replace(/\/+$/, ""),

  aylaUrl: (process.env.SAS_AYLA_URL || "https://api.gruposaborparaense.com.br/api/ayla/v1/acesso/validar").trim(),
  sasToken: (process.env.SAS_TOKEN || "").trim(),

  // Webhook do OpenClaw (local). Padrão do OpenClaw: 127.0.0.1:8787/telegram-webhook
  openclawWebhookUrl: (process.env.OPENCLAW_WEBHOOK_URL || "http://127.0.0.1:8787/telegram-webhook").trim(),

  // Segredo que o Telegram envia no header X-Telegram-Bot-Api-Secret-Token.
  // Deve ser igual ao configurado no setWebhook e no channels.telegram.webhookSecret do OpenClaw.
  webhookSecret: (process.env.TELEGRAM_WEBHOOK_SECRET || "").trim(),

  // Porta/caminho públicos deste bridge (normalmente atrás de um reverse proxy).
  port: parseInt(process.env.BRIDGE_PORT || "8080", 10),
  host: (process.env.BRIDGE_HOST || "127.0.0.1").trim(),
  path: (process.env.BRIDGE_PATH || "/telegram-webhook").trim(),

  // Cache de autorização para reduzir chamadas; revogação passa a valer após o TTL.
  authCacheTtlMs: parseInt(process.env.AUTH_CACHE_TTL_MS || "300000", 10),

  timeoutMs: parseInt(process.env.HTTP_TIMEOUT_MS || "12000", 10),

  denyMessage:
    process.env.DENY_MESSAGE ||
    "Olá! Seu acesso ainda não foi autorizado pelo administrador do Grupo Sabor Paraense.\n\nSolicite sua liberação no painel Ayla IA.",
};

function log(msg, extra) {
  const ts = new Date().toISOString();
  if (extra !== undefined) console.log(`[${ts}] ${msg}`, extra);
  else console.log(`[${ts}] ${msg}`);
}
function warn(msg, extra) {
  const ts = new Date().toISOString();
  if (extra !== undefined) console.warn(`[${ts}] ${msg}`, extra);
  else console.warn(`[${ts}] ${msg}`);
}

// ---- Cache simples em memória -----------------------------------------
const authCache = new Map(); // telegramUserId -> { authorized: bool, exp: number }

function cacheGet(id) {
  const hit = authCache.get(id);
  if (!hit) return null;
  if (Date.now() > hit.exp) {
    authCache.delete(id);
    return null;
  }
  return hit.authorized;
}
function cacheSet(id, authorized) {
  authCache.set(id, { authorized, exp: Date.now() + CFG.authCacheTtlMs });
}

// ---- Utilidades HTTP ---------------------------------------------------
function readBody(req, maxBytes = 1_000_000) {
  return new Promise((resolve, reject) => {
    const chunks = [];
    let size = 0;
    req.on("data", (c) => {
      size += c.length;
      if (size > maxBytes) {
        reject(new Error("payload too large"));
        req.destroy();
        return;
      }
      chunks.push(c);
    });
    req.on("end", () => resolve(Buffer.concat(chunks)));
    req.on("error", reject);
  });
}

async function fetchWithTimeout(url, options) {
  const ac = new AbortController();
  const t = setTimeout(() => ac.abort(), CFG.timeoutMs);
  try {
    return await fetch(url, { ...options, signal: ac.signal });
  } finally {
    clearTimeout(t);
  }
}

function safeEqual(a, b) {
  const ba = Buffer.from(String(a));
  const bb = Buffer.from(String(b));
  if (ba.length !== bb.length) return false;
  return crypto.timingSafeEqual(ba, bb);
}

// ---- Extração de dados do update do Telegram ---------------------------
function extrairContexto(update) {
  const m =
    update.message ||
    update.edited_message ||
    update.channel_post ||
    (update.callback_query && update.callback_query.message) ||
    null;

  const from =
    (update.message && update.message.from) ||
    (update.edited_message && update.edited_message.from) ||
    (update.callback_query && update.callback_query.from) ||
    null;

  const chatId = m && m.chat ? m.chat.id : from ? from.id : null;

  if (!from) return null;

  const nome = [from.first_name, from.last_name].filter(Boolean).join(" ").trim();

  return {
    telegramUserId: String(from.id),
    username: from.username ? String(from.username) : null,
    firstName: from.first_name || null,
    lastName: from.last_name || null,
    nome: nome || from.username || String(from.id),
    chatId,
  };
}

// ---- Autorização via API SAS ------------------------------------------
async function autorizar(ctx) {
  const cached = cacheGet(ctx.telegramUserId);
  if (cached !== null) return cached;

  const payload = {
    telegram_user_id: ctx.telegramUserId,
    telegram_username: ctx.username || "",
    telegram_nome: ctx.nome || "",
  };

  let authorized = false;
  try {
    const resp = await fetchWithTimeout(CFG.aylaUrl, {
      method: "POST",
      headers: {
        Authorization: `Bearer ${CFG.sasToken}`,
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify(payload),
    });

    const data = await resp.json().catch(() => ({}));
    authorized = resp.ok && data && data.success === true && data.data && data.data.autorizado === true;
  } catch (e) {
    // Fail-closed: qualquer falha de rede/timeout nega o acesso.
    warn("Falha ao validar acesso no SAS (negando por segurança):", e.message);
    authorized = false;
  }

  cacheSet(ctx.telegramUserId, authorized);
  return authorized;
}

// ---- Telegram: responder acesso negado --------------------------------
async function enviarNegado(chatId) {
  if (!chatId || !CFG.botToken) return;
  try {
    await fetchWithTimeout(`${CFG.telegramApiRoot}/bot${CFG.botToken}/sendMessage`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ chat_id: chatId, text: CFG.denyMessage }),
    });
  } catch (e) {
    warn("Falha ao enviar mensagem de acesso negado:", e.message);
  }
}

// ---- Encaminhar update autorizado ao OpenClaw -------------------------
async function encaminharOpenClaw(rawBody, secretHeader) {
  const headers = { "Content-Type": "application/json" };
  if (secretHeader) headers["X-Telegram-Bot-Api-Secret-Token"] = secretHeader;
  return fetchWithTimeout(CFG.openclawWebhookUrl, {
    method: "POST",
    headers,
    body: rawBody,
  });
}

// ---- Servidor ----------------------------------------------------------
const server = http.createServer(async (req, res) => {
  // Health check
  if (req.method === "GET" && (req.url === "/health" || req.url === "/healthz")) {
    res.writeHead(200, { "Content-Type": "application/json" });
    res.end(JSON.stringify({ ok: true }));
    return;
  }

  if (req.method !== "POST" || req.url.split("?")[0] !== CFG.path) {
    res.writeHead(404, { "Content-Type": "application/json" });
    res.end(JSON.stringify({ ok: false }));
    return;
  }

  // Valida o segredo do webhook (se configurado).
  const secretHeader = req.headers["x-telegram-bot-api-secret-token"];
  if (CFG.webhookSecret && !safeEqual(secretHeader || "", CFG.webhookSecret)) {
    warn("Webhook recebido com secret inválido — ignorado.");
    res.writeHead(401, { "Content-Type": "application/json" });
    res.end(JSON.stringify({ ok: false }));
    return;
  }

  let raw;
  try {
    raw = await readBody(req);
  } catch (e) {
    res.writeHead(413, { "Content-Type": "application/json" });
    res.end(JSON.stringify({ ok: false }));
    return;
  }

  let update;
  try {
    update = JSON.parse(raw.toString("utf8") || "{}");
  } catch (e) {
    // Corpo inválido: responde 200 para o Telegram não reenviar.
    res.writeHead(200, { "Content-Type": "application/json" });
    res.end(JSON.stringify({ ok: true }));
    return;
  }

  // Responde ao Telegram imediatamente; processa de forma assíncrona.
  res.writeHead(200, { "Content-Type": "application/json" });
  res.end(JSON.stringify({ ok: true }));

  const ctx = extrairContexto(update);

  // Sem usuário identificável: encaminha como está (ex.: eventos de serviço).
  if (!ctx) {
    encaminharOpenClaw(raw, secretHeader).catch((e) => warn("Erro ao encaminhar update sem usuário:", e.message));
    return;
  }

  try {
    const ok = await autorizar(ctx);
    if (ok) {
      log(`Telegram autorizado via SAS. user=${ctx.telegramUserId} username=${ctx.username || "-"}`);
      await encaminharOpenClaw(raw, secretHeader);
    } else {
      warn(`Telegram recusado via SAS. user=${ctx.telegramUserId} username=${ctx.username || "-"}`);
      await enviarNegado(ctx.chatId);
    }
  } catch (e) {
    warn("Erro no processamento do update:", e.message);
  }
});

// ---- Validação de configuração e boot ----------------------------------
function validarConfig() {
  const faltando = [];
  if (!CFG.botToken) faltando.push("TELEGRAM_BOT_TOKEN");
  if (!CFG.sasToken) faltando.push("SAS_TOKEN");
  if (!CFG.aylaUrl) faltando.push("SAS_AYLA_URL");
  if (!CFG.openclawWebhookUrl) faltando.push("OPENCLAW_WEBHOOK_URL");
  if (faltando.length) {
    warn("Configuração incompleta. Defina as variáveis: " + faltando.join(", "));
  }
  if (!CFG.webhookSecret) {
    warn("TELEGRAM_WEBHOOK_SECRET não definido — o bridge não validará a origem do webhook.");
  }
}

validarConfig();
server.listen(CFG.port, CFG.host, () => {
  log(`Ayla Telegram auth bridge ouvindo em http://${CFG.host}:${CFG.port}${CFG.path}`);
  log(`Encaminhando autorizados para: ${CFG.openclawWebhookUrl}`);
  log(`Validando acesso em: ${CFG.aylaUrl}`);
});
