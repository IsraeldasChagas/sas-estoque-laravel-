# Comparativo: VendaFácil × SAS Delivery (protótipo)

**Projeto referência:** `C:\vendaffacil` (não alterado)  
**Protótipo SAS:** `sas-estoque-laravel/frontend/delivery.*`

## Reproduzido

| Item | VendaFácil | SAS Delivery |
|------|------------|--------------|
| Vitrine pública | `/loja/{slug}` | `deliveryVitrine` |
| Banner rotativo | `loja.blade.php` carousel | Banner demo com destaque |
| Filtro categorias | select + chips | chips horizontais |
| Grid produtos | col-6/4/3 cards | `dlv-prod-grid` responsivo |
| Selo personalizável | badge | destaque / indisponível / promo |
| Página/modal produto | `produto.blade.php` | `#dlvModal` |
| Adicionais min/max | acrescimo_escolhas | checkboxes demo |
| Retirar ingredientes | ingredientes UI | checkboxes retirada |
| Carrinho | `carrinho.blade.php` | modal + FAB flutuante |
| Fluxo etapas | compra-fluxo-etapas | Cardápio → Carrinho → Checkout → Pedido |
| Entrega vs retirada | checkout radio | wizard etapa 2 |
| CEP + endereço | checkout fields | campos demo |
| Taxa entrega | faixas CEP / OSRM | valor fixo por bairro demo |
| Pagamento PIX | formas loja | QR + copiar código demo |
| Troco dinheiro | checkout | campo troco |
| Confirmação pedido | `pedido-show` | código DLV-xxxx |
| Timeline status | recebido → entregue | `dlv-timeline` |
| WhatsApp | wa.me link | link demonstrativo |
| Cadastros admin | produtos, categorias, adicionais, faixas | 15 telas admin |
| Dashboard KPIs | empresa.dashboard | deliveryDashboard |

## Adaptado para SAS

- Marca **Grupo Sabor Paraense** (não VendaFácil)
- Menu lateral SAS + tema claro/escuro
- Produtos paraenses (Tacacá, Maniçoba, cupuaçu)
- Estado em memória (`dlvState`), sem session Laravel
- Vitrine dentro do shell autenticado (não URL pública `/loja/slug`)

## De fora (proposital)

- Fidelidade / OTP (`fidelidade.blade.php`)
- OSRM / Google Maps frete real
- Pagamento online real
- WhatsApp API Business
- PWA / manifest
- Entregador (`entregador-pedido`)
- PDV interno VF
- Fiscal VF
- Venda externa / fiado

## Depende de backend

- Persistência pedidos, clientes, endereços
- Upload imagens reais
- Validação cupom server-side
- Cálculo frete por CEP/OSRM
- Notificação push / polling status
- Integração estoque (baixa insumos)

## Depende de integrações futuras

- Gateway PIX
- WhatsApp API
- Ayla (pedido por voz)
- Financeiro (receita delivery)
- Fiscal (NFC-e delivery)
