# SAS-ESTOQUE — MÓDULO 5: PRODUÇÃO, FICHA TÉCNICA, CUSTO E RASTREABILIDADE FISCAL

## Instrução para o Cursor IA

Você está trabalhando no **SAS-Estoque**, sistema já existente e em produção.

Implementar exclusivamente:

# MÓDULO 5 — PRODUÇÃO

Este módulo utiliza a estrutura criada anteriormente em:

- Módulo 1 — Cadastro Fiscal e Tributário;
- Módulo 2 — Compras / Entrada Fiscal / Estoque por CNPJ;
- Módulo 3 — Movimentações de Estoque e Eventos Fiscais.

Fluxo principal:

```text
FICHA TÉCNICA
      ↓
ARROZ + PEIXE + ÓLEO + TEMPEROS
      ↓
ORDEM / REGISTRO DE PRODUÇÃO
      ↓
BAIXA DOS INSUMOS E LOTES
      ↓
CÁLCULO DO CUSTO REAL
      ↓
PRODUTO PRODUZIDO — PRATO X
      ↓
ENTRADA DO PRODUTO PRODUZIDO
      ↓
CUSTO + ORIGEM + INFORMAÇÃO FISCAL
      ↓
EVENTO DE PRODUÇÃO
```

O objetivo é fazer o SAS saber exatamente:

- o que foi produzido;
- quanto foi produzido;
- quais insumos foram utilizados;
- de quais lotes saíram;
- qual CNPJ realizou a produção;
- quanto custaram os insumos;
- qual foi o custo da produção;
- qual é o custo unitário do produto produzido;
- quais informações fiscais acompanham os insumos;
- qual evento fiscal foi gerado;
- quais perdas de produção ocorreram.

> Não implementar apuração tributária mensal definitiva neste módulo.
> Não alterar o PDV.
> Não misturar produção com perda ou consumo interno.

---

# 1. REGRA PRINCIPAL

Antes de programar:

1. Mapear módulos atuais de produtos, estoque, lotes, receitas/fichas técnicas e produção.
2. Reutilizar estruturas existentes.
3. Não criar segundo estoque.
4. Não duplicar produtos.
5. Não apagar históricos.
6. Não modificar compras.
7. Não alterar transferências.
8. Não alterar PDV.
9. Utilizar transações de banco.
10. Utilizar migrations incrementais e reversíveis.

---

# 2. CONCEITO DE FICHA TÉCNICA

Criar ou adaptar:

# Ficha Técnica de Produção

Exemplo:

```text
Produto final:
Prato Filhote Frito

Rendimento:
10 pratos

Ingredientes:

Arroz .............. 2,000 kg
Filhote ............ 3,000 kg
Óleo ............... 0,500 L
Farinha ............ 0,400 kg
Temperos ........... 0,200 kg
```

A ficha técnica representa o consumo padrão esperado.

---

# 3. ESTRUTURA DA FICHA TÉCNICA

Possível tabela:

`fichas_tecnicas`

Campos:

```text
id
empresa_id nullable
produto_final_id
nome
rendimento_quantidade
rendimento_unidade
versao
ativo
observacao
created_at
updated_at
```

Itens:

`ficha_tecnica_itens`

```text
id
ficha_tecnica_id
produto_insumo_id
quantidade_padrao
unidade_medida
percentual_perda_prevista nullable
observacao nullable
created_at
updated_at
```

Adaptar à estrutura atual.

---

# 4. PRODUTO FINAL

Somente produto corretamente classificado deve ser utilizado como produto final.

Consultar classificação fiscal do Módulo 1.

Exemplo:

```text
Produto: Filhote Frito Executivo
Tipo Fiscal: Produção própria
```

Gerar alerta caso um produto marcado apenas como revenda seja selecionado como produto produzido.

---

# 5. INSUMOS

Ingredientes devem estar cadastrados como produtos.

Preferencialmente:

```text
tipo_fiscal = insumo
```

Exemplos:

- arroz;
- peixe;
- óleo;
- farinha;
- temperos;
- legumes.

Não permitir ingrediente fictício sem vínculo com estoque quando a intenção for controlar custo e rastreabilidade.

---

# 6. UNIDADES DE MEDIDA

A produção deve respeitar unidades de medida.

Exemplos:

```text
kg
g
L
ml
un
cx
pct
```

Se já existir conversão de unidades, reutilizar.

Caso seja necessário converter:

```text
1 kg = 1000 g
1 L = 1000 ml
```

Criar arquitetura segura de conversão.

---

# 7. RENDIMENTO

Toda ficha deve possuir rendimento.

Exemplo:

```text
Ficha:
10 pratos

Custo total:
R$ 180,00

Custo unitário teórico:
R$ 18,00
```

O cálculo deve utilizar valores reais do estoque/lotes quando a produção for executada.

---

# 8. ORDEM / REGISTRO DE PRODUÇÃO

Criar ou adaptar:

`producoes`

Campos sugeridos:

```text
id
empresa_id
unidade_id
ficha_tecnica_id
produto_final_id
quantidade_planejada
quantidade_produzida
data_producao
status
usuario_id
custo_insumos
custo_adicional
custo_total
custo_unitario
observacao
created_at
updated_at
```

---

# 9. STATUS DA PRODUÇÃO

Utilizar:

```text
rascunho
planejada
em_producao
finalizada
cancelada
```

Uma produção finalizada não deve ser apagada.

Se necessário, utilizar estorno/cancelamento controlado.

---

# 10. FLUXO DE PRODUÇÃO

```text
Selecionar empresa/unidade
        ↓
Selecionar produto final
        ↓
Carregar ficha técnica
        ↓
Informar quantidade a produzir
        ↓
Calcular insumos necessários
        ↓
Verificar estoque
        ↓
Selecionar lotes
        ↓
Registrar consumo real
        ↓
Baixar insumos
        ↓
Calcular custo
        ↓
Registrar rendimento real
        ↓
Entrada do produto produzido
        ↓
Gerar evento de produção
```

---

# 11. CÁLCULO DA NECESSIDADE

Exemplo:

Ficha para 10 pratos:

```text
Arroz = 2 kg
Peixe = 3 kg
```

Produção solicitada:

```text
20 pratos
```

Sistema sugere:

```text
Arroz = 4 kg
Peixe = 6 kg
```

Permitir ajuste do consumo real.

---

# 12. CONSUMO PREVISTO x CONSUMO REAL

Guardar ambos:

```text
quantidade_prevista
quantidade_real
```

Isso permitirá descobrir desperdícios.

Exemplo:

```text
Previsto peixe: 3,000 kg
Real: 3,400 kg
Diferença: 0,400 kg
```

---

# 13. LOTES UTILIZADOS

A produção deve registrar exatamente quais lotes foram consumidos.

Exemplo:

```text
Peixe
Lote 001 → 2 kg
Lote 002 → 1,4 kg
```

Nunca perder a rastreabilidade da origem.

---

# 14. SELEÇÃO DE LOTE

Seguir regra existente do SAS.

Quando aplicável, priorizar:

- validade;
- política FEFO/FIFO existente;
- lote selecionado manualmente quando autorizado.

Não alterar silenciosamente a política atual.

---

# 15. VALIDAÇÃO DE SALDO

Antes de finalizar:

```text
saldo disponível >= consumo real
```

Validar dentro da transaction.

Não permitir estoque negativo indevido.

---

# 16. BAIXA DOS INSUMOS

Ao finalizar produção:

```text
Arroz ↓
Peixe ↓
Óleo ↓
Temperos ↓
```

Gerar movimentações vinculadas à produção.

Tipo:

```text
producao
```

ou subtipo:

```text
consumo_producao
```

Nunca registrar como consumo interno.

Nunca registrar como perda.

---

# 17. PRODUTO PRODUZIDO

Depois da baixa dos insumos:

```text
Produto Final ↑
```

Gerar entrada de produção.

Tipo:

```text
entrada_producao
```

Vincular:

```text
producao_id
empresa_id
unidade_id
produto_final_id
quantidade
custo_total
custo_unitario
```

---

# 18. LOTE DO PRODUTO PRODUZIDO

Quando aplicável, gerar lote próprio.

Exemplo:

```text
PROD-2026-000123
```

Guardar:

```text
data_producao
data_validade nullable
quantidade_inicial
quantidade_atual
custo_unitario
empresa_id
unidade_id
producao_id
```

---

# 19. CUSTO DOS INSUMOS

O custo deve vir dos lotes realmente consumidos.

Exemplo:

```text
Arroz:
2 kg × custo do lote

Peixe:
3 kg × custo dos lotes

Óleo:
0,5 L × custo do lote
```

Somar:

```text
custo_insumos
```

---

# 20. CUSTO TOTAL DE PRODUÇÃO

Estrutura inicial:

```text
Custo Total =
Custo dos Insumos
+ Custos Adicionais de Produção
```

Não incluir automaticamente custos que o SAS ainda não controla.

Preparar campos opcionais para:

```text
embalagem
mão de obra
energia
gás
outros custos
```

---

# 21. CUSTO UNITÁRIO

Fórmula:

```text
custo_unitario =
custo_total / quantidade_produzida
```

Tratar divisão por zero.

---

# 22. CUSTO TEÓRICO x REAL

Guardar:

```text
custo_teorico
custo_real
```

Permitir relatório de diferença.

Isso ajudará o restaurante a identificar desperdício e alteração de custo.

---

# 23. PERDA PREVISTA DE PRODUÇÃO

A ficha técnica pode informar perda prevista.

Exemplo:

```text
Peixe bruto = 10 kg
Perda prevista limpeza = 20%
Rendimento esperado = 8 kg
```

Não confundir perda técnica prevista com perda de estoque extraordinária.

---

# 24. PERDA REAL NA PRODUÇÃO

Permitir registrar:

```text
peso_bruto
peso_liquido
perda_real
```

Quando aplicável.

A perda deve ficar vinculada à produção.

---

# 25. DESVIO DE PRODUÇÃO

Calcular:

```text
Consumo Real - Consumo Previsto
```

Mostrar:

```text
Dentro do esperado
Acima do esperado
Abaixo do esperado
```

Não transformar automaticamente qualquer diferença em evento fiscal de perda.

---

# 26. CNPJ RESPONSÁVEL PELA PRODUÇÃO

Toda produção deve possuir:

```text
empresa_id
unidade_id
```

A unidade deve pertencer à empresa.

---

# 27. REGRA ENTRE CNPJs

Não permitir uma produção consumir diretamente estoque pertencente a outro CNPJ.

Exemplo proibido:

```text
Produção = CNPJ B
Insumo fiscalmente pertencente = CNPJ C
```

Antes deve existir operação válida entre os CNPJs conforme módulo de movimentações.

---

# 28. PROPRIEDADE DO PRODUTO FINAL

O produto produzido pertence ao mesmo CNPJ responsável pela produção.

Fluxo:

```text
Insumos CNPJ B
      ↓
Produção CNPJ B
      ↓
Produto Final CNPJ B
```

---

# 29. INFORMAÇÃO FISCAL DOS INSUMOS

Preservar referência até:

```text
Compra
NF de entrada
Item da NF
Produto
Lote
Crédito fiscal potencial
```

A produção não deve apagar essa cadeia.

---

# 30. INFORMAÇÃO FISCAL DO PRODUTO FINAL

O produto final mantém sua própria classificação fiscal cadastrada no Módulo 1.

A produção deve vincular:

```text
produto_final
perfil_tributario
empresa
produção
insumos consumidos
```

Não copiar cegamente CST/NCM dos insumos para o produto final.

---

# 31. EVENTO FISCAL DE PRODUÇÃO

Ao finalizar produção, criar evento:

```text
tipo_evento = producao
```

Vincular:

```text
empresa_id
unidade_id
producao_id
produto_final_id
valor_base
data_evento
status
```

---

# 32. EVENTOS DOS INSUMOS

Cada baixa de insumo deve estar vinculada ao evento/produção.

Exemplo:

```text
Produção #125
 ├─ Arroz / Lote A
 ├─ Peixe / Lote B
 ├─ Óleo / Lote C
 └─ Temperos / Lote D
```

---

# 33. EVENTO FISCAL NÃO É IMPOSTO FINAL

Nesta fase:

```text
Evento de produção
```

serve para registrar a transformação.

Não calcular automaticamente:

- imposto mensal;
- guia;
- DARF;
- ICMS definitivo;
- PIS/COFINS definitivo;
- CBS/IBS definitivo.

---

# 34. REVERSÃO / CANCELAMENTO

Produção finalizada não deve ser excluída.

Caso seja necessário cancelar:

```text
Produção
   ↓
Estorno controlado
   ↓
Restaurar insumos quando permitido
   ↓
Retirar produto final
   ↓
Cancelar eventos relacionados
```

Manter histórico.

---

# 35. TRANSAÇÃO DE BANCO

Finalização deve ocorrer em uma única transaction:

```text
validar saldo
baixar insumos
registrar lotes consumidos
calcular custo
gerar produto final
criar lote final
registrar entrada
gerar eventos
finalizar produção
```

Se falhar:

```text
ROLLBACK
```

---

# 36. CONCORRÊNCIA

Bloquear saldos/lotes necessários durante a finalização quando apropriado.

Evitar que duas produções consumam simultaneamente o mesmo saldo.

---

# 37. INTERFACE — FICHAS TÉCNICAS

Menu sugerido:

```text
Produção
 ├── Fichas Técnicas
 ├── Produções
 └── Relatórios
```

Se já existir menu Produção, reutilizar.

---

# 38. TELA DA FICHA

Mostrar:

```text
Produto final
Rendimento
Versão
Status

INGREDIENTES
Produto | Quantidade | Unidade | Perda prevista
```

Mostrar também:

```text
Custo estimado atual
Custo unitário estimado
```

---

# 39. VERSIONAMENTO DA FICHA

Não sobrescrever silenciosamente histórico.

Se ficha utilizada em produções anteriores for alterada, considerar versionamento:

```text
Versão 1
Versão 2
```

Produções antigas continuam ligadas à versão utilizada.

---

# 40. TELA NOVA PRODUÇÃO

Passos:

```text
1. Empresa / Unidade
2. Produto a produzir
3. Ficha técnica
4. Quantidade planejada
5. Insumos necessários
6. Lotes disponíveis
7. Consumo real
8. Rendimento real
9. Custos
10. Confirmar produção
```

---

# 41. PREVISÃO ANTES DE PRODUZIR

Antes da confirmação mostrar:

```text
Produção planejada: 50 pratos

Arroz necessário: ...
Saldo: ...

Peixe necessário: ...
Saldo: ...

Óleo necessário: ...
Saldo: ...

Custo estimado: R$ ...
Custo estimado por prato: R$ ...
```

---

# 42. ALERTA DE FALTA DE INSUMO

Se faltar:

```text
Produção não pode ser concluída.
```

Mostrar exatamente:

```text
Produto
Necessário
Disponível
Faltante
```

---

# 43. ALERTA DE CUSTO

Permitir futuramente configurar alerta:

```text
Custo atual da ficha aumentou X%
```

Nesta fase pode apenas preparar comparação.

---

# 44. RELATÓRIO DE PRODUÇÃO

Filtros:

```text
Período
Empresa
Unidade
Produto final
Status
```

Mostrar:

```text
Data
Produção
Produto
Quantidade
Custo total
Custo unitário
Responsável
Status
```

---

# 45. RELATÓRIO DE CONSUMO DE INSUMOS

Mostrar:

```text
Produção
Produto
Lote
Previsto
Real
Diferença
Custo
```

---

# 46. RELATÓRIO DE DESPERDÍCIO

Mostrar:

```text
Produto
Quantidade prevista
Quantidade real
Desvio
Custo do desvio
```

Separar:

- perda técnica prevista;
- consumo acima do previsto;
- perda extraordinária.

---

# 47. RASTREABILIDADE COMPLETA

O sistema deve conseguir navegar:

```text
Produto final
      ↓
Produção
      ↓
Insumos
      ↓
Lotes
      ↓
Compras
      ↓
Notas Fiscais
      ↓
Fornecedores
```

E também no sentido inverso:

```text
Lote de peixe
      ↓
Produções que utilizaram o lote
      ↓
Produtos produzidos
```

---

# 48. POSSÍVEIS TABELAS

Somente se não existirem equivalentes:

```text
fichas_tecnicas
ficha_tecnica_itens
producoes
producao_insumos
producao_lotes
```

Adaptar à arquitetura real.

---

# 49. PRODUCAO_INSUMOS

Campos possíveis:

```text
id
producao_id
produto_id
quantidade_prevista
quantidade_real
custo_total
created_at
updated_at
```

---

# 50. PRODUCAO_LOTES

Campos possíveis:

```text
id
producao_id
producao_insumo_id
lote_id
quantidade_consumida
custo_unitario
custo_total
created_at
updated_at
```

---

# 51. SERVICES

Se o projeto usa services:

```text
FichaTecnicaService
ProducaoService
CustoProducaoService
RastreabilidadeProducaoService
```

Reutilizar:

```text
MovimentacaoEstoqueService
EventoFiscalService
```

do Módulo 3.

---

# 52. NÃO DUPLICAR REGRA DE ESTOQUE

Toda baixa deve passar pelo mecanismo central de movimentação criado anteriormente.

Não fazer:

```text
produto->estoque -= quantidade
```

diretamente no controller.

Usar o serviço central de estoque.

---

# 53. PERMISSÕES

Se aplicável:

```text
producao.visualizar
producao.criar
producao.finalizar
producao.cancelar
ficha_tecnica.visualizar
ficha_tecnica.criar
ficha_tecnica.editar
relatorio_producao.visualizar
```

---

# 54. AUDITORIA

Registrar:

```text
usuário
empresa
unidade
ficha
versão
produto
quantidade planejada
quantidade produzida
insumos previstos
insumos reais
lotes
custos
data/hora
cancelamento
```

---

# 55. TESTES DA FICHA TÉCNICA

Testar:

- criar ficha;
- adicionar ingredientes;
- editar;
- versionar;
- rendimento;
- produto final;
- insumos;
- unidades de medida.

---

# 56. TESTES DA PRODUÇÃO

Testar:

- produção com saldo suficiente;
- produção sem saldo;
- consumo de múltiplos lotes;
- baixa correta;
- entrada do produto final;
- custo total;
- custo unitário;
- empresa correta;
- unidade correta;
- evento fiscal;
- rollback em falha.

---

# 57. TESTE ENTRE CNPJs

Garantir que:

```text
Produção CNPJ B
```

não consuma diretamente:

```text
Estoque CNPJ C
```

---

# 58. TESTE DE RASTREABILIDADE

Confirmar:

```text
produto final
→ produção
→ insumo
→ lote
→ NF
→ compra
→ fornecedor
```

---

# 59. TESTES DE REGRESSÃO

Confirmar:

```text
Produtos: OK
Perfis fiscais: OK
Empresas: OK
Unidades: OK
Compras: OK
NF Entrada: OK
Lotes: OK
Estoque: OK
Movimentações: OK
Transferências: OK
Eventos fiscais: OK
PDV: OK
```

---

# 60. NÃO ALTERAR O PDV

Neste módulo não:

- criar venda;
- alterar preço de venda automaticamente;
- gerar imposto de venda;
- bloquear PDV.

A integração do produto produzido com a venda será tratada no módulo de Venda/PDV.

---

# 61. NÃO CRIAR APURAÇÃO TRIBUTÁRIA

Não calcular nesta fase:

```text
ICMS mensal
PIS/COFINS mensal
IRPJ
CSLL
CBS/IBS final
imposto total a pagar
```

---

# 62. DOCUMENTAÇÃO

Criar:

`docs/modulo-fiscal-05-producao.md`

Documentar:

- migrations;
- tabelas;
- models;
- services;
- controllers;
- rotas;
- telas;
- cálculos;
- ficha técnica;
- custo;
- lotes;
- eventos;
- testes;
- pendências.

---

# 63. RELATÓRIO FINAL DO CURSOR

```text
MÓDULO 5 — PRODUÇÃO

[OK] Ficha técnica
[OK] Versionamento
[OK] Insumos
[OK] Rendimento
[OK] Ordem de produção
[OK] Baixa dos insumos
[OK] Lotes consumidos
[OK] Custo dos insumos
[OK] Custo total
[OK] Custo unitário
[OK] Produto produzido
[OK] Lote do produto final
[OK] Rastreabilidade
[OK] Evento fiscal
[OK] Empresa/CNPJ
[OK] Auditoria
[OK] Relatórios
[OK] Testes

Arquivos criados:
...

Arquivos alterados:
...

Migrations:
...

Testes:
...

Resultado:
...

Pendências:
...
```

---

# 64. ORDEM EXATA DE IMPLEMENTAÇÃO

1. Mapear estrutura atual.
2. Verificar se já existe ficha técnica/receita.
3. Verificar produção existente.
4. Mapear estoque e lotes.
5. Reutilizar MovimentacaoEstoqueService.
6. Reutilizar EventoFiscalService.
7. Criar/adaptar ficha técnica.
8. Criar versionamento.
9. Criar itens da ficha.
10. Criar/adaptar ordem de produção.
11. Calcular necessidade de insumos.
12. Selecionar lotes.
13. Registrar consumo previsto/real.
14. Baixar insumos.
15. Calcular custo.
16. Registrar rendimento.
17. Criar entrada do produto final.
18. Criar lote final quando necessário.
19. Criar evento fiscal.
20. Criar rastreabilidade.
21. Criar relatórios.
22. Criar testes.
23. Validar regressões.
24. Documentar.

---

# 65. CRITÉRIOS DE ACEITE

O módulo estará concluído quando:

- ficha técnica funcionar;
- produto final possuir rendimento;
- ingredientes forem produtos reais;
- consumo previsto for calculado;
- consumo real puder ser informado;
- lotes consumidos forem identificados;
- estoque dos insumos baixar corretamente;
- produto produzido entrar corretamente;
- custo real acompanhar a produção;
- custo unitário for calculado;
- produto final pertencer ao CNPJ produtor;
- estoque de outro CNPJ não puder ser consumido diretamente;
- informação fiscal dos insumos permanecer rastreável;
- evento fiscal de produção for criado;
- produção finalizada não puder ser apagada sem estorno;
- testes passarem.

---

# 66. RESULTADO ESPERADO

Exemplo:

```text
PRODUÇÃO #125

CNPJ:
Sabor Paraense B

Produto:
Filhote Frito Executivo

Quantidade:
50 pratos

INSUMOS

Arroz
Previsto: 10 kg
Real: 10,2 kg
Lotes: A01 / A02

Peixe
Previsto: 15 kg
Real: 15,8 kg
Lotes: P22 / P23

Óleo
Previsto: 2,5 L
Real: 2,7 L

Temperos
Previsto: 1 kg
Real: 1 kg

Custo dos insumos:
R$ ...

Custo total:
R$ ...

Custo unitário:
R$ ...

Produto final:
50 unidades adicionadas ao estoque

Evento:
PRODUÇÃO

Rastreabilidade:
Produto final → Produção → Insumos → Lotes → NF → Fornecedor
```

---

# 67. PRÓXIMO MÓDULO

Depois deste módulo, avançar para:

# MÓDULO 6 — VENDA / PDV E EVENTO TRIBUTÁRIO DE SAÍDA

Fluxo futuro:

```text
Produto no estoque
      ↓
PDV do CNPJ correto
      ↓
Venda
      ↓
Baixa do lote
      ↓
Documento fiscal
      ↓
Receita
      ↓
Tributos da saída
      ↓
Evento fiscal
```

Não iniciar o Módulo 6 antes de validar o Módulo 5.

---

# COMANDO FINAL PARA O CURSOR

Implemente exclusivamente o **Módulo 5 — Produção, Ficha Técnica, Custo e Rastreabilidade Fiscal**.

Não recrie módulos existentes.

A produção deve partir de uma ficha técnica versionada.

Ao produzir, calcular os insumos necessários, identificar os lotes realmente consumidos, baixar esses insumos pelo mecanismo central de movimentação de estoque e registrar o produto final no estoque do mesmo CNPJ responsável pela produção.

Calcule o custo utilizando os lotes efetivamente consumidos.

Preserve a rastreabilidade fiscal dos insumos até compra, NF e fornecedor.

O produto final deve manter sua própria classificação fiscal e ficar vinculado à produção, empresa, unidade, custo e lote quando aplicável.

Produção não é perda e não é consumo interno.

Não permitir que um CNPJ consuma diretamente estoque fiscal pertencente a outro CNPJ.

Gerar evento fiscal de produção, mas não calcular apuração tributária definitiva.

Não alterar o PDV.

Use transactions, migrations reversíveis e os serviços centrais de estoque/eventos já implementados.

Preserve integralmente os dados existentes.

Ao finalizar, execute testes de regressão e entregue relatório técnico completo antes de iniciar outro módulo.
