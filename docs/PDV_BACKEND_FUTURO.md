# PDV — Backend Futuro (sugestão)

> **Não implementar nesta fase.** Este documento apenas antecipa o modelo para validação do protótipo.

## Tabelas sugeridas (sem migrations agora)

| Tabela | Propósito |
|--------|-----------|
| `pdv_terminais` | Caixas/terminais por unidade |
| `pdv_caixas` | Abertura/fechamento de caixa |
| `pdv_caixa_movimentos` | Sangrias, suprimentos, entradas |
| `pdv_mesas_operacionais` | Estado operacional da mesa (livre/ocupada/…) |
| `pdv_comandas` | Comanda vinculada à mesa/venda |
| `pdv_comanda_itens` | Itens, adicionais, observações, setor |
| `pdv_pedidos` | Pedidos enviados à produção |
| `pdv_pedido_itens` | Status KDS por item |
| `pdv_vendas` | Cabeçalho da venda |
| `pdv_venda_itens` | Itens faturados |
| `pdv_pagamentos` | Formas, NSU, parcelas, troco |
| `pdv_clientes` | Clientes do comercial |
| `pdv_descontos` | Políticas e auditoria de desconto |
| `pdv_configuracoes` | Flags por unidade/terminal |
| `pdv_impressoras` | Mapeamento de impressão |
| `pdv_setores_producao` | Cozinha, bar, sobremesa |

## Endpoints futuros (conceitual)

- `POST /api/pdv/caixas/abrir` … `POST /api/pdv/caixas/{id}/fechar`
- `GET/POST /api/pdv/mesas` … ações (abrir, transferir, juntar, fechar)
- `POST /api/pdv/pedidos` … avançar status KDS
- `POST /api/pdv/vendas` … pagamentos / cancelamentos
- `GET /api/pdv/relatorios/*`

## Reutilização futura

- **Fechamento existente** (`fechamento` / `fechamentoDash`): possível consolidação ou espelhamento — **não integrado nesta fase**.
- **Produtos/ficha técnica:** origem de preços e baixa de estoque.
- **Reservas:** status `reservada` da mesa pode conversar com `reservaMesa`.
- **Fiscal:** módulo próprio (NFC-e/NF-e) desacoplado até estar maduro.
- **Ayla:** comandos de pedido/mesa sob políticas de escrita controlada.

## Regras

1. Nenhuma migration deve ser criada a partir deste documento nesta etapa.
2. Nenhuma rota de persistência deve existir até aprovação do protótipo.
3. Dados do protótipo (`DADOS_DEMONSTRACAO_PDV`) não devem migrar automaticamente para o banco.
