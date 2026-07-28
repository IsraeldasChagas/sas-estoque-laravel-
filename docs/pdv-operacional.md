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

## Fluxo mesa (Comercial → Mesas e Comandas)

1. Selecionar **unidade** (mesma do PDV; salva no navegador).
2. Mapa real (`GET /api/pdv/salao`) — livre / reservada / ocupada / bloqueada.
3. **Clicar na mesa** → abre comanda (`POST /api/pdv/comandas/abrir`) e painel lateral.
4. Toque nos **produtos** (valida estoque/CNPJ ao lançar).
5. **Pré-conta** (impressão) ou **Fechar conta** → venda fiscal + mesa livre + reserva finalizada.

API extra: `PATCH /pdv/comandas/{id}` (pessoas), `GET .../pre-conta`, `GET /pdv/comandas/abertas`.

## Tabelas

- `pdv_comandas`, `pdv_comanda_itens`
- Colunas em `vendas`: `mesa_id`, `comanda_id`, `reserva_mesa_id`, `origem_venda`

## Pendências (futuro)

Caixa aberto/fechado, KDS persistido, TEF, NFC-e, divisão de conta avançada — ver `PDV_BACKEND_FUTURO.md`.
