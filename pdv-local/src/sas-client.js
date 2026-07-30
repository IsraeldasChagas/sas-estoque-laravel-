const { loadConfig } = require("./paths");

async function sasFetch(path, opts = {}) {
  const cfg = loadConfig();
  if (!cfg.api_url) throw new Error("API URL não configurada.");
  const base = String(cfg.api_url).replace(/\/$/, "");
  const url = `${base}${path.startsWith("/") ? path : `/${path}`}`;
  const headers = {
    "Content-Type": "application/json",
    Accept: "application/json",
    ...(opts.headers || {}),
  };
  if (cfg.usuario_id) headers["X-Usuario-Id"] = String(cfg.usuario_id);
  if (cfg.token) headers.Authorization = `Bearer ${cfg.token}`;

  const res = await fetch(url, { ...opts, headers });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) {
    const err = new Error(data.error || data.message || `HTTP ${res.status}`);
    err.status = res.status;
    err.data = data;
    throw err;
  }
  return data;
}

module.exports = { sasFetch };
