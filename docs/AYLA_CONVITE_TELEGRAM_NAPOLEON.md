# Ayla Convite Telegram — Deploy Napoleon (SAS)

## Arquivos para publicar

```
backend/database/migrations/2026_07_16_100001_ayla_convite_telegram.php
backend/app/Models/AylaConvite.php
backend/app/Models/AylaUsuarioAutorizado.php
backend/app/Support/Ayla/AylaTelefone.php
backend/app/Services/Ayla/AylaConviteService.php
backend/app/Services/Ayla/AylaTelegramSyncService.php
backend/app/Services/AylaAccessService.php
backend/app/Http/Middleware/CheckAylaBridgeToken.php
backend/app/Http/Controllers/AylaConviteController.php
backend/app/Http/Controllers/AylaUsuarioController.php
backend/app/Http/Controllers/Api/AylaController.php
backend/routes/ayla_admin_routes.php
backend/routes/ayla_routes.php
backend/config/ayla.php
backend/bootstrap/app.php
frontend/ayla-ia.js
```

## Variáveis .env (Napoleon)

Adicionar ou conferir:

```env
AYLA_TELEGRAM_BOT_USERNAME=AylaSaborPsraenseBot
AYLA_BRIDGE_TOKEN=<gerar string longa aleatória>
AYLA_VPS_SYNC_URL=https://IP-OU-DOMINIO-VPS:8091
AYLA_VPS_SYNC_TOKEN=<igual ao da VPS>
AYLA_CONVITE_VALIDADE_HORAS=24
```

`AYLA_BRIDGE_TOKEN` deve ser **idêntico** ao configurado no bridge da VPS.

## Comandos exatos

```bash
cd /caminho/do/backend

# Publicar código (git pull / deploy habitual)

php artisan migrate --path=database/migrations/2026_07_16_100001_ayla_convite_telegram.php

php artisan config:clear
php artisan route:clear
php artisan test --filter=AylaConviteTest
```

## Rollback

```bash
php artisan migrate:rollback --path=database/migrations/2026_07_16_100001_ayla_convite_telegram.php
```

Preserva dados de `ayla_usuarios_autorizados`; remove tabela `ayla_convites` e colunas novas.

## Testes curl (após login ADMIN no painel)

Substitua `TOKEN_ADMIN` pelo header `X-Usuario-Id` de um ADMIN.

```bash
# Gerar convite
curl -s -X POST "https://SEU-DOMINIO/api/ayla-admin/usuarios/1/convite" \
  -H "Content-Type: application/json" \
  -H "X-Usuario-Id: TOKEN_ADMIN" \
  -d '{"telefone_telegram":"69984639070"}'

# Status do convite
curl -s "https://SEU-DOMINIO/api/ayla-admin/usuarios/1/convite" \
  -H "X-Usuario-Id: TOKEN_ADMIN"

# Vincular (simula bridge)
curl -s -X POST "https://SEU-DOMINIO/api/ayla/v1/telegram/vincular" \
  -H "Authorization: Bearer SEU_AYLA_BRIDGE_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"convite_token":"TOKEN_DO_CONVITE","telegram_user_id":"5431293656","telegram_username":"usuario","telegram_nome":"Nome"}'
```

## Permissões

Somente **ADMIN** pode gerar, renovar, cancelar, desvincular e sincronizar. GERENTE mantém visualização conforme regras existentes do módulo Ayla.
