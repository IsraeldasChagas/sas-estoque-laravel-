# AYLA — Inventário Técnico do SAS-Estoque

> **Data do levantamento:** 2026-07-12  
> **Escopo:** `sas-estoque-laravel/` (backend Laravel + frontend SPA)  
> **Objetivo:** Base para integração da assistente Ayla sem duplicar funcionalidades existentes.

---

## 1. Visão geral da arquitetura

| Camada | Tecnologia | Observação |
|--------|------------|------------|
| Backend | Laravel (PHP) | API REST em `/api` |
| Frontend | HTML + JS vanilla | SPA em `frontend/` |
| Banco | MySQL | Tabelas legadas + migrations Laravel |
| Auth padrão | Header `X-Usuario-Id` | Lookup em `usuarios` (ativo=1) |
| Auth externa | Bearer token | Apenas OpenClaw (`openclaw.token`) |
| ORM | Misto | Eloquent em módulos novos; `DB::table()` em estoque legado |
| Policies/Repositories | **Não existem** | Permissões via closures nos routes |

### Estrutura de pastas relevante

```
backend/
  routes/
    api.php              # ~11.160 linhas — monólito + requires modulares
    *_routes.php         # 15 arquivos modulares
  app/
    Http/Controllers/    # 20 controllers
    Http/Middleware/     # 2 middlewares
    Models/              # 21 models Eloquent
    Services/            # 8 services
    Support/             # Helpers, cálculos, registries IA
  database/migrations/   # 92 migrations
frontend/
  index.html             # Menu + seções
  app.js                 # Navegação, permissões, fetchJSON
  config.js              # API_URL
```

### Arquivos de rotas incluídos por `api.php` (ordem)

1. `energia_routes.php`
2. `patrimonio_routes.php` → `patrimonio_reports.php`
3. `investimento_routes.php`
4. `financeiro_routes.php`
5. `configuracoes_routes.php`
6. `ia_routes.php` (legado)
7. `sas_ia_routes.php`
8. `imposto_routes.php`
9. `ai_agent_routes.php`
10. `tema_routes.php`
11. `rh_rescisao_routes.php`
12. `ai_assistant_routes.php` (OpenClaw)
13. `openclaw_config_routes.php`

---

## 2. Sistema de permissões

### 2.1 Autenticação

| Mecanismo | Onde | Como funciona |
|-----------|------|---------------|
| `X-Usuario-Id` | Maioria das rotas | ID do usuário logado; validado contra `usuarios.ativo=1` |
| `Authorization: Bearer` | OpenClaw `/ia/*` | Token em `.env` ou `sistema_configuracoes` |
| `sas.usuario` middleware | Kanban web, grupo RH | `EnsureSasUsuario.php` |
| Login | `POST /api/login` | Email + senha → retorna user com `id`, `perfil`, `permissoes_menu` |

### 2.2 Perfis (`usuarios.perfil`)

| Perfil | Label UI |
|--------|----------|
| `ADMIN` | Administrador |
| `GERENTE` | Gerente |
| `FINANCEIRO` | Financeiro |
| `ASSISTENTE_ADMINISTRATIVO` | Auxiliar Administrativo |
| `ATENDENTE_CAIXA` | Atendente Caixa |
| `FUNCIONARIO` | Funcionário |
| `ESTOQUISTA` | Estoquista |
| `COZINHA` | Cozinha |
| `BAR` | Bar |
| `ATENDENTE` | Atendente |
| `VISUALIZADOR` | Visualizador |

### 2.3 Permissões por módulo (`permissoes_menu`)

- Coluna JSON em `usuarios`
- Array de chaves de seção (ex.: `["produtos","estoque","compras"]`)
- Se preenchido, **substitui** o padrão do perfil
- Chaves = `ALL_NAV_SECTION_IDS` em `frontend/app.js`
- Backend SAS IA valida via `SasIaContext::podeUsarFerramenta()` contra `SasIaToolRegistry::mapaModulos()`

### 2.4 Flags de capacidade (frontend `PERMISSOES`)

| Flag | Perfis com acesso |
|------|-------------------|
| `canManageUsuarios` | ADMIN |
| `canManageProdutos` | ADMIN, GERENTE, ESTOQUISTA, COZINHA, BAR |
| `canManageUnidades` | ADMIN, GERENTE |
| `canManageCompras` | ADMIN, GERENTE, ESTOQUISTA, etc. |
| `canRegistrarMovimentacoes` | ADMIN, GERENTE, ESTOQUISTA, COZINHA, BAR |

---

## 3. Menu do sistema (frontend)

### 3.1 Estrutura de submenus

| Submenu | Seções (chaves) |
|---------|-----------------|
| *(topo)* | `dashboard`, `kanbanAdministrativo`, `unidades`, `usuarios`, `produtos`, `fechaTecnica`, `estoque`, `lotes`, `locais`, `movimentacoes`, `compras`, `relatorios`, `fornecedores` |
| Fechamento | `fechamento`, `fechamentoDash` |
| Reserva | `reservaMesa`, `historicoReservas` |
| RH | `funcionarios`, `rhRelatorios`, `rhFolhaPonto` + Recrutamento + Rescisão |
| RH → Recrutamento | `rhDashboard`, `rhVagas`, `rhCandidatos`, `rhEntrevistas`, `rhBancoTalentos` |
| RH → Rescisão | `rhRescisaoDashboard`, `rhRescisaoSimulador`, `rhRescisaoCalculo`, `rhRescisaoComparativo`, `rhRescisaoHistorico`, `rhRescisaoRelatorios` |
| Manutenção → Energia | `energiaDashboard`, `energiaEquipamentos`, `energiaProjecao`, `energiaRelatorios` |
| Financeiro | `boletao`, `impostos`, `alvara`, `proventos`, `despesasFixas`, `valeConsumo`, `reciboAjuda`, `financeiroDashboard`, `financeiroFluxoCaixa`, `financeiroContasReceber`, `financeiroDre`, `financeiroCmv`, `financeiroCentrosCusto`, `financeiroOrcamento`, `financeiroIndicadores` |
| Investimento | `investimentoDashboard`, `investimentoReservas`, `investimentoSimulador`, `investimentoCarteira`, `investimentoResgates`, `investimentoRelatorios` |
| Patrimônio | `patrimonioDashboard`, `patrimonios`, `patrimonioCategorias`, `patrimonioMovimentacoes`, `patrimonioManutencoes`, `patrimonioInventario`, `patrimonioRelatorios`, `patrimonioConfiguracoes` |
| IA | `sasIa`, `sasIaDocumentos`, `sasIaConfiguracoes`, `iaAgentes`, `iaAssistente`, `iaConfiguracoes` |
| Configurações | `configuracoesPainel`, `openClawIntegracao`, `iaAgentes`, Backup (ADMIN) |
| Administração | `logs` |

---

## 4. Inventário por módulo

Legenda de ações: **C**=criar, **E**=editar, **X**=excluir, **Q**=consultar, **A**=aprovar, **N**=cancelar, **P**=exportar/PDF

---

### 4.1 Dashboard

| Item | Detalhe |
|------|---------|
| Seção menu | `dashboard` |
| Tabelas | `produtos`, `stock_lotes`, `movimentacoes`, `lotes`, `listas_compras` |
| Controllers | *(closures em api.php)* |
| Rotas principais | `GET /estoque-abaixo-minimo`, `GET /perdas-recentes`, `GET /lotes-a-vencer`, `GET /estoque/resumo`, `GET /lotes/stats` |
| Permissões | Todos os perfis operacionais |
| Ações API | **Q** apenas |
| Ferramenta SAS IA | `consultar_resumo_produtos`, `consultar_produtos_abaixo_estoque_minimo`, `consultar_kanban_resumo` |

---

### 4.2 Kanban Administrativo

| Item | Detalhe |
|------|---------|
| Seção menu | `kanbanAdministrativo` |
| Tabelas | `kanban_tasks` |
| Model | `KanbanTask` |
| Controller | `KanbanTaskController` |
| Rotas | `GET/POST /kanban-tasks`, `PUT/DELETE /kanban-tasks/{id}`, `PATCH /kanban-tasks/{id}/status` |
| Middleware | `sas.usuario` |
| Ações API | **C, E, X, Q** |
| Ferramenta SAS IA | `consultar_kanban_resumo` |

---

### 4.3 Unidades

| Item | Detalhe |
|------|---------|
| Seção menu | `unidades` |
| Tabelas | `unidades` |
| Model | `Unidade` |
| Rotas | `GET/POST /unidades`, `GET/PUT/DELETE /unidades/{id}`, `DELETE /unidades/{id}/remover` |
| Ações API | **C, E, X, Q** |
| Ferramenta SAS IA | `consultar_resumo_unidades`, `consultar_cadastro_geral` |

---

### 4.4 Usuários

| Item | Detalhe |
|------|---------|
| Seção menu | `usuarios` |
| Tabelas | `usuarios` |
| Model | `Usuario` |
| Controller | `UsuarioController` |
| Rotas | `GET/POST /usuarios`, `GET/PUT/DELETE /usuarios/{id}`, `PUT /usuarios/me/senha` |
| Permissões | ADMIN (`canManageUsuarios`) |
| Ações API | **C, E, X, Q** |
| Ferramenta SAS IA | `consultar_resumo_usuarios` |
| Risco Ayla | **Bloquear escrita** — alteração de permissões |

---

### 4.5 Produtos

| Item | Detalhe |
|------|---------|
| Seção menu | `produtos` |
| Tabelas | `produtos`, `stock_lotes`, `fichas_tecnicas` |
| Rotas | `GET/POST /produtos`, `GET/PUT /produtos/{id}`, `PUT /produtos/{id}/desativar|ativar`, `DELETE /produtos/{id}`, `GET /produtos/{id}/estoque` |
| Ações API | **C, E, X, Q** (desativar ≠ excluir) |
| Ferramenta SAS IA | `consultar_resumo_produtos`, `consultar_produto_por_nome`, `consultar_produtos_abaixo_estoque_minimo` |
| OpenClaw | `GET /ia/produto`, `GET /ia/estoque-baixo` |
| Risco Ayla | **Bloquear exclusão direta** |

---

### 4.6 Ficha Técnica

| Item | Detalhe |
|------|---------|
| Seção menu | `fechaTecnica` |
| Tabelas | `fichas_tecnicas` |
| Rotas | `GET/POST /fichas-tecnicas`, `PUT/DELETE /fichas-tecnicas/{id}`, `GET /fichas-tecnicas/{id}/pdf` |
| Ações API | **C, E, X, Q, P** |

---

### 4.7 Estoque

| Item | Detalhe |
|------|---------|
| Seção menu | `estoque` |
| Tabelas | `stock_lotes`, `lotes`, `movimentacoes` |
| Controller | `Api/EntradaEstoqueController` |
| Service | `EntradaEstoqueService` |
| Rotas | `GET /estoque/resumo`, `POST /estoque/entradas`, `POST /entrada`, `POST /saida` |
| Ações API | **C** (entrada/saída), **Q** |
| Motivos saída | `PRODUCAO`, `CONSUMO`, `PERDA`, `TRANSFERENCIA` |
| Ferramenta SAS IA | `consultar_estoque_por_unidade`, `consultar_movimentacoes_recentes` |
| OpenClaw | `POST /ia/lancar-perda` (com confirmação) |
| Risco Ayla | **Escrita exige confirmação** |

---

### 4.8 Lotes

| Item | Detalhe |
|------|---------|
| Seção menu | `lotes` |
| Tabelas | `lotes`, `stock_lotes` |
| Rotas | `GET/POST /lotes`, `GET/PUT/DELETE /lotes/{id}`, `GET /lotes/stats`, `GET /lotes-a-vencer`, `GET /lotes/{id}/etiqueta.pdf` |
| Ações API | **C, E, X, Q, P** |
| Ferramenta SAS IA | `consultar_lotes_proximos_vencer` |
| OpenClaw | `GET /ia/produtos-vencendo` |

---

### 4.9 Locais

| Item | Detalhe |
|------|---------|
| Seção menu | `locais` |
| Tabelas | `locais` |
| Rotas | `GET/POST /locais`, `GET/PUT/DELETE /locais/{id}`, `PATCH /locais/{id}/status` |
| Ações API | **C, E, X, Q** |
| Ferramenta SAS IA | `consultar_locais_estoque` |

---

### 4.10 Movimentações

| Item | Detalhe |
|------|---------|
| Seção menu | `movimentacoes` |
| Tabelas | `movimentacoes` |
| Rotas | `GET /movimentacoes`, `DELETE /movimentacoes/{id}`, `GET /perdas-recentes` |
| Ações API | **Q, X** (reversão) |
| Ferramenta SAS IA | `consultar_movimentacoes_recentes` |

---

### 4.11 Compras (Listas)

| Item | Detalhe |
|------|---------|
| Seção menu | `compras` |
| Tabelas | `listas_compras`, `listas_itens`, `estabelecimentos_compra` |
| Rotas | `GET/POST /listas`, `GET/PUT/DELETE /listas/{id}`, `PUT /listas/{id}/finalizar`, `POST /listas/{id}/estoque`, `GET /sugestoes-compras` |
| Rotas itens | `GET/POST /itens`, `GET/PUT/DELETE /itens/{id}` |
| Rotas estabelecimentos | `GET/POST /estabelecimentos-globais`, `GET/POST /listas/{id}/estabelecimentos` |
| Ações API | **C, E, X, Q, A** (finalizar), lançar estoque |
| Ferramenta SAS IA | `consultar_compras_recentes` |
| OpenClaw | `POST /ia/cadastrar-compra` (com confirmação) |
| Risco Ayla | Cancelar compra = sensível |

---

### 4.12 Relatórios (estoque)

| Item | Detalhe |
|------|---------|
| Seção menu | `relatorios` |
| Rotas | Agregações via dashboard + exportações pontuais |
| Ações API | **Q, P** |

---

### 4.13 Fornecedores

| Item | Detalhe |
|------|---------|
| Seção menu | `fornecedores` |
| Tabelas | `fornecedores`, `fornecedores_backup` |
| Model | `Fornecedor` |
| Controller | `FornecedorController` |
| Rotas | `GET/POST /fornecedores`, `GET/PUT /fornecedores/{id}`, `PUT /fornecedores/{id}/desativar|ativar`, `DELETE /fornecedores/{id}` |
| Ações API | **C, E, X, Q** |
| Ferramenta SAS IA | `consultar_fornecedores`, `consultar_cadastro_geral` |

---

### 4.14 Fechamento de Caixa

| Item | Detalhe |
|------|---------|
| Seções menu | `fechamento`, `fechamentoDash` |
| Tabelas | `fechamentos_caixa` |
| Rotas | `GET/POST /fechamentos-caixa`, `GET/PUT/DELETE /fechamentos-caixa/{id}`, PDFs |
| Ações API | **C, E, X, Q, P** |
| Ferramenta SAS IA | `consultar_vendas_do_dia`, `consultar_fechamentos_recentes` |

---

### 4.15 Boletos (Boletão)

| Item | Detalhe |
|------|---------|
| Seção menu | `boletao` |
| Tabelas | `boletos`, `boleto_anexos` |
| Model | `Boleto`, `BoletoAnexo` |
| Controller | `BoletoController` |
| Service | `BoletoFluxoCaixaService` |
| Rotas | `GET/POST /boletos`, `GET/PUT/DELETE /boletos/{id}`, anexos, `GET /boletos/resumo`, `GET /boletos/economia-mensal` |
| Ações API | **C, E, X, Q, P** (baixar/pagar) |
| Ferramenta SAS IA | `consultar_boletos_resumo` |
| Risco Ayla | **Baixar contas = sensível** |

---

### 4.16 Impostos

| Item | Detalhe |
|------|---------|
| Seção menu | `impostos` |
| Tabelas | `impostos`, `imposto_anexos` |
| Model | `Imposto`, `ImpostoAnexo` |
| Controller | `ImpostoController` |
| Arquivo rotas | `imposto_routes.php` |
| Rotas | `GET/POST /impostos`, `GET/POST/DELETE /impostos/{id}`, anexos, `POST /impostos/{id}/gerar-boleto` |
| Ações API | **C, E, X, Q, P** |

---

### 4.17 Alvarás

| Item | Detalhe |
|------|---------|
| Seção menu | `alvara` |
| Tabelas | `alvaras` |
| Model | `Alvara` |
| Controller | `AlvaraController` |
| Rotas | `GET/POST /alvaras`, `GET/PUT/DELETE /alvaras/{id}`, anexos |
| Ações API | **C, E, X, Q** |
| Ferramenta SAS IA | `consultar_alvaras_vencendo` |

---

### 4.18 Proventos

| Item | Detalhe |
|------|---------|
| Seção menu | `proventos` |
| Tabelas | `proventos`, `proventos_assinaturas`, `proventos_logs` |
| Rotas | CRUD + `POST /proventos/{id}/autorizar`, `/enviar-codigo`, `/confirmar-assinatura`, `/finalizar`, `/cancelar`, `GET /proventos/{id}/recibo.pdf` |
| Ações API | **C, E, X, Q, A, N, P** |
| Ferramenta SAS IA | `consultar_proventos_resumo` |
| Risco Ayla | Autorizar/finalizar = sensível |

---

### 4.19 Despesas Fixas

| Item | Detalhe |
|------|---------|
| Seção menu | `despesasFixas` |
| Tabelas | `despesas_fixas`, `despesas_fixas_categorias` |
| Rotas | CRUD despesas + categorias |
| Ações API | **C, E, X, Q** |
| Ferramenta SAS IA | `consultar_despesas_fixas_resumo` |

---

### 4.20 Vale/Consumo

| Item | Detalhe |
|------|---------|
| Seção menu | `valeConsumo` |
| Tabelas | `financeiro_vale_consumo` |
| Rotas | `GET/POST /financeiro/vale-consumo`, resumo, relatórios CSV/PDF |
| Ações API | **C, E, Q, P** |
| Ferramenta SAS IA | `consultar_vale_consumo_recente` |

---

### 4.21 Recibo Ajuda de Custo

| Item | Detalhe |
|------|---------|
| Seção menu | `reciboAjuda` |
| Tabelas | `recibos_ajuda_custo` |
| Rotas | CRUD + PDF |
| Ações API | **C, E, X, Q, P** |
| Ferramenta SAS IA | `consultar_recibos_ajuda_resumo` |

---

### 4.22 Financeiro Gerencial

| Item | Detalhe |
|------|---------|
| Seções menu | `financeiroDashboard`, `financeiroFluxoCaixa`, `financeiroContasReceber`, `financeiroDre`, `financeiroCmv`, `financeiroCentrosCusto`, `financeiroOrcamento`, `financeiroIndicadores` |
| Tabelas | `financeiro_categorias`, `financeiro_centros_custo`, `financeiro_clientes`, `financeiro_lancamentos`, `financeiro_contas_receber`, `financeiro_orcamentos`, `financeiro_indicadores_cache` |
| Support | `FinanceiroGerencialCalculo.php` |
| Arquivo rotas | `financeiro_routes.php` |
| Rotas | `GET /financeiro/dashboard`, fluxo-caixa, contas-receber, dre, cmv, centros-custo, orçamento, indicadores, categorias |
| Ações API | **C, E, X, Q** |
| Ferramenta SAS IA | `consultar_resumo_financeiro` |
| Risco Ayla | **Financeiro bloqueado na fase 1** (como OpenClaw) |

---

### 4.23 Reservas de Mesa

| Item | Detalhe |
|------|---------|
| Seções menu | `reservaMesa`, `historicoReservas` |
| Tabelas | `mesas`, `reservas_mesas` |
| Models | `Mesa`, `ReservaMesa` |
| Controllers | `MesaController`, `ReservaMesaController` |
| Support | `ReservaMesaAcesso.php`, `SasIaReservaQuery.php` |
| Rotas | `GET/POST /mesas`, `GET/POST /reservas-mesas`, `POST /reservas-mesas/{id}/cancelar`, `PATCH /reservas-mesas/{id}/status` |
| Ações API | **C, E, Q, N** |
| Ferramenta SAS IA | `consultar_reservas_periodo`, `consultar_mesas_resumo` |

---

### 4.24 RH — Funcionários

| Item | Detalhe |
|------|---------|
| Seções menu | `funcionarios`, `rhRelatorios`, `rhFolhaPonto` |
| Tabelas | `funcionarios`, `funcionarios_salarios`, `rh_folhas_ponto` |
| Controllers | `Rh/RhFolhaPontoController` |
| Rotas | `GET/POST /funcionarios`, `PUT/DELETE /funcionarios/{id}`, folhas-ponto CRUD + PDF |
| Ações API | **C, E, X, Q, P** |
| Ferramenta SAS IA | `consultar_funcionarios_resumo`, `consultar_folha_ponto_resumo` |
| Risco Ayla | Alterar dados de funcionário = sensível |

---

### 4.25 RH — Recrutamento

| Item | Detalhe |
|------|---------|
| Seções menu | `rhDashboard`, `rhVagas`, `rhCandidatos`, `rhEntrevistas`, `rhBancoTalentos` |
| Tabelas | `rh_vagas`, `rh_candidatos`, `rh_curriculos`, `rh_entrevistas`, `rh_documentos`, `rh_historico`, `rh_auditoria` |
| Controllers | `Rh/RhDashboardController`, `RhVagaController`, `RhCandidatoController`, `RhEntrevistaController`, `RhDocumentoController` |
| Rotas web públicas | `/vagas`, `/vagas/{slug}/candidatar` (web.php) |
| Rotas API | vagas, candidatos, entrevistas, documentos CRUD |
| Ações API | **C, E, X, Q, P** |
| Ferramenta SAS IA | `consultar_rh_recrutamento_resumo`, `consultar_vagas_rh`, `consultar_candidatos_rh` |

---

### 4.26 RH — Rescisão

| Item | Detalhe |
|------|---------|
| Seções menu | `rhRescisaoDashboard`, `rhRescisaoSimulador`, `rhRescisaoCalculo`, `rhRescisaoComparativo`, `rhRescisaoHistorico`, `rhRescisaoRelatorios` |
| Tabelas | `rh_rescisoes`, `rh_rescisao_cenarios` |
| Support | `RhRescisaoCalculo.php`, `RhRescisaoTrctPdf.php` |
| Arquivo rotas | `rh_rescisao_routes.php` |
| Rotas | `POST /rh/rescisoes/calcular`, `POST /rh/rescisoes/comparar`, CRUD + `POST /rh/rescisoes/{id}/confirmar`, PDF |
| Ações API | **C, E, X, Q, A, P** |
| Ferramenta SAS IA | `consultar_rescisoes_rh` |

---

### 4.27 Energia

| Item | Detalhe |
|------|---------|
| Seções menu | `energiaDashboard`, `energiaEquipamentos`, `energiaProjecao`, `energiaRelatorios` |
| Tabelas | `energia_equipamentos_consumo` |
| Support | `EnergiaCalculo.php` |
| Arquivo rotas | `energia_routes.php` |
| Rotas | equipamentos CRUD, dashboard, relatórios CSV/PDF |
| Ações API | **C, E, X, Q, P** |
| Ferramenta SAS IA | `consultar_energia_resumo`, `consultar_equipamentos_energia` |

---

### 4.28 Patrimônio

| Item | Detalhe |
|------|---------|
| Seções menu | `patrimonioDashboard`, `patrimonios`, `patrimonioCategorias`, `patrimonioMovimentacoes`, `patrimonioManutencoes`, `patrimonioInventario`, `patrimonioRelatorios`, `patrimonioConfiguracoes` |
| Tabelas | `patrimonio_categorias`, `patrimonios`, `patrimonio_movimentacoes`, `patrimonio_manutencoes`, `patrimonio_documentos`, `patrimonio_fotos`, `patrimonio_historico`, `patrimonio_inventario`, `patrimonio_inventario_itens`, `patrimonio_setores` |
| Arquivo rotas | `patrimonio_routes.php`, `patrimonio_reports.php` |
| Rotas | CRUD completo + inventário + múltiplos relatórios PDF/CSV |
| Ações API | **C, E, X, Q, P** |
| Ferramenta SAS IA | `consultar_patrimonio_resumo`, `consultar_patrimonio_manutencoes` |

---

### 4.29 Investimento

| Item | Detalhe |
|------|---------|
| Seções menu | `investimentoDashboard`, `investimentoReservas`, `investimentoSimulador`, `investimentoCarteira`, `investimentoResgates`, `investimentoRelatorios` |
| Tabelas | `investimento_reservas`, `investimento_carteira`, `investimento_resgates` |
| Support | `InvestimentoMercado.php`, `InvestimentoCalculo.php` |
| Arquivo rotas | `investimento_routes.php` |
| Rotas | catalogos, dashboard, simular, reservas/carteira/resgates CRUD, relatórios |
| Ações API | **C, E, X, Q, P** |
| Ferramenta SAS IA | `consultar_investimento_resumo` |

---

### 4.30 Configurações do Sistema

| Item | Detalhe |
|------|---------|
| Seções menu | `configuracoesPainel`, `openClawIntegracao` |
| Tabelas | `sistema_configuracoes` |
| Controllers | `OpenClawConfigController` |
| Rotas | `GET/POST /configuracoes-sistema`, `GET/POST /openclaw/config`, `POST /openclaw/gerar-token`, `GET /openclaw/logs` |
| Ações API | **Q, E** (ADMIN) |

---

### 4.31 Logs / Auditoria

| Item | Detalhe |
|------|---------|
| Seção menu | `logs` |
| Tabelas | `audit_logs`, `logs_usuarios`, `logs_etiquetas` |
| Support | `AuditLog.php` |
| Rotas | `GET /audit-logs`, `POST /audit-logs/registrar` |
| Ações API | **Q** |
| Ferramenta SAS IA | `consultar_logs_recentes` |

---

### 4.32 Admin (Backup/Deploy)

| Item | Detalhe |
|------|---------|
| Rotas | `POST /admin/backup`, `GET /admin/backups`, `POST /admin/restaurar`, `GET /admin/mapa-sistema.pdf`, `GET /deploy` |
| Ações API | Destrutivas — **fora do escopo Ayla** |

---

## 5. Integrações de IA existentes

### 5.1 SAS IA (principal — chat interno)

| Item | Detalhe |
|------|---------|
| Prefixo | `/api/sas-ia/*` |
| Auth | `X-Usuario-Id` |
| Controller | `SasIaController` |
| Services | `SasIaChatService`, `SasIaToolService`, `SasIaModuleQueryService` |
| Ferramentas | **35 consultas read-only** (`SasIaToolRegistry`) |
| Persistência | `ai_conversations`, `ai_messages`, `ai_tool_logs`, `ai_documents` |
| Limite diário | Por perfil (`SAS_IA_LIMIT_*` no .env) |
| Escrita | **Nenhuma** — só consultas |

### 5.2 OpenClaw (assistente externo WhatsApp)

| Item | Detalhe |
|------|---------|
| Prefixo ações | `/api/ia/*` ⚠️ colide com legado |
| Prefixo config | `/api/openclaw/*` |
| Auth | Bearer token (`CheckOpenClawToken`) |
| Controller | `Api/AiAssistantController` |
| Service | `AiAssistantService` |
| Ações | 4 consultas + 2 escritas (perda, compra) com confirmação |
| Logs | `ai_assistant_logs` |
| Escopo atual | Somente estoque |

### 5.3 IA Legado

| Item | Detalhe |
|------|---------|
| Prefixo | `/api/ia/status`, `/api/ia/config`, `/api/ia/chat` |
| Auth | `X-Usuario-Id` |
| Função | ChatGPT simples sem ferramentas |
| Status | **Superseded** por SAS IA |

### 5.4 AI Agents

| Item | Detalhe |
|------|---------|
| Prefixo | `/api/ai-agents/*` |
| Tabelas | `ai_agents`, `ai_agent_modules` |
| Módulos | `atendimento`, `rh`, `financeiro`, `restaurante`, `pericia`, `administrativo` |
| Função | Personas/configuração de agentes |

---

## 6. Models Eloquent existentes

`AiAssistantLog`, `AiAgent`, `AiDocument`, `AiToolLog`, `AiMessage`, `AiConversation`, `Boleto`, `BoletoAnexo`, `Imposto`, `ImpostoAnexo`, `Unidade`, `KanbanTask`, `Alvara`, `ReservaMesa`, `Mesa`, `Usuario`, `Fornecedor`, `FornecedorBackup`, `User`

> Estoque, movimentações, compras, financeiro gerencial e RH usam predominantemente `DB::table()`.

---

## 7. Middlewares

| Alias | Classe | Função |
|-------|--------|--------|
| `sas.usuario` | `EnsureSasUsuario` | Valida `X-Usuario-Id` + CORS |
| `openclaw.token` | `CheckOpenClawToken` | Bearer token OpenClaw + CORS |

---

## 8. Tabelas do banco (consolidado)

### Legadas (não criadas por migration)
`produtos`, `unidades`, `lotes`, `movimentacoes`, `locais`, `usuarios`, `listas_compras`, `listas_itens`, `stock_lotes`

### Criadas por migrations (principais)
`ai_*`, `sistema_configuracoes`, `financeiro_*`, `rh_*`, `patrimonio_*`, `investimento_*`, `energia_*`, `boletos`, `impostos`, `proventos`, `despesas_fixas`, `recibos_ajuda_custo`, `fechamentos_caixa`, `kanban_tasks`, `mesas`, `reservas_mesas`, `fornecedores`, `alvaras`, `fichas_tecnicas`, `audit_logs`, `funcionarios`, `funcionarios_salarios`

---

## 9. Mapa de ferramentas SAS IA (reutilizáveis pela Ayla)

Total: **35 ferramentas**, todas **somente leitura**.

| Módulo | Ferramentas |
|--------|-------------|
| Estoque | `consultar_resumo_produtos`, `consultar_produtos_abaixo_estoque_minimo`, `consultar_produto_por_nome`, `consultar_estoque_por_unidade`, `consultar_lotes_proximos_vencer`, `consultar_locais_estoque`, `consultar_movimentacoes_recentes`, `consultar_compras_recentes` |
| Cadastros | `consultar_fornecedores`, `consultar_resumo_unidades`, `consultar_resumo_usuarios`, `consultar_cadastro_geral` |
| Financeiro | `consultar_vendas_do_dia`, `consultar_fechamentos_recentes`, `consultar_resumo_financeiro`, `consultar_boletos_resumo`, `consultar_alvaras_vencendo`, `consultar_proventos_resumo`, `consultar_despesas_fixas_resumo`, `consultar_vale_consumo_recente`, `consultar_recibos_ajuda_resumo` |
| Reservas | `consultar_reservas_periodo`, `consultar_mesas_resumo` |
| RH | `consultar_funcionarios_resumo`, `consultar_rh_recrutamento_resumo`, `consultar_vagas_rh`, `consultar_candidatos_rh`, `consultar_folha_ponto_resumo`, `consultar_rescisoes_rh` |
| Energia | `consultar_energia_resumo`, `consultar_equipamentos_energia` |
| Patrimônio | `consultar_patrimonio_resumo`, `consultar_patrimonio_manutencoes` |
| Investimento | `consultar_investimento_resumo` |
| Admin | `consultar_kanban_resumo`, `consultar_logs_recentes`, `consultar_manual_documentacao` |

---

## 10. Conflitos e duplicações identificadas

| Problema | Detalhe |
|---------|---------|
| Prefixo `/ia/*` | OpenClaw ações + IA legado compartilham namespace |
| 3 camadas de IA | Legado, SAS IA, OpenClaw — lógicas paralelas |
| Auth inconsistente | `X-Usuario-Id` vs Bearer vs nenhum em algumas rotas |
| Sem rate limit global | Apenas limite diário SAS IA por usuário |
| Sem policies Laravel | Permissões espalhadas em closures |
| Escrita duplicável | OpenClaw `lancar-perda` vs `POST /saida` vs futura Ayla |

---

## 11. Resumo quantitativo

| Métrica | Valor |
|---------|-------|
| Módulos de menu | ~70 seções |
| Arquivos de rotas | 18 |
| Controllers | 20 |
| Models Eloquent | 21 |
| Services | 8 |
| Middlewares | 2 |
| Migrations | 92 |
| Ferramentas SAS IA | 35 (read-only) |
| Ações OpenClaw | 6 (4Q + 2 escrita) |
| Integrações IA | 4 (SAS IA, OpenClaw, Legado, Agents) |

---

*Documento gerado para aprovação antes da implementação da API Ayla.*
