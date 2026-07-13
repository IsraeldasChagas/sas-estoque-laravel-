# Inventário de IAs Legadas — SAS-Estoque

Data: 2026-07-13
Objetivo: mapear todas as implementações de IA do sistema, classificar cada uma e
definir a decisão (remover / manter / adaptar), **preservando a Ayla 100% intacta**.

## Regra de ouro aplicada

- **Ayla** (API `/api/ayla/v1`, módulo administrativo `Ayla IA`, MCP, Telegram, áudio, OpenClaw) → **NÃO TOCAR**.
- **OpenClaw** (integração via WhatsApp/gateway) → **MANTER** (protegido pelas regras 6 do pedido).
- Vários *services* do motor "SAS IA" são **reutilizados internamente pela Ayla** → **MANTER como dependência**, remover apenas a interface antiga.

## Legenda de decisão

- **A. Remover completamente** — código legado sem uso e sem dependência da Ayla.
- **B. Manter internamente** — reutilizado pela Ayla (motor de ferramentas/consultas).
- **C. Remover apenas interface** — menu/tela/rota pública/frontend antigo.
- **D. Não mexer** — pertence à Ayla ou ao OpenClaw.

---

## 1. IAs antigas identificadas

| IA antiga | Descrição |
|---|---|
| **Assistente IA (ChatGPT / OpenAI)** | Chat direto com a OpenAI. Rotas `/ia/chat`, `/ia/config`, `/ia/status`. Frontend `ia-assistente.js` (telas `iaAssistente`, `iaConfiguracoes`). |
| **SAS IA (chat inteligente)** | Chat com ferramentas/consulta a módulos. Controller `SasIaController`, service `SasIaChatService`, frontend `sas-ia.js` (telas `sasIa`, `sasIaDocumentos`, `sasIaConfiguracoes`) + widget flutuante. |
| **Agentes de IA** | Cadastro de personas/prompts por módulo. Controller `AiAgentController`, frontend `ai-agents.js` (tela `iaAgentes`). |

---

## 2. Inventário detalhado (arquivo · tipo · uso · dependência Ayla · decisão)

### Rotas

| Arquivo | Rotas | Dep. Ayla | Decisão |
|---|---|---|---|
| `backend/routes/ia_routes.php` | `/ia/status`, `/ia/config`, `/ia/chat` (closures OpenAI) | Não | **A — Remover** |
| `backend/routes/sas_ia_routes.php` | `/sas-ia*` → `SasIaController` | Não | **A — Remover** |
| `backend/routes/ai_agent_routes.php` | `/ai-agents*` → `AiAgentController` | Não | **A — Remover** |
| `backend/routes/ai_assistant_routes.php` | `/ia/estoque-baixo` etc. (OpenClaw, `openclaw.token`) | — | **D — OpenClaw, manter** |
| `backend/routes/openclaw_config_routes.php` | `/openclaw/*` | — | **D — OpenClaw, manter** |
| `backend/routes/ayla_routes.php` | `/ayla/v1/*` | Ayla | **D — Ayla, manter** |
| `backend/routes/ayla_admin_routes.php` | `/ayla-admin/*` | Ayla | **D — Ayla, manter** |

### Controllers

| Arquivo | Usa | Dep. Ayla | Decisão |
|---|---|---|---|
| `app/Http/Controllers/SasIaController.php` | SasIaChatService, OpenAiService, SasIaDocumentService, AiAgentResolver, SasIaBranding, SasIaContext, SasIaDocumentTextExtractor, AiConversation | Não | **A — Remover** |
| `app/Http/Controllers/AiAgentController.php` | AiAgent, AiAgentResolver | Não | **A — Remover** |
| `app/Http/Controllers/Api/AiAssistantController.php` | AiAssistantService, OpenClawSettings | — | **D — OpenClaw, manter** |
| `app/Http/Controllers/OpenClawConfigController.php` | OpenClawSettings, AiAssistantLog | — | **D — OpenClaw, manter** |
| `app/Http/Controllers/Api/AylaController.php` | AylaApiService, AylaAccessService... | Ayla | **D — Ayla, manter** |
| `app/Http/Controllers/AylaUsuarioController.php` | AylaAuditLog, AylaUsuarioAutorizado... | Ayla | **D — Ayla, manter** |

### Services

| Arquivo | Usado por | Dep. Ayla | Decisão |
|---|---|---|---|
| `app/Services/OpenAiService.php` | SasIaChatService, SasIaController | Não | **A — Remover** |
| `app/Services/SasIaChatService.php` | SasIaController | Não | **A — Remover** |
| `app/Services/SasIaToolService.php` | **AylaApiService**, SasIaChatService | **Sim** | **B — Manter interno** |
| `app/Services/SasIaModuleQueryService.php` | SasIaToolService | **Sim (transitivo)** | **B — Manter interno** |
| `app/Services/SasIaDocumentService.php` | SasIaModuleQueryService, SasIaController | **Sim (transitivo)** | **B — Manter interno** |
| `app/Services/AiAssistantService.php` | **AylaApiService**, Api\AiAssistantController | **Sim** | **B/D — Manter** |
| `app/Services/AylaApiService.php` | Api\AylaController | Ayla | **D — Ayla, manter** |
| `app/Services/AylaAccessService.php` | Api\AylaController | Ayla | **D — Ayla, manter** |

### Support

| Arquivo | Usado por | Dep. Ayla | Decisão |
|---|---|---|---|
| `app/Support/AiAgentResolver.php` | SasIaChatService, SasIaController, AiAgentController | Não | **A — Remover** |
| `app/Support/SasIa/SasIaPrefetchService.php` | SasIaChatService | Não | **A — Remover** |
| `app/Support/SasIa/SasIaResponseSanitizer.php` | SasIaChatService | Não | **A — Remover** |
| `app/Support/SasIa/SasIaBranding.php` | SasIaChatService, SasIaController | Não | **A — Remover** |
| `app/Support/SasIa/SasIaDocumentTextExtractor.php` | SasIaController | Não | **A — Remover** |
| `app/Support/SasIa/SasIaContext.php` | **AylaApiService, AylaAccessService**, SasIaToolService... | **Sim** | **B — Manter interno** |
| `app/Support/SasIa/SasIaToolRegistry.php` | SasIaContext, OpenAiService | **Sim (via SasIaContext)** | **B — Manter interno** |
| `app/Support/SasIa/SasIaReservaQuery.php` | SasIaModuleQueryService | **Sim (transitivo)** | **B — Manter interno** |
| `app/Support/OpenClaw/OpenClawSettings.php` | AiAssistantService, OpenClaw* | — | **D — OpenClaw, manter** |
| `app/Support/Ayla/*` | Ayla | Ayla | **D — Ayla, manter** |
| `app/Support/Financeiro/FinanceiroGerencialCalculo.php` | SasIaToolService + Financeiro | **Sim (compartilhado)** | **B — Manter** |

### Models

| Arquivo | Usado por | Dep. Ayla | Decisão |
|---|---|---|---|
| `app/Models/AiAgent.php` | AiAgentController, AiAgentResolver, SasIaChatService | Não | **A — Remover** |
| `app/Models/AiConversation.php` | SasIaChatService, SasIaController | Não | **A — Remover** |
| `app/Models/AiMessage.php` | SasIaChatService | Não | **A — Remover** |
| `app/Models/AiToolLog.php` | SasIaChatService | Não | **A — Remover** |
| `app/Models/AiDocument.php` | SasIaDocumentService | **Sim (transitivo)** | **B — Manter** |
| `app/Models/AiAssistantLog.php` | AiAssistantService, OpenClawConfigController | — | **D — OpenClaw, manter** |
| `app/Models/AylaUsuarioAutorizado.php`, `AylaAuditLog.php` | Ayla | Ayla | **D — Ayla, manter** |

### Frontend

| Arquivo / bloco | Tipo | Dep. Ayla | Decisão |
|---|---|---|---|
| `frontend/ia-assistente.js` | JS (`iaAssistente`, `iaConfiguracoes`) | Não | **A — Remover arquivo** |
| `frontend/sas-ia.js` | JS (`sasIa*` + widget flutuante) | Não | **A — Remover arquivo** |
| `frontend/ai-agents.js` | JS (`iaAgentes`) | Não | **A — Remover arquivo** |
| `frontend/style-ia.css` | CSS legado (sas-ia/ia-chat/ai-agents) | Não | **A — Remover arquivo** |
| `index.html` submenu `iaMenu` (285–298) | Menu | Não | **C — Remover interface** |
| `index.html` divisor "IA" + `iaAgentes` em Configurações (308–309) | Menu | Não | **C — Remover interface** |
| `index.html` seções `sasIa*`, `iaAgentes`, `iaAssistente`, `iaConfiguracoes` | Telas | Não | **C — Remover interface** |
| `index.html` widget `#sasIaFloatRoot` | Widget | Não | **C — Remover interface** |
| `index.html` checkboxes de permissão legadas | Modal | Não | **C — Remover interface** |
| `app.js` ids/permissões/grants/loaders legados | JS | Não | **C — Remover interface** |
| `frontend/ayla-ia.js`, seções/menu `ayla*` | Ayla | Ayla | **D — Não mexer** |
| `frontend/openclaw-integracao.js`, seção/menu `openClawIntegracao` | OpenClaw | — | **D — Não mexer** |
| `frontend/style-configuracoes.css` (compartilhado Ayla+OpenClaw) | CSS | Ayla/OpenClaw | **D — Não mexer** |

### Configuração

| Arquivo/chave | Uso | Decisão |
|---|---|---|
| `config/openai.php`, bloco `openai` em `config/services.php` | lido por OpenAiService (removido) e `SasIaContext::limiteDiario()` (fora do caminho Ayla) | **Manter por segurança** — sem impacto na Ayla; candidato a remoção futura (ver `IA_LEGADA_BANCO_DADOS.md`) |
| chaves `.env` `ia_ativo/ia_api_key/...` (em `sistema_configuracoes`) | usadas pelas rotas removidas | **Manter dados**; candidatas a limpeza futura |
| `config/openclaw.php`, `config/ayla.php` | OpenClaw / Ayla | **D — Manter** |

### Tabelas / migrations (ver `IA_LEGADA_BANCO_DADOS.md`)

| Tabela | Migration | Decisão |
|---|---|---|
| `ai_conversations`, `ai_messages`, `ai_tool_logs` | `2026_06_17_000001_create_sas_ia_tables.php` | Candidatas a remoção futura (**não** dropar agora) |
| `ai_documents` | mesma migration acima | **Manter** (dependência Ayla via SasIaDocumentService) |
| `ai_agents`, `ai_agent_modules` | `2026_06_23_000001_create_ai_agents_tables.php` | Candidatas a remoção futura |
| `ai_assistant_logs` | `2026_07_06_000001_...` | **Manter** (OpenClaw) |
| `ayla_audit_logs`, `ayla_usuarios_autorizados` | `2026_07_12_*` | **Manter** (Ayla) |

> **Migrations não são apagadas** e **nenhum DROP é executado** — decisão da FASE 4.
