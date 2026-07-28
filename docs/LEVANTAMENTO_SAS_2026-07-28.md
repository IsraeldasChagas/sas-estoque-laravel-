# Levantamento completo — Grupo Sabor Paraense (SAS-Estoque)

**Data:** 28/07/2026  
**Público:** diretoria e gestão  
**Arquivo:** `LEVANTAMENTO_SAS_2026-07-28` (Sistema + Fiscal)  
**PDF:** logo do grupo no topo; legenda de termos no **Rodapé** (final).

---

# Parte 1 — Sistema (geral)

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


---

# Parte 2 — Fiscal

## 1. Fiscal: dá para usar?

| Situação | Pronto? |
|----------|---------|
| Cadastro CNPJ, produtos fiscais, compras com nota de entrada, vendas no PDV com estoque | **Sim** — falta cadastro e rotina |
| Painel do mês (estimativa de entradas/saídas) | **Sim** — contador confirma valores |
| **NFC-e no caixa** (via Focus) | **Sim no SAS** — falta Focus, CSC e teste SEFAZ |
| Pacote ZIP mensal para o contador | **Sim** |
| SPED, PGDAS enviado, DCTF | **Não** — contador / outros programas |
| Cancelar cupom, NF-e B2B, importar XML de compra sozinho | **Ainda não** no sistema |

---

## 2. Como as peças se ligam (visão simples)

```
Empresa (CNPJ) → Unidade da loja
    → Produtos com dados fiscais
    → Compras (nota de entrada, se usar)
    → Venda no PDV → baixa estoque → NFC-e (se ligado)
    → Painel do mês + pacote para o contador
```

Atalho no menu: **Comercial → Fiscal** (mesmas funções das configurações fiscais).

---

## 3. Onde a operação acessa

| Menu | Função |
|------|--------|
| Configurações → Empresas (CNPJ) | Quem emite nota |
| Configurações → Emissão NF-e / NFC-e | Focus, token, CSC, ligar cupom no PDV |
| Configurações → Pacote contador | Download mensal para o escritório |
| Comercial → PDV | Venda que pode gerar cupom |
| Configurações → Consolidação (M7) | Visão gerencial do período |

---

## 4. Quem faz o quê

| Responsável | O quê |
|-------------|--------|
| **SAS (já feito)** | Telas, registro de venda, envio para Focus, pacote ZIP |
| **Operação** | Empresa, unidade, produtos completos, PDV na unidade certa |
| **Focus + contador** | Certificado digital, conta Focus, CSC, homologação e produção |
| **Contador** | Obrigações legais; usa o ZIP como apoio |

---

## 5. Passo a passo para cupom fiscal

1. Cadastrar **empresa** e ligar à **unidade** do caixa.  
2. Completar **produtos** que entram na nota (códigos fiscais).  
3. Abrir conta **Focus**, certificado no painel Focus.  
4. No SAS: **Emissão NF-e / NFC-e** → homologação → testar venda.  
5. Aprovado → mudar para **produção**.  
6. Todo mês: **Pacote contador** para o escritório.  

---

## 6. O que o SAS não faz na parte fiscal

- Não substitui SPED, PGDAS transmitido nem contador  
- Não cancela NFC-e pela tela hoje  
- Não emite NF-e B2B automática  
- Não importa XML de compra sozinho  

Isso **não impede** vender com cupom após configurar Focus.

---

*Complemento fiscal — Grupo Sabor Paraense, 28/07/2026.*


---

## Rodapé — Legenda de siglas e termos

**Nome do documento:** LEVANTAMENTO_SAS_2026-07-28  

Para leitura da **diretoria** — só o que aparece no texto do levantamento.

### Negócio e operação

| Termo | Significado |
|-------|-------------|
| **SAS / SAS-Estoque** | Sistema de gestão do Grupo Sabor Paraense |
| **PDV** | Caixa / ponto de venda na loja |
| **Comanda** | Conta aberta de uma mesa |
| **Delivery** | Pedidos para entrega |
| **DRE** | Relatório de resultado (receitas x despesas) — gestão |
| **CMV** | Quanto de mercadoria saiu do estoque pelas vendas |
| **Fechamento de caixa** | Conferência do dinheiro e vendas do dia |
| **Ficha técnica** | Receita do prato (insumos) |
| **VendaFácil** | Sistema externo de pedidos conectado ao SAS |
| **Ayla** | Assistente de IA para **consultar** dados (não substitui o caixa) |

### Fiscal (quando aparecer)

| Termo | Significado |
|-------|-------------|
| **CNPJ** | Cadastro da empresa |
| **NFC-e** | Cupom fiscal eletrônico na venda ao cliente |
| **NF-e** | Nota fiscal “grande”, usual em venda para outra empresa |
| **Focus NFe** | Empresa parceira que envia a nota para o governo (SEFAZ) |
| **SEFAZ** | Fazenda estadual que autoriza ou barra o cupom |
| **CSC** | Códigos exigidos pela SEFAZ para NFC-e |
| **Homologação** | Ambiente de **teste** do cupom |
| **Produção** | Cupom **valendo** de verdade |
| **Pacote contador** | Arquivo ZIP mensual com vendas e resumos para o escritório |
| **SPED / PGDAS** | Obrigações oficiais — feitas pelo **contador**, não pelo SAS |
| **M7** | Painel fiscal de **estimativa** do mês (gestão, não imposto oficial) |

### Perfis de acesso

| Termo | Significado |
|-------|-------------|
| **ADMIN** | Usuário com acesso total, inclusive configurações |
| **GERENTE** | Operação da loja e relatórios; menos configuração que ADMIN |

---

*Grupo Sabor Paraense — legenda no final do PDF `LEVANTAMENTO_SAS_2026-07-28`.*
