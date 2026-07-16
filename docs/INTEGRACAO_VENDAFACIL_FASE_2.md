# Integração VendaFácil — Fase 2

**Data:** 2026-07-16  
**Status:** Concluída — conexão real, health check, logs e mapeamento manual de unidades.

---

## Escopo desta fase

### Implementado

- Salvamento real de configuração (URL, token, ambiente, timeout, retries, webhook secret, recursos, observações)
- Criptografia de credenciais (Laravel `encrypted`)
- Teste de autenticação via API remota
- Identificação de empresa, ambiente e versão
- Health check (consulta ao abrir tela ou clicar em Atualizar)
- Logs com filtros
- Tratamento de erros amigáveis
- Mapeamento manual de unidades (`integration_mappings`, `entity_type = unit`)
- Desconexão com confirmação e opção de limpar mapeamentos

### Não implementado (próximas fases)

Produtos, clientes, pedidos, delivery, vendas, PDV, caixa, fiscal, pagamentos, webhooks de negócio, sincronização operacional e baixa de estoque.

---

## Configuração

### Campos na tela Integrações → VendaFácil

| Campo | Regras |
|---|---|
| URL base | Ex.: `https://vendaffacil.com.br/api/v1` — barra final removida automaticamente |
| Bearer Token | Criptografado; mascarado no retorno; não sobrescrito se enviado mascarado |
| Ambiente | `production` ou `homologation` — HTTPS obrigatório em produção |
| Timeout | 3–60 segundos |
| Tentativas | 0–5 (retries em erros 5xx) |
| Webhook secret | Criptografado; não aparece em logs |
| Integração ativa | Deve estar ativa para testar conexão |
| Recursos habilitados | Estrutural apenas (sem sync) |
| Empresa remota | Preenchida automaticamente após teste — **não editável manualmente** |
| Unidades | Mapeamento manual: `external_id`, nome externo, principal, ativo |

### Registro no banco

Tabela genérica `integrations` com `provider = vendafacil` (sem tabela exclusiva).

---

## API remota utilizada

### Teste de conexão / Health

```
GET {api_url}/integration/status
```

Quando `api_url` já termina em `/api/v1` (recomendado), o path relativo é `/integration/status`.

**Headers:**

```
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

**Resposta esperada (flexível):**

```json
{
  "data": {
    "company": { "id": "123", "name": "Minha Empresa" },
    "environment": "homologation",
    "api_version": "1.0.0"
  }
}
```

### Unidades (opcional)

```
GET {api_url}/units
```

Não é obrigatório para concluir a Fase 2. A UI permite cadastro manual do `external_id`.

---

## Fluxo do teste de conexão

1. Usuário ADMIN salva URL + token e ativa a integração.
2. Clica em **Testar conexão**.
3. `VendaFacilController` → `IntegrationManager::testConnection()`.
4. `VendaFacilProvider::testConnection()` → `HttpIntegrationClient::get()`.
5. Resposta validada (empresa identificada, versão, ambiente).
6. Status atualizado (`connected` ou erro específico).
7. Log gravado em `integration_logs` (sem secrets).
8. Cards da tela atualizados com empresa, tempo de resposta e HTTP status.

---

## Status locais

| Código | Label UI |
|---|---|
| `not_configured` | Não configurado |
| `configured` | Configurado |
| `connected` | Online |
| `degraded` | Instável (3+ falhas consecutivas) |
| `disconnected` | Desconectado |
| `authentication_error` | Token inválido |
| `connection_error` | Offline |
| `disabled` | Desativado |

---

## Mensagens de erro

| Situação | Mensagem |
|---|---|
| 401 | Token inválido ou expirado. |
| 403 | O token não possui permissão para esta operação. |
| 404 | O endpoint da API não foi encontrado. Verifique a URL configurada. |
| 408 / timeout | A API demorou mais que o limite configurado. |
| 422 | A configuração enviada é inválida. |
| 429 | Limite de requisições excedido. |
| 5xx | O VendaFácil apresentou uma falha temporária. |
| Connection refused | Não foi possível conectar ao servidor do VendaFácil. |
| DNS | O domínio configurado não pôde ser localizado. |
| SSL | O certificado HTTPS da API não pôde ser validado. |
| JSON inválido | A API respondeu em formato inesperado. |

Implementação: `app/Support/Integrations/IntegrationErrorMapper.php`.

---

## Logs

Registrados em `integration_logs` com: provider, operação, endpoint, método, HTTP status, tempo, usuário, IP, tentativa.

**Nunca registrados:** Authorization, Bearer Token, webhook secret, senhas.

Filtros na tela: data, status, sucesso/falha, operação, provider.

---

## Desconexão

`POST /api/integracoes/vendafacil/desconectar`

- Desativa integração
- Remove `bearer_token` e `webhook_secret`
- Preserva logs
- Preserva mapeamentos (padrão); opcional `clear_mappings: true` remove vínculos de unidades

---

## Segurança

- SSRF: bloqueio de localhost, redes privadas e protocolos não HTTP(S)
- Exceção dev: `INTEGRATION_SSRF_ALLOW_PRIVATE=true` em `.env`
- Tokens nunca retornados completos ao frontend
- Stack traces nunca expostos ao usuário final

---

## Testes automatizados

Arquivo: `backend/tests/Feature/VendaFacilIntegrationTest.php`

Usa `Http::fake()` — não depende da API real.

```bash
cd backend
php artisan test --filter=VendaFacilIntegrationTest
```

Cenários: salvar, token criptografado/mascarado, 200, 401, 404, JSON inválido, offline, desativada, permissão, desconexão, logs sem token, mapeamento de unidade, health check.

---

## Teste manual

1. Executar migrations: `php artisan migrate`
2. Login como ADMIN no SAS
3. Menu **Integrações → VendaFácil**
4. Informar URL base (ex.: `https://vendaffacil.com.br/api/v1`) e Bearer Token válido
5. Marcar **Integração ativa** e **Salvar**
6. **Testar conexão** — verificar empresa, ambiente, versão e tempo
7. Abrir **Health Check** e **Logs**
8. Mapear unidades manualmente e **Salvar unidades**
9. **Desconectar** para validar remoção de credenciais

---

## Limitações conhecidas

- Health check executa chamada HTTP ao vivo (não há loop automático)
- Endpoint de unidades remotas é opcional; mapeamento é manual
- Multiempresa SAS ainda não completa — uma instalação = uma empresa local
- Recursos habilitados são flags estruturais sem efeito comercial
- Sincronizar retorna HTTP 501 (não disponível nesta fase)

---

## Arquivos principais (Fase 2)

| Tipo | Arquivos |
|---|---|
| Migration | `database/migrations/2026_07_16_000002_extend_integrations_phase2.php` |
| Config | `config/integrations.php` |
| Support | `IntegrationStatus`, `IntegrationUrlValidator`, `IntegrationErrorMapper` |
| Services | `HttpIntegrationClient`, `IntegrationManager`, `VendaFacilProvider`, `IntegrationRuntimeConfig` |
| Controllers | `VendaFacilController`, `HealthCheckController`, `IntegrationLogController` |
| Frontend | `frontend/integracoes.js`, `frontend/style-integracoes.css` |
| Testes | `tests/Feature/VendaFacilIntegrationTest.php` |
