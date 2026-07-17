# Fluxos — Integrações, Telegram, Ayla, OpenClaw e MCP

Data: 2026-07-16

## 1. Fluxo administrativo completo

```text
Administrador
  → login no SAS
  → frontend conserva currentUser/token
  → abre Ayla IA → Usuários autorizados
  → frontend GET /api/ayla-admin/opcoes
  → frontend GET /api/ayla-admin/usuarios
  → backend identifica ADMIN por X-Usuario-Id
  → administrador seleciona usuário SAS
  → define telefone, unidades, módulos e capacidades
  → POST /api/ayla-admin/usuarios
  → AylaUsuarioController
  → AylaUsuarioAutorizado
  → ayla_usuarios_autorizados
```

Observação: o Bearer do login não é relacionado ao `X-Usuario-Id`; a autenticação administrativa é incompleta.

## 2. Fluxo de convite Telegram

```text
Administrador
  → "Gerar convite"
  → POST /api/ayla-admin/usuarios/{id}/convite
  → AylaConviteController::gerar
  → AylaConviteService::gerar
      → valida telefone
      → cancela convites pendentes anteriores
      → random_bytes(32)
      → token em hex
      → HMAC SHA-256 com APP_KEY
      → grava somente token_hash
      → expiração padrão 24h
      → monta https://t.me/{BOT}?start={TOKEN}
  → resposta ao frontend
      → link
      → telefone mascarado
      → expiração
      → mensagem WhatsApp
      → wa.me
```

O token real só existe na resposta de geração. O banco contém hash.

## 3. Fluxo `/start TOKEN`

```text
Usuário toca no deep link
  → Telegram abre o bot
  → usuário toca em Iniciar
  → Telegram envia update ao webhook do bridge
  → telegram-auth-bridge extrai:
      from.id
      username
      nome
      chat_id
      texto
  → regex reconhece /start TOKEN
  → POST /api/ayla/v1/telegram/vincular
      Authorization: Bearer AYLA_BRIDGE_TOKEN
      convite_token
      telegram_user_id
      username
      nome
  → CheckAylaBridgeToken
  → AylaController::vincularTelegram
  → AylaConviteService::vincularPorToken
```

### Validações no vínculo

1. token presente;
2. Telegram ID numérico;
3. hash correspondente;
4. convite existe;
5. não usado;
6. não cancelado;
7. não expirado;
8. Telegram ID não está em outro acesso ativo;
9. acesso vinculado existe.

### Persistência

```text
ayla_usuarios_autorizados:
  telegram_user_id
  telegram_username
  telegram_nome
  telegram_vinculado_em
  status = ativo

ayla_convites:
  status = usado
  usado_em
  identidade Telegram capturada
```

## 4. Fluxo de sincronização da allowlist

```text
AylaConviteService
  → AylaTelegramSyncService::adicionarAllowlist
  → POST {AYLA_VPS_SYNC_URL}/internal/allowlist/adicionar
      Bearer AYLA_VPS_SYNC_TOKEN
      telegram_user_id
  → sync-server.js
      valida token
      aplica rate limit
      valida ID
      executa sync-allowlist.sh adicionar ID
  → script:
      flock
      backup openclaw.json
      lê allowFrom
      adiciona sem duplicar
      força dmPolicy=allowlist
      openclaw config validate
      tenta restart openclaw-gateway
      restaura backup se falhar
```

Resultado é salvo em:

- `telegram_sync_status`;
- `telegram_sync_erro`;
- `telegram_sincronizado_em`.

## 5. Fluxo de mensagem normal

```text
Usuário Telegram
  → Telegram Bot API
  → webhook público do telegram-auth-bridge
  → extrai Telegram User ID
  → cache de autorização?
      SIM: usa booleano por até 5 minutos
      NÃO:
        POST /api/ayla/v1/acesso/validar
        Bearer AYLA_SAS_TOKEN
        telegram_user_id/username/nome
  → AylaAccessService
      encontra vínculo
      exige status ativo
      exige usuário SAS ativo
      calcula módulos/unidades/capacidades
  → bridge reduz resposta a true/false
  → se negado: envia mensagem de acesso negado
  → se autorizado: encaminha update bruto ao webhook local OpenClaw
```

## 6. Fluxo OpenClaw → ferramenta

### Caminho Ayla HTTP/MCP

```text
OpenClaw
  → carrega skill-ayla
  → interpreta intenção
  → escolhe ferramenta
  → chama servidor MCP externo OU endpoint HTTP
  → Bearer AYLA_SAS_TOKEN
  → /api/ayla/v1/*
  → CheckAylaToken
  → AylaController
  → AylaApiService
  → SasIaToolService / SasIaModuleQueryService
     ou AylaKanbanService
     ou AylaPatrimonioService
     ou AylaReservasService
  → Query Builder/Eloquent
  → banco
  → AylaResponse JSON
  → MCP/OpenClaw
  → modelo gera texto
  → Telegram
```

### Quebra de contexto

O bridge conhece `usuario_id`, módulos e unidades após `/acesso/validar`, mas não encaminha esses valores ao OpenClaw/MCP. Os snippets MCP enviam apenas o Bearer compartilhado.

Sem `X-Usuario-Id`, `AylaApiService` pode construir contexto ADMIN sintético. Assim, a autorização calculada no início não necessariamente limita a consulta final.

## 7. Fluxo de consulta

Exemplo estoque:

```text
Telegram: "Como está o estoque da unidade X?"
  → bridge autoriza
  → OpenClaw escolhe endpoint/tool
  → GET /api/ayla/v1/estoque?... 
  → AylaController valida filtros
  → AylaApiService::executarFerramenta
  → SasIaToolService
  → SasIaModuleQueryService
  → tabelas de estoque/produtos/unidades
  → JSON estruturado
  → OpenClaw resume em linguagem natural
  → Telegram
```

## 8. Fluxo de ação controlada em reservas

```text
Usuário solicita alteração
  → OpenClaw chama POST /reservas/acoes/preparar
  → AylaWriteGuard
      verifica read-only
      usuário/contexto
      módulo/menu
      permissão de escrita
  → AylaAcaoPendenteService::preparar
  → grava ayla_acoes_pendentes (pendente, expira em 10 min)
  → resposta com preview
  → usuário confirma
  → POST /reservas/acoes/{id}/confirmar
  → verifica dono/status/expiração
  → AylaReservasService executa
  → ação = executada ou erro
  → resposta ao OpenClaw/Telegram
```

Não existe lock/transação envolvendo verificação e execução; confirmações concorrentes são risco.

## 9. Bloqueio, reativação, revogação e desvinculação

### Bloquear

```text
PATCH /ayla-admin/usuarios/{id}/status {bloqueado}
  → mantém Telegram ID
  → remove allowlist
  → acesso passa a ser negado
```

O cache do bridge pode manter autorização por até cinco minutos.

### Reativar

```text
PATCH ... {ativo}
  → mantém vínculo
  → adiciona allowlist
```

### Revogar

```text
DELETE /ayla-admin/usuarios/{id}
  → status revogado
  → preserva registro/auditoria
  → remove allowlist
```

### Desvincular

```text
POST /usuarios/{id}/telegram/desvincular
  → remove allowlist
  → limpa Telegram ID/username/nome/datas/sync
  → status pendente
  → permite novo convite
```

## 10. Fluxo da integração VendaFácil

### Salvar configuração

```text
Admin → UI Integrações → VendaFácil
  → PUT /api/integracoes/vendafacil
  → VendaFacilController
  → valida URL/timeout/retries
  → IntegrationManager::saveConfiguration
  → Integration model
  → token/secret criptografados
  → tabela integrations
```

### Testar

```text
Admin → Testar conexão
  → POST /api/integracoes/vendafacil/testar
  → IntegrationManager
  → VendaFacilProvider
  → HttpIntegrationClient
  → GET {base}/api/v1/integration/status
      Bearer token
  → valida empresa/ambiente/versão
  → grava integration_logs
  → atualiza integrations
```

### Health

```text
UI → GET /api/integracoes/health-check?live=1
  → HealthCheckController
  → IntegrationManager
  → VendaFacilProvider::healthCheck
  → chamada HTTP
```

O health não persiste resultado e o cache escrito não é lido.

### Unidades

```text
UI → PUT /vendafacil/unidades
  → IntegrationManager::saveUnitMappings
  → integration_mappings
  → integrations.unidade_mappings
```

Não há sincronização de unidades nem consumo comercial do mapping.

## 11. Stack OpenClaw legado

Fluxo independente:

```text
OpenClaw/skill-sas-estoque
  → /api/ia/*
  → CheckOpenClawToken
  → AiAssistantController
  → AiAssistantService
  → banco
  → ai_assistant_logs
```

Esse caminho pode executar operações com modelo de confirmação próprio e não usa `ayla_acoes_pendentes`.

## 12. Logs por etapa

```text
Frontend            → console do navegador
Integrações         → integration_logs
Ayla API/admin      → ayla_audit_logs
Ações Ayla          → ayla_acoes_pendentes
Laravel             → storage/logs/laravel.log
OpenClaw legado     → ai_assistant_logs
Bridge Telegram     → stdout/journald
Sync allowlist      → stdout/journald + backups
OpenClaw runtime    → VPS, fora do repo
MCP                 → não definido no repo
```

## 13. Fluxos incompletos

1. Identidade Telegram não chega às ferramentas.
2. Runtime MCP não está no repositório.
3. Manifesto MCP não cobre toda API.
4. OpenClaw pode disputar o webhook.
5. Bridge e allowlist aplicam modelos conflitantes.
6. Não existe reconciliação completa de allowlist.
7. Configuração de Telegram/áudio no painel não controla bridge.
8. VendaFácil não sincroniza domínios comerciais.
9. Não há filas/retry durável.
10. Não há retenção/limpeza agendada.

