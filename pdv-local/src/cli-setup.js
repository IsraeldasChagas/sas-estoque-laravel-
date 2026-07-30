const readline = require("readline");
const { loadConfig, saveConfig } = require("./paths");
const { getDb } = require("./db");

function ask(rl, q, def = "") {
  const suf = def !== "" && def != null ? ` [${def}]` : "";
  return new Promise((resolve) => {
    rl.question(`${q}${suf}: `, (ans) => {
      const v = String(ans || "").trim();
      resolve(v === "" ? def : v);
    });
  });
}

async function main() {
  getDb();
  const cfg = loadConfig();
  const rl = readline.createInterface({ input: process.stdin, output: process.stdout });
  console.log("\n=== Setup PDV Local (1 loja = 1 unidade) ===\n");

  const loja_nome = await ask(rl, "Nome da loja", cfg.loja_nome || "");
  const api_url = await ask(rl, "URL da API SAS", cfg.api_url);
  const unidade_id = await ask(rl, "unidade_id desta loja", cfg.unidade_id || "");
  const unidade_nome = await ask(rl, "Nome da unidade", cfg.unidade_nome || "");
  const usuario_id = await ask(rl, "usuario_id do operador (sync)", cfg.usuario_id || "");
  const token = await ask(rl, "Token Bearer (deixe vazio para manter)", "");
  const porta = await ask(rl, "Porta local", cfg.porta || 8787);
  rl.close();

  const next = saveConfig({
    loja_nome,
    api_url,
    unidade_id: unidade_id ? Number(unidade_id) : null,
    unidade_nome,
    usuario_id: usuario_id ? Number(usuario_id) : null,
    token: token || cfg.token,
    porta: Number(porta) || 8787,
  });

  console.log("\nConfig salva em data/config.json");
  console.log(JSON.stringify({ ...next, token: next.token ? "***" : "" }, null, 2));
  console.log("\nPróximo: npm run pull   (baixa produtos da unidade)");
  console.log("Depois:  npm start\n");
}

main().catch((e) => {
  console.error(e.message);
  process.exit(1);
});
