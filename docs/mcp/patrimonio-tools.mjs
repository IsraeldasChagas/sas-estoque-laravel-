// ---------------------------------------------------------------------------
// Ferramentas MCP — Patrimônio (Ayla API v1, somente leitura)
// ---------------------------------------------------------------------------
//
// Como usar na VPS (NÃO editável pelo Cursor):
//   1. Copie o bloco `patrimonioTools(...)` para /opt/sas-estoque-mcp/server.mjs
//      (ou importe este arquivo).
//   2. No registro de ferramentas do MCP, faça o spread:
//        tools: [ ...kanbanTools(cfg), ...patrimonioTools(cfg), /* demais */ ]
//   3. Reinicie o serviço: systemctl restart sas-estoque-mcp
//
// Requisitos de ambiente já usados pelo MCP:
//   AYLA_API_URL   = https://api.gruposaborparaense.com.br
//   AYLA_SAS_TOKEN = ayla_sas_...   (token gerado no painel Ayla)
//
// Todas as ferramentas são GET (somente leitura). O MCP nunca deve expor
// endpoints de escrita de patrimônio nesta fase.
// ---------------------------------------------------------------------------

/**
 * @param {{ apiUrl: string, token: string }} cfg
 */
export function patrimonioTools(cfg) {
  const base = `${cfg.apiUrl.replace(/\/$/, "")}/api/ayla/v1`;

  async function aylaGet(path, query = {}) {
    const url = new URL(base + path);
    for (const [k, v] of Object.entries(query)) {
      if (v !== undefined && v !== null && v !== "") url.searchParams.set(k, String(v));
    }
    const res = await fetch(url, {
      method: "GET",
      headers: {
        Authorization: `Bearer ${cfg.token}`,
        "Content-Type": "application/json",
        "X-Ayla-Channel": "mcp",
      },
    });
    const body = await res.json().catch(() => ({}));
    return { httpStatus: res.status, ...body };
  }

  return [
    {
      name: "patrimonio_consultar",
      description:
        "Consulta bens patrimoniais (somente leitura). Use para bens, equipamentos, veículos, computadores, geladeiras, máquinas, móveis, número patrimonial, valor patrimonial ou localização.",
      inputSchema: {
        type: "object",
        properties: {
          busca: { type: "string", description: "Nome, código, série, marca, modelo, fornecedor" },
          patrimonio_id: { type: "integer", description: "ID do bem" },
          unidade_id: { type: "integer", description: "ID da unidade" },
          unidade: { type: "string", description: "Nome da unidade (ex.: Doce Norte)" },
          categoria: { type: "string", description: "Categoria (ex.: Informática, Veículos)" },
          status: { type: "string", description: "ativo, manutencao, baixado, vendido, quebrado" },
          responsavel: { type: "string", description: "Nome do responsável (parcial)" },
          setor: { type: "string", description: "Nome ou ID do setor" },
          data_inicio: { type: "string", description: "Aquisição inicial YYYY-MM-DD" },
          data_fim: { type: "string", description: "Aquisição final YYYY-MM-DD" },
          valor_minimo: { type: "number", description: "Valor de compra mínimo" },
          valor_maximo: { type: "number", description: "Valor de compra máximo" },
          limite: { type: "integer", description: "Máximo de bens (1-50)" },
        },
      },
      handler: (args) => aylaGet("/patrimonio", args),
    },
    {
      name: "patrimonio_resumo",
      description:
        "Resumo patrimonial: totais, valor total, por unidade, por categoria, aquisições recentes, mais antigos, maior valor e alertas.",
      inputSchema: {
        type: "object",
        properties: {
          unidade_id: { type: "integer", description: "Unidade (opcional)" },
          categoria: { type: "string", description: "Categoria (opcional)" },
        },
      },
      handler: (args) => aylaGet("/patrimonio/resumo", args),
    },
    {
      name: "patrimonio_detalhar",
      description: "Detalha um bem patrimonial (inclui manutenções e movimentações).",
      inputSchema: {
        type: "object",
        properties: { id: { type: "integer", description: "ID do bem" } },
        required: ["id"],
      },
      handler: (args) => aylaGet(`/patrimonio/${encodeURIComponent(args.id)}`),
    },
    {
      name: "patrimonio_por_unidade",
      description: "Resumo e lista de bens de uma unidade.",
      inputSchema: {
        type: "object",
        properties: { id: { type: "integer", description: "ID da unidade" } },
        required: ["id"],
      },
      handler: (args) => aylaGet(`/patrimonio/unidade/${encodeURIComponent(args.id)}`),
    },
    {
      name: "patrimonio_alertas",
      description:
        "Alertas patrimoniais: garantia próxima/vencida, manutenção próxima/atrasada, bens sem responsável, sem unidade, sem número patrimonial ou irregulares.",
      inputSchema: {
        type: "object",
        properties: { unidade_id: { type: "integer", description: "Unidade (opcional)" } },
      },
      handler: (args) => aylaGet("/patrimonio/alertas", args),
    },
  ];
}

export default patrimonioTools;
