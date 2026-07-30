const { getDb } = require("./db");
const { pullCatalogo } = require("./catalog");

async function main() {
  getDb();
  const r = await pullCatalogo();
  console.log("Catálogo atualizado:", r);
}

main().catch((e) => {
  console.error("Falha no pull:", e.message);
  process.exit(1);
});
