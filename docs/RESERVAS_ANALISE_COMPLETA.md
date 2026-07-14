# Análise completa — Módulo Reservas de Mesas (SAS-Estoque)

> Engenharia reversa. **Somente leitura.** Baseado no código real em
> `sas-estoque-laravel` (sem suposições de features inexistentes).
>
> Data do mapeamento: 2026-07-14.

---

## 1. Visão geral

O módulo operacional de reservas vive em:

| Camada | Onde |
|--------|------|
| Backend | `MesaController`, `ReservaMesaController`, models `Mesa`/`ReservaMesa`, suporte `ReservaMesaAcesso` |
| Rotas SAS | `backend/routes/api.php` (`/mesas`, `/reservas-mesas`) |
| Frontend | `frontend/index.html` + lógica em `frontend/app.js` + estilos em `frontend/style.css` |
| Banco | Tabelas `mesas` e `reservas_mesas` (sem FK formal; só índices) |
| Ayla | Leitura + escrita controlada (preparar → confirmar) em `/api/ayla/v1/reservas*` |

Não há: Repository, Policy, Observer, FormRequest, API Resource, Job, Seeder de mesas/reservas, View SQL, Trigger.

---

## 2. Cadastro de mesas

### Campos reais (`mesas`)

| Campo | Tipo | Obrigatório | Observação |
|-------|------|-------------|------------|
| `id` | PK | sim | |
| `unidade_id` | unsignedBigInteger | sim | Index; unique com `numero_mesa` |
| `numero_mesa` | string(50) | sim | Único por unidade |
| `nome_mesa` | string nullable | não | |
| `capacidade` | unsignedInteger default 4 | sim na API | = “lugares / cadeiras” agregados |
| `localizacao` | string nullable | não | Texto livre (não XY) |
| `pode_juntar` | boolean default false | não | **Persistido, não usado na regra de negócio** |
| `pode_separar` | boolean default false | não | **Persistido, não usado na regra de negócio** |
| `status` | string(30) default `livre` | sim | Ver §10 |
| `observacao` | text nullable | não | |
| `ativo` | boolean default true | sim | Soft-delete via inativação |
| `created_at` / `updated_at` | timestamps | | |

### O que **não** existe no cadastro de mesa

- Setor, cor, posição X/Y, ordem visual, quantidade fixa de cadeiras separada da capacidade, cadeira individual, capacidade “máxima” ≠ capacidade (só existe `capacidade`).

### Como a UI cria mesa hoje

Frontend: `prompt()` para número + capacidade → `POST /mesas` com `unidade_id`.
Não expõe formulário completo de `nome_mesa`, `localizacao`, `pode_juntar`, etc. (API aceita, UI não oferece todos).

---

## 3. Cadeiras

| Pergunta | Resposta (código) |
|----------|-------------------|
| Cadastro individual de cadeira? | **NÃO** |
| Só quantidade? | **SIM** — campo `mesas.capacidade` |
| Cadeira extra? | **NÃO** |
| Capacidade máxima aparte? | **NÃO** — um único inteiro |
| Limite global? | Validação `qtd_pessoas` 1–99 e `<= mesa.capacidade` |
| Bloqueio de mesa? | **SIM** — status mesa `bloqueada` impede nova reserva |

---

## 4. Reserva — campos reais (`reservas_mesas`)

| Campo | Existe? | Detalhe |
|-------|---------|---------|
| Cliente (`nome_cliente`) | SIM | required |
| Telefone (`telefone_cliente`) | SIM | nullable, max 30 |
| WhatsApp | Parcial | Sem coluna; frontend abre `wa.me` com o telefone |
| Observações (`observacao`) | SIM | max 500 na validação |
| Quantidade pessoas (`qtd_pessoas`) | SIM | 1–99; default 1 |
| Mesa (`mesa_id`) | SIM | 1 mesa por reserva |
| Unidade (`unidade_id`) | SIM | |
| Status | SIM | ver fluxo |
| Data (`data_reserva`) | SIM | date; create `after_or_equal:today` |
| Hora (`hora_reserva`) | SIM | time; validado `H:i` |
| Duração / hora fim | **NÃO** | |
| Responsável | Parcial | `usuario_id` (quem cadastrou); sem nome de “garçom” |
| Origem / canal | **NÃO** | |
| Confirmação (timestamp) | **NÃO** | só muda `status` → `confirmada` |
| Check-in | Parcial | status `cliente_chegou` |
| Check-out | Parcial | status `finalizada` |
| Cancelamento | Parcial | status `cancelada` (sem `motivo` dedicado no SAS; Ayla pode anexar motivo em `observacao`) |
| Local (`local`) | SIM | string 100 |
| Ocasião (`ocasiao`) | SIM | string 255 |
| `data_hora` composta | **NÃO** | |

---

## 5. Fluxo de status (reserva)

Estados reais: `pendente` → `confirmada` → `cliente_chegou` → `finalizada`  
Também: `cancelada`, `no_show`.

| Ação SAS | Endpoint | Efeito |
|----------|----------|--------|
| Criar | `POST /reservas-mesas` | Default `pendente`; mesa → `reservada` |
| Editar | `PUT /reservas-mesas/{id}` | Bloqueado se `cancelada|finalizada|no_show` |
| Cancelar | `POST /reservas-mesas/{id}/cancelar` | status `cancelada`; mesa `livre` se sem outras ativas no dia |
| Confirmar / Chegada / Finalizar / No-show | `PATCH /reservas-mesas/{id}/status` | Qualquer status do enum (sem máquina de estados rígida) |
| Exclusão física | **NÃO** | `destroy` só chama `cancelar`; rota DELETE não registrada para reservas |

Efeitos na mesa (`alterarStatus`):

- `cancelada|no_show|finalizada` + sem outras ativas no dia → mesa `livre`
- `cliente_chegou` → mesa `aguardando_cliente`
- `pendente|confirmada` → mesa `reservada`
- Nunca seta mesa `ocupada` automaticamente

---

## 6. Disponibilidade / conflito

Regra **única** no `ReservaMesaController` (e espelhada na Ayla):

> Conflito se existir outra reserva com mesmo `mesa_id` + mesma `data_reserva` + mesma `hora_reserva` (exata) e status **fora** de `cancelada|no_show|finalizada`.

- Sem intervalo/duração/buffer.
- Mesa inativa ou `bloqueada` → não cria.
- Resumo “livres”: total mesas ativas − mesas com **qualquer** reserva ativa na **data** (não por horário).

---

## 7. Capacidade

- Fonte: `mesas.capacidade`.
- Validação: `qtd_pessoas <= capacidade` no store/update.
- Frontend limita o input max pela mesa selecionada.

---

## 8. Juntar mesas

| Capacidade | Resposta |
|------------|----------|
| Uma reserva ocupar duas mesas? | **NÃO** (1 `mesa_id` por linha) |
| Três mesas? | **NÃO** |
| Mesa composta / temporária? | **NÃO** |
| Flags `pode_juntar` / `pode_separar`? | Existem no banco; **não entram na regra** |

Frontend “Juntar mesa” = **mover** a reserva para outra mesa livre (PUT `mesa_id`).  
Frontend “Separar mesa” = cria **segunda reserva** + reduz `qtd_pessoas` da primeira (dois registros).

---

## 9. Adicionar cadeiras

**NÃO há** suporte a adicionar cadeiras em runtime. Só alterar `capacidade` da mesa (`PUT /mesas/{id}`) — operação de cadastro de mesa, não de reserva.

---

## 10. Mapa do salão

| Item | Resposta |
|------|----------|
| Existe mapa espacial? | **NÃO** |
| O que existe? | Grade de **cards** (`.mesa-card`) |
| Posição X,Y? | **NÃO** |
| Drag and drop? | **NÃO** |
| Automático/estático? | Render dinâmico a partir de `GET /mesas` + reservas do dia |

---

## 11. Situação das mesas (status)

`livre` | `reservada` | `aguardando_cliente` | `ocupada` | `bloqueada`

Não existem status: manutenção, limpeza (como enum dedicado).

---

## 12. Dashboard / indicadores

Tela operacional `#reservaMesaSection` + `GET /reservas-mesas/resumo`:

- Contagens do dia (totais por status, livres, etc. — implementado no controller `resumo`)
- Filtros: unidade, data, turno (faixa de hora no index), status
- Grid de mesas + tabela do dia

Não é um dashboard analítico separado com BI.

---

## 13. Relatórios

- **Histórico** (`GET /reservas-mesas/historico`) com contagem por cliente (telefone ou nome).
- Sem módulo de relatório PDF/Excel dedicado a reservas no código analisado.

---

## 14. Histórico

SIM — seção `historicoReservas`. Grava/ lê as mesmas linhas de `reservas_mesas` (não há tabela de histórico separado). Retorna também `total_reservas_cliente`.

---

## 15. Auditoria

- Módulo SAS reservas: **sem** tabela própria de auditoria de mudanças.
- Ayla: `ayla_audit_logs` nas chamadas da API Ayla; ações pendentes em `ayla_acoes_pendentes`.

---

## 16. Permissões

Chaves de menu: **`reservaMesa`**, **`historicoReservas`**.

Perfis com acesso padrão (quando `permissoes_menu` vazio) em `ReservaMesaAcesso`: ADMIN, GERENTE, BAR, FINANCEIRO, ASSISTENTE_ADMINISTRATIVO, ATENDENTE, ATENDENTE_CAIXA.

Escrita Ayla: + `pode_executar_acoes` + `ayla_read_only = false`.

---

## 17. Matriz de capacidades atuais

| Capacidade | Hoje |
|------------|------|
| reservar | ☑ |
| cancelar | ☑ |
| editar | ☑ |
| confirmar | ☑ |
| mudar mesa | ☑ |
| mudar horário | ☑ |
| adicionar cadeira | ☐ |
| juntar mesas (1 reserva N mesas) | ☐ |
| dividir mesa (UI “separar” = 2 reservas) | parcial ☑ |
| controlar ocupação | parcial ☑ |
| calcular capacidade | ☑ |
| sugerir mesa | ☑ (Ayla `disponibilidade`; UI não sugere automaticamente) |
| sugerir combinação | ☐ |
| criar mesa | ☑ |
| bloquear mesa | ☑ (status) |
| liberar mesa | ☑ (via status/finalizar/cancelar) |
| agenda diária | ☑ |
| agenda semanal | ☐ |
| mapa visual XY | ☐ |
| fila / lista de espera | ☐ |
| QR Code reservas | ☐ |
| WhatsApp | parcial (link `wa.me`, sem API) |
| Ayla | ☑ leitura + escrita controlada |
| Telegram via Ayla | ☑ (gateway + skill; não no controller SAS) |

---

## Documentos relacionados

- `RESERVAS_BANCO.md`
- `RESERVAS_ROTAS.md`
- `RESERVAS_ARQUITETURA.md`
- `RESERVAS_FLUXO.md`
- `RESERVAS_AYLA.md`
- `RESERVAS_MATRIZ.csv`
- `RESERVAS_DIAGRAMA.puml`
