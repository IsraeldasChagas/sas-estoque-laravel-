# SAS-ESTOQUE — MÓDULO 3: MOVIMENTAÇÕES DE ESTOQUE E EVENTOS FISCAIS

## Instrução para o Cursor IA

Você está trabalhando no projeto **SAS-Estoque**, já existente e em produção.

Este documento descreve exclusivamente a implementação do:

# **Módulo 3 — Movimentações de Estoque e Eventos Fiscais**

Este módulo depende de:

- **Módulo 1 — Cadastro Fiscal e Tributário**
- **Módulo 2 — Compras, Entrada Fiscal e Vínculo do Estoque ao CNPJ**

O objetivo deste módulo é centralizar e classificar todas as saídas/movimentações de estoque conforme o motivo real da operação.

Fluxo principal:

```text
ESTOQUE
   ↓
MOVIMENTAÇÃO
   ↓
MOTIVO / DESTINO
   │
   ├── TRANSFERÊNCIA INTERNA
   ├── OPERAÇÃO ENTRE CNPJs
   ├── PRODUÇÃO
   ├── CONSUMO INTERNO
   ├── PERDA
   ├── AVARIA
   ├── VENCIMENTO
   └── EXTRAVIO / FURTO
            ↓
      BAIXA / ENTRADA
            ↓
       EVENTO FISCAL
            ↓
      RASTREABILIDADE
```

> **IMPORTANTE:** nesta fase NÃO implementar apuração tributária final, cálculo definitivo de impostos a recolher ou simulador de planejamento tributário.
>
> O foco é registrar corretamente a natureza da movimentação, preservar a rastreabilidade e gerar eventos fiscais estruturados para uso posterior.

---

# 1. PRINCÍPIO DE IMPLEMENTAÇÃO

O SAS já possui estoque e algumas movimentações.

Portanto:

1. Não recriar o módulo de estoque.
2. Não apagar histórico existente.
3. Não recriar transferência se já existe.
4. Não duplicar saldo.
5. Não recalcular estoque histórico.
6. Não quebrar compras.
7. Não quebrar lotes.
8. Não alterar o PDV nesta fase.
9. Não criar apuração tributária final.
10. Criar uma camada de classificação e evento fiscal sobre as movimentações existentes.
11. Utilizar migrations incrementais e reversíveis.
12. Preservar todos os dados atuais.
13. Reutilizar services/controllers existentes quando adequado.
14. Antes de alterar qualquer estrutura, mapear o fluxo real atual de estoque e transferências.

---

# 2. OBJETIVO DO MÓDULO

Ao final deste módulo, o SAS deverá responder:

```text
Por que este produto saiu do estoque?
```

```text
De qual CNPJ saiu?
```

```text
Para onde foi?
```

```text
Qual lote foi afetado?
```

```text
Qual quantidade saiu?
```

```text
Qual foi o custo da movimentação?
```

```text
Essa movimentação foi transferência, produção, consumo, perda, avaria, vencimento ou extravio?
```

```text
Existe evento fiscal vinculado?
```

---

# 3. TIPOS DE MOVIMENTAÇÃO

Criar uma classificação central.

Campo sugerido:

`tipo_movimentacao`

Valores:

```text
transferencia_interna
operacao_entre_cnpjs
producao
consumo_interno
perda
avaria
vencimento
extravio
furto
```

Opcionalmente manter tipos já existentes, mas mapear para essa estrutura.

---

# 4. CONCEITO DE MOVIMENTAÇÃO

Toda movimentação deve possuir, conforme aplicável:

```text
empresa_origem_id
unidade_origem_id
empresa_destino_id nullable
unidade_destino_id nullable
produto_id
lote_id nullable
quantidade
custo_unitario
custo_total
tipo_movimentacao
data_movimentacao
usuario_id
observacao
status
```

Não duplicar campos já existentes.

---

# 5. EVENTO FISCAL

Cada movimentação relevante deve gerar um registro estruturado de:

# **Evento Fiscal**

Sugestão de tabela:

`eventos_fiscais`

Campos:

```text
id
empresa_id
unidade_id nullable
movimentacao_id
produto_id
lote_id nullable
tipo_evento
origem_evento
status
data_evento
valor_base
valor_estimado nullable
observacao
created_at
updated_at
```

---

# 6. TIPOS DE EVENTO FISCAL

Sugestão:

```text
transferencia_interna
operacao_entre_empresas
consumo_producao
consumo_interno
perda
avaria
vencimento
extravio
furto
```

Esses eventos NÃO representam necessariamente imposto devido.

Eles servem para:

- registrar a ocorrência;
- identificar impacto fiscal potencial;
- alimentar apuração futura;
- permitir auditoria.

---

# 7. STATUS DO EVENTO FISCAL

Criar:

```text
pendente_analise
sem_impacto
impacto_potencial
validado
processado
cancelado
```

Nesta fase, priorizar:

```text
pendente_analise
sem_impacto
impacto_potencial
```

---

# 8. TRANSFERÊNCIA INTERNA

Conceito:

```text
Origem e destino pertencem à mesma Empresa/Pessoa Jurídica
```

Regra:

```text
empresa_origem_id == empresa_destino_id
```

Fluxo:

```text
Estoque Unidade A
    ↓
Baixa Unidade A
    ↓
Transferência Interna
    ↓
Entrada Unidade B
    ↓
Mesmo CNPJ / mesma empresa
```

---

# 9. TRATAMENTO DA TRANSFERÊNCIA INTERNA

Nesta fase:

- mover estoque;
- manter lote quando aplicável;
- manter custo;
- manter rastreabilidade;
- manter origem fiscal;
- gerar evento fiscal do tipo `transferencia_interna`;
- não tratar como venda;
- não gerar faturamento;
- não duplicar estoque;
- não alterar propriedade fiscal para outro CNPJ.

---

# 10. OPERAÇÃO ENTRE CNPJs DIFERENTES

Conceito:

```text
empresa_origem_id != empresa_destino_id
```

Mesmo que o dono/sócio seja o mesmo.

Fluxo:

```text
CNPJ A
   ↓
Saída de estoque
   ↓
Operação entre empresas
   ↓
Documento fiscal vinculado
   ↓
Entrada no CNPJ B
   ↓
Estoque passa a pertencer ao CNPJ B
```

---

# 11. DIFERENÇA ENTRE TRANSFERÊNCIA E OPERAÇÃO ENTRE EMPRESAS

O sistema deve decidir automaticamente:

```text
SE empresa_origem_id == empresa_destino_id
    transferencia_interna
SENÃO
    operacao_entre_cnpjs
```

O usuário não deve precisar conhecer toda a regra.

---

# 12. TRAVA PARA OPERAÇÃO ENTRE CNPJs

Quando forem empresas diferentes:

- não concluir silenciosamente como transferência interna;
- exigir referência documental adequada;
- registrar CNPJ origem;
- registrar CNPJ destino;
- registrar produto;
- lote;
- quantidade;
- custo;
- documento fiscal ou status documental.

---

# 13. DOCUMENTO FISCAL DA OPERAÇÃO ENTRE CNPJs

Preparar campos:

```text
documento_fiscal_id nullable
numero_documento nullable
chave_acesso nullable
modelo_documento nullable
status_documental
```

Status:

```text
pendente
vinculado
validado
cancelado
```

Não criar emissor fiscal completo nesta fase.

---

# 14. ENTRADA NO CNPJ DESTINO

Ao concluir operação válida:

```text
CNPJ origem
   ↓
Baixa estoque origem
   ↓
Entrada estoque destino
```

O estoque destino deve passar a possuir a propriedade fiscal do CNPJ destino.

Garantir rastreabilidade com a operação de origem.

---

# 15. PRODUÇÃO

Produção deve ser tratada como transformação.

Exemplo:

```text
Arroz
Peixe
Óleo
Temperos
   ↓
PRODUÇÃO
   ↓
Prato preparado
```

---

# 16. MOVIMENTAÇÃO DE PRODUÇÃO

Nesta fase, se o SAS já possui produção/ficha técnica, integrar.

Fluxo:

```text
Insumos
   ↓
Baixa dos lotes utilizados
   ↓
Evento: consumo_producao
   ↓
Produto produzido
   ↓
Entrada no estoque de produto acabado
```

---

# 17. NÃO TRATAR PRODUÇÃO COMO PERDA

Baixa de ingrediente para produção deve ser classificada como:

`producao`

e nunca como:

`perda`

---

# 18. CUSTO DA PRODUÇÃO

Manter método existente.

A movimentação deve registrar:

```text
quantidade_consumida
custo_unitario
custo_total
lote_origem
produto_final
producao_id
```

---

# 19. CONSUMO INTERNO

Conceito:

produto utilizado pela própria empresa sem venda e sem incorporação direta à produção.

Exemplos:

- uso interno;
- materiais consumidos pela operação;
- refeição de funcionário, se classificada dessa forma;
- itens de limpeza e consumo.

---

# 20. MOVIMENTAÇÃO DE CONSUMO INTERNO

Fluxo:

```text
Estoque
   ↓
Consumo Interno
   ↓
Baixa
   ↓
Evento Fiscal
```

Campos adicionais:

```text
motivo_consumo
setor_destino nullable
responsavel nullable
```

---

# 21. PERDA

Conceito:

produto inutilizado ou perdido sem venda.

Exemplos:

- deterioração;
- desperdício;
- quebra não classificada como avaria específica;
- perda operacional.

---

# 22. MOVIMENTAÇÃO DE PERDA

Campos:

```text
motivo_perda
quantidade
produto_id
lote_id
custo_total
responsavel
observacao
anexo nullable
```

Gerar evento fiscal:

`perda`

Status inicial:

`impacto_potencial`

---

# 23. AVARIA

Avaria deve ser separada de perda genérica.

Exemplos:

- embalagem danificada;
- garrafa quebrada;
- produto contaminado;
- dano físico.

Tipo:

`avaria`

---

# 24. VENCIMENTO

Produtos vencidos devem gerar movimentação própria:

`vencimento`

Campos:

```text
produto_id
lote_id
data_validade
quantidade
custo_total
data_baixa
```

Preferencialmente permitir seleção direta por lote vencido.

---

# 25. EXTRAVIO

Conceito:

mercadoria desaparecida sem causa confirmada.

Tipo:

`extravio`

Registrar:

```text
responsavel_registro
data_constatacao
produto
lote
quantidade
custo
observacao
```

---

# 26. FURTO

Tipo separado:

`furto`

Permitir:

```text
numero_ocorrencia nullable
anexo_ocorrencia nullable
data_ocorrencia
observacao
```

Não exigir boletim em todos os casos na aplicação, mas permitir anexar e rastrear.

---

# 27. PERDA x AVARIA x VENCIMENTO x EXTRAVIO x FURTO

Não usar um único motivo genérico.

Separar porque futuramente o tratamento fiscal e documental pode ser diferente.

---

# 28. MOTIVO OBRIGATÓRIO

Para movimentos não comerciais como:

- consumo interno;
- perda;
- avaria;
- extravio;
- furto;

exigir motivo/justificativa.

---

# 29. ANEXOS

Se o SAS já possui sistema de anexos, permitir anexar:

- foto;
- relatório;
- documento;
- ocorrência;
- comprovante.

Não criar novo módulo de documentos se já existir solução reutilizável.

---

# 30. RASTREABILIDADE POR LOTE

Toda movimentação deve preservar:

```text
produto
lote
origem
destino
quantidade
custo
data
usuário
tipo
```

Quando produto trabalhar por lote, o lote deve ser obrigatório.

---

# 31. VALIDAÇÃO DE SALDO

Antes de qualquer saída:

```text
quantidade_solicitada <= saldo_disponivel
```

Não permitir saldo negativo, salvo se o sistema atual possuir regra explícita autorizada.

---

# 32. CONCORRÊNCIA

Movimentações devem ser processadas de forma segura.

Usar:

- transação de banco;
- lock apropriado quando necessário;
- validação de saldo dentro da transaction.

Evitar duas saídas simultâneas consumirem o mesmo saldo.

---

# 33. EVENTO DE ESTOQUE

Toda movimentação deve gerar/usar um registro de histórico.

Exemplo:

```text
tipo
produto_id
lote_id
empresa_origem_id
unidade_origem_id
empresa_destino_id
unidade_destino_id
quantidade
custo
referencia_tipo
referencia_id
usuario_id
data
```

---

# 34. EVENTO FISCAL NÃO SUBSTITUI EVENTO DE ESTOQUE

Manter separação:

```text
Movimentação de Estoque
```

= quantidade/custo/localização

```text
Evento Fiscal
```

= natureza e impacto fiscal potencial

Relacionar os dois.

---

# 35. CANCELAMENTO DE MOVIMENTAÇÃO

Se o sistema permitir cancelar:

- não apagar histórico;
- gerar reversão;
- restaurar saldo corretamente;
- cancelar evento fiscal relacionado;
- manter auditoria.

---

# 36. NÃO EXCLUIR MOVIMENTAÇÃO PROCESSADA

Movimentação processada não deve ser apagada fisicamente.

Preferir:

```text
cancelada
estornada
```

---

# 37. INTERFACE CENTRAL DE MOVIMENTAÇÕES

Criar ou adaptar página:

# **Movimentações de Estoque**

Filtros:

```text
Período
Empresa/CNPJ
Unidade
Produto
Lote
Tipo de movimentação
Status
Usuário
```

---

# 38. TELA NOVA MOVIMENTAÇÃO

Fluxo:

```text
1. Empresa / Unidade origem
2. Produto / Lote
3. Quantidade
4. Motivo / Tipo
5. Destino, se aplicável
6. Justificativa
7. Documento/anexo, se aplicável
8. Confirmar
```

---

# 39. CAMPOS DINÂMICOS POR TIPO

## Transferência interna

Mostrar:

```text
Unidade destino
```

## Operação entre CNPJs

Mostrar:

```text
Empresa destino
Unidade destino
Documento fiscal
```

## Produção

Mostrar:

```text
Ordem/Produção
Produto final
```

## Consumo interno

Mostrar:

```text
Motivo
Setor
```

## Perda/Avaria/Vencimento

Mostrar:

```text
Motivo
Lote
Anexo opcional
```

## Extravio/Furto

Mostrar:

```text
Descrição
Ocorrência
Anexo
```

---

# 40. DECISÃO AUTOMÁTICA ENTRE TRANSFERÊNCIA E OPERAÇÃO ENTRE CNPJs

Se o usuário escolher origem e destino:

```text
empresa_origem_id == empresa_destino_id
```

Classificar:

`transferencia_interna`

Senão:

`operacao_entre_cnpjs`

Não confiar somente na opção escolhida manualmente.

---

# 41. ALERTAS

Criar alertas para:

```text
Saldo insuficiente
Lote incompatível
Empresa destino diferente
Documento fiscal pendente
Produto sem classificação fiscal
Produto com perfil tributário incompleto
Movimentação sem justificativa
```

---

# 42. EVENTO FISCAL AUTOMÁTICO

Após processar a movimentação:

```text
Movimentação Estoque
       ↓
FiscalEventService
       ↓
Evento Fiscal
```

O service deve receber dados da movimentação e classificar.

---

# 43. SERVICE SUGERIDO

Se arquitetura permitir:

```text
MovimentacaoEstoqueService
EventoFiscalService
TransferenciaInternaService
OperacaoEntreEmpresasService
PerdaEstoqueService
ConsumoInternoService
```

Evitar controller com toda a regra.

---

# 44. BANCO DE DADOS

Possíveis migrations:

```text
add_tipo_fiscal_to_movimentacoes_estoque
add_company_links_to_movimentacoes_estoque
create_eventos_fiscais_table
add_document_reference_to_movimentacoes
add_reason_fields_to_movimentacoes
```

Adaptar à estrutura real.

---

# 45. MODEL EVENTO FISCAL

Possível:

```text
EventoFiscal
```

Relacionamentos:

```text
belongsTo Empresa
belongsTo Unidade
belongsTo Produto
belongsTo Lote
belongsTo MovimentacaoEstoque
```

---

# 46. STATUS DA MOVIMENTAÇÃO

Sugestão:

```text
rascunho
pendente
processada
cancelada
estornada
```

---

# 47. PERMISSÕES

Se o SAS usa permissões:

```text
movimentacao.visualizar
movimentacao.criar
movimentacao.cancelar
movimentacao.transferir
movimentacao.perda
movimentacao.consumo
movimentacao.extravio
evento_fiscal.visualizar
```

---

# 48. AUDITORIA

Registrar:

```text
quem criou
quem aprovou
quem cancelou
quando
produto
lote
quantidade
origem
destino
motivo
valores
```

---

# 49. RELATÓRIO DE MOVIMENTAÇÕES

Criar/adaptar relatório:

Colunas:

```text
Data
CNPJ
Unidade
Produto
Lote
Tipo
Quantidade
Custo
Destino
Status
Evento Fiscal
```

---

# 50. RELATÓRIO DE PERDAS

Filtros:

```text
Período
Empresa
Unidade
Produto
Tipo
```

Tipos:

```text
perda
avaria
vencimento
extravio
furto
```

Mostrar:

```text
Quantidade
Custo
Motivo
Responsável
Evento fiscal
```

---

# 51. RELATÓRIO DE CONSUMO INTERNO

Mostrar:

```text
Empresa
Unidade
Produto
Quantidade
Custo
Setor
Motivo
```

---

# 52. RELATÓRIO DE TRANSFERÊNCIAS

Separar:

```text
Transferências Internas
Operações entre CNPJs
```

Nunca misturar como se fossem a mesma natureza.

---

# 53. NÃO CALCULAR IMPOSTO FINAL

Nesta fase, evento fiscal deve guardar:

```text
tipo
base
classificação
impacto potencial
status
```

Não gerar guia.
Não calcular imposto total do mês.
Não fazer DRE fiscal.
Não fazer planejamento tributário.

---

# 54. PREPARAÇÃO PARA APURAÇÃO

A estrutura deve permitir futuramente:

```text
Eventos Fiscais
   ↓
Débitos
Créditos
Estornos
   ↓
Apuração
```

---

# 55. PREPARAÇÃO PARA ESTORNO DE CRÉDITO

Perda, avaria, vencimento, extravio e furto devem permitir identificar:

```text
qual lote
qual compra
qual NF
qual crédito fiscal potencial
```

Não calcular estorno agora.

---

# 56. PREPARAÇÃO PARA PRODUÇÃO

A produção deve permitir identificar:

```text
quais insumos saíram
quais lotes foram usados
qual produto final foi gerado
qual custo foi transferido
```

---

# 57. PREPARAÇÃO PARA PDV

Futuramente o PDV verificará:

```text
estoque.empresa_id == pdv.empresa_id
```

Não implementar essa trava ainda.

---

# 58. TESTES OBRIGATÓRIOS

## Transferência interna

- mesma empresa;
- baixa na origem;
- entrada no destino;
- estoque total consolidado não muda;
- evento fiscal criado;
- custo mantido;
- lote rastreável.

## Operação entre CNPJs

- empresas diferentes;
- classifica corretamente;
- exige documento/status;
- baixa origem;
- entrada destino;
- propriedade fiscal muda;
- evento fiscal criado.

## Produção

- insumo baixa;
- lote rastreado;
- evento produção criado;
- não classifica como perda.

## Consumo interno

- baixa estoque;
- exige motivo;
- evento criado.

## Perda

- baixa;
- justificativa;
- evento fiscal potencial.

## Avaria

- baixa;
- classificação correta.

## Vencimento

- lote válido;
- data de validade;
- baixa correta.

## Extravio/Furto

- baixa;
- justificativa;
- registro documental opcional.

---

# 59. TESTES DE REGRESSÃO

Confirmar:

```text
Produtos: OK
Empresas: OK
Unidades: OK
Compras: OK
Notas de entrada: OK
Lotes: OK
Estoque: OK
Transferências atuais: OK
PDV: OK
Usuários/permissões: OK
```

---

# 60. DOCUMENTAÇÃO

Criar:

`docs/modulo-fiscal-03-movimentacoes-estoque.md`

Documentar:

- migrations;
- tabelas;
- models;
- services;
- controllers;
- rotas;
- telas;
- tipos;
- eventos fiscais;
- regras;
- testes;
- pendências.

---

# 61. RELATÓRIO FINAL DO CURSOR

Formato:

```text
MÓDULO 3 — MOVIMENTAÇÕES DE ESTOQUE

[OK] Tipos de movimentação
[OK] Transferência interna
[OK] Operação entre CNPJs
[OK] Produção
[OK] Consumo interno
[OK] Perda
[OK] Avaria
[OK] Vencimento
[OK] Extravio/Furto
[OK] Eventos fiscais
[OK] Rastreabilidade por lote
[OK] Auditoria
[OK] Relatórios
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

# 62. ORDEM EXATA DE IMPLEMENTAÇÃO

1. Mapear estoque e movimentações atuais.
2. Identificar tabela/serviço atual de transferências.
3. Mapear lotes e vínculo com CNPJ.
4. Criar enum/tipos de movimentação.
5. Criar/adaptar estrutura de movimentação.
6. Criar eventos fiscais.
7. Implementar transferência interna.
8. Implementar operação entre CNPJs.
9. Integrar produção.
10. Implementar consumo interno.
11. Implementar perda.
12. Implementar avaria.
13. Implementar vencimento.
14. Implementar extravio/furto.
15. Criar interface central.
16. Criar relatórios.
17. Criar auditoria.
18. Executar testes.
19. Validar regressões.
20. Documentar.

---

# 63. CRITÉRIOS DE ACEITE

O Módulo 3 estará concluído somente quando:

- toda saída tiver motivo/tipo definido;
- toda movimentação tiver origem identificada;
- toda saída puder ser rastreada por produto e lote;
- transferência interna for separada de operação entre CNPJs;
- produção não for confundida com perda;
- consumo interno for separado;
- perda, avaria, vencimento, extravio e furto forem tipos distintos;
- cada movimentação relevante gerar evento fiscal;
- nenhuma operação duplicar estoque;
- não houver saldo negativo indevido;
- histórico não for apagado;
- cancelamentos forem reversíveis;
- testes passarem.

---

# 64. RESULTADO ESPERADO

Ao final, o SAS deverá saber:

```text
Este produto saiu por quê?
```

```text
Foi para outra unidade da mesma empresa?
```

```text
Foi para outro CNPJ?
```

```text
Foi utilizado na produção?
```

```text
Foi consumido internamente?
```

```text
Foi perdido, avariado ou vencido?
```

```text
Foi extraviado ou furtado?
```

```text
Qual evento fiscal está relacionado?
```

Ainda NÃO deverá responder definitivamente:

```text
Quanto imposto esse evento gerou?
```

```text
Quanto crédito precisa ser estornado?
```

```text
Quanto imposto será pago no mês?
```

---

# 65. PRÓXIMA FASE

Após concluir este módulo:

# **MÓDULO 4 — TRANSFERÊNCIAS E OPERAÇÕES FISCAIS ENTRE EMPRESAS**

Se a estrutura do Módulo 3 já cobrir suficientemente as transferências, o Módulo 4 poderá aprofundar apenas:

- documentos fiscais;
- regras por operação;
- integração com emissão;
- validação fiscal;
- tratamento entre CNPJs.

Depois:

# **MÓDULO 5 — PRODUÇÃO E CUSTO FISCAL**

E posteriormente:

# **MÓDULO 6 — VENDA / PDV E EVENTOS TRIBUTÁRIOS DE SAÍDA**

---

# COMANDO FINAL PARA O CURSOR

Implemente exclusivamente o **Módulo 3 — Movimentações de Estoque e Eventos Fiscais**.

Não recrie o estoque.

Não recrie transferências já existentes sem antes mapear a implementação atual.

Centralize os motivos de movimentação em:

- transferência interna;
- operação entre CNPJs;
- produção;
- consumo interno;
- perda;
- avaria;
- vencimento;
- extravio;
- furto.

Cada movimentação deve continuar realizando corretamente a baixa/entrada de estoque e, adicionalmente, criar um evento fiscal estruturado e rastreável.

Diferencie automaticamente transferência interna de operação entre empresas por meio da comparação de `empresa_id` de origem e destino.

Preserve lote, custo, origem fiscal e histórico.

Não implemente apuração tributária final nesta fase.

Não altere o PDV.

Não gere imposto definitivo.

Use migrations incrementais e reversíveis.

Preserve todos os dados existentes.

Ao finalizar, execute testes de regressão e entregue relatório técnico completo antes de iniciar o próximo módulo.
