/**
 * Módulo Impostos — cadastro + anexos. Pagamento via Boletos (sem duplicar dados).
 */
(function () {
  "use strict";

  const MAX_BYTES = 5 * 1024 * 1024;
  let impEditId = null;

  function el(id) {
    return document.getElementById(id);
  }

  function esc(s) {
    if (typeof escapeHtml === "function") return escapeHtml(s);
    var d = document.createElement("div");
    d.textContent = s == null ? "" : String(s);
    return d.innerHTML;
  }

  function fmtMoeda(n) {
    if (typeof formatCurrencyBRL === "function") return formatCurrencyBRL(n);
    var v = Number(n);
    return Number.isFinite(v) ? v.toLocaleString("pt-BR", { style: "currency", currency: "BRL" }) : "—";
  }

  function fmtData(d) {
    if (!d) return "—";
    var s = String(d).slice(0, 10);
    if (s.length === 10) {
      var p = s.split("-");
      return p[2] + "/" + p[1] + "/" + p[0];
    }
    return s;
  }

  function statusLabel(st) {
    return { A_VENCER: "A vencer", VENCIDO: "Atrasado", PAGO: "Pago", CANCELADO: "Cancelado" }[st] || st || "—";
  }

  function statusClass(st) {
    return { A_VENCER: "status-a-vencer", VENCIDO: "status-vencido", PAGO: "status-pago", CANCELADO: "status-cancelado" }[st] || "";
  }

  function api(path, opts) {
    if (typeof fetchJSON === "function") return fetchJSON(path, opts);
    return fetch((window.APP_CONFIG && window.APP_CONFIG.API_URL) || "/api" + path, opts).then(function (r) {
      return r.json();
    });
  }

  function apiForm(path, method, fd) {
    if (typeof fetchForm === "function") return fetchForm(path, method, fd);
    throw new Error("fetchForm indisponível");
  }

  function toast(msg, type) {
    if (typeof showToast === "function") showToast(msg, type);
    else alert(msg);
  }

  function filtrosAtivos() {
    var f = {};
    if (el("impostosMesAnoFiltro")?.value) f.mes_ano = el("impostosMesAnoFiltro").value;
    if (el("impostosUnidadeFiltro")?.value) f.unidade_id = el("impostosUnidadeFiltro").value;
    if (el("impostosStatusFiltro")?.value) f.status = el("impostosStatusFiltro").value;
    return f;
  }

  function populateMesAno() {
    var select = el("impostosMesAnoFiltro");
    if (!select) return;
    var hoje = new Date();
    var anoAtual = hoje.getFullYear();
    var nomes = ["Janeiro", "Fevereiro", "Março", "Abril", "Maio", "Junho", "Julho", "Agosto", "Setembro", "Outubro", "Novembro", "Dezembro"];
    var val = select.value;
    select.innerHTML = '<option value="">Todos os meses</option>';
    for (var ano = Math.max(2026, anoAtual - 1); ano <= anoAtual + 1; ano++) {
      for (var m = 0; m < 12; m++) {
        var opt = document.createElement("option");
        opt.value = ano + "-" + String(m + 1).padStart(2, "0");
        opt.textContent = nomes[m] + " " + ano;
        select.appendChild(opt);
      }
    }
    if (val) select.value = val;
  }

  async function carregarUnidadesFiltro() {
    var sel = el("impostosUnidadeFiltro");
    var formSel = el("impostoForm")?.querySelector('[name="unidade_id"]');
    try {
      var unidades = await api("/unidades");
      [sel, formSel].forEach(function (s) {
        if (!s) return;
        var keep = s.value;
        s.innerHTML = '<option value="">' + (s === formSel ? "Selecione (opcional)" : "Todas as unidades") + "</option>";
        unidades.forEach(function (u) {
          var o = document.createElement("option");
          o.value = u.id;
          o.textContent = u.nome;
          s.appendChild(o);
        });
        if (keep) s.value = keep;
      });
    } catch (e) {
      console.error(e);
    }
  }

  function renderAnexosPreview(input, preview) {
    if (!preview || !input?.files?.length) {
      if (preview) { preview.innerHTML = ""; preview.style.display = "none"; }
      return;
    }
    var bad = Array.from(input.files).find(function (f) { return f.size > MAX_BYTES; });
    if (bad) {
      toast('Arquivo "' + bad.name + '" excede 5 MB', "error");
      input.value = "";
      preview.innerHTML = "";
      preview.style.display = "none";
      return;
    }
    preview.innerHTML = Array.from(input.files).map(function (f) {
      return '<div class="boleto-anexo-preview-item">' + esc(f.name) + "</div>";
    }).join("");
    preview.style.display = "block";
  }

  function renderAnexosAtual(anexos, container, impostoId, tipo) {
    if (!container) return;
    var lista = (anexos || []).filter(function (a) { return (a.tipo || "guia") === tipo; });
    if (!lista.length) {
      container.innerHTML = "";
      container.style.display = "none";
      return;
    }
    container.innerHTML = lista.map(function (a) {
      return (
        '<div style="display:flex;gap:0.5rem;align-items:center;margin-top:0.35rem;">' +
          '<a href="' + esc((typeof API_URL !== "undefined" ? API_URL : "") + "/impostos/anexos/" + a.id) + '" target="_blank" rel="noopener">' + esc(a.nome) + "</a>" +
          '<button type="button" class="btn secondary imp-remover-anexo" data-anexo-id="' + a.id + '">Remover</button>' +
        "</div>"
      );
    }).join("");
    container.style.display = "block";
  }

  function limparAnexosUI() {
    ["impostoAnexosGuiaInput", "impostoAnexosNotaInput"].forEach(function (id) {
      var i = el(id);
      if (i) i.value = "";
    });
    ["impostoAnexosGuiaPreview", "impostoAnexosNotaPreview", "impostoAnexosGuiaAtual", "impostoAnexosNotaAtual"].forEach(function (id) {
      var d = el(id);
      if (d) { d.innerHTML = ""; d.style.display = "none"; }
    });
  }

  function appendAnexos(fd, input, field) {
    if (!input?.files?.length) return;
    fd.delete(field);
    fd.delete(field + "[]");
    Array.from(input.files).forEach(function (f) {
      fd.append(field + "[]", f);
    });
  }

  async function loadImpostos(filtros) {
    filtros = filtros || filtrosAtivos();
    var tbody = el("impostosTable");
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="9" class="empty-row">Carregando…</td></tr>';
    try {
      var qs = new URLSearchParams(filtros).toString();
      var lista = await api("/impostos" + (qs ? "?" + qs : ""));
      if (!lista.length) {
        tbody.innerHTML = '<tr><td colspan="9" class="empty-row">Nenhum imposto cadastrado.</td></tr>';
        return;
      }
      tbody.innerHTML = lista.map(function (imp) {
        var boleto = imp.boleto;
        var boletoHtml = "—";
        if (boleto?.id) {
          var pag = boleto.status === "PAGO"
            ? '<br><small>Pago ' + fmtData(boleto.data_pagamento) + " — " + fmtMoeda(boleto.valor_pago) + "</small>"
            : "";
          boletoHtml = '<span>Boleto #' + boleto.id + pag + '</span>';
        }
        var anexos = (imp.anexos || []).length
          ? imp.anexos.map(function (a) {
              return '<a href="' + esc((typeof API_URL !== "undefined" ? API_URL : "") + "/impostos/anexos/" + a.id) + '" target="_blank" title="' + esc(a.nome) + '">📎</a>';
            }).join(" ")
          : "—";
        return (
          "<tr>" +
            '<td><span class="status-badge ' + statusClass(imp.status) + '">' + statusLabel(imp.status) + "</span></td>" +
            "<td>" + esc(imp.tipo_imposto) + "</td>" +
            "<td>" + esc(imp.descricao) + "</td>" +
            "<td>" + esc(imp.competencia || "—") + "</td>" +
            "<td>" + fmtData(imp.data_vencimento) + "</td>" +
            "<td>" + fmtMoeda(imp.valor) + "</td>" +
            "<td>" + boletoHtml + "</td>" +
            "<td>" + anexos + "</td>" +
            '<td class="table-actions">' +
              '<button type="button" class="btn secondary btn-sm imp-editar" data-id="' + imp.id + '">Editar</button> ' +
              (boleto?.id
                ? (boleto.status === "PAGO"
                  ? ""
                  : '<button type="button" class="btn primary btn-sm imp-pagar" data-boleto="' + boleto.id + '">Pagar no boleto</button> ')
                : '<button type="button" class="btn primary btn-sm imp-gerar-boleto" data-id="' + imp.id + '">Gerar boleto</button> ') +
              '<button type="button" class="btn danger btn-sm imp-excluir" data-id="' + imp.id + '">Excluir</button>' +
            "</td>" +
          "</tr>"
        );
      }).join("");
    } catch (e) {
      tbody.innerHTML = '<tr><td colspan="9" class="empty-row">Erro ao carregar.</td></tr>';
      toast(e?.message || "Erro ao carregar impostos", "error");
    }
  }

  function abrirModalNovo() {
    impEditId = null;
    var form = el("impostoForm");
    if (!form) return;
    form.reset();
    limparAnexosUI();
    el("impostoModalTitle").textContent = "Novo imposto";
    el("impostoModal").classList.add("active");
    el("impostoPagamentoAviso").style.display = "block";
  }

  async function abrirModalEditar(id) {
    try {
      var imp = await api("/impostos/" + id);
      impEditId = id;
      var form = el("impostoForm");
      form.reset();
      limparAnexosUI();
      ["tipo_imposto", "descricao", "orgao", "competencia", "numero_documento", "data_vencimento", "observacoes"].forEach(function (n) {
        if (form.elements[n]) form.elements[n].value = imp[n] || "";
      });
      if (form.elements.unidade_id) form.elements.unidade_id.value = imp.unidade_id || "";
      if (form.elements.valor) {
        if (typeof applyFechamentoValorInput === "function") applyFechamentoValorInput(form.elements.valor, Number(imp.valor));
        else form.elements.valor.value = imp.valor;
      }
      renderAnexosAtual(imp.anexos, el("impostoAnexosGuiaAtual"), id, "guia");
      renderAnexosAtual(imp.anexos, el("impostoAnexosNotaAtual"), id, "nota");
      el("impostoModalTitle").textContent = "Editar imposto";
      el("impostoModal").classList.add("active");
      el("impostoPagamentoAviso").style.display = "block";
    } catch (e) {
      toast(e?.message || "Erro ao abrir imposto", "error");
    }
  }

  function fecharModal() {
    el("impostoModal")?.classList.remove("active");
    impEditId = null;
  }

  async function salvarImposto(e) {
    e.preventDefault();
    var form = el("impostoForm");
    var fd = new FormData(form);
    var valorEl = form.elements.valor;
    if (valorEl && typeof parseCurrencyInput === "function") {
      var v = parseCurrencyInput(valorEl);
      if (v != null) fd.set("valor", String(v));
    }
    appendAnexos(fd, el("impostoAnexosGuiaInput"), "anexos_guia");
    appendAnexos(fd, el("impostoAnexosNotaInput"), "anexos_nota");
    try {
      if (impEditId) {
        await apiForm("/impostos/" + impEditId, "POST", fd);
        toast("Imposto atualizado.", "success");
      } else {
        await apiForm("/impostos", "POST", fd);
        toast("Imposto cadastrado. Gere o boleto para pagar em Financeiro → Boletos.", "success");
      }
      fecharModal();
      await loadImpostos();
    } catch (err) {
      toast(err?.message || "Erro ao salvar", "error");
    }
  }

  async function gerarBoleto(id) {
    try {
      var res = await api("/impostos/" + id + "/gerar-boleto", { method: "POST" });
      toast(res.message || "Boleto gerado.", "success");
      await loadImpostos();
    } catch (e) {
      toast(e?.message || "Erro ao gerar boleto", "error");
    }
  }

  function irPagarBoleto(boletoId) {
    if (typeof navigateTo === "function") {
      navigateTo("boletao");
    }
    toast("Abra o boleto #" + boletoId + " em Boletos para registrar o pagamento e anexar o comprovante.", "info");
    if (typeof abrirModalPagamento === "function") {
      setTimeout(function () { abrirModalPagamento(boletoId); }, 600);
    }
  }

  window.loadImpostos = loadImpostos;

  window.setupImpostosModule = function setupImpostosModule() {
    populateMesAno();
    carregarUnidadesFiltro();

    el("openNovoImposto")?.addEventListener("click", abrirModalNovo);
    el("closeImposto")?.addEventListener("click", fecharModal);
    el("cancelImposto")?.addEventListener("click", fecharModal);
    el("impostoForm")?.addEventListener("submit", salvarImposto);
    el("recarregarImpostos")?.addEventListener("click", function () { loadImpostos(); });
    el("impostoModal")?.addEventListener("click", function (e) {
      if (e.target === el("impostoModal")) fecharModal();
    });

    ["impostosMesAnoFiltro", "impostosUnidadeFiltro", "impostosStatusFiltro"].forEach(function (id) {
      el(id)?.addEventListener("change", function () { loadImpostos(); });
    });
    el("limparFiltrosImpostos")?.addEventListener("click", function () {
      ["impostosMesAnoFiltro", "impostosUnidadeFiltro", "impostosStatusFiltro"].forEach(function (id) {
        var s = el(id);
        if (s) s.value = "";
      });
      loadImpostos();
    });

    el("impostoAnexosGuiaInput")?.addEventListener("change", function () {
      renderAnexosPreview(el("impostoAnexosGuiaInput"), el("impostoAnexosGuiaPreview"));
    });
    el("impostoAnexosNotaInput")?.addEventListener("change", function () {
      renderAnexosPreview(el("impostoAnexosNotaInput"), el("impostoAnexosNotaPreview"));
    });

    el("impostosTable")?.addEventListener("click", function (ev) {
      var t = ev.target.closest("button");
      if (!t) return;
      if (t.classList.contains("imp-editar")) abrirModalEditar(t.getAttribute("data-id"));
      if (t.classList.contains("imp-excluir") && confirm("Excluir este imposto?")) {
        api("/impostos/" + t.getAttribute("data-id"), { method: "DELETE" })
          .then(function () { toast("Excluído.", "success"); loadImpostos(); })
          .catch(function (e) { toast(e?.message || "Erro", "error"); });
      }
      if (t.classList.contains("imp-gerar-boleto")) gerarBoleto(t.getAttribute("data-id"));
      if (t.classList.contains("imp-pagar")) irPagarBoleto(t.getAttribute("data-boleto"));
    });

    document.body.addEventListener("click", function (ev) {
      var btn = ev.target.closest(".imp-remover-anexo");
      if (!btn || !el("impostoModal") || !el("impostoModal").classList.contains("active")) return;
      var aid = btn.getAttribute("data-anexo-id");
      if (!aid || !confirm("Remover anexo?")) return;
      api("/impostos/anexos/" + aid, { method: "DELETE" })
        .then(function () {
          toast("Anexo removido.", "success");
          if (impEditId) abrirModalEditar(impEditId);
        })
        .catch(function (e) { toast(e?.message || "Erro", "error"); });
    });
  };
})();
