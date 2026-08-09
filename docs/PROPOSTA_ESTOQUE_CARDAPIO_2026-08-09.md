# Proposta: Estoque Administrativo + Estoque do Cardápio

**SAS Estoque — Grupo Sabor Paraense**  
**Data:** 2026-08-09  
**Objetivo:** permitir vender o cardápio com baixa de estoque controlada, sem misturar nem quebrar o estoque administrativo que já existe.

---

## 1. Resumo executivo

Hoje o **estoque principal** (`produtos` + `stock_lotes` + `movimentacoes`) foi pensado para **administração** (compras, lotes, produção, CMV, transferências, perdas).

O **cardápio** (`dlv_produtos`) já existe para Delivery, PDV e mesas, mas a venda **não controla bem o estoque comercial**:

| Situação | Baixa de estoque hoje? |
|----------|------------------------|
| Revenda (bebida etc.) com `estoque_produto_id` | Sim — FIFO no produto administrativo |
| Prato com ficha técnica | Não — só aviso de saldo; não explode ingredientes na venda |
| Pedido Delivery | Não — contador cosmético `dlv_produtos.estoque` |
| Produção (ordem) | Sim — baixa insumos e entra produto final |

**Decisão proposta:** manter o estoque administrativo **como está** e criar um **segundo estoque: Estoque do Cardápio**, ligado aos itens vendáveis. Toda venda (PDV, mesa, delivery) dá baixa nesse estoque e registra movimentação.

---

## 2. Problema atual (por que está difícil vender)

### 2.1 Dois mundos pouco conectados

```text
ESTOQUE ADMIN (já existe)              CARDÁPIO (já existe)
─────────────────────────              ────────────────────
produtos                               dlv_produtos
stock_lotes / lotes                    preco, tipo_venda
movimentacoes                          estoque_produto_id?
entrada / saída / fiscal               ficha_tecnica_id?
produção / CMV                         dlv_produtos.estoque (só número)

         venda PDV hoje ──► tenta baixar só o produto_id resolvido
         prato sem produto final ──► trava ou não controla insumos
         delivery ──► quase não mexe no estoque real
```

### 2.2 Lacunas que atrapalham a venda

1. **Prato no cardápio** pode ter só ficha técnica → `produto_id = 0` → venda fiscal rejeita ou não baixa BOM.
2. **Baixa na venda** não lê a ficha técnica (não consome arroz, carne, etc. no ato da venda).
3. **Delivery** usa um campo `estoque` isolado, sem FIFO e sem `movimentacoes`.
4. **Comanda** não reserva saldo — só baixa no fechar conta (risco de vender o que não tem).
5. Operação mistura “insumo de cozinha” com “item à venda no cardápio” no mesmo pensamento de estoque.

### 2.3 O que já funciona e deve ser preservado

- Entradas, saídas manuais, lotes, validade (FEFO/FIFO)
- Produção com ficha técnica (baixa insumos + entrada do produto final)
- Venda fiscal/PDV de **revenda** vinculada a `estoque_produto_id`
- Módulo fiscal, CMV, unidades/CNPJ

**Regra de ouro:** não refatorar o estoque administrativo existente. Acrescentar o estoque comercial do cardápio ao lado.

---

## 3. Solução proposta: dois estoques

### 3.1 Estoque A — Administrativo (inalterado)

| Aspecto | Descrição |
|---------|-----------|
| Para quem | Compras, cozinha, fiscal, CMV, produção |
| Tabelas | `produtos`, `stock_lotes`, `lotes`, `movimentacoes` |
| Movimentos | ENTRADA compra, SAIDA produção/consumo/perda/transferência |
| UI | Consulta Estoque, Lotes, Entrada/Saída, Fiscal |

Continua sendo a **fonte da verdade dos insumos e produtos de depósito**.

### 3.2 Estoque B — Cardápio (novo)

| Aspecto | Descrição |
|---------|-----------|
| Para quem | Venda no PDV, mesas, delivery |
| Unidade de controle | Cada item do cardápio (`dlv_produtos`) |
| Saldo | Quantidade disponível para vender (porções / unidades) |
| Movimentos | ENTRADA (abastecimento/produção), SAIDA (venda), AJUSTE, ESTORNO |
| UI | Nova tela **Estoque do Cardápio** + indicadores no PDV |

Quando o cliente compra no cardápio → **baixa imediata (ou no fechar conta) no Estoque B**, com histórico completo.

### 3.3 Como os dois conversam

```text
  [Compras / Insumos]
           │
           ▼
  ESTOQUE A (Admin) ──produção / montagem / abastecimento──► ESTOQUE B (Cardápio)
           ▲                                                      │
           │                                                      │ venda PDV/mesa/delivery
           └──── (opcional) baixa de insumos via ficha ───────────┘
                                                                  ▼
                                                         movimentações cardápio
                                                         + venda fiscal
```

Há **dois modos operacionais** (podem coexistir por tipo de item):

| Modo | Quando usar | O que acontece na venda |
|------|-------------|-------------------------|
| **M1 — Porção pronta** | Prato já produzido / montado e “colocado no cardápio” | Baixa 1 porção no Estoque B. Estoque A já foi baixado na produção. |
| **M2 — Explosão de ficha** | Venda direta sem estoque intermediário de prato | Baixa insumos no Estoque A pela ficha + registra saída no Estoque B (controle comercial). |
| **M3 — Revenda** | Bebida, sobremesa embalada | Baixa no Estoque B e espelha no produto admin (`estoque_produto_id`) como hoje. |

**Recomendação inicial (fase 1):** M1 + M3 — mais simples, alinhado ao que o sistema já faz bem (produção + venda de SKU). M2 entra depois.

---

## 4. Modelo de dados sugerido

### 4.1 Novas tabelas

#### `cardapio_estoque_saldos`

Saldo atual por item do cardápio e unidade.

| Campo | Tipo | Nota |
|-------|------|------|
| `id` | PK | |
| `unidade_id` | FK | Unidade/loja |
| `dlv_produto_id` | FK | Item do cardápio |
| `quantidade` | decimal | Saldo disponível para venda |
| `estoque_minimo` | decimal | Alerta no PDV |
| `updated_at` | timestamp | |

Unique: `(unidade_id, dlv_produto_id)`.

#### `cardapio_estoque_movimentacoes`

Livro-razão do estoque comercial.

| Campo | Tipo | Nota |
|-------|------|------|
| `id` | PK | |
| `unidade_id` | FK | |
| `dlv_produto_id` | FK | |
| `tipo` | enum | `ENTRADA`, `SAIDA`, `AJUSTE`, `ESTORNO` |
| `origem` | enum | `PRODUCAO`, `ABASTECIMENTO`, `VENDA_PDV`, `VENDA_MESA`, `VENDA_DELIVERY`, `MANUAL`, `CANCELAMENTO` |
| `quantidade` | decimal | Sempre positiva; sinal pelo `tipo` |
| `saldo_apos` | decimal | Auditoria |
| `venda_id` | FK nullable | Ligação com venda fiscal |
| `comanda_id` | FK nullable | |
| `dlv_pedido_id` | FK nullable | Delivery |
| `producao_id` | FK nullable | Quando veio de produção |
| `usuario_id` | FK nullable | |
| `motivo` | string nullable | |
| `created_at` | timestamp | |

### 4.2 Ajustes em tabelas existentes (mínimos)

| Tabela / coluna | Mudança |
|-----------------|---------|
| `dlv_produtos` | Manter `estoque` como espelho/legado ou deprecar após migração para `cardapio_estoque_saldos` |
| `dlv_produtos` | Flag `controla_estoque_cardapio` (bool, default true) — item sem controle (ex.: taxa) não baixa |
| `venda_itens` | `cardapio_produto_id` + `cardapio_movimentacao_id` (rastreio) |
| `dlv_pedidos` | Usar de verdade `estoque_baixado_em` após baixa no Estoque B |

### 4.3 O que NÃO muda no Estoque A

- Sem duplicar `stock_lotes` para cardápio
- Sem mudar regra FIFO/FEFO de insumos
- Sem quebrar fiscal/produção atuais

---

## 5. Regras de negócio

### 5.1 Abastecimento do Estoque B (entrada)

Fontes possíveis:

1. **Produção finalizada** — ao concluir ordem de produção do prato vinculado, entra N porções no cardápio da unidade.
2. **Abastecimento manual** — cozinha/gerente informa “hoje temos 40 marmitas de X”.
3. **Transferência lógica** — a partir do saldo do produto final no Estoque A (`estoque_produto_id`), “liberar para venda” N unidades no cardápio.

Toda entrada gera linha em `cardapio_estoque_movimentacoes` com `tipo=ENTRADA`.

### 5.2 Venda (saída)

1. Resolver item do cardápio (`cardapio_produto_id`).
2. Se `controla_estoque_cardapio`:
   - Verificar saldo ≥ quantidade (bloquear ou avisar conforme config).
   - Baixar `cardapio_estoque_saldos`.
   - Registrar `SAIDA` com origem `VENDA_PDV` / `VENDA_MESA` / `VENDA_DELIVERY`.
3. Se item for **revenda** (M3): manter baixa FIFO no Estoque A (comportamento atual).
4. Se item for **prato no modo M1**: só baixa Estoque B (insumos já saíram na produção).
5. Se item for **prato no modo M2** (fase 2): explodir ficha e baixar insumos no Estoque A + saída no B.

### 5.3 Cancelamento / estorno

- Cancelar venda ou item → `ESTORNO` no Estoque B (devolve saldo).
- Se havia baixa no Estoque A, restaurar como já existe em `restaurarEstoqueAposExcluirSaida`.

### 5.4 Reserva em comanda (fase 1.5)

- Ao lançar item na mesa: **reservar** (saldo disponível diminui, mas só confirma SAIDA no fechar conta).
- Ou: validar saldo no lançamento e baixar só no finalize (mais simples, aceita risco).

**Sugestão fase 1:** validar no lançamento + baixar no finalize (sem tabela de reserva ainda).

---

## 6. Fluxos operacionais do dia a dia

### 6.1 Fluxo recomendado — prato do dia

```text
1. Manhã: compras / estoque admin (A) — como hoje
2. Cozinha: produção do prato (ficha) → baixa insumos (A) → entra produto final (A)
3. Liberação: “enviar X porções ao cardápio” → ENTRADA no Estoque B
4. Cliente pede no PDV/mesa/delivery → SAIDA no Estoque B
5. Relatório: movimentações do cardápio + CMV admin
```

### 6.2 Fluxo — bebida / revenda

```text
1. Compra entra no Estoque A
2. Item cardápio vinculado a estoque_produto_id
3. Abastecimento B espelha ou sincroniza com A (ou baixa A direto na venda — como hoje)
4. Venda → baixa B (+ A se M3)
```

### 6.3 Fluxo — delivery

```text
1. Pedido confirmado / em preparo → valida saldo B
2. Pedido pago/entregue → SAIDA B + marca estoque_baixado_em
3. Cancelamento → ESTORNO B
```

---

## 7. Telas e APIs (escopo de implementação)

### 7.1 Frontend

| Tela | Função |
|------|--------|
| **Estoque do Cardápio** | Lista itens, saldo por unidade, mínimo, status |
| **Abastecer cardápio** | Entrada manual / a partir de produção |
| **Movimentações cardápio** | Histórico filtrável (igual Movimentações admin) |
| **Cardápio → Itens** | Flag controla estoque + vínculo produção/produto |
| **PDV / Mesas** | Badge “sem estoque” / bloqueio se saldo 0 |
| **Delivery admin** | Mesmo saldo B (acabar com contador isolado) |

### 7.2 APIs sugeridas

```text
GET    /api/cardapio-estoque?unidade_id=
POST   /api/cardapio-estoque/entrada
POST   /api/cardapio-estoque/ajuste
GET    /api/cardapio-estoque/movimentacoes
POST   /api/pdv/vendas/balcao          → passa a baixar B
POST   /api/pdv/comandas/{id}/finalizar → passa a baixar B
POST   /api/delivery/pedidos/...       → passa a baixar B
```

### 7.3 Pontos de código a alterar (referência)

| Arquivo | Papel |
|---------|-------|
| `app/Support/VendaFiscalSupport.php` | Após/antes FIFO admin: baixa Estoque B |
| `app/Support/CardapioComercialSupport.php` | Expor saldo e regras de disponibilidade |
| `app/Support/PdvComercialSupport.php` | Validar saldo ao vender/finalizar |
| `app/Support/Delivery/DeliveryPedidoService` (ou equivalente) | Baixa real no delivery |
| `app/Support/ProducaoFiscalSupport.php` | Opcional: entrada automática no B ao finalizar produção |
| `frontend/comercial-pdv.js`, `delivery.js` | UI saldo / bloqueio |
| Novo: `CardapioEstoqueSupport.php` | Motor do Estoque B |

---

## 8. Plano de implementação (fases)

### Fase 0 — Documentação e acordo (este documento)
- Validar M1+M3 primeiro com o negócio
- Definir se prato sem produção intermediária (M2) é necessário no curto prazo

### Fase 1 — Estoque B mínimo viável (MVP venda)
1. Migration: `cardapio_estoque_saldos` + `cardapio_estoque_movimentacoes`
2. Service `CardapioEstoqueSupport` (entrada, saída, ajuste, consulta)
3. Tela Estoque do Cardápio + abastecimento manual
4. PDV balcão + finalizar comanda: baixa B + movimentação
5. Bloquear venda se saldo insuficiente (configurável)
6. Relatório simples de movimentações
7. Migrar/ignorar `dlv_produtos.estoque` cosmético

**Resultado:** dá para vender cardápio controlando porções do dia.

### Fase 1.5 — Produção → Cardápio
- Ao finalizar produção do prato vinculado, oferecer/auto “entrar no estoque do cardápio”
- Reduz trabalho manual de abastecimento

### Fase 2 — Delivery + estornos robustos
- Baixa B no ciclo do pedido delivery
- Estorno em cancelamento
- Reservas em comanda (opcional)

### Fase 3 — Explosão de ficha na venda (M2)
- Só se a operação exigir “vende e já consome insumos” sem passar por produção
- Reaproveitar leitura de `ficha_tecnica_itens` / `ingredientes_json`
- Cuidado com semiacabados e custo médio

### Fase 4 — Indicadores e CMV comercial
- Dashboard: vendido vs produzido vs perdido
- Alertas de estoque mínimo no cardápio
- Concilição Estoque A × Estoque B

---

## 9. Matriz “antes × depois”

| Canal | Antes | Depois (Fase 1) |
|-------|-------|-----------------|
| PDV revenda | Baixa A | Baixa A + B (ou B espelhando A) |
| PDV prato | Frágil / sem BOM | Baixa B (porção abastecida) |
| Mesa | Igual PDV no finalize | Igual + validação de saldo B |
| Delivery | Contador fake | Baixa B real + histórico |
| Admin compras/lotes | OK | **Inalterado** |
| Produção | Baixa insumos A | A igual + opcional entrada B |

---

## 10. Riscos e cuidados

| Risco | Mitigação |
|-------|-----------|
| Duplicar baixa (A e B) sem critério | Regras claras M1/M2/M3 por `tipo_venda` |
| Operação esquecer de abastecer B | Fase 1.5: entrada automática da produção; alerta no PDV |
| Performance no finalize | Baixa em transação DB; índices em `(unidade_id, dlv_produto_id)` |
| Dados antigos inconsistentes | Script de carga inicial de saldos (zerar ou inventário rápido) |
| Confusão do usuário entre A e B | Nomes na UI: **Estoque (Admin)** vs **Estoque do Cardápio** |

---

## 11. Carga inicial sugerida

1. Inventário rápido: para cada item ativo do cardápio da unidade, informar saldo do dia.
2. Ou zerar tudo e só vender o que for abastecido a partir de agora.
3. Itens sem controle (`controla_estoque_cardapio = false`) seguem vendáveis sem saldo.

---

## 12. Critérios de sucesso

- [ ] Vender no PDV um item do cardápio e ver saldo B cair na hora (ou no fechar conta)
- [ ] Ver a movimentação com origem `VENDA_PDV` / `VENDA_MESA`
- [ ] Estoque administrativo de insumos continua funcionando igual em compras/produção
- [ ] Item com saldo 0 não vende (ou avisa, conforme config)
- [ ] Delivery deixa de usar contador isolado e usa o mesmo Estoque B
- [ ] Relatório do dia: entradas (produção/abastecimento) × saídas (vendas) × saldo

---

## 13. Decisão pedida ao time

1. **Confirmar** dois estoques (Admin intacto + Cardápio novo)?  
2. **Começar pela Fase 1 (M1 + M3)** — porção/revenda — sem explosão de ficha na venda?  
3. **Abastecimento:** manual no MVP ou já amarrar na produção (Fase 1.5)?  
4. **Saldo zerado:** **bloquear** venda ou só **avisar**?

---

## 14. Próximo passo técnico (quando aprovado)

1. Criar migrations das duas tabelas.  
2. Implementar `CardapioEstoqueSupport`.  
3. Integrar baixa em `VendaFiscalSupport` / PDV.  
4. Tela Estoque do Cardápio no frontend.  
5. Testes: `PdvCardapioOperacionalTest` + novos casos de saldo/baixa/estorno.

---

## Apêndice A — Arquivos-chave do estado atual

- `backend/app/Support/ProducaoEstoqueSupport.php` — FIFO admin  
- `backend/app/Support/VendaFiscalSupport.php` — baixa na venda  
- `backend/app/Support/CardapioComercialSupport.php` — resolve item do cardápio  
- `backend/app/Support/Delivery/CardapioFichaEstoqueSupport.php` — aviso de insumos (sem baixa)  
- `backend/routes/delivery_routes.php` / `pdv_routes.php`  
- `docs/pdv-operacional.md`

## Apêndice B — Glossário rápido

| Termo | Significado |
|-------|-------------|
| Estoque A | Administrativo / depósito / insumos |
| Estoque B | Comercial / porções disponíveis no cardápio |
| Revenda | Item vendido 1:1 com produto de estoque |
| Prato | Item com ficha técnica |
| Baixa | Redução de saldo por saída/venda |
| FIFO/FEFO | Ordem de consumo de lotes no Estoque A |

---

*Documento gerado a partir da análise do código SAS Estoque em 2026-08-09. Salvo também em PDF: `PROPOSTA_ESTOQUE_CARDAPIO_2026-08-09.pdf`.*

---

## Status de implementação (2026-08-09)

**Implementado no código:**

- Tabelas `cardapio_estoque_saldos` + `cardapio_estoque_movimentacoes`
- Flag `dlv_produtos.controla_estoque_cardapio`
- `CardapioEstoqueSupport` + APIs `/api/cardapio-estoque*`
- Baixa na venda PDV/mesa (`VendaFiscalSupport`) e delivery
- Tela **Cardápio → Estoque**
- Bloqueio de venda com saldo zerado (Fase 1: M1 + M3)

**Ainda futuro:** entrada automática produção→cardápio (1.5), reserva em comanda, explosão de ficha na venda (M2).
