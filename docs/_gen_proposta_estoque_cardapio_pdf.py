#!/usr/bin/env python3
"""Gera PDF da proposta Estoque Admin + Estoque do Cardápio."""
from pathlib import Path

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_JUSTIFY, TA_LEFT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import cm
from reportlab.platypus import (
    PageBreak,
    Paragraph,
    Preformatted,
    SimpleDocTemplate,
    Spacer,
    Table,
    TableStyle,
)

OUT = Path(__file__).resolve().parent / "PROPOSTA_ESTOQUE_CARDAPIO_2026-08-09.pdf"
GREEN = colors.HexColor("#14532d")
LIGHT = colors.HexColor("#ecfdf5")
BORDER = colors.HexColor("#86efac")
AMBER = colors.HexColor("#92400e")
AMBER_BG = colors.HexColor("#fffbeb")


def styles():
    base = getSampleStyleSheet()
    return {
        "title": ParagraphStyle(
            "T", parent=base["Title"], fontSize=16, spaceAfter=4, textColor=GREEN, alignment=TA_CENTER
        ),
        "sub": ParagraphStyle(
            "Sub",
            parent=base["Normal"],
            fontSize=9,
            alignment=TA_CENTER,
            textColor=colors.HexColor("#334155"),
            spaceAfter=12,
        ),
        "h1": ParagraphStyle(
            "H1", parent=base["Heading1"], fontSize=12, spaceBefore=10, spaceAfter=5, textColor=GREEN
        ),
        "h2": ParagraphStyle(
            "H2",
            parent=base["Heading2"],
            fontSize=10,
            spaceBefore=7,
            spaceAfter=3,
            textColor=colors.HexColor("#166534"),
        ),
        "body": ParagraphStyle(
            "B", parent=base["Normal"], fontSize=8.5, leading=11, alignment=TA_JUSTIFY, spaceAfter=3
        ),
        "bullet": ParagraphStyle(
            "Bu", parent=base["Normal"], fontSize=8.5, leading=11, leftIndent=10, spaceAfter=1.5
        ),
        "mono": ParagraphStyle(
            "M", parent=base["Code"], fontName="Courier", fontSize=7, leading=9, spaceAfter=5
        ),
        "caption": ParagraphStyle(
            "C",
            parent=base["Normal"],
            fontSize=7.5,
            textColor=colors.HexColor("#64748b"),
            spaceAfter=6,
            alignment=TA_LEFT,
        ),
        "callout": ParagraphStyle(
            "Call", parent=base["Normal"], fontSize=8.5, leading=11, alignment=TA_CENTER, textColor=GREEN
        ),
        "th": ParagraphStyle("TH", parent=base["Normal"], fontSize=7.5, leading=9.5, textColor=colors.white),
        "td": ParagraphStyle("TD", parent=base["Normal"], fontSize=7.5, leading=9.5),
    }


def p(text, style):
    return Paragraph(str(text).replace("\n", "<br/>"), style)


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
                ("LEFTPADDING", (0, 0), (-1, -1), 3),
                ("RIGHTPADDING", (0, 0), (-1, -1), 3),
                ("TOPPADDING", (0, 0), (-1, -1), 3),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 3),
            ]
        )
    )
    return t


def callout_box(text, s):
    t = Table([[p(text, s["callout"])]], colWidths=[17 * cm])
    t.setStyle(
        TableStyle(
            [
                ("BACKGROUND", (0, 0), (-1, -1), LIGHT),
                ("BOX", (0, 0), (-1, -1), 1, BORDER),
                ("LEFTPADDING", (0, 0), (-1, -1), 8),
                ("RIGHTPADDING", (0, 0), (-1, -1), 8),
                ("TOPPADDING", (0, 0), (-1, -1), 6),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 6),
            ]
        )
    )
    return t


def warn_box(text, s):
    style = ParagraphStyle("W", parent=s["callout"], textColor=AMBER)
    t = Table([[p(text, style)]], colWidths=[17 * cm])
    t.setStyle(
        TableStyle(
            [
                ("BACKGROUND", (0, 0), (-1, -1), AMBER_BG),
                ("BOX", (0, 0), (-1, -1), 1, colors.HexColor("#fcd34d")),
                ("LEFTPADDING", (0, 0), (-1, -1), 8),
                ("RIGHTPADDING", (0, 0), (-1, -1), 8),
                ("TOPPADDING", (0, 0), (-1, -1), 6),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 6),
            ]
        )
    )
    return t


def build():
    s = styles()
    story = []

    story.append(p("Proposta: Estoque Administrativo + Estoque do Cardápio", s["title"]))
    story.append(p("SAS Estoque — Grupo Sabor Paraense · 2026-08-09", s["sub"]))
    story.append(
        callout_box(
            "<b>Decisão:</b> manter o estoque administrativo como está e criar um "
            "<b>segundo estoque (Cardápio)</b>. Toda venda (PDV, mesa, delivery) dá baixa "
            "nesse estoque e registra movimentação.",
            s,
        )
    )
    story.append(Spacer(1, 0.3 * cm))

    # 1
    story.append(p("1. Resumo executivo", s["h1"]))
    story.append(
        p(
            "O estoque principal (produtos + stock_lotes + movimentacoes) foi pensado para "
            "<b>administração</b>: compras, lotes, produção, CMV, transferências e perdas. "
            "O cardápio (dlv_produtos) já serve Delivery, PDV e mesas, mas a venda <b>não "
            "controla bem o estoque comercial</b> — especialmente pratos e delivery.",
            s["body"],
        )
    )
    story.append(
        table(
            ["Situação", "Baixa de estoque hoje?"],
            [
                ["Revenda com estoque_produto_id", "Sim — FIFO no produto administrativo"],
                ["Prato com ficha técnica", "Não — só aviso; não explode ingredientes"],
                ["Pedido Delivery", "Não — contador cosmético dlv_produtos.estoque"],
                ["Produção (ordem)", "Sim — baixa insumos e entra produto final"],
            ],
            [9 * cm, 8 * cm],
            s,
        )
    )
    story.append(Spacer(1, 0.25 * cm))

    # 2
    story.append(p("2. Problema atual", s["h1"]))
    story.append(p("2.1 Dois mundos pouco conectados", s["h2"]))
    story.append(
        Preformatted(
            "ESTOQUE ADMIN (já existe)          CARDÁPIO (já existe)\n"
            "produtos / lotes / movimentacoes   dlv_produtos / preço / tipo_venda\n"
            "entrada / saída / produção         estoque_produto_id? ficha?\n"
            "\n"
            "venda PDV → tenta baixar só produto_id resolvido\n"
            "prato sem produto final → trava ou não controla insumos\n"
            "delivery → quase não mexe no estoque real",
            s["mono"],
        )
    )
    story.append(p("2.2 Lacunas que atrapalham a venda", s["h2"]))
    for b in [
        "• Prato pode ter só ficha → produto_id = 0 → venda rejeita ou não baixa BOM.",
        "• Baixa na venda não lê a ficha técnica (não consome insumos no ato).",
        "• Delivery usa campo estoque isolado, sem FIFO e sem movimentacoes.",
        "• Comanda não reserva saldo — só baixa no fechar conta.",
        "• Mistura mental de “insumo de cozinha” com “item à venda no cardápio”.",
    ]:
        story.append(p(b, s["bullet"]))
    story.append(p("2.3 O que preservar", s["h2"]))
    story.append(
        p(
            "Entradas, saídas, lotes, validade, produção com ficha, venda fiscal de revenda, "
            "CMV e multi-unidade. <b>Regra de ouro:</b> não refatorar o estoque admin — "
            "acrescentar o estoque comercial ao lado.",
            s["body"],
        )
    )

    # 3
    story.append(p("3. Solução: dois estoques", s["h1"]))
    story.append(p("3.1 Estoque A — Administrativo (inalterado)", s["h2"]))
    story.append(
        table(
            ["Aspecto", "Descrição"],
            [
                ["Para quem", "Compras, cozinha, fiscal, CMV, produção"],
                ["Tabelas", "produtos, stock_lotes, lotes, movimentacoes"],
                ["Movimentos", "ENTRADA compra, SAIDA produção/consumo/perda/transf."],
                ["UI", "Consulta Estoque, Lotes, Entrada/Saída, Fiscal"],
            ],
            [4 * cm, 13 * cm],
            s,
        )
    )
    story.append(Spacer(1, 0.15 * cm))
    story.append(p("3.2 Estoque B — Cardápio (novo)", s["h2"]))
    story.append(
        table(
            ["Aspecto", "Descrição"],
            [
                ["Para quem", "Venda no PDV, mesas, delivery"],
                ["Unidade de controle", "Cada item do cardápio (dlv_produtos)"],
                ["Saldo", "Quantidade disponível para vender (porções/unidades)"],
                ["Movimentos", "ENTRADA, SAIDA (venda), AJUSTE, ESTORNO"],
                ["UI", "Nova tela Estoque do Cardápio + indicadores no PDV"],
            ],
            [4 * cm, 13 * cm],
            s,
        )
    )
    story.append(Spacer(1, 0.15 * cm))
    story.append(p("3.3 Como os dois conversam", s["h2"]))
    story.append(
        Preformatted(
            "[Compras/Insumos] → ESTOQUE A (Admin)\n"
            "                         │ produção / abastecimento\n"
            "                         ▼\n"
            "                   ESTOQUE B (Cardápio) → venda PDV/mesa/delivery\n"
            "                         │\n"
            "                         ▼\n"
            "              movimentações cardápio + venda fiscal",
            s["mono"],
        )
    )
    story.append(
        table(
            ["Modo", "Quando usar", "Na venda"],
            [
                [
                    "M1 — Porção pronta",
                    "Prato produzido e liberado no cardápio",
                    "Baixa 1 porção no B (A já baixou na produção)",
                ],
                [
                    "M2 — Explosão ficha",
                    "Venda direta sem estoque intermediário",
                    "Baixa insumos no A + saída no B (fase 2)",
                ],
                [
                    "M3 — Revenda",
                    "Bebida, embalado",
                    "Baixa B e espelha no produto admin (como hoje)",
                ],
            ],
            [4 * cm, 6.5 * cm, 6.5 * cm],
            s,
        )
    )
    story.append(Spacer(1, 0.1 * cm))
    story.append(
        warn_box(
            "<b>Recomendação inicial (Fase 1):</b> modos M1 + M3 — alinhados ao que o "
            "sistema já faz bem. Explosão de ficha na venda (M2) fica para depois.",
            s,
        )
    )

    story.append(PageBreak())

    # 4
    story.append(p("4. Modelo de dados sugerido", s["h1"]))
    story.append(p("4.1 Tabela cardapio_estoque_saldos", s["h2"]))
    story.append(
        table(
            ["Campo", "Nota"],
            [
                ["unidade_id + dlv_produto_id", "Unique — saldo por loja/item"],
                ["quantidade", "Saldo disponível para venda"],
                ["estoque_minimo", "Alerta no PDV"],
            ],
            [7 * cm, 10 * cm],
            s,
        )
    )
    story.append(Spacer(1, 0.15 * cm))
    story.append(p("4.2 Tabela cardapio_estoque_movimentacoes", s["h2"]))
    story.append(
        table(
            ["Campo", "Nota"],
            [
                ["tipo", "ENTRADA | SAIDA | AJUSTE | ESTORNO"],
                ["origem", "PRODUCAO, ABASTECIMENTO, VENDA_PDV, VENDA_MESA, VENDA_DELIVERY, MANUAL, CANCELAMENTO"],
                ["quantidade / saldo_apos", "Auditoria do saldo"],
                ["venda_id / comanda_id / dlv_pedido_id / producao_id", "Rastreio da origem"],
            ],
            [7 * cm, 10 * cm],
            s,
        )
    )
    story.append(Spacer(1, 0.15 * cm))
    story.append(p("4.3 Ajustes mínimos no existente", s["h2"]))
    for b in [
        "• dlv_produtos.controla_estoque_cardapio (bool) — itens sem controle não baixam.",
        "• venda_itens.cardapio_movimentacao_id — rastreio.",
        "• dlv_pedidos.estoque_baixado_em — passar a ser preenchido de verdade.",
        "• Não duplicar stock_lotes; não mudar FIFO/FEFO do Estoque A.",
    ]:
        story.append(p(b, s["bullet"]))

    # 5
    story.append(p("5. Regras de negócio", s["h1"]))
    story.append(p("5.1 Entrada (abastecimento do B)", s["h2"]))
    for b in [
        "• Produção finalizada → entra N porções no cardápio da unidade.",
        "• Abastecimento manual → “hoje temos 40 marmitas de X”.",
        "• Liberar para venda a partir do produto final no Estoque A.",
    ]:
        story.append(p(b, s["bullet"]))
    story.append(p("5.2 Saída (venda)", s["h2"]))
    for b in [
        "• Resolver cardapio_produto_id → se controla estoque, validar saldo ≥ qtd.",
        "• Baixar cardapio_estoque_saldos + registrar SAIDA com origem do canal.",
        "• Revenda (M3): manter baixa FIFO no Estoque A.",
        "• Prato M1: só baixa B. Prato M2 (fase 2): explode ficha no A + saída B.",
    ]:
        story.append(p(b, s["bullet"]))
    story.append(p("5.3 Estorno e comanda", s["h2"]))
    story.append(
        p(
            "Cancelamento gera ESTORNO no B (e restaura A se houver). Na Fase 1: "
            "validar saldo no lançamento da comanda e baixar no finalize — sem tabela "
            "de reserva ainda.",
            s["body"],
        )
    )

    # 6
    story.append(p("6. Fluxo do dia a dia (prato)", s["h1"]))
    story.append(
        Preformatted(
            "1. Manhã: compras / estoque admin (A) — como hoje\n"
            "2. Cozinha: produção → baixa insumos (A) → entra produto final (A)\n"
            "3. Liberação: enviar X porções ao cardápio → ENTRADA no B\n"
            "4. Cliente pede no PDV/mesa/delivery → SAIDA no B\n"
            "5. Relatório: movimentações cardápio + CMV admin",
            s["mono"],
        )
    )

    # 7
    story.append(p("7. Telas e APIs", s["h1"]))
    story.append(
        table(
            ["Tela / API", "Função"],
            [
                ["Estoque do Cardápio", "Saldo por unidade, mínimo, status"],
                ["Abastecer cardápio", "Entrada manual ou via produção"],
                ["Movimentações cardápio", "Histórico filtrável"],
                ["PDV / Mesas / Delivery", "Badge sem estoque / bloqueio saldo 0"],
                ["GET/POST /api/cardapio-estoque/*", "Consulta, entrada, ajuste, histórico"],
                ["Vendas PDV / comanda / delivery", "Passam a baixar o Estoque B"],
            ],
            [6 * cm, 11 * cm],
            s,
        )
    )
    story.append(Spacer(1, 0.15 * cm))
    story.append(p("Pontos de código a alterar", s["h2"]))
    story.append(
        p(
            "VendaFiscalSupport, CardapioComercialSupport, PdvComercialSupport, "
            "DeliveryPedidoService, ProducaoFiscalSupport (opcional), frontend PDV/delivery, "
            "novo CardapioEstoqueSupport.",
            s["body"],
        )
    )

    story.append(PageBreak())

    # 8
    story.append(p("8. Plano de implementação", s["h1"]))
    story.append(
        table(
            ["Fase", "Entrega"],
            [
                ["0", "Este documento — acordo de negócio (M1+M3)"],
                [
                    "1 — MVP",
                    "Tabelas + CardapioEstoqueSupport + tela + baixa PDV/mesa + bloqueio saldo 0",
                ],
                ["1.5", "Produção → entrada automática no Estoque B"],
                ["2", "Delivery real + estornos + reserva em comanda (opcional)"],
                ["3", "Explosão de ficha na venda (M2), se necessário"],
                ["4", "Dashboard vendido × produzido × perdido + conciliação A×B"],
            ],
            [3 * cm, 14 * cm],
            s,
        )
    )

    # 9
    story.append(p("9. Antes × depois", s["h1"]))
    story.append(
        table(
            ["Canal", "Antes", "Depois (Fase 1)"],
            [
                ["PDV revenda", "Baixa A", "Baixa A + B"],
                ["PDV prato", "Frágil / sem BOM", "Baixa B (porção abastecida)"],
                ["Mesa", "Igual PDV no finalize", "Igual + validação saldo B"],
                ["Delivery", "Contador fake", "Baixa B real + histórico"],
                ["Admin compras/lotes", "OK", "Inalterado"],
                ["Produção", "Baixa insumos A", "A igual + opcional entrada B"],
            ],
            [3.5 * cm, 5.5 * cm, 8 * cm],
            s,
        )
    )

    # 10
    story.append(p("10. Riscos e cuidados", s["h1"]))
    story.append(
        table(
            ["Risco", "Mitigação"],
            [
                ["Duplicar baixa A e B", "Regras claras M1/M2/M3 por tipo_venda"],
                ["Esquecer abastecer B", "Fase 1.5 automática + alerta no PDV"],
                ["Dados antigos", "Inventário rápido ou zerar e abastecer do zero"],
                ["Confusão do usuário", "UI: Estoque (Admin) vs Estoque do Cardápio"],
            ],
            [5 * cm, 12 * cm],
            s,
        )
    )

    # 11-12
    story.append(p("11. Critérios de sucesso", s["h1"]))
    for b in [
        "• Vender no PDV e ver saldo B cair; movimentação com origem VENDA_PDV/MESA.",
        "• Estoque admin de insumos continua igual em compras/produção.",
        "• Saldo 0 não vende (ou avisa, conforme config).",
        "• Delivery usa o mesmo Estoque B (fim do contador isolado).",
        "• Relatório do dia: entradas × saídas × saldo.",
    ]:
        story.append(p(b, s["bullet"]))

    story.append(p("12. Decisões pedidas ao time", s["h1"]))
    story.append(
        callout_box(
            "1) Confirmar dois estoques (Admin intacto + Cardápio novo)?<br/>"
            "2) Começar pela Fase 1 (M1 + M3) sem explosão de ficha na venda?<br/>"
            "3) Abastecimento manual no MVP ou já amarrar na produção (1.5)?<br/>"
            "4) Saldo zerado: <b>bloquear</b> venda ou só <b>avisar</b>?",
            s,
        )
    )
    story.append(Spacer(1, 0.25 * cm))

    story.append(p("13. Próximo passo técnico (quando aprovado)", s["h1"]))
    for b in [
        "1. Migrations das duas tabelas.",
        "2. Implementar CardapioEstoqueSupport.",
        "3. Integrar baixa em VendaFiscalSupport / PDV.",
        "4. Tela Estoque do Cardápio no frontend.",
        "5. Testes de saldo, baixa e estorno.",
    ]:
        story.append(p(b, s["bullet"]))

    story.append(Spacer(1, 0.35 * cm))
    story.append(
        p(
            "<i>Documento gerado a partir da análise do código SAS Estoque em 2026-08-09. "
            "Versão Markdown: PROPOSTA_ESTOQUE_CARDAPIO_2026-08-09.md</i>",
            s["caption"],
        )
    )

    doc = SimpleDocTemplate(
        str(OUT),
        pagesize=A4,
        leftMargin=1.5 * cm,
        rightMargin=1.5 * cm,
        topMargin=1.4 * cm,
        bottomMargin=1.4 * cm,
        title="Proposta Estoque Cardápio — SAS Estoque",
        author="Grupo Sabor Paraense",
    )
    doc.build(story)
    print(f"PDF gerado: {OUT}")


if __name__ == "__main__":
    build()
