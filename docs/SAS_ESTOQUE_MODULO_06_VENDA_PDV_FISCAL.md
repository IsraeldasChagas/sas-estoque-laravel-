# SAS-ESTOQUE — MÓDULO 6: VENDA / PDV, BAIXA FISCAL E TRIBUTOS DE SAÍDA

## Instrução para o Cursor IA

Você está trabalhando no **SAS-Estoque**, sistema já existente e em produção.

Implementar exclusivamente:

# MÓDULO 6 — VENDA / PDV E EVENTO TRIBUTÁRIO DE SAÍDA

Este módulo depende da estrutura criada nos módulos anteriores:

- Módulo 1 — Cadastro Fiscal e Tributário;
- Módulo 2 — Compras / Entrada Fiscal / Estoque por CNPJ;
- Módulo 3 — Movimentações de Estoque e Eventos Fiscais;
- Módulo 5 — Produção / Ficha Técnica / Custo.

O fluxo principal desta fase será:

```text
ESTOQUE FISCAL DO CNPJ
        ↓
PDV DO MESMO CNPJ
        ↓
VENDA
        ↓
VALIDAÇÃO DO ESTOQUE
        ↓
BAIXA DO PRODUTO / LOTE
        ↓
DOCUMENTO FISCAL
        ↓
RECEITA DA VENDA
        ↓
TRIBUTOS DA SAÍDA
        ↓
EVENTO FISCAL
        ↓
PAINEL FISCAL
```

A regra central é:

```text
PDV do CNPJ B
somente pode vender
estoque fiscal pertencente ao CNPJ B.
```

Isso deve impedir inconsistências como:

```text
CNPJ C comprou a mercadoria
mas não possui saída.

CNPJ B não comprou/não recebeu a mercadoria
mas registra a venda.
```

Se o estoque pertence ao CNPJ C e precisa ser vendido pelo CNPJ B, antes da venda deve existir uma operação válida C → B registrada pelos módulos de movimentação/operação entre empresas.

---

# 1. REGRA PRINCIPAL DE IMPLEMENTAÇÃO

Antes de programar:

1. Mapear o PDV atual.
2. Mapear tabela de vendas.
3. Mapear itens de venda.
4. Mapear baixa atual de estoque.
5. Mapear documentos fiscais já existentes.
6. Mapear integrações fiscais existentes.
7. Mapear empresa/CNPJ associado ao PDV/unidade.
8. Mapear estoque e lotes.
9. Não recriar o PDV.
10. Não criar segundo fluxo de venda.
11. Não duplicar baixa de estoque.
12. Não apagar vendas históricas.
13. Não alterar compras.
14. Não alterar produção.
15. Não alterar transferências.
16. Criar camada fiscal sobre a venda atual.
17. Utilizar transactions.
18. Criar migrations incrementais e reversíveis.
19. Preservar dados existentes.

---

# 2. OBJETIVO

Ao final deste módulo, toda venda deverá responder:

```text
Qual CNPJ realizou a venda?
```

```text
Qual unidade/PDV realizou a venda?
```

```text
De qual estoque saiu o produto?
```

```text
O estoque pertence ao mesmo CNPJ vendedor?
```

```text
Qual lote foi consumido?
```

```text
Qual documento fiscal está vinculado?
```

```text
Qual foi a receita?
```

```text
Quais tributos foram classificados/registrados na saída?
```

```text
Qual evento fiscal foi criado?
```

---

# 3. IDENTIDADE FISCAL DO PDV

Todo PDV/unidade de venda deve estar vinculado a:

```text
empresa_id
unidade_id
```

O CNPJ deve vir da entidade Empresa.

Não permitir que operador escolha livremente um CNPJ diferente durante cada venda.

O PDV deve possuir uma identidade fiscal definida pela configuração da unidade.

---

# 4. REGRA DE PROPRIEDADE DO ESTOQUE

Antes de adicionar/confirmar item na venda, validar:

```text
empresa_estoque_id == empresa_pdv_id
```

Se verdadeiro:

```text
VENDA PERMITIDA
```

Se falso:

```text
VENDA BLOQUEADA
```

---

# 5. MENSAGEM DE BLOQUEIO

Exemplo:

```text
Venda não permitida.

Este estoque pertence ao CNPJ:
XX.XXX.XXX/XXXX-XX

O PDV atual pertence ao CNPJ:
YY.YYY.YYY/YYYY-YY

Realize primeiro a operação de movimentação entre as empresas.
```

Não permitir que usuário comum ignore essa trava.

---

# 6. NÃO CONFUNDIR UNIDADE COM CNPJ

Duas unidades podem pertencer à mesma empresa.

Exemplo:

```text
Unidade 1 → CNPJ A
Unidade 2 → CNPJ A
```

Nesse caso a análise fiscal deve considerar a mesma pessoa jurídica, observando a disponibilidade real do estoque por unidade e o fluxo de transferência interna quando necessário.

Outro cenário:

```text
Unidade 2 → CNPJ A
Unidade 3 → CNPJ B
```

Aqui são empresas diferentes.

Não permitir venda cruzada de estoque sem operação A → B.

---

# 7. EXEMPLO DO PROBLEMA QUE DEVE SER IMPEDIDO

Situação incorreta:

```text
Fornecedor
    ↓
Compra pelo CNPJ C
    ↓
Estoque C
```

Depois:

```text
PDV B
    ↓
Venda da mercadoria de C
```

Resultado inconsistente:

```text
C comprou e aparentemente não deu saída.
B vendeu sem possuir entrada correspondente.
```

O sistema deve bloquear isso.

---

# 8. FLUXO CORRETO ENTRE CNPJs

Quando empresas forem diferentes:

```text
Fornecedor
    ↓
CNPJ C compra
    ↓
Estoque C
    ↓
Operação documentada C → B
    ↓
Baixa C
    ↓
Entrada B
    ↓
Estoque fiscal B
    ↓
PDV B
    ↓
Venda
```

---

# 9. VÍNCULO DA VENDA À EMPRESA

Toda venda deve possuir:

```text
empresa_id
unidade_id
pdv_id nullable
```

Esses dados devem ser determinados pelo contexto do PDV.

---

# 10. ITENS DA VENDA

Cada item deve permitir rastrear:

```text
venda_id
produto_id
lote_id
empresa_id
unidade_id
quantidade
preco_unitario
desconto
valor_total
custo_unitario
custo_total
```

Não duplicar campos existentes.

---

# 11. BAIXA DO ESTOQUE

A baixa deve utilizar o mecanismo central criado anteriormente.

Não fazer baixa manual diretamente no controller.

Fluxo:

```text
Venda
   ↓
MovimentacaoEstoqueService
   ↓
Baixa
   ↓
Produto/Lote
   ↓
CNPJ correto
```

---

# 12. LOTE

Quando o produto utiliza lote, registrar exatamente qual lote foi consumido.

Preservar:

```text
Compra
→ NF entrada
→ Lote
→ Estoque
→ Venda
```

Para produto produzido:

```text
Insumos
→ Produção
→ Lote do produto final
→ Venda
```

---

# 13. SELEÇÃO DE LOTE

Respeitar política existente:

- FIFO;
- FEFO;
- seleção manual autorizada;
- outra regra já implementada.

Não substituir a política atual sem necessidade.

---

# 14. SALDO

Antes da venda:

```text
quantidade_venda <= saldo_disponivel_do_CNPJ_e_unidade
```

Validar dentro da transaction.

Não permitir saldo negativo indevido.

---

# 15. TRANSAÇÃO DE BANCO

Finalização da venda deve envolver transaction para:

```text
validar CNPJ
validar saldo
registrar venda
registrar itens
baixar estoque
vincular lotes
registrar documento fiscal
registrar evento fiscal
registrar tributos
```

Se etapa crítica falhar:

```text
ROLLBACK
```

---

# 16. DOCUMENTO FISCAL

A venda deve possuir vínculo com o documento fiscal aplicável ao fluxo já utilizado pelo SAS.

Preparar/usar:

```text
documento_fiscal_id
tipo_documento
numero_documento
serie
chave_acesso
status_documento
data_emissao
```

Não recriar emissor fiscal se já existir integração.

---

# 17. STATUS DO DOCUMENTO

Sugestão:

```text
pendente
processando
autorizado
rejeitado
cancelado
contingencia
```

Adaptar ao provedor/integrador já utilizado.

---

# 18. VENDA x DOCUMENTO FISCAL

Manter relação clara:

```text
Venda
    hasOne/hasMany DocumentoFiscal
```

conforme arquitetura real.

Nunca perder vínculo entre venda e documento.

---

# 19. RECEITA

Toda venda finalizada deve registrar a receita no CNPJ vendedor.

Estrutura:

```text
empresa_id
venda_id
data
valor_bruto
descontos
valor_liquido
```

Reutilizar financeiro existente quando apropriado.

Não criar segundo financeiro.

---

# 20. CLASSIFICAÇÃO FISCAL DO ITEM VENDIDO

Na venda, consultar:

```text
Produto
Tipo fiscal
Perfil tributário
NCM
CEST
Origem
CST/CSOSN
Monofásico
ST
```

A regra de saída também deve considerar:

```text
Empresa/CNPJ vendedor
Regime tributário
Natureza da operação
Documento fiscal
```

---

# 21. SNAPSHOT FISCAL DA VENDA

Não depender apenas do cadastro atual do produto para consultar vendas antigas.

Guardar no item/documento um snapshot dos dados fiscais utilizados na venda.

Exemplo:

```text
ncm
cest
cfop
cst_icms
csosn
origem_mercadoria
perfil_tributario_id
```

Assim, se o cadastro mudar futuramente, a venda histórica permanece fiel ao que foi utilizado.

---

# 22. TRIBUTOS DA SAÍDA

Criar/adaptar estrutura para registrar os tributos associados ao item/documento de saída.

Preparar para:

```text
ICMS
ICMS-ST
PIS
COFINS
IPI
CBS
IBS
outros
```

Não inventar alíquotas.

Utilizar regras/configurações fiscais válidas do sistema.

---

# 23. TRIBUTO REALIZADO

Diferenciar:

```text
tributação potencial do estoque
```

de:

```text
tributo realizado pela venda
```

Quando a venda ocorrer, o evento deixa de ser apenas uma projeção gerencial e passa a representar uma operação efetivamente realizada, respeitando o documento fiscal e as regras aplicáveis.

---

# 24. ESTRUTURA DE TRIBUTOS DA VENDA

Possível tabela:

`tributos_venda`

Campos:

```text
id
empresa_id
venda_id
venda_item_id nullable
documento_fiscal_id nullable
tipo_tributo
base_calculo
aliquota nullable
valor
status
created_at
updated_at
```

Não criar se já existir estrutura equivalente.

---

# 25. STATUS DO TRIBUTO

Sugestão:

```text
calculado
documentado
validado
apurado
cancelado
```

Nesta fase, o foco é:

```text
calculado/documentado
```

A apuração mensal consolidada poderá ser aprofundada no módulo fiscal.

---

# 26. EVENTO FISCAL DE VENDA

Ao finalizar uma venda, criar:

```text
tipo_evento = venda
```

Vincular:

```text
empresa_id
unidade_id
venda_id
documento_fiscal_id
produto_id / itens
valor_base
valor_tributos
data_evento
status
```

---

# 27. EVENTO DE ESTOQUE x EVENTO FISCAL

Manter separados:

```text
Evento de Estoque
= saída física/quantitativa
```

```text
Evento Fiscal
= venda/operação tributária
```

Relacionar ambos à venda.

---

# 28. PRODUTO DE REVENDA

Fluxo:

```text
Compra
→ NF Entrada
→ Lote
→ Estoque CNPJ
→ PDV mesmo CNPJ
→ Venda
→ Documento fiscal
→ Tributo da saída
```

---

# 29. PRODUTO DE PRODUÇÃO PRÓPRIA

Fluxo:

```text
Compra dos insumos
→ Estoque dos insumos
→ Produção
→ Produto final
→ Estoque do produto produzido
→ PDV
→ Venda
→ Documento fiscal
→ Tributo da saída
```

A venda deve preservar vínculo com a origem produtiva quando disponível.

---

# 30. PRODUTO MONOFÁSICO / ST / OUTROS TRATAMENTOS

Não aplicar uma regra genérica única.

A classificação do produto deve orientar o tratamento correto.

O sistema deve ser preparado para distinguir tratamentos fiscais específicos sem inventar tributação.

---

# 31. DEVOLUÇÃO

Preparar arquitetura para vincular futura devolução à venda original.

Não implementar módulo completo de devoluções se estiver fora do escopo atual.

---

# 32. CANCELAMENTO DA VENDA

Venda processada não deve ser apagada.

Fluxo:

```text
Venda
   ↓
Cancelamento
   ↓
Documento fiscal cancelado quando aplicável
   ↓
Estorno controlado da baixa
   ↓
Cancelamento dos eventos
```

Manter auditoria.

---

# 33. NÃO DUPLICAR ESTOQUE NO CANCELAMENTO

Ao cancelar, restaurar quantidade somente uma vez.

Garantir idempotência.

---

# 34. CONCORRÊNCIA

Durante a venda:

- validar saldo dentro da transaction;
- utilizar lock apropriado quando necessário;
- evitar venda simultânea do mesmo saldo.

---

# 35. PDVs MULTIEMPRESA

O sistema pode possuir vários PDVs:

```text
PDV Unidade A → Empresa A
PDV Unidade B → Empresa B
PDV Unidade C → Empresa C
```

Cada sessão/terminal deve conhecer sua empresa/unidade.

---

# 36. TROCA DE EMPRESA

Se administrador puder operar múltiplas empresas, a troca deve ser explícita e auditada.

Nunca trocar silenciosamente o CNPJ de uma venda já iniciada.

---

# 37. CARRINHO

Ao iniciar venda:

```text
empresa_id
unidade_id
```

devem ficar fixados.

Itens adicionados devem pertencer ao estoque autorizado para esse contexto.

---

# 38. ALERTA NO CARRINHO

Se produto existir no cadastro, mas não houver saldo no CNPJ atual:

```text
Produto sem estoque disponível para este CNPJ.
```

Não buscar automaticamente estoque de outro CNPJ.

---

# 39. VISIBILIDADE DE ESTOQUE

O operador pode, se permitido, visualizar:

```text
Estoque nesta unidade
```

Administrador pode visualizar:

```text
Estoque em outras unidades/CNPJs
```

Mas visualizar não significa poder vender.

---

# 40. PAINEL DA VENDA

Após finalizar mostrar:

```text
Venda
CNPJ
Unidade
Documento fiscal
Valor bruto
Descontos
Valor líquido
Custo
Tributos registrados
Status fiscal
```

---

# 41. PAINEL FISCAL

Alimentar painel com:

```text
Vendas do período
Receita bruta
Receita líquida
Tributos de saída registrados
Produtos vendidos
Custo das mercadorias/produtos
Eventos fiscais
Documentos pendentes
Documentos rejeitados
```

Filtros:

```text
Período
Empresa/CNPJ
Unidade
Produto
Tipo fiscal
Perfil tributário
```

---

# 42. PAINEL POR CNPJ

Permitir:

```text
CNPJ A
CNPJ B
CNPJ C
Consolidado gerencial
```

O consolidado não deve misturar contabilmente as obrigações das empresas.

Deve ser apenas visão gerencial.

---

# 43. INDICADOR DE INCONSISTÊNCIA

Criar relatório/alerta para detectar:

```text
Venda sem estoque de origem
Venda sem lote quando obrigatório
Venda sem empresa
Venda sem documento fiscal quando exigido
Estoque de CNPJ diferente
Documento rejeitado
Evento fiscal ausente
```

---

# 44. RELATÓRIO "VENDA SEM ORIGEM"

Esse relatório é importante para eliminar o problema:

```text
B vendeu sem comprar/receber.
```

O sistema deve procurar vendas sem cadeia de origem válida:

```text
Venda
→ Estoque
→ Lote
→ Entrada/Produção/Operação entre empresas
```

---

# 45. RELATÓRIO DE VENDAS POR CNPJ

Mostrar:

```text
Data
Venda
CNPJ
Unidade
Produto
Quantidade
Receita
Custo
Documento
Tributos
Status
```

---

# 46. RELATÓRIO DE TRIBUTOS DE SAÍDA

Mostrar:

```text
CNPJ
Período
Tributo
Base
Valor
Documento
Venda
Status
```

---

# 47. RELATÓRIO DE MARGEM

Preparar visão:

```text
Receita
- Custo
- Tributos registrados
= Margem gerencial
```

Não substituir DRE contábil.

---

# 48. RASTREABILIDADE COMPLETA — REVENDA

O sistema deve navegar:

```text
Venda
↓
Item
↓
Lote
↓
Compra
↓
NF Entrada
↓
Fornecedor
```

---

# 49. RASTREABILIDADE COMPLETA — PRODUÇÃO

```text
Venda
↓
Produto produzido
↓
Produção
↓
Insumos
↓
Lotes
↓
Compras
↓
NF Entrada
↓
Fornecedores
```

---

# 50. POSSÍVEIS MIGRATIONS

Somente se necessário:

```text
add_empresa_unidade_to_vendas
add_fiscal_snapshot_to_venda_itens
add_fiscal_document_to_vendas
create_tributos_venda_table
add_venda_reference_to_eventos_fiscais
```

Adaptar à arquitetura real.

---

# 51. SERVICES

Reutilizar serviços existentes.

Possíveis:

```text
VendaService
VendaFiscalService
TributoVendaService
DocumentoFiscalVendaService
```

Obrigatoriamente reutilizar o mecanismo central de:

```text
MovimentacaoEstoqueService
EventoFiscalService
```

quando existirem.

---

# 52. VALIDADOR DE ESTOQUE FISCAL

Criar responsabilidade clara, por exemplo:

```text
EstoqueFiscalValidator
```

Função conceitual:

```text
validarVenda(
    empresa,
    unidade,
    produto,
    lote,
    quantidade
)
```

Validar:

- propriedade fiscal;
- unidade;
- saldo;
- lote;
- status do estoque.

---

# 53. SEGURANÇA

A trava por CNPJ deve existir no backend.

Não basta esconder botão no frontend.

Mesmo chamada direta à API deve ser rejeitada se tentar vender estoque de outro CNPJ.

---

# 54. LOG DE TENTATIVA BLOQUEADA

Registrar tentativas de:

```text
PDV B tentando vender estoque C
```

Guardar:

```text
usuário
data
empresa PDV
empresa estoque
produto
quantidade
```

Isso ajuda na auditoria.

---

# 55. PERMISSÕES

Se aplicável:

```text
pdv.vender
pdv.cancelar_venda
venda.visualizar
venda_fiscal.visualizar
documento_fiscal.visualizar
tributos_venda.visualizar
painel_fiscal.visualizar
```

Não criar permissão para "ignorar CNPJ" em venda comum.

---

# 56. TESTES — CNPJ CORRETO

Cenário:

```text
PDV B
Estoque B
```

Resultado:

```text
Venda permitida.
```

---

# 57. TESTES — CNPJ INCORRETO

Cenário:

```text
PDV B
Estoque C
```

Resultado:

```text
Venda bloqueada.
```

Nenhuma baixa deve ocorrer.

---

# 58. TESTE APÓS OPERAÇÃO C → B

Cenário:

```text
Estoque originalmente C
↓
Operação válida C → B
↓
Entrada B
↓
PDV B
```

Resultado:

```text
Venda permitida.
```

---

# 59. TESTES DE VENDA

Testar:

- saldo suficiente;
- saldo insuficiente;
- lote correto;
- lote de outro CNPJ;
- empresa correta;
- unidade correta;
- baixa;
- documento fiscal;
- tributos;
- evento fiscal;
- cancelamento;
- rollback.

---

# 60. TESTE DE RASTREABILIDADE

Revenda:

```text
Venda → Lote → Compra → NF → Fornecedor
```

Produção:

```text
Venda → Produção → Insumos → Lotes → NF → Fornecedor
```

---

# 61. TESTES DE REGRESSÃO

Confirmar:

```text
Produtos: OK
Fiscal produto: OK
Empresas: OK
Unidades: OK
Compras: OK
NF Entrada: OK
Estoque: OK
Lotes: OK
Movimentações: OK
Transferências: OK
Produção: OK
Eventos fiscais: OK
PDV: OK
Financeiro existente: OK
```

---

# 62. NÃO IMPLEMENTAR PLANEJAMENTO TRIBUTÁRIO AINDA

Não fazer automaticamente:

```text
"Venda por B porque paga menos imposto."
```

O PDV executa operações reais.

Planejamento tributário será um módulo separado de simulação e análise.

---

# 63. NÃO REDIRECIONAR VENDA AUTOMATICAMENTE

Se B não possui estoque:

não enviar a venda automaticamente para C.

Se C possui estoque:

não alterar automaticamente o CNPJ emissor.

Mostrar a inconsistência e exigir regularização operacional.

---

# 64. DOCUMENTAÇÃO

Criar:

`docs/modulo-fiscal-06-venda-pdv.md`

Documentar:

- migrations;
- models;
- services;
- controllers;
- validações;
- regras de CNPJ;
- estoque;
- lotes;
- documento fiscal;
- tributos;
- eventos;
- painel;
- relatórios;
- testes.

---

# 65. RELATÓRIO FINAL DO CURSOR

```text
MÓDULO 6 — VENDA / PDV

[OK] Identidade fiscal do PDV
[OK] Validação CNPJ x estoque
[OK] Trava de venda cruzada
[OK] Venda
[OK] Baixa
[OK] Lote
[OK] Documento fiscal
[OK] Receita
[OK] Tributos da saída
[OK] Evento fiscal
[OK] Painel
[OK] Rastreabilidade
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

# 66. ORDEM EXATA DE IMPLEMENTAÇÃO

1. Mapear PDV atual.
2. Mapear venda e itens.
3. Mapear baixa de estoque.
4. Identificar empresa/unidade do PDV.
5. Mapear documentos fiscais.
6. Criar/adaptar vínculo fiscal da venda.
7. Criar validador CNPJ x estoque.
8. Implementar trava backend.
9. Integrar lote.
10. Integrar baixa via serviço central.
11. Criar snapshot fiscal.
12. Vincular documento fiscal.
13. Registrar receita.
14. Registrar tributos da saída.
15. Criar evento fiscal.
16. Alimentar painel.
17. Criar relatórios de inconsistência.
18. Criar auditoria.
19. Criar testes.
20. Validar regressões.
21. Documentar.

---

# 67. CRITÉRIOS DE ACEITE

O módulo estará concluído quando:

- todo PDV possuir identidade fiscal;
- toda venda estiver vinculada a empresa/CNPJ;
- PDV não puder vender estoque pertencente a outro CNPJ;
- validação existir no backend;
- operação válida entre CNPJs permitir posterior venda pelo destino;
- venda baixar o estoque correto;
- lote permanecer rastreável;
- documento fiscal ficar vinculado;
- receita pertencer ao CNPJ vendedor;
- tributos de saída ficarem registrados;
- evento fiscal de venda for criado;
- painel receber os dados;
- vendas inconsistentes forem detectáveis;
- cancelamento não duplicar saldo;
- testes passarem.

---

# 68. RESULTADO ESPERADO

Exemplo correto:

```text
Fornecedor
    ↓
Compra — CNPJ B
    ↓
NF Entrada
    ↓
Estoque B
    ↓
PDV B
    ↓
Venda
    ↓
Baixa do Estoque B
    ↓
Documento Fiscal B
    ↓
Receita B
    ↓
Tributos da Venda B
    ↓
Evento Fiscal
    ↓
Painel Fiscal B
```

Outro exemplo correto:

```text
Fornecedor
    ↓
Compra — CNPJ C
    ↓
Estoque C
    ↓
Operação C → B
    ↓
Saída C
    ↓
Entrada B
    ↓
Estoque B
    ↓
PDV B
    ↓
Venda
```

Exemplo que deve ser BLOQUEADO:

```text
Fornecedor
    ↓
Compra — CNPJ C
    ↓
Estoque C

        X

PDV B
    ↓
Venda direta do estoque C
```

---

# 69. PRÓXIMA FASE

Depois deste módulo, avançar para:

# MÓDULO 7 — FISCAL, APURAÇÃO E PLANEJAMENTO TRIBUTÁRIO

Esse módulo consolidará:

```text
Compras
+ Créditos
+ Transferências/operações
+ Produção
+ Consumo
+ Perdas
+ Vendas
+ Tributos realizados
+ Estoque
        ↓
APURAÇÃO / PROJEÇÃO
        ↓
PAINEL FISCAL POR CNPJ
        ↓
PLANEJAMENTO TRIBUTÁRIO
```

Não iniciar o Módulo 7 antes de validar o Módulo 6.

---

# COMANDO FINAL PARA O CURSOR

Implemente exclusivamente o **Módulo 6 — Venda / PDV, Baixa Fiscal e Tributos de Saída**.

Não recrie o PDV existente.

A regra obrigatória é:

**O PDV de um CNPJ somente pode vender estoque fiscal pertencente ao mesmo CNPJ.**

Essa validação deve existir no backend e não pode depender somente da interface.

Se o estoque pertencer a outro CNPJ, bloquear a venda e orientar que primeiro seja registrada a operação válida entre as empresas.

Não redirecionar vendas automaticamente entre CNPJs.

Não alterar automaticamente o CNPJ emissor.

Toda venda deve ficar vinculada à empresa, unidade, estoque, lote, documento fiscal, receita, tributos de saída e evento fiscal.

Utilize o mecanismo central de movimentação para a baixa.

Preserve a rastreabilidade:

**Venda → Estoque/Lote → Compra/NF ou Produção → Origem.**

Crie painel e relatórios capazes de identificar vendas sem origem válida.

Use transactions, migrations reversíveis e auditoria.

Preserve todos os dados existentes.

Ao finalizar, execute testes de regressão e entregue relatório técnico completo antes de iniciar o módulo de apuração e planejamento tributário.
