# Integração Ayla ↔ Reservas de Mesas (Fase 1 — Somente Leitura)

Integração do módulo **Reservas de Mesas** do SAS-Estoque à assistente **Ayla**.
Nesta fase a Ayla **apenas consulta** reservas, mesas, disponibilidade, ocupação
e histórico. Nenhuma ação de escrita (criar, editar, cancelar, confirmar,
concluir, mover mesa, bloquear/liberar ou excluir) foi liberada.

## 1. Arquitetura

```
Telegram / OpenClaw
     ↓
 MCP SAS-Estoque (ferramentas reservas_*)
     ↓
 API /api/ayla/v1/reservas*   ← token + rate limit + auditoria
     ↓
 AylaApiService → SasIaToolService → SasIaModuleQueryService
     ↓
 AylaReservasService (somente leitura)
     ↓
 Banco: reservas_mesas + mesas (+ unidades, usuarios)
```

O cliente **nunca** envia nome de tabela/campo/ferramenta arbitrário: cada endpoint
mapeia internamente para uma ferramenta read-only da allow-list.

## 2. Arquivos

### Criados
- `backend/app/Services/Ayla/AylaReservasService.php` — consultar, detalhar, resumo, por unidade, disponibilidade, alertas.
- `docs/mcp/reservas-tools.mjs` — bloco MCP para a VPS.
- `docs/AYLA_RESERVAS_INTEGRACAO.md` — este documento.

### Alterados
- `backend/app/Http/Controllers/Api/AylaController.php` — endpoints + `parseReservasArgs()` + `parseDisponibilidadeArgs()`.
- `backend/routes/ayla_routes.php` — 6 rotas GET de reservas.
- `backend/app/Support/Ayla/AylaSettings.php` — módulo `reservas` em `modulosLiberados()`.
- `backend/app/Support/SasIa/SasIaToolRegistry.php` — 6 ferramentas `reservas_*`.
- `backend/app/Services/SasIaModuleQueryService.php` — handlers `reservas_*`.
- `backend/app/Services/AylaApiService.php` — allow-list `TOOLS_PERMITIDAS`.
- `backend/app/Models/AylaAuditLog.php` — redige `telefone` / `telefone_cliente` / `phone`.
- `backend/tests/Feature/AylaApiTest.php` — testes de reservas.
- `openclaw/mcp-sas-estoque/tools.json` — ferramentas MCP.
- `openclaw/skill-ayla/SKILL.md` — instruções para a Ayla.
- `docs/AYLA_API_V1.md` — tabela de endpoints.

**Não alterados:** `MesaController`, `ReservaMesaController`, telas
`reservaMesa`/`historicoReservas`, migrations, Kanban, Patrimônio, Estoque,
Telegram, personalidade OpenClaw, allowlist de canal.

## 3. Endpoints (todos GET, prefixo `/api/ayla/v1`)

| Rota | Descrição |
|---|---|
| `/reservas` | Lista de reservas com filtros |
| `/reservas/resumo` | Resumo (hoje, amanhã, status, unidade, horário, mesas) |
| `/reservas/disponibilidade` | Mesas livres/ocupadas para data+horário |
| `/reservas/alertas` | Próximas, pendentes, atrasadas, conflitos, etc. |
| `/reservas/unidade/{id}` | Resumo + reservas do dia da unidade |
| `/reservas/{id}` | Detalhe permitido de uma reserva |

## 4. Filtros de `/reservas`

| Filtro | Tipo | Regra |
|---|---|---|
| `busca` | string | ≤120 chars |
| `reserva_id` | int > 0 | |
| `unidade_id` | int > 0 | precisa estar autorizada |
| `mesa_id` | int > 0 | |
| `status` | enum | ver §5 |
| `data` | date | `YYYY-MM-DD` (não combinar com início/fim) |
| `data_inicio` / `data_fim` | date | período |
| `cliente` | string | LIKE parcial |
| `telefone` | dígitos | ≤20; mascarado em logs |
| `quantidade_minima` / `quantidade_maxima` | int > 0 | |
| `horario_inicio` / `horario_fim` | hora | `HH:MM` ou `HH:MM:SS` |
| `limite` | 1–50 | padrão 50 |

### `/reservas/disponibilidade` (obrigatórios)

- `unidade_id` (ou `unidade` por nome)
- `data` (`YYYY-MM-DD`)
- `horario` (`HH:MM`)
- opcional: `quantidade_pessoas`, `duracao_minutos` (informativo; banco não grava duração)

## 5. Campos e status reais

### `mesas`
`unidade_id`, `numero_mesa`, `nome_mesa`, `capacidade`, `localizacao`,
`pode_juntar`, `pode_separar`, `status` (`livre|reservada|aguardando_cliente|ocupada|bloqueada`),
`observacao`, `ativo`, timestamps.

### `reservas_mesas`
`unidade_id`, `mesa_id`, `usuario_id`, `nome_cliente`, `telefone_cliente`,
`data_reserva`, `hora_reserva`, `qtd_pessoas`,
`status` (`pendente|confirmada|cancelada|cliente_chegou|no_show|finalizada`),
`observacao`, `local`, `ocasiao`, timestamps.

**Não existem no banco:** `data_hora` composta, duração/`hora_fim`,
timestamps dedicados de cancelamento/confirmação (só `updated_at`),
campo `responsavel` (apenas `usuario_id`).

**Conflito:** mesma `mesa_id` + `data_reserva` + `hora_reserva` (exato), com status ativo.

## 6. Permissões

Permissão efetiva = `permissoes_menu` do usuário ∩ módulos da Ayla ∩ unidades permitidas.

- Chaves reais: `reservaMesa`, `historicoReservas` (via `SasIaToolRegistry`).
- Módulo Ayla: `reservas` em `AylaSettings::modulosLiberados()`.
- ADMIN: todas as unidades. GERENTE/demais: escopo via `SasIaContext`.
- Sem `X-Usuario-Id`: leitor de sistema restrito por `AYLA_ALLOWED_UNITS`.
- Sem permissão → **403**. Unidade fora do escopo → **403** (`UNIT_NOT_ALLOWED`).

## 7. Validações

- IDs inteiros positivos; `limite` 1–50; busca ≤120; datas/horários válidos;
  status em allow-list; unidade autorizada; filtros incompatíveis rejeitados
  (`data` + `data_inicio`/`data_fim`).

## 8. Auditoria (`ayla_audit_logs`)

Registra: usuário, IP, método, rota, ação (`ayla.reservas*`), filtros sanitizados,
status HTTP, duração, sucesso/erro.

**Não registra:** token, senha, API key. Telefone → `[REDACTED]` / `[MASKED]` nos filtros.

## 9. Ferramentas MCP / internas

`reservas_consultar`, `reservas_resumo`, `reservas_detalhar`,
`reservas_por_unidade`, `reservas_disponibilidade`, `reservas_alertas`.

## 10. Exemplos curl

```bash
BASE="https://api.gruposaborparaense.com.br/api/ayla/v1"
TOKEN="ayla_sas_..."

curl -s -H "Authorization: Bearer $TOKEN" "$BASE/reservas?data=$(date +%F)"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/reservas/resumo"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/reservas?status=pendente"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/reservas?unidade_id=2"
curl -s -H "Authorization: Bearer $TOKEN" \
  "$BASE/reservas/disponibilidade?unidade_id=1&data=$(date +%F)&horario=20:00&quantidade_pessoas=6"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/reservas/alertas"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/reservas/unidade/1"
```

## 11. Perguntas no Telegram

- “Ayla, quais reservas temos hoje?”
- “Ayla, faça um resumo das reservas.”
- “Ayla, quais mesas estão livres agora?”
- “Ayla, existe mesa para 6 pessoas às 20h?”
- “Ayla, quais reservas são da Unidade 2?”
- “Ayla, quais reservas estão próximas do horário?”
- “Ayla, quais reservas ainda não foram confirmadas?”

## 12. Limitações (Fase 1)

- Somente GET; sem criar/editar/cancelar/confirmar/excluir.
- Sem duração de slot; conflito por horário exato.
- Controllers e telas do módulo Reservas **não** foram alterados.

## 13. Publicar no Napoleon (API)

1. Enviar o backend (código PHP + rotas).
2. Limpar caches: `php artisan route:clear && php artisan config:clear && php artisan cache:clear`.
3. Conferir: `php artisan route:list --path=ayla/v1/reservas`.

## 14. Atualizar MCP na VPS

```javascript
// Em /opt/sas-estoque-mcp/server.mjs
import { reservasTools } from "./reservas-tools.mjs"; // ou cole o export

tools: [
  // ...existentes...
  ...reservasTools(cfg),
]
```

Reiniciar: `systemctl restart sas-estoque-mcp`

Arquivo de entrega: `docs/mcp/reservas-tools.mjs`.

## 15. Atualizar OpenClaw skill

Copiar/atualizar `openclaw/skill-ayla/SKILL.md` no ambiente do agente (seção Reservas).
Não alterar personalidade, Telegram, voz, allowlist, Kanban, Patrimônio ou Estoque além das novas instruções de ferramentas.

## 16. Testar no Telegram

1. Usuário na allowlist, com vínculo SAS e menu `reservaMesa` ou `historicoReservas`.
2. Perguntar: “quais reservas temos hoje?”
3. Confirmar resposta com dados reais e sem tentativa de escrita.
