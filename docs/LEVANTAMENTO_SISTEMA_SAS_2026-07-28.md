# Levantamento — SAS-Estoque (visão diretoria)

**Data:** 28/07/2026  
**Para quem é:** diretoria e gestão (sem detalhe de programação).  
**Arquivo completo (Sistema + Fiscal):** gere o PDF `LEVANTAMENTO_SAS_2026-07-28` com o script interno da equipe técnica, se precisar de cópia oficial.

---

## 1. Mensagem principal

O **SAS-Estoque** é o sistema do **Grupo Sabor Paraense** para operar **estoque, compras, financeiro gerencial, loja (PDV), delivery, reservas, RH, patrimônio e fiscal interno**, incluindo **emissão de cupom fiscal (NFC-e)** quando a Focus e o cadastro estiverem configurados.

**Em uma frase:** a maior parte **já está pronta para uso**; falta **cadastro, rotina da operação** e, para nota fiscal, **configuração com Focus e contador** — não falta “construir o sistema do zero”.

---

## 2. Está pronto para usar?

| Pergunta | Resposta |
|----------|----------|
| Controlar estoque, compras e fornecedores? | **Sim** — usar após cadastro inicial |
| Vender no caixa e baixar estoque? | **Sim** — Comercial → PDV / comandas |
| Delivery, cardápio, fidelidade, orçamentos, reservas? | **Sim** — se a unidade for usar esses processos |
| Relatórios financeiros gerenciais (fluxo, DRE, fechamento de caixa)? | **Sim** — depende de lançamentos no dia a dia |
| Emitir **NFC-e** (cupom na SEFAZ)? | **Sim no sistema** — falta configurar **Focus**, CSC e produtos/empresa |
| Substituir **contador**, SPED ou **folha de pagamento**? | **Não** |
| iFood / Rappi nativo, folha salarial completa? | **Não** — fora do escopo hoje |

---

## 3. O que tem no sistema (por área)

Legenda das colunas: **Pronto** = pode usar; **Configurar** = o que a operação ou diretoria precisa providenciar.

### Operação e estoque

| Área | Pronto | Configurar |
|------|--------|------------|
| Unidades, produtos, locais, lotes | Sim | Cadastrar lojas e itens |
| Entrada/saída de estoque, movimentações | Sim | Processo interno |
| Lista de compras e fornecedores | Sim | Rotina de compra |
| Ficha técnica (receitas) | Sim | Montar fichas dos pratos |
| Relatórios de estoque | Sim | — |

### Loja e vendas

| Área | Pronto | Configurar |
|------|--------|------------|
| PDV / caixa | Sim | Unidade, produtos, formas de pagamento |
| Mesas e comandas, cozinha | Sim | Mapa de mesas / fluxo da casa |
| Clientes e histórico de vendas | Sim | — |
| Fechamento de caixa (comercial e financeiro) | Sim | Conferência diária |

### Delivery e cardápio

| Área | Pronto | Configurar |
|------|--------|------------|
| Cardápio (itens, preços, categorias) | Sim | Vincular produtos |
| Delivery (pedidos, vitrine, entregadores, fretes) | Sim | Catálogo e regras de entrega |
| VendaFácil (pedidos externos) | Sim | Contrato/token e teste de conexão |

### Financeiro (gestão)

| Área | Pronto | Configurar |
|------|--------|------------|
| Fluxo de caixa, contas a receber, DRE, CMV | Sim | Lançar movimentos |
| Boletos, despesas fixas, impostos (controle de vencimentos) | Sim | Anexos e datas |
| Orçamento empresarial e indicadores | Sim | Metas e lançamentos |

### Pessoas, patrimônio e outros

| Área | Pronto | Configurar |
|------|--------|------------|
| Funcionários, ponto, vagas, candidatos | Sim | Cadastro RH |
| Cálculo/simulação de rescisão | Sim | Dados contratuais |
| Folha de **pagamento** / eSocial | **Não** | Contador / outro sistema |
| Patrimônio (bens, manutenção, inventário de bens) | Sim | Cadastro de equipamentos |
| Energia (equipamentos e projeção) | Sim | Lançar consumos |
| Investimentos (carteira, simulador) | Sim | Se o grupo usar o módulo |
| Reservas de mesa | Sim | Mesas e capacidade |
| Programa de fidelidade | Sim | Regras e prêmios |
| Orçamentos comerciais (eventos/serviços) | Sim | Se usar esse fluxo |

### Assistente de IA (Ayla)

| Área | Pronto | Configurar |
|------|--------|------------|
| Consultar estoque, produtos, patrimônio, reservas (leitura) | Sim | Permissões e canais (ex.: Telegram), com TI |
| IA lançar venda ou nota sozinha | **Não** | Operação continua no menu normal |

---

## 4. Fiscal (resumo para diretoria)

| Item | Pronto | Quem faz |
|------|--------|----------|
| Cadastro de empresa (CNPJ), produtos fiscais | Sim | Operação + fiscal interno |
| Venda no PDV com baixa de estoque | Sim | Loja |
| **NFC-e automática no caixa** (Focus) | Sim no SAS | **Focus** (certificado), **SEFAZ/CSC**, depois ligar em “Emissão NF-e/NFC-e” |
| Painel do mês (entradas/saídas **estimadas**) | Sim | Gestão; **contador valida** |
| Pacote mensal ZIP para o contador | Sim | Enviar todo mês ao escritório |
| SPED, PGDAS transmitido, cancelamento de cupom pelo sistema | **Não** | Contador / evolução futura |
| NF-e B2B automática | **Não** | Evolução futura |

**Ordem prática para cupom fiscal:** cadastrar empresa e produtos → contratar/configurar **Focus** → testar em **homologação** → liberar **produção**.

Detalhe fiscal ampliado: **Parte 2** deste pacote (mesmo PDF unificado) ou arquivo fiscal separado mantido pela TI.

---

## 5. O que a diretoria **não** deve esperar do SAS

- Escrituração oficial (SPED, PGDAS enviado, DCTF)  
- Folha de pagamento completa  
- Substituir o contador — o **pacote mensal** só **ajuda**  
- Marketplaces (iFood etc.) integrados nativamente  

---

## 6. O que precisa existir para “estar no ar”

Isso **não** é detalhe de programação — é o que a **operação + TI** combinam:

1. **Servidor** onde o SAS já roda (ou será instalado) — responsabilidade da **TI**.  
2. **Atualização do banco** quando a TI liberar nova versão (comando único de atualização — TI).  
3. **Usuários** criados (ADMIN, gerentes, caixa, estoque).  
4. **Cadastro mínimo:** unidades, produtos, fornecedores.  
5. Se usar **PDV:** treinar caixa e escolher unidade correta.  
6. Se usar **NFC-e:** Focus + checklist fiscal (seção 4).  
7. **Backup** periódico (TI / ADMIN).  

Sem isso, o software existe mas a loja **não opera** de forma confiável.

---

## 7. Conclusão para decisão

| Decisão | Situação em 28/07/2026 |
|---------|-------------------------|
| Investir operação no SAS para estoque + loja? | **Sim** — módulos prontos |
| Contar com NFC-e pelo SAS? | **Sim**, após configuração Focus/CSC |
| Parar contador / SPED? | **Não** |
| Prioridade da diretoria | Cadastro, treinamento, homologação fiscal, rotina de fechamento e pacote ao contador |

---

*Grupo Sabor Paraense — levantamento para diretoria. Versão 28/07/2026.*
