# Reservas — capacidade flexível, composição e emergência

## O que foi entregue

1. **Capacidade flexível** em `mesas`: base, extras, máxima (mantém `capacidade` legado).
2. **Composição** via pivô `reserva_mesas` (até 4 mesas, mesma unidade / grupo).
3. **Cadastro emergencial** de mesa pela Ayla (somente preparar → confirmar).
4. **Alertas operacionais** (Kanban se existir + nota na observação da reserva).
5. **Frontend** mostra faixa de capacidade, extras, vínculos e alerta de preparo físico.

## Migration

`2026_07_14_120000_add_capacidade_flexivel_e_composicao_mesas.php`

- Não remove colunas antigas.
- Backfill: `capacidade` → `capacidade_base` / `capacidade_maxima`.
- Backfill pivô das reservas existentes (`mesa_id` principal).

Rodar no ambiente adequado:

```bash
php artisan migrate
```

## Ações Ayla (sempre com confirmação)

| Ação | Uso |
|------|-----|
| `criar` / `criar_reserva` | Reserva simples, com extras ou mesa sugerida |
| `preparar_composicao_mesas` | Junta mesas (`dados.mesas[]`) |
| `criar_mesa_emergencial` | Cadastra mesa emergencial |
| `criar_mesa_emergencial_e_reservar` | Cadastra + reserva |
| `ajustar_capacidade_mesa` | Ajusta base/extras/máxima |
| `criar_alerta_operacional` | Task Kanban / nota de preparo |

Fluxo: `POST /api/ayla/v1/reservas/acoes/preparar` → confirmação humana → `.../confirmar`.

## Motor de disponibilidade

Prioridade: mesa exata → mesa maior → cadeiras extras → composição → ajuste/emergência.

## Compatibilidade

- Reservas antigas continuam válidas (`mesa_id` + pivô).
- Escrita direta SAS (`/reservas-mesas`) segue funcionando; sync do pivô da mesa principal.
- POST/PUT Ayla direto continua bloqueado (`CONFIRMATION_REQUIRED`).
