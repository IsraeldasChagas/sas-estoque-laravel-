# Integrações — Arquitetura do SAS-Estoque

**Atualizado:** 2026-07-16  
**Fase 1:** Infraestrutura (concluída)  
**Fase 2:** Conexão real VendaFácil (concluída) — ver `docs/INTEGRACAO_VENDAFACIL_FASE_2.md`

---

## Padrão obrigatório

```
IntegrationManager
        ↓
IntegrationProviderInterface
        ↓
Provider específico (ex.: VendaFacilProvider)
        ↓
HttpIntegrationClient
```

**Nunca** chamar `HttpIntegrationClient` diretamente a partir de controllers de domínio.

---

## Menu (frontend)

| Submenu | Section ID | Status |
|---|---|---|
| VendaFácil | `integracaoVendafacil` | Fase 2 — conexão real |
| Aplicações Conectadas | `integracaoAplicacoes` | Ativo |
| Health Check | `integracaoHealthCheck` | Fase 2 — consulta ao vivo |
| Logs | `integracaoLogs` | Fase 2 — filtros |
| Webhooks | `integracaoWebhooks` | Placeholder |
| Tokens | `integracaoTokens` | Placeholder |
| Configurações | `integracaoConfiguracoes` | Placeholder |

Arquivos: `frontend/integracoes.js`, `frontend/style-integracoes.css`, `frontend/app.js`, `frontend/index.html`.

---

## Backend

### Migrations

| Arquivo | Conteúdo |
|---|---|
| `2026_07_16_000001_create_integrations_tables.php` | Tabelas base |
| `2026_07_16_000002_extend_integrations_phase2.php` | `integration_status`, `empresa_external_name`, `observacoes`, campos de log |

### Rotas VendaFácil (Fase 2)

| Método | Rota | Ação |
|---|---|---|
| GET | `/api/integracoes/vendafacil` | Configuração mascarada |
| PUT | `/api/integracoes/vendafacil` | Salvar configuração |
| POST | `/api/integracoes/vendafacil/testar` | Testar conexão |
| GET | `/api/integracoes/vendafacil/health` | Health do provider |
| POST | `/api/integracoes/vendafacil/desconectar` | Desconectar |
| GET | `/api/integracoes/vendafacil/logs` | Logs do provider |
| GET/PUT | `/api/integracoes/vendafacil/unidades` | Mapeamento manual |

Demais rotas: `backend/routes/integrations_routes.php`.

### Status padronizados

`not_configured`, `configured`, `connected`, `degraded`, `disconnected`, `authentication_error`, `connection_error`, `disabled`

Classe: `app/Support/Integrations/IntegrationStatus.php`.

### Segurança

| Item | Implementação |
|---|---|
| Tokens criptografados | Cast `encrypted` em `Integration` |
| Retorno mascarado | `Integration::paraPainel()` |
| Token mascarado não sobrescreve | `HttpIntegrationClient::isMaskedSecret()` |
| SSRF | `IntegrationUrlValidator` + `config/integrations.php` |
| Logs sanitizados | `IntegrationLog::sanitizarPayload()` |

Variável de ambiente: `INTEGRATION_SSRF_ALLOW_PRIVATE=true` (somente dev).

### Autenticação das rotas

- Header `X-Usuario-Id`
- **Visualizar:** ADMIN, GERENTE
- **Configurar / testar / desconectar / mapear:** ADMIN

Mapeamento de sections (não há permission strings Laravel separadas):

| Ação | Section / perfil |
|---|---|
| Ver telas | `integracaoVendafacil`, `integracaoHealthCheck`, `integracaoLogs` |
| Configurar VendaFácil | `integracaoVendafacil` + perfil ADMIN no backend |

---

## O que NÃO está implementado (após Fase 2)

- Sincronização de produtos, clientes, pedidos, delivery, vendas, PDV, caixa, fiscal
- Webhooks de negócio
- Baixa automática de estoque
- Scheduler comercial

---

## Comandos

```bash
cd backend
php artisan migrate
php artisan test --filter=VendaFacilIntegrationTest
```

Rollback da migration Fase 2:

```bash
php artisan migrate:rollback --path=database/migrations/2026_07_16_000002_extend_integrations_phase2.php
```

---

## Documentos relacionados

- `docs/INTEGRACAO_VENDAFACIL_FASE_2.md` — detalhes da Fase 2
- `docs/MAPEAMENTO_SAS_ESTOQUE_INTEGRACAO_VENDAFFACIL.md` — análise do domínio
