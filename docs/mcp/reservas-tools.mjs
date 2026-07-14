// ---------------------------------------------------------------------------
// Ferramentas MCP — Reservas de Mesas (Ayla API v1, somente leitura)
// ---------------------------------------------------------------------------
//
// Como usar na VPS (NÃO editável pelo Cursor):
//   1. Copie o bloco `reservasTools(...)` para /opt/sas-estoque-mcp/server.mjs
//      (ou importe este arquivo).
//   2. No registro de ferramentas do MCP, faça o spread:
//        tools: [
//          ...kanbanTools(cfg),
//          ...patrimonioTools(cfg),
//          ...reservasTools(cfg),  // <-- adicionar
//          /* demais */
//        ]
//   3. Reinicie o serviço: systemctl restart sas-estoque-mcp
//
// Requisitos de ambiente já usados pelo MCP:
//   AYLA_API_URL   = https://api.gruposaborparaense.com.br
//   AYLA_SAS_TOKEN = ayla_sas_...   (token gerado no painel Ayla)
//
// Todas as ferramentas são GET (somente leitura). O MCP nunca deve expor
// endpoints de escrita de reservas nesta fase.
// ---------------------------------------------------------------------------

/**
 * @param {{ apiUrl: string, token: string }} cfg
 */
export function reservasTools(cfg) {
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
      name: "reservas_consultar",
      description:
        "Consulta reservas de mesa (somente leitura). Use para reservas de hoje/amanhã/semana, por status, unidade, cliente, telefone, quantidade de pessoas, horário, observações ou histórico.",
      inputSchema: {
        type: "object",
        properties: {
          busca: { type: "string", description: "Cliente, telefone, observação, local ou ocasião" },
          reserva_id: { type: "integer", description: "ID da reserva" },
          unidade_id: { type: "integer", description: "ID da unidade" },
          unidade: { type: "string", description: "Nome da unidade" },
          mesa_id: { type: "integer", description: "ID da mesa" },
          status: {
            type: "string",
            description: "pendente, confirmada, cancelada, cliente_chegou, no_show, finalizada",
          },
          data: { type: "string", description: "Data YYYY-MM-DD" },
          data_inicio: { type: "string", description: "Início do período YYYY-MM-DD" },
          data_fim: { type: "string", description: "Fim do período YYYY-MM-DD" },
          cliente: { type: "string", description: "Nome do cliente (parcial)" },
          telefone: { type: "string", description: "Telefone (somente dígitos)" },
          quantidade_minima: { type: "integer", description: "Quantidade mínima de pessoas" },
          quantidade_maxima: { type: "integer", description: "Quantidade máxima de pessoas" },
          horario_inicio: { type: "string", description: "HH:MM ou HH:MM:SS" },
          horario_fim: { type: "string", description: "HH:MM ou HH:MM:SS" },
          limite: { type: "integer", description: "Máximo de reservas (1-50)" },
        },
      },
      handler: (args) => aylaGet("/reservas", args),
    },
    {
      name: "reservas_resumo",
      description:
        "Resumo de reservas: totais, hoje, amanhã, por status, por unidade, por horário, mesas mais usadas e pessoas esperadas.",
      inputSchema: {
        type: "object",
        properties: {
          unidade_id: { type: "integer", description: "Unidade (opcional)" },
          unidade: { type: "string", description: "Nome da unidade (opcional)" },
        },
      },
      handler: (args) => aylaGet("/reservas/resumo", args),
    },
    {
      name: "reservas_detalhar",
      description: "Detalha uma reserva de mesa.",
      inputSchema: {
        type: "object",
        properties: { id: { type: "integer", description: "ID da reserva" } },
        required: ["id"],
      },
      handler: (args) => aylaGet(`/reservas/${encodeURIComponent(args.id)}`),
    },
    {
      name: "reservas_por_unidade",
      description: "Resumo e lista de reservas do dia de uma unidade.",
      inputSchema: {
        type: "object",
        properties: { id: { type: "integer", description: "ID da unidade" } },
        required: ["id"],
      },
      handler: (args) => aylaGet(`/reservas/unidade/${encodeURIComponent(args.id)}`),
    },
    {
      name: "reservas_disponibilidade",
      description:
        "Mesas disponíveis/ocupadas para unidade, data e horário. Use para 'mesa livre agora', 'existe mesa para N pessoas às Xh'.",
      inputSchema: {
        type: "object",
        properties: {
          unidade_id: { type: "integer", description: "ID da unidade (obrigatório)" },
          data: { type: "string", description: "YYYY-MM-DD (obrigatório)" },
          horario: { type: "string", description: "HH:MM (obrigatório)" },
          quantidade_pessoas: { type: "integer", description: "Capacidade mínima desejada" },
          duracao_minutos: {
            type: "integer",
            description: "Informativo (o sistema não grava duração; conflito é no mesmo horário exato)",
          },
        },
        required: ["unidade_id", "data", "horario"],
      },
      handler: (args) => aylaGet("/reservas/disponibilidade", args),
    },
    {
      name: "reservas_alertas",
      description:
        "Alertas: próximas do horário, sem confirmação, atrasadas, conflitos, sem mesa, acima da capacidade, canceladas recentes.",
      inputSchema: {
        type: "object",
        properties: {
          unidade_id: { type: "integer", description: "Unidade (opcional)" },
          unidade: { type: "string", description: "Nome da unidade (opcional)" },
        },
      },
      handler: (args) => aylaGet("/reservas/alertas", args),
    },
  ];
}
