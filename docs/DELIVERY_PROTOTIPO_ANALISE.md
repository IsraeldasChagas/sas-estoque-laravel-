# Delivery — Análise do Protótipo Visual

**Referência analisada:** `C:\vendaffacil` (VendaFácil, pasta com dois “f”).  
**Escopo:** somente frontend navegável no SAS-Estoque. Sem banco, API ou persistência.

## Arquitetura

| Camada | Arquivo |
|--------|---------|
| CSS | `frontend/delivery.css` |
| JS | `frontend/delivery.js` — `DADOS_DEMONSTRACAO_DELIVERY` |
| Shell | `frontend/index.html` — menu + 15 sections |
| Nav | `frontend/app.js` — permissões e `navigateTo` |

## Mapeamento VendaFácil → SAS

| VendaFácil (`C:\vendaffacil`) | SAS Delivery |
|-------------------------------|--------------|
| `publico/loja.blade.php` | `deliveryVitrine` |
| `publico/produto.blade.php` | Modal de produto na vitrine |
| `publico/carrinho.blade.php` | Modal carrinho |
| `publico/checkout.blade.php` | Wizard checkout (4 etapas) |
| `publico/pedido-show.blade.php` | Confirmação + timeline |
| `partials/publico/compra-fluxo-etapas.blade.php` | `dlvFluxoHtml` |
| `empresa/produtos`, `categorias`, `adicionais` | Cadastros admin |
| `empresa/loja-entrega-faixas-index` | `deliveryTaxas` |
| `empresa/pedidos` | `deliveryPedidos` |
| `empresa/configuracoes` | `deliveryConfiguracoes` |

## Fluxo da vitrine (como VendaFácil)

1. **Cardápio** — banner, busca, categorias, grid de produtos  
2. **Carrinho** — itens, cupom, taxa demo, total  
3. **Checkout** — cliente → entrega/retirada → pagamento → confirmação  
4. **Pedido** — código fictício, timeline, link `wa.me` demonstrativo  

## Permissões

| Perfil | Telas |
|--------|-------|
| ADMIN | Todas (15) |
| GERENTE | Dashboard, Pedidos, Produtos, Clientes, Relatórios |
| ATENDENTE | Pedidos, Clientes |
| MARKETING | Vitrine, Categorias, Banners, Cupons |

## Integrações futuras (não implementadas)

- OSRM / Google Maps (frete real)
- PIX / gateway de pagamento
- WhatsApp API oficial
- Estoque, financeiro, fiscal, Ayla
