const { getDb } = require("./db");
const { syncPendentes } = require("./sales");

async function main() {
  getDb();
  const r = await syncPendentes();
  console.log("Sync:", r);
}

main().catch((e) => {
  console.error("Falha no sync:", e.message);
  process.exit(1);
});
