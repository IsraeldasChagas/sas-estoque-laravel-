# Diagnóstico técnico final — Integrações e Ayla IA

Data: 2026-07-16  
Método: auditoria estática, somente leitura.

## 1. Parecer geral

O SAS-Estoque possui uma base funcional para:

- configuração e teste do VendaFácil;
- API determinística da Ayla;
- autorização de usuários Telegram;
- convites de uso único;
- auditoria;
- escrita controlada em reservas;
- integração com OpenClaw por bridge e skill.

Entretanto, a arquitetura atual não forma uma única plataforma de integração. Há três stacks paralelos:

1. Integrações genéricas/VendaFácil;
2. OpenClaw/IA legado;
3. Ayla/Telegram atual.

O risco mais grave não é a ausência de funcionalidade, mas a quebra entre a autorização do Telegram e o contexto efetivamente usado pelas ferramentas Laravel.

## 2. O que já funciona

### Integrações/VendaFácil

- configuração persistente;
- token e secret criptografados;
- mascaramento no frontend;
- validação básica de URL/SSRF;
- teste HTTP real;
- tratamento amigável de erros;
- empresa/ambiente/versão remotos;
- logs;
- health ao vivo;
- desconexão;
- mapeamento manual de unidades;
- testes automatizados da Fase 2.

### Ayla

- API v1 com Bearer e rate limit;
- respostas padronizadas;
- consultas determinísticas;
- integração com produtos/estoque/compras/fornecedores;
- Kanban;
- patrimônio;
- reservas;
- auditoria;
- cadastro de usuários autorizados;
- módulos/unidades/capacidades;
- ações pendentes;
- convites Telegram;
- vínculo por `/start TOKEN`;
- token de convite armazenado somente como hash;
- uso único, expiração e cancelamento;
- bloqueio/reativação/revogação/desvinculação;
- sync pontual de allowlist;
- testes de API e convite.

### Bridge/OpenClaw

- webhook Telegram;
- validação opcional do secret;
- fail-closed quando SAS não responde;
- cache de autorização;
- encaminhamento para webhook local;
- skill Ayla;
- scripts de allowlist com validação, lock e backup.

## 3. O que não funciona ou depende de infraestrutura externa

### Integrações

- sincronização de produtos;
- sincronização de estoque;
- clientes;
- pedidos;
- vendas;
- delivery;
- caixa/pagamentos/fiscal;
- webhooks reais;
- scheduler/jobs;
- status OpenClaw no catálogo genérico.

### Ayla/OpenClaw

- servidor MCP completo não está no repositório;
- runtime OpenClaw não está no repositório;
- memória e histórico conversacional não podem ser auditados localmente;
- configuração real do webhook na VPS não pode ser confirmada;
- reload efetivo do OpenClaw após allowlist é incerto;
- áudio não possui implementação encontrada;
- configurações do painel não controlam diretamente bridge/runtime;
- manifesto MCP não cobre toda API.

## 4. Achados críticos

### C-01 — Identidade administrativa forjável

Rotas de Integrações, Ayla Admin e OpenClaw Config confiam em `X-Usuario-Id`. Não há validação de que o Bearer/sessão pertence ao usuário.

Impacto:

- impersonação de ADMIN;
- leitura/alteração de credenciais;
- geração de convites;
- revogação/bloqueio de usuários;
- exposição de logs.

### C-02 — Contexto Telegram não é propagado

`/acesso/validar` retorna usuário, módulos, unidades e capacidades. O bridge reduz isso a booleano e encaminha o update sem identidade SAS.

Chamadas MCP/HTTP posteriores usam apenas o token compartilhado. Sem `X-Usuario-Id`, a camada de serviço pode criar contexto ADMIN.

Impacto:

- bypass de escopo por unidade;
- bypass de módulos;
- capacidades de consulta/ação não garantidas;
- auditoria sem usuário real.

### C-03 — Fallback ADMIN sem usuário

`AylaApiService` constrói usuário sintético ADMIN quando não há usuário resolvido.

Impacto: qualquer portador de `AYLA_SAS_TOKEN` pode obter contexto amplo.

### C-04 — MCP executável ausente

Há manifesto e snippets, mas não servidor MCP completo.

Impacto:

- deploy não reproduzível;
- contratos não verificáveis;
- comportamento da VPS diverge do repositório;
- impossível confirmar autenticação/contexto.

## 5. Achados de alto risco

### A-01 — Dois modelos conflitantes de acesso Telegram

- README do bridge: `dmPolicy=open`, `allowFrom=["*"]`;
- script de sync: força `dmPolicy=allowlist` e IDs explícitos.

Uma sincronização pode mudar silenciosamente o modelo de segurança.

### A-02 — Propriedade do webhook

Telegram permite um único webhook. Se o OpenClaw registrar o próprio webhook, substitui o bridge e contorna a validação SAS.

### A-03 — Confirmação concorrente

`AylaAcaoPendenteService` não usa transação/lock abrangente. Duas confirmações podem executar a mesma ação.

### A-04 — Reinício do gateway pode não ocorrer

O script usa `systemctl --user`; a documentação propõe serviço system-wide com outro contexto. O script pode alterar arquivo e considerar sucesso sem recarregar o gateway.

### A-05 — SSRF incompleto

Validação não cobre redirecionamentos e DNS rebinding. Não há allowlist de hosts VendaFácil.

### A-06 — Health/teste sem limite adequado

Rotas administrativas podem provocar chamadas externas síncronas repetidas. Retry máximo pode bloquear worker por longo período.

### A-07 — Chave OpenAI em ambiente

Foi identificada configuração de chave OpenAI no `.env` local auditado. O valor não é reproduzido aqui. Caso tenha sido exposto fora do servidor, deve ser considerado comprometido.

## 6. Achados médios

1. Health de Integrações não persiste nem loga.
2. Cache de health é escrito, mas nunca lido.
3. Remoção de mapping na UI não remove linha normalizada.
4. Mappings têm duas fontes de verdade.
5. Falhas 422 do teste VendaFácil perdem mensagem no frontend.
6. Botões ADMIN podem permanecer desabilitados após ação.
7. `token_valid` considera várias falhas como token válido.
8. Configuração HTTP homologação pode permanecer ao trocar para produção.
9. Sem foreign keys nas tabelas auditadas.
10. Unicidade global de `integrations` com NULL é frágil no MySQL.
11. Convites não têm `UNIQUE(token_hash)`.
12. Usuário/Telegram autorizado não possui unique de banco.
13. Cache do bridge posterga revogação em até cinco minutos.
14. Bridge aceita iniciar sem webhook secret.
15. Bridge usa `SAS_TOKEN` como fallback do bridge token dedicado.
16. Updates sem remetente identificável são encaminhados.
17. Telegram é confirmado antes do processamento; falhas posteriores não têm retry.
18. Configurações Ayla do painel são display-only em vários casos.
19. Teste Ayla ignora URL configurada e usa `APP_URL`.
20. Token gerado no painel pode ser ignorado quando env tem prioridade.
21. Não há limpeza de convites/ações expirados.
22. Não há retenção de logs.

## 7. Código duplicado

### Dois stacks de IA

| Aspecto | Ayla | Legado |
|---|---|---|
| API | `/api/ayla/v1` | `/api/ia` |
| Token | `AYLA_SAS_TOKEN` | `OPENCLAW_SAS_TOKEN` |
| Controller | `AylaController` | `AiAssistantController` |
| Serviço | `AylaApiService` | `AiAssistantService` |
| Logs | `ayla_audit_logs` | `ai_assistant_logs` |
| Skill | `skill-ayla` | `skill-sas-estoque` |
| Confirmação | ação pendente | resend `confirmacao=true` |

### Contratos de ferramentas duplicados

- `tools.json`;
- snippets `.mjs`;
- `SKILL.md`;
- `SasIaToolRegistry`;
- `AylaController`;
- documentação.

Os schemas já divergem.

### Configurações duplicadas

- `config/openai.php` e `config/services.php`;
- AylaSettings e env;
- OpenClawSettings e env;
- mensagens Telegram no painel e no env do bridge;
- status OpenClaw no catálogo genérico e no módulo próprio.

## 8. Código morto ou sem consumidor identificado

- tabelas `ai_conversations`, `ai_messages`, `ai_tool_logs`, `ai_documents`;
- agentes/prompt Rafaela no fluxo atual;
- `SasIaToolRegistry::definitions()` sem provider ativo;
- `AylaAccessService::estadoTelegram()`;
- `AylaTelegramSyncService::sincronizar()`;
- endpoint/script `sincronizar` de allowlist como no-op;
- cache de health/config de Integrações;
- `IntegrationManager::toProviderArray()` privado;
- métodos remotos individuais do provider;
- endpoints VendaFácil health/logs/unidades sem consumidor UI;
- setup vazio de Integrações;
- model/tabela de webhooks sem fluxo;
- placeholders Tokens/Configurações/Webhooks;
- imports/injeções não usados em controllers.

## 9. APIs não utilizadas ou parcialmente utilizadas

- `/api/integracoes/vendafacil/health`;
- `/api/integracoes/vendafacil/logs`;
- GET `/api/integracoes/vendafacil/unidades`;
- `/api/integracoes/vendafacil/sincronizar`;
- `/api/integracoes/webhooks`;
- `/internal/allowlist/sincronizar`;
- vários endpoints Ayla sem ferramenta MCP;
- stack `/api/ia` permanece, mas é paralelo ao caminho Telegram atual.

## 10. Configuração e documentação divergentes

1. Textos frontend ainda falam em Fase 1/estrutura.
2. Documentação Ayla diz somente leitura, mas reservas têm escrita.
3. README bridge manda abrir OpenClaw; sync força allowlist.
4. `AylaSaborPsraenseBot` pode conter typo.
5. Instalação automatizada instala skill legada, não skill Ayla.
6. `.env.example` de Integrações não documenta SSRF privada.
7. Painel Telegram salva flags/mensagens que bridge não lê.
8. Catálogo Integrações lista OpenClaw, mas omite Ayla/Telegram/MCP.

## 11. Melhorias recomendadas

### Prioridade 0 — segurança

1. Implementar autenticação de sessão/token real e vincular identidade ao token.
2. Remover confiança em `X-Usuario-Id` fornecido pelo cliente.
3. Propagar contexto assinado Telegram → OpenClaw → MCP → Laravel.
4. Remover fallback ADMIN; falhar fechado sem usuário.
5. Tornar webhook secret obrigatório.
6. Remover fallback de bridge token para SAS token.

### Prioridade 1 — coerência arquitetural

7. Escolher um único stack de assistente.
8. Escolher um único modelo de gate Telegram.
9. Versionar servidor MCP completo e deploy.
10. Gerar contratos MCP a partir de fonte única.
11. Definir ownership do webhook e monitorá-lo.
12. Implementar reconciliação autoritativa de allowlist.

### Prioridade 2 — integridade

13. Transação e lock para confirmação de ação.
14. Foreign keys/uniques compatíveis com dados existentes.
15. Unificar mapping de unidades.
16. Corrigir unicidade de integração global.
17. Separar `last_tested_at` de `last_sync_at`.

### Prioridade 3 — resiliência

18. Fila para sync/HTTP externo.
19. Retry durável e idempotência.
20. Circuit breaker e connect timeout.
21. Rate limit administrativo.
22. Retenção/pruning de logs, convites e ações.
23. Health persistido e observável.

### Prioridade 4 — manutenção

24. Remover código legado após migração controlada.
25. Eliminar configurações display-only ou conectá-las ao runtime.
26. Atualizar documentação e nomenclatura.
27. Testar migrations reais/MySQL.
28. Testar concorrência e trust boundaries.

## 12. Matriz de risco

| Severidade | Achado | Probabilidade | Impacto |
|---|---|---|---|
| Crítica | `X-Usuario-Id` forjável | Alta | Administração completa |
| Crítica | Contexto Telegram perdido | Alta | Bypass de escopo |
| Crítica | ADMIN sintético | Alta | Consulta ampla |
| Crítica | MCP fora do repo | Alta | Deploy não auditável |
| Alta | Webhook pode ser sobrescrito | Média | Bypass total do bridge |
| Alta | Gate open vs allowlist | Alta | Bloqueio/bypass/drift |
| Alta | Confirmação concorrente | Média | Escrita duplicada |
| Alta | SSRF parcial | Média | Acesso a rede interna |
| Média | Cache de revogação | Alta | Acesso temporário indevido |
| Média | Sem retenção | Alta | Crescimento/privacidade |

## 13. Limites desta auditoria

Não foram inspecionados em runtime:

- VPS;
- configuração efetiva OpenClaw;
- webhook registrado no Telegram;
- BotFather;
- `openclaw.json`;
- servidor MCP em `/opt`;
- systemd/journald reais;
- banco de produção;
- secrets reais;
- comportamento da API VendaFácil.

As conclusões sobre esses itens derivam dos artefatos do repositório.

## 14. Conclusão

O código já oferece uma base rica, mas a segurança de identidade não atravessa toda a cadeia. Antes de ampliar funcionalidades, o sistema precisa consolidar:

1. autenticação administrativa;
2. propagação de identidade;
3. modelo único Telegram/OpenClaw;
4. servidor MCP versionado;
5. stack único de IA;
6. integridade e observabilidade.

Enquanto esses pontos permanecerem, adicionar novas ferramentas aumenta o alcance de um contexto potencialmente privilegiado e difícil de auditar.

