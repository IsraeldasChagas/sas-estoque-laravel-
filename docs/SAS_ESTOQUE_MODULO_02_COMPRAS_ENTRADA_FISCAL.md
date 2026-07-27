# SAS-ESTOQUE — MÓDULO 2: COMPRAS, ENTRADA FISCAL E VÍNCULO DO ESTOQUE AO CNPJ

## Instrução para o Cursor IA

Você está trabalhando no projeto **SAS-Estoque**, já existente e em produção.

Este documento descreve exclusivamente a implementação do:

# **Módulo 2 — Compras, Entrada Fiscal e Vínculo do Estoque ao CNPJ**

Este módulo depende do **Módulo 1 — Cadastro Fiscal e Tributário** já concluído.

O fluxo desta fase é:

```text
COMPRA
   ↓
NOTA FISCAL DE ENTRADA
   ↓
CNPJ COMPRADOR
   ↓
PRODUTO COM CLASSIFICAÇÃO FISCAL
   ↓
TRIBUTOS / CRÉDITOS DA ENTRADA
   ↓
LOTE
   ↓
ESTOQUE VINCULADO AO CNPJ
```

> **IMPORTANTE:** esta fase NÃO deve alterar o funcionamento geral do módulo de compras.
>
> O objetivo é adicionar uma **camada fiscal e de rastreabilidade** sobre o processo já existente.

---

# 1. REGRAS DE IMPLEMENTAÇÃO

1. Não recriar o módulo de compras.
2. Não apagar tabelas existentes.
3. Não substituir o fluxo atual de compra.
4. Não alterar saldos históricos.
5. Não modificar transferências nesta fase.
6. Não alterar produção.
7. Não alterar perdas.
8. Não alterar consumo.
9. Não alterar extravio.
10. Não alterar PDV.
11. Adicionar somente a camada fiscal necessária.
12. Usar migrations novas e reversíveis.
13. Preservar todos os dados existentes.
14. Reutilizar estruturas já existentes sempre que possível.
15. Antes de alterar qualquer arquivo, mapear a arquitetura real do módulo atual.

---

# 2. OBJETIVO DO MÓDULO 2

Ao final deste módulo, toda entrada de compra deverá responder:

- Qual CNPJ realizou a compra?
- Qual fornecedor vendeu?
- Qual NF-e originou a entrada?
- Quais produtos entraram?
- Quais lotes foram criados ou atualizados?
- Quais tributos vieram destacados na entrada?
- Quais créditos fiscais foram classificados como potencialmente aproveitáveis?
- A qual CNPJ pertence fiscalmente o estoque gerado por esta compra?

---

# 3. FLUXO COMPLETO DESTA FASE

```text
FORNECEDOR
    ↓
COMPRA
    ↓
NOTA FISCAL DE ENTRADA
    ↓
EMPRESA / CNPJ COMPRADOR
    ↓
ITENS DA NOTA
    ↓
CLASSIFICAÇÃO FISCAL DO PRODUTO
    ↓
TRIBUTOS DA ENTRADA
    ↓
CRÉDITOS POTENCIAIS
    ↓
LOTE
    ↓
ENTRADA NO ESTOQUE
    ↓
ESTOQUE PERTENCENTE AO CNPJ
```

---

# 4. ANTES DE ALTERAR O PROJETO

O Cursor deve mapear:

- tabela de compras;
- itens de compra;
- notas fiscais;
- fornecedores;
- produtos;
- lotes;
- estoques;
- unidades;
- empresas/CNPJs;
- entradas de estoque;
- movimentações;
- custos;
- rotas;
- controllers;
- services;
- requests;
- views;
- permissões;
- relatórios.

Pesquisar por campos existentes como:

```text
empresa_id
unidade_id
cnpj
fornecedor_id
produto_id
lote_id
numero_nota
chave_nfe
cfop
ncm
cst
csosn
icms
pis
cofins
ipi
frete
desconto
valor_total
custo
```

Não duplicar estruturas existentes.

---

# 5. VÍNCULO DA COMPRA À EMPRESA / CNPJ

Toda compra deverá estar vinculada a:

`empresa_id`

E, quando aplicável, também a:

`unidade_id`

Relacionamentos esperados:

```text
Compra belongsTo Empresa
Compra belongsTo Unidade
```

A unidade deve pertencer à mesma empresa informada na compra.

Validar isso no backend.

---

# 6. INTERFACE DA COMPRA

No formulário atual de compra, adicionar:

## Empresa Compradora

Campos:

```text
Empresa / CNPJ
Unidade de entrada
Regime tributário
Inscrição estadual
```

Os dados fiscais devem vir da entidade Empresa criada no Módulo 1.

Não digitar CNPJ manualmente se a empresa já estiver cadastrada.

---

# 7. REGRA DE COERÊNCIA EMPRESA x UNIDADE

Ao selecionar uma empresa, o campo Unidade deve listar somente unidades pertencentes àquela empresa.

Não permitir:

```text
Compra vinculada à Empresa A
Unidade pertencente à Empresa B
```

---

# 8. NOTA FISCAL DE ENTRADA

Criar ou adaptar estrutura de Nota Fiscal de Entrada.

Se já existir, reutilizar.

Campos sugeridos:

```text
id
empresa_id
unidade_id
fornecedor_id
compra_id
modelo_documento
serie
numero
chave_acesso
data_emissao
data_entrada
valor_produtos
valor_frete
valor_seguro
valor_desconto
valor_outras_despesas
valor_total
status
observacoes
created_at
updated_at
```

Não duplicar se já houver tabela equivalente.

---

# 9. STATUS DA NOTA FISCAL

Sugestão:

```text
rascunho
importada
validada
processada
cancelada
```

A nota só deve gerar efeito em estoque quando estiver no estado apropriado conforme o fluxo atual.

---

# 10. ITENS DA NOTA FISCAL DE ENTRADA

Cada item deve ficar vinculado a:

```text
nota_fiscal_entrada_id
produto_id
lote_id nullable
```

Campos fiscais por item:

```text
ncm
cest
cfop
cst_icms
csosn
origem_mercadoria
quantidade
unidade_medida
valor_unitario
valor_produto
valor_desconto
valor_frete_rateado
valor_seguro_rateado
valor_outras_despesas_rateado
valor_total_item
```

Tributos:

```text
base_icms
aliquota_icms
valor_icms

base_icms_st
aliquota_icms_st
valor_icms_st

base_pis
aliquota_pis
valor_pis

base_cofins
aliquota_cofins
valor_cofins

base_ipi
aliquota_ipi
valor_ipi
```

Preparação futura:

```text
base_cbs
aliquota_cbs
valor_cbs

base_ibs
aliquota_ibs
valor_ibs
```

Campos CBS/IBS podem ficar nulos enquanto não forem utilizados.

---

# 11. NÃO RECALCULAR DOCUMENTO FISCAL SEM NECESSIDADE

Os valores fiscais da NF de entrada devem refletir os dados do documento.

Nesta fase o sistema deve principalmente:

- armazenar;
- validar;
- classificar;
- relacionar.

Não criar motor tributário definitivo.

---

# 12. HERANÇA DA CLASSIFICAÇÃO DO PRODUTO

Ao incluir um produto na compra, consultar o cadastro fiscal do produto.

Exemplo:

```text
Produto: Heineken 600ml
Tipo fiscal: Revenda
Perfil tributário: Cerveja
NCM: ...
CEST: ...
Monofásico: Sim/Não
ST: Sim/Não
```

Mas os dados recebidos na NF devem ser preservados como informação documental da entrada.

Manter separadamente:

1. classificação fiscal cadastral do produto;
2. informação fiscal efetivamente recebida na NF.

---

# 13. VALIDAÇÃO DE DIVERGÊNCIA FISCAL

Se houver divergência entre cadastro e NF, não atualizar automaticamente o produto.

Exemplo:

```text
NCM do cadastro: X
NCM da nota: Y
```

Gerar alerta:

**Divergência fiscal detectada**

Registrar também divergências de:

- CEST;
- CST;
- CSOSN;
- CFOP;
- origem da mercadoria.

---

# 14. TRIBUTOS DA ENTRADA

O sistema deve conseguir registrar e consolidar por nota:

```text
ICMS
ICMS-ST
PIS
COFINS
IPI
CBS
IBS
Outros
```

---

# 15. CRÉDITOS FISCAIS DA ENTRADA

Criar conceito de:

# **Crédito Fiscal Potencial**

Não considerar automaticamente que todo imposto destacado é crédito aproveitável.

Campos sugeridos:

```text
credito_icms_potencial
credito_pis_potencial
credito_cofins_potencial
credito_ipi_potencial
credito_cbs_potencial
credito_ibs_potencial
```

Status:

```text
nao_analisado
potencial
nao_aproveitavel
aproveitavel
aproveitado
estornado
```

Nesta fase priorizar:

```text
nao_analisado
potencial
nao_aproveitavel
```

---

# 16. REGRA DE CRÉDITO

A análise futura poderá considerar:

- regime tributário;
- tipo do produto;
- natureza fiscal;
- operação;
- legislação;
- documento fiscal;
- uso do produto.

Nesta fase:

- registrar;
- marcar como potencial;
- permitir revisão;
- não efetivar apuração definitiva.

---

# 17. RASTREABILIDADE DO CRÉDITO

Cada crédito deve ser rastreável até:

```text
Empresa/CNPJ
Nota fiscal
Item da nota
Produto
Lote
```

---

# 18. LOTE

A entrada deve criar ou atualizar lote conforme regra atual.

O lote deve permitir rastrear:

```text
empresa_id
unidade_id
produto_id
nota_fiscal_entrada_id
item_nota_entrada_id
fornecedor_id
```

Além dos campos atuais:

```text
quantidade
custo_unitario
data_entrada
validade
```

Não recriar estrutura de lote se já existir.

---

# 19. VÍNCULO FISCAL DO ESTOQUE

Este é um dos objetivos centrais do módulo.

O estoque deverá permitir saber:

```text
Este saldo pertence fiscalmente a qual empresa/CNPJ?
```

Estrutura conceitual:

```text
Estoque
    empresa_id
    unidade_id
    produto_id
    lote_id
```

Antes de adicionar `empresa_id` diretamente em tabelas, verificar se a relação Unidade → Empresa já garante rastreabilidade suficiente.

---

# 20. NÃO DUPLICAR SALDO

Se hoje o estoque é calculado por:

```text
produto + unidade
```

evoluir com cuidado.

Não criar novo saldo paralelo que duplique quantidades existentes.

A propriedade fiscal deve ser adicionada à rastreabilidade, não criar um segundo estoque.

---

# 21. REGRA DE PROPRIEDADE FISCAL DO ESTOQUE

A compra define a origem fiscal do saldo:

```text
Compra realizada pelo CNPJ A
        ↓
Entrada na Unidade A1
        ↓
Lote pertence ao CNPJ A
        ↓
Estoque fiscal pertence ao CNPJ A
```

Esse vínculo será utilizado depois em:

- transferências;
- operações entre CNPJs;
- produção;
- perda;
- extravio;
- venda.

---

# 22. CUSTO DO PRODUTO

Manter o método atual de custo:

- custo médio;
- FIFO;
- outro método existente.

Não mudar o método nesta fase.

Garantir somente que o custo seja rastreável até:

```text
compra
nota
item
lote
```

---

# 23. RATEIO DE DESPESAS

Se o módulo atual já possui rateio, manter.

Caso exista estrutura adequada, considerar:

```text
frete
seguro
outras despesas
desconto
```

Não criar complexidade desnecessária nesta fase.

---

# 24. EVENTO DE ENTRADA

Ao concluir a compra, gerar ou complementar movimentação:

```text
ENTRADA_COMPRA
```

Referências:

```text
empresa_id
unidade_id
produto_id
lote_id
compra_id
nota_fiscal_entrada_id
item_nota_entrada_id
quantidade
custo
data
```

---

# 25. NÃO MISTURAR COMPRA COM OUTRAS ENTRADAS

Entrada por compra deve ser distinguível de:

```text
entrada por transferência
entrada por operação entre CNPJs
entrada por devolução
entrada por ajuste
entrada por produção
```

Nesta fase implementar efetivamente somente:

```text
compra
```

---

# 26. AUDITORIA DA ENTRADA

Registrar, conforme arquitetura atual:

- usuário;
- data/hora;
- empresa;
- unidade;
- fornecedor;
- NF;
- produto;
- quantidade;
- lote;
- valores;
- tributos;
- alterações relevantes.

---

# 27. DOCUMENTOS ANEXOS

Se já existir suporte, vincular:

- XML da NF-e;
- DANFE/PDF;
- comprovantes.

Não criar um grande módulo documental fora do escopo.

---

# 28. IMPORTAÇÃO DE XML

Se já existe importação XML, adaptar para preencher os novos campos.

Se ainda não existe:

- manter entrada manual funcionando;
- preparar arquitetura para importação futura;
- não ampliar escopo sem necessidade.

---

# 29. ALERTAS

Criar alertas como:

```text
Produto sem classificação fiscal
Produto com cadastro fiscal incompleto
NCM divergente
CEST divergente
CST/CSOSN divergente
CFOP não informado
Unidade incompatível com CNPJ comprador
NF duplicada
```

---

# 30. POLÍTICA DE BLOQUEIO

Produtos antigos podem estar com fiscal incompleto.

Não bloquear todo o processo por isso.

Preferir:

```text
aviso + pendência fiscal
```

Bloquear somente inconsistências críticas, como:

```text
empresa e unidade incompatíveis
NF duplicada quando não permitida
```

---

# 31. STATUS FISCAL DA ENTRADA

Criar indicador:

```text
pendente
com_alerta
validada
processada
```

Adaptar ao fluxo existente.

---

# 32. PAINEL DA COMPRA

Na visualização da compra, incluir bloco:

# Dados Fiscais

Mostrar:

```text
Empresa/CNPJ
Unidade
Número NF
Chave NF-e
Fornecedor
Total dos produtos
Total de tributos destacados
Créditos potenciais
Status fiscal
```

---

# 33. RESUMO TRIBUTÁRIO DA NOTA

Criar visual simples:

```text
ICMS: R$ ...
ICMS-ST: R$ ...
PIS: R$ ...
COFINS: R$ ...
IPI: R$ ...
CBS: R$ ...
IBS: R$ ...
```

Separar claramente:

```text
Tributo destacado
```

de:

```text
Crédito potencial
```

---

# 34. LISTAGEM DE COMPRAS

Adicionar, sem poluir:

```text
Empresa
CNPJ
Unidade
NF
Fornecedor
Valor
Status fiscal
```

Filtros:

```text
Por CNPJ
Por unidade
Por fornecedor
Por status fiscal
Por período
```

---

# 35. RELATÓRIO DE ENTRADAS FISCAIS

Filtros:

```text
Período
Empresa/CNPJ
Unidade
Fornecedor
Produto
NCM
Tipo fiscal
Perfil tributário
```

Colunas:

```text
Data
NF
Fornecedor
Produto
Quantidade
Valor
ICMS
PIS
COFINS
IPI
Crédito potencial
Lote
CNPJ
```

---

# 36. RELATÓRIO DE CRÉDITOS POTENCIAIS

Preparar relatório gerencial:

```text
Empresa/CNPJ
Produto
Nota
Tributo
Valor destacado
Valor potencial
Status
```

Não tratar esse relatório como apuração final.

---

# 37. BANCO DE DADOS

Possíveis migrations:

```text
add_empresa_id_to_compras_table
create_notas_fiscais_entrada_table
create_itens_notas_fiscais_entrada_table
create_creditos_fiscais_entrada_table
add_fiscal_links_to_lotes_table
```

Ajustar à arquitetura existente.

Não duplicar tabelas.

---

# 38. MODELS

Possíveis Models:

```text
NotaFiscalEntrada
ItemNotaFiscalEntrada
CreditoFiscalEntrada
```

Relacionamentos esperados:

```text
NotaFiscalEntrada
    belongsTo Empresa
    belongsTo Unidade
    belongsTo Fornecedor
    belongsTo Compra
    hasMany ItensNotaFiscalEntrada
    hasMany CreditosFiscaisEntrada

ItemNotaFiscalEntrada
    belongsTo NotaFiscalEntrada
    belongsTo Produto
    belongsTo Lote
```

---

# 39. CRÉDITO FISCAL — ESTRUTURA

Possível estrutura:

```text
empresa_id
nota_fiscal_entrada_id
item_nota_fiscal_entrada_id
produto_id
lote_id
tipo_tributo
valor_destacado
valor_potencial
status
observacao
```

---

# 40. SERVICES

Se o projeto usa Services, separar responsabilidades:

```text
CompraFiscalService
NotaFiscalEntradaService
CreditoFiscalEntradaService
EntradaEstoqueFiscalService
```

Evitar service monolítico.

---

# 41. TRANSAÇÕES DE BANCO

Quando o processamento envolver:

- compra;
- NF;
- itens;
- lote;
- estoque;
- créditos;

usar transação de banco.

Se uma etapa crítica falhar, não deixar processamento parcial.

---

# 42. IDEMPOTÊNCIA

Evitar duplicidade de NF-e.

Quando aplicável, usar:

```text
chave_acesso
```

como identificador fiscal único por empresa.

---

# 43. REGRA DE DUPLICIDADE

Não permitir processar duas vezes a mesma NF-e para o mesmo CNPJ.

Mensagem:

```text
Nota fiscal já cadastrada ou processada para este CNPJ.
```

---

# 44. CANCELAMENTO

Se o fluxo atual permite cancelamento de compra, adaptar para preservar:

- saldo correto;
- lote;
- vínculo fiscal;
- status de crédito potencial.

Não implementar estorno tributário definitivo nesta fase.

---

# 45. PERMISSÕES

Se o SAS usa permissões:

```text
compra_fiscal.visualizar
compra_fiscal.editar
nota_entrada.visualizar
nota_entrada.editar
credito_entrada.visualizar
credito_entrada.revisar
```

Reutilizar padrão atual.

---

# 46. TESTES DE COMPRA

Testar:

- criar compra vinculada à empresa;
- selecionar unidade válida;
- impedir unidade de outra empresa;
- compra antiga continua funcionando;
- NF pode ser vinculada;
- item pode ser vinculado a produto;
- lote fica rastreável;
- estoque recebe rastreabilidade fiscal;
- saldo não é duplicado.

---

# 47. TESTES DE NOTA

Testar:

- chave duplicada;
- valores;
- fornecedor;
- empresa;
- unidade;
- itens;
- tributos;
- status fiscal;
- divergência de NCM;
- divergência de CEST;
- divergência de CST/CSOSN.

---

# 48. TESTES DE CRÉDITOS

Testar:

- crédito potencial é criado;
- vínculo com item;
- vínculo com produto;
- vínculo com CNPJ;
- vínculo com lote;
- status;
- não marcar automaticamente como aproveitado.

---

# 49. TESTES DE REGRESSÃO

Confirmar:

```text
Produtos: OK
Empresas: OK
Unidades: OK
Compras: OK
Estoque: OK
Lotes: OK
Transferências: OK
PDV: OK
Login/permissões: OK
```

---

# 50. NÃO ALTERAR TRANSFERÊNCIAS

Mesmo com estoque vinculado ao CNPJ, não alterar ainda:

```text
Transferência entre unidades
```

Isso será tratado no módulo de movimentações.

---

# 51. NÃO ALTERAR PRODUÇÃO

Não consumir insumos automaticamente nesta fase.

---

# 52. NÃO ALTERAR PERDAS / AVARIAS / VENCIMENTOS

Não gerar estorno de crédito ainda.

---

# 53. NÃO ALTERAR EXTRAVIO / FURTO

Não gerar efeito fiscal definitivo ainda.

---

# 54. NÃO ALTERAR CONSUMO INTERNO

Não gerar tratamento tributário ainda.

---

# 55. NÃO ALTERAR VENDA / PDV

Não bloquear venda por CNPJ ainda.

A estrutura deve somente deixar o estoque preparado para isso.

---

# 56. DOCUMENTAÇÃO

Criar:

`docs/modulo-fiscal-02-compras-entrada.md`

Documentar:

- migrations;
- tabelas;
- models;
- relacionamentos;
- services;
- controllers;
- rotas;
- telas;
- regras;
- testes;
- alertas;
- pendências.

---

# 57. RELATÓRIO FINAL DO CURSOR

Formato:

```text
MÓDULO 2 — COMPRAS / ENTRADA FISCAL

[OK] Compra vinculada ao CNPJ
[OK] Unidade validada
[OK] Nota Fiscal de Entrada
[OK] Itens fiscais
[OK] Tributos destacados
[OK] Créditos potenciais
[OK] Lotes vinculados
[OK] Estoque rastreável por CNPJ
[OK] Alertas fiscais
[OK] Testes

Arquivos criados:
...

Arquivos alterados:
...

Migrations:
...

Testes executados:
...

Resultado:
...

Pendências:
...
```

---

# 58. ORDEM EXATA DE IMPLEMENTAÇÃO

1. Analisar a estrutura atual.
2. Mapear compra, NF, estoque e lote.
3. Verificar relações Empresa/Unidade do Módulo 1.
4. Criar migrations incrementais.
5. Vincular compra à Empresa/CNPJ.
6. Estruturar Nota Fiscal de Entrada.
7. Estruturar itens fiscais da NF.
8. Registrar tributos destacados.
9. Criar créditos fiscais potenciais.
10. Vincular lote à origem fiscal.
11. Garantir rastreabilidade do estoque por CNPJ.
12. Criar alertas e status fiscal.
13. Adaptar interface.
14. Criar relatórios.
15. Executar testes.
16. Documentar.

---

# 59. CRITÉRIOS DE ACEITE

O Módulo 2 estará concluído quando:

- compra estiver vinculada à Empresa/CNPJ;
- unidade estiver coerente com a empresa;
- NF de entrada puder ser registrada;
- itens da NF estiverem vinculados aos produtos;
- tributos destacados forem armazenados;
- créditos potenciais forem classificados;
- lote estiver vinculado à origem fiscal;
- estoque puder ser rastreado até o CNPJ comprador;
- compra antiga continuar funcionando;
- estoque não for duplicado;
- transferências não forem alteradas;
- PDV não for alterado;
- migrations forem reversíveis;
- testes passarem.

---

# 60. RESULTADO ESPERADO

Ao final desta fase, o SAS deverá conseguir responder:

```text
Este produto entrou por qual compra?
Qual NF originou o lote?
Qual fornecedor vendeu?
Qual CNPJ comprou?
Qual empresa é dona fiscal desse estoque?
Quais tributos vieram destacados?
Existe crédito fiscal potencial?
Qual lote recebeu essa entrada?
```

Ainda NÃO deverá decidir:

```text
Como transferir para outro CNPJ?
Qual imposto será estornado em uma perda?
Quanto imposto a venda gerará?
Qual CNPJ é melhor para vender?
```

---

# 61. PRÓXIMA FASE

Após concluir este módulo:

# **MÓDULO 3 — MOVIMENTAÇÕES DE ESTOQUE E DESTINO FISCAL**

Responsável por:

```text
Transferência interna
Operação entre CNPJs
Produção
Consumo interno
Perda
Avaria
Vencimento
Extravio/Furto
```

Não iniciar o Módulo 3 antes de validar o Módulo 2.

---

# COMANDO FINAL PARA O CURSOR

Implemente exclusivamente o **Módulo 2 — Compras, Entrada Fiscal e Vínculo do Estoque ao CNPJ**.

Não recrie o módulo de compras.

Adicione uma camada fiscal sobre a estrutura atual.

Toda compra deve ficar vinculada à Empresa/CNPJ comprador.

Toda nota fiscal de entrada deve ficar vinculada à compra, fornecedor, empresa e unidade.

Cada item deve manter seus dados fiscais documentais.

Os tributos destacados devem ser armazenados separadamente dos créditos potenciais.

Todo lote gerado por compra deve permitir rastrear a origem até o CNPJ comprador.

O estoque resultante da compra deve permitir identificar sua propriedade fiscal.

Não alterar transferências, produção, perdas, extravio, consumo ou PDV nesta fase.

Use migrations incrementais e reversíveis.

Preserve todos os dados existentes.

Ao finalizar, execute testes de regressão e entregue relatório técnico completo antes de seguir para o próximo módulo.
