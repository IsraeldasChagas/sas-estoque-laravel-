# Fluxo operacional — Reservas de Mesas

Base: `ReservaMesaController` + frontend `app.js`.

## Criar reserva

1. Usuário escolhe unidade/data na tela e abre modal.
2. Preenche: mesa, cliente, telefone (opcional), data, hora, qtd, local, ocasião, status (`pendente|confirmada` no form), obs.
3. `POST /reservas-mesas`.
4. Backend valida: unidade, mesa ativa, não bloqueada, capacidade, data ≥ hoje, hora `H:i`, conflito exato.
5. Insere reserva; seta mesa `reservada`.
6. UI pode oferecer diálogo WhatsApp de confirmação.

## Editar

1. Modal edição ou ações.
2. `PUT /reservas-mesas/{id}`.
3. Recusa se status ∈ `{cancelada, finalizada, no_show}`.
4. Se mudar mesa/data/hora → revalida capacidade e conflito (exceto self).
5. Mesa antiga pode voltar a `livre` se não houver outras ativas na data.

## Cancelar

1. `POST /reservas-mesas/{id}/cancelar` (ou ação UI).
2. Status → `cancelada`.
3. Mesa → `livre` se nenhuma outra ativa no mesmo dia na mesa.

## Confirmar

1. `PATCH .../status` com `{ "status": "confirmada" }`.
2. Mesa → `reservada`.

## Cliente chegou

1. `PATCH` com `cliente_chegou`.
2. Mesa → `aguardando_cliente`.

## Finalizar (liberar)

1. `PATCH` com `finalizada` (UI “liberar mesa”).
2. Mesa → `livre` se sem outras ativas no dia.

## No-show

1. `PATCH` com `no_show`.
2. Mesa → `livre` (mesma regra de finais).

## Juntar (UI)

1. Picker de mesas livres.
2. `PUT` troca `mesa_id` da **mesma** reserva.
3. Não cria composição multi-mesa.

## Separar (UI)

1. Divide pessoas: reduz `qtd_pessoas` na reserva A + `POST` reserva B em outra mesa.
2. Duas linhas independentes.

## Histórico

1. Filtros data início/fim + status.
2. `GET /reservas-mesas/historico`.
3. Lista reservas + `total_reservas_cliente` (agrupa por telefone ou nome).

## Turno (filtro listagem)

Frontend envia `turno` (ex.: 11 almoço, 17 jantar).  
Backend: `HOUR(hora_reserva) >= turno AND < turno+4`.

## Máquina de estados

Não há transição bloqueada no backend além de “não editar cancelada/finalizada/no_show”.  
Qualquer status do enum pode ser setado via `alterarStatus` se o usuário tiver acesso.
