# Banco de Dados — IAs Legadas (recomendação, sem DROP)

Data: 2026-07-13

> **Política aplicada (FASE 4):** nenhum `DROP TABLE` foi executado e nenhuma
> migration destrutiva foi criada. Este documento apenas **recomenda** e lista
> as tabelas candidatas à remoção futura, para decisão consciente em produção.

## Tabelas candidatas à remoção futura

| Tabela | Migration de origem | Referência no código após limpeza | Recomendação |
|---|---|---|---|
| `ai_conversations` | `2026_06_17_000001_create_sas_ia_tables.php` | Apenas leitura opcional em `SasIaContext::perguntasHoje()` (via `Schema::hasTable`, fora do caminho da Ayla) | Candidata a remoção futura |
| `ai_messages` | `2026_06_17_000001_create_sas_ia_tables.php` | Apenas leitura opcional em `SasIaContext::perguntasHoje()` (via `Schema::hasTable`) | Candidata a remoção futura |
| `ai_tool_logs` | `2026_06_17_000001_create_sas_ia_tables.php` | Nenhuma (models removidos) | Candidata a remoção futura |
| `ai_agents` | `2026_06_23_000001_create_ai_agents_tables.php` | Nenhuma (controller/model removidos) | Candidata a remoção futura |
| `ai_agent_modules` | `2026_06_23_000001_create_ai_agents_tables.php` | Nenhuma | Candidata a remoção futura |

## Tabelas que DEVEM ser mantidas

| Tabela | Motivo |
|---|---|
| `ai_documents` | Usada por `SasIaDocumentService` → `AiDocument`, que faz parte do grafo de dependências da **Ayla** (via `SasIaModuleQueryService`). **NÃO remover.** |
| `ai_assistant_logs` | Usada pela integração **OpenClaw** (`AiAssistantService`, `OpenClawConfigController`). **NÃO remover.** |
| `ayla_audit_logs` | **Ayla.** Não tocar. |
| `ayla_usuarios_autorizados` | **Ayla.** Não tocar. |
| `sistema_configuracoes` | Compartilhada por todo o sistema (não é exclusiva de IA). Mantidas as chaves `ia_*` como dado histórico; podem ser limpas manualmente no futuro. |

## Observações de segurança

- `SasIaContext::perguntasHoje()` verifica `Schema::hasTable()` antes de consultar
  `ai_conversations`/`ai_messages`; portanto, mesmo se essas tabelas forem
  removidas no futuro, **não haverá erro em runtime**. Esse método também **não é
  chamado no caminho da Ayla** (a Ayla é uma API somente-leitura sem LLM).
- A migration `2026_06_17_000001_create_sas_ia_tables.php` cria as 3 tabelas de
  chat **e** a tabela `ai_documents` (que deve permanecer). Por isso a migration
  **não foi apagada**. Uma futura migration de limpeza deve dropar somente
  `ai_conversations`, `ai_messages` e `ai_tool_logs`.

## Como remover no futuro (quando aprovado, com backup do banco)

Antes de qualquer remoção, confirmar em produção se há registros relevantes:

```sql
SELECT 'ai_conversations' t, COUNT(*) n FROM ai_conversations
UNION ALL SELECT 'ai_messages', COUNT(*) FROM ai_messages
UNION ALL SELECT 'ai_tool_logs', COUNT(*) FROM ai_tool_logs
UNION ALL SELECT 'ai_agents', COUNT(*) FROM ai_agents
UNION ALL SELECT 'ai_agent_modules', COUNT(*) FROM ai_agent_modules;
```

Somente após backup completo do banco e aprovação, criar uma migration dedicada
com `Schema::dropIfExists(...)` para essas 5 tabelas — **nunca** incluindo
`ai_documents`, `ai_assistant_logs` ou tabelas `ayla_*`.

## Variáveis / chaves candidatas a limpeza futura (não removidas)

- `.env`: `OPENAI_API_KEY`, `OPENAI_MODEL` (usadas apenas por código já removido / por `SasIaContext::limiteDiario()` com fallback). **Mantidas por segurança.**
- `config/openai.php` e bloco `openai` de `config/services.php`: mantidos (fallback seguro).
- `sistema_configuracoes`: chaves `ia_ativo`, `ia_api_key`, `ia_modelo`, `ia_instrucoes` (do chat OpenAI legado). Mantidas como dado; podem ser limpas manualmente.
