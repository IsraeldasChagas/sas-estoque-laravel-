# Levantamento fiscal — complemento diretoria

**Data:** 28/07/2026  
**Uso:** detalhe da **Parte 2 — Fiscal** do documento unificado `LEVANTAMENTO_SAS_2026-07-28`.

---

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
