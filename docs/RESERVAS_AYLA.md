# Ayla × Reservas de Mesas

## O que a Ayla já usa

### Consulta (GET) — funcionando

| Endpoint | Tool / MCP |
|----------|------------|
| `/api/ayla/v1/reservas` | `reservas_consultar` |
| `/api/ayla/v1/reservas/resumo` | `reservas_resumo` |
| `/api/ayla/v1/reservas/{id}` | `reservas_detalhar` |
| `/api/ayla/v1/reservas/unidade/{id}` | `reservas_por_unidade` |
| `/api/ayla/v1/reservas/disponibilidade` | `reservas_disponibilidade` |
| `/api/ayla/v1/reservas/alertas` | `reservas_alertas` |

Service: `AylaReservasService` (ler).  
Docs: `AYLA_RESERVAS_INTEGRACAO.md`.

### Escrita controlada (POST) — preparada no código

| Endpoint | Tool / MCP |
|----------|------------|
| `/reservas/acoes/preparar` | `reservas_preparar_acao` (+ aliases criar/atualizar/status/mesa) |
| `/reservas/acoes/{id}/confirmar` | `reservas_confirmar_acao` |
| `/reservas/acoes/{id}/cancelar` | `reservas_cancelar_acao` |

Ações: `criar`, `atualizar`, `alterar_mesa`, `confirmar`, `registrar_chegada`, `finalizar`, `cancelar`.  
Service: `AylaAcaoPendenteService` + métodos write de `AylaReservasService`.  
Docs: `AYLA_RESERVAS_ESCRITA_CONTROLADA.md`.  
MCP entrega: `docs/mcp/reservas-tools.mjs`, `docs/mcp/reservas-write-tools.mjs`.

### Gate de escrita

1. `ayla_read_only` = false  
2. `X-Usuario-Id` válido  
3. Menu `reservaMesa` ou `historicoReservas` (ADMIN bypass menu)  
4. `pode_executar_acoes` (GERENTE/outros; ADMIN liberado no guard)  
5. Confirmação humana + `acao_id` (expira 10 min)

POST/PUT/PATCH direto em `/reservas` → **403** `CONFIRMATION_REQUIRED`.

### Tools SAS IA (legado/paralelo)

- `consultar_reservas_periodo`
- `consultar_mesas_resumo`

Permissões mapa: `reservaMesa`, `historicoReservas` (+ `sasIa` nos legados).

---

## O que a Ayla **não** faz (ainda)

- Exclusão física
- Criar/editar mesa, capacidade, bloquear mesa via Ayla
- Juntar compostas / adicionar cadeiras / fila de espera
- Duração de slot (parâmetro informativo ignorado no banco)
- Enviar WhatsApp (só o painel abre `wa.me`)
- Alterar `usuario_id` responsável

---

## Skill OpenClaw

`openclaw/skill-ayla/SKILL.md`: consulta direta; escrita sempre preparar → perguntar → confirmar.

---

## Tabela Ayla auxiliar

`ayla_acoes_pendentes` — payload/resumo da intenção até confirmar ou expirar.  
`ayla_audit_logs` — auditoria das chamadas (telefone mascarado).
