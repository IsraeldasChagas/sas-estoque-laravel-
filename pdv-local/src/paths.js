const fs = require("fs");
const path = require("path");

const ROOT = path.join(__dirname, "..");
const DATA_DIR = path.join(ROOT, "data");
const CONFIG_PATH = path.join(DATA_DIR, "config.json");
const DB_PATH = path.join(DATA_DIR, "pdv-local.sqlite");

const DEFAULTS = {
  porta: 8787,
  host: "0.0.0.0",
  api_url: "https://api.gruposaborparaense.com.br/api",
  unidade_id: null,
  unidade_nome: "",
  usuario_id: null,
  token: "",
  loja_nome: "",
  sync_intervalo_ms: 30000,
};

function ensureDataDir() {
  if (!fs.existsSync(DATA_DIR)) fs.mkdirSync(DATA_DIR, { recursive: true });
}

function loadConfig() {
  ensureDataDir();
  if (!fs.existsSync(CONFIG_PATH)) {
    fs.writeFileSync(CONFIG_PATH, JSON.stringify(DEFAULTS, null, 2), "utf8");
    return { ...DEFAULTS };
  }
  const raw = JSON.parse(fs.readFileSync(CONFIG_PATH, "utf8"));
  return { ...DEFAULTS, ...raw };
}

function saveConfig(partial) {
  const next = { ...loadConfig(), ...partial };
  ensureDataDir();
  fs.writeFileSync(CONFIG_PATH, JSON.stringify(next, null, 2), "utf8");
  return next;
}

module.exports = {
  ROOT,
  DATA_DIR,
  CONFIG_PATH,
  DB_PATH,
  DEFAULTS,
  ensureDataDir,
  loadConfig,
  saveConfig,
};
