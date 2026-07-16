# Mapeamento do SAS-Estoque para Integração com VendafFacil

**Data da análise:** 2026-07-16  
**Escopo:** somente leitura do código em `sas-estoque-laravel`  
**Nome do sistema externo:** VendafFacil (`vendaffacil`) — sistema independente e vendável; o SAS-Estoque será apenas consumidor autorizado da API.  
**Restrição desta tarefa:** nenhum código de produção alterado; nenhuma migration; nenhuma implementação. Este arquivo é o único artefato criado.

**Etiquetas usadas neste documento**
- `[ENCONTRADO]` — fato observado no código/banco/docs do SAS
- `[RECOMENDAÇÃO]` — proposta futura (não implementada)
- `[RISCO]` — risco técnico ou de negócio
- `[PENDENTE]` — depende de mapeamento/API do VendafFacil ou de decisão de produto

---

## 1. Resumo executivo

[ENCONTRADO] O SAS-Estoque é um monólito **Laravel 12 + PHP ^8.2** com frontend SPA (`frontend/index.html` + `app.js` + módulos `*.js`). O núcleo operacional (estoque, produtos, compras, usuários, lotes, movimentações) concentra-se em closures em `backend/routes/api.php` (~11k linhas). Módulos posteriores usam arquivos `*_routes.php` e, em menor escala, Controllers/Services.

[ENCONTRADO] **Não existe tabela `empresas`**. A “empresa” é modelada como chaves em `sistema_configuracoes` (`empresa_nome`, `empresa_cnpj`, …), com seed default contendo texto de uma organização específica — inadequado para multiempresa genérica.

[ENCONTRADO] **Existe multiunidade** via tabela `unidades` e campo `unidade_id` em várias entidades. O filtro por unidade é majoritariamente **explícito por query string**, não por global scope Eloquent.

[ENCONTRADO] O módulo **Comercial/PDV** no frontend (`comercial-pdv.js`) é **protótipo visual sem API/persistência**. O módulo **Delivery** (protótipo) **foi removido** do frontend; não há `delivery.js`/`delivery.css` nem rotas/tabelas delivery.

[ENCONTRADO] Integrações existentes úteis como referência de padrão: **OpenClaw** (painel Configurações → Integrações, token mascarado, unidades permitidas, teste HTTP) e **Ayla** (API tokenizada read-only). Já há uso de `Illuminate\Support\Facades\Http`.

[RECOMENDAÇÃO] A integração futura deve tratar o SAS como **cliente HTTP** da API do VendafFacil, com:
- configuração por **tenant/empresa lógica + unidades**;
- tabela genérica de **mapeamentos externos** (`integration_mappings`);
- logs/idempotência/webhooks;
- UI em **Configurações → Integrações → VendafFacil**, espelhando o padrão OpenClaw.

[RISCO] Sem modelo multiempresa real, qualquer integração “empresa ↔ VendafFacil” exige desenho novo de tenant no SAS (ou mapeamento 1:1 SAS-instalação ↔ empresa VendafFacil) antes de produção multi-cliente.

---

## 2. Arquitetura atual do SAS-Estoque

```
Frontend SPA (HTML/JS)
  index.html + app.js + comercial-pdv.js + openclaw-integracao.js + …
       │  header X-Usuario-Id (+ CORS liberado)
       ▼
Laravel 12 (backend/)
  routes/api.php (closures — núcleo)
  routes/*_routes.php (módulos)
  Controllers (minoria) / Services (Ayla, SasIa, EntradaEstoque, Boleto…)
  Support/* (OpenClawSettings, AuditLog, AylaSettings…)
       ▼
MySQL
  tabelas core legadas (sem create migration no repo) + migrations 2026_*
```

[ENCONTRADO] Padrão arquitetural: **híbrido procedural + parcialmente Service-oriented**. Poucos Models Eloquent; maioria acessa `DB::table(...)`.

[ENCONTRADO] Não há pastas `app/Jobs`, `app/Events`, `app/Listeners`, `app/Policies`, `app/Repositories`, `app/Actions` (glob nesta análise).

[ENCONTRADO] Autenticação de API do app: login gera token aleatório no JSON de resposta, mas a autorização prática das rotas usa **`X-Usuario-Id`** (`EnsureSasUsuario` / checks manuais). O token de login **não é validado de forma central** como Bearer session store.

[ENCONTRADO] Autorização: perfis em `usuarios.perfil` + `permissoes_menu` (JSON) no frontend (`PERMISSOES` em `app.js`). Backend frequentemente checa perfil ADMIN/GERENTE pontualmente.

---

## 3. Tecnologias e versões

| Item | Valor | Etiqueta |
|---|---|---|
| PHP | `^8.2` (`backend/composer.json`) | [ENCONTRADO] |
| Laravel | `^12.0` | [ENCONTRADO] |
| Queue | `QUEUE_CONNECTION=database` (`.env.example`) | [ENCONTRADO] |
| Cache | `CACHE_STORE=database` | [ENCONTRADO] |
| Session | `SESSION_DRIVER=database` | [ENCONTRADO] |
| DB default | MySQL (`DB_CONNECTION=mysql`) | [ENCONTRADO] |
| PDF | `dompdf/dompdf` | [ENCONTRADO] |
| QR | `endroid/qr-code` | [ENCONTRADO] |
| PDF parse | `smalot/pdfparser` | [ENCONTRADO] |
| Frontend | SPA estática (sem Vite obrigatório no fluxo principal do painel) | [ENCONTRADO] |
| HTTP client | Laravel `Http` facade (OpenClaw, AylaUsuario, InvestimentoMercado) | [ENCONTRADO] |
| Sanctum/Passport | **Não** presente em `composer.json` | [ENCONTRADO] |
| Guzzle direto | Via HTTP client do Laravel (Guzzle sob o hood) | [ENCONTRADO] |

---

## 4. Estrutura multiempresa e multiunidade

### 4.1 Empresa

| Aspecto | Situação | Etiqueta |
|---|---|---|
| Tabela `empresas` | **Não encontrada** | [ENCONTRADO] |
| Model Empresa | **Não encontrado** | [ENCONTRADO] |
| Config “empresa” | `sistema_configuracoes` chaves `empresa_nome`, `empresa_cnpj`, `empresa_email`, `empresa_telefone`, `empresa_endereco` | [ENCONTRADO] |
| Seed default | migration `2026_06_10_000001_create_sistema_configuracoes_table.php` grava nome de organização específica no seed | [RISCO] acoplamento de marca no seed |
| `empresa_id` | Aparece pontualmente (ex.: `rh_rescisoes.empresa_id` nullable) — **não** é tenant global | [ENCONTRADO] |

[RECOMENDAÇÃO] Para integração genérica multiempresa:
1. Introduzir futuramente `empresas` (ou usar 1 instalação SAS = 1 empresa VendafFacil no curto prazo); **ou**
2. Guardar `vendaffacil_empresa_id` (e mapeamentos) em tabela de integração **sem** hardcode de cliente.

### 4.2 Unidade

| Aspecto | Situação | Etiqueta |
|---|---|---|
| Tabela | `unidades` | [ENCONTRADO] |
| Model | `backend/app/Models/Unidade.php` | [ENCONTRADO] |
| Campos fillable | `nome`, `endereco`, `cnpj`, `telefone`, `email`, `gerente_usuario_id`, `ativo` | [ENCONTRADO] |
| Sessão | Login devolve `unidade_id` do usuário; frontend filtra em várias telas | [ENCONTRADO] |
| Filtro | Query `?unidade_id=` em produtos/estoque/financeiro etc. | [ENCONTRADO] |
| Global scopes | **Não** há BelongsToTenant/empresa scope | [ENCONTRADO] |
| Middleware tenant | **Não** existe middleware de isolamento por empresa/unidade | [RISCO] |

[RISCO] Vazamento entre unidades: várias rotas listam “todas” quando `unidade_id` omitido; admin vê cross-unidade. Para VendafFacil, **sempre** enviar `unidade` mapeada explicitamente e validar permissão no SAS antes de chamar a API.

[RECOMENDAÇÃO] Pontos que precisarão enviar empresa/unidade ao VendafFacil:
- sincronização de produtos/estoque;
- abertura/consulta de pedidos e vendas;
- emissão fiscal;
- webhooks de status (validar assinatura + unidade mapeada).

---

## 5. Usuários, perfis e permissões

### 5.1 Usuários

[ENCONTRADO]
- Tabela: `usuarios` (Model `App\Models\Usuario`)
- Campos relevantes: `nome`, `email`, `senha_hash`, `perfil`, `unidade_id`, `foto`/`foto_path`, `permissoes_menu`, `tema_cores`, `ativo`, `criado_em`
- Scaffold Laravel `users` / Model `User` existe mas **não** é o auth do app
- Login: `POST /api/login` em `routes/api.php`

### 5.2 Perfis (frontend)

[ENCONTRADO] Objeto `PERMISSOES` em `frontend/app.js` — perfis como `ADMIN`, `GERENTE`, `ESTOQUISTA`, `COZINHA`, `BAR`, `FINANCEIRO`, `ATENDENTE`, `CAIXA`, `GARCOM`, `MARKETING`, etc., cada um com lista de `sections` (ids de menu).

### 5.3 Policies / Gates

[ENCONTRADO] **Não há** Policies nem Gates Laravel registrados para domínio.

### 5.4 Menus condicionados

[ENCONTRADO] `aplicarPermissoesNaUI` / `getEffectiveSections` em `app.js` ocultam links `data-section` e submenus pais (Comercial, Configurações, Ayla…).

### 5.5 Permissões futuras sugeridas

[RECOMENDAÇÃO] Estender o modelo atual de `sections` / `permissoes_menu` (checkboxes no modal de usuário) com chaves novas, **sem** reinventar RBAC Laravel no curto prazo:

| Chave sugerida | Uso |
|---|---|
| `integracaoVendafFacil` (section) | Ver tela Configurações → Integrações → VendafFacil |
| `integracao.vendaffacil.visualizar` | [PENDENTE] se migrar para permission strings |
| `integracao.vendaffacil.configurar` | Salvar URL/token/mapeamentos |
| `integracao.vendaffacil.testar` | Botão Testar conexão |
| `integracao.vendaffacil.sincronizar` | Sync manual |
| `comercial.delivery.visualizar` | UI delivery remoto |
| `comercial.pedidos.criar` | Criar pedido via API |
| `comercial.pedidos.cancelar` | Cancelar remoto |
| `comercial.fiscal.emitir` | Solicitar emissão |

[RECOMENDAÇÃO] Fase 1: mapear essas chaves como **sections** no mesmo padrão OpenClaw (`openClawIntegracao`). Fase 2: se necessário, tabela `permissoes` + pivot.

---

## 6. Menu, navegação e configurações

### 6.1 Onde o menu é construído

[ENCONTRADO]
- HTML estático: `frontend/index.html` (sidebar `<nav>`)
- Controle de visibilidade: `frontend/app.js` (`PERMISSOES`, `navigateTo`, toggles de submenu)
- Sections registradas em `ALL_NAV_SECTION_IDS`

### 6.2 Configurações (atual)

```
Configuracoes
└── Integrações (divider)
    ├── OpenClaw / Assistente IA   (data-section="openClawIntegracao")
    └── Backup / Restaurar         (botão ADMIN)
```

Arquivos: `frontend/openclaw-integracao.js`, `backend/routes/openclaw_config_routes.php`, `backend/app/Support/OpenClaw/OpenClawSettings.php`.

### 6.3 Comercial (atual)

```
Comercial
├── Dashboard Comercial
├── PDV / Caixa
├── Mesas e Comandas
├── Pedidos
├── Cozinha / Produção
├── Pagamentos
├── Fechamento de Caixa
├── Clientes
├── Histórico de Vendas
├── Relatórios Comerciais
├── Configurações do PDV
└── Fiscal (Em desenvolvimento)
```

[ENCONTRADO] Tudo alimentado por `comercial-pdv.js` com **dados em memória** (`DADOS_DEMONSTRACAO_PDV`). Sem backend comercial de vendas.

### 6.4 Onde encaixar VendafFacil

[RECOMENDAÇÃO]
```
Configuracoes
└── Integrações
    ├── OpenClaw / Assistente IA
    └── VendafFacil                 ← nova section (ex.: vendafFacilIntegracao)
```

E, quando a UI comercial deixar de ser protótipo e passar a consumir API:
```
Comercial
├── Pedidos          → proxy/consulta VendafFacil
├── Delivery         → remoto VendafFacil (não recriar módulo local)
├── Vendas
├── Caixa
└── Fiscal           → solicitar emissão no VendafFacil
```

### 6.5 Resquícios do Delivery removido

| Item | Status | Etiqueta |
|---|---|---|
| `frontend/delivery.js` / `delivery.css` | **Ausentes** | [ENCONTRADO] |
| Menu Delivery / sections `delivery*` | **Ausentes** no HTML/JS ativo | [ENCONTRADO] |
| Rotas/tabelas delivery | **Não encontradas** | [ENCONTRADO] |
| Docs antigos `DELIVERY_*.md` | Removidos | [ENCONTRADO] |
| Menções em docs de mapeamento | “Removido do produto” | [ENCONTRADO] |

[RECOMENDAÇÃO] Delivery futuro deve ser **somente remoto** via VendafFacil, sem reintroduzir protótipo local.

---

## 7. Produtos e estoque

### 7.1 Produtos

[ENCONTRADO] Tabela legada `produtos` (sem migration create no repositório). Validação em `POST /api/produtos`:

| Campo | Uso |
|---|---|
| `nome` | obrigatório |
| `categoria` | string (não há tabela categorias) |
| `unidade_base` | unidade de medida |
| `unidade_id` | unidade organizacional (nullable) |
| `codigo_barras` | código interno (auto `PROD-…` se vazio) |
| `descricao` | texto |
| `custo_medio` | custo |
| `estoque_minimo` | mínimo |
| `ativo` | soft disable |
| `foto` | path upload |

[ENCONTRADO] **Não** há campos fiscais NCM/CFOP/CSOSN no create validado. **Não** há preço de venda comercial persistido no fluxo de produtos de estoque (preço aparece em ficha técnica / protótipo PDV).

### 7.2 Estoque / lotes / movimentações

[ENCONTRADO]
- `stock_lotes` / `lotes` — quantidade, `custo_unitario`, `unidade_id`, validade
- `movimentacoes` — entradas/saídas/perdas (motivo etc.)
- Fichas técnicas: `fichas_tecnicas` (`ingredientes_json`, `preco_prato`, `sugestao_venda`)
- Inventário físico de estoque ciclo completo: **não existe** (há inventário patrimonial)

### 7.3 Campos para sync com VendafFacil

[RECOMENDAÇÃO] Candidatos a sincronização:
- identidade: `id`, `nome`, `codigo_barras`, `categoria`, `ativo`
- estoque: saldo por `unidade_id`, lotes/validade (se VendafFacil consumir disponibilidade)
- custo: `custo_medio` (cuidado: pode ser confidencial — sync seletivo)
- comercial: preços/variações/adicionais — **origem preferencial VendafFacil** se forem produtos de venda

[RECOMENDAÇÃO] Vínculo externo (sem alterar agora):
- preferir tabela `integration_mappings` (genérica);
- alternativa: colunas `vendaffacil_product_id` / `external_id` em `produtos` (fase posterior).

---

## 8. Clientes

### 8.1 O que existe no SAS

| Fonte | Campos | Etiqueta |
|---|---|---|
| `financeiro_clientes` | `nome`, `documento`, `email`, `telefone`, `unidade_id`, `ativo`, `observacoes` | [ENCONTRADO] |
| Menu próprio CRM | **Não** | [ENCONTRADO] |
| Protótipo PDV clientes | nome, telefone, whatsapp, cpf, obs, preferências (só memória) | [ENCONTRADO] |
| Endereço / CEP / LGPD consent | **Não** no `financeiro_clientes` | [ENCONTRADO] |
| Histórico de pedidos | **Não** (sem pedidos reais) | [ENCONTRADO] |

### 8.2 Onde o cliente deve viver

[RECOMENDAÇÃO]
- **Cliente comercial (delivery/PDV):** master no **VendafFacil**; SAS consulta/espelha IDs necessários para financeiro/AR.
- **Cliente financeiro (contas a receber):** pode permanecer no SAS (`financeiro_clientes`) com mapeamento opcional.
- Evitar dual-master sem regras claras de merge CPF/CNPJ.

[PENDENTE] Contrato de API VendafFacil para create/update/search cliente + LGPD.

---

## 9. Formas de pagamento

### 9.1 No SAS hoje

| Área | Situação | Etiqueta |
|---|---|---|
| `financeiro_lancamentos.forma_pagamento` | string livre | [ENCONTRADO] |
| `financeiro_contas_receber.forma_recebimento` | string livre | [ENCONTRADO] |
| `fechamentos_caixa` | auditoria maquinha vs sistema; `linhas_json`; campos `sistema_pdv`, `maquinha` | [ENCONTRADO] |
| Catálogo tipado (Pix, débito, crédito…) | **Não** como tabela | [ENCONTRADO] |
| Protótipo PDV | formas demo em JS | [ENCONTRADO] |
| Máquinas / taxas / parcelamento estruturados | **Não** | [ENCONTRADO] |

[RECOMENDAÇÃO] Equivalência futura via `integration_mappings` (`payment_method` SAS string ↔ id VendafFacil). Fechamento de caixa operacional do PDV deve ser **remoto**; o `fechamentos_caixa` do SAS pode continuar como auditoria interna ou espelho resumido.

---

## 10. Pedidos, vendas, PDV e delivery

| Capacidade | Status no SAS | Etiqueta |
|---|---|---|
| Pedidos reais | Não (só demo JS) | [ENCONTRADO] |
| Vendas | Não | [ENCONTRADO] |
| Carrinho | Demo PDV | [ENCONTRADO] |
| Mesas | **Sim** (`mesas`, `MesaController`) — reservas | [ENCONTRADO] |
| Comandas PDV | Demo | [ENCONTRADO] |
| Delivery | Removido; não recriar localmente | [ENCONTRADO] |
| Retirada/balcão | Só demo | [ENCONTRADO] |
| Pagamentos PDV | Demo | [ENCONTRADO] |
| Status/cancelamento | Demo | [ENCONTRADO] |
| Impressão | Não mapeado como módulo | [ENCONTRADO] |
| Taxas/entregador | Não | [ENCONTRADO] |
| Adicionais / remoção ingredientes | Não no estoque; demo PDV limitado | [ENCONTRADO] |
| Fechamento caixa (auditoria) | **Sim** `fechamentos_caixa` | [ENCONTRADO] |
| Reservas de mesa | **Sim** `reservas_mesas` | [ENCONTRADO] |

### Classificação futura

| Tipo | Exemplos | Etiqueta |
|---|---|---|
| Ficar **local** (SAS) | estoque, compras, fornecedores, perdas, fichas técnicas, reservas de mesa (hoje), RH, patrimônio | [RECOMENDAÇÃO] |
| Ficar **remoto** (VendafFacil) | delivery, PDV vendas, fiscal emissão, comprovantes, status comerciais | [RECOMENDAÇÃO] |
| **Espelhar** | ids de pedidos/vendas, totais do dia, baixas de estoque decorrentes de venda, mapeamento produtos | [RECOMENDAÇÃO] |
| **Conflito** | protótipo Comercial local vs API VendafFacil — não persistir pedidos locais em paralelo | [RISCO] |

[RECOMENDAÇÃO] Reutilizar do protótipo apenas **UX/labels** e estrutura de sections; persistência e regras = VendafFacil.

---

## 11. Fiscal

[ENCONTRADO]
- Emissão NF-e/NFC-e/NFS-e: **não existe**
- Tela `comercialFiscal`: placeholder “em desenvolvimento” no protótipo
- Módulo `impostos`: gestão financeira de impostos (não emissão fiscal de venda)
- `alvaras`: documentos da empresa/unidade
- Certificados A1/A3, XML, DANFE, contingência: **não encontrados**

[RECOMENDAÇÃO] Fluxo futuro (somente desenho):
1. SAS envia payload de venda/pedido + `unidade` mapeada + idempotency-key
2. VendafFacil emite e responde: `status`, `numero`, `chave`, `protocolo`, URLs/base64 `xml`/`pdf`, `mensagem_erro`
3. SAS grava espelho em tabela `integration_fiscal_documents` (futura) + log
4. UI SAS só consulta/baixa PDF — **não** emite localmente

[PENDENTE] Endpoints e layout fiscal exatos do VendafFacil.

---

## 12. Banco de dados

### 12.1 Tabelas relevantes para integração

| Tabela | Finalidade | Model | PK | empresa_id | unidade_id | Notas |
|---|---|---|---|---|---|---|
| `usuarios` | Auth app | `Usuario` | id | não | sim | perfil, permissoes_menu |
| `unidades` | Filiais | `Unidade` | id | não | — | CNPJ por unidade |
| `sistema_configuracoes` | KV config | — | chave | n/a | n/a | openclaw_*, empresa_* |
| `produtos` | Itens estoque | — | id | não | nullable | legado |
| `stock_lotes` / `lotes` | Estoque/lotes | — | id | não | sim | custo, validade |
| `movimentacoes` | Mov. estoque | — | id | não | (via lote/unidade) | perdas etc. |
| `fichas_tecnicas` | Composição | — | id | não | não | ingredientes_json |
| `fornecedores` | Compras | `Fornecedor` | id | não | — | |
| `financeiro_clientes` | Clientes AR | — | id | não | nullable | |
| `financeiro_lancamentos` | Fluxo caixa | — | id | não | nullable | forma_pagamento string |
| `financeiro_contas_receber` | AR | — | id | não | nullable | |
| `fechamentos_caixa` | Auditoria caixa | — | id | não | nullable | linhas_json |
| `mesas` | Mesas | `Mesa` | id | não | sim | |
| `reservas_mesas` | Reservas | `ReservaMesa` | id | não | sim | |
| `audit_logs` | Auditoria | — | id | não | não | |
| `ai_assistant_logs` | Logs OpenClaw/IA | `AiAssistantLog` | id | não | — | padrão de log integração |
| `jobs` / `failed_jobs` | Filas scaffold | — | id | — | — | sem Jobs de domínio |
| `rh_rescisoes` | RH | — | id | nullable | sim | empresa_id pontual |

[ENCONTRADO] Tabelas core de estoque são **legadas** (create migration ausente no repo) — risco de drift de schema entre ambientes.

### 12.2 Conflitos futuros possíveis

| Conflito | Detalhe | Etiqueta |
|---|---|---|
| IDs numéricos | Colisão se espelhar IDs VendafFacil sem namespace | [RISCO] |
| Status enums | Strings livres no SAS vs enums no VendafFacil | [RISCO] |
| Soft delete | `deleted_at` em financeiro; produtos usam `ativo` | [ENCONTRADO] |
| Timestamps | `usuarios.criado_em` vs `created_at` padrão | [ENCONTRADO] |
| Nome “produto” | Estoque SAS ≠ produto comercial VendafFacil | [RISCO] |
| Unidade | Unidade org. SAS vs unidade de medida | [RISCO] ambiguidade semântica |

---

## 13. APIs e integrações existentes

### 13.1 O que já existe

| Capacidade | Situação | Evidência | Etiqueta |
|---|---|---|---|
| Laravel HTTP Client | Sim | `OpenClawConfigController`, `AylaUsuarioController`, `InvestimentoMercado` | [ENCONTRADO] |
| Bearer token | Sim (OpenClaw/Ayla outbound/inbound) | middlewares `openclaw.token`, `ayla.token` | [ENCONTRADO] |
| Webhooks inbound | Não padronizado para ERP externo | — | [ENCONTRADO] |
| Jobs/retries domínio | Scaffold filas; **sem** Jobs app | `app/Jobs` vazio | [ENCONTRADO] |
| Timeout | Pontual (`Http::timeout(15)`) | OpenClaw test | [ENCONTRADO] |
| Circuit breaker | Não | — | [ENCONTRADO] |
| Idempotência | Não genérica | — | [ENCONTRADO] |
| Logs request/response | Parcial (`ai_assistant_logs`, `audit_logs`, `\Log`) | — | [ENCONTRADO] |

### 13.2 Padrão recomendado (não criar agora)

[RECOMENDAÇÃO] Alinhar ao estilo OpenClaw/Ayla:

```
config/vendaffacil.php
app/Support/VendafFacil/VendafFacilSettings.php
app/Services/VendafFacil/VendafFacilClient.php      # Http::withToken, timeout, retries
app/Services/VendafFacil/VendafFacilService.php     # orquestração de domínio
app/Http/Controllers/VendafFacilIntegrationController.php
app/Http/Controllers/VendafFacilWebhookController.php
app/Jobs/SyncVendafFacilJob.php
Models: IntegrationLog, IntegrationMapping
routes/vendaffacil_routes.php  require em api.php
frontend/vendaffacil-integracao.js
```

---

## 14. Segurança e LGPD

| Tema | Situação | Etiqueta |
|---|---|---|
| Auth app | `X-Usuario-Id` sem validação de token de sessão no middleware | [RISCO] |
| CORS | `Access-Control-Allow-Origin: *` em várias respostas | [RISCO] |
| CSRF | Validado no web; exceções kanban; API SPA usa headers | [ENCONTRADO] |
| Rate limit Ayla | `AYLA_RATE_LIMIT` | [ENCONTRADO] |
| Secrets .env | OpenAI, OpenClaw, Ayla tokens (não copiados aqui) | [ENCONTRADO] |
| Tokens em DB | `sistema_configuracoes` texto puro (OpenClaw) | [RISCO] |
| Mascaramento | `OpenClawSettings::mascararToken` | [ENCONTRADO] bom padrão a reusar |
| Crypt Laravel | Disponível; pouco usado para secrets de integração | [RECOMENDAÇÃO] criptografar tokens |
| LGPD clientes | Sem consentimento/endereço no financeiro_clientes | [PENDENTE] |
| Deploy key em query | rota `/api/deploy?key=…` hardcoded no código | [RISCO] |

[RECOMENDAÇÃO] Para VendafFacil:
- nunca logar Authorization/token/webhook secret em claro;
- validar HMAC/assinatura de webhooks;
- escopo por unidade mapeada;
- idempotency-key em POSTs;
- permissões granulares na tela de integração;
- não hardcodar tenant do Grupo Sabor Paraense.

---

## 15. Pontos de integração com o VendafFacil

[RECOMENDAÇÃO] Fronteiras principais:

1. **Configuração / health** — testar API, credenciais, ambiente
2. **Catálogo** — mapear produtos SAS ↔ produtos comerciais VendafFacil
3. **Estoque** — push disponibilidade / pull baixas por venda
4. **Pedidos/Delivery/PDV** — UI SAS → API VendafFacil
5. **Clientes** — busca/criação remota
6. **Pagamentos/Caixa** — consulta status / espelho totais
7. **Fiscal** — solicitar emissão e armazenar espelho
8. **Webhooks** — status pedido, pagamento, documento fiscal

---

## 16. Mapa de correspondência entre entidades

| SAS-Estoque | VendafFacil esperado | Estratégia de vínculo | Observações |
|---|---|---|---|
| Instalação / `sistema_configuracoes` | Empresa (tenant) | config `vendaffacil_empresa_id` + mapping | [PENDENTE] API |
| `unidades.id` | Loja/unidade | `integration_mappings` entity=`unidade` | Obrigatório multiunidade |
| `produtos.id` | Produto comercial / item | mapping `produto` | Nem todo produto estoque vende |
| `codigo_barras` | SKU/código | chave natural auxiliar | Colisões possíveis |
| `financeiro_clientes.id` | Cliente | mapping opcional | Master preferencial VF |
| `forma_pagamento` (string) | Forma pagamento id | mapping `payment_method` | Normalizar catálogo |
| Pedido (não existe) | Pedido | só ID externo + espelho | Não criar dual local |
| Venda (não existe) | Venda | idem | |
| `fechamentos_caixa.id` | Caixa/sessão | mapping fraco / resumo | Auditoria local ≠ caixa VF |
| Documento fiscal (não existe) | NFC-e/NF-e | espelho + mapping | |
| `mesas.id` | Mesa VF (se houver) | mapping se PDV mesa remoto | Reservas SAS podem ficar locais |

[RECOMENDAÇÃO] Preferir **tabela genérica** `integration_mappings`:

```text
id, provider='vendaffacil', empresa_local_id?, unidade_id?,
entity_type, local_id, external_id, external_uuid?,
meta_json, last_synced_at, created_at, updated_at
UNIQUE(provider, entity_type, local_id)
UNIQUE(provider, entity_type, external_id)
```

Usar **UUID/idempotency key** em operações de escrita.

---

## 17. Funcionalidades que devem permanecer no SAS

- Estoque, lotes, validade, movimentações, perdas
- Compras / listas / fornecedores
- Fichas técnicas / composição
- Unidades (cadastro operacional)
- Gestão administrativa, usuários, permissões de menu
- Financeiro gerencial, boletos, despesas, impostos (pagamento), alvarás
- RH, patrimônio, energia, investimento, kanban
- Reservas de mesa (até decisão de unificar com VF)
- Auditoria interna `audit_logs`
- Fechamento de caixa **auditoria** (se ainda necessário além do caixa VF)

---

## 18. Funcionalidades que poderão ficar no VendafFacil

- Clientes comerciais (delivery/PDV)
- Produtos comerciais, variações, adicionais, cardápio/vitrine
- Pedidos, delivery, status, entregadores, taxas
- Vendas, PDV, caixa operacional
- Formas de pagamento comerciais e maquininhas
- Fiscal (emissão, XML, PDF, contingência)
- Comprovantes e histórico comercial canônico

---

## 19. Funcionalidades que precisarão existir nos dois sistemas

| Funcionalidade | SAS | VendafFacil | Sync | Motivo |
|---|---|---|---|---|
| Unidade/loja | Cadastro | Cadastro | mapping | Isolamento multiunidade |
| Produto (venda+insumo) | Insumo/estoque | Comercial | mapping + regras | Baixa estoque por venda |
| Cliente (híbrido) | AR financeiro | Comercial | opcional | Contas a receber |
| Totais de venda do dia | Indicadores/CMV | Origem | pull | DRE/CMV |
| Documento fiscal espelho | Consulta/arquivo | Emissão | push status | Operador no SAS |
| Usuário operador | Auth SAS | (opcional user VF) | [PENDENTE] | Quem emite/cancela |

---

## 20. Riscos e conflitos encontrados

| Risco | Impacto | Probabilidade | Recomendação |
|---|---|---|---|
| Sem tabela empresas / tenant | Alto | Alta | Desenhar tenant genérico antes de multi-cliente |
| Seed/config com marca fixa | Médio | Alta | Remover defaults de cliente específico na fase de implementação |
| Auth só por `X-Usuario-Id` | Alto | Alta | Token/sessão validável + HTTPS |
| CORS `*` | Médio | Alta | Restringir origens em produção |
| Tokens em texto em `sistema_configuracoes` | Alto | Média | `Crypt::encryptString` |
| Protótipo Comercial confundido com real | Alto | Alta | Marcar UI como “via VendafFacil”; não persistir local |
| Produto estoque ≠ produto venda | Alto | Alta | Mapping explícito; não sync cego |
| `api.php` monolítico | Médio | Alta | Novas rotas VF em `vendaffacil_routes.php` |
| Filas sem Jobs de domínio | Médio | Média | Sync assíncrono com `SyncVendafFacilJob` |
| Dualidade fechamento caixa SAS vs caixa VF | Médio | Média | Definir fonte da verdade |
| Rota deploy com chave no código | Alto | Baixa/Média | Remover/proteger (fora do escopo desta doc) |

---

## 21. Arquivos que poderão ser modificados futuramente

| Arquivo | Responsabilidade | Relação com a integração | Risco de alteração |
|---|---|---|---|
| `backend/routes/api.php` | Núcleo API | `require` do novo routes file | Alto (arquivo enorme) |
| `backend/bootstrap/app.php` | Middleware aliases | Alias `vendaffacil.webhook` etc. | Médio |
| `backend/config/services.php` | Credenciais serviços | Entrada vendaffacil | Baixo |
| `backend/.env.example` | Docs env | Chaves sem segredos | Baixo |
| `frontend/index.html` | Menu/sections | Link Integrações + Comercial | Médio |
| `frontend/app.js` | Permissões/navegação | Sections e loaders | Alto |
| `frontend/comercial-pdv.js` | UI comercial | Trocar demo por API client | Alto |
| `backend/routes/configuracoes_routes.php` | Config sistema | Possível leitura resumo | Baixo |
| `docs/SAS_ESTOQUE_*.md` | Docs internos | Atualizar status | Baixo |

---

## 22. Arquivos que poderão ser criados futuramente

| Arquivo | Responsabilidade | Relação com a integração | Risco de alteração |
|---|---|---|---|
| `docs/MAPEAMENTO_SAS_ESTOQUE_INTEGRACAO_VENDAFFACIL.md` | Este mapeamento | Base | — (já criado) |
| `backend/config/vendaffacil.php` | Config tipada | URL, timeouts | Baixo |
| `backend/app/Support/VendafFacil/VendafFacilSettings.php` | Ler/gravar config | Espelha OpenClawSettings | Médio |
| `backend/app/Services/VendafFacil/VendafFacilClient.php` | HTTP | Retries/timeout | Médio |
| `backend/app/Services/VendafFacil/VendafFacilService.php` | Casos de uso | Sync/pedidos/fiscal | Alto |
| `backend/app/Http/Controllers/VendafFacilIntegrationController.php` | Painel API | CRUD config/teste | Médio |
| `backend/app/Http/Controllers/VendafFacilWebhookController.php` | Webhooks | Assinatura | Alto |
| `backend/app/Jobs/SyncVendafFacilJob.php` | Fila | Sync | Médio |
| `backend/app/Models/IntegrationMapping.php` | Vínculos | IDs externos | Médio |
| `backend/app/Models/IntegrationLog.php` | Logs | Auditoria integração | Baixo |
| `backend/routes/vendaffacil_routes.php` | Rotas | Registro limpo | Baixo |
| `backend/database/migrations/*_integration_*.php` | Schema | mappings/logs/config | Médio |
| `frontend/vendaffacil-integracao.js` | UI config | Padrão OpenClaw | Médio |
| `frontend/vendaffacil-comercial.js` | UI pedidos/etc. | Consome API SAS proxy | Alto |

**Não criar agora** — apenas listados.

---

## 23. Proposta da tela Configurações → Integrações → VendafFacil

[RECOMENDAÇÃO] Replicar UX do painel OpenClaw (`openclaw-integracao.js` + section HTML):

**Card status**
- Badge: Conectado / Desconectado / Erro
- Ambiente: Homologação | Produção
- Última sincronização + último erro resumido

**Formulário**
- URL da API (sem slash final normalizado)
- Token (input password; exibir mascarado ao carregar; nunca ecoar completo em logs)
- Timeout (segundos)
- Tentativas (retries)
- Webhook secret (mascarado)
- Empresa correspondente (external id / seleção) — genérico, não nome fixo de cliente
- Unidades SAS ↔ unidades VendafFacil (matriz de mapeamento)
- Recursos habilitados (checkboxes): produtos, estoque, pedidos, delivery, vendas, caixa, fiscal, clientes
- Botões: **Testar conexão**, **Salvar**, **Sincronizar**, **Desconectar** (com confirmação)

**Painéis auxiliares**
- Histórico de erros / `IntegrationLog` (tabela como logs OpenClaw)
- Link para docs da API (quando existir)

**Permissões**
- Visualizar: ADMIN/GERENTE (ou section dedicada)
- Configurar/testar/sincronizar: ADMIN (+ permissão fina)

---

## 24. Fluxos de comunicação propostos

### 24.1 Teste de conexão
```
SAS UI → POST /api/integracoes/vendaffacil/testar
     → VendafFacilClient GET /health (ou equivalente)
     → grava IntegrationLog
     → retorna status mascarando segredos
```

### 24.2 Criar pedido (operador no SAS)
```
SAS UI → SAS API (valida permissão + unidade)
     → VendafFacilClient POST /pedidos (Idempotency-Key)
     → salva mapping pedido
     → (opcional) reserva/baixa estoque local conforme regra
```

### 24.3 Webhook status
```
VendafFacil → POST /api/webhooks/vendaffacil
           → valida assinatura
           → atualiza espelho/status
           → IntegrationLog
```

### 24.4 Fiscal
```
SAS → POST emissão no VendafFacil
   ← status/chave/xml/pdf/erro
   → espelho local para consulta
```

---

## 25. Plano de implantação por fases

| Fase | Conteúdo | Etiqueta |
|---|---|---|
| 0 | Este mapeamento + mapeamento espelho da API VendafFacil | [PENDENTE] lado VF |
| 1 | Config + Client + Testar conexão + logs (sem sync de negócio) | [RECOMENDAÇÃO] |
| 2 | `integration_mappings` + sync produtos/unidades | [RECOMENDAÇÃO] |
| 3 | Pedidos/vendas/delivery read-only na UI Comercial | [RECOMENDAÇÃO] |
| 4 | Escrita (criar/cancelar pedido) + webhooks | [RECOMENDAÇÃO] |
| 5 | Baixa de estoque por venda (regras) | [RECOMENDAÇÃO] |
| 6 | Fiscal solicitar/consultar | [RECOMENDAÇÃO] |
| 7 | Hardening segurança (token encrypt, CORS, auth sessão) | [RECOMENDAÇÃO] |

---

## 26. Checklist antes da implementação

- [ ] API VendafFacil documentada (OpenAPI/Postman) com ambientes homo/prod
- [ ] Decisão de tenant: 1 SAS = 1 empresa VF **ou** multiempresa no SAS
- [ ] Contrato de mapeamento unidade ↔ loja
- [ ] Definir master de produtos comerciais vs insumos
- [ ] Definir master de clientes
- [ ] Estratégia de baixa de estoque (sync imediato vs job)
- [ ] Formato de Idempotency-Key e webhooks assinados
- [ ] Permissões de menu/sections aprovadas
- [ ] Não reintroduzir Delivery local
- [ ] Remover/neutralizar defaults de marca específica em configs novas
- [ ] Plano de não persistir pedidos no protótipo PDV
- [ ] Política de retenção de XML/PDF fiscais
- [ ] Testes de não vazamento cross-unidade
- [ ] Revisar auth `X-Usuario-Id` antes de produção da integração

---

## 27. Dúvidas técnicas que precisam ser respondidas pelo mapeamento do VendafFacil

[PENDENTE]
1. Qual a URL base e versionamento da API (`/api/v1`…)?
2. Auth: Bearer estático, OAuth2 client credentials, ou API key por empresa?
3. Como se representa **empresa** e **unidade/loja** nos recursos?
4. Endpoints de health/ping?
5. CRUD/search de clientes, produtos, pedidos, vendas, caixa, formas de pagamento?
6. Delivery: recursos e machine de status?
7. Fiscal: endpoint de emissão, consulta, cancelamento, contingência; formatos XML/PDF?
8. Webhooks disponíveis, header de assinatura, retries do lado VF?
9. Limites de rate, paginação, filtros por updated_since (sync incremental)?
10. Idempotência: header suportado?
11. Sandbox/homologação com dados fictícios?
12. Campos fiscais obrigatórios no produto (NCM, origem, CSOSN…)?
13. Pedido pode referenciar SKU externo (código SAS) ou só ID VF?
14. Há API para espelhar mesas/comandas ou só delivery/balcão?
15. Multi-tenant: um token acessa só uma empresa?
16. SLA e códigos de erro padronizados?
17. LGPD: endpoints de exclusão/anonimização de cliente?
18. Webhook de pagamento confirmado vs pedido separado?

---

## Anexos — Tabelas obrigatórias consolidadas

### A1. Arquivos relevantes

| Arquivo | Responsabilidade | Relação com a integração | Risco de alteração |
|---|---|---|---|
| `backend/composer.json` | Versões PHP/Laravel | Baseline técnico | Baixo |
| `backend/bootstrap/app.php` | Rotas/middleware | Aliases futuros | Médio |
| `backend/routes/api.php` | API principal | Include routes VF | Alto |
| `backend/routes/openclaw_config_routes.php` | Padrão integração | Modelo a copiar | Baixo |
| `backend/app/Support/OpenClaw/OpenClawSettings.php` | Config+mask token | Modelo Settings | Baixo |
| `backend/app/Http/Middleware/EnsureSasUsuario.php` | Auth header | Segurança chamada UI | Alto |
| `backend/app/Http/Controllers/OpenClawConfigController.php` | Teste HTTP | Modelo controller | Baixo |
| `backend/config/services.php` | Serviços | Entrada VF | Baixo |
| `backend/.env.example` | Env documentado | Chaves VF sem segredo | Baixo |
| `frontend/index.html` | Menu | Slot Integrações/Comercial | Médio |
| `frontend/app.js` | Permissões/nav | Sections VF | Alto |
| `frontend/comercial-pdv.js` | UI comercial demo | Substituir por API | Alto |
| `frontend/openclaw-integracao.js` | UI integração | Template UX | Baixo |
| `backend/database/migrations/2026_06_10_000001_create_sistema_configuracoes_table.php` | KV config | Possível guardar chaves VF | Médio |
| `backend/database/migrations/2026_06_09_000001_create_financeiro_gerencial_tables.php` | Clientes/financeiro | Equivalências | Médio |
| `backend/database/migrations/2026_04_13_000002_create_fechamentos_caixa_table.php` | Caixa auditoria | Conflito com caixa VF | Médio |
| `backend/app/Models/Unidade.php` | Unidades | Mapping loja | Baixo |
| `backend/app/Models/Usuario.php` | Usuários | Permissões | Médio |
| `docs/SAS_ESTOQUE_MAPEAMENTO_COMPLETO.md` | Inventário módulos | Contexto | Baixo |

### A2. Entidades

| Entidade | Tabela | Model | Empresa | Unidade | Possível ID externo |
|---|---|---|---|---|---|
| Usuário | `usuarios` | `Usuario` | não | `unidade_id` | opcional user VF |
| Unidade | `unidades` | `Unidade` | não | PK | `vendaffacil_store_id` |
| Produto | `produtos` | — | não | nullable | `vendaffacil_product_id` |
| Lote/estoque | `stock_lotes`/`lotes` | — | não | sim | raramente |
| Cliente financeiro | `financeiro_clientes` | — | não | nullable | `vendaffacil_customer_id` |
| Lançamento | `financeiro_lancamentos` | — | não | nullable | origem venda VF |
| Fechamento caixa | `fechamentos_caixa` | — | não | nullable | sessão caixa VF |
| Mesa | `mesas` | `Mesa` | não | sim | mesa VF |
| Reserva | `reservas_mesas` | `ReservaMesa` | não | sim | — |
| Config | `sistema_configuracoes` | — | chaves empresa_* | — | empresa VF id |
| Pedido/Venda/Fiscal | — | — | — | — | **somente externos** |

### A3. Correspondência (resumo)

Ver seção **16**.

### A4. Funcionalidades (resumo)

| Funcionalidade | Fica no SAS | Fica no VendafFacil | Sincroniza | Motivo |
|---|---|---|---|---|
| Estoque/compras | Sim | Não | Parcial | Core SAS |
| PDV/vendas | UI | Sim | Sim | Fonte VF |
| Delivery | UI | Sim | Sim | Removido localmente |
| Fiscal emissão | Consulta | Sim | Espelho | Sem motor fiscal SAS |
| Clientes comerciais | Opcional | Sim | Opcional | Evitar dual-master |
| Fechamento auditoria | Sim | Caixa operacional VF | Resumo | Papéis distintos |
| Reservas mesa | Sim (hoje) | [PENDENTE] | [PENDENTE] | Decisão produto |

### A5. Riscos

Ver seção **20**.

### A6. Endpoints que o SAS poderá consumir futuramente

[RECOMENDAÇÃO] / [PENDENTE] paths reais dependem do VendafFacil — sugestão inicial:

| Método | Endpoint sugerido | Finalidade | Dados enviados | Retorno esperado |
|---|---|---|---|---|
| GET | `/api/v1/health` | Teste conexão | — | `{ status }` |
| GET | `/api/v1/empresas/me` | Resolver tenant | — | empresa id/nome |
| GET | `/api/v1/unidades` | Listar lojas | — | lista + ids |
| GET | `/api/v1/produtos` | Sync catálogo | `updated_since` | página produtos |
| POST | `/api/v1/produtos/map` | Confirmar vínculo | local_sku, external_id | mapping |
| GET | `/api/v1/clientes` | Busca | documento/telefone | cliente |
| POST | `/api/v1/clientes` | Criar | dados LGPD | id |
| POST | `/api/v1/pedidos` | Criar pedido | itens, unidade, pagamentos | pedido+status |
| GET | `/api/v1/pedidos/{id}` | Consultar | — | detalhe |
| POST | `/api/v1/pedidos/{id}/cancelar` | Cancelar | motivo | status |
| GET | `/api/v1/vendas` | Histórico | filtros | lista |
| GET | `/api/v1/caixa/sessao-atual` | Caixa | unidade | totais |
| POST | `/api/v1/fiscal/emitir` | Emitir doc | venda_id, tipo | status/chave |
| GET | `/api/v1/fiscal/{id}` | Consultar doc | — | xml/pdf/status |
| GET | `/api/v1/formas-pagamento` | Catálogo | — | lista |

---

## Confirmação de escopo desta tarefa

- **Nenhum** arquivo de código de aplicação foi alterado para implementar integração.
- **Nenhuma** migration foi criada/executada.
- **Nenhum** comando de sistema foi necessário para a entrega do documento (análise por leitura de arquivos).
- Artefato único criado: este Markdown.

**Nome oficial do parceiro de integração neste documento:** VendafFacil (`vendaffacil`).
