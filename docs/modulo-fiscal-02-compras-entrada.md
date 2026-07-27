# Módulo fiscal 2 — Compras e entrada fiscal

Camada fiscal sobre **listas de compras** (`listas_compras`), sem substituir o fluxo existente.

## Fluxo

```text
Lista de compras → NF de entrada (opcional) → Lançamento no estoque → Rastreio por empresa/CNPJ
```

## Migration

`2026_07_27_000003_fiscal_modulo_02_compras_entrada.php`

- `listas_compras.empresa_id`, `status_fiscal`
- `notas_fiscais_entrada`, `itens_notas_fiscais_entrada`, `creditos_fiscais_entrada`
- Vínculos em `lotes` e `movimentacoes` (+ `tipo_entrada_fiscal`)

## Backend

| Componente | Função |
|------------|--------|
| `FiscalCompraEntradaSupport` | Validações, divergências, créditos, pós-lançamento |
| `fiscal_compras_entrada_routes.php` | API fiscal |
| `api.php` | `GET /listas` enriquecido + filtros; `POST/PUT /listas` com `empresa_id`; estoque com rastreio |

## API `/api/fiscal/compras`

| Método | Rota |
|--------|------|
| GET | `/listas/{id}` — pacote fiscal |
| PUT | `/listas/{id}` — `empresa_id` |
| PUT | `/listas/{id}/nota` — NF + itens + tributos |
| GET | `/relatorio-entradas` |
| GET | `/creditos-potenciais` |

`GET /api/listas?empresa_id=&status_fiscal=` — filtros na listagem.

## Frontend

| Onde | O quê |
|------|--------|
| **Lista de compras** | Colunas empresa/NF/status fiscal; filtros; painel **Dados fiscais** |
| **Nova lista** | Empresa compradora + unidades filtradas |
| **Config → Fiscal → Entradas fiscais (compras)** | Relatórios de entradas e créditos potenciais |

Cache: `fiscal-compras.js?v=20260727-fiscal-m2-ready`

## Deploy

```bash
cd backend
php artisan migrate --force
php artisan test --filter=Fiscal
```

Publicar `backend` + `frontend`, **Ctrl+F5**.

## Testes

- `tests/Unit/FiscalCompraEntradaSupportTest.php`
- `tests/Feature/FiscalCompraEntradaApiTest.php` (empresa/unidade, chave NF duplicada)

## Pendências (próximas fases)

- Importação XML NF-e
- Permissões granulares `compra_fiscal.*`
- Módulo 3 — movimentações / destino fiscal

## Especificação

Ver também `docs/SAS_ESTOQUE_MODULO_02_COMPRAS_ENTRADA_FISCAL.md` (cópia do documento de produto).
