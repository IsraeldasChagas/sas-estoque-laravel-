#!/usr/bin/env python3
"""Gera PDF de treinamento Reserva ↔ Fidelidade (UML + regras)."""
from pathlib import Path

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_JUSTIFY, TA_LEFT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import cm
from reportlab.platypus import (
    KeepTogether,
    PageBreak,
    Paragraph,
    Preformatted,
    SimpleDocTemplate,
    Spacer,
    Table,
    TableStyle,
)

OUT = Path(__file__).resolve().parent / "RESERVA_FIDELIDADE_INTERACAO.pdf"
GREEN = colors.HexColor("#14532d")
LIGHT = colors.HexColor("#ecfdf5")
BORDER = colors.HexColor("#86efac")


def styles():
    base = getSampleStyleSheet()
    return {
        "title": ParagraphStyle(
            "T", parent=base["Title"], fontSize=18, spaceAfter=6, textColor=GREEN, alignment=TA_CENTER
        ),
        "sub": ParagraphStyle(
            "Sub", parent=base["Normal"], fontSize=10, alignment=TA_CENTER, textColor=colors.HexColor("#334155"), spaceAfter=14
        ),
        "h1": ParagraphStyle(
            "H1", parent=base["Heading1"], fontSize=13, spaceBefore=12, spaceAfter=6, textColor=GREEN
        ),
        "h2": ParagraphStyle(
            "H2", parent=base["Heading2"], fontSize=11, spaceBefore=8, spaceAfter=4, textColor=colors.HexColor("#166534")
        ),
        "body": ParagraphStyle(
            "B", parent=base["Normal"], fontSize=9, leading=12, alignment=TA_JUSTIFY, spaceAfter=4
        ),
        "bullet": ParagraphStyle(
            "Bu", parent=base["Normal"], fontSize=9, leading=12, leftIndent=10, spaceAfter=2
        ),
        "mono": ParagraphStyle(
            "M", parent=base["Code"], fontName="Courier", fontSize=7.5, leading=9.5, spaceAfter=6
        ),
        "caption": ParagraphStyle(
            "C", parent=base["Normal"], fontSize=8, textColor=colors.HexColor("#64748b"), spaceAfter=8, alignment=TA_LEFT
        ),
        "callout": ParagraphStyle(
            "Call", parent=base["Normal"], fontSize=9, leading=12, alignment=TA_CENTER, textColor=GREEN
        ),
        "th": ParagraphStyle("TH", parent=base["Normal"], fontSize=8, leading=10, textColor=colors.white),
        "td": ParagraphStyle("TD", parent=base["Normal"], fontSize=8, leading=10),
    }


def p(text, style):
    return Paragraph(text.replace("\n", "<br/>"), style)


def table(headers, rows, col_widths, s):
    data = [[p(h, s["th"]) for h in headers]]
    for row in rows:
        data.append([p(c, s["td"]) for c in row])
    t = Table(data, colWidths=col_widths, repeatRows=1)
    t.setStyle(
        TableStyle(
            [
                ("BACKGROUND", (0, 0), (-1, 0), GREEN),
                ("BACKGROUND", (0, 1), (-1, -1), colors.white),
                ("ROWBACKGROUNDS", (0, 1), (-1, -1), [colors.white, LIGHT]),
                ("GRID", (0, 0), (-1, -1), 0.4, BORDER),
                ("VALIGN", (0, 0), (-1, -1), "TOP"),
                ("LEFTPADDING", (0, 0), (-1, -1), 4),
                ("RIGHTPADDING", (0, 0), (-1, -1), 4),
                ("TOPPADDING", (0, 0), (-1, -1), 3),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 3),
            ]
        )
    )
    return t


def callout_box(text, s):
    inner = p(text, s["callout"])
    t = Table([[inner]], colWidths=[16.5 * cm])
    t.setStyle(
        TableStyle(
            [
                ("BACKGROUND", (0, 0), (-1, -1), LIGHT),
                ("BOX", (0, 0), (-1, -1), 1, GREEN),
                ("LEFTPADDING", (0, 0), (-1, -1), 10),
                ("RIGHTPADDING", (0, 0), (-1, -1), 10),
                ("TOPPADDING", (0, 0), (-1, -1), 8),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 8),
            ]
        )
    )
    return t


def ascii_block(title, lines, s):
    body = Preformatted(lines.strip("\n"), s["mono"])
    return KeepTogether([p(f"<b>{title}</b>", s["h2"]), body, p("Diagrama em ASCII (espelha o UML / Mermaid do .md e .puml).", s["caption"])])


def build():
    s = styles()
    story = []

    story.append(p("Treinamento: Reserva ↔ Fidelidade", s["title"]))
    story.append(p("SAS Estoque · UML + regras de negócio · Rev. 2 (2026-07-20) — valor mínimo do selo", s["sub"]))
    story.append(
        callout_box(
            "No salão, a <b>Reserva</b> registra opt-in e conta paga; o <b>Fidelidade</b> guarda cartão e selos; "
            "a ponte é o <b>ReservaFidelidadeService</b>. O cliente ganha <b>1 selo</b> por reserva participante "
            "somente se o valor da conta for ≥ <b>Libera selo a partir de</b> (padrão R$ 100, configurável).",
            s,
        )
    )
    story.append(Spacer(1, 8))

    # Slide visão
    story.append(p("1. Visão geral dos módulos", s["h1"]))
    story.append(
        table(
            ["Módulo", "Papel", "Onde"],
            [
                ["Reserva", "Agenda mesa + conta paga no salão", "Painel Reserva (renderReservaFidelidade)"],
                ["Fidelidade", "Programa, cartão, ledger, recompensas", "Admin Fidelidade + tabelas fid_*"],
                ["Ponte", "Chamada síncrona (sem Event/Job)", "ReservaFidelidadeService"],
            ],
            [3.2 * cm, 6.5 * cm, 6.8 * cm],
            s,
        )
    )
    story.append(Spacer(1, 6))
    story.append(p("Regras de ouro", s["h2"]))
    for line in [
        "• Não há Events/Jobs Laravel entre Reserva e Fidelidade.",
        "• Delivery usa ponte paralela no mesmo cartão fid_contas — não misturar no fluxo do salão.",
        "• LGPD/OTP ficam na vitrine pública, não na tela do staff.",
        "• Chave do cartão: (unidade_id, telefone_normalizado).",
    ]:
        story.append(p(line, s["bullet"]))

    story.append(p("2. Atores", s["h1"]))
    story.append(
        table(
            ["Ator", "Responsabilidade"],
            [
                ["Cliente", "Opt-in verbal; dados; depois abre vitrine com OTP/LGPD"],
                ["Staff / salão", "Opt-in, dados, conta paga, resgate, WhatsApp"],
                ["Admin Fidelidade", "Programa (incl. Libera selo a partir de) e recompensas"],
                ["Sistema", "Idempotência do selo, identidade, vínculo loja↔unidade"],
            ],
            [3.5 * cm, 13 * cm],
            s,
        )
    )

    story.append(PageBreak())
    story.append(p("3. Casos de uso (UML)", s["h1"]))
    story.append(
        ascii_block(
            "Diagrama de casos de uso",
            """
                    +------------------+
 Cliente ---------> | UC6 Vitrine OTP  |
                    +------------------+
 Staff -----------> | UC1 Opt-in       |
 Staff -----------> | UC2 Identidade   |
 Staff -----------> | UC3 Conta paga   |···> UC8 Creditar selo
 Staff -----------> | UC4 Resgate      |     (se valor >= mínimo)
 Staff -----------> | UC5 Link vitrine |
 AdminFid --------> | UC7 Config prog. |···> define selo_valor_minimo
 Sistema ---------> | UC8 Selo         |
""",
            s,
        )
    )
    story.append(
        table(
            ["UC", "Nome", "Resultado"],
            [
                ["UC1", "Opt-in", "participa_fidelidade + dados na reserva"],
                ["UC2", "Identidade", "Nome + CPF + e-mail válidos"],
                ["UC3", "Conta paga", "Pagamentos; cartão; selo se valor ≥ mínimo"],
                ["UC4", "Resgate", "Débito ledger + resgate entregue"],
                ["UC5", "Link vitrine", "URL / WhatsApp"],
                ["UC6", "Consulta pública", "OTP + LGPD na vitrine"],
                ["UC7", "Config programa", "fid_programas.selo_valor_minimo + recompensas"],
                ["UC8", "Selo", "fid_ledger tipo=selo, chave reserva-{id}-selo"],
            ],
            [1.5 * cm, 3.5 * cm, 11.5 * cm],
            s,
        )
    )

    story.append(p("4. Roteiro operacional no salão", s["h1"]))
    for i, line in enumerate(
        [
            "Abrir a reserva (telefone preenchido).",
            "Marcar participa do fidelidade.",
            "Salvar nome completo, CPF e e-mail.",
            "Informar valor + formas de pagamento → Conta paga.",
            "Sistema cria/atualiza cartão; libera selo só se valor ≥ mínimo.",
            "(Opcional) Resgatar recompensa no painel.",
            "(Opcional) Enviar link WhatsApp da vitrine.",
        ],
        1,
    ):
        story.append(p(f"{i}. {line}", s["bullet"]))

    story.append(PageBreak())
    story.append(p("5. Regra de negócio: valor mínimo do selo", s["h1"]))
    story.append(
        table(
            ["Configuração", "Onde", "Padrão"],
            [
                ["Libera selo a partir de (R$)", "Fidelidade → Programa → selo_valor_minimo", "100,00"],
                ["Valor 0", "Mesmo campo", "Libera selo em qualquer valor"],
            ],
            [5 * cm, 7.5 * cm, 4 * cm],
            s,
        )
    )
    story.append(Spacer(1, 6))
    story.append(
        table(
            ["Situação", "Conta paga?", "Cartão?", "Selo?"],
            [
                ["Valor ≥ mínimo + opt-in", "Sim", "Sim", "Sim (+1)"],
                ["Valor &lt; mínimo + opt-in", "Sim", "Sim", "Não (+ motivo)"],
                ["Sem opt-in", "Sim", "Não", "Não"],
                ["Conta já paga (replay)", "Já estava", "—", "Não 2º selo"],
            ],
            [5.5 * cm, 3.5 * cm, 3.5 * cm, 4 * cm],
            s,
        )
    )
    story.append(Spacer(1, 4))
    story.append(p("Idempotência: chave <b>reserva-{id}-selo</b> — repetir a ação não gera segundo selo.", s["body"]))

    story.append(p("6. Sequência UML — conta paga", s["h1"]))
    story.append(
        ascii_block(
            "Sequência (conta paga + gate do mínimo)",
            """
Staff -> UI: opt-in + dados
UI -> API: PATCH .../fidelidade-dados
API -> Svc: salvarDadosFidelidade / validarCadastro

Staff -> UI: conta paga + pagamentos
UI -> API: POST .../fidelidade/conta-paga
API -> Svc: registrarContaPaga(valor)

  [participa?]
    Svc: garantirConta
    [valor >= selo_valor_minimo?]
      YES -> Ledger: +1 selo (reserva-{id}-selo)  => selo_liberado=true
      NO  -> conta paga + cartão, SEM selo       => selo_motivo

Svc -> DB: conta_paga, valor_conta, pagamentos
API -> UI: toast sucesso ou aviso
""",
            s,
        )
    )

    story.append(p("7. Diagrama de atividade — decisão do selo", s["h1"]))
    story.append(
        ascii_block(
            "Atividade",
            """
[Conta paga]
    |
    v
participa? --N--> grava conta sem cartão --> FIM
    |
    S
    v
programa+tel+ID ok? --N--> ERRO --> FIM
    |
    S
    v
garantirConta
    |
    v
valor >= mínimo? --S--> +1 selo --> FIM
    |
    N
    v
conta paga SEM selo (+ motivo) --> FIM
""",
            s,
        )
    )

    story.append(PageBreak())
    story.append(p("8. Classes (resumo)", s["h1"]))
    story.append(
        ascii_block(
            "Relacionamentos principais",
            """
ReservaMesaController
        |
        +--> ReservaFidelidadeService ----> ReservaMesa
        |              |
        |              +--> FidPrograma (selo_valor_minimo)
        |              +--> FidelidadeIdentidadeService
        |              +--> FidelidadeLedgerService --> fid_ledger
        |              +--> FidelidadeResgateService
        +--> FidelidadeVitrineLinkService
""",
            s,
        )
    )

    story.append(p("9. APIs Reserva → Fidelidade", s["h1"]))
    story.append(
        table(
            ["Método", "Rota", "Serviço"],
            [
                ["GET", "/reservas-mesas/{id}/fidelidade", "snapshot()"],
                ["PATCH", ".../participa-fidelidade", "opt-in"],
                ["PATCH", ".../fidelidade-dados", "salvarDadosFidelidade()"],
                ["POST", ".../fidelidade/conta-paga", "registrarContaPaga()"],
                ["POST", ".../fidelidade/resgatar", "pagarComSelos()"],
                ["POST", ".../fidelidade/selo", "creditarSelo() — API só"],
                ["POST", ".../fidelidade/garantir", "garantirConta() — API só"],
            ],
            [2.2 * cm, 7.5 * cm, 6.8 * cm],
            s,
        )
    )

    story.append(p("10. Edge cases (FAQ treinamento)", s["h1"]))
    story.append(
        table(
            ["Situação", "Comportamento"],
            [
                ["Conta R$ 80 com mínimo R$ 100", "Conta paga, cartão ok, SEM selo"],
                ["Conta R$ 100+", "+1 selo"],
                ["Telefone inválido / dados incompletos", "Bloqueia cartão/selo"],
                ["Conta já paga", "Replay; não 2º selo"],
                ["Mesmo telefone no Delivery", "Mesmo fid_contas; referencia_tipo diferente"],
                ["LGPD no salão", "Não; cliente aceita na vitrine"],
            ],
            [6 * cm, 10.5 * cm],
            s,
        )
    )

    story.append(p("11. Reserva vs Delivery", s["h1"]))
    story.append(
        table(
            ["", "Reserva (salão)", "Delivery"],
            [
                ["Ponte", "ReservaFidelidadeService", "DeliveryPedidoFidelidadeService"],
                ["Gatilho selo", "Conta paga + valor ≥ mínimo", "Checkout / sucesso do pedido"],
                ["LGPD no join", "Não", "Sim"],
                ["referencia_tipo", "reserva_mesa", "delivery_pedido"],
            ],
            [3.5 * cm, 6.5 * cm, 6.5 * cm],
            s,
        )
    )

    story.append(Spacer(1, 10))
    story.append(p("12. Arquivos do pacote de treinamento", s["h1"]))
    story.append(
        table(
            ["Arquivo", "Uso"],
            [
                ["RESERVA_FIDELIDADE_INTERACAO.md", "Doc completa + Mermaid (slides)"],
                ["RESERVA_FIDELIDADE_INTERACAO.pdf", "Este PDF (projeção / impressão)"],
                ["RESERVA_FIDELIDADE_UML.puml", "PlantUML: casos de uso, classes, sequências, atividade"],
            ],
            [7 * cm, 9.5 * cm],
            s,
        )
    )
    story.append(Spacer(1, 8))
    story.append(
        p(
            "Ponte principal: <b>backend/app/Services/Fidelidade/ReservaFidelidadeService.php</b> · "
            "Config do mínimo: <b>frontend/fidelidade.js</b> (Libera selo a partir de) · "
            "Migration: <b>2026_07_20_140000_add_selo_valor_minimo_to_fid_programas.php</b>.",
            s["body"],
        )
    )

    doc = SimpleDocTemplate(
        str(OUT),
        pagesize=A4,
        leftMargin=1.6 * cm,
        rightMargin=1.6 * cm,
        topMargin=1.4 * cm,
        bottomMargin=1.4 * cm,
        title="Treinamento Reserva ↔ Fidelidade",
        author="SAS Estoque",
    )
    doc.build(story)
    print(f"PDF gerado: {OUT}")


if __name__ == "__main__":
    build()
