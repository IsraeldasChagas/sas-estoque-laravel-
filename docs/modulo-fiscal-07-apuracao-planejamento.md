# Módulo 7 — Fiscal, apuração e planejamento tributário

Camada de **consolidação gerencial** sobre M1–M3, M5 e M6. Não duplica operações; lê entradas, vendas, créditos, eventos e estoque.

## Deploy

```bash
cd backend && php artisan migrate --force
```

Publicar backend + frontend; hard refresh (`Ctrl+F5`). Cache: `fiscal-m7`.

## Banco (migration `2026_07_27_000007`)

- `regras_fiscais` — parâmetros versionados (`configuracao_json`), seed inicial **sem** alíquotas em PHP de rota
- `apuracoes_fiscais` / `apuracao_fiscal_itens`
- `estornos_fiscais` (estrutura; listagem também deriva de `eventos_fiscais`)
- `cenarios_tributarios` — simulações salvas

## Backend

| Support | Função |
|---------|--------|
| `RegraFiscalSupport` | Vigência, estimativa percentual sobre base |
| `FiscalConsolidacaoSupport` | Cards, listagens, por CNPJ, estoque potencial |
| `ApuracaoFiscalSupport` | Apuração por período (débitos `tributos_venda`, créditos NF entrada) |
| `PlanejamentoTributarioSupport` | 3 cenários (C→C, B→B, C→B→venda) |

Rotas: `backend/routes/fiscal_modulo_07_routes.php` (prefixo `/api/fiscal/...`).

## API (resumo)

- `GET /fiscal/consolidacao/visao-geral?empresa_id&data_ini&data_fim`
- `GET /fiscal/consolidacao/{entradas|saidas|creditos|estornos|por-cnpj|estoque-potencial|tributos-recolher}`
- `GET /fiscal/regras`
- `POST /fiscal/apuracao/calcular` — body: `empresa_id`, `periodo_inicio`, `periodo_fim`
- `PATCH /fiscal/apuracao/{id}/validar`
- `POST /fiscal/planejamento/simular` — empresas C/B, quantidades, preços
- `POST /fiscal/planejamento/cenarios` — persistir simulação

## Frontend

Menu **Fiscal → consolidação (M7)** — abas: visão geral, entradas, saídas, créditos, estornos, tributos a recolher, estoque potencial, por CNPJ, apuração, planejamento.

Arquivos: `fiscal-modulo-07.js`, `fiscal-modulo-07.css`.

## Disclaimer

Estimativas para apoio à decisão. Regras seed em `regras_fiscais` devem ser revisadas pelo contador. Simulador **não** movimenta estoque nem emite documentos.

## Spec completa

Ver cópia em `docs/SAS_ESTOQUE_MODULO_07_FISCAL_PLANEJAMENTO_TRIBUTARIO.md`.
