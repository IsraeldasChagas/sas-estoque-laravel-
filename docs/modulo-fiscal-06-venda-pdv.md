# Módulo 6 — Venda / PDV fiscal

Camada fiscal sobre vendas reais via API. O **PDV comercial continua protótipo visual**, mas pode finalizar venda fiscal quando a unidade real estiver selecionada.

## Regra central

`empresa_id` do estoque (lote/unidade) deve ser igual ao `empresa_id` do PDV/unidade da venda. Caso contrário: **HTTP 422**, log em `venda_fiscal_bloqueios_log`, sem baixa.

## Migration

`2026_07_27_000006_fiscal_modulo_06_venda_pdv.php` — `vendas`, `venda_itens`, `tributos_venda`, log de bloqueios, `eventos_fiscais.venda_id`.

## Backend

- `VendaFiscalSupport` — validação, finalização em transação, baixa FIFO, snapshot fiscal, tributos placeholder, evento `venda`.
- `venda_fiscal_routes.php` — validar item, POST venda, painel, relatório sem lote.

## Frontend

- **Fiscal → Vendas PDV (fiscal)** — carrinho com validação por item.
- **PDV/Caixa** — select “Unidade fiscal”; pagamento chama `fiscalPdvConfirmarPagamento` quando unidade + carrinho com IDs de produto reais.

## Deploy

```bash
php artisan migrate --force
```

## Pendências

- PDV persistente completo (tabelas `pdv_vendas` do doc futuro) — substituir/alias `vendas` quando existir.
- Emissão NFC-e/NF-e (integração emissor).
- Cálculo real de alíquotas (Módulo 7).
- Cancelamento/estorno de venda.
- Tipo de movimentação específico `venda` (hoje motivo `VENDA` na movimentação).

Spec: `docs/SAS_ESTOQUE_MODULO_06_VENDA_PDV_FISCAL.md`
