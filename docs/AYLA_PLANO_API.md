# AYLA — Plano de Integração API

> **Data:** 2026-07-12  
> **Base:** `docs/AYLA_INVENTARIO_SISTEMA.md`  
> **Status:** Aguardando aprovação — **não implementado**

---

## 1. Objetivo

Criar uma API dedicada para a assistente **Ayla** com prefixo `/api/ayla/v1`, reutilizando ao máximo a infraestrutura existente (SAS IA tools, services, permissões) **sem duplicar** OpenClaw, SAS IA chat ou rotas legadas.

### Princípios

1. **Não reinventar** — delegar consultas ao `SasIaToolService` / `SasIaModuleQueryService`
2. **Não colidir** — prefixo exclusivo `/ayla/v1` (evitar `/ia/*`)
3. **Segurança primeiro** — token Bearer, rate limit, logs, confirmação para escrita
4. **Fases incrementais** — começar leitura; escrita controlada depois
5. **Respeitar permissões** — `permissoes_menu` + perfil do solicitante

---

## 2. Relação com integrações existentes

| Integração | Papel | Relação com Ayla |
|------------|-------|------------------|
| **SAS IA** (`/sas-ia/*`) | Chat interno com tool-calling OpenAI | **Reutilizar** `SasIaToolRegistry`, `SasIaToolService`, `SasIaContext` |
| **OpenClaw** (`/ia/*`) | Assistente externo WhatsApp, escopo estoque | **Não duplicar** — Ayla substitui/evolui OpenClaw com escopo maior |
| **IA Legado** (`/ia/chat`) | ChatGPT simples | **Ignorar** — obsoleto |
| **AI Agents** | Personas por módulo | **Estender** — adicionar módulo `ayla` em `AiAgentResolver` |

### Decisão arquitetural

A Ayla terá **dois modos de consumo**:

| Modo | Uso | Endpoint |
|------|-----|----------|
| **REST direto** | VPS/integrador chama endpoints tipados | `/api/ayla/v1/*` |
| **Chat** (fase 2) | Conversa com tool-calling | `/api/ayla/v1/chat` (reusa loop SAS IA) |

---

## 3. Estrutura proposta `/api/ayla/v1`

### 3.1 Endpoints — Fase 1 (leitura)

| Endpoint | Método | Descrição | Reutiliza |
|----------|--------|-----------|-----------|
| `/status` | GET | Saúde, versão, módulos ativos | Novo |
| `/unidades` | GET | Listar unidades | `consultar_resumo_unidades` |
| `/unidades/{id}` | GET | Detalhe + relatório unidade | `relatorioUnidade` (OpenClaw) + unidades |
| `/produtos` | GET | Busca por nome/ID | `consultar_produto_por_nome` |
| `/produtos/abaixo-minimo` | GET | Estoque abaixo do mínimo | `consultar_produtos_abaixo_estoque_minimo` |
| `/estoque` | GET | Resumo por unidade | `consultar_estoque_por_unidade` |
| `/estoque/movimentacoes` | GET | Movimentações recentes | `consultar_movimentacoes_recentes` |
| `/lotes/vencendo` | GET | Lotes a vencer | `consultar_lotes_proximos_vencer` |
| `/compras` | GET | Listas recentes | `consultar_compras_recentes` |
| `/fornecedores` | GET | Fornecedores | `consultar_fornecedores` |
| `/dashboard` | GET | KPIs consolidados | Agregação de tools estoque+financeiro |
| `/relatorios/unidade/{id}` | GET | Relatório resumido | `AiAssistantService::relatorioUnidade` |

### 3.2 Endpoints — Fase 2 (escrita com confirmação)

| Endpoint | Método | Confirmação | Reutiliza |
|----------|--------|-------------|-----------|
| `/estoque/perdas` | POST | **Obrigatória** (`confirmacao: true`) | `AiAssistantService::lancarPerda` |
| `/compras` | POST | **Obrigatória** | `AiAssistantService::cadastrarCompra` |
| `/reservas` | POST | **Obrigatória** | `ReservaMesaController` (wrapper) |
| `/kanban/tarefas` | POST | Opcional | `KanbanTaskController` (wrapper) |

### 3.3 Endpoints — Fase 3 (módulos expandidos, somente leitura)

| Endpoint | Método | Reutiliza tool SAS IA |
|----------|--------|----------------------|
| `/financeiro/resumo` | GET | `consultar_resumo_financeiro` |
| `/financeiro/boletos` | GET | `consultar_boletos_resumo` |
| `/financeiro/fechamentos` | GET | `consultar_fechamentos_recentes` |
| `/rh/funcionarios` | GET | `consultar_funcionarios_resumo` |
| `/rh/vagas` | GET | `consultar_vagas_rh` |
| `/rh/candidatos` | GET | `consultar_candidatos_rh` |
| `/reservas` | GET | `consultar_reservas_periodo` |
| `/energia` | GET | `consultar_energia_resumo` |
| `/patrimonio` | GET | `consultar_patrimonio_resumo` |
| `/investimento` | GET | `consultar_investimento_resumo` |

### 3.4 Endpoints — Fase 4 (chat)

| Endpoint | Método | Descrição |
|----------|--------|-----------|
| `/chat` | POST | Mensagem + histórico → resposta (tool loop) |
| `/conversas` | GET | Listar conversas do solicitante |
| `/conversas/{id}` | GET | Histórico de uma conversa |

### 3.5 Endpoints que **NÃO** serão criados

| Recurso | Motivo |
|---------|--------|
| `/financeiro` (escrita) | Risco alto — valores, baixas, DRE |
| `DELETE` em qualquer recurso | Bloqueio explícito |
| `/usuarios` (escrita) | Permissões sensíveis |
| `/admin/*` | Backup/restore destrutivo |
| `/rh/funcionarios` (escrita) | Dados pessoais sensíveis |
| `/produtos` DELETE | Bloqueado (como OpenClaw) |

---

## 4. Módulos consultáveis pela Ayla

### Fase 1 — Liberados (leitura)

| Módulo | Consultas | Escrita |
|--------|-----------|---------|
| Unidades | ✅ | ❌ |
| Produtos | ✅ | ❌ |
| Estoque | ✅ | ⚠️ perda (fase 2, com confirmação) |
| Lotes | ✅ | ❌ |
| Movimentações | ✅ | ❌ |
| Compras | ✅ | ⚠️ criar lista (fase 2, com confirmação) |
| Fornecedores | ✅ | ❌ |
| Dashboard | ✅ | ❌ |
| Relatórios | ✅ | ❌ |

### Fase 3 — Liberados (leitura expandida)

| Módulo | Consultas | Escrita |
|--------|-----------|---------|
| Financeiro (resumo) | ✅ | ❌ |
| Boletos (resumo) | ✅ | ❌ |
| Fechamento | ✅ | ❌ |
| RH (resumos) | ✅ | ❌ |
| Reservas | ✅ | ⚠️ fase 2 |
| Energia | ✅ | ❌ |
| Patrimônio | ✅ | ❌ |
| Investimento | ✅ | ❌ |
| Alvarás | ✅ | ❌ |
| Proventos (resumo) | ✅ | ❌ |

### Bloqueados permanentemente

| Módulo/Ação | Motivo |
|-------------|--------|
| Exclusão de qualquer registro | Regra de negócio |
| Financeiro (escrita/baixa) | Valores monetários |
| Usuários/permissões | Segurança |
| Backup/restore | Destrutivo |
| RH (alterar funcionário) | LGPD |
| Deploy | Infraestrutura |

---

## 5. Ações e regras de confirmação

### 5.1 Somente leitura (sem confirmação)

Todas as 35 ferramentas do `SasIaToolRegistry` + endpoints REST de consulta.

### 5.2 Escrita com confirmação obrigatória

Padrão em duas etapas (igual OpenClaw):

```json
// Etapa 1 — preview
POST /api/ayla/v1/estoque/perdas
{ "produto_id": 10, "unidade_id": 1, "qtd": 2 }
→ { "success": false, "message": "Confirme...", "data": { "preview": {...}, "requer_confirmacao": true } }

// Etapa 2 — executar
POST /api/ayla/v1/estoque/perdas
{ "produto_id": 10, "unidade_id": 1, "qtd": 2, "confirmacao": true }
→ { "success": true, "message": "Perda registrada.", "data": {...} }
```

| Ação | Confirmação | Fase |
|------|-------------|------|
| Lançar perda de estoque | ✅ Obrigatória | 2 |
| Cadastrar lista de compra | ✅ Obrigatória | 2 |
| Criar reserva de mesa | ✅ Obrigatória | 2 |
| Criar tarefa kanban | ⚠️ Recomendada | 2 |
| Entrada de estoque | ✅ Obrigatória | 3+ |
| Saída de estoque (consumo) | ✅ Obrigatória | 3+ |
| Cancelar reserva | ✅ Obrigatória | 3+ |
| Cancelar compra | ✅ Obrigatória | 3+ |

---

## 6. Endpoints existentes reutilizáveis

### 6.1 Reutilizar via Service (recomendado)

| Service/Método | Uso Ayla |
|----------------|----------|
| `SasIaToolService::executar()` | Todas as consultas read-only |
| `SasIaContext` | Permissões + escopo unidade |
| `AiAssistantService` | Perda, compra, relatório unidade |
| `SasIaChatService::processar()` | Chat com tool-calling (fase 4) |
| `OpenAiService` | Chamadas LLM (se chat) |
| `AiAgentResolver` | Persona Ayla |

### 6.2 Reutilizar via proxy HTTP (não recomendado)

Evitar chamar `/api/produtos` etc. internamente — bypass de permissões e logs inconsistentes.

### 6.3 Não reutilizar diretamente

| Endpoint existente | Motivo |
|-------------------|--------|
| `/api/ia/*` (OpenClaw) | Namespace colide; será substituído por `/ayla/v1` |
| `/api/sas-ia/chat` | Chat interno com auth diferente |
| `/api/ia/chat` (legado) | Sem tools, obsoleto |
| Rotas `api.php` closures | Sem padronização de resposta |

---

## 7. Endpoints novos a criar

### Fase 1 — Infraestrutura + leitura

| Arquivo | Descrição |
|---------|-----------|
| `routes/ayla_routes.php` | Definição de rotas |
| `app/Http/Middleware/CheckAylaToken.php` | Auth Bearer |
| `app/Http/Controllers/Api/AylaController.php` | Endpoints REST |
| `app/Http/Controllers/AylaConfigController.php` | Painel config (ADMIN) |
| `app/Services/AylaApiService.php` | Orquestração (delega para SAS IA tools) |
| `app/Support/Ayla/AylaSettings.php` | Config key-value |
| `app/Support/Ayla/AylaResponse.php` | Envelope JSON padrão |
| `app/Models/AylaAuditLog.php` | Logs (ou estender `ai_assistant_logs`) |
| `config/ayla.php` | Env vars |
| Migration | `ayla_audit_logs` ou colunas em `ai_assistant_logs` |
| `frontend/ayla-integracao.js` | Tela config (opcional, fase 1) |

### Fase 2 — Escrita controlada

| Arquivo | Descrição |
|---------|-----------|
| `app/Services/AylaActionService.php` | Ações com confirmação |
| Endpoints POST em `AylaController` | perdas, compras |

### Fase 3 — Módulos expandidos

| Arquivo | Descrição |
|---------|-----------|
| Sub-controllers ou métodos | financeiro, rh, reservas (read-only) |

### Fase 4 — Chat

| Arquivo | Descrição |
|---------|-----------|
| `app/Services/AylaChatService.php` | Wrapper sobre `SasIaChatService` |
| `app/Support/Ayla/AylaToolRegistry.php` | Extensão ou alias de `SasIaToolRegistry` |

---

## 8. Segurança

### 8.1 Autenticação

| Mecanismo | Detalhe |
|-----------|---------|
| Token Bearer | Header `Authorization: Bearer <AYLA_SAS_TOKEN>` |
| Env | `AYLA_SAS_TOKEN` no `.env` |
| DB fallback | `sistema_configuracoes.ayla_sas_token` (gerado no painel) |
| Middleware | `ayla.token` → `CheckAylaToken` |
| Identificação solicitante | Header opcional `X-Ayla-User-Id` ou `X-Usuario-Id` para escopo de permissões |

### 8.2 Rate limit

| Regra | Valor sugerido |
|-------|----------------|
| Consultas | 60 req/min por token |
| Escritas | 10 req/min por token |
| Chat | 20 msg/min por token |
| Implementação | Laravel `RateLimiter` no middleware |

### 8.3 Logs de auditoria

Cada chamada registra em `ayla_audit_logs` (ou `ai_assistant_logs` com `origem=ayla`):

| Campo | Descrição |
|-------|-----------|
| `id` | PK |
| `user_id` | Solicitante (nullable) |
| `origem` | `ayla` |
| `ip` | IP do request |
| `comando` | `GET /ayla/v1/estoque` |
| `acao` | `estoque.consultar` |
| `payload` | JSON request (sanitizado) |
| `resposta` | JSON response (resumido) |
| `status` | `ok`, `erro`, `bloqueado`, `pendente` |
| `created_at` | Timestamp |

### 8.4 Resposta padronizada

```json
{
  "success": true,
  "message": "Texto curto para WhatsApp/voz",
  "data": {},
  "meta": {
    "acao": "estoque.consultar",
    "timestamp": "2026-07-12T15:00:00-03:00",
    "requer_confirmacao": false
  }
}
```

Erros (sem expor stack trace):

```json
{
  "success": false,
  "message": "Unidade não permitida.",
  "data": {},
  "meta": { "code": "FORBIDDEN_UNIT" }
}
```

### 8.5 Separação leitura/escrita

| Camada | Leitura | Escrita |
|--------|---------|---------|
| Rotas | `GET /ayla/v1/*` | `POST /ayla/v1/*` |
| Middleware | `ayla.token` | `ayla.token` + `ayla.write` |
| Config | `ayla_acoes_leitura` | `ayla_acoes_escrita` (allow-list) |
| Logs | status `ok` | status `ok` ou `pendente` |

### 8.6 Ações sensíveis — matriz de bloqueio

| Ação | Leitura | Escrita | Confirmação | Bloqueio |
|------|---------|---------|-------------|----------|
| Consultar estoque | ✅ | — | — | — |
| Lançar perda | — | ⚠️ Fase 2 | ✅ | — |
| Excluir produto | — | ❌ | — | **Bloqueado** |
| Baixar boleto | — | ❌ | — | **Bloqueado** |
| Alterar financeiro | — | ❌ | — | **Bloqueado** |
| Alterar permissões | — | ❌ | — | **Bloqueado** |
| Cancelar compra | — | ⚠️ Fase 3 | ✅ | — |
| Alterar funcionário | — | ❌ | — | **Bloqueado** |
| Backup/restore | — | ❌ | — | **Bloqueado** |

---

## 9. Permissões específicas Ayla

### 9.1 Configuração (`sistema_configuracoes`)

| Chave | Descrição |
|-------|-----------|
| `ayla_ativo` | Liga/desliga integração |
| `ayla_sas_token` | Token Bearer |
| `ayla_url` | URL do gateway Ayla (VPS) |
| `ayla_unidades_permitidas` | JSON array de IDs |
| `ayla_acoes_leitura` | JSON array de ações GET |
| `ayla_acoes_escrita` | JSON array de ações POST |
| `ayla_rate_limit` | Req/min |

### 9.2 Menu frontend

| Chave | Label |
|-------|-------|
| `aylaIntegracao` | Configurações → Integrações → Ayla |

### 9.3 Escopo de permissões do solicitante

Quando `X-Usuario-Id` é enviado:
- Validar `usuarios.ativo=1`
- Aplicar `permissoes_menu` via `SasIaContext`
- Filtrar tools/endpoints por módulo permitido

Quando apenas token Bearer (sem user):
- Aplicar allow-list de `ayla_acoes_*` e `ayla_unidades_permitidas`
- **Somente leitura** por padrão

---

## 10. Riscos de segurança

| # | Risco | Severidade | Mitigação |
|---|-------|------------|-----------|
| 1 | Token vazado | **Alta** | Rotação, env + DB, mascaramento no painel |
| 2 | Escrita não autorizada em estoque | **Alta** | Confirmação 2 etapas + allow-list |
| 3 | Bypass de permissões via token sem user | **Média** | Leitura only sem user; escrita exige `X-Usuario-Id` |
| 4 | Colisão `/ia/*` com OpenClaw | **Média** | Prefixo exclusivo `/ayla/v1` |
| 5 | Duplicação de lógica (3 IAs) | **Média** | Delegar para `SasIaToolService` |
| 6 | Sem rate limit | **Média** | Middleware `RateLimiter` |
| 7 | Logs com dados sensíveis | **Média** | Sanitizar payload (sem senhas/tokens) |
| 8 | Exposição de stack trace | **Baixa** | `APP_DEBUG=false` + handler genérico |
| 9 | Financeiro via consulta indireta | **Baixa** | Fase 3 com permissão explícita |
| 10 | LGPD em dados RH | **Alta** | RH somente leitura resumida; sem CPF completo na API |

---

## 11. Plano de execução por etapas

### Etapa 0 — Aprovação (atual)
- [x] Levantamento (`AYLA_INVENTARIO_SISTEMA.md`)
- [x] Plano (`AYLA_PLANO_API.md`)
- [ ] Aprovação do cliente

### Etapa 1 — Infraestrutura (estimativa: 1 sprint)
- [ ] `config/ayla.php` + `.env.example`
- [ ] Migration `ayla_audit_logs`
- [ ] `CheckAylaToken` middleware
- [ ] `AylaSettings` + painel config ADMIN
- [ ] `AylaResponse` envelope
- [ ] `routes/ayla_routes.php` + require em `api.php`
- [ ] `GET /ayla/v1/status`

### Etapa 2 — Leitura estoque (estimativa: 1 sprint)
- [ ] `AylaApiService` delegando para `SasIaToolService`
- [ ] Endpoints: unidades, produtos, estoque, lotes, movimentações, compras, fornecedores
- [ ] `GET /ayla/v1/dashboard`
- [ ] `GET /ayla/v1/relatorios/unidade/{id}`
- [ ] Logs em toda chamada
- [ ] Rate limit
- [ ] Testes manuais + botão "Testar conexão" no painel

### Etapa 3 — Escrita controlada (estimativa: 0.5 sprint)
- [ ] `AylaActionService` com confirmação
- [ ] `POST /ayla/v1/estoque/perdas`
- [ ] `POST /ayla/v1/compras`
- [ ] Escrita exige `X-Usuario-Id`

### Etapa 4 — Leitura expandida (estimativa: 1 sprint)
- [ ] Endpoints financeiro, RH, reservas, energia, patrimônio, investimento
- [ ] Permissões por módulo via `SasIaContext`

### Etapa 5 — Chat Ayla (estimativa: 1 sprint)
- [ ] `POST /ayla/v1/chat`
- [ ] Módulo `ayla` em `AiAgentResolver`
- [ ] Persona Ayla em `ai_agents`
- [ ] Conversas em `ai_conversations`

### Etapa 6 — Deprecar OpenClaw (estimativa: 0.5 sprint)
- [ ] Migrar consumidores de `/ia/*` para `/ayla/v1/*`
- [ ] Manter `/openclaw/config` como alias ou redirecionar para Ayla
- [ ] Documentar breaking changes

---

## 12. Arquivos que seriam alterados

### Novos (criar)

```
backend/config/ayla.php
backend/routes/ayla_routes.php
backend/app/Http/Middleware/CheckAylaToken.php
backend/app/Http/Controllers/Api/AylaController.php
backend/app/Http/Controllers/AylaConfigController.php
backend/app/Services/AylaApiService.php
backend/app/Services/AylaActionService.php        (fase 3)
backend/app/Services/AylaChatService.php           (fase 5)
backend/app/Support/Ayla/AylaSettings.php
backend/app/Support/Ayla/AylaResponse.php
backend/app/Models/AylaAuditLog.php
backend/database/migrations/xxxx_create_ayla_audit_logs_table.php
frontend/ayla-integracao.js                        (opcional)
openclaw/skill-sas-estoque/SKILL.md               (atualizar URLs)
```

### Existentes (alterar)

```
backend/routes/api.php                             (+ require ayla_routes.php)
backend/bootstrap/app.php                          (+ alias ayla.token)
backend/.env.example                               (+ AYLA_SAS_TOKEN)
frontend/index.html                                (+ menu Integrações → Ayla)
frontend/app.js                                  (+ permissões, navigateTo)
frontend/style-configuracoes.css                   (+ estilos painel)
backend/app/Support/AiAgentResolver.php            (+ módulo 'ayla')
```

### Existentes (reutilizar sem alterar)

```
backend/app/Services/SasIaToolService.php
backend/app/Services/SasIaModuleQueryService.php
backend/app/Support/SasIa/SasIaToolRegistry.php
backend/app/Support/SasIa/SasIaContext.php
backend/app/Services/AiAssistantService.php
backend/app/Services/SasIaChatService.php
backend/app/Services/OpenAiService.php
```

### Não alterar (manter paralelo até deprecação)

```
backend/routes/ai_assistant_routes.php             (OpenClaw)
backend/routes/openclaw_config_routes.php
backend/routes/sas_ia_routes.php
backend/routes/ia_routes.php
```

---

## 13. Comparativo OpenClaw vs Ayla proposto

| Aspecto | OpenClaw (atual) | Ayla (proposto) |
|---------|------------------|-----------------|
| Prefixo | `/api/ia/*` | `/api/ayla/v1/*` |
| Auth | Bearer token | Bearer token + opcional `X-Usuario-Id` |
| Escopo | Só estoque | Estoque → expandido |
| Consultas | 4 endpoints | 35+ tools via REST |
| Escrita | 2 ações | 2+ com confirmação |
| Permissões | Allow-list fixa | `permissoes_menu` + allow-list |
| Rate limit | ❌ | ✅ |
| Logs | `ai_assistant_logs` | `ayla_audit_logs` |
| Chat | ❌ (só REST) | ✅ (fase 5) |
| Painel config | ✅ | ✅ (novo) |

---

## 14. Recomendação final

1. **Aprovar** este plano
2. **Implementar Etapas 1–2** (infra + leitura estoque) — entrega rápida de valor
3. **Migrar OpenClaw** para consumir `/ayla/v1` na Etapa 6
4. **Não duplicar** — Ayla é a evolução unificada; OpenClaw vira consumidor
5. **Manter SAS IA** para chat interno no painel web (público diferente)

---

*Aguardando aprovação para iniciar implementação.*
