# Banco de dados — Reservas de Mesas

Fonte: migrations em `backend/database/migrations/`.

## Tabelas

| Tabela | Migration |
|--------|-----------|
| `mesas` | `2026_03_11_000001_create_mesas_table.php` + `2026_03_12_000001_add_pode_juntar_separar_to_mesas.php` |
| `reservas_mesas` | `2026_03_11_000002_create_reservas_mesas_table.php` + `2026_03_17_000001_add_local_ocasiao_to_reservas_mesas.php` |
| `ayla_acoes_pendentes` | `2026_07_14_100001_create_ayla_acoes_pendentes_table.php` (confirmação Ayla; não é o módulo SAS) |

## `mesas`

| Coluna | Tipo | Default | Null |
|--------|------|---------|------|
| id | bigint PK | | |
| unidade_id | unsignedBigInteger | | N |
| numero_mesa | string(50) | | N |
| nome_mesa | string | | Y |
| capacidade | unsignedInteger | 4 | N |
| localizacao | string | | Y |
| pode_juntar | boolean | false | N |
| pode_separar | boolean | false | N |
| status | string(30) | livre | N |
| observacao | text | | Y |
| ativo | boolean | true | N |
| created_at, updated_at | timestamps | | Y |

### Índices
- UNIQUE `(unidade_id, numero_mesa)`
- INDEX `unidade_id`, `status`, `ativo`

### Foreign Keys
**Nenhuma** (`foreign()` não declarado). Integridade apenas lógica no código.

### Triggers / Views
**Nenhum** no código do projeto.

---

## `reservas_mesas`

| Coluna | Tipo | Default | Null |
|--------|------|---------|------|
| id | bigint PK | | |
| unidade_id | unsignedBigInteger | | N |
| mesa_id | unsignedBigInteger | | N |
| usuario_id | unsignedBigInteger | | Y |
| nome_cliente | string | | N |
| telefone_cliente | string(30) | | Y |
| data_reserva | date | | N |
| hora_reserva | time | | N |
| qtd_pessoas | unsignedInteger | 1 | N |
| status | string(30) | pendente | N |
| observacao | text | | Y |
| local | string(100) | | Y |
| ocasiao | string(255) | | Y |
| created_at, updated_at | timestamps | | Y |

### Índices
- INDEX `unidade_id`, `mesa_id`, `usuario_id`, `status`
- INDEX composto `(data_reserva, unidade_id)`

### Foreign Keys
**Nenhuma.**

### Relacionamentos lógicos (Eloquent)
```
unidades 1 ─── N mesas
unidades 1 ─── N reservas_mesas
mesas    1 ─── N reservas_mesas
usuarios 1 ─── N reservas_mesas (usuario_id nullable)
```

Cada reserva aponta para **exatamente uma** mesa.

---

## Status permitidos (domínio, string livre no banco)

**Mesa:** `livre`, `reservada`, `aguardando_cliente`, `ocupada`, `bloqueada`  
**Reserva:** `pendente`, `confirmada`, `cancelada`, `cliente_chegou`, `no_show`, `finalizada`

Não há CHECK constraint SQL; enforce via validação PHP.

---

## O que não existe no schema

- `cadeiras`, `mesa_composicao`, `reservas_mesas_mesas` (N:N)
- `duracao_minutos`, `hora_fim`, `data_hora`
- `motivo_cancelamento`, `confirmado_em`, `checkin_em`
- `posicao_x`, `posicao_y`, `cor`, `ordem`
- `fila_espera`, `lista_espera`
- tabela de auditoria específica de reservas

---

## Conflito (sem unique DB)

Não há UNIQUE `(mesa_id, data_reserva, hora_reserva)`. Conflito é checado em PHP no controller.
