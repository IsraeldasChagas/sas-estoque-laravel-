---
name: ayla-sas-estoque
description: Consulta somente leitura no SAS-Estoque via API Ayla v1 (Reservas, Kanban, patrimônio, estoque, compras, etc.).
metadata:
  openclaw:
    requires:
      env: ["AYLA_API_URL", "AYLA_SAS_TOKEN"]
    primaryEnv: AYLA_SAS_TOKEN
tools:
  - http
---

# Ayla — API SAS-Estoque (somente leitura)

Assistente **Ayla** do Grupo Sabor Paraense. Use esta skill para consultar o sistema **sem alterar dados**.

## Variáveis de ambiente (VPS / gateway)

```bash
AYLA_API_URL=https://api.gruposaborparaense.com.br
AYLA_SAS_TOKEN=ayla_sas_COLE_O_TOKEN_DO_PAINEL
```

Todas as requisições:

```
Authorization: Bearer {AYLA_SAS_TOKEN}
Content-Type: application/json
```

Opcional (aplica permissões do usuário SAS):

```
X-Usuario-Id: {id do usuário}
```

## Kanban Administrativo — `kanban_consultar`

Use quando o usuário perguntar sobre **tarefas**, **pendências**, **atrasos**, **responsáveis** ou **setores** do kanban.

```http
GET {AYLA_API_URL}/api/ayla/v1/kanban
GET {AYLA_API_URL}/api/ayla/v1/kanban?status=atrasado
GET {AYLA_API_URL}/api/ayla/v1/kanban?status=pendente
GET {AYLA_API_URL}/api/ayla/v1/kanban?responsavel=Thiago
GET {AYLA_API_URL}/api/ayla/v1/kanban?unidade=Centro
GET {AYLA_API_URL}/api/ayla/v1/kanban?prioridade=alta
GET {AYLA_API_URL}/api/ayla/v1/kanban?setor=RH
GET {AYLA_API_URL}/api/ayla/v1/kanban?vencimento=hoje
```

### Filtros disponíveis

| Parâmetro | Exemplos |
|---|---|
| `status` | `pendente`, `atrasado`, `em_andamento`, `concluida`, `bloqueada` |
| `prioridade` | `alta`, `media`, `baixa` |
| `responsavel` | Nome parcial (ex.: `Thiago`) |
| `unidade` | Nome da unidade (ex.: `Centro`) |
| `unidade_id` | ID numérico |
| `setor` | `RH`, `Financeiro`, `Estoque`, `Compras`, `Cozinha` |
| `vencimento` | `hoje`, `amanha`, `atrasado` |
| `data` | `YYYY-MM-DD` (prazo) |
| `texto` | Busca em título/descrição |
| `limit` | 1–50 (padrão 50) |

### Resposta

O campo `data` inclui:

- `tarefas` — lista de tarefas encontradas
- `total` — total que atende aos filtros
- `resumo` — contagens (pendentes, atrasadas, vencem hoje, prioridade alta, etc.)

Use `message` e `resumo` para responder em português, de forma curta (WhatsApp/Telegram).

## Patrimônio — bens da empresa (somente leitura)

Use estas ferramentas quando o usuário perguntar sobre: **bens, equipamentos, patrimônio, veículos, computadores, geladeiras, máquinas, móveis, número patrimonial, valor patrimonial, manutenção, garantia ou localização de bens**.

```http
GET {AYLA_API_URL}/api/ayla/v1/patrimonio
GET {AYLA_API_URL}/api/ayla/v1/patrimonio?unidade=Doce Norte
GET {AYLA_API_URL}/api/ayla/v1/patrimonio?status=manutencao
GET {AYLA_API_URL}/api/ayla/v1/patrimonio?categoria=Informática
GET {AYLA_API_URL}/api/ayla/v1/patrimonio?responsavel=João
GET {AYLA_API_URL}/api/ayla/v1/patrimonio/resumo
GET {AYLA_API_URL}/api/ayla/v1/patrimonio/resumo?unidade_id=2
GET {AYLA_API_URL}/api/ayla/v1/patrimonio/{id}
GET {AYLA_API_URL}/api/ayla/v1/patrimonio/unidade/{id}
GET {AYLA_API_URL}/api/ayla/v1/patrimonio/alertas
```

### Filtros de `patrimonio`

| Parâmetro | Exemplos |
|---|---|
| `busca` | Nome, código, série, marca, modelo |
| `status` | `ativo`, `manutencao`, `baixado`, `vendido`, `quebrado` |
| `categoria` | `Informática`, `Veículos`, `Refrigeração` |
| `unidade` / `unidade_id` | `Doce Norte` / `2` |
| `responsavel` | Nome parcial |
| `setor` | `cozinha`, etc. |
| `data_inicio` / `data_fim` | `YYYY-MM-DD` (aquisição) |
| `valor_minimo` / `valor_maximo` | Valor de compra |
| `limite` | 1–50 |

### Mapeamento de perguntas → ferramenta

- "Quantos bens temos?" / "valor total do patrimônio" → `patrimonio/resumo`
- "Resumo do patrimônio" / "relatório patrimonial da Unidade 2" → `patrimonio/resumo?unidade_id=2` ou `patrimonio/unidade/2`
- "Quais equipamentos estão em manutenção?" → `patrimonio?status=manutencao`
- "Quais bens estão sem responsável?" / "garantias vencendo" / "manutenção próxima" → `patrimonio/alertas`
- "Quais computadores/geladeiras/veículos?" → `patrimonio?categoria=...` ou `patrimonio?busca=...`
- "Detalhe do bem X" → `patrimonio/{id}`

Responda em português, curto, usando `message` e os campos de `resumo`/`alertas`.

## Reservas de Mesas — ferramentas `reservas_*`

Quando o usuário perguntar sobre **reservas**, **mesas**, **disponibilidade**, **ocupação**, **cliente da reserva**, **horário**, **quantidade de pessoas**, **confirmação**, **cancelamento** ou **histórico de reservas**, use automaticamente as ferramentas abaixo (somente leitura).

```http
GET {AYLA_API_URL}/api/ayla/v1/reservas
GET {AYLA_API_URL}/api/ayla/v1/reservas?data=2026-07-14
GET {AYLA_API_URL}/api/ayla/v1/reservas?status=confirmada
GET {AYLA_API_URL}/api/ayla/v1/reservas?status=pendente
GET {AYLA_API_URL}/api/ayla/v1/reservas?unidade_id=2
GET {AYLA_API_URL}/api/ayla/v1/reservas?cliente=Maria
GET {AYLA_API_URL}/api/ayla/v1/reservas?quantidade_minima=10
GET {AYLA_API_URL}/api/ayla/v1/reservas/resumo
GET {AYLA_API_URL}/api/ayla/v1/reservas/{id}
GET {AYLA_API_URL}/api/ayla/v1/reservas/unidade/{id}
GET {AYLA_API_URL}/api/ayla/v1/reservas/disponibilidade?unidade_id=1&data=2026-07-14&horario=20:00&quantidade_pessoas=6
GET {AYLA_API_URL}/api/ayla/v1/reservas/alertas
```

### Status reais de reserva

`pendente`, `confirmada`, `cancelada`, `cliente_chegou`, `no_show`, `finalizada`

### Quando usar cada ferramenta

- "Quais reservas temos hoje/amanhã/nessa semana?" → `reservas_consultar` com `data` ou `data_inicio`/`data_fim`
- "Resumo das reservas" / "ocupação por unidade" / "horários mais ocupados" → `reservas_resumo`
- "Reservas da Unidade 2" → `reservas_por_unidade` ou `reservas_consultar?unidade_id=2`
- "Mesas livres agora" / "existe mesa para 6 às 20h?" → `reservas_disponibilidade`
- "Próximas do horário" / "ainda não confirmadas" / "atrasadas" → `reservas_alertas`
- "Detalhe da reserva X" → `reservas_detalhar`

### Regras deste módulo

- **Somente leitura** — não criar, editar, cancelar, confirmar, concluir, mover mesa ou excluir reserva.
- Conflito de mesa = mesma mesa + mesma data + **mesmo horário exato** (não há duração no banco).
- Não inventar campos (sem `data_hora` composta nem `hora_fim`).

## Outros endpoints úteis

```http
GET {AYLA_API_URL}/api/ayla/v1/status
GET {AYLA_API_URL}/api/ayla/v1/dashboard
GET {AYLA_API_URL}/api/ayla/v1/produtos?busca=arroz
GET {AYLA_API_URL}/api/ayla/v1/estoque?unidade_id=1
```

## Regras

- **Somente leitura** — não criar, editar, mover ou excluir tarefas do kanban, bens do patrimônio nem reservas nesta fase.
- Não cadastrar, transferir, baixar, alterar responsável/valor ou registrar manutenção de patrimônio.
- Se `success` for `false`, explique o `message` ao usuário.
- Respeite unidades não autorizadas (`UNIT_NOT_ALLOWED`) e registros inexistentes (`NOT_FOUND`).
