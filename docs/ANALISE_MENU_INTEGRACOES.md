# Análise técnica — Menu Integrações

Data da auditoria: 2026-07-16  
Escopo: estado atual do repositório, sem alterações de código ou banco.

## 1. Visão geral

O menu **Integrações** é uma infraestrutura genérica cuja única integração externa parcialmente operacional é o VendaFácil. O módulo permite:

- armazenar configuração e credenciais criptografadas;
- testar a API remota;
- identificar empresa, ambiente e versão;
- executar health check;
- registrar logs;
- mapear unidades manualmente;
- desconectar e limpar credenciais.

Não existe sincronização operacional de produtos, estoque, clientes, pedidos, vendas, delivery, caixa, pagamentos ou fiscal. A ação de sincronização retorna HTTP 501.

O OpenClaw aparece no catálogo do menu, mas não usa essa arquitetura: possui controllers, settings, token, rotas e logs próprios. Ayla também é um módulo independente.

## 2. Frontend e menu

### 2.1 Arquivos

| Item | Localização | Finalidade |
|---|---|---|
| Menu e sections | `frontend/index.html` | Links e containers das telas |
| Navegação e permissões | `frontend/app.js` | Exibição por perfil e carregamento das sections |
| Implementação das telas | `frontend/integracoes.js` | Configuração, teste, health, logs e unidades |
| Estilos | `frontend/style-integracoes.css` | Layout responsivo e badges |

### 2.2 Submenus

| Section | Situação atual |
|---|---|
| `integracaoVendafacil` | Implementada até conexão/health/mapeamento |
| `integracaoAplicacoes` | Catálogo de aplicações |
| `integracaoHealthCheck` | Health ao vivo |
| `integracaoLogs` | Logs com filtros |
| `integracaoWebhooks` | Placeholder |
| `integracaoTokens` | Placeholder |
| `integracaoConfiguracoes` | Placeholder |

Há textos antigos em `frontend/index.html` descrevendo VendaFácil e Health como “estruturais”, embora a Fase 2 esteja implementada.

### 2.3 Permissões de interface

- ADMIN recebe todas as sections.
- GERENTE recebe Aplicações, Health e Logs.
- `frontend/app.js` reforça essas permissões por perfil, mesmo se `permissoes_menu` tentar removê-las.
- A UI oferece checkboxes de sections no cadastro de usuários, mas as regras automáticas podem prevalecer.

### 2.4 Cliente HTTP

`frontend/integracoes.js` chama `window.fetchJSON`, definido em `frontend/app.js`.

Headers enviados:

- `Authorization: Bearer ...`;
- `X-Usuario-Id`;
- headers de dispositivo.

Nas rotas de Integrações, o Bearer não é validado. A identidade efetiva é determinada apenas por `X-Usuario-Id`.

## 3. Rotas internas

Arquivo: `backend/routes/integrations_routes.php`, incluído por `backend/routes/api.php`.

### 3.1 Catálogo e observabilidade

| Método | Rota | Controller |
|---|---|---|
| GET | `/api/integracoes/aplicacoes` | `IntegrationController::aplicacoes` |
| GET | `/api/integracoes/health-check` | `HealthCheckController::index` |
| GET | `/api/integracoes/logs` | `IntegrationLogController::index` |

### 3.2 VendaFácil

| Método | Rota | Finalidade |
|---|---|---|
| GET | `/api/integracoes/vendafacil` | Obter configuração mascarada |
| PUT/POST | `/api/integracoes/vendafacil` | Salvar configuração |
| POST | `/api/integracoes/vendafacil/testar` | Testar conexão |
| POST | `/api/integracoes/vendafacil/testar-conexao` | Alias do teste |
| GET | `/api/integracoes/vendafacil/health` | Health dedicado |
| GET | `/api/integracoes/vendafacil/logs` | Logs do provider |
| GET | `/api/integracoes/vendafacil/unidades` | Unidades e mappings |
| PUT/POST | `/api/integracoes/vendafacil/unidades` | Salvar mappings |
| POST | `/api/integracoes/vendafacil/sincronizar` | Não implementado; 501 |
| POST | `/api/integracoes/vendafacil/limpar-cache` | Remove chaves de cache |
| POST | `/api/integracoes/vendafacil/desconectar` | Limpa credenciais |

### 3.3 Webhooks

| Método | Rota | Situação |
|---|---|---|
| GET | `/api/integracoes/webhooks` | Estrutural |
| POST | `/api/integracoes/webhooks/{provider}` | Público, retorna 501 |

Não há verificação de assinatura funcional, replay protection ou processamento de evento.

## 4. Controllers

### `IntegrationBaseController`

Local: `backend/app/Http/Controllers/Integrations/IntegrationBaseController.php`

Responsabilidades:

- resposta JSON com CORS `*`;
- identifica usuário por `X-Usuario-Id`;
- ADMIN/ADMINISTRADOR pode configurar;
- ADMIN/ADMINISTRADOR/GERENTE pode visualizar.

Não usa policy nem middleware autenticador. O método `corsPreflight()` não possui consumidor.

### `VendaFacilController`

Local: `backend/app/Http/Controllers/Integrations/VendaFacilController.php`

Chamado por todas as rotas VendaFácil. Delega chamadas externas ao `IntegrationManager`; não chama o HTTP client diretamente.

Fluxos:

- `show`: cria ou carrega integração e retorna config segura;
- `save/update`: valida payload, URL e valores numéricos;
- `testarConexao`: aciona Manager e retorna resultado;
- `health`: consulta estado ao vivo ou local;
- `logs`: delega ao controller de logs;
- `unidades/salvarUnidades`: lê e grava mappings;
- `sincronizar`: retorna não implementado;
- `desconectar`: limpa secrets e opcionalmente mappings.

### `HealthCheckController`

Executa health dos providers registrados. Hoje só VendaFácil. Importa `IntegrationLog`, mas não o usa.

### `IntegrationLogController`

Consulta `integration_logs`. Filtros:

- provider;
- status;
- operação;
- usuário;
- unidade;
- sucesso/falha;
- data inicial/final;
- limite.

### `IntegrationController`

Monta catálogo de VendaFácil, OpenClaw, WhatsApp, iFood, Marketplace, ERP e Outros. Injeta `IntegrationManager`, mas não utiliza a instância.

O card OpenClaw consulta a tabela genérica `integrations`, embora o OpenClaw use `OpenClawSettings`; por isso o status pode permanecer offline sem refletir o serviço real.

### `WebhookController`

Existe como estrutura futura. Não processa webhooks reais.

## 5. Serviços, contrato e provider

### Cadeia obrigatória

```text
Controller
  → IntegrationManager
    → IntegrationProviderInterface
      → VendaFacilProvider
        → HttpIntegrationClient
          → API VendaFácil
```

### `IntegrationManager`

Local: `backend/app/Services/Integrations/IntegrationManager.php`

Responsável por:

- registrar e localizar providers;
- criar/carregar integração;
- montar configuração em memória;
- salvar configuração;
- testar conexão;
- registrar log;
- atualizar estado;
- health check;
- desconectar;
- salvar mappings;
- limpar cache.

Pontos relevantes:

- `GET` da configuração pode criar um registro no banco;
- a validação do provider é calculada, mas não bloqueia efetivamente configuração parcial;
- `last_sync_at` é atualizado em teste de conexão, não em sincronização;
- cache de health é escrito, mas não lido;
- cache de config é removido, mas não há escrita correspondente;
- método privado `toProviderArray()` não possui consumidor.

### `IntegrationProviderInterface`

Local: `backend/app/Contracts/Integrations/IntegrationProviderInterface.php`

Define:

- identificação e nome;
- recursos;
- validação;
- teste;
- health;
- empresa/ambiente/versão remotos;
- sincronização.

`providerName()` duplica semanticamente `getProviderCode()`. Os métodos individuais de empresa, ambiente e versão não têm consumidor identificado.

### `VendaFacilProvider`

Local: `backend/app/Services/Integrations/Providers/VendaFacilProvider.php`

Endpoints externos:

- `GET {api_url}/api/v1/integration/status`;
- `GET {api_url}/api/v1/units`.

O segundo é opcional e não é acionado pela UI normal.

Recursos declarados, mas sem implementação operacional:

- produtos;
- estoque;
- clientes;
- pedidos;
- delivery;
- vendas;
- caixa;
- fiscal;
- pagamentos.

O health considera token válido para qualquer resposta diferente de 401, inclusive 403, 404 e 500.

### `HttpIntegrationClient`

Local: `backend/app/Services/Integrations/HttpIntegrationClient.php`

Suporta:

- GET, POST, PUT, PATCH e DELETE;
- Bearer Token;
- headers JSON;
- timeout;
- retries em conexão e 5xx;
- medição do tempo;
- tratamento de JSON inválido;
- mapeamento de erros HTTP/conexão.

Limitações:

- sem `connectTimeout`;
- retries síncronos podem ocupar worker por minutos;
- 5xx com corpo inválido retorna `INVALID_JSON` antes do retry;
- sem circuit breaker, telemetria, idempotency key ou OAuth;
- redirecionamentos não são revalidados pela proteção SSRF.

### Registro

- `backend/app/Providers/IntegrationServiceProvider.php`;
- registrado em `backend/bootstrap/providers.php`.

Somente `VendaFacilProvider` é registrado.

## 6. Models e banco

### Tabelas

Migrations:

- `2026_07_16_000001_create_integrations_tables.php`;
- `2026_07_16_000002_extend_integrations_phase2.php`.

#### `integrations`

Campos principais:

- `provider`, `name`, `api_url`;
- `bearer_token`, `webhook_secret`;
- `environment`;
- `empresa_external_id`, `empresa_external_name`;
- `unidade_mappings`, `enabled_resources`, `config_json`;
- `timeout_seconds`, `retry_count`;
- `connection_status`, `integration_status`;
- datas/erros/tempo/versão;
- `is_active`;
- `empresa_id`, `unidade_id`;
- `observacoes`;
- timestamps.

Índices:

- unique lógico `provider + empresa_id + unidade_id`;
- provider;
- connection_status.

No MySQL, múltiplos NULL podem permitir mais de uma integração global do mesmo provider.

#### `integration_logs`

Campos:

- integração/provider/operação/direção;
- método/endpoint;
- tempo/tentativa/status HTTP;
- status/mensagem;
- empresa/unidade/usuário/IP;
- payloads sanitizados;
- data.

#### `integration_mappings`

Campos:

- integração;
- tipo de entidade;
- ID local e externo;
- UUID externo;
- unidade;
- metadados;
- última sincronização;
- timestamps.

Unique por `(integration_id, entity_type, local_id)` e também por external ID.

#### `integration_webhooks`

Campos:

- integração;
- tipo de evento;
- path;
- secret;
- ativo;
- última recepção;
- timestamps.

Sem fluxo funcional atual.

### Models

| Model | Finalidade |
|---|---|
| `Integration` | Config, casts criptografados e serialização segura |
| `IntegrationLog` | Logs e sanitização recursiva |
| `IntegrationMapping` | Mapeamentos |
| `IntegrationWebhook` | Estrutura de webhooks |

Não existem foreign keys nas tabelas de integração. Relacionamentos Eloquent são lógicos.

## 7. Segurança e configuração

### Credenciais

- `bearer_token` e `webhook_secret` usam cast Laravel `encrypted`;
- respostas usam mascaramento;
- logs removem token, Authorization, secret, senha e variações;
- `APP_KEY` é dependência crítica para descriptografia.

### SSRF

Arquivos:

- `backend/config/integrations.php`;
- `backend/app/Support/Integrations/IntegrationUrlValidator.php`.

Proteções:

- somente HTTP/HTTPS;
- HTTPS obrigatório em produção;
- bloqueio de localhost;
- bloqueio de IPs privados/reservados;
- resolução inicial de DNS.

Lacunas:

- redirects não são revalidados;
- possível DNS rebinding;
- sem allowlist de domínio VendaFácil;
- userinfo/query/fragment não são proibidos;
- exceção de rede privada é global.

### Autenticação

Não há Sanctum, JWT ou policy aplicada a essas rotas. O Bearer do login é enviado pelo frontend, mas ignorado. O acesso depende apenas de `X-Usuario-Id`.

### Configuração externa

Não foi encontrada configuração VendaFácil em `.env.example`. A flag relevante para SSRF é `INTEGRATION_SSRF_ALLOW_PRIVATE`, citada na documentação, mas ausente do exemplo de ambiente.

## 8. Jobs, eventos, listeners, repositories e requests

Não foram encontrados diretórios `app/Jobs`, `app/Events` ou `app/Listeners` neste projeto. O módulo não possui:

- jobs/filas;
- eventos/listeners;
- repositories;
- Form Requests dedicados;
- API Resources;
- policies;
- traits específicos.

Todo processamento é síncrono.

## 9. Bugs e inconsistências prováveis

1. `X-Usuario-Id` pode ser forjado.
2. CORS `*` aumenta a superfície de exploração.
3. Alterar ambiente para produção sem reenviar URL pode manter HTTP previamente salvo.
4. Mappings removidos na UI não são excluídos da tabela e reaparecem.
5. Há duas fontes de verdade para mappings: JSON e tabela.
6. Falha de teste retorna 422 e o frontend pode perder a mensagem `mensagem`.
7. Falhas não atualizam os cards porque `fetchJSON` lança exceção antes do update visual.
8. `intSetBusy(false)` tende a manter botões ADMIN desabilitados.
9. Health não persiste resultado nem loga.
10. Cache de health não é consumido.
11. `token_valid` pode ser verdadeiro em falhas não-401.
12. Sem rate limit no teste/health.
13. Sem foreign keys ou retenção de logs.
14. Aliases de rotas duplicam superfície.
15. Endpoint de unidades remotas não é usado pela UI.

## 10. Código morto, placeholders e duplicidades

- cache de health/config sem ciclo completo;
- `providerName()` duplicado;
- métodos remotos individuais sem consumidor;
- endpoint dedicado de health não usado pela UI;
- endpoint dedicado de logs não usado pela UI;
- GET de unidades não usado pela UI;
- sincronização sempre 501;
- Webhooks/Tokens/Configurações são placeholders;
- `setupIntegracoesModule()` vazio;
- tabela/model de webhooks sem operação real;
- catálogo OpenClaw desconectado do seu settings real;
- aliases POST/PUT e teste duplicado.

## 11. Estado funcional confirmado por código

### Funciona

- salvar configuração;
- criptografia e mascaramento;
- teste do endpoint de status;
- identificação de empresa/ambiente/versão;
- logging do teste;
- desconexão;
- mapeamento manual;
- health ao vivo;
- filtros de logs.

### Não funciona ou não existe

- sincronização comercial;
- produtos/clientes/pedidos/vendas/estoque;
- webhooks;
- OAuth/JWT/Sanctum;
- scheduler/queue;
- integração real do card OpenClaw;
- consumo operacional dos recursos e mappings.

## 12. Dependências

- Laravel HTTP Client;
- Eloquent/Query Builder;
- Cache Laravel;
- Crypt/casts criptografados;
- banco relacional;
- API externa VendaFácil;
- `APP_KEY`;
- frontend SPA e `fetchJSON`.

