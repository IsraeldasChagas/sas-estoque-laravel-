# SAS-Estoque — Mapeamento Completo

**Data:** 2026-07-14  
**Escopo:** análise somente leitura do código em `sas-estoque-laravel`  
**Critério:** “feito” = implementação comprovada em menu/tela + backend/rotas + banco (quando aplicável) + fluxo utilizável.  
**Não implementado nesta tarefa:** nenhuma alteração de código, banco, menus, Ayla ou permissões.

---

## 1. Resumo executivo

O SAS-Estoque é um monólito Laravel + SPA HTML/JS com o núcleo operacional (estoque, produtos, compras, usuários) concentrado em closures em `backend/routes/api.php` (~11k linhas), e módulos verticais posteriores em arquivos `*_routes.php` (energia, patrimônio, investimento, financeiro gerencial, RH rescisão, Ayla, OpenClaw).

**Contagens (inventário desta análise):**

| Classificação | Qtd. de itens |
|---|---:|
| **A. Funcional** | 48 |
| **B. Parcial** | 14 |
| **C. Estrutura inicial** | 3 |
| **D. Legado / duplicado** | 12 |
| **E. Não existe** | 22 |
| **Total avaliado** | **99** |

**Ayla (confirmado via `php artisan route:list --path=ayla/v1`):** 18 rotas GET de consulta + 1 POST de autenticação (`/acesso/validar`). Sem escrita de domínio. MCP em-repo cobre só Kanban + Patrimônio; a API HTTP da Ayla também cobre estoque/ops.

**Maior risco estrutural:** lógica de negócio espalhada em closures gigantes de `api.php` (difícil testar, revisar e integrar); tabelas core legadas sem migration create no repositório; dualidade auth `usuarios` vs scaffold `users`.

---

## 2. Arquitetura atual

```
Frontend (SPA)
  index.html + app.js + módulos *.js
       │  X-Usuario-Id
       ▼
Laravel routes/api.php  (+ *_routes.php)
       │
  Controllers (minoritário) | Closures (majoritário)
       │
  Services (Ayla, SasIa*, EntradaEstoque, Boleto…)
       │
  MySQL (tabelas core legadas + migrations 2026_*)

Integrações laterais:
  Telegram bridge (openclaw/telegram-auth-bridge)
  → autentica via POST /api/ayla/v1/acesso/validar
  → OpenClaw (VPS) → HTTP/MCP → /api/ayla/v1/*
  Painel OpenClaw legado → /api/openclaw/* e /api/ia/* (token openclaw)
```

**Evidências:**
- Registro de rotas: `backend/bootstrap/app.php`, `backend/routes/api.php` L11148–11159
- Middleware: `sas.usuario`, `ayla.token`, `openclaw.token`
- Frontend sections: `frontend/index.html` + `frontend/app.js` (`ALL_NAV_SECTION_IDS`, `PERMISSOES`, `navigateTo`)

---

## 3–9. Lista completa dos módulos (por classificação)

### A. FUNCIONAL (confirmado)

Evidência típica: menu (`data-section`) + seção HTML + loader JS + rotas API + tabela/migration (ou alter legado) + permissões.

| Módulo | Menu | Frontend | Backend | Banco | Ayla |
|---|---|---|---|---|---|
| Dashboard principal | `dashboard` | `app.js` `loadDashboard` | rotas dashboard em `api.php` | agrega outras | parcial (via `/dashboard`) |
| Boas-vindas / Minha conta | sim | HTML + app.js | usuários/me | `usuarios` | não |
| Unidades | `unidades` | app.js | `/api/unidades*` | `unidades` (legado, model `Unidade`) | **sim** `/unidades` |
| Usuários | `usuarios` | app.js | `/api/usuarios*` (closures) | `usuarios` | não |
| Produtos | `produtos` | app.js | `/api/produtos*` | `produtos` (legado) | **sim** `/produtos*` |
| Ficha técnica | `fechaTecnica` | app.js | `/api/fichas-tecnicas*` | `fichas_tecnicas` | não |
| Consulta estoque | `estoque` | app.js | `/api/estoque/*`, entradas/saídas | `lotes`, `movimentacoes` | **sim** `/estoque` |
| Lotes | `lotes` | app.js | `/api/lotes*` | `lotes` | **sim** `/lotes/vencendo` |
| Locais | `locais` | app.js | `/api/locais*` | locais (legado/runtime) | não |
| Movimentações | `movimentacoes` | app.js | `/api/movimentacoes*` | `movimentacoes` | **sim** `/estoque/movimentacoes` |
| Lista de compras | `compras` | app.js | `/api/listas*` | listas/itens legado | **sim** `/compras` |
| Fornecedores | `fornecedores` | app.js | `FornecedorController` | `fornecedores` | **sim** `/fornecedores` |
| Relatórios (estoque) | `relatorios` | app.js | rotas relatório em `api.php` | várias | parcial (`/relatorios/unidade/{id}`) |
| Logs / auditoria | `logs` | app.js | `/api/audit-logs*` | `audit_logs` | não |
| Kanban administrativo | `kanbanAdministrativo` | app.js + blade web | `KanbanTaskController` | `kanban_tasks` | **sim** `/kanban` |
| Fechamento de caixa | `fechamento`, `fechamentoDash` | app.js | `/api/fechamentos-caixa*` | `fechamentos_caixa` | não |
| Boletos | `boletao` | app.js | `BoletoController` | `boletos`, `boleto_anexos` | não |
| Impostos | `impostos` | `impostos.js` | `ImpostoController` | `impostos` | não |
| Documentos empresa (alvará) | `alvara` | app.js | `AlvaraController` | `alvaras` | não |
| Proventos | `proventos` | app.js | `/api/proventos*` | `proventos*` | não |
| Despesas fixas | `despesasFixas` | app.js | `/api/despesas-fixas*` | `despesas_fixas*` | não |
| Vale / consumo | `valeConsumo` | app.js | `/api/financeiro/vale-consumo*` | `financeiro_vale_consumo` | não |
| Recibo ajuda de custo | `reciboAjuda` | app.js | `/api/recibos-ajuda*` | `recibos_ajuda_custo` | não |
| Financeiro — Dashboard | `financeiroDashboard` | `financeiro-gerencial.js` | `financeiro_routes.php` | `financeiro_*` | não |
| Financeiro — Fluxo de caixa | `financeiroFluxoCaixa` | idem | CRUD `/financeiro/fluxo-caixa*` | `financeiro_lancamentos` | não |
| Financeiro — Contas a receber | `financeiroContasReceber` | idem | CRUD `/financeiro/contas-receber*` | `financeiro_contas_receber` | não |
| Financeiro — DRE | `financeiroDre` | idem | GET `/financeiro/dre` | lançamentos/cálculo | não |
| Financeiro — Saídas estoque (CMV) | `financeiroCmv` | idem | GET `/financeiro/cmv` | mov. estoque | não |
| Financeiro — Centros de custo | `financeiroCentrosCusto` | idem | `/financeiro/centros-custo*` | `financeiro_centros_custo` | não |
| Financeiro — Orçamento | `financeiroOrcamento` | idem | `/financeiro/orcamento*` | `financeiro_orcamentos` | não |
| Financeiro — Indicadores | `financeiroIndicadores` | idem | GET `/financeiro/indicadores` | cache indicadores | não |
| Reservas (mesa) | `reservaMesa` | app.js | `MesaController`, `ReservaMesaController` | `mesas`, `reservas_mesas` | não |
| Histórico reservas | `historicoReservas` | app.js | idem | idem | não |
| Funcionários | `funcionarios` | app.js | `/api/funcionarios*` | `funcionarios` | não |
| RH — Relatório | `rhRelatorios` | app.js | api RH + funcionários | funcionários | não |
| RH — Folha de ponto | `rhFolhaPonto` | app.js | `RhFolhaPontoController` | `rh_folhas_ponto` | não |
| RH — Dashboard recrutamento | `rhDashboard` | app.js | `RhDashboardController` | rh_* | não |
| RH — Vagas | `rhVagas` | app.js + público web | `RhVagaController`, `RhPublicoController` | `rh_vagas` | não |
| RH — Candidatos | `rhCandidatos` | app.js | `RhCandidatoController` | `rh_candidatos` | não |
| RH — Rescisão (6 telas) | `rhRescisao*` | `rh-rescisao.js` | `rh_rescisao_routes.php` | `rh_rescisoes*` | não |
| Energia (4 telas) | `energia*` | `energia.js` | `energia_routes.php` | `energia_equipamentos_consumo` | não |
| Patrimônio (8 telas) | `patrimonio*` / `patrimonios` | `patrimonio.js` | `patrimonio_routes.php` + reports | `patrimonio_*` | **sim** `/patrimonio*` |
| Investimento (6 telas) | `investimento*` | `investimento.js` | `investimento_routes.php` | `investimento_*` | não |
| Configurações — Painel | `configuracoesPainel` | `configuracoes-painel.js` | `configuracoes_routes.php` | `sistema_configuracoes` | não |
| Tema / cores | — (painel) | tema UI | `tema_routes.php` | sistema_configuracoes | não |
| OpenClaw — painel config | `openClawIntegracao` | `openclaw-integracao.js` | `/api/openclaw/*` | config + `ai_assistant_logs` | não (é integração paralela) |
| Ayla IA — admin (6 telas) | `ayla*` | `ayla-ia.js` | `/api/ayla-admin/*` | `ayla_*` | painel da própria Ayla |
| Backup / Restaurar | botão nav | modal app.js | rotas admin backup em `api.php` | dump/restore | não |

### B. PARCIAL

| Módulo | O que existe | O que falta | Evidência |
|---|---|---|---|
| RH — Entrevistas | HTML `rhEntrevistasSection`, API `RhEntrevistaController` `/api/rh/entrevistas*` | **Sem link no menu**; não está nos profiles default | `index.html`, `api.php`, `app.js` |
| RH — Banco de talentos | HTML `rhBancoTalentosSection`, loader app.js | **Sem link no menu** (só em PERMISSOES ADMIN/GERENTE) | idem |
| Inventário de estoque | Grade “Consulta Estoque” + edição | **Não** há módulo/rota `inventario` de estoque dedicado (só inventário **patrimonial**) | menu `estoque`; patrimônio tem `patrimonioInventario` |
| Contas a pagar | Fluxo via **boletos** + despesas fixas | Sem módulo nomeado “contas a pagar” unificado | `boletao`, `despesasFixas` |
| Clientes (financeiro) | API `/financeiro/clientes` + tabela `financeiro_clientes` | Sem item de menu próprio; subordinado a Contas a Receber | `financeiro_routes.php` |
| Sugestões / compras inteligentes | `GET /api/sugestoes-compras` | Sem tela dedicada destacada no menu | `api.php` |
| Garantia patrimonial | Alertas Ayla leem JSON `dados_especificos` | **Sem coluna** `vencimento_garantia` no banco | `AylaPatrimonioService` |
| MCP SAS-Estoque | `tools.json` com kanban + patrimônio; `docs/mcp/patrimonio-tools.mjs` | Não expõe o restante da API Ayla (estoque, etc.) | `openclaw/mcp-sas-estoque/tools.json` |
| Skill OpenClaw legado | `skill-sas-estoque` aponta `/api/ia` | Paralelo/conflitante com skill Ayla `/api/ayla/v1` | `openclaw/skill-sas-estoque/SKILL.md` |
| WhatsApp | campos `whatsapp`, links `wa.me` | Sem API WhatsApp Business / envio automatizado | `api.php`, funcionários |
| Audio Ayla | chaves `ayla_audio_*` no painel | Integração runtime TTS/STT no OpenClaw fora do backend Laravel | `AylaSettings` |
| Recibo ajuda (F5) | Clique no menu carrega; loader frágil no hard refresh | Soft gap UX | frontend inventory |
| SasIa tools registry | dezenas de tools (RH, financeiro, energia…) | **Não** há endpoints Ayla para a maioria; só serviços internos | `SasIaToolRegistry` vs `ayla_routes.php` |
| Testes automatizados | `AylaApiTest` (22 cases) + unit RH migration | Cobertura mínima fora da Ayla; `ExampleTest` scaffold falha (GET `/` → 302) | `php artisan test` |

### C. ESTRUTURA INICIAL

| Item | Evidência |
|---|---|
| Menu “Manutenção” genérico | Contém apenas filhos **Energia**; sem módulo “manutenção geral” | `index.html` submenu Manutenção |
| Jobs / filas de domínio | Scaffold `jobs` migration; pasta `app/Jobs` **inexistente** | Glob `app/Jobs` |
| Base de conhecimento / agentes IA | Tabelas `ai_documents`, `ai_agents*` ainda no banco; UI/chat removidos na limpeza | migrations `2026_06_17_*`, `2026_06_23_*`; FE limpo |

### D. LEGADO / DUPLICADO

| Item | Evidência |
|---|---|
| Chat Assistente IA / SAS IA / Agentes (UI) | Removidos do frontend (`backup/ia-legada/`); rotas `ia_routes`/`sas_ia_routes`/`ai_agent_routes` removidas | docs `IA_LEGADA_*` |
| Tabelas `ai_conversations`, `ai_messages`, `ai_tool_logs`, `ai_agents`, `ai_agent_modules` | Migradas; models/rotas de chat removidos; `ai_documents` mantido por DI SasIa | `IA_LEGADA_BANCO_DADOS.md` |
| `UsuarioController` | Arquivo existe; API real é closure em `api.php` | sem Route:: → Controller |
| Tabela Laravel `users` + model `User` | Scaffold; app usa `usuarios` | migration `0001_01_01_000000` |
| `fornecedores_backup` | Tabela com migration; uso operacional secundário | migration `2026_03_07_000002` |
| Entry `fechamento` duplicada no menu | Dois links com mesmo `data-section` | `index.html` |
| OpenClaw `/api/ia/*` vs Ayla `/api/ayla/v1/*` | Duas APIs paralelas read/write (OpenClaw tem ações) | `ai_assistant_routes.php`, `ayla_routes.php` |
| Handlers bootstrap para `api/sas-ia/upload-*` | Exceções mencionadas; **rotas não registradas** | `bootstrap/app.php` |
| Scripts `frontend/scripts/*.js` | Ferramentas encoding/dev; não no HTML | inventário frontend |
| Router legado `src/routes/router.js` | Carregado; inerte com sections estáticas | `index.html` |
| Documentação / skill antiga `SAS_ESTOQUE_API_URL=/api/ia` | Pode confundir deploy | `skill-sas-estoque`, `instalar-skill.sh` |

### E. NÃO EXISTE (procurado; não encontrado implementação real)

| Item | Busca |
|---|---|
| PDV / caixa operador (módulo próprio) | Só campos “PDV” no **fechamento de caixa** (auditoria maquinha vs sistema) |
| Vendas (módulo) | Não há `/vendas` / menu vendas |
| Cardápio digital | Não encontrado |
| Delivery / entregadores / taxa entrega | Removido do produto |
| Fidelidade | Não encontrado |
| CRM comercial | Não encontrado (só `financeiro_clientes` de AR) |
| NF-e / NFC-e / NFS-e (emissão) | Não encontrado (apenas campos nota em lotes/UI) |
| Produção / PCP | Não encontrado (ficha técnica ≠ produção) |
| Escalas de trabalho | Não encontrado |
| Folha salarial completa | Não encontrado (só folha de **ponto**) |
| Atestados / Advertências (módulos) | Não encontrado |
| Férias (módulo) | Só campos no **cálculo de rescisão** |
| Comissões | Não encontrado |
| Inventário físico de estoque (ciclo completo) | Não encontrado |
| iFood API | Só rótulo “Cartão iFood” em proventos |
| OSRM / roteamento | Não encontrado |
| Stripe / Pagar.me | Não encontrado |
| Categorias de produto (módulo) | Não encontrado como entity separada no menu |

---

## 10. Integrações existentes

| Integração | Status | Evidência |
|---|---|---|
| Telegram (auth bridge) | **Funcional (parcial pipeline)** | `openclaw/telegram-auth-bridge/server.js` + `POST /ayla/v1/acesso/validar` |
| OpenClaw (config + API `/ia`) | **Funcional** | `openclaw_config_routes.php`, `ai_assistant_routes.php`, painel FE |
| Ayla API v1 | **Funcional (somente leitura)** | `ayla_routes.php`; `AylaApiTest` 22 passed |
| Ayla admin panel | **Funcional** | `ayla_admin_routes.php`, `ayla-ia.js` |
| MCP tools (repo) | **Parcial** | só kanban+patrimonio em `tools.json` |
| OpenAI | **Config / serviços SasIa** | `config/openai.php`; chave usada historicamente pelo motor SasIa (chat removido; tools permanecem) |
| WhatsApp | **Parcial (deeplink)** | `wa.me` / campos texto |
| Slack webhook logging | Config Laravel | `LOG_SLACK_WEBHOOK_URL` |
| Emissão fiscal | **Não existe** | — |
| Pagamentos online | **Não existe** | — |
| iFood / OSRM | **Não existe** como API | — |

---

## 11. Situação da Ayla (detalhada)

### 11.1 Endpoints `/api/ayla/v1/*` (confirmado `route:list`)

| Método | Rota | Controller |
|---|---|---|
| GET | `/status` | `AylaController@status` |
| GET | `/unidades` | `unidades` |
| GET | `/produtos`, `/produtos/abaixo-minimo` | `produtos*` |
| GET | `/estoque`, `/estoque/movimentacoes` | `estoque`, `movimentacoes` |
| GET | `/lotes/vencendo` | `lotesVencendo` |
| GET | `/compras` | `compras` |
| GET | `/fornecedores` | `fornecedores` |
| GET | `/dashboard` | `dashboard` |
| GET | `/kanban` | `kanban` |
| GET | `/patrimonio`, `/resumo`, `/alertas`, `/unidade/{id}`, `/{id}` | `patrimonio*` |
| GET | `/relatorios/unidade/{id}` | `relatorioUnidade` |
| POST | `/acesso/validar` | `validarAcesso` (auth Telegram; **não** escrita de domínio) |

### 11.2 Services usados
- `AylaApiService` (`TOOLS_PERMITIDAS`)
- `AylaAccessService`
- `Ayla\AylaKanbanService`
- `Ayla\AylaPatrimonioService`
- `SasIaToolService` / `SasIaModuleQueryService` (estoque/ops)
- `AiAssistantService::relatorioUnidade` (relatório)
- Support: `AylaSettings`, `AylaResponse`, `CheckAylaToken`, models `AylaAuditLog`, `AylaUsuarioAutorizado`

### 11.3 Ferramentas MCP (repo)
`kanban_consultar`, `patrimonio_consultar`, `patrimonio_resumo`, `patrimonio_detalhar`, `patrimonio_por_unidade`, `patrimonio_alertas`  
Arquivo: `openclaw/mcp-sas-estoque/tools.json` + entrega VPS `docs/mcp/patrimonio-tools.mjs`

### 11.4 Módulos que a Ayla **realmente** consulta
estoque/ops (produtos, unidades, estoque, movimentações, lotes, compras, fornecedores, dashboard, relatório unidade), **kanban**, **patrimônio**.

### 11.5 No painel / registry mas **sem** endpoint Ayla
Tools em `SasIaToolRegistry` para RH, financeiro, reservas, energia, investimento, logs, etc. — **sem** rota `/api/ayla/v1/...` correspondente. Não contabilizar como “Ayla acessa”.

### 11.6 Permissões
- Token Bearer (`ayla.token`)
- `AylaSettings::unidadesPermitidas()` / `unidadePermitida()`
- Com `X-Usuario-Id`: `SasIaContext` + `permissoes_menu` ∩ módulos da ferramenta
- Admin Ayla: usuários em `ayla_usuarios_autorizados`
- Módulos anunciados em `/status`: `AylaSettings::modulosLiberados()` = dashboard, unidades, produtos, estoque, movimentacoes, lotes, compras, fornecedores, relatorios, kanban, patrimonio

### 11.7 Escrita habilitada?
- **Domínio:** não (só GET).
- **Auth:** POST `/acesso/validar`.
- **Admin:** CRUD em `/api/ayla-admin/*` (config/usuários Ayla), fora do escopo de negócio.

### 11.8 Ainda não integrados à Ayla (módulos SAS funcionais sem endpoint)
Finanças (boletos, DRE, fluxo, CR, fechamento…), RH completo, reservas/mesas, energia, investimento, ficha técnica, impostos, alvarás, proventos, despesas, vale, recibos, logs, configurações.

---

## 12. Riscos técnicos

1. **Monólito de closures em `api.php`** — alta complexidade, baixo isolamento, regressões difíceis.  
2. **Core sem migrations create** (`produtos`, `unidades`, `usuarios`, `lotes`, `movimentacoes`, `listas_*`) — onboarding/ambiente novo frágil.  
3. **Duas APIs de IA** (OpenClaw `/ia` com escrita vs Ayla somente leitura) — risco de confusão operacional.  
4. **MCP incompleto vs API** — Telegram/OpenClaw pode “ver” só parte do que a API oferece.  
5. **Tabelas `ai_*` órfãs** pós-limpeza — dívida de banco; risco de confusão.  
6. **Permissões custom vs menu** — telas RH sem nav dependem de hash/`permissoes_menu`.  
7. **`ExampleTest` falhando** — ruído em CI (`GET /` → 302).  
8. **Depreciação patrimônio / garantia** — dados opcionais/JSON; alertas Ayla podem vir vazios.

---

## 13. Débitos técnicos

- Extrair domains de `api.php` para controllers/services.  
- Publicar migrations create do core legado (ou dump schema versionado).  
- Unificar skill OpenClaw na base Ayla (`/api/ayla/v1`).  
- Ampliar MCP tools para endpoints Ayla já existentes.  
- Remover ou arquivar `UsuarioController` e tabelas `ai_*` candidatas (após backup).  
- Linkar ou ocultar RH Entrevistas / Banco de talentos.  
- Ampliar testes Feature além da Ayla.  
- Documentar dualidade `users` vs `usuarios`.

---

## 14. Prioridades recomendadas

1. **Integração Ayla — Financeiro (somente leitura)** — alto valor gerencial.  
2. **Integração Ayla — RH resumo / funcionários** — já há tools SasIa internas.  
3. **Integração Ayla — Reservas / fechamento** — operação diária.  
4. **Completar MCP** com tools para estoque/dashboard já na API.  
5. **Unificar documentação deploy** (Napoleon + VPS) na Ayla.  
6. **Menu RH incompleto** (entrevistas / banco).  
7. **Limpeza controlada de tabelas `ai_*` órfãs** (sem DROP automático em prod).  
8. **Refatoração gradual de `api.php`**.  
9. **Decisão de produto:** PDV / fiscal — planejar do zero se forem roadmap. Delivery foi removido do escopo.  
10. **Cobertura de testes** nos módulos financeiros/RH.

Ordem sugerida de desenvolvimento (após estabilizar Ayla ops+kanban+patrimônio):

1. Ayla financeiro read-only  
2. Ayla reservas + fechamento resumo  
3. Ayla RH resumo  
4. MCP parity  
5. Correções de UX/menu RH  
6. (Futuro) PDV/fiscal apenas se houver escopo explícito

---

## Verificações executadas

| Comando | Resultado |
|---|---|
| `php artisan route:list --path=ayla/v1` | 19 rotas (18 + OPTIONS) |
| `php artisan test --filter=AylaApiTest` | 22 passed |
| Buscas FE/BE por PDV, NFe, ifood… | documentadas nas seções E/B |
| Glob `app/Jobs` | **inexistente** |
| Inventário migrations | 94 arquivos |
| Cross menu ↔ section ↔ loader | inventário frontend |

**Não executado:** migração em produção / inspeção live do MySQL (sem DROP/SELECT prod). Contagens de registros em tabelas: **não** verificadas em banco vivo.

---

## Critério de confiança (exemplos)

| Afirmação | Critério | Evidência |
|---|---|---|
| Ayla patrim. existe | Confirmado | `ayla_routes.php`, `route:list`, `AylaApiTest` |
| PDV módulo existe | Não encontrado | só campos em fechamento |
| RH Entrevistas usável via menu | Inconsistente | API+HTML sim; menu não |
| IA chat SAS | Legado removido FE | backup + docs limpeza; tabelas still |

---

*Documento gerado por análise estática de código. Complementar com validação em homologação (navegação real + dados) antes de decisões de roadmap.*
