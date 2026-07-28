# Levantamento fiscal — SAS-Estoque (Grupo Sabor Paraense)

**Data:** 28/07/2026 (revisão 2 — emissão Focus NFC-e integrada)  
**Repositório:** `sas-estoque-laravel`  
**Método:** análise estática de código, rotas, migrations, telas, testes e docs `modulo-fiscal-*.md`.

---

## 1. Resumo executivo

| Pergunta | Resposta |
|----------|----------|
| Operar **estoque + CNPJ + venda com baixa**? | **Sim** |
| Emitir **NFC-e válida (SEFAZ)** via **Focus NFe**? | **Sim**, se config ativa + token/CSC/cadastro OK (homologação ou produção) |
| Emitir **NF-e** (nota completa)? | **Não implementado** (contrato existe; retorno “fase posterior”) |
| Substituir **contador / SPED / escrituração**? | **Não** — M7 é estimativa gerencial |
| Emissor configurado | **Focus NFe** (URL homolog/prod automática, token criptografado) |

**Veredicto:** o SAS está **pronto para operação fiscal interna completa** e **pronto para emitir NFC-e no PDV** quando você preencher a tela **Emissão NF-e / NFC-e** e a Focus/SEFAZ aceitarem o documento. A venda **não é desfeita** se a SEFAZ rejeitar — dá para reemitir.

---

## 2. Fluxo de emissão (PDV → Focus → SEFAZ)

```text
PDV / Caixa (pagamento)
    → POST /pdv/vendas/balcao  OU  POST /fiscal/vendas
    → VendaFiscalSupport::finalizarVenda (baixa estoque, trava CNPJ)
    → FiscalEmissaoService::emitirNfceParaVenda (se config ativa)
    → FocusNfeClient POST /v2/nfce?ref=...
    → (poll GET se “processando”)
    → Atualiza vendas: chave, número, série, url_danfe, status_documento
    → Log: fiscal_emissao_logs
    → Resposta JSON inclui objeto "emissao" para o frontend (toast)
```

**Reemitir (admin):** `POST /api/fiscal/emissao/vendas/{vendaId}/nfce`  
**Pular emissão numa venda:** `POST /fiscal/vendas` com `"sem_emissao": true`

---

## 3. Mapa de telas

| Menu | Seção | Função |
|------|--------|--------|
| **Configurações** | **Emissão NF-e / NFC-e** | Focus, homolog/prod, token, CSC, séries, checklist, **Emissão ativa** |
| **Configurações** | Empresas (CNPJ) | CNPJ, regime, IE, UF |
| **Configurações** | Perfis tributários | NCM/CFOP/CST padrão |
| **Configurações** | Entradas / Movimentações / Produção / Vendas PDV / M7 | Relatórios e operação |
| **Comercial** | Fiscal | Visão + M7 + PDV + **Emissão (NF)** + Empresas |
| **Comercial** | PDV / Caixa | Venda balcão → emissão automática se config OK |
| **Estoque** | Produtos / Unidades | Dados fiscais + empresa da unidade |
| **Compras** | Listas | NF entrada, status fiscal |

---

## 4. Módulos fiscais — status atualizado

| Módulo | Status | Uso literal | Pendências |
|--------|--------|-------------|------------|
| **M1 Cadastro** | PRONTO | Empresas, perfis, produto fiscal, unidade→empresa | Migrar CNPJ legado em unidades antigas |
| **M2 Compras entrada** | PRONTO | NF manual, créditos, `empresa_id` | Importação XML NF-e |
| **M3 Movimentações** | PRONTO | Eventos fiscais em saídas/perdas/transferências | Permissões granulares, backfill histórico |
| **M5 Produção** | PARCIAL | Ordem produção + baixa insumos + evento | Cardápio/ficha: `produto_id` nos insumos |
| **M6 Venda PDV** | **PRONTO + NFC-e** | Venda, baixa FIFO, trava CNPJ, **NFC-e Focus** | Cancelamento NFC-e SEFAZ; NF-e; caixa persistente avançado |
| **M7 Consolidação** | PARCIAL | Apuração **estimada**, simulador | Alíquotas oficiais; não é obrigação acessória |
| **Emissão Focus** | **OPERACIONAL** | Config + emissão automática PDV | NF-e; certificado direto (A1 local); VendaFácil emissor |
| **Integração VendaFácil** | PARCIAL | API conexão | Emissão NF via VF no SAS |

---

## 5. Backend — componentes de emissão (novo)

| Componente | Caminho |
|------------|---------|
| Cliente HTTP Focus | `App\Services\Fiscal\FocusNfeClient` |
| Emissor NFC-e | `App\Services\Fiscal\FocusNfeDocumentEmitter` |
| Orquestração pós-venda | `App\Services\Fiscal\FiscalEmissaoService` |
| Montagem JSON NFC-e | `App\Support\FocusNfcePayloadBuilder` |
| Config por CNPJ | `App\Models\FiscalEmissaoConfig` + `FiscalEmissaoConfigSupport` |
| Contrato NF-e (futuro) | `App\Contracts\Fiscal\FiscalDocumentEmitterInterface` |

**Rotas emissão:** `backend/routes/fiscal_emissao_config_routes.php`  
**Hook vendas:** `venda_fiscal_routes.php`, `PdvComercialSupport.php`

---

## 6. Rotas fiscais (resumo)

| Área | Arquivo | Exemplos |
|------|---------|----------|
| Cadastro | `fiscal_cadastro_routes.php` | `/fiscal/empresas`, `/fiscal/perfis-tributarios` |
| Compras | `fiscal_compras_entrada_routes.php` | `/fiscal/compras/*` |
| Movimentações | `fiscal_movimentacao_routes.php` | eventos, relatórios |
| Produção | `producao_fiscal_routes.php` | `/fiscal/producoes/*` |
| Vendas | `venda_fiscal_routes.php` | `POST /fiscal/vendas` (+ `emissao` na resposta) |
| M7 | `fiscal_modulo_07_routes.php` | `/fiscal/consolidacao/*`, apuração |
| Emissão | `fiscal_emissao_config_routes.php` | `/fiscal/emissao/config/*`, `POST .../vendas/{id}/nfce` |
| PDV | `pdv_routes.php` | `POST /pdv/vendas/balcao` (+ emissão) |

---

## 7. Banco de dados — migrations fiscais

```text
2026_07_27_000001 … 000007  — M1 a M7
2026_07_28_180000           — cardápio ficha_tecnica_id
2026_07_28_190000           — fiscal_emissao_configs
2026_07_28_200000           — vendas (danfe, ref, série) + fiscal_emissao_logs
```

**Deploy:**

```bash
cd backend
php artisan migrate --force
```

---

## 8. Checklist — emitir NFC-e de verdade

1. **Empresa (CNPJ)** com IE, UF, regime  
2. **Unidade** vinculada à empresa  
3. **Produtos** com NCM, CFOP saída, CSOSN/CST (ex. Simples 102)  
4. **Focus:** empresa + certificado A1 **no painel Focus**  
5. **SAS → Emissão NF-e / NFC-e:** provedor Focus, ambiente **Homologação** (testes), token homolog, CSC SEFAZ, série/número, **Emissão ativa**  
6. Validar checklist na tela (botão Validar)  
7. **PDV:** unidade correta → pagar → ler toast / `emissao` na resposta  
8. Homologação OK → trocar para **Produção** + token produção  

**URLs Focus (padrão no sistema):**

- Homologação: `https://homologacao.focusnfe.com.br`  
- Produção: `https://api.focusnfe.com.br`  

---

## 9. O que ainda falta (pós-NFC-e PDV)

| Item | Prioridade |
|------|------------|
| Cancelamento / inutilização NFC-e (API Focus) | Alta |
| NF-e (B2B) — `emitirNfe` | Média |
| Importação XML compra | Média |
| Contingência offline SEFAZ | Média |
| SPED, EFD, livros oficiais | Fora escopo atual |
| Tributos reais automáticos na venda | Média (hoje placeholder + M7 estimativa) |
| Emissão via VendaFácil | Baixa (integração separada) |
| **Pacote contador (export mensal ZIP)** | **Pronto** — apoio ao escritório; não é SPED/PGDAS |

---

## 9.1 Pacote contador (export mensal por CNPJ)

**Menu:** Configurações → **Pacote contador** (ADMIN/GERENTE).

**API:**

| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/fiscal/pacote-contador/meta` | Metadados (público) |
| GET | `/fiscal/pacote-contador/preview?empresa_id=&mes=YYYY-MM` | Resumo antes do download |
| GET | `/fiscal/pacote-contador/download?...` | ZIP (`Content-Disposition`; CORS expõe header) |

**Conteúdo do ZIP:** `LEIA-ME.txt`, `manifest.json`, `empresa.json`, `resumo_gerencial.json`, `apuracao_estimada.json` (se M7 ativo), `config_emissao.json` (sem tokens), CSVs (`vendas`, `vendas_itens`, `notas_entrada`, `eventos_fiscais`, `logs_emissao_nfce`).

**Backend:** `FiscalPacoteContadorSupport.php`, `fiscal_pacote_contador_routes.php`  
**Frontend:** `fiscal-pacote-contador.js` / `.css`

**Disclaimer:** pacote informativo para o contador; obrigações legais (SPED, PGDAS transmitido, DCTF, etc.) continuam fora do sistema.

---

## 10. Testes automatizados

Comando: `php artisan test --filter="Fiscal|FocusNfce|PacoteContador"`

| Resultado (28/07/2026) | |
|------------------------|--|
| Passou | 22 testes |
| Ignorados | 4 (M7/vendas sem migration no teste unitário isolado) |

Inclui: cadastro, compras, movimentações, emissão config API, **FocusNfcePayloadBuilder**, **FiscalPacoteContadorApiTest**.

---

## 11. Frontend

| Arquivo | Papel |
|---------|--------|
| `fiscal-emissao-config.js` | Config Focus + passo a passo |
| `fiscal-pacote-contador.js` | Export mensal ZIP para contador |
| `comercial-pdv.js` | PDV; toast com resultado `emissao` |
| `fiscal-venda-pdv.js` | Tela venda fiscal + mensagem emissão |
| Demais `fiscal-*.js` | M1–M7 |

Cache publicação (referência): `comercial-pdv.js?v=20260728-focus-emissao`

---

## 12. Cardápio ↔ ficha ↔ estoque

| Tipo | Comportamento |
|------|----------------|
| Revenda | Baixa `estoque_produto_id` |
| Prato | `ficha_tecnica_id` → insumos da receita; baixa na venda operacional |
| NFC-e | Itens da nota usam **produtos de estoque** da venda (NCM/CFOP do cadastro) |

---

## 13. Conclusão

O SAS-Estoque passou de “só preparação fiscal” para **operação + NFC-e Focus no PDV**. Você **não emite** cupom válido sem configurar Focus/CSC/cadastro; com isso feito, **cada venda no Caixa pode gerar NFC-e** automaticamente.

Documentação Focus: https://doc.focusnfe.com.br/reference/ambiente  

Specs internas: `docs/modulo-fiscal-01-cadastro.md` … `modulo-fiscal-07-apuracao-planejamento.md`

---

*Revisão gerada após integração Focus NFC-e (28/07/2026). Validar em homologação SEFAZ antes de produção.*
