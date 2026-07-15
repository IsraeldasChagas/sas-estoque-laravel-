# PDV — Mapa de Telas (Protótipo)

```
Comercial
├── comercialDashboard        Dashboard Comercial
├── comercialPdv              PDV / Caixa (venda rápida)
├── comercialMesas            Mesas e Comandas (+ modo garçom responsivo)
├── comercialPedidos          Pedidos
├── comercialCozinha          Cozinha / Produção (KDS)
├── comercialPagamentos       Pagamentos
├── comercialFechamento       Fechamento de Caixa (PDV)
├── comercialClientes         Clientes
├── comercialHistorico        Histórico de Vendas
├── comercialRelatorios       Relatórios Comerciais
├── comercialConfiguracoes    Configurações do PDV
└── comercialFiscal           Fiscal (Em desenvolvimento)
```

## Hash / seção / DOM

| Hash | `data-section` | Section id | Root id |
|------|----------------|------------|---------|
| `#comercialDashboard` | comercialDashboard | comercialDashboardSection | comercialDashboardRoot |
| `#comercialPdv` | comercialPdv | comercialPdvSection | comercialPdvRoot |
| `#comercialMesas` | comercialMesas | comercialMesasSection | comercialMesasRoot |
| `#comercialPedidos` | comercialPedidos | comercialPedidosSection | comercialPedidosRoot |
| `#comercialCozinha` | comercialCozinha | comercialCozinhaSection | comercialCozinhaRoot |
| `#comercialPagamentos` | comercialPagamentos | comercialPagamentosSection | comercialPagamentosRoot |
| `#comercialFechamento` | comercialFechamento | comercialFechamentoSection | comercialFechamentoRoot |
| `#comercialClientes` | comercialClientes | comercialClientesSection | comercialClientesRoot |
| `#comercialHistorico` | comercialHistorico | comercialHistoricoSection | comercialHistoricoRoot |
| `#comercialRelatorios` | comercialRelatorios | comercialRelatoriosSection | comercialRelatoriosRoot |
| `#comercialConfiguracoes` | comercialConfiguracoes | comercialConfiguracoesSection | comercialConfiguracoesRoot |
| `#comercialFiscal` | comercialFiscal | comercialFiscalSection | comercialFiscalRoot |

## Modais

| Id | Uso |
|----|-----|
| `#cpdvModal` | Painel da mesa, pagamento simulado, ações demográficas |

## Resoluções

| Breakpoint | Comportamento |
|------------|---------------|
| Desktop (>1024) | PDV 2 colunas; KDS 4 colunas |
| Tablet (≤1024) | PDV empilhado; KDS 2 colunas |
| Celular (≤700) | Mesas 2 colunas; KDS 1 coluna; modo garçom usable |
