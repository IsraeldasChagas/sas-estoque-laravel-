# PDV operacional (2026-07-27)

## Deploy

```bash
cd backend && php artisan migrate --force
```

Publicar backend + frontend; cache `20260728-cardapio-pdv`.

## Cardápio único (Delivery + PDV + mesas)

1. Cadastre **categorias** e **produtos** em **Delivery → Produtos** (`dlv_produtos`).
2. Cada item deve ter **preço** e, para venda no salão/balcão, **produto de estoque vinculado** (`estoque_produto_id`).
3. Flags por item:
   - **Visível na loja pública** — vitrine delivery (`visivel_loja`)
   - **Visível no PDV e mesas** — balcão e comandas (`visivel_pdv`)
   - **Ativa / indisponível** — pausa ou “sem venda” na loja online
4. Quando a unidade tem itens no cardápio delivery, o PDV **não** lista mais a tabela `produtos` direto — usa o cardápio.

API: `GET /api/pdv/produtos?unidade_id=` → itens do cardápio (fallback estoque se a unidade não tiver `dlv_produtos`).

## Fluxo balcão (PDV / Caixa)

1. **Comercial → PDV / Caixa** — selecionar **unidade** (mesmo CNPJ do estoque).
2. Lista itens do **cardápio** da unidade.
3. **Pagar** → `POST /api/pdv/vendas/balcao` (aceita `cardapio_produto_id` + `produto_id` de estoque) → baixa FIFO + `vendas`.

## Fluxo mesa (Comercial → Mesas e Comandas)

1. Selecionar **unidade** (mesma do PDV; salva no navegador).
2. Mapa real (`GET /api/pdv/salao`).
3. **Clicar na mesa** → comanda + painel com **mesmos itens do cardápio**.
4. Lançamento: `POST /pdv/comandas/{id}/itens` com `cardapio_produto_id`.
5. **Fechar conta** → venda fiscal + mesa livre.

## Tabelas

- `dlv_produtos`, `dlv_categorias` — cardápio
- `pdv_comandas`, `pdv_comanda_itens` (`cardapio_produto_id` opcional)
- Colunas em `vendas`: `mesa_id`, `comanda_id`, `reserva_mesa_id`, `origem_venda`

## Pendências (futuro)

Caixa aberto/fechado, KDS persistido, TEF, NFC-e, divisão de conta avançada — ver `PDV_BACKEND_FUTURO.md`.
