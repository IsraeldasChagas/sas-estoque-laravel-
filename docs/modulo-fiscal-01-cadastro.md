# Módulo Fiscal 01 — Cadastro (SAS-Estoque)

**Especificação:** `SAS_ESTOQUE_MODULO_01_CADASTRO_FISCAL.md`  
**Data conclusão:** 2026-07-27  
**Escopo:** cadastro fiscal de produtos, perfis tributários e empresas/CNPJ — **sem** cálculo de imposto, movimentação fiscal ou alteração de estoque/transferências/PDV.

---

## Análise da estrutura existente

| Item | Situação |
|------|----------|
| **Produtos** | CRUD em `api.php` + modal `#produtoModal`. Campos fiscais nullable; status derivado (pendente/incompleto/completo). |
| **Unidades** | `unidades.cnpj` legado + `empresa_id` opcional. |
| **Empresas / perfis** | Tabelas `empresas`, `perfis_tributarios`. |
| **Permissões** | ADMIN edita empresas/perfis; ADMIN e GERENTE visualizam e usam dados fiscais em produtos. |

---

## Backend

- Migration: `2026_07_27_000001_fiscal_modulo_01_cadastro.php`
- Support: `App\Support\FiscalCadastroSupport`
- Rotas: `backend/routes/fiscal_cadastro_routes.php` (`/fiscal/meta`, CRUD empresas/perfis, sugestão por perfil)
- Produtos/unidades: campos fiscais no POST/PUT; lista com `status_fiscal`, labels e filtro `?status_fiscal=`

**Testes:** `tests/Unit/FiscalCadastroSupportTest.php`, `tests/Feature/FiscalCadastroApiTest.php`

---

## Frontend

- `frontend/fiscal-cadastro.js` + `fiscal-cadastro.css`
- Menu **Configurações → Empresas (CNPJ)** / **Perfis tributários**
- Modal produto: abas **Dados gerais** / **Dados fiscais** (tipo obrigatório em produto novo; aplicar perfil com confirmação)
- Listagem produtos: colunas tipo/perfil/badge fiscal + filtro
- Unidades: select **Empresa responsável** (modal e formulário inline)

Integração em `app.js`: navegação, `loadProdutos` + filtro fiscal, `renderProdutos`, `submitProduto`, unidade `empresa_id`.

Cache publicação: `app.js?v=20260727-fiscal-m1b`, `fiscal-cadastro.js?v=20260727-fiscal-m1b`.

---

## Deploy

```bash
cd backend
php artisan migrate --force
```

Frontend: hard refresh (Ctrl+F5) após publicar HTML/JS/CSS.

---

## Relatório de entrega (§59)

| Critério | Status |
|----------|--------|
| Cadastro de empresas (CNPJ, regime, IE/IM) | OK |
| Cadastro de perfis tributários | OK |
| Produto com classificação fiscal e vínculo a perfil | OK |
| Unidade vinculada a empresa | OK |
| Indicador de completude fiscal na listagem | OK |
| Sem cálculo de imposto / NF / estoque fiscal | OK (fora de escopo) |
| Permissões ADMIN (editar) / GERENTE (ver) | OK |
| Testes automatizados mínimos | OK |

**Pendências futuras (outros módulos):** emissão fiscal, movimentação, integração PDV, migração em massa CNPJ legado das unidades.
