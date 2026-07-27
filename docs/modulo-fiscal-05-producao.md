# Módulo 5 — Produção, ficha técnica e rastreabilidade fiscal

Integra a **ficha técnica** existente (`fichas_tecnicas` + ingredientes JSON) com **ordens de produção**, baixa FIFO de insumos, entrada do produto final e **evento fiscal** `producao`.

## Migration

`2026_07_27_000005_fiscal_modulo_05_producao.php`

- Campos de produção em `fichas_tecnicas` (produto final, rendimento, versão, etc.)
- `ficha_tecnica_itens`, `producoes`, `producao_insumos`, `producao_lotes`
- `producao_id` em `movimentacoes`, `lotes`, `eventos_fiscais`

## Backend

- `ProducaoEstoqueSupport` — baixa FIFO (mesma regra de `/saida`)
- `ProducaoFiscalSupport` — criar produção, simular saldo, finalizar em transação
- `producao_fiscal_routes.php` — API `/fiscal/producoes`, finalizar, simular, rastreabilidade
- `PATCH /fichas-tecnicas/{id}/producao` — produto final + rendimento

## Fluxo operacional

1. Na **Ficha técnica**, salvar vínculo: produto final + rendimento (painel “Dados para produção”).
2. Ingredientes devem ter **`produto_id`** (campo no JSON) ou ID igual a um produto existente.
3. **Fiscal → Produção (ficha técnica)** — criar ordem, simular saldo, **Finalizar** (baixa + entrada `PROD-…` + evento).

## Deploy

```bash
cd backend && php artisan migrate --force
```

Publicar backend + frontend; Ctrl+F5.

## Pendências

- Seletor de produto no ingrediente da ficha (hoje `produto_id` manual ou match por nome)
- Cancelamento/estorno completo de produção finalizada
- Relatórios de desvio previsto × real (estrutura de dados pronta)
- Versionamento automático ao editar ficha já usada em produção

Spec: `docs/SAS_ESTOQUE_MODULO_05_PRODUCAO_FICHA_TECNICA.md`
