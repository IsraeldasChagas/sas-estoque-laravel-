# Ayla — Convite e vínculo Telegram

**Data:** 2026-07-16  
**Status:** Implementado no SAS-Estoque (Parte A)

## Resumo

Fluxo sem Telegram User ID manual: o administrador informa o **telefone**, gera um **link único** (`https://t.me/{bot}?start={TOKEN}`), o usuário toca em **Iniciar** no bot, e o SAS vincula automaticamente o `telegram_user_id` e sincroniza a allowlist na VPS.

## Decisão de arquitetura — bot único

Foi escolhida a **Opção preferencial**: o bridge existente (`openclaw/telegram-auth-bridge`) continua como único consumidor do webhook do Telegram e trata:

1. `/start TOKEN` → vinculação via `POST /api/ayla/v1/telegram/vincular`
2. Demais mensagens → fluxo atual (`acesso/validar` + encaminhamento ao OpenClaw)

Não há segundo bot nem `getUpdates` concorrente.

## Telefone vs Telegram User ID

| Campo | Função |
|---|---|
| `telefone_telegram` | Identificação humana, envio WhatsApp, confirmação do convite |
| `telegram_user_id` | Vínculo real de autorização (preenchido automaticamente no `/start`) |

O telefone **não substitui** o Telegram User ID e **não** conecta o usuário sozinho.

## Componentes

| Camada | Arquivos |
|---|---|
| Migration | `2026_07_16_100001_ayla_convite_telegram.php` |
| Models | `AylaConvite`, `AylaUsuarioAutorizado` (campos novos) |
| Services | `AylaConviteService`, `AylaTelegramSyncService` |
| Controllers | `AylaConviteController`, `AylaController::vincularTelegram` |
| Middleware | `CheckAylaBridgeToken` |
| Frontend | `frontend/ayla-ia.js` |
| Bridge VPS | `openclaw/telegram-auth-bridge/server.js` |
| Allowlist VPS | `openclaw/ayla-telegram-bridge/` |

## Rotas administrativas

| Método | Rota |
|---|---|
| POST | `/api/ayla-admin/usuarios/{id}/convite` |
| POST | `/api/ayla-admin/usuarios/{id}/convite/renovar` |
| DELETE | `/api/ayla-admin/usuarios/{id}/convite` |
| GET | `/api/ayla-admin/usuarios/{id}/convite` |
| POST | `/api/ayla-admin/usuarios/{id}/telegram/sincronizar` |
| POST | `/api/ayla-admin/usuarios/{id}/telegram/desvincular` |

## Rota do bridge

`POST /api/ayla/v1/telegram/vincular` — Bearer `AYLA_BRIDGE_TOKEN`

## Variáveis (.env SAS)

```
AYLA_TELEGRAM_BOT_USERNAME=AylaSaborPsraenseBot
AYLA_BRIDGE_TOKEN=
AYLA_VPS_SYNC_URL=
AYLA_VPS_SYNC_TOKEN=
AYLA_CONVITE_VALIDADE_HORAS=24
```

## Testes

```bash
cd backend
php artisan test --filter=AylaConviteTest
```

## Documentação relacionada

- `docs/AYLA_CONVITE_TELEGRAM_NAPOLEON.md` — deploy no servidor
- `docs/AYLA_CONVITE_TELEGRAM_VPS.md` — bridge e allowlist
- `docs/AYLA_CONVITE_TELEGRAM_FLUXO.puml` — diagrama

## Compatibilidade

Usuários já conectados (Israel, Thiago, Iracema, etc.) **não são alterados** pela migration. A allowlist existente é preservada; apenas adições/remoções pontuais via sync.
