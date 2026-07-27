# Módulo 3 — Movimentações de estoque e eventos fiscais

Camada fiscal sobre o fluxo existente de saídas (`POST /saida`) e transferências, sem recriar estoque nem duplicar saldo.

## Migration

- `2026_07_27_000004_fiscal_modulo_03_movimentacoes.php`
  - Colunas em `movimentacoes`: `tipo_movimentacao`, `empresa_origem_id`, `empresa_destino_id`, `status_movimentacao`, `status_documental`, documento, motivo detalhado, custo total.
  - Tabela `eventos_fiscais` vinculada a `movimentacao_id`.

## Backend

- `App\Support\FiscalMovimentacaoSupport` — classificação automática (transferência interna vs operação entre CNPJs), validações, criação/cancelamento de eventos.
- `backend/routes/fiscal_movimentacao_routes.php` — meta, listagem de eventos, relatórios de perdas e transferências.
- Hooks em `api.php`:
  - `POST /saida` — campos fiscais + evento após movimentação.
  - `GET /movimentacoes` — filtro `tipo_movimentacao`, resumo de evento fiscal.
  - `DELETE /movimentacoes/{id}` — cancela eventos fiscais relacionados.

## Tipos

- `tipo_movimentacao`: transferencia_interna, operacao_entre_cnpjs, producao, consumo_interno, perda, avaria, vencimento, extravio, furto.
- Motivos de saída estendidos: AVARIA, VENCIMENTO, EXTRAVIO, FURTO (além dos já existentes).

## Frontend

- `fiscal-movimentacoes.js` / `.css` — extras no modal de saída, filtro fiscal na lista, relatório em **Fiscal → Movimentações e eventos fiscais**.

## Deploy

```bash
cd backend && php artisan migrate --force
```

Publicar `backend` + `frontend`, Ctrl+F5.

## Pendências (fase seguinte)

- Permissões granulares `movimentacao.*` / `evento_fiscal.*`.
- Anexos por movimentação (reutilizar módulo de documentos se existir).
- Backfill de `tipo_movimentacao` em histórico antigo (opcional).
- Integração profunda com ficha técnica / produção.
- Apuração tributária (Módulo 4+).

## Spec

Ver `docs/SAS_ESTOQUE_MODULO_03_MOVIMENTACOES_EVENTOS_FISCAIS.md`.
