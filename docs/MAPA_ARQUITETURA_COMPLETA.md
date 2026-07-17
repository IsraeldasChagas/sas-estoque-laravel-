# Mapa de arquitetura completa — Integrações e Ayla IA

Data: 2026-07-16  
Natureza: engenharia reversa estática do repositório.

## 1. Contexto geral

O sistema possui três conjuntos independentes que a interface aproxima visualmente, mas que não compartilham uma única camada técnica:

1. **Integrações genéricas**: hoje centradas no VendaFácil.
2. **OpenClaw legado**: `/api/ia`, `/api/openclaw`, WhatsApp e skill `sas-estoque`.
3. **Ayla IA atual**: `/api/ayla/v1`, painel `/api/ayla-admin`, Telegram, convites e skill Ayla.

## 2. Diagrama de componentes

```text
┌────────────────────────── FRONTEND SAS ──────────────────────────┐
│                                                                 │
│  Integrações UI       OpenClaw UI             Ayla IA UI        │
│  integracoes.js       openclaw-*.js           ayla-ia.js        │
└────────┬───────────────────┬──────────────────────┬──────────────┘
         │                   │                      │
         ▼                   ▼                      ▼
 /api/integracoes/*   /api/openclaw/*       /api/ayla-admin/*
         │             /api/ia/*                    │
         ▼                   │                      ▼
 IntegrationManager         AiAssistant       AylaUsuarioController
         │                   stack             AylaConviteController
         ▼                                          │
 VendaFacilProvider                                DB Ayla
         │
 HttpIntegrationClient
         │
         ▼
 API VendaFácil

Telegram ──webhook──> telegram-auth-bridge (VPS)
                          │
                          ├─ /start TOKEN
                          │      └─> /api/ayla/v1/telegram/vincular
                          │              ├─> DB Ayla
                          │              └─> VPS allowlist sync
                          │
                          └─ mensagem normal
                                 ├─> /api/ayla/v1/acesso/validar
                                 └─> OpenClaw local webhook
                                          │
                                          ▼
                                     OpenClaw runtime
                                          │
                                 Skill HTTP ou MCP externo
                                          │
                                          ▼
                                   /api/ayla/v1/*
                                          │
                                          ▼
                                  Laravel services
                                          │
                                          ▼
                                         DB
```

## 3. Fronteiras e responsabilidades

### Navegador

- renderiza menu;
- envia `X-Usuario-Id`;
- envia Bearer do login, mesmo quando backend não o valida;
- executa ações administrativas.

### Laravel Napoleon

- persiste configuração e usuários;
- autoriza requests;
- oferece API de ferramentas;
- consulta e altera banco;
- registra auditoria;
- coordena bridge/allowlist via HTTP.

### VPS

- recebe webhook Telegram;
- valida convite e acesso no SAS;
- encaminha update ao OpenClaw;
- mantém API/script de allowlist;
- hospeda OpenClaw e MCP externo.

### OpenClaw

- mantém conversa e contexto textual;
- seleciona ferramentas;
- chama HTTP/MCP;
- gera resposta em linguagem natural;
- envia resposta ao Telegram.

### VendaFácil

- responde status da integração;
- opcionalmente responde unidades;
- não participa ainda de fluxos comerciais SAS.

## 4. Inventário de APIs e autenticação

| API | Consumidor | Autenticação real |
|---|---|---|
| `/api/integracoes/*` | Frontend SAS | `X-Usuario-Id` manual |
| `/api/ayla-admin/*` | Frontend Ayla | `X-Usuario-Id` manual |
| `/api/ayla/v1/*` | OpenClaw/MCP/bridge | Bearer `AYLA_SAS_TOKEN` |
| `/api/ayla/v1/telegram/vincular` | Bridge | Bearer `AYLA_BRIDGE_TOKEN` |
| `/api/ia/*` | Skill OpenClaw legado | Bearer `OPENCLAW_SAS_TOKEN` |
| `/api/openclaw/*` | Frontend config | `X-Usuario-Id` manual |
| VPS `/internal/allowlist/*` | Laravel | Bearer `AYLA_VPS_SYNC_TOKEN` |
| API VendaFácil | Laravel | Bearer criptografado por integração |

Não foi encontrada autenticação Sanctum/JWT/OAuth aplicada a esses fluxos administrativos.

## 5. MCP

### Artefatos presentes

| Artefato | Local |
|---|---|
| Manifesto de tools | `openclaw/mcp-sas-estoque/tools.json` |
| Patrimônio | `docs/mcp/patrimonio-tools.mjs` |
| Reservas leitura | `docs/mcp/reservas-tools.mjs` |
| Reservas escrita | `docs/mcp/reservas-write-tools.mjs` |
| Skill Ayla | `openclaw/skill-ayla/SKILL.md` |

### Ferramentas descritas

- `kanban_consultar`;
- conjunto `patrimonio_*`;
- consultas `reservas_*`;
- preparar/confirmar/cancelar ações de reserva.

### Ausente do repositório

- servidor MCP executável completo;
- bootstrap/transporte MCP;
- `initialize`;
- `tools/list`;
- implementação central de `tools/call`;
- resources;
- prompts MCP;
- client MCP Laravel;
- health/telemetria;
- unit systemd do servidor MCP.

Os snippets pressupõem `/opt/sas-estoque-mcp/server.mjs` externo.

### Resources e prompts

Nenhum MCP Resource e nenhum MCP Prompt foram encontrados.

### Divergência de cobertura

O Laravel expõe mais ferramentas que o manifesto MCP. Ausentes no manifesto:

- status;
- unidades;
- produtos;
- estoque;
- movimentações;
- lotes;
- compras;
- fornecedores;
- dashboard;
- relatório por unidade.

Essas operações aparecem na skill como chamadas HTTP diretas, criando dois estilos de integração.

## 6. Telegram

### Componentes

| Componente | Local |
|---|---|
| Bridge webhook | `openclaw/telegram-auth-bridge/server.js` |
| Env exemplo | `openclaw/telegram-auth-bridge/.env.example` |
| Serviço systemd | `openclaw/telegram-auth-bridge/ayla-telegram-auth.service` |
| Sync API | `openclaw/ayla-telegram-bridge/sync-server.js` |
| Script allowlist | `openclaw/ayla-telegram-bridge/bin/sync-allowlist.sh` |
| Convite Laravel | `AylaConviteService`, `AylaConviteController` |

### Webhook/polling

- modelo implementado: webhook;
- não foi encontrado polling `getUpdates`;
- o bridge deve ser o único proprietário do webhook do bot;
- se o OpenClaw registrar webhook próprio, pode substituir o bridge.

### IDs

- `telegram_user_id`: autorização real;
- `chat_id`: usado pelo bridge para resposta;
- username/nome: metadados;
- telefone: identificação e WhatsApp; não é autenticação;
- convite: token aleatório cujo hash é persistido.

### Cache

O bridge mantém cache booleano de autorização por cinco minutos. Bloqueio/revogação pode demorar até o TTL para afetar o gate dinâmico.

## 7. OpenClaw

### Stack Ayla

- bridge Telegram;
- `skill-ayla`;
- MCP externo ou chamadas HTTP;
- token `AYLA_SAS_TOKEN`.

### Stack legado

- `skill-sas-estoque`;
- `/api/ia/*`;
- `AiAssistantService`;
- `OpenClawConfigController`;
- token `OPENCLAW_SAS_TOKEN`;
- logs `ai_assistant_logs`.

### Comunicação

- recebe update bruto do bridge em webhook local;
- decide ferramenta fora do Laravel;
- consulta Laravel por HTTP/MCP;
- devolve mensagem ao Telegram.

O runtime/config efetivo da VPS não está neste repositório, então sessão, modelo, memória e comportamento final não podem ser confirmados por código local.

## 8. Banco de dados

### 8.1 Integrações

| Tabela | Campos/relacionamentos principais | Índices/FKs |
|---|---|---|
| `integrations` | provider, URLs, secrets, status, empresa/unidade, JSON config | unique lógico provider/tenant; sem FK |
| `integration_logs` | integração, endpoint, HTTP, usuário, payloads | índices provider/data, integração/data, status; sem FK |
| `integration_mappings` | integração, entidade, local/external ID | uniques locais/externos; sem FK |
| `integration_webhooks` | integração, evento, path, secret | índices integração/evento e ativo; sem FK |

### 8.2 Ayla

| Tabela | Campos principais | Índices/FKs |
|---|---|---|
| `ayla_usuarios_autorizados` | usuário, Telegram, telefone, módulos, unidades, capacidades, status, sync | índices usuário/Telegram/status; sem FK/unique |
| `ayla_convites` | acesso, usuário, token_hash, status/datas, Telegram, telefone, criador | índices acesso/status, hash/status, expiração; sem FK |
| `ayla_acoes_pendentes` | usuário, Telegram, canal, módulo, ação, payload, status, resultado | índices diversos; sem FK |
| `ayla_audit_logs` | usuário, IP, método, rota, ação, payload/resumo, HTTP, duração | ação/status/data; sem FK |
| `sistema_configuracoes` | chave/valor | chave é PK |

### 8.3 IA legado

| Tabela | Estado |
|---|---|
| `ai_conversations` | Sem consumidor Ayla atual identificado |
| `ai_messages` | Sem consumidor Ayla atual identificado |
| `ai_tool_logs` | Legado |
| `ai_documents` | Legado |
| `ai_agents` | Agentes/prompts legados |
| `ai_agent_modules` | Relações de agentes legadas |
| `ai_assistant_logs` | Logs do `/api/ia` legado |

### 8.4 Domínio acessado

- `usuarios`;
- `unidades`;
- produtos/estoque/lotes/movimentações;
- compras/fornecedores;
- kanban;
- patrimônio;
- mesas/reservas.

## 9. Relacionamentos lógicos

```text
usuarios.id
  ├─ ayla_usuarios_autorizados.usuario_id
  ├─ ayla_audit_logs.user_id
  ├─ ayla_acoes_pendentes.usuario_id
  └─ integration_logs.usuario_id

ayla_usuarios_autorizados.id
  └─ ayla_convites.ayla_usuario_autorizado_id

integrations.id
  ├─ integration_logs.integration_id
  ├─ integration_mappings.integration_id
  └─ integration_webhooks.integration_id
```

Esses vínculos não são protegidos por foreign keys.

## 10. Configurações e precedência

### Ayla

| Configuração | Origem |
|---|---|
| Token API | env `AYLA_SAS_TOKEN`, depois DB |
| Bridge | env `AYLA_BRIDGE_TOKEN` |
| Bot username | config/env, com fallback |
| VPS sync | env URL/token |
| Flags de painel | `sistema_configuracoes` |

### OpenClaw legado

Usa `OpenClawSettings`, `config/openclaw.php` e `OPENCLAW_SAS_TOKEN`, com precedência diferente da Ayla.

### Integrações

VendaFácil usa tabela `integrations`; secrets são criptografados via cast Eloquent.

## 11. Logs

| Camada | Local |
|---|---|
| Laravel geral | `backend/storage/logs/laravel.log` |
| Ayla | `ayla_audit_logs` |
| Ações Ayla | `ayla_acoes_pendentes` |
| Integrações | `integration_logs` |
| OpenClaw legado | `ai_assistant_logs` |
| Bridge Telegram | stdout/journald do serviço |
| Sync allowlist | stdout/journald + backups |
| OpenClaw runtime | VPS/journald, fora do repo |
| MCP | não definido no repo |
| Queue/jobs | inexistente para estes fluxos |

Não há política de retenção identificada.

## 12. Dependências externas

- Telegram Bot API;
- OpenClaw;
- servidor MCP externo;
- API VendaFácil;
- VPS;
- banco relacional;
- Laravel HTTP Client/Cache/Crypt;
- systemd, Node.js, Bash, Python, `flock`;
- `openclaw config validate`.

## 13. Trust boundaries

### Não confiáveis

- navegador;
- headers `X-Usuario-Id`;
- webhook público;
- payload Telegram;
- URL VendaFácil configurável;
- resposta VendaFácil;
- chamada externa à VPS.

### Segredos

- `APP_KEY`;
- `AYLA_SAS_TOKEN`;
- `AYLA_BRIDGE_TOKEN`;
- `AYLA_VPS_SYNC_TOKEN`;
- Telegram bot token;
- Telegram webhook secret;
- `OPENCLAW_SAS_TOKEN`;
- Bearer VendaFácil;
- webhook secret VendaFácil;
- possível chave OpenAI.

## 14. Invariantes pretendidas e realidade

| Invariante | Situação |
|---|---|
| Telegram ID não concede acesso sozinho | Validado em `/acesso/validar` |
| Contexto do usuário limita ferramentas | Quebrado na propagação bridge → OpenClaw → MCP |
| Bridge é único consumidor Telegram | Intenção; depende da VPS |
| Allowlist reflete usuários ativos | Atualizações pontuais; sem reconciliação completa |
| Convite é uso único | Implementado com hash e lock |
| Ações exigem confirmação | Implementado, mas sujeito a concorrência |
| Segredos não aparecem no frontend | Em geral implementado |
| Admin está autenticado | Não: só header com ID |

