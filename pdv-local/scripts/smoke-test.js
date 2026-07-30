const http = require("http");
const { getDb } = require("../src/db");
const { saveConfig } = require("../src/paths");

function req(method, path, body) {
  return new Promise((resolve, reject) => {
    const data = body ? JSON.stringify(body) : null;
    const r = http.request(
      {
        hostname: "127.0.0.1",
        port: 8787,
        path,
        method,
        headers: data
          ? { "Content-Type": "application/json", "Content-Length": Buffer.byteLength(data) }
          : {},
      },
      (res) => {
        let b = "";
        res.on("data", (c) => (b += c));
        res.on("end", () => {
          let parsed = null;
          try {
            parsed = b ? JSON.parse(b) : null;
          } catch {
            parsed = { raw: b };
          }
          resolve({ status: res.statusCode, body: parsed });
        });
      }
    );
    r.on("error", reject);
    if (data) r.write(data);
    r.end();
  });
}

(async () => {
  saveConfig({
    unidade_id: 1,
    unidade_nome: "Loja teste",
    usuario_id: 1,
    loja_nome: "Loja teste",
  });
  const db = getDb();
  db.prepare("DELETE FROM produtos").run();
  db.prepare(
    `INSERT INTO produtos (
      id, cardapio_produto_id, estoque_produto_id, nome, preco, disponivel, estoque_ok, fonte, updated_at
    ) VALUES (10, 10, 10, 'Acai 500ml', 18.5, 1, 1, 'teste', datetime('now'))`
  ).run();

  const st = await req("GET", "/api/status");
  console.log("status", st.status, "produtos=", st.body.produtos, "unidade=", st.body.unidade_id);

  const prods = await req("GET", "/api/produtos");
  console.log("produtos", prods.body.length, prods.body[0] && prods.body[0].nome);

  const ped = await req("POST", "/api/pedidos", {
    atendente: "Ana",
    mesa_label: "Mesa 4",
    itens: [{ produto_id: 10, nome: "Acai 500ml", quantidade: 2, preco_unitario: 18.5 }],
  });
  console.log("pedido", ped.status, "id=", ped.body.id, "total=", ped.body.total);

  const venda = await req("POST", "/api/vendas/balcao", {
    itens: [
      {
        produto_id: 10,
        estoque_produto_id: 10,
        nome: "Acai 500ml",
        quantidade: 1,
        preco_unitario: 18.5,
      },
    ],
    forma_pagamento: "Dinheiro",
  });
  console.log(
    "venda",
    venda.status,
    "id=",
    venda.body.id,
    "sync=",
    venda.body.status_sync,
    "total=",
    venda.body.total
  );

  const pagar = await req("POST", `/api/pedidos/${ped.body.id}/pagar`, {
    forma_pagamento: "PIX",
  });
  console.log(
    "pagar",
    pagar.status,
    "id=",
    pagar.body.id || pagar.body.error,
    "sync=",
    pagar.body.status_sync
  );

  const vendas = await req("GET", "/api/vendas?status=pendente");
  console.log("pendentes", vendas.body.length);
  if (venda.status !== 201 || ped.status !== 201 || pagar.status !== 201) {
    process.exit(1);
  }
  console.log("SMOKE OK");
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
