# Integração Ayla ↔ Patrimônio (Fase 1 — Somente Leitura)

Integração do módulo **Patrimônio** do SAS-Estoque à assistente **Ayla**.
Nesta fase a Ayla **apenas consulta e analisa** bens patrimoniais. Nenhuma ação de
escrita (cadastrar, editar, transferir, baixar, excluir, depreciar, anexar,
registrar manutenção, alterar responsável/valor/status) foi liberada.

## 1. Arquitetura

```
Telegram / OpenClaw
     ↓
 MCP SAS-Estoque (ferramentas patrimonio_*)
     ↓
 API /api/ayla/v1/patrimonio*   ← token + rate limit + auditoria
     ↓
 AylaApiService → SasIaToolService → SasIaModuleQueryService
     ↓
 AylaPatrimonioService (somente leitura)
     ↓
 Banco: patrimonios (+ categorias, unidades, setores, manutencoes, movimentacoes)
```

O cliente **nunca** envia nome de tabela/campo/ferramenta arbitrário: cada endpoint
mapeia internamente para uma ferramenta read-only da allow-list.

## 2. Arquivos

### Criados
- `backend/app/Services/Ayla/AylaPatrimonioService.php` — consultas, resumo, detalhe, por unidade, alertas.
- `docs/mcp/patrimonio-tools.mjs` — bloco MCP para a VPS.
- `docs/AYLA_PATRIMONIO_INTEGRACAO.md` — este documento.

### Alterados
- `backend/app/Http/Controllers/Api/AylaController.php` — endpoints + `parsePatrimonioArgs()` + código `NOT_FOUND`/`VALIDATION_ERROR` no `normalizar()`.
- `backend/routes/ayla_routes.php` — 5 rotas GET de patrimônio.
- `backend/app/Support/Ayla/AylaSettings.php` — módulo `patrimonio` em `modulosLiberados()`.
- `backend/app/Support/SasIa/SasIaToolRegistry.php` — 5 ferramentas registradas (mapa de módulos + definitions).
- `backend/app/Services/SasIaModuleQueryService.php` — handlers `patrimonio_*` delegando ao `AylaPatrimonioService`.
- `backend/app/Services/AylaApiService.php` — allow-list `TOOLS_PERMITIDAS` + preserva `code` de erro.
- `backend/tests/Feature/AylaApiTest.php` — 10 testes novos de patrimônio.
- `openclaw/mcp-sas-estoque/tools.json` — 5 ferramentas MCP.
- `openclaw/skill-ayla/SKILL.md` — instruções para a Ayla usar patrimônio.
- `docs/AYLA_API_V1.md` — tabela de endpoints.

## 3. Endpoints (todos GET, prefixo `/api/ayla/v1`)

| Rota | Descrição |
|---|---|
| `/patrimonio` | Lista de bens com filtros |
| `/patrimonio/resumo` | Resumo patrimonial (totais, valores, por unidade/categoria, alertas) |
| `/patrimonio/alertas` | Alertas (garantia, manutenção, pendências) |
| `/patrimonio/unidade/{id}` | Resumo + lista de bens de uma unidade |
| `/patrimonio/{id}` | Detalhes de um bem (com manutenções e movimentações) |

## 4. Filtros de `/patrimonio`

| Filtro | Tipo | Regra |
|---|---|---|
| `busca` | string | ≤120 chars; nome/código/série/marca/modelo/fornecedor/categoria/unidade |
| `patrimonio_id` | int > 0 | |
| `unidade_id` | int > 0 | precisa estar autorizada |
| `unidade` | string | resolve nome → id (404 se não existir) |
| `categoria` | string/int | nome (LIKE) ou id |
| `status` | enum | `ativo`, `manutencao`, `baixado`, `vendido`, `quebrado` |
| `responsavel` | string | LIKE parcial |
| `setor` | string/int | nome (LIKE) ou id |
| `data_inicio` / `data_fim` | date | `YYYY-MM-DD` (aquisição) |
| `valor_minimo` / `valor_maximo` | number ≥ 0 | valor de compra |
| `limite` | 1–50 | padrão 50 |

## 5. Campos reais do banco (sem campos fictícios)

Tabela `patrimonios`: `codigo` (número patrimonial), `nome`, `numero_serial`,
`categoria_id`→`patrimonio_categorias`, `marca` (fabricante), `modelo`, `cor`,
`quantidade`, `unidade_id`→`unidades`, `setor_id`/`setor`, `responsavel` (texto),
`situacao`, `valor_compra`, `data_compra`, `vida_util_meses`, `valor_atual`,
`depreciacao`, `fornecedor`, `numero_nf`, `observacoes`, `dados_especificos` (JSON).

Observações importantes:
- **Garantia**: não há coluna dedicada. Alertas de garantia usam
  `dados_especificos.vencimento_garantia` (JSON), quando existir.
- **Manutenção**: `proxima_manutencao` vive em `patrimonio_manutencoes` (por registro).
- **Localização**: não há coluna própria em `patrimonios`; `setor` é o mais próximo.

## 6. Permissões

Permissão efetiva = `permissoes_menu` do usuário ∩ módulos da Ayla ∩ unidades permitidas.

- Ferramentas exigem um dos módulos: `patrimonios`, `patrimonioDashboard`,
  `patrimonioRelatorios`, `patrimonioManutencoes` (via `SasIaToolRegistry`).
- ADMIN: todas as unidades. GERENTE/demais: escopo da própria unidade
  (`SasIaContext`).
- Sem `X-Usuario-Id` (token only): leitor de sistema restrito por
  `AYLA_ALLOWED_UNITS`.
- Usuário sem permissão → **403** (`PERMISSION_DENIED`).
- Unidade não autorizada → **403** (`UNIT_NOT_ALLOWED`). Nunca retorna bens de
  unidade fora do escopo.

## 7. Validações

IDs inteiros positivos; `limite` 1–50; `busca`/textos ≤120 chars; datas
`YYYY-MM-DD`; valores numéricos ≥ 0; `status` em allow-list real; unidade
existente e autorizada. Parâmetro inválido → **422** (`VALIDATION_ERROR`).
Bem inexistente → **404** (`NOT_FOUND`).

## 8. Auditoria (`ayla_audit_logs`)

Cada requisição registra: usuário, IP, método, rota, ação (`ayla.patrimonio*`),
filtros (query), canal/sender (Telegram, quando enviados em `X-Ayla-Channel` /
`X-Ayla-Sender-Id`), contagem retornada, status HTTP, duração e sucesso/erro.
**Nunca** registra token, senha, API key ou documentos sensíveis (sanitização em
`AylaAuditLog`).

## 9. Ferramentas registradas

`patrimonio_consultar`, `patrimonio_resumo`, `patrimonio_detalhar`,
`patrimonio_por_unidade`, `patrimonio_alertas`.

## 10. Exemplos curl

```bash
TOKEN="ayla_sas_xxx"
BASE="https://api.gruposaborparaense.com.br/api/ayla/v1"

curl -s -H "Authorization: Bearer $TOKEN" "$BASE/patrimonio/resumo"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/patrimonio?status=manutencao"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/patrimonio?unidade=Doce%20Norte"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/patrimonio?categoria=Inform%C3%A1tica"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/patrimonio/alertas"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/patrimonio/unidade/2"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/patrimonio/10"
```

## 11. Exemplos de perguntas (Telegram)

- "Ayla, faça um resumo do patrimônio."
- "Ayla, quais bens existem na Unidade 2?"
- "Ayla, quais equipamentos estão em manutenção?"
- "Ayla, quais bens estão sem responsável?"
- "Ayla, qual o valor total do patrimônio?"
- "Ayla, quais garantias vencem em breve?"

## 12. Limitações (Fase 1)

- Somente leitura. Sem cadastro/edição/transferência/baixa/exclusão/depreciação.
- Garantia depende de `dados_especificos.vencimento_garantia` (não há coluna).
- "Localização" aproximada via `setor`.

## 13. Como publicar no Napoleon (deploy do backend)

```bash
# no servidor da API (Napoleon)
cd /caminho/sas-estoque-laravel/backend
git pull
php artisan config:clear
php artisan route:clear
php artisan route:cache   # opcional
# sem migrations novas nesta entrega
```

## 14. Como atualizar a VPS (MCP)

1. Copie `docs/mcp/patrimonio-tools.mjs` para a VPS (ex.: `/opt/sas-estoque-mcp/patrimonio-tools.mjs`).
2. Em `/opt/sas-estoque-mcp/server.mjs`, importe e faça o spread no registro de ferramentas:

```js
import { patrimonioTools } from "./patrimonio-tools.mjs";

const cfg = { apiUrl: process.env.AYLA_API_URL, token: process.env.AYLA_SAS_TOKEN };

const tools = [
  ...kanbanTools(cfg),
  ...patrimonioTools(cfg),   // <-- adicionar
  // ...demais ferramentas
];
```

3. Garanta as variáveis de ambiente: `AYLA_API_URL`, `AYLA_SAS_TOKEN`.
4. Reinicie: `systemctl restart sas-estoque-mcp` (ou `openclaw gateway restart`).
5. Atualize a skill: copie `openclaw/skill-ayla/SKILL.md` para o workspace do OpenClaw.

## 15. Como testar pelo Telegram

Com o bridge/gateway ativo e o usuário autorizado:

1. Envie: "Ayla, faça um resumo do patrimônio."
2. Envie: "Ayla, quais equipamentos estão em manutenção?"
3. Envie: "Ayla, quais garantias vencem em breve?"

A Ayla chamará as ferramentas MCP `patrimonio_*` automaticamente.
