---
name: ayla-sas-estoque
description: Consulta somente leitura no SAS-Estoque via API Ayla v1 (Kanban, estoque, compras, etc.).
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

## Outros endpoints úteis

```http
GET {AYLA_API_URL}/api/ayla/v1/status
GET {AYLA_API_URL}/api/ayla/v1/dashboard
GET {AYLA_API_URL}/api/ayla/v1/produtos?busca=arroz
GET {AYLA_API_URL}/api/ayla/v1/estoque?unidade_id=1
```

## Regras

- **Somente leitura** — não criar, editar, mover ou excluir tarefas do kanban nesta fase.
- Se `success` for `false`, explique o `message` ao usuário.
- Respeite unidades não autorizadas (`UNIT_NOT_ALLOWED`).
