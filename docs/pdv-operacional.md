# PDV operacional (2026-07-27) — atualizado 2026-08-09

## Deploy

```bash
cd backend && php artisan migrate --force
```

Publicar backend + frontend; cache `20260809-estoque-cardapio`.

## Dois estoques

1. **Estoque Admin** — compras, lotes, produção, CMV (`produtos` / `stock_lotes`) — **inalterado**.
2. **Estoque do Cardápio** — porções/itens à venda (`cardapio_estoque_saldos`) — **novo**.

Fluxo: abasteça em **Cardápio → Estoque** → venda no PDV/mesa/delivery dá baixa automática e grava movimentação.

- **Revenda:** baixa Estoque do Cardápio **e** estoque admin (FIFO).
- **Prato:** baixa só Estoque do Cardápio (insumos já saíram na produção).
- Saldo zerado **bloqueia** a venda.

## Cardápio único (Delivery + PDV + mesas)

1. Cadastre **categorias** e **produtos** em **Cardápio → Itens** (`dlv_produtos`).
2. Cada item deve ter **preço**.
3. Em **Cardápio → Estoque**, abasteça o saldo do dia (ou ajuste inventário).
4. Flags por item:
   - **Visível na loja pública** — vitrine delivery (`visivel_loja`)
   - **Visível no PDV e mesas** — balcão e comandas (`visivel_pdv`)
   - **Ativa / indisponível** — pausa ou “sem venda” na loja online
5. Quando a unidade tem itens no cardápio delivery, o PDV **não** lista mais a tabela `produtos` direto — usa o cardápio.

API: `GET /api/pdv/produtos?unidade_id=` → itens do cardápio com `saldo_cardapio` / `disponivel`.
API estoque B: `GET/POST /api/cardapio-estoque*`.

## Fluxo balcão (PDV / Caixa)

1. **Comercial → PDV / Caixa** — selecionar **unidade**.
2. Lista itens do **cardápio** (indisponíveis se saldo cardápio = 0).
3. **Pagar** → `POST /api/pdv/vendas/balcao` → baixa estoque do cardápio (+ admin se revenda) + `vendas`.

## Fluxo mesa (Comercial → Mesas e Comandas)

1. Selecionar **unidade**.
2. Lançamento valida saldo do cardápio.
3. **Fechar conta** → venda fiscal + baixa estoque do cardápio.

## Tabelas

- `dlv_produtos`, `dlv_categorias` — cardápio
- `cardapio_estoque_saldos`, `cardapio_estoque_movimentacoes` — estoque comercial
- `pdv_comandas`, `pdv_comanda_itens` (`cardapio_produto_id` opcional)
- Colunas em `vendas` / `venda_itens`: `cardapio_produto_id`, `cardapio_movimentacao_id`

## Pendências (futuro)

Reserva em comanda, explosão de ficha na venda (M2).

**Já feito:** ao finalizar produção fiscal, a quantidade produzida entra automaticamente no Estoque do Cardápio dos itens vinculados à ficha (`ficha_tecnica_id`) ou ao produto final (`estoque_produto_id`).
