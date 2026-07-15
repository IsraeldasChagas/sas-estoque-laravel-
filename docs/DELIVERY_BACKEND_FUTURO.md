# Delivery — Backend Futuro (sugestão)

> Nenhuma migration nesta fase.

## Tabelas sugeridas

| Tabela | Uso |
|--------|-----|
| `delivery_categorias` | Categorias da vitrine |
| `delivery_produtos` | Cardápio delivery |
| `delivery_adicionais` | Grupos min/max/obrigatório |
| `delivery_variacoes` | Tamanho, sabor, preço extra |
| `delivery_banners` | Slides da vitrine |
| `delivery_cupons` | Promoções |
| `delivery_taxas_entrega` | Bairro/CEP (como VF faixas) |
| `delivery_horarios` | Funcionamento por unidade |
| `delivery_formas_pagamento` | Config pagamento |
| `delivery_clientes` | Clientes |
| `delivery_enderecos` | Endereços |
| `delivery_pedidos` | Cabeçalho pedido |
| `delivery_pedido_itens` | Linhas + opções |
| `delivery_pedido_status` | Timeline |

## Reuso possível com VendaFácil

Conceitos de `C:\vendaffacil` (faixas CEP, adicionais, fluxo checkout) podem inspirar o schema, mas **sem compartilhar banco** com o VF nesta fase.
