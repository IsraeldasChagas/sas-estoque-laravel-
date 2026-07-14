// ---------------------------------------------------------------------------
// Ferramentas MCP — Reservas ESCRITA CONTROLADA (Ayla API v1)
// ---------------------------------------------------------------------------
//
// Fluxo obrigatório:
//   1) reservas_preparar_acao  → obtém acao_id + resumo
//   2) Mostrar resumo ao usuário e PEDIR confirmação
//   3) Se o usuário confirmar → reservas_confirmar_acao
//   4) Se negar → reservas_cancelar_acao
//
// NUNCA executar confirmação sem resposta afirmativa do usuário.
// NUNCA usar POST /reservas direto (retorna 403 CONFIRMATION_REQUIRED).
//
// VPS:
//   1. Copie este arquivo para /opt/sas-estoque-mcp/reservas-write-tools.mjs
//   2. Em server.mjs:
//        import { reservasWriteTools } from "./reservas-write-tools.mjs";
//        tools: [ ...reservasTools(cfg), ...reservasWriteTools(cfg) ]
//   3. systemctl restart sas-estoque-mcp
// ---------------------------------------------------------------------------

/**
 * @param {{ apiUrl: string, token: string }} cfg
 */
export function reservasWriteTools(cfg) {
  const base = `${cfg.apiUrl.replace(/\/$/, "")}/api/ayla/v1`;

  async function aylaPost(path, body = {}, headers = {}) {
    const res = await fetch(base + path, {
      method: "POST",
      headers: {
        Authorization: `Bearer ${cfg.token}`,
        "Content-Type": "application/json",
        "X-Ayla-Channel": "mcp",
        ...headers,
      },
      body: JSON.stringify(body),
    });
    const data = await res.json().catch(() => ({}));
    return { httpStatus: res.status, ...data };
  }

  return [
    {
      name: "reservas_preparar_acao",
      description:
        "Prepara criação/edição/status/mesa de reserva SEM executar. Use antes de qualquer escrita. Retorna acao_id e resumo para o usuário confirmar.",
      inputSchema: {
        type: "object",
        properties: {
          acao: {
            type: "string",
            description:
              "criar | atualizar | alterar_mesa | confirmar | registrar_chegada | finalizar | cancelar",
          },
          dados: {
            type: "object",
            description:
              "Campos da ação. criar: unidade_id, data/data_reserva, horario/hora_reserva, quantidade_pessoas/qtd_pessoas, nome_cliente/cliente, telefone opcional, mesa_id opcional. Status: reserva_id. atualizar: reserva_id + campos.",
          },
          usuario_id: { type: "integer", description: "ID do usuário SAS (enviado no header)" },
          telegram_user_id: { type: "string", description: "Telegram ID do solicitante" },
        },
        required: ["acao", "dados"],
      },
      handler: (args) => {
        const headers = {};
        if (args.usuario_id) headers["X-Usuario-Id"] = String(args.usuario_id);
        if (args.telegram_user_id) headers["X-Telegram-User-Id"] = String(args.telegram_user_id);
        const { usuario_id, telegram_user_id, ...body } = args;
        return aylaPost("/reservas/acoes/preparar", body, headers);
      },
    },
    {
      name: "reservas_confirmar_acao",
      description:
        "Executa ação pendente SOMENTE após o usuário confirmar (sim/confirmar/pode fazer). Nunca chame sem confirmação verbal.",
      inputSchema: {
        type: "object",
        properties: {
          acao_id: { type: "integer" },
          usuario_id: { type: "integer" },
          telegram_user_id: { type: "string" },
        },
        required: ["acao_id"],
      },
      handler: (args) => {
        const headers = {};
        if (args.usuario_id) headers["X-Usuario-Id"] = String(args.usuario_id);
        if (args.telegram_user_id) headers["X-Telegram-User-Id"] = String(args.telegram_user_id);
        return aylaPost(`/reservas/acoes/${encodeURIComponent(args.acao_id)}/confirmar`, {}, headers);
      },
    },
    {
      name: "reservas_cancelar_acao",
      description: "Cancela ação pendente quando o usuário desiste. Não altera reservas.",
      inputSchema: {
        type: "object",
        properties: {
          acao_id: { type: "integer" },
          usuario_id: { type: "integer" },
          telegram_user_id: { type: "string" },
        },
        required: ["acao_id"],
      },
      handler: (args) => {
        const headers = {};
        if (args.usuario_id) headers["X-Usuario-Id"] = String(args.usuario_id);
        if (args.telegram_user_id) headers["X-Telegram-User-Id"] = String(args.telegram_user_id);
        return aylaPost(`/reservas/acoes/${encodeURIComponent(args.acao_id)}/cancelar`, {}, headers);
      },
    },
    {
      name: "reservas_criar",
      description:
        "ATENÇÃO: não cria diretamente. Prefira reservas_preparar_acao com acao=criar. Esta ferramenta só prepara.",
      inputSchema: {
        type: "object",
        properties: {
          dados: { type: "object" },
          usuario_id: { type: "integer" },
          telegram_user_id: { type: "string" },
        },
        required: ["dados"],
      },
      handler: (args) => {
        const headers = {};
        if (args.usuario_id) headers["X-Usuario-Id"] = String(args.usuario_id);
        if (args.telegram_user_id) headers["X-Telegram-User-Id"] = String(args.telegram_user_id);
        return aylaPost("/reservas/acoes/preparar", { acao: "criar", dados: args.dados }, headers);
      },
    },
    {
      name: "reservas_atualizar",
      description: "Prepara atualização (nome, telefone, data, horário, pessoas, observação). Exige confirmação depois.",
      inputSchema: {
        type: "object",
        properties: {
          dados: { type: "object", description: "Deve incluir reserva_id" },
          usuario_id: { type: "integer" },
          telegram_user_id: { type: "string" },
        },
        required: ["dados"],
      },
      handler: (args) => {
        const headers = {};
        if (args.usuario_id) headers["X-Usuario-Id"] = String(args.usuario_id);
        if (args.telegram_user_id) headers["X-Telegram-User-Id"] = String(args.telegram_user_id);
        return aylaPost("/reservas/acoes/preparar", { acao: "atualizar", dados: args.dados }, headers);
      },
    },
    {
      name: "reservas_alterar_status",
      description:
        "Prepara mudança de status: confirmar | registrar_chegada | finalizar | cancelar. Exige confirmação.",
      inputSchema: {
        type: "object",
        properties: {
          acao: { type: "string", description: "confirmar | registrar_chegada | finalizar | cancelar" },
          reserva_id: { type: "integer" },
          motivo: { type: "string", description: "Opcional para cancelar" },
          usuario_id: { type: "integer" },
          telegram_user_id: { type: "string" },
        },
        required: ["acao", "reserva_id"],
      },
      handler: (args) => {
        const headers = {};
        if (args.usuario_id) headers["X-Usuario-Id"] = String(args.usuario_id);
        if (args.telegram_user_id) headers["X-Telegram-User-Id"] = String(args.telegram_user_id);
        return aylaPost(
          "/reservas/acoes/preparar",
          { acao: args.acao, dados: { reserva_id: args.reserva_id, motivo: args.motivo } },
          headers
        );
      },
    },
    {
      name: "reservas_alterar_mesa",
      description: "Prepara troca de mesa. Exige confirmação depois.",
      inputSchema: {
        type: "object",
        properties: {
          reserva_id: { type: "integer" },
          mesa_id: { type: "integer" },
          usuario_id: { type: "integer" },
          telegram_user_id: { type: "string" },
        },
        required: ["reserva_id", "mesa_id"],
      },
      handler: (args) => {
        const headers = {};
        if (args.usuario_id) headers["X-Usuario-Id"] = String(args.usuario_id);
        if (args.telegram_user_id) headers["X-Telegram-User-Id"] = String(args.telegram_user_id);
        return aylaPost(
          "/reservas/acoes/preparar",
          { acao: "alterar_mesa", dados: { reserva_id: args.reserva_id, mesa_id: args.mesa_id } },
          headers
        );
      },
    },
  ];
}
