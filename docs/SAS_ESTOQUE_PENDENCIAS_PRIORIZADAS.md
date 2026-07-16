# SAS-Estoque — Pendências Priorizadas

**Data:** 2026-07-14  
**Base:** `docs/SAS_ESTOQUE_MAPEAMENTO_COMPLETO.md` + `docs/SAS_ESTOQUE_MATRIZ_MODULOS.csv`  
**Regra:** esta lista **não** implementa nada; apenas prioriza trabalho futuro.

---

## Crítico

1. **Dois canais de IA em produção (OpenClaw `/api/ia` com ações vs Ayla somente leitura)**  
   Risco de o time/agente usar a API errada e executar perdas/compras sem o fluxo Ayla.  
   Evidência: `ai_assistant_routes.php`, `ayla_routes.php`, skills distintas.

2. **Schema core sem migrations create no repositório** (`produtos`, `unidades`, `usuarios`, `lotes`, `movimentacoes`, listas)  
   Ambientes novos e auditorias de DR ficam frágeis.  
   Evidência: migrations só alteram; models `Unidade`/`Usuario` apontam para tabelas legado.

3. **MCP incompleto vs API Ayla**  
   Telegram/OpenClaw com `tools.json` só Kanban+Patrimônio pode “não enxergar” estoque já disponível em `/api/ayla/v1`.  
   Evidência: `openclaw/mcp-sas-estoque/tools.json` vs `ayla_routes.php`.

4. **Monólito `api.php` (~11k linhas de closures)**  
   Qualquer mudança de estoque/RH/financeiro aumenta risco de regressão.  
   Evidência: `backend/routes/api.php`.

---

## Alta prioridade

5. **Integrar Financeiro à Ayla (somente leitura)**  
   Módulo funcional no SAS; ainda sem endpoint Ayla. Alto valor para perguntas gerenciais.

6. **Integrar Reservas + Fechamento (resumo) à Ayla**  
   Operação diária; tools SasIa já existem no registry sem rota Ayla.

7. **Integrar RH (resumo funcionários / recrutamento / rescisões) à Ayla**  
   Tools já mapeadas em `SasIaToolRegistry`; falta exposição HTTP Ayla + MCP.

8. **Ampliar `tools.json` / VPS MCP** para todos os GET `/api/ayla/v1` já existentes (estoque, dashboard, produtos…).

9. **Unificar documentação e skills de deploy** para apontar apenas Ayla (`/api/ayla/v1`), depreciando narrativa `/api/ia` quando Ayla for o padrão.

10. **RH Entrevistas e Banco de talentos** — HTML/API existem, menu ausente. Decidir: publicar no menu ou remover do produto.

---

## Média prioridade

11. Limpar menu duplicado `fechamento` (dois links iguais).  
12. Corrigir loader F5 de `reciboAjuda`.  
13. Remover ou documentar `UsuarioController` órfão.  
14. Decisão sobre tabelas `ai_*` órfãs (candidatas a DROP futuro com backup — já listadas em `IA_LEGADA_BANCO_DADOS.md`).  
15. Garantia patrimonial: formalizar campo no banco ou documentar limitação Ayla (só JSON).  
16. Expandir testes Feature (financeiro, RH, patrimônio core — hoje forte só na Ayla).  
17. Corrigir / remover `ExampleTest` (GET `/` → 302) para CI limpa.  
18. Expor ou documentar `financeiro_clientes` e `sugestoes-compras` (backends sem destaque no menu).

---

## Baixa prioridade

19. Extrair domains de `api.php` para controllers/services (refatoração gradual).  
20. Versionar dump/schema do core legado.  
21. Documentar matriz completa `permissoes_menu` × perfil.  
22. Avaliar destino de `ai_documents` / `SasIaDocumentService` sem UI.  
23. Revisar dualidade Laravel `users` vs `usuarios`.  
24. Remover scripts/dev mortos de `frontend/scripts` do pacote de deploy (se aplicável).

---

## Melhoria futura (produto — hoje **não existem**)

Só iniciar após decisão de negócio explícita; **não** há código funcional:

| Tema | Situação atual |
|---|---|
| PDV / vendas / caixa operador | Só campos auxiliares no fechamento |
| Delivery / entregadores / taxa | Removido do produto (não usar) |
| Cardápio digital | Não encontrado |
| NF-e / NFC-e / NFS-e | Não encontrado |
| Fidelidade / CRM | Não encontrado |
| Inventário físico de estoque | Não encontrado |
| Escalas / folha salarial / atestados / advertências | Não encontrado |
| Comissões | Não encontrado |
| Produção / PCP | Não encontrado (só ficha técnica) |
| iFood / OSRM / Stripe / Pagar.me | Não encontrado como integração |

---

## Top 10 pendências (visão executiva)

1. Unificar narrativa e operação Ayla vs OpenClaw `/ia`.  
2. Completar MCP com tools já cobertas pela API Ayla.  
3. Versionar schema core (produtos/unidades/usuarios/estoque).  
4. Ayla → Financeiro read-only.  
5. Ayla → Reservas + Fechamento resumo.  
6. Ayla → RH resumos.  
7. Menu RH incompleto (entrevistas / banco).  
8. Reduzir dívida de `api.php` (extração por domínio).  
9. Limpeza controlada de `ai_*` órfãs.  
10. Decisão de roadmap: PDV/fiscal (greenfield).

---

## Ordem recomendada de desenvolvimento

```
Estabilizar narrativa Ayla + MCP parity
        ↓
Ayla Financeiro (GET)
        ↓
Ayla Reservas / Fechamento (GET)
        ↓
Ayla RH (GET)
        ↓
Correções UX/menu RH + CI tests
        ↓
Refatoração progressiva api.php
        ↓
(Se negócio aprovar) PDV / Fiscal do zero
```

---

*Documento analítico. Nenhuma alteração foi feita no sistema nesta entrega.*
