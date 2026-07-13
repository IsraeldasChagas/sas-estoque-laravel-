# Ayla IA — Módulo Administrativo

Módulo independente no menu principal do SAS-Estoque para administrar a assistente
**Ayla** (Telegram/OpenClaw) que consome a API `/api/ayla/v1` (somente leitura).

> Esta versão **não libera nenhuma escrita operacional**. `pode_executar_acoes`
> existe no modelo, mas permanece `false` e a API está em modo somente leitura.

## 1. Arquitetura

```
Telegram → OpenClaw (VPS) → MCP → API /api/ayla/v1 → Services SAS → Banco
                                   ↑
        Painel "Ayla IA" (SAS)  →  /api/ayla-admin/*  (X-Usuario-Id, ADMIN)
```

- **API pública da Ayla** (`/api/ayla/v1`): Bearer token, somente leitura, com o
  endpoint `POST /acesso/validar` para o gateway validar um usuário de Telegram.
- **Painel administrativo** (`/api/ayla-admin/*`): autenticado por `X-Usuario-Id`
  (mesmo padrão do restante do painel). ADMIN para alterações; ADMIN/GERENTE para
  ler dashboard e logs.

Regra de segurança central: **o Telegram ID nunca concede acesso sozinho**. O
acesso é sempre vinculado a um usuário SAS existente e ativo, e a permissão
efetiva é a interseção:

```
permissoes_menu (SAS)  ∩  módulos permitidos (Ayla)  ∩  unidades permitidas (Ayla)
```

A Ayla nunca concede mais do que o usuário já possui no SAS.

## 2. Telas (menu 🤖 Ayla IA)

| Tela | Seção | Acesso |
|---|---|---|
| Dashboard | `aylaDashboard` | ADMIN e GERENTE |
| Usuários autorizados | `aylaUsuarios` | ADMIN |
| Permissões | `aylaPermissoes` | ADMIN |
| Canais e voz | `aylaCanaisVoz` | ADMIN (edição) / leitura |
| Logs | `aylaLogs` | ADMIN e GERENTE |
| Configurações | `aylaConfiguracoes` | ADMIN |

- **Dashboard**: status da integração, API SAS, Telegram, contagem de usuários,
  consultas do dia e últimas atividades. Botão “Testar conexão”.
- **Usuários autorizados**: tabela + formulário para vincular usuário SAS ao
  Telegram, com botões Ativar/Bloquear/Revogar.
- **Permissões**: seleção de módulos de leitura e capacidades por usuário. Aviso
  permanente de modo somente leitura.
- **Canais e voz**: Telegram (status + teste), WhatsApp (desativado) e voz
  (provedor, voz, idioma, responder em áudio).
- **Logs**: filtros (período, usuário, ação, rota, status) + paginação. Dados
  sensíveis nunca são exibidos.
- **Configurações**: ativação, URL da API/gateway, rate limit, unidades globais,
  mensagens, token (mascarado, gerar novo) e administrador principal.

## 3. Permissões

- ADMIN: acesso completo (adicionado automaticamente em `applyPermissions`).
- GERENTE: somente o Dashboard.
- Demais perfis: sem acesso por padrão (só via `permissoes_menu` explícito com as
  chaves `aylaDashboard`, `aylaUsuarios`, `aylaPermissoes`, `aylaCanaisVoz`,
  `aylaLogs`, `aylaConfiguracoes`).

Reutiliza `usuarios.perfil`, `usuarios.permissoes_menu`, usuário ativo e a unidade
do usuário. Não cria sistema de perfis paralelo.

## 4. Rotas

### API Ayla (Bearer token — middleware `ayla.token`)
- `POST /api/ayla/v1/acesso/validar` — valida um Telegram ID vinculado.
  (Demais endpoints somente leitura em `docs/AYLA_API_V1.md`.)

### Painel administrativo (`X-Usuario-Id`)
```
GET    /api/ayla-admin/opcoes
GET    /api/ayla-admin/dashboard
GET    /api/ayla-admin/logs
GET    /api/ayla-admin/config
PUT    /api/ayla-admin/config
POST   /api/ayla-admin/gerar-token
POST   /api/ayla-admin/testar-conexao
POST   /api/ayla-admin/admin-principal
GET    /api/ayla-admin/usuarios
POST   /api/ayla-admin/usuarios
GET    /api/ayla-admin/usuarios/{id}
PUT    /api/ayla-admin/usuarios/{id}
PATCH  /api/ayla-admin/usuarios/{id}/status
DELETE /api/ayla-admin/usuarios/{id}   (revoga; não apaga)
```

## 5. Banco de dados

Tabela `ayla_usuarios_autorizados` (migration
`2026_07_12_000002_create_ayla_usuarios_autorizados_table.php`):

`id, usuario_id, telegram_user_id, telegram_username, telegram_nome, cargo,
unidades_permitidas (json), modulos_permitidos (json), pode_usar_texto,
pode_usar_audio, pode_consultar_dados, pode_executar_acoes, status
(pendente|ativo|bloqueado|revogado), ultimo_acesso_em, autorizado_por,
autorizado_em, observacoes, timestamps`.

Índices: `usuario_id`, `telegram_user_id`, `status`. A unicidade de
`telegram_user_id` **ativo** é garantida na aplicação (não há vínculo físico
duplicado do mesmo usuário SAS, nem dois ativos com o mesmo Telegram).

Comando (não roda automaticamente em produção):

```bash
php artisan migrate --path=database/migrations/2026_07_12_000002_create_ayla_usuarios_autorizados_table.php
```

Configurações de painel são gravadas em `sistema_configuracoes` (chaves `ayla_*`).

## 6. Como cadastrar o administrador principal

1. Menu **Ayla IA → Configurações**.
2. Bloco “Administrador principal da Ayla”: selecione seu usuário SAS e informe seu
   Telegram User ID.
3. Salvar. Um vínculo ativo com acesso total (somente leitura) é criado. Nenhum ID
   pessoal é fixado em código/migration.

## 7. Como cadastrar um gerente / vincular Telegram

1. Menu **Ayla IA → Usuários autorizados → + Novo acesso**.
2. Selecione o usuário SAS, informe cargo, Telegram User ID e username.
3. Marque unidades e módulos permitidos e as capacidades (texto/áudio/consulta).
4. Defina status **ativo** e salve.

## 8. Como bloquear / revogar

- **Bloquear**: botão “Bloquear” na linha do usuário (pede confirmação). Impede o
  acesso até ser reativado.
- **Revogar**: botão “Revogar” (pede confirmação). Encerra o acesso definitivamente,
  **sem apagar** o registro (auditoria preservada). `DELETE` apenas muda o status
  para `revogado`.

## 9. Como testar a conexão

Dashboard → “Testar conexão”. O backend consulta internamente
`/api/ayla/v1/status` com o token do servidor e grava data/status do último teste.
O token nunca é enviado ao navegador.

## 10. Como atualizar o token na VPS

1. **Ayla IA → Configurações → Token de acesso → Gerar novo token** (confirmação).
2. Copie o token exibido (mostrado **uma única vez**).
3. Atualize `AYLA_SAS_TOKEN` no `.env` do servidor (`php artisan config:clear`).
4. Atualize o token no gateway/OpenClaw na VPS.

O token nunca é alterado automaticamente na VPS. Se `AYLA_SAS_TOKEN` estiver
definido no `.env`, ele tem prioridade sobre o token do painel.

## 11. Limitações da versão somente leitura

- Nenhuma escrita: sem exclusões, financeiro, boletos, usuários, permissões
  operacionais, estoque, perdas, compras, reservas.
- `pode_executar_acoes` sempre `false`; ações de escrita bloqueadas na API.
- WhatsApp desativado (apenas informativo). Voz é apenas configuração registrada,
  consumida pela VPS.
- O painel não edita arquivos da VPS.

## 12. Segurança

- Backend valida todas as permissões (nunca confia só no frontend).
- Nenhum segredo no JavaScript; token sempre mascarado; gerado token exibido 1x.
- Sanitização e validação no controller; rate limit na API; auditoria em
  `ayla_audit_logs`.
- Sem exclusão física; sem exposição de stack trace; `APP_DEBUG` inalterado.
- LGPD: dados pessoais/documentos sensíveis não são exibidos em logs.
