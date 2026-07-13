# Remoção das IAs Legadas — Relatório Final

Data: 2026-07-13
Status: **Concluído com sucesso. A Ayla permaneceu 100% intacta.**

## 1. IAs antigas encontradas

1. **Assistente IA (ChatGPT / OpenAI)** — chat direto com a OpenAI (`/ia/chat`, `/ia/config`, `/ia/status`).
2. **SAS IA (chat inteligente + widget flutuante)** — `SasIaController` / `SasIaChatService` / `sas-ia.js`.
3. **Agentes de IA** — `AiAgentController` / `ai-agents.js`.

> **OpenClaw** foi tratado como integração **ativa a preservar** (regra 6 do pedido), **não** como IA legada.

## 2. Arquivos removidos (movidos antes para `backup/ia-legada/`)

### Backend
- `routes/ia_routes.php`
- `routes/sas_ia_routes.php`
- `routes/ai_agent_routes.php`
- `app/Http/Controllers/SasIaController.php`
- `app/Http/Controllers/AiAgentController.php`
- `app/Services/OpenAiService.php`
- `app/Services/SasIaChatService.php`
- `app/Support/AiAgentResolver.php`
- `app/Support/SasIa/SasIaPrefetchService.php`
- `app/Support/SasIa/SasIaResponseSanitizer.php`
- `app/Support/SasIa/SasIaBranding.php`
- `app/Support/SasIa/SasIaDocumentTextExtractor.php`
- `app/Models/AiAgent.php`
- `app/Models/AiConversation.php`
- `app/Models/AiMessage.php`
- `app/Models/AiToolLog.php`

### Frontend
- `ia-assistente.js`
- `sas-ia.js`
- `ai-agents.js`
- `style-ia.css`

## 3. Arquivos alterados

- `backend/routes/api.php` — removidos 3 `require` (ia_routes, sas_ia_routes, ai_agent_routes).
- `frontend/index.html` — removidos: submenu "IA" (`iaMenu`), divisor + item "Agentes" duplicado em Configurações, 6 seções legadas (`sasIa*`, `iaAgentes`, `iaAssistente`, `iaConfiguracoes`), widget flutuante `#sasIaFloatRoot`, 6 checkboxes de permissão legadas, 3 `<script>` legados e o `<link>` de `style-ia.css`.
- `frontend/app.js` — removidos: ids legados de `ALL_NAV_SECTION_IDS` e de `PERMISSOES.ADMIN`, blocos de concessão (grants) de SAS IA / IA legado, bloco de visibilidade `iaNavSubmenu`, referência a `iaAgentes` na visibilidade de Configurações, toggle `.open` do `iaMenu`, dispatch de loaders legados, e chamadas soltas `sasIaFloatSyncPerm` / `setupIaAgentesModule`.

## 4. Menus removidos
- Submenu lateral **"IA"** (SAS IA, Manuais IA, Config. SAS IA, Agentes, Assistente legado, Config. legado).
- Item **"Agentes"** duplicado dentro do submenu **Configurações**.

## 5. Rotas removidas
- `GET /api/ia/status`, `GET|POST /api/ia/config`, `POST /api/ia/chat`
- `GET|POST|DELETE /api/sas-ia*` (index, chat, config, upload-foto, upload-documento, conversas, documentos)
- `GET|POST|PUT|DELETE /api/ai-agents*`

Confirmado via `php artisan route:list`: nenhuma dessas rotas existe mais.

## 6. Services preservados por dependência da Ayla (Categoria B)
- `app/Services/SasIaToolService.php`
- `app/Services/SasIaModuleQueryService.php`
- `app/Services/SasIaDocumentService.php`
- `app/Services/AiAssistantService.php` (compartilhado Ayla + OpenClaw)
- `app/Support/SasIa/SasIaContext.php`
- `app/Support/SasIa/SasIaToolRegistry.php`
- `app/Support/SasIa/SasIaReservaQuery.php`
- `app/Models/AiDocument.php`
- `app/Support/Financeiro/FinanceiroGerencialCalculo.php`

> Conforme FASE 7, esses componentes **não foram renomeados nem refatorados**; permanecem como **dependência interna da Ayla**.

## 7. Componentes preservados (OpenClaw / Ayla — Categoria D)
- OpenClaw: `routes/ai_assistant_routes.php`, `routes/openclaw_config_routes.php`, `Api\AiAssistantController`, `OpenClawConfigController`, `OpenClaw\OpenClawSettings`, `CheckOpenClawToken`, `AiAssistantLog`, `config/openclaw.php`, `openclaw-integracao.js`, seção/menu `openClawIntegracao`.
- Ayla: `routes/ayla_routes.php`, `routes/ayla_admin_routes.php`, `Api\AylaController`, `AylaUsuarioController`, `AylaApiService`, `AylaAccessService`, `Ayla\AylaSettings`, `Ayla\AylaResponse`, `CheckAylaToken`, `AylaAuditLog`, `AylaUsuarioAutorizado`, `config/ayla.php`, `ayla-ia.js`, menu/seções `ayla*`, variáveis `AYLA_*`, integração Telegram, áudio, MCP.

## 8. Tabelas NÃO apagadas
Nenhum `DROP` foi executado (FASE 4). Candidatas a remoção futura documentadas em `IA_LEGADA_BANCO_DADOS.md`:
`ai_conversations`, `ai_messages`, `ai_tool_logs`, `ai_agents`, `ai_agent_modules`.
Mantidas: `ai_documents` (dep. Ayla), `ai_assistant_logs` (OpenClaw), `ayla_*`.

## 9. Variáveis antigas candidatas a remoção futura (não removidas)
- `.env`: `OPENAI_API_KEY`, `OPENAI_MODEL`
- `config/openai.php` e bloco `openai` de `config/services.php`
- `sistema_configuracoes`: `ia_ativo`, `ia_api_key`, `ia_modelo`, `ia_instrucoes`

Mantidas por segurança (fallback seguro; sem impacto na Ayla).

## 10. Testes executados

| Teste | Resultado |
|---|---|
| `php -l` em `api.php` + services preservados (7 arquivos) | ✅ Sem erros |
| `node --check` em `app.js`, `ayla-ia.js`, `openclaw-integracao.js` | ✅ OK |
| `php artisan route:list` (compilação total) | ✅ Exit 0 (nenhum controller ausente) |
| Rotas Ayla presentes (`/api/ayla/v1/*`, `/api/ayla-admin/*`) | ✅ 28 rotas |
| Rotas OpenClaw presentes (`/api/openclaw/*`, `/api/ia/estoque-baixo`) | ✅ Presentes |
| Rotas legadas (`sas-ia`, `ai-agents`, `ia/chat`, `ia/config`, `ia/status`) | ✅ Removidas |
| `php artisan test --filter=AylaApiTest` | ✅ 7 passed (19 assertions) |
| `php artisan test` (suíte completa) | ✅ 9 passed / 1 falha **pré-existente** (`ExampleTest` scaffold do Laravel: `GET /` → 302, não relacionado à limpeza) |
| Busca por referências a arquivos/classes removidos | ✅ Nenhuma (apenas falsos-positivos `energia_routes` ≈ `ia_routes`) |

## 11. Riscos
- Baixo. A Ayla e a OpenClaw não compartilham interface pública com as IAs removidas.
- Único ponto sensível — o grafo de dependências da Ayla usa `SasIa*` services — foi **preservado integralmente**.
- Falha de teste `ExampleTest` é do scaffold padrão do Laravel e **anterior** a esta limpeza.

## 12. Como fazer rollback
Todos os arquivos removidos estão em `backup/ia-legada/` com a mesma estrutura de pastas.

Para reverter:
1. Copiar de volta os arquivos de `backup/ia-legada/backend/...` e `backup/ia-legada/frontend/...` para seus caminhos originais.
2. Restaurar em `backend/routes/api.php` os 3 `require` removidos (`ia_routes.php`, `sas_ia_routes.php`, `ai_agent_routes.php`).
3. Restaurar em `frontend/index.html` os `<script>`/`<link>`, o submenu `iaMenu`, as seções e o widget.
4. Restaurar em `frontend/app.js` os ids/permissões/grants/loaders legados.
5. `php artisan route:list` para validar.

## 13. Confirmações explícitas

> **A Ayla não foi alterada.**

> **As rotas, permissões, integrações, áudio, Telegram, OpenClaw, MCP e módulo administrativo da Ayla permaneceram intactos.**
