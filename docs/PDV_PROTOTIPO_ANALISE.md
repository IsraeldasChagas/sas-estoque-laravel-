# PDV / Comercial — Análise do Protótipo Visual

**Escopo desta fase:** apenas frontend navegável.  
**Fora de escopo:** banco, migrations, models, controllers, rotas de persistência, fiscal real, estoque, financeiro, Ayla.

## Arquitetura visual

| Camada | Arquivo | Papel |
|--------|---------|--------|
| CSS | `frontend/comercial-pdv.css` | Layout spacious, cards, PDV, KDS, mesas, fiscal desabilitado |
| JS | `frontend/comercial-pdv.js` | `DADOS_DEMONSTRACAO_PDV`, renderizadores, modal `#cpdvModal`, estado em memória |
| Shell | `frontend/index.html` | Menu Comercial + 12 `view-section` + checkboxes de permissão |
| Nav | `frontend/app.js` | `ALL_NAV_SECTION_IDS`, `PERMISSOES`, `navigateTo`, `setupComercialPdvModule` |

Padrão visual alinhado a Patrimônio / Energia / Investimento (respiro, `page-wrap`, botões compactos, tema claro/escuro via CSS existente).

## Menus

**Menu principal:** Comercial (ícone carrinho)

**Submenus:**

1. Dashboard Comercial → `comercialDashboard`
2. PDV / Caixa → `comercialPdv`
3. Mesas e Comandas → `comercialMesas`
4. Pedidos → `comercialPedidos`
5. Cozinha / Produção → `comercialCozinha`
6. Pagamentos → `comercialPagamentos`
7. Fechamento de Caixa → `comercialFechamento`
8. Clientes → `comercialClientes`
9. Histórico de Vendas → `comercialHistorico`
10. Relatórios Comerciais → `comercialRelatorios`
11. Configurações do PDV → `comercialConfiguracoes`
12. Fiscal → `comercialFiscal` (rótulo “Em desenvolvimento”)

## Telas e componentes

- **Dashboard:** KPIs demo + gráficos Chart.js + aviso de protótipo
- **PDV:** grade de produtos / carrinho / ações visuais
- **Mesas:** mapa por status + modal de comanda + modo garçom (tablet/celular responsivo)
- **Pedidos:** filtros e tabela demo
- **Cozinha:** KDS 4 colunas (cozinha / bar / sobremesa)
- **Pagamentos / Fechamento / Clientes / Histórico / Relatórios / Config:** formulários e cards sem persistência
- **Fiscal:** cards desabilitados, sem XML/API/certificado

## Fluxos simulados (apenas UI)

Abrir mesa → adicionar pessoas/garçom → lançar pedido → enviar produção → pré-conta → pagamento (integral/parcial/múltiplas formas) → fechar mesa / fechar caixa.

Também: transferir/juntar/dividir conta, suspender/recuperar venda no PDV, avançar cards no KDS (memória da sessão).

## Dados simulados

Constante `DADOS_DEMONSTRACAO_PDV` em `comercial-pdv.js`:

- unidades, mesas, produtos, categorias, garçons, clientes, pedidos, vendas, KDS

Estado temporário: `cpdvState` (carrinho, desconto, mesa selecionada). **Sem localStorage definitivo.**

## Permissões (protótipo)

| Perfil | Acesso |
|--------|--------|
| ADMIN | Todas as chaves `comercial*` |
| GERENTE | Dashboard, Mesas, Pedidos, Cozinha, Histórico, Relatórios |
| CAIXA / ATENDENTE_CAIXA | PDV, Pagamentos, Fechamento |
| GARCOM | Mesas, Pedidos |

Chaves: `comercialDashboard`, `comercialPdv`, `comercialMesas`, `comercialPedidos`, `comercialCozinha`, `comercialPagamentos`, `comercialFechamento`, `comercialClientes`, `comercialHistorico`, `comercialRelatorios`, `comercialConfiguracoes`, `comercialFiscal`.

## Necessário no banco (futuro)

Ver `PDV_BACKEND_FUTURO.md` — sugestão de tabelas; **nenhuma migration nesta fase**.

## Necessário no backend (futuro)

APIs de vendas, mesas/comandas, pedidos/KDS, pagamentos, caixa PDV, clientes, relatórios; regras de permissão server-side; integração estoque (baixa), financeiro (caixa) e fiscal (NFC-e/NF-e).

## Integrações futuras

| Sistema | Uso previsto |
|---------|--------------|
| Estoque | Baixa de insumos / ficha técnica |
| Financeiro | Consolidaçãos de caixa e DRE |
| Fiscal | NFC-e / NF-e / contingência / IBS-CBS |
| Ayla | Pedidos por voz / assistência operacional |

**Confirmação desta fase:** Ayla, estoque, financeiro e fiscal atuais **não foram alterados**.
