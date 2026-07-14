# Ayla — Reservas: escrita controlada (preparar → confirmar)

Evolução da integração de **Reservas de Mesas** para permitir ações de escrita
**somente após confirmação explícita** do usuário. Sem exclusão física.

## 1. Fluxo

```
Usuário pede alteração
    ↓
GET disponibilidade / consultar (opcional)
    ↓
POST /reservas/acoes/preparar   ← valida, não grava reserva
    ↓
Ayla mostra resumo e pergunta “Deseja confirmar?”
    ↓
Usuário: sim / confirmar / pode fazer
    ↓
POST /reservas/acoes/{id}/confirmar   ← executa 1 vez
    ↓
Auditoria em ayla_audit_logs + status executada
```

Negativa (`não`, `cancelar`, `deixa`) → `POST .../cancelar` (não altera reserva).  
Expiração: **10 minutos**.  
Outro usuário/Telegram não pode confirmar.

## 2. Arquivos

### Criados
- `database/migrations/2026_07_14_100001_create_ayla_acoes_pendentes_table.php`
- `app/Models/AylaAcaoPendente.php`
- `app/Support/Ayla/AylaWriteGuard.php`
- `app/Services/Ayla/AylaAcaoPendenteService.php`
- `docs/mcp/reservas-write-tools.mjs`
- `docs/AYLA_RESERVAS_ESCRITA_CONTROLADA.md` (este)

### Alterados
- `AylaReservasService.php` — criar/atualizar/status/mesa + preview
- `AylaController.php` — preparar/confirmar/cancelar + bloqueio de escrita direta
- `ayla_routes.php`
- `SasIaToolRegistry.php`, `SasIaModuleQueryService.php`, `AylaApiService.php`
- `AylaUsuarioController.php` — `pode_executar_acoes` liberável se não read_only
- `AylaAccessService.php`, `AylaResponse.php` (CORS)
- `openclaw/skill-ayla/SKILL.md`, `openclaw/mcp-sas-estoque/tools.json`
- `tests/Feature/AylaApiTest.php`

**Não alterados:** `ReservaMesaController`, `MesaController`, telas, Kanban, Patrimônio,
Telegram/voz/allowlist (além da skill), rotas GET existentes.

## 3. Migration

Tabela `ayla_acoes_pendentes`: usuário, telegram, canal, módulo, ação, payload JSON,
resumo, status (`pendente|confirmada|executada|cancelada|expirada|erro`),
`expira_em`, `confirmado_em`, `executado_em`, resultado JSON, timestamps.

```bash
# Napoleon / staging — NÃO rode em produção sem revisão
php artisan migrate --path=database/migrations/2026_07_14_100001_create_ayla_acoes_pendentes_table.php
php artisan migrate:rollback --path=database/migrations/2026_07_14_100001_create_ayla_acoes_pendentes_table.php
```

## 4. Endpoints

| Método | Rota | Efeito |
|---|---|---|
| POST | `/api/ayla/v1/reservas/acoes/preparar` | Cria ação pendente |
| POST | `/api/ayla/v1/reservas/acoes/{id}/confirmar` | Executa |
| POST | `/api/ayla/v1/reservas/acoes/{id}/cancelar` | Cancela pendente |
| POST/PUT/PATCH | `/reservas`, `/reservas/{id}`, `.../status`, `.../mesa` | **403** `CONFIRMATION_REQUIRED` |
| GET | `/reservas*` | Inalterado (leitura) |
| DELETE | qualquer | Continua bloqueado (405) |

## 5. Ações permitidas / bloqueadas

**Permitidas:** criar; editar cliente/telefone/pessoas/data/hora/obs; alterar mesa;
confirmar; registrar chegada; finalizar; cancelar (com motivo opcional em observação).

**Bloqueadas:** exclusão física; escrita sem confirmação; criar/alterar mesa ou capacidade;
alterar unidade sem nova disponibilidade; sobreposição (conflito exato);
alterar `usuario_id` responsável.

Status reais: `pendente|confirmada|cancelada|cliente_chegou|no_show|finalizada`.

## 6. Permissões

`permissoes_menu` (`reservaMesa` / `historicoReservas`)
∩ módulos Ayla ∩ unidades ∩ **`pode_executar_acoes`**
∩ **`ayla_read_only = false`**.

- ADMIN: pode escrever (ainda precisa `read_only` off).
- GERENTE/outros: `pode_executar_acoes = true` no vínculo Ayla.
- Sem flag: consulta OK, escrita **403**.

Headers: `X-Usuario-Id` (obrigatório na escrita), `X-Telegram-User-Id` (quando canal Telegram).

## 7. Auditoria

`ayla_audit_logs`: usuário, ação (`ayla.reservas.acao.*`), payload sanitizado,
HTTP, duração. Telefone mascarado/`[REDACTED]`. Sem token/senha/API key.

## 8. MCP (VPS)

```javascript
import { reservasWriteTools } from "./reservas-write-tools.mjs";
tools: [
  ...reservasTools(cfg),
  ...reservasWriteTools(cfg),
]
```

Arquivo: `docs/mcp/reservas-write-tools.mjs`. Reiniciar MCP após deploy.

## 9. Publicar na Napoleon

1. Deploy do código + migration (revisada).
2. `php artisan migrate --path=database/migrations/2026_07_14_100001_create_ayla_acoes_pendentes_table.php`
3. Painel Ayla: desativar somente leitura; marcar `pode_executar_acoes` nos usuários autorizados.
4. `php artisan route:clear && php artisan config:clear`
5. Conferir: `php artisan route:list --path=ayla/v1/reservas`

## 10. Telegram (exemplos)

- “Ayla, reserve para João amanhã às 20h, 6 pessoas, Unidade 2.”  
  → disponibilidade → preparar → resumo → “Deseja confirmar?” → após “sim” → confirmar.
- “Confirme a reserva 154.” → preparar `confirmar` → perguntar → executar.
- “Cancela a pendente.” (se mudou de ideia antes) → `cancelar_acao`.

## 11. Confirmações

- Nenhuma ação é executada sem confirmação.
- Nenhuma reserva é excluída fisicamente.
- O módulo Reservas existente continua funcionando normalmente.
