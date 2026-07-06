---
name: sas-estoque
description: Consulta e ações controladas no SAS-Estoque (Grupo Sabor Paraense) via API segura.
metadata:
  openclaw:
    requires:
      env: ["SAS_ESTOQUE_API_URL", "SAS_ESTOQUE_TOKEN"]
    primaryEnv: SAS_ESTOQUE_TOKEN
tools:
  - http
---

# SAS-Estoque — skill OpenClaw

Assistente do **Grupo Sabor Paraense** para consultar estoque e executar ações permitidas no sistema SAS-Estoque.

## Variáveis de ambiente (configurar na VPS)

No `openclaw.json` ou `.env` do gateway:

```bash
SAS_ESTOQUE_API_URL=https://api.gruposaborparaense.com.br/api/ia
SAS_ESTOQUE_TOKEN=oc_sas_COLE_O_TOKEN_GERADO_NO_PAINEL
```

O token é gerado em: **SAS-Estoque → Configurações → Integrações → OpenClaw**.

Todas as requisições usam:

```
Authorization: Bearer {SAS_ESTOQUE_TOKEN}
Content-Type: application/json
```

## Consultas (resposta direta)

Responda em português, de forma curta, ideal para WhatsApp. Use o campo `message` da API como base.

### Estoque abaixo do mínimo

```http
GET {SAS_ESTOQUE_API_URL}/estoque-baixo?unidade_id={id opcional}
```

### Produtos/lotes vencendo

```http
GET {SAS_ESTOQUE_API_URL}/produtos-vencendo?dias=7&unidade_id={id opcional}
```

### Buscar produto

```http
GET {SAS_ESTOQUE_API_URL}/produto?nome={texto}&unidade_id={id opcional}
GET {SAS_ESTOQUE_API_URL}/produto?id={id}
```

### Relatório da unidade

```http
GET {SAS_ESTOQUE_API_URL}/relatorio-unidade/{unidade_id}
```

## Ações (sempre em 2 etapas)

Ações que alteram dados **exigem confirmação do usuário** no WhatsApp.

1. Primeira chamada **sem** `confirmacao` → API retorna preview e pede confirmação.
2. Segunda chamada **com** `"confirmacao": true` → executa.

### Lançar perda de estoque

```http
POST {SAS_ESTOQUE_API_URL}/lancar-perda
{
  "produto_id": 10,
  "unidade_id": 1,
  "qtd": 2,
  "observacao": "opcional"
}
```

Para confirmar, reenvie o mesmo JSON com `"confirmacao": true`.

### Cadastrar lista de compra

```http
POST {SAS_ESTOQUE_API_URL}/cadastrar-compra
{
  "nome": "Compra semanal",
  "unidade_id": 1,
  "itens": [
    { "produto_id": 5, "quantidade_planejada": 10 }
  ]
}
```

Para confirmar: `"confirmacao": true`.

## Regras importantes

- **Nunca** tentar excluir produto — bloqueado.
- **Não** consultar financeiro, boletos ou fechamento — bloqueado nesta fase.
- Se `success` for `false`, explique o `message` ao usuário.
- Para perdas e compras, **sempre** mostre o preview e peça "Confirma?" antes de enviar com `confirmacao: true`.

## Formato de resposta da API

```json
{
  "success": true,
  "message": "texto resumido para WhatsApp",
  "data": {}
}
```

Use `message` como resposta principal ao usuário.
