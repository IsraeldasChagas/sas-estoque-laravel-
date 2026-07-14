# Ayla API v1 — Somente Leitura

API dedicada para o assistente **Ayla** consultar o SAS-Estoque com segurança.
Esta versão é **exclusivamente de leitura**: não há endpoints de escrita, exclusão,
financeiro, RH, estoque, perdas, compras ou reservas.

## 1. Arquitetura

```
Telegram / OpenClaw
        ↓
   MCP SAS-Estoque
        ↓
 API /api/ayla/v1   ← autenticação + rate limit + auditoria
        ↓
 Services SAS IA (SasIaToolService / SasIaModuleQueryService / AiAssistantService)
        ↓
     Banco de dados
```

A Ayla **nunca** acessa diretamente banco, models ou rotas internas. O MCP chama
apenas esta API. Cada endpoint mapeia internamente para uma ferramenta read-only já
existente do SAS IA — o cliente **não** envia o nome da ferramenta.

Arquivos principais:

- `backend/config/ayla.php` — configuração
- `backend/app/Support/Ayla/AylaSettings.php` — leitura de config e token
- `backend/app/Http/Middleware/CheckAylaToken.php` — auth + rate limit
- `backend/app/Support/Ayla/AylaResponse.php` — envelope padrão
- `backend/app/Services/AylaApiService.php` — orquestra ferramentas SAS IA
- `backend/app/Http/Controllers/Api/AylaController.php` — endpoints
- `backend/routes/ayla_routes.php` — rotas (prefixo `ayla/v1`)
- `backend/app/Models/AylaAuditLog.php` + migration `ayla_audit_logs`

## 2. Autenticação

Header obrigatório:

```
Authorization: Bearer <AYLA_SAS_TOKEN>
```

- O token é validado com `hash_equals`.
- Fonte do token: `.env` (`AYLA_SAS_TOKEN`) ou fallback em `sistema_configuracoes`
  (chave `ayla_sas_token`).
- Integração desativada → **503** (`INTEGRATION_DISABLED`).
- Token ausente/ inválido → **401** (`UNAUTHORIZED`). Tentativas inválidas são
  auditadas **sem** gravar o token.

## 3. Headers

| Header | Obrigatório | Uso |
|---|---|---|
| `Authorization: Bearer TOKEN` | sim | autenticação |
| `X-Usuario-Id` | opcional | aplica perfil, `permissoes_menu` e escopo de unidade do usuário |
| `X-Ayla-Channel` | opcional | apenas auditoria (ex.: `telegram`) |
| `X-Ayla-Sender-Id` | opcional | apenas auditoria (identificador externo) |

`X-Ayla-Channel` e `X-Ayla-Sender-Id` **não concedem permissão**.

Sem `X-Usuario-Id`: apenas consultas, restritas por `AYLA_ALLOWED_UNITS`, sem
qualquer escrita.

## 4. Rate limit

60 requisições por minuto (configurável) por combinação **token + IP**.
Excedido → **429** (`RATE_LIMITED`) com `meta.retry_after` em segundos.

## 5. Envelope de resposta

Sucesso:

```json
{
  "success": true,
  "message": "Mensagem curta e natural",
  "data": {},
  "meta": { "acao": "ayla.status", "timestamp": "2026-07-12T17:00:00-03:00", "read_only": true }
}
```

Erro:

```json
{
  "success": false,
  "message": "Mensagem segura",
  "data": {},
  "meta": { "acao": "ayla.produtos", "code": "VALIDATION_ERROR", "timestamp": "..." }
}
```

Nunca são retornados: stack trace, SQL, senha, token, chave API, caminho interno
ou detalhes do servidor.

## 6. Endpoints (todos GET)

| Método | Rota | Parâmetros |
|---|---|---|
| GET | `/api/ayla/v1/status` | — |
| GET | `/api/ayla/v1/unidades` | `busca`, `limite` |
| GET | `/api/ayla/v1/produtos` | `busca` (obrigatório), `unidade_id`, `limite` |
| GET | `/api/ayla/v1/produtos/abaixo-minimo` | `unidade_id`, `limite` |
| GET | `/api/ayla/v1/estoque` | `unidade_id`, `produto_id`, `busca`, `limite` |
| GET | `/api/ayla/v1/estoque/movimentacoes` | `unidade_id`, `dias`, `limite` |
| GET | `/api/ayla/v1/lotes/vencendo` | `unidade_id`, `dias` (padrão 30), `limite` |
| GET | `/api/ayla/v1/compras` | `unidade_id`, `dias`, `status`, `limite` |
| GET | `/api/ayla/v1/fornecedores` | `busca`, `ativo`, `limite` |
| GET | `/api/ayla/v1/dashboard` | — |
| GET | `/api/ayla/v1/kanban` | `status`, `prioridade`, `responsavel`, `unidade`, `unidade_id`, `setor`, `data`, `vencimento`, `texto`, `limit` |
| GET | `/api/ayla/v1/patrimonio` | `busca`, `patrimonio_id`, `unidade_id`, `unidade`, `categoria`, `status`, `responsavel`, `setor`, `data_inicio`, `data_fim`, `valor_minimo`, `valor_maximo`, `limite` |
| GET | `/api/ayla/v1/patrimonio/resumo` | `unidade_id`, `categoria` |
| GET | `/api/ayla/v1/patrimonio/alertas` | `unidade_id` |
| GET | `/api/ayla/v1/patrimonio/unidade/{id}` | `id` na rota |
| GET | `/api/ayla/v1/patrimonio/{id}` | `id` na rota |
| GET | `/api/ayla/v1/relatorios/unidade/{id}` | `id` na rota |

### Validações

- IDs: inteiros positivos.
- `limite`: 1 a 50.
- `dias`: 1 a 365.
- `busca`: até 120 caracteres.
- `status`: `aberta`, `pendente`, `aprovada`, `concluida`, `cancelada`, `rascunho`.
- unidade precisa existir e estar autorizada.

## 7. Códigos de erro

| code | HTTP | Significado |
|---|---|---|
| `INTEGRATION_DISABLED` | 503 | integração desativada |
| `TOKEN_NOT_CONFIGURED` | 503 | token não configurado no servidor |
| `UNAUTHORIZED` | 401 | token ausente/ inválido |
| `INVALID_USER` | 401 | `X-Usuario-Id` inválido/ inativo |
| `RATE_LIMITED` | 429 | limite de requisições excedido |
| `VALIDATION_ERROR` | 422 | parâmetro inválido |
| `UNIT_NOT_ALLOWED` | 403 | unidade não autorizada |
| `PERMISSION_DENIED` | 403 | sem permissão para o dado |
| `NOT_FOUND` | 404 | recurso inexistente |
| `INTERNAL_ERROR` | 500 | falha genérica (sem detalhes) |

## 8. Exemplos curl

```bash
TOKEN="seu_token_aqui"
BASE="https://api.gruposaborparaense.com.br/api/ayla/v1"

# Status
curl -s "$BASE/status" -H "Authorization: Bearer $TOKEN"

# Produtos abaixo do mínimo em uma unidade
curl -s "$BASE/produtos/abaixo-minimo?unidade_id=1&limite=20" \
  -H "Authorization: Bearer $TOKEN"

# Buscar produto
curl -s "$BASE/produtos?busca=arroz&limite=10" \
  -H "Authorization: Bearer $TOKEN"

# Lotes vencendo em 15 dias, identificando o solicitante
curl -s "$BASE/lotes/vencendo?dias=15" \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Usuario-Id: 42" \
  -H "X-Ayla-Channel: telegram" \
  -H "X-Ayla-Sender-Id: 55991234567"

# Dashboard gerencial
curl -s "$BASE/dashboard" -H "Authorization: Bearer $TOKEN"
```

## 9. Segurança

- Somente métodos GET nesta versão.
- Token validado com `hash_equals`; nunca logado nem retornado.
- Rate limit por token+IP.
- Erros sem detalhes sensíveis.
- Escrita não implementada (nenhuma rota POST/PUT/PATCH/DELETE).

## 10. Auditoria

Tabela `ayla_audit_logs` registra: `user_id`, `ip`, `metodo`, `rota`, `acao`,
`payload`, `resposta_resumo`, `status`, `http_status`, `duracao_ms`, timestamps.

Antes de gravar são removidas as chaves sensíveis (`authorization`, `token`,
`password`, `senha`, `api_key`, `secret`, `cpf`, etc.). Falha na auditoria
**nunca** derruba a requisição.

## 11. Limitações desta versão

- Apenas leitura.
- Sem chat, sem novo agente, sem nova integração OpenAI.
- Sem financeiro/ RH no `dashboard`.
- `produtos` exige `busca` (não há busca por `produto_id` isolado nesta versão).

## 12. Configuração

`.env`:

```env
AYLA_SAS_TOKEN=
AYLA_ENABLED=true
AYLA_RATE_LIMIT=60
AYLA_ALLOWED_UNITS=
AYLA_READ_ONLY=true
```

### Rotacionar o token

1. Gere um token forte (ex.: `openssl rand -hex 32`).
2. Atualize `AYLA_SAS_TOKEN` no `.env` (ou a chave `ayla_sas_token` em
   `sistema_configuracoes`).
3. `php artisan config:clear` (e `config:cache` se usar cache).
4. Atualize o token no MCP/Ayla.

### Unidades permitidas

`AYLA_ALLOWED_UNITS` aceita IDs separados por vírgula (ex.: `1,3,5`). Vazio =
todas as unidades. Aplica-se ao modo sem `X-Usuario-Id`; com usuário, vale o
escopo do próprio usuário.

## 13. Migration e testes

```bash
php artisan migrate --path=database/migrations/2026_07_12_000001_create_ayla_audit_logs_table.php
php artisan test --filter=AylaApiTest
```
