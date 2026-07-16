# Ayla Convite Telegram — Deploy VPS

## Decisão: bot único

O **mesmo bot** do OpenClaw recebe webhooks via `telegram-auth-bridge`. O bridge intercepta `/start TOKEN` antes da autorização normal. Não criar segundo bot.

## Diretórios

```
/opt/ayla-telegram-bridge/
  bin/sync-allowlist.sh
  sync-server.js
  backups/
  .env

/opt/telegram-auth-bridge/   (ou caminho atual)
  server.js
  .env
```

Copiar do repositório:

- `openclaw/ayla-telegram-bridge/` → `/opt/ayla-telegram-bridge/`
- Atualizar `openclaw/telegram-auth-bridge/server.js` no serviço existente

## Variáveis VPS

### Bridge Telegram (`telegram-auth-bridge/.env`)

```env
TELEGRAM_BOT_TOKEN=<token BotFather>
SAS_AYLA_URL=https://api.gruposaborparaense.com.br/api/ayla/v1/acesso/validar
SAS_TOKEN=<AYLA_SAS_TOKEN>
AYLA_BRIDGE_TOKEN=<igual ao Napoleon>
OPENCLAW_WEBHOOK_URL=http://127.0.0.1:8787/telegram-webhook
TELEGRAM_WEBHOOK_SECRET=<secret>
```

### Sync allowlist (`/opt/ayla-telegram-bridge/.env`)

```env
AYLA_VPS_SYNC_TOKEN=<igual ao Napoleon AYLA_VPS_SYNC_TOKEN>
SYNC_HOST=127.0.0.1
SYNC_PORT=8091
SYNC_SCRIPT=/opt/ayla-telegram-bridge/bin/sync-allowlist.sh
OPENCLAW_CONFIG=/home/USUARIO/.openclaw/openclaw.json
```

## Comandos exatos

```bash
# Allowlist script
chmod +x /opt/ayla-telegram-bridge/bin/sync-allowlist.sh

# Teste manual allowlist
/opt/ayla-telegram-bridge/bin/sync-allowlist.sh adicionar 5431293656

# Sync API (systemd exemplo)
node /opt/ayla-telegram-bridge/sync-server.js

# Reiniciar bridge Telegram
systemctl --user restart ayla-telegram-auth
```

## Serviço systemd (sync API)

Criar `/etc/systemd/system/ayla-allowlist-sync.service`:

```ini
[Unit]
Description=Ayla OpenClaw Allowlist Sync API
After=network.target

[Service]
Type=simple
User=openclaw
WorkingDirectory=/opt/ayla-telegram-bridge
EnvironmentFile=/opt/ayla-telegram-bridge/.env
ExecStart=/usr/bin/node /opt/ayla-telegram-bridge/sync-server.js
Restart=on-failure

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now ayla-allowlist-sync
```

Expor `8091` apenas em `127.0.0.1` ou via túnel seguro para o Napoleon.

## Teste allowlist

```bash
curl -s -X POST "http://127.0.0.1:8091/internal/allowlist/adicionar" \
  -H "Authorization: Bearer SEU_AYLA_VPS_SYNC_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"telegram_user_id":"5431293656"}'
```

## Teste Telegram

1. Admin gera convite no SAS
2. Abrir link no celular → Iniciar
3. Bridge registra `/start TOKEN` e chama Napoleon
4. Usuário recebe mensagem de boas-vindas
5. Enviar mensagem à Ayla → deve responder

## Rollback

1. Restaurar backup em `/opt/ayla-telegram-bridge/backups/`
2. `openclaw config validate`
3. `systemctl --user restart openclaw-gateway`
4. Reverter `server.js` do bridge para versão anterior se necessário

## Segurança

- `sync-allowlist.sh` aceita apenas IDs numéricos
- Lock de arquivo evita concorrência
- Backup automático antes de alterar `openclaw.json`
- Nunca substitui allowlist por lista vazia em remoção isolada
- Tokens nunca vão para logs
