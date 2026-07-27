# PDV operacional (2026-07-27)

## Deploy

```bash
cd backend && php artisan migrate --force
```

Publicar backend + frontend; cache `20260727-pdv-op`.

## Fluxo balcão (PDV / Caixa)

1. **Comercial → PDV / Caixa** — selecionar **unidade** (mesmo CNPJ do estoque).
2. Lista produtos com saldo na unidade (`GET /api/pdv/produtos?unidade_id=`).
3. Preço sugerido: ficha técnica (`preco_prato` / `sugestao_venda`) ou campos do produto.
4. **Pagar** → `POST /api/pdv/vendas/balcao` → baixa FIFO + `vendas` + evento fiscal.

## Fluxo mesa

1. **Comercial → Mesas e Comandas** — mesma unidade.
2. Mapa via `GET /api/pdv/salao?unidade_id=`.
3. Toque na mesa → abre comanda (`POST /api/pdv/comandas/abrir`).
4. Lançar itens (`POST /api/pdv/comandas/{id}/itens`).
5. **Fechar conta** → `POST /api/pdv/comandas/{id}/finalizar` → venda fiscal + mesa livre + reserva `finalizada` / `conta_paga` se vinculada.

## Tabelas

- `pdv_comandas`, `pdv_comanda_itens`
- Colunas em `vendas`: `mesa_id`, `comanda_id`, `reserva_mesa_id`, `origem_venda`

## Pendências (futuro)

Caixa aberto/fechado, KDS persistido, TEF, NFC-e, divisão de conta avançada — ver `PDV_BACKEND_FUTURO.md`.
