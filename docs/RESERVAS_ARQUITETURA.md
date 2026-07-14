# Arquitetura — Módulo Reservas de Mesas

## Diagrama lógico

```
┌─────────────────────────────┐
│  Frontend (SPA vanilla)     │
│  index.html + app.js        │
│  seções: reservaMesa,       │
│  historicoReservas          │
└──────────────┬──────────────┘
               │ HTTP + X-Usuario-Id
               ▼
┌─────────────────────────────┐
│  routes/api.php             │
│  /mesas , /reservas-mesas   │
└──────────────┬──────────────┘
               │
       ┌───────┴────────┐
       ▼                ▼
 MesaController   ReservaMesaController
       │                │
       │   ReservaMesaAcesso (menu)
       ▼                ▼
   Model Mesa      Model ReservaMesa
       │                │
       └────────┬───────┘
                ▼
         MySQL/MariaDB
         mesas / reservas_mesas
```

## Ayla (paralela, não altera controllers SAS)

```
Telegram/OpenClaw → MCP → /api/ayla/v1/reservas*
        → AylaController
        → AylaReservasService / AylaAcaoPendenteService
        → Models Mesa / ReservaMesa (mesmas tabelas)
```

Escrita Ayla **não** chama `ReservaMesaController`; replica regras no service (conflito, capacidade, status→mesa).

## Camadas que **não existem**

| Camada | Status |
|--------|--------|
| Service domain SAS | Ausente (lógica no controller) |
| Repository | Ausente |
| FormRequest / Resource | Ausente |
| Policy / Observer | Ausente |
| Job / Queue | Ausente |
| Seeder mesas/reservas | Ausente |

## Frontend

| Peça | Arquivo |
|------|---------|
| Menu “Reserva” | `index.html` |
| Tela operacional + hist. | `#reservaMesaSection`, `#historicoReservasSection` |
| Modais | `#reservaMesaModal`, `#reservaDetalhesModal`, picker JS |
| Estilo cards | `style.css` (`.mesa-card`, `.reservas-mesas-*`) |
| Lógica | `app.js` |

UI = **grade de cards**, não mapa espacial.

## Integrações auxiliares

| Integração | Como |
|------------|------|
| WhatsApp | Cliente abre `https://wa.me/{telefone}?text=...` (sem backend WhatsApp) |
| SAS IA legado | `SasIaReservaQuery` / tools `consultar_reservas_periodo`, `consultar_mesas_resumo` |
| Ayla | Ver `RESERVAS_AYLA.md` |

## Permissões

Chaves: `reservaMesa`, `historicoReservas`.  
Resolvidas em `ReservaMesaAcesso` + `permissoes_menu` do usuário (ou defaults de perfil).

## Pontos de acoplamento

1. Status da mesa sincronizado manualmente no PHP após criar/alterar/cancelar reserva.
2. Conflito em PHP, sem UNIQUE no banco → race condition possível sob concorrência alta.
3. Flags `pode_juntar`/`pode_separar` no schema sem consumo nas regras.
