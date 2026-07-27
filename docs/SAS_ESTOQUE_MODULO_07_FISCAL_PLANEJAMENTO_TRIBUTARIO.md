# SAS-ESTOQUE — MÓDULO 7: FISCAL, APURAÇÃO, PROJEÇÃO E PLANEJAMENTO TRIBUTÁRIO

## Instrução para o Cursor IA

Você está trabalhando no **SAS-Estoque**, sistema já existente e em produção.

Implementar exclusivamente:

# MÓDULO 7 — FISCAL E PLANEJAMENTO TRIBUTÁRIO

Este módulo deve ser iniciado somente depois que os módulos anteriores estiverem funcionando e validados:

- Módulo 1 — Cadastro Fiscal e Tributário;
- Módulo 2 — Compras / Entrada Fiscal / Estoque por CNPJ;
- Módulo 3 — Movimentações de Estoque e Eventos Fiscais;
- Módulo 5 — Produção / Ficha Técnica / Custo;
- Módulo 6 — Venda / PDV / Tributos de Saída.

Este módulo será a camada de consolidação fiscal e gerencial do SAS.

Fluxo conceitual:

```text
CADASTRO FISCAL
      +
COMPRAS / ENTRADAS
      +
CRÉDITOS POTENCIAIS
      +
MOVIMENTAÇÕES
      +
TRANSFERÊNCIAS / OPERAÇÕES ENTRE CNPJs
      +
PRODUÇÃO
      +
CONSUMO
      +
PERDAS / AVARIAS / VENCIMENTOS / EXTRAVIOS
      +
VENDAS / SAÍDAS
      ↓
EVENTOS FISCAIS
      ↓
MÓDULO FISCAL
      ↓
APURAÇÃO / PROJEÇÃO
      ↓
PAINEL POR CNPJ
      ↓
PLANEJAMENTO TRIBUTÁRIO
```

O objetivo é permitir ao SAS responder:

- quanto entrou;
- quanto saiu;
- quais tributos foram registrados;
- quais créditos existem;
- quais créditos podem exigir revisão/estorno;
- quanto há estimado a recolher;
- quanto do estoque ainda possui tributação potencial;
- qual a situação individual de cada CNPJ;
- qual a visão consolidada gerencial;
- como diferentes cenários operacionais podem alterar a carga tributária estimada.

> IMPORTANTE:
>
> O módulo deve ser uma ferramenta de apoio fiscal e planejamento tributário.
>
> Não deve substituir a escrituração contábil/fiscal oficial nem declarar automaticamente que determinado cenário é legal ou definitivo.
>
> Regras tributárias devem ser parametrizáveis, versionadas por vigência e passíveis de validação por contador/profissional responsável.
>
> Nunca codificar alíquotas ou regras fiscais sensíveis diretamente em controllers ou telas.

---

# 1. MENU FISCAL

Criar menu principal:

# Fiscal

Submenus:

```text
Fiscal
├── Visão Geral
├── Entradas
├── Saídas
├── Créditos
├── Estornos
├── Tributos a Recolher
├── Estoque e Tributação Potencial
├── Por CNPJ
└── Planejamento Tributário
```

Se já existir menu Fiscal, adaptar e não duplicar.

---

# 2. PRINCÍPIO DA ARQUITETURA

O Módulo Fiscal não deve criar cópias dos dados operacionais.

Ele deve consolidar informações vindas dos módulos anteriores.

Exemplo:

```text
Compra
→ gera dados fiscais de entrada

Venda
→ gera dados fiscais de saída

Perda
→ gera evento fiscal

Produção
→ gera evento fiscal

Operação entre CNPJs
→ gera eventos/documentos relacionados
```

O módulo Fiscal lê e consolida esses eventos.

---

# 3. FONTE DE VERDADE

Não criar campos como:

```text
total_imposto_manual
```

sem rastreabilidade.

Todo valor deve possuir origem.

Exemplo:

```text
Tributo
→ Evento fiscal
→ Venda
→ Documento
→ Item
→ Produto
```

ou:

```text
Crédito
→ NF Entrada
→ Item
→ Produto
→ Lote
```

---

# 4. MOTOR FISCAL

Criar arquitetura separada para cálculo/classificação.

Sugestão:

```text
FiscalEngine
```

ou serviços menores:

```text
ApuracaoFiscalService
CreditoFiscalService
EstornoFiscalService
ProjecaoTributariaService
PlanejamentoTributarioService
RegraFiscalService
```

Evitar um único service gigante.

---

# 5. REGRAS FISCAIS PARAMETRIZÁVEIS

Criar estrutura para regras fiscais.

Possível tabela:

`regras_fiscais`

Campos conceituais:

```text
id
nome
tributo
regime_tributario
tipo_operacao
tipo_produto nullable
perfil_tributario_id nullable
uf_origem nullable
uf_destino nullable
vigencia_inicio
vigencia_fim nullable
configuracao_json
ativo
versao
observacao
created_at
updated_at
```

Não codificar legislação complexa diretamente em código quando puder ser parametrizada.

---

# 6. VIGÊNCIA

Toda regra tributária deve permitir:

```text
vigencia_inicio
vigencia_fim
```

Isso é obrigatório para preservar cálculos históricos.

Uma alteração futura de regra não pode mudar silenciosamente uma apuração antiga.

---

# 7. VERSIONAMENTO

Guardar:

```text
versao_regra
regra_fiscal_id
data_calculo
```

nos resultados relevantes.

Assim será possível saber qual regra foi utilizada.

---

# 8. TRIBUTOS SUPORTADOS

A arquitetura deve estar preparada para:

```text
ICMS
ICMS-ST
PIS
COFINS
IPI
IRPJ
CSLL
CBS
IBS
ISS quando aplicável
outros
```

Não significa que todos serão calculados da mesma forma.

Cada regime/operação deve utilizar apenas os tributos aplicáveis.

---

# 9. REGIMES TRIBUTÁRIOS

O motor deve considerar o cadastro da Empresa:

```text
Simples Nacional
Lucro Presumido
Lucro Real
Outro
```

Não aplicar a mesma fórmula para empresas de regimes diferentes.

---

# 10. VISÃO GERAL

Criar dashboard:

# Fiscal — Visão Geral

Filtros:

```text
Período
Empresa/CNPJ
Unidade
```

Cards:

```text
Entradas
Saídas
Receita
Créditos Potenciais
Créditos Validados
Estornos
Tributos Estimados a Recolher
Valor do Estoque
Tributação Potencial do Estoque
Pendências Fiscais
```

---

# 11. GRÁFICOS

Se o padrão atual do SAS possuir gráficos, mostrar:

```text
Entradas x Saídas
Tributos por período
Tributos por CNPJ
Créditos x Débitos
Evolução da carga tributária
```

Não poluir o dashboard.

---

# 12. ENTRADAS

Menu:

# Fiscal → Entradas

Consolidar:

```text
Compras
Notas fiscais de entrada
Operações entre empresas recebidas
Devoluções quando implementadas
Outras entradas fiscais válidas
```

---

# 13. TELA DE ENTRADAS

Mostrar:

```text
Data
CNPJ
Unidade
Fornecedor/Origem
Documento
Produto
Valor
Tributos destacados
Créditos potenciais
Status fiscal
```

Filtros:

```text
Período
CNPJ
Unidade
Fornecedor
Produto
NCM
Perfil tributário
Status
```

---

# 14. SAÍDAS

Menu:

# Fiscal → Saídas

Consolidar:

```text
Vendas
Operações entre CNPJs
Devoluções
Outras saídas documentadas
```

Não misturar perda e consumo como venda.

Esses eventos devem aparecer em categorias próprias quando necessário.

---

# 15. TELA DE SAÍDAS

Mostrar:

```text
Data
CNPJ
Unidade
Tipo de saída
Documento
Destinatário/Cliente
Produto
Receita/Valor
Tributos
Status
```

---

# 16. CRÉDITOS

Menu:

# Fiscal → Créditos

Consolidar créditos originados principalmente das entradas.

Categorias:

```text
Não analisado
Potencial
Validado
Aproveitável
Aproveitado
Não aproveitável
Estornado
```

---

# 17. RASTREABILIDADE DO CRÉDITO

Ao clicar no crédito:

```text
Crédito
↓
Tributo
↓
Item NF
↓
NF Entrada
↓
Compra
↓
Produto
↓
Lote
↓
Movimentações relacionadas
```

---

# 18. NÃO APROVEITAR CRÉDITO AUTOMATICAMENTE

O sistema não deve assumir:

```text
imposto destacado = crédito automaticamente aproveitável
```

A regra depende do contexto tributário.

Permitir validação por usuário autorizado.

---

# 19. ESTORNOS

Menu:

# Fiscal → Estornos

Consolidar eventos que podem exigir revisão de crédito.

Exemplos:

```text
Perda
Avaria
Vencimento
Extravio
Furto
Cancelamento
Devolução
Outros eventos configurados
```

---

# 20. MOTOR DE ESTORNO

O sistema deve conseguir rastrear:

```text
Evento
↓
Produto
↓
Lote
↓
Compra
↓
NF
↓
Crédito relacionado
```

A partir disso, poderá calcular ou sugerir eventual estorno conforme regra fiscal configurada.

---

# 21. STATUS DO ESTORNO

```text
nao_analisado
potencial
confirmado
dispensado
processado
cancelado
```

---

# 22. TRIBUTOS A RECOLHER

Menu:

# Fiscal → Tributos a Recolher

Consolidar por:

```text
CNPJ
Período
Tributo
Regime
```

Mostrar:

```text
Débitos
Créditos considerados
Estornos
Ajustes
Valor estimado
Valor validado
Status
```

---

# 23. NÃO CONFUNDIR ESTIMATIVA COM APURAÇÃO OFICIAL

Exibir claramente:

```text
Estimativa Gerencial
```

quando ainda não validado.

E:

```text
Validado
```

quando conferido por usuário autorizado.

Nunca apresentar projeção como guia oficial paga.

---

# 24. STATUS DA APURAÇÃO

```text
aberta
calculada
com_pendencias
validada
fechada
reaberta
```

---

# 25. APURAÇÃO POR PERÍODO

Possível entidade:

`apuracoes_fiscais`

Campos:

```text
id
empresa_id
periodo_inicio
periodo_fim
regime_tributario
status
total_debitos
total_creditos
total_estornos
total_ajustes
total_estimado
total_validado nullable
regra_versao
calculado_em
validado_em nullable
validado_por nullable
created_at
updated_at
```

---

# 26. ITENS DA APURAÇÃO

Possível:

`apuracao_fiscal_itens`

```text
apuracao_id
tributo
debitos
creditos
estornos
ajustes
valor_estimado
valor_validado nullable
```

---

# 27. ESTOQUE E TRIBUTAÇÃO POTENCIAL

Menu:

# Fiscal → Estoque e Tributação Potencial

Este painel deve responder:

```text
O que ainda está no estoque?
```

```text
Quanto custou?
```

```text
A qual CNPJ pertence?
```

```text
Qual classificação fiscal possui?
```

```text
Que carga tributária poderá ocorrer se for vendido?
```

---

# 28. TRIBUTAÇÃO POTENCIAL

Conceito:

```text
Estoque atual
×
cenário de venda
×
regra fiscal aplicável
=
tributação potencial estimada
```

Não registrar isso como imposto devido.

É projeção.

---

# 29. TELA DE ESTOQUE FISCAL

Mostrar:

```text
CNPJ
Unidade
Produto
Tipo Fiscal
Perfil Tributário
Lote
Quantidade
Custo
Valor de estoque
Tributação potencial estimada
```

---

# 30. SIMULAÇÃO DE VENDA DO ESTOQUE

Permitir selecionar:

```text
Produto
Quantidade
Preço estimado de venda
CNPJ vendedor
```

Mostrar:

```text
Receita estimada
Custo
Tributos estimados
Margem gerencial estimada
```

Sem criar venda real.

---

# 31. POR CNPJ

Menu:

# Fiscal → Por CNPJ

Exibir cards/lista das empresas:

```text
CNPJ A
CNPJ B
CNPJ C
```

Para cada uma:

```text
Regime tributário
Entradas
Saídas
Receita
Estoque
Créditos
Estornos
Tributos estimados
Pendências
```

---

# 32. CONSOLIDADO DO GRUPO

Permitir:

```text
Visão Consolidada Gerencial
```

Mas deixar visualmente claro:

```text
NÃO É APURAÇÃO FISCAL ÚNICA.
```

Cada CNPJ continua com sua própria apuração.

---

# 33. PLANEJAMENTO TRIBUTÁRIO

Menu:

# Fiscal → Planejamento Tributário

Este módulo será um simulador.

Ele não altera automaticamente:

- compra;
- estoque;
- venda;
- CNPJ;
- documento fiscal.

Somente compara cenários.

---

# 34. CENÁRIOS PRINCIPAIS

Implementar inicialmente:

## Cenário 1

```text
C compra
↓
C vende
```

## Cenário 2

```text
B compra
↓
B vende
```

## Cenário 3

```text
C compra
↓
Operação C → B
↓
B vende
```

---

# 35. SIMULAÇÃO

Entrada do simulador:

```text
Produto
Quantidade
Preço de compra
Preço de venda
Empresa compradora
Empresa vendedora
Unidade
UF
Operação
Custos adicionais
```

Sempre aproveitar dados reais existentes quando disponíveis.

---

# 36. RESULTADO POR CENÁRIO

Mostrar:

```text
Cenário
Receita estimada
Custo de aquisição
Custo da operação entre empresas
Tributos de entrada
Créditos estimados
Tributos intermediários
Tributos da venda
Carga tributária total estimada
Margem estimada
```

---

# 37. COMPARAÇÃO

Exemplo visual:

```text
CENÁRIO A
C compra → C vende

Carga estimada:
R$ 12.000,00


CENÁRIO B
B compra → B vende

Carga estimada:
R$ 9.500,00


CENÁRIO C
C compra → C→B → B vende

Carga estimada:
R$ 10.300,00
```

Os valores acima são apenas exemplos de interface.

Nunca usar esses valores como regra.

---

# 38. ECONOMIA ESTIMADA

O sistema poderá calcular:

```text
Diferença entre cenários
```

Exemplo:

```text
Menor carga estimada:
Cenário B

Diferença para Cenário A:
R$ 2.500,00
```

Usar linguagem:

```text
Economia tributária estimada
```

e não:

```text
Economia garantida
```

---

# 39. NÃO RECOMENDAR OPERAÇÃO ARTIFICIAL

O sistema não deve concluir:

```text
"Transfira a venda para B porque paga menos."
```

Deve apresentar:

```text
Cenário B apresenta menor carga estimada nas premissas informadas.
```

E exibir validações/alertas.

---

# 40. VALIDAÇÃO DE SUBSTÂNCIA OPERACIONAL

Criar checklist gerencial para cenários entre empresas:

```text
A empresa compradora realmente realizará a compra?
A mercadoria realmente ingressará no estoque?
Existe operação real entre os CNPJs?
Existe documentação fiscal correspondente?
O CNPJ vendedor possuirá estoque antes da venda?
O regime tributário está corretamente cadastrado?
```

O simulador não deve substituir análise profissional.

---

# 41. ALERTA DE CENÁRIO

Mostrar:

```text
SIMULAÇÃO GERENCIAL

Este resultado depende das premissas, regras fiscais cadastradas,
regime tributário, classificação do produto e documentação da operação.

Validar com o responsável contábil/fiscal antes da execução.
```

---

# 42. CENÁRIO BASEADO EM DADOS REAIS

Permitir selecionar produto existente.

O sistema busca:

```text
NCM
CEST
Perfil tributário
Tipo fiscal
Custo atual
CNPJ
Regime tributário
Histórico de compras
Preço de venda
```

O usuário ajusta apenas o necessário.

---

# 43. CENÁRIO MANUAL

Também permitir simulação sem movimentar estoque:

```text
Novo cenário
```

Nada do cenário deve gerar:

- compra;
- NF;
- estoque;
- venda;
- lançamento financeiro.

---

# 44. SALVAR CENÁRIO

Criar:

`cenarios_tributarios`

Campos conceituais:

```text
id
nome
usuario_id
produto_id nullable
premissas_json
resultado_json
regra_versao
created_at
updated_at
```

---

# 45. COMPARAÇÃO DE CENÁRIOS

Permitir selecionar até alguns cenários e comparar lado a lado.

Exemplo:

```text
C compra → C vende
B compra → B vende
C compra → B → B vende
```

---

# 46. INDICADORES DA COMPARAÇÃO

Mostrar:

```text
Receita
Custo
Créditos estimados
Débitos estimados
Tributos totais
Carga efetiva estimada
Margem
Diferença tributária
```

---

# 47. CARGA EFETIVA ESTIMADA

Quando aplicável:

```text
Carga efetiva estimada =
Tributos totais estimados / Receita estimada × 100
```

Tratar divisão por zero.

---

# 48. LUCRO REAL x LUCRO PRESUMIDO

O simulador deve respeitar o regime cadastrado de cada empresa.

Exemplo:

```text
CNPJ C → Lucro Presumido
CNPJ B → Lucro Real
```

Não comparar apenas alíquota nominal.

Considerar a cadeia configurada e os componentes aplicáveis.

---

# 49. SIMPLES NACIONAL

Se empresa estiver no Simples:

usar motor/regra própria.

Não aplicar fórmulas de Lucro Real ou Presumido.

Preparar estrutura para considerar informações necessárias à faixa/anexo/segregação conforme regras vigentes e dados disponíveis.

---

# 50. PRODUTO DE REVENDA x PRODUÇÃO PRÓPRIA

O planejamento deve distinguir:

```text
Revenda
```

de:

```text
Produção própria
```

porque a cadeia econômica/fiscal pode ser diferente.

---

# 51. PRODUTOS COM TRATAMENTO ESPECIAL

Considerar classificação existente:

```text
Monofásico
Substituição tributária
Outros tratamentos configurados
```

Não presumir tratamento sem cadastro/regra válida.

---

# 52. OPERAÇÃO ENTRE EMPRESAS

No cenário:

```text
C compra → C→B → B vende
```

calcular separadamente:

```text
Etapa 1 — Compra C
Etapa 2 — Operação C→B
Etapa 3 — Entrada B
Etapa 4 — Venda B
```

Depois consolidar:

```text
Carga total estimada da cadeia
```

---

# 53. NÃO OLHAR APENAS O IMPOSTO DA VENDA FINAL

A comparação deve considerar a cadeia completa.

Um cenário só é melhor se o resultado total for melhor dentro das premissas.

---

# 54. PAINEL DE PENDÊNCIAS

Criar:

# Pendências Fiscais

Exemplos:

```text
Produto sem NCM
Produto sem perfil tributário
NF com divergência
Crédito não analisado
Evento fiscal pendente
Venda sem documento
Documento rejeitado
Estorno pendente
Regra fiscal sem vigência
Estoque sem origem fiscal
```

---

# 55. SCORE NÃO

Não criar "nota fiscal 8/10" arbitrária.

Usar status objetivos:

```text
Sem pendências
Com pendências
Crítico
```

baseados em regras claras.

---

# 56. FECHAMENTO DO PERÍODO

Permitir:

```text
Abrir período
Calcular
Recalcular
Validar
Fechar
Reabrir com permissão
```

Fechamento deve preservar snapshot.

---

# 57. SNAPSHOT DE APURAÇÃO

Ao fechar período, guardar resultados.

Mudanças posteriores em cadastro/regra não devem alterar automaticamente o período fechado.

---

# 58. AUDITORIA

Registrar:

```text
quem calculou
quem validou
quem fechou
quem reabriu
quando
regra utilizada
alterações
justificativa
```

---

# 59. PERMISSÕES

Sugestão:

```text
fiscal.visualizar
fiscal.entradas
fiscal.saidas
fiscal.creditos
fiscal.estornos
fiscal.apuracao
fiscal.validar
fiscal.fechar
fiscal.reabrir
fiscal.planejamento
fiscal.configurar_regras
```

Separar visualização de validação/fechamento.

---

# 60. EXPORTAÇÕES

Se o SAS já possui exportação:

permitir PDF/Excel para:

- Entradas;
- Saídas;
- Créditos;
- Estornos;
- Apuração;
- Estoque fiscal;
- Comparação de cenários.

Não criar mecanismo duplicado.

---

# 61. RELATÓRIO EXECUTIVO

Criar visão gerencial:

```text
CNPJ
Regime
Receita
Tributos estimados
Carga efetiva
Créditos
Estornos
Estoque
Tributação potencial
Pendências
```

---

# 62. RELATÓRIO DE RASTREABILIDADE

Permitir clicar em valor fiscal e chegar à origem.

Exemplo:

```text
Tributo
↓
Venda
↓
Documento
↓
Produto
↓
Lote
↓
Compra/Produção
```

---

# 63. PERFORMANCE

Como o módulo consolidará muitos dados:

- usar índices;
- evitar N+1;
- utilizar agregações;
- considerar cache apenas para dashboards;
- nunca usar cache como fonte de verdade fiscal;
- invalidar cache corretamente.

---

# 64. POSSÍVEIS ÍNDICES

Avaliar:

```text
empresa_id
unidade_id
data_evento
tipo_evento
status
produto_id
lote_id
periodo
tributo
```

---

# 65. BANCO DE DADOS

Possíveis novas estruturas:

```text
regras_fiscais
apuracoes_fiscais
apuracao_fiscal_itens
estornos_fiscais
cenarios_tributarios
```

Reutilizar:

```text
eventos_fiscais
creditos_fiscais_entrada
tributos_venda
empresas
produtos
lotes
movimentacoes
```

Não duplicar.

---

# 66. TESTES — VISÃO GERAL

Testar:

- filtro por período;
- filtro por CNPJ;
- valores de entrada;
- valores de saída;
- créditos;
- estornos;
- tributos;
- pendências.

---

# 67. TESTES — APURAÇÃO

Testar:

- CNPJ separado;
- períodos;
- regras por vigência;
- cálculo;
- recalculo;
- validação;
- fechamento;
- snapshot;
- reabertura autorizada.

---

# 68. TESTES — ESTOQUE POTENCIAL

Testar:

- estoque por CNPJ;
- produto;
- lote;
- custo;
- projeção;
- projeção não gera obrigação real.

---

# 69. TESTES — SIMULADOR

Testar:

```text
C compra → C vende
B compra → B vende
C compra → C→B → B vende
```

Garantir que cada etapa da cadeia seja considerada.

---

# 70. TESTE DE REGIMES DIFERENTES

Exemplo:

```text
C = Lucro Presumido
B = Lucro Real
```

Confirmar que o simulador utiliza regras distintas.

---

# 71. TESTE DE NÃO MOVIMENTAÇÃO

Simulação tributária não pode:

- alterar estoque;
- criar compra;
- criar NF;
- criar venda;
- alterar financeiro;
- gerar evento fiscal real.

---

# 72. TESTES DE REGRESSÃO

Confirmar:

```text
Produtos: OK
Empresas: OK
Unidades: OK
Compras: OK
NF Entrada: OK
Lotes: OK
Estoque: OK
Movimentações: OK
Transferências: OK
Produção: OK
PDV: OK
Vendas: OK
Eventos fiscais: OK
Financeiro: OK
```

---

# 73. DOCUMENTAÇÃO

Criar:

`docs/modulo-fiscal-07-apuracao-planejamento.md`

Documentar:

- arquitetura;
- menus;
- regras fiscais;
- vigências;
- cálculos;
- apuração;
- créditos;
- estornos;
- projeções;
- simulador;
- cenários;
- permissões;
- auditoria;
- testes;
- limitações.

---

# 74. RELATÓRIO FINAL DO CURSOR

```text
MÓDULO 7 — FISCAL E PLANEJAMENTO TRIBUTÁRIO

[OK] Visão Geral
[OK] Entradas
[OK] Saídas
[OK] Créditos
[OK] Estornos
[OK] Tributos a Recolher
[OK] Estoque e Tributação Potencial
[OK] Visão por CNPJ
[OK] Consolidado Gerencial
[OK] Motor de Regras
[OK] Vigências
[OK] Apuração
[OK] Planejamento Tributário
[OK] Comparação de Cenários
[OK] Auditoria
[OK] Permissões
[OK] Relatórios
[OK] Testes

Arquivos criados:
...

Arquivos alterados:
...

Migrations:
...

Regras implementadas:
...

Testes:
...

Resultado:
...

Pendências:
...
```

---

# 75. ORDEM EXATA DE IMPLEMENTAÇÃO

1. Auditar módulos anteriores.
2. Mapear eventos fiscais existentes.
3. Mapear créditos de entrada.
4. Mapear tributos de saída.
5. Criar estrutura de regras fiscais.
6. Implementar vigência/versionamento.
7. Criar Visão Geral.
8. Criar Entradas.
9. Criar Saídas.
10. Criar Créditos.
11. Criar Estornos.
12. Criar Apuração.
13. Criar Tributos a Recolher.
14. Criar Estoque e Tributação Potencial.
15. Criar visão Por CNPJ.
16. Criar Consolidado Gerencial.
17. Criar Planejamento Tributário.
18. Implementar cenários.
19. Criar comparação.
20. Criar pendências fiscais.
21. Criar auditoria.
22. Criar permissões.
23. Criar relatórios/exportações.
24. Criar testes.
25. Validar regressões.
26. Documentar.

---

# 76. CRITÉRIOS DE ACEITE

O módulo estará concluído quando:

- entradas forem consolidadas por CNPJ;
- saídas forem consolidadas por CNPJ;
- créditos forem rastreáveis;
- estornos forem rastreáveis;
- tributos a recolher puderem ser estimados/validados;
- estoque possuir projeção tributária separada de obrigação realizada;
- cada CNPJ possuir visão independente;
- consolidado for apenas gerencial;
- regras possuírem vigência e versão;
- períodos fechados preservarem snapshot;
- simulador não alterar dados reais;
- os três cenários iniciais funcionarem;
- regimes tributários diferentes forem respeitados;
- cadeia C→B for considerada integralmente;
- resultados forem apresentados como estimativas quando não validados;
- auditoria funcionar;
- testes passarem.

---

# 77. RESULTADO FINAL ESPERADO

O SAS deverá conseguir mostrar:

```text
CNPJ B
Regime: Lucro Real

Entradas: R$ ...
Saídas: R$ ...
Créditos: R$ ...
Estornos: R$ ...
Tributos estimados: R$ ...
Estoque: R$ ...
Tributação potencial: R$ ...
```

E:

```text
CNPJ C
Regime: Lucro Presumido

Entradas: R$ ...
Saídas: R$ ...
Créditos: R$ ...
Estornos: R$ ...
Tributos estimados: R$ ...
Estoque: R$ ...
Tributação potencial: R$ ...
```

Depois permitir comparar:

```text
CENÁRIO 1
C compra → C vende

CENÁRIO 2
B compra → B vende

CENÁRIO 3
C compra → operação C→B → B vende
```

Resultado:

```text
Receita estimada
Custo
Créditos
Tributos da cadeia
Carga tributária efetiva estimada
Margem estimada
Diferença entre cenários
```

Sem movimentar o estoque real.

---

# 78. PRINCÍPIO FINAL

O SAS deve separar quatro conceitos:

```text
1. TRIBUTO REGISTRADO
   Originado por operação real/documento.

2. CRÉDITO
   Originado por entrada e sujeito às regras de aproveitamento.

3. TRIBUTAÇÃO POTENCIAL
   Projeção sobre estoque/operação futura.

4. PLANEJAMENTO TRIBUTÁRIO
   Comparação simulada entre cenários.
```

Nunca misturar esses quatro conceitos.

---

# COMANDO FINAL PARA O CURSOR

Implemente exclusivamente o **Módulo 7 — Fiscal, Apuração, Projeção e Planejamento Tributário**.

Este módulo deve consolidar os dados fiscais reais gerados pelos módulos anteriores e criar as áreas:

- Visão Geral;
- Entradas;
- Saídas;
- Créditos;
- Estornos;
- Tributos a Recolher;
- Estoque e Tributação Potencial;
- Por CNPJ;
- Planejamento Tributário.

Crie um motor de regras fiscais parametrizável, versionado e com vigência.

Não codifique alíquotas tributárias sensíveis diretamente em controllers ou views.

Toda apuração deve ser rastreável até os eventos e documentos de origem.

Mantenha cada CNPJ fiscalmente separado.

A visão consolidada do grupo deve ser apenas gerencial.

Crie o simulador inicial com os três cenários:

1. C compra → C vende;
2. B compra → B vende;
3. C compra → operação C→B → B vende.

O simulador deve considerar toda a cadeia, e não somente o imposto da venda final.

O resultado deve mostrar carga tributária estimada, custos, créditos, margem e diferenças entre cenários.

Nunca altere estoque, compra, venda, documento ou financeiro a partir de uma simulação.

Nunca redirecione automaticamente faturamento para outro CNPJ.

Apresente resultados não validados como **estimativas gerenciais** e mantenha mecanismo de validação por responsável autorizado.

Preserve todos os dados existentes.

Use migrations incrementais e reversíveis.

Crie auditoria, testes, documentação e relatório final antes de considerar o módulo concluído.
