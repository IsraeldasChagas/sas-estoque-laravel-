# Ayla — Bridge de autenticação do Telegram

Substitui o **pairing manual** do OpenClaw por **autenticação externa via API SAS-Estoque**.
Ao receber a primeira mensagem de um usuário no Telegram, o bridge captura os dados,
valida no painel Ayla e só então cria a sessão — sem Pairing Code e sem
`openclaw pairing approve`.

## Por que um bridge?

O OpenClaw controla o acesso a DMs de forma **estática** (`dmPolicy` + `allowFrom`)
e não chama um endpoint externo por mensagem. Para autenticar dinamicamente via a
API SAS, colocamos este bridge **na frente** do webhook do OpenClaw:

```
Telegram
   ↓  (webhook público)
Bridge  ── POST /api/ayla/v1/acesso/validar (Bearer SAS_TOKEN)
   ↓  autorizado?
   ├─ sim → encaminha o update ao webhook local do OpenClaw  → sessão criada
   └─ não → responde "acesso não autorizado" e descarta
```

O OpenClaw fica em `dmPolicy: "open"` escutando **apenas em 127.0.0.1**; como só o
bridge (o gatekeeper) alcança o webhook local, o acesso continua protegido.

## O que o bridge faz

1. Captura automaticamente `telegram_user_id`, `username`, `first_name`, `last_name`.
2. `POST` para `SAS_AYLA_URL` com header `Authorization: Bearer ${SAS_TOKEN}`:
   ```json
   { "telegram_user_id": "...", "telegram_username": "...", "telegram_nome": "..." }
   ```
3. **Autorizado** (`data.autorizado === true`): encaminha o update ao OpenClaw e
   registra `Telegram autorizado via SAS.`
4. **Não autorizado**: responde ao usuário e registra `Telegram recusado via SAS.`:
   > Olá! Seu acesso ainda não foi autorizado pelo administrador do Grupo Sabor Paraense.
   >
   > Solicite sua liberação no painel Ayla IA.

Segurança: falha de rede/timeout **nega** o acesso (fail-closed); valida o
`X-Telegram-Bot-Api-Secret-Token`; token nunca é logado.

## Requisitos

- Node.js >= 18 (usa `fetch` nativo, sem dependências).
- Bot do Telegram (BotFather).
- Token da Ayla gerado em **SAS-Estoque → Ayla IA → Configurações → Token**.

## Instalação na VPS

```bash
sudo mkdir -p /opt/ayla-telegram-auth-bridge
sudo cp server.js package.json /opt/ayla-telegram-auth-bridge/
sudo cp .env.example /opt/ayla-telegram-auth-bridge/.env
sudo nano /opt/ayla-telegram-auth-bridge/.env   # preencha os valores
```

Preencha o `.env` (veja `.env.example`): `TELEGRAM_BOT_TOKEN`, `SAS_TOKEN`,
`TELEGRAM_WEBHOOK_SECRET`, e confirme `OPENCLAW_WEBHOOK_URL`.

### Rodar como serviço (systemd)

```bash
sudo cp ayla-telegram-auth.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now ayla-telegram-auth
journalctl -u ayla-telegram-auth -f
```

## Configurar o OpenClaw (webhook mode, sem pairing)

No `openclaw.json` (ou `.env`) do gateway:

```json5
{
  channels: {
    telegram: {
      enabled: true,
      botToken: "SEU_BOT_TOKEN",
      dmPolicy: "open",          // sem pairing; o bridge é quem autoriza
      allowFrom: ["*"],          // seguro: webhook só escuta em 127.0.0.1
      webhookUrl: "https://api.gruposaborparaense.com.br/telegram-webhook",
      webhookSecret: "MESMO_SEGREDO_DO_BRIDGE",
      webhookHost: "127.0.0.1",
      webhookPort: 8787,
      webhookPath: "/telegram-webhook"
    }
  }
}
```

Reinicie o gateway: `openclaw gateway restart`.

> `webhookUrl` acima é o endereço **público** que o Telegram chama — que deve
> apontar (via reverse proxy) para **este bridge**, não diretamente para o OpenClaw.

## Reverse proxy (nginx) e webhook do Telegram

Aponte o caminho público para o bridge:

```nginx
location /telegram-webhook {
    proxy_pass http://127.0.0.1:8080/telegram-webhook;
    proxy_set_header X-Telegram-Bot-Api-Secret-Token $http_x_telegram_bot_api_secret_token;
}
```

Registre o webhook no Telegram (uma vez):

```bash
curl -s "https://api.telegram.org/bot${TELEGRAM_BOT_TOKEN}/setWebhook" \
  -d "url=https://api.gruposaborparaense.com.br/telegram-webhook" \
  -d "secret_token=${TELEGRAM_WEBHOOK_SECRET}"
```

## Teste

1. `curl http://127.0.0.1:8080/health` → `{"ok":true}`.
2. Envie uma DM ao bot com um Telegram ID **não** cadastrado → recebe a mensagem de acesso negado; log `Telegram recusado via SAS.`.
3. Cadastre/ative o usuário em **Ayla IA → Usuários autorizados** com esse Telegram ID.
4. Envie outra DM (após o TTL do cache, padrão 5 min) → o bot responde normalmente; log `Telegram autorizado via SAS.`.

## Observações

- O acesso é sempre vinculado a um usuário SAS ativo (o Telegram ID sozinho não concede acesso).
- Revogar/bloquear no painel passa a valer após o `AUTH_CACHE_TTL_MS` (padrão 5 min).
- A API Ayla continua **somente leitura** nesta versão.
