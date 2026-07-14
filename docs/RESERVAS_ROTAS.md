# Rotas — Reservas de Mesas

## SAS (operacional) — `backend/routes/api.php`

CORS aplicado inline nas closures. Autenticação: header `X-Usuario-Id` (padrão do painel).

### Mesas

| Método | Path | Controller |
|--------|------|------------|
| GET | `/mesas` | `MesaController@index` |
| POST | `/mesas` | `MesaController@store` |
| GET | `/mesas/{id}` | `MesaController@show` |
| PUT | `/mesas/{id}` | `MesaController@update` |
| DELETE | `/mesas/{id}` | `MesaController@destroy` (inativa / cancela reservas ativas conforme regra) |

### Reservas

| Método | Path | Controller |
|--------|------|------------|
| GET | `/reservas-mesas` | `ReservaMesaController@index` |
| GET | `/reservas-mesas/resumo` | `ReservaMesaController@resumo` |
| GET | `/reservas-mesas/historico` | `ReservaMesaController@historico` |
| POST | `/reservas-mesas` | `ReservaMesaController@store` |
| GET | `/reservas-mesas/{id}` | `ReservaMesaController@show` |
| PUT | `/reservas-mesas/{id}` | `ReservaMesaController@update` |
| POST | `/reservas-mesas/{id}/cancelar` | `ReservaMesaController@cancelar` |
| PATCH | `/reservas-mesas/{id}/status` | `ReservaMesaController@alterarStatus` |

Não há `DELETE /reservas-mesas/{id}` registrado (método `destroy` no controller apenas aliases `cancelar`).

### Query params relevantes

**index reservas:** `unidade_id`, `data`, `status`, `turno` (hora base 4h), `busca`  
**resumo:** `unidade_id`, `data`  
**historico:** `unidade_id`, `data_inicio`, `data_fim`, `status`  
**index mesas:** `unidade_id` (lista ativos)

---

## Ayla — `backend/routes/ayla_routes.php`

Prefixo: `/api/ayla/v1` · Middleware: `ayla.token`

### Leitura

| Método | Path |
|--------|------|
| GET | `/reservas` |
| GET | `/reservas/resumo` |
| GET | `/reservas/disponibilidade` |
| GET | `/reservas/alertas` |
| GET | `/reservas/unidade/{id}` |
| GET | `/reservas/{id}` |

### Escrita controlada

| Método | Path | Efeito |
|--------|------|--------|
| POST | `/reservas/acoes/preparar` | Cria pendente (não grava reserva) |
| POST | `/reservas/acoes/{id}/confirmar` | Executa |
| POST | `/reservas/acoes/{id}/cancelar` | Cancela pendente |

### Escrita direta bloqueada

| Método | Path | Resposta |
|--------|------|----------|
| POST | `/reservas` | 403 `CONFIRMATION_REQUIRED` |
| PUT | `/reservas/{id}` | 403 |
| PATCH | `/reservas/{id}/status` | 403 |
| PATCH | `/reservas/{id}/mesa` | 403 |

---

## Frontend (consumo)

Arquivo único de lógica: `frontend/app.js` (módulo reservas ~`setupReservasMesasModule`).  
Chamada típica: `fetch`/`api` para os paths SAS acima (não chama Ayla no browser do painel).
