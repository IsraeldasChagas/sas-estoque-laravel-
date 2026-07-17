# Análise técnica — Menu Ayla IA

Data da auditoria: 2026-07-16  
Escopo: backend, frontend, Telegram, OpenClaw e persistência no estado atual do repositório.

## 1. Conclusão arquitetural

A Ayla não é um chatbot executado pelo Laravel. O Laravel funciona como:

- painel administrativo;
- API autenticada;
- gateway determinístico de ferramentas;
- camada de autorização;
- camada de consulta/escrita no banco;
- auditoria;
- cadastro e vínculo Telegram.

O runtime conversacional, modelo de linguagem, memória de conversa e seleção de ferramentas pertencem ao OpenClaw, fora do Laravel. O repositório contém skills e descritores, mas não contém o runtime OpenClaw nem um servidor MCP executável completo.

## 2. Frontend e menu

### Arquivos

| Arquivo | Finalidade |
|---|---|
| `frontend/index.html` | Menu, sections e botões |
| `frontend/app.js` | Navegação e permissões |
| `frontend/ayla-ia.js` | Dashboard, usuários, convites, permissões, canais, logs e config |

### Sections

- Dashboard;
- Usuários autorizados;
- Permissões;
- Canais e voz;
- Logs;
- Configurações.

ADMIN recebe todas as sections. GERENTE recebe Dashboard conforme matriz de permissões do frontend. Escritas administrativas são bloqueadas no backend para não-ADMIN.

### Funções do frontend

`frontend/ayla-ia.js` implementa:

- `loadAylaDashboard`;
- `loadAylaUsuarios`;
- cadastro/edição de acesso;
- telefone Telegram;
- geração/renovação/cancelamento de convite;
- polling de convite;
- copiar link/mensagem e abrir `wa.me`;
- bloquear, reativar, revogar, desvincular;
- sincronizar allowlist;
- permissões por módulo/unidade;
- configuração de Telegram/voz;
- logs administrativos.

O Telegram User ID deixa de ser exigido no formulário novo, mas usuários antigos com ID permanecem suportados.

## 3. Rotas

### 3.1 API Ayla v1

Arquivo: `backend/routes/ayla_routes.php`.

Proteção principal: middleware `ayla.token`, exceto vínculo por convite, que usa `ayla.bridge`.

Grupos funcionais:

- status;
- unidades;
- produtos e abaixo do mínimo;
- estoque e movimentações;
- lotes vencendo;
- compras;
- fornecedores;
- dashboard;
- kanban;
- patrimônio;
- reservas;
- relatório por unidade;
- validação de acesso Telegram;
- vínculo Telegram;
- escrita controlada de reservas.

Rotas de escrita direta de reservas existem apenas para retornar erro e exigir fluxo preparar/confirmar.

### 3.2 Painel administrativo

Arquivo: `backend/routes/ayla_admin_routes.php`.

Rotas:

- opções;
- dashboard;
- logs;
- config;
- gerar token;
- testar conexão;
- administrador principal;
- CRUD de usuários autorizados;
- mudança de status;
- convite;
- renovar/cancelar convite;
- sincronizar/desvincular Telegram.

Essas rotas não usam middleware autenticador. Os controllers validam manualmente `X-Usuario-Id`.

## 4. Controllers

### `Api\AylaController`

Local: `backend/app/Http/Controllers/Api/AylaController.php`.

Controller central da API v1. Responsabilidades:

- validar parâmetros;
- resolver usuário por header;
- despachar ferramentas;
- padronizar respostas;
- auditar;
- conduzir ações pendentes;
- validar acesso Telegram;
- vincular convite.

Ele não chama OpenAI nem executa prompt. Recebe chamadas já decididas pelo OpenClaw/MCP.

### `AylaUsuarioController`

Local: `backend/app/Http/Controllers/AylaUsuarioController.php`.

Responsabilidades:

- CRUD de usuários autorizados;
- módulos, unidades e capacidades;
- status ativo/bloqueado/revogado;
- integração com allowlist em mudança de status;
- dashboard e logs;
- configurações;
- geração do token SAS;
- teste da API;
- administrador principal.

### `AylaConviteController`

Local: `backend/app/Http/Controllers/AylaConviteController.php`.

Responsabilidades:

- gerar/renovar/cancelar convite;
- retornar status;
- produzir mensagem/URL WhatsApp;
- sincronizar allowlist;
- desvincular Telegram;
- registrar auditoria.

### Stack legado

Também permanecem ativos:

- `Api\AiAssistantController`;
- `OpenClawConfigController`;
- rotas `/api/ia/*`;
- rotas `/api/openclaw/*`.

Esse stack não é a API Ayla v1 e usa token/configuração/log próprios.

## 5. Middleware e autenticação

### `CheckAylaToken`

Local: `backend/app/Http/Middleware/CheckAylaToken.php`.

Executa:

- verifica se Ayla está ativa;
- extrai Bearer;
- compara com `hash_equals`;
- aplica rate limit por token + IP;
- registra tentativa inválida;
- responde CORS.

Token efetivo:

1. `AYLA_SAS_TOKEN`;
2. fallback `sistema_configuracoes.ayla_sas_token`.

### `CheckAylaBridgeToken`

Local: `backend/app/Http/Middleware/CheckAylaBridgeToken.php`.

Protege exclusivamente `/api/ayla/v1/telegram/vincular` usando `AYLA_BRIDGE_TOKEN`.

### Identidade administrativa

Painel Ayla usa apenas:

```text
X-Usuario-Id → usuarios.id ativo → perfil ADMIN/GERENTE
```

Não há validação de que o Bearer do login pertence ao ID informado.

### Identidade nas ferramentas

`AylaController` tenta resolver `X-Usuario-Id`. Quando ausente, `AylaApiService` constrói contexto sintético ADMIN, com ID 0.

O bridge Telegram valida o usuário, mas reduz a resposta a booleano e encaminha o update original ao OpenClaw. O contexto SAS, módulos e unidades não seguem automaticamente para as chamadas MCP/HTTP posteriores.

Esse é o principal rompimento da cadeia de autorização.

## 6. Services

### `AylaAccessService`

Local: `backend/app/Services/AylaAccessService.php`.

Fluxo `autorizarTelegram`:

1. busca `telegram_user_id`;
2. exige vínculo ativo;
3. exige usuário SAS existente e ativo;
4. cria `SasIaContext`;
5. calcula módulos efetivos;
6. calcula unidades;
7. retorna capacidades;
8. atualiza último acesso.

O resultado completo é entregue ao bridge, mas o bridge só conserva `autorizado`.

### `AylaApiService`

Local: `backend/app/Services/AylaApiService.php`.

Mantém allow-list interna de nomes de ferramentas e despacha para serviços determinísticos. Não aceita SQL arbitrário do modelo.

Problemas:

- allow-list descrita como read-only contém ferramentas de escrita;
- contexto sem usuário vira ADMIN;
- `relatorioUnidade` depende do stack paralelo de IA.

### `SasIaToolService`

Local: `backend/app/Services/SasIaToolService.php`.

Implementa ferramentas de dashboard, estoque, produtos, compras, fornecedores e outras consultas.

### `SasIaModuleQueryService`

Local: `backend/app/Services/SasIaModuleQueryService.php`.

Executa grande parte das consultas diretas via Query Builder. Também contém ferramentas de escrita do stack IA.

### Serviços de domínio Ayla

| Serviço | Finalidade |
|---|---|
| `AylaKanbanService` | Consultas Kanban |
| `AylaPatrimonioService` | Consultas de patrimônio |
| `AylaReservasService` | Consultas e mutações de reservas |
| `AylaAcaoPendenteService` | Preparar, confirmar e cancelar ações |
| `AylaConviteService` | Convites e vínculo Telegram |
| `AylaTelegramSyncService` | API interna de allowlist VPS |

### Escrita controlada

```text
preparar
  → ayla_acoes_pendentes
  → confirmação explícita
  → AylaReservasService
  → atualização do status/resultado
```

Não há transação/`lockForUpdate` abrangendo a confirmação e a execução. Confirmações concorrentes podem executar a mesma ação duas vezes.

## 7. Prompt, agentes, skills e providers

### Prompt principal

Não existe prompt principal Ayla no Laravel.

O prompt/instruções operacionais ficam em:

- `openclaw/skill-ayla/SKILL.md`.

### Skill legada

- `openclaw/skill-sas-estoque/SKILL.md`.

Ela usa `/api/ia/*`, é orientada ao OpenClaw/WhatsApp e possui modelo de confirmação diferente.

### Agentes legados

Migrations criam:

- `ai_agents`;
- `ai_agent_modules`.

Há prompt semeado para “Rafaela Almeida”, mas não foi encontrado runtime ativo que o carregue. Componentes antigos estão em `backup/ia-legada`.

### Providers

Não existe provider OpenAI ativo no fluxo Ayla backend. Há configs:

- `backend/config/openai.php`;
- seção OpenAI em `backend/config/services.php`.

Não foi encontrada chamada ativa à API OpenAI no backend Ayla.

## 8. Ferramentas e acesso ao banco

Cadeia principal:

```text
AylaController
  → AylaApiService
    → SasIaToolService / SasIaModuleQueryService
      → serviços Ayla de domínio
        → Query Builder / Eloquent
          → banco
```

Tabelas consultadas incluem:

- `usuarios`, `unidades`;
- `produtos`, `stock_lotes`, `lotes`, `movimentacoes`;
- `listas_compras`, `fornecedores`;
- `kanban_tasks`;
- tabelas de patrimônio;
- `mesas`, `reservas_mesas`, `reserva_mesas`;
- `sistema_configuracoes`;
- tabelas Ayla.

Não há SQL livre fornecido pelo LLM.

## 9. Conversas, memória e contexto

### No Laravel Ayla atual

Não existe persistência ativa de:

- conversa;
- mensagem;
- memória semântica;
- sessão Telegram;
- histórico de prompt;
- resumo de contexto.

O contexto conversacional pertence ao OpenClaw externo.

### Legado residual

Tabelas existentes:

- `ai_conversations`;
- `ai_messages`;
- `ai_tool_logs`;
- `ai_documents`;
- `ai_agents`;
- `ai_agent_modules`.

Não foi identificado consumidor ativo no fluxo Ayla atual.

## 10. Models

| Model | Tabela | Finalidade |
|---|---|---|
| `AylaUsuarioAutorizado` | `ayla_usuarios_autorizados` | Vínculo SAS/Telegram e permissões |
| `AylaConvite` | `ayla_convites` | Convite de uso único |
| `AylaAcaoPendente` | `ayla_acoes_pendentes` | Estado de ação controlada |
| `AylaAuditLog` | `ayla_audit_logs` | Auditoria |

Relacionamentos são majoritariamente lógicos, sem foreign keys.

## 11. Configurações

### `backend/config/ayla.php`

- versão;
- enabled;
- read-only;
- token;
- rate limit;
- unidades;
- bot username;
- bridge token;
- URL/token de sync VPS;
- validade do convite.

### `AylaSettings`

Local: `backend/app/Support/Ayla/AylaSettings.php`.

Usa `sistema_configuracoes` para:

- ativa/read-only;
- URLs API/gateway;
- rate limit;
- unidades;
- mensagens;
- Telegram;
- áudio;
- último teste;
- token.

Campos provavelmente apenas administrativos/display:

- `ayla_api_url`;
- `ayla_gateway_url`;
- `ayla_msg_nao_autorizado`;
- `ayla_msg_boas_vindas`;
- `ayla_telegram_ativo`;
- `ayla_audio_*`.

O bridge usa seu próprio `.env`, não essas chaves. O teste de conexão usa `APP_URL`, não `ayla_api_url`.

## 12. Permissões

Camadas pretendidas:

1. usuário SAS ativo;
2. perfil/menu SAS;
3. vínculo Ayla ativo;
4. módulos permitidos Ayla;
5. unidades permitidas Ayla;
6. capacidades texto/áudio/consulta/ações;
7. read-only global.

Na prática:

- `/acesso/validar` calcula essa interseção corretamente;
- o bridge não propaga esse contexto às ferramentas;
- as ferramentas podem cair no contexto ADMIN sintético;
- algumas flags, como `pode_consultar_dados`, só aparecem na resposta de autorização.

## 13. Jobs, filas, eventos e listeners

Não foram encontrados Jobs, Events ou Listeners Ayla/OpenClaw. Não há fila no fluxo:

- vínculo;
- sync de allowlist;
- auditoria;
- chamadas à VPS;
- ações;
- encaminhamento Telegram.

Tudo é síncrono.

## 14. Logs

### Ayla

Tabela `ayla_audit_logs`, model `AylaAuditLog`.

Registra:

- usuário;
- IP;
- método/rota/ação;
- payload/resumo;
- status HTTP;
- duração;
- timestamps.

Falhas de auditoria são engolidas para não quebrar a operação.

### Ações

`ayla_acoes_pendentes` mantém payload, status, resultado e datas.

### Laravel

`storage/logs/laravel.log`, conforme configuração padrão.

### OpenClaw legado

Tabela/model `ai_assistant_logs`.

## 15. Testes

Arquivos:

- `backend/tests/Feature/AylaApiTest.php`;
- `backend/tests/Feature/AylaConviteTest.php`.

Cobrem:

- token;
- status;
- filtros;
- kanban;
- patrimônio;
- reservas;
- ações controladas;
- convites;
- vínculo;
- cancelamento;
- desvinculação;
- permissões administrativas básicas.

Lacunas:

- spoofing de `X-Usuario-Id`;
- contexto ADMIN sem usuário;
- propagação de módulos/unidades;
- concorrência de confirmação;
- sync/revogação real;
- bridge/VPS/OpenClaw;
- migrations reais, pois testes criam schemas manuais.

## 16. Código morto, duplicado ou inconsistente

- stack `/api/ia` permanece ativo em paralelo;
- tabelas `ai_*` sem consumidor Ayla;
- prompt/agente Rafaela sem runtime;
- configs OpenAI duplicadas;
- `SasIaToolRegistry` sem provider ativo;
- `AylaAccessService::estadoTelegram()` sem consumidor;
- `AylaTelegramSyncService::sincronizar()` sem consumidor;
- parâmetros `$userId` pouco usados em serviços de domínio;
- configurações Telegram/áudio do painel não controlam o bridge;
- documentação “somente leitura” contradiz escrita de reservas;
- token gerado no painel pode ser ignorado se env tiver prioridade.

## 17. Riscos principais

1. Identidade administrativa forjável por header.
2. Bearer compartilhado não vinculado ao usuário.
3. Contexto Telegram não chega às ferramentas.
4. Fallback ADMIN sem usuário.
5. Confirmação de escrita sujeita a corrida.
6. Dois stacks de assistente ativos.
7. Configurações duplicadas e com precedência divergente.
8. Sem retenção de logs/convites/ações expiradas.
9. Sem fila/retry para integrações.
10. Dependência de componentes externos não versionados no repositório.

