/**
 * Orçamentos — Etapa 1.
 * Protótipo 100% frontend: nenhum fetch, API, banco ou persistência.
 */
(function () {
  "use strict";

  const SECTIONS = {
    dashboard: "orcamentosDashboard",
    novo: "orcamentosNovo",
    lista: "orcamentosLista",
    proposta: "orcamentosProposta",
    modelos: "orcamentosModelos",
    clientes: "orcamentosClientes",
    itens: "orcamentosItens",
    configuracoes: "orcamentosConfiguracoes",
    relatorios: "orcamentosRelatorios",
  };

  const STATUS_CLASS = {
    aprovado: "success",
    convertido: "success",
    pendente: "warning",
    "em negociação": "warning",
    recusado: "danger",
  };

  const state = {
    loaded: new Set(),
    wizardStep: 1,
    wizardType: "Evento",
    activeItemTab: "Produtos",
    selectedBudget: null,
    budgets: [
      { id: 1048, cliente: "Mariana Lopes", tipo: "Buffet", data: "16/07/2026", valor: 12850, responsavel: "Ana Paula", status: "aprovado" },
      { id: 1047, cliente: "Construtora Vale", tipo: "Evento", data: "16/07/2026", valor: 28600, responsavel: "Carlos Lima", status: "em negociação" },
      { id: 1046, cliente: "Roberto Almeida", tipo: "Serviço", data: "15/07/2026", valor: 3450, responsavel: "Ana Paula", status: "pendente" },
      { id: 1045, cliente: "Clínica Mais Vida", tipo: "Locação", data: "14/07/2026", valor: 8990, responsavel: "Thiago Souza", status: "convertido" },
      { id: 1044, cliente: "Beatriz & Henrique", tipo: "Mesa", data: "13/07/2026", valor: 6750, responsavel: "Ana Paula", status: "recusado" },
      { id: 1043, cliente: "Grupo Horizonte", tipo: "Equipamento", data: "12/07/2026", valor: 15400, responsavel: "Carlos Lima", status: "aprovado" },
    ],
    clients: [
      { nome: "Mariana Lopes", cidade: "Porto Velho", telefone: "(69) 99241-8802", ultimo: "#1048", total: 26350 },
      { nome: "Construtora Vale", cidade: "Ariquemes", telefone: "(69) 98420-1177", ultimo: "#1047", total: 48900 },
      { nome: "Clínica Mais Vida", cidade: "Porto Velho", telefone: "(69) 99352-6621", ultimo: "#1045", total: 18740 },
      { nome: "Grupo Horizonte", cidade: "Ji-Paraná", telefone: "(69) 98110-2234", ultimo: "#1043", total: 62100 },
      { nome: "Beatriz & Henrique", cidade: "Cacoal", telefone: "(69) 99206-9040", ultimo: "#1044", total: 6750 },
      { nome: "Roberto Almeida", cidade: "Porto Velho", telefone: "(69) 98412-7710", ultimo: "#1046", total: 11320 },
    ],
  };

  const wizardSteps = [
    "Cliente",
    "Tipo",
    "Produtos",
    "Equipe",
    "Equipamentos",
    "Consumo",
    "Frete",
    "Financeiro",
  ];

  function root(id) {
    return document.getElementById(id);
  }

  function esc(value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function money(value) {
    return Number(value || 0).toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
  }

  function toast(message, type) {
    if (typeof window.showToast === "function") window.showToast(message, type || "info");
  }

  function go(section) {
    if (typeof window.navigateTo === "function") window.navigateTo(section);
  }

  function simulate(button, message, callback) {
    if (!button || button.disabled) return;
    const original = button.innerHTML;
    button.disabled = true;
    button.innerHTML = "Aguarde…";
    setTimeout(function () {
      button.disabled = false;
      button.innerHTML = original;
      if (message) toast(message, "success");
      if (callback) callback();
    }, 550);
  }

  function badge(status) {
    const cls = STATUS_CLASS[String(status).toLowerCase()] || "";
    return `<span class="orc-badge ${cls ? `orc-badge--${cls}` : ""}">${esc(status)}</span>`;
  }

  function header(title, subtitle, icon, actions, crumb) {
    return `
      <header class="orc-head">
        <div>
          <div class="orc-breadcrumb"><button type="button" data-orc-go="${SECTIONS.dashboard}">Orçamentos</button> / ${esc(crumb || title)}</div>
          <div class="orc-head__title">
            <span class="orc-head__icon" aria-hidden="true">${icon || "□"}</span>
            <div><h2>${esc(title)}</h2><p>${esc(subtitle || "")}</p></div>
          </div>
        </div>
        <div class="orc-actions">${actions || ""}</div>
      </header>`;
  }

  function shell(content) {
    return `<div class="orc-shell">${content}</div>`;
  }

  function skeleton(target, render) {
    if (!target) return;
    if (state.loaded.has(target.id)) {
      render();
      return;
    }
    target.innerHTML = shell(`
      <div class="orc-skeleton"></div>
      <div class="orc-kpis">
        <div class="orc-skeleton"></div><div class="orc-skeleton"></div>
        <div class="orc-skeleton"></div><div class="orc-skeleton"></div>
      </div>`);
    setTimeout(function () {
      state.loaded.add(target.id);
      render();
    }, 320);
  }

  function bindCommon(container) {
    if (!container) return;
    container.querySelectorAll("[data-orc-go]").forEach(function (button) {
      button.addEventListener("click", function () { go(button.dataset.orcGo); });
    });
    container.querySelectorAll("[data-orc-demo]").forEach(function (button) {
      button.addEventListener("click", function () {
        simulate(button, button.dataset.orcDemo || "Ação simulada com sucesso.");
      });
    });
  }

  function kpi(label, value, icon, trend, target) {
    return `
      <article class="orc-kpi" ${target ? `data-orc-go="${target}"` : ""} tabindex="0">
        <div class="orc-kpi__top"><span>${esc(label)}</span><span class="orc-kpi__icon">${icon}</span></div>
        <div class="orc-kpi__value">${value}</div>
        <div class="orc-kpi__trend">${esc(trend || "Dados simulados")}</div>
      </article>`;
  }

  function loadDashboard() {
    const target = root("orcamentosDashboardRoot");
    skeleton(target, function () {
      const recent = state.budgets.slice(0, 5).map(function (b) {
        return `<div class="orc-list-row orc-clickable" data-budget="${b.id}">
          <div class="orc-avatar">#</div>
          <div><strong>#${b.id} · ${esc(b.cliente)}</strong><div class="orc-muted">${esc(b.tipo)} · ${esc(b.data)}</div></div>
          <div style="text-align:right">${badge(b.status)}<div class="orc-money">${money(b.valor)}</div></div>
        </div>`;
      }).join("");
      const topClients = state.clients.slice(0, 5).map(function (c, index) {
        return `<div class="orc-list-row">
          <div class="orc-avatar">${index + 1}</div>
          <div><strong>${esc(c.nome)}</strong><div class="orc-muted">${esc(c.cidade)} · ${esc(c.ultimo)}</div></div>
          <div class="orc-money">${money(c.total)}</div>
        </div>`;
      }).join("");
      target.innerHTML = shell(`
        ${header("Dashboard de Orçamentos", "Visão comercial consolidada do período.", "▦",
          `<button class="orc-btn orc-btn--primary" data-orc-go="${SECTIONS.novo}">＋ Novo Orçamento</button>`, "Dashboard")}
        <div class="orc-notice">Protótipo navegável com dados fictícios. Nenhuma informação é gravada.</div>
        <div class="orc-kpis">
          ${kpi("Total de orçamentos", "128", "▤", "+12% no mês", SECTIONS.lista)}
          ${kpi("Pendentes", "18", "◷", "7 aguardam retorno", SECTIONS.lista)}
          ${kpi("Aprovados", "74", "✓", "57,8% do total", SECTIONS.lista)}
          ${kpi("Recusados", "9", "×", "7% do total", SECTIONS.lista)}
          ${kpi("Em negociação", "21", "↔", "Potencial de R$ 94 mil", SECTIONS.lista)}
          ${kpi("Convertidos", "63", "◆", "49,2% de conversão", SECTIONS.lista)}
          ${kpi("Valor total", money(486920), "R$", "+18,4% no período", SECTIONS.relatorios)}
          ${kpi("Ticket médio", money(3804.06), "∅", "Aprovação média: 2,8 dias", SECTIONS.relatorios)}
        </div>
        <div class="orc-grid-2">
          <section class="orc-card">
            <div class="orc-card__head"><div><h3>Evolução dos orçamentos</h3><div class="orc-muted">Valores simulados nos últimos 7 meses</div></div>${badge("crescimento")}</div>
            <div class="orc-chart" aria-label="Gráfico simulado de evolução">
              ${[42, 56, 48, 68, 74, 86, 95].map(function (h, i) {
                return `<div class="orc-chart__bar" style="--h:${h}%"><span>${["Jan","Fev","Mar","Abr","Mai","Jun","Jul"][i]}</span></div>`;
              }).join("")}
            </div>
          </section>
          <section class="orc-card"><div class="orc-card__head"><h3>Top clientes</h3><button class="orc-btn orc-btn--sm" data-orc-go="${SECTIONS.clientes}">Ver todos</button></div><div class="orc-list">${topClients}</div></section>
        </div>
        <section class="orc-card"><div class="orc-card__head"><h3>Últimos orçamentos</h3><button class="orc-btn orc-btn--sm" data-orc-go="${SECTIONS.lista}">Abrir lista</button></div><div class="orc-list">${recent}</div></section>
        <button class="orc-fab" data-orc-go="${SECTIONS.novo}" title="Novo orçamento" aria-label="Novo orçamento">＋</button>`);
      bindCommon(target);
      target.querySelectorAll("[data-budget]").forEach(function (row) {
        row.addEventListener("click", function () {
          state.selectedBudget = Number(row.dataset.budget);
          go(SECTIONS.proposta);
        });
      });
    });
  }

  function filtersList() {
    return `
      <section class="orc-card">
        <div class="orc-filter-grid">
          <label class="orc-field"><span>Pesquisar</span><input class="orc-input" id="orcListSearch" placeholder="Número, cliente ou responsável"></label>
          <label class="orc-field"><span>Cliente</span><select class="orc-select" id="orcListClient"><option value="">Todos</option>${state.clients.map(c => `<option>${esc(c.nome)}</option>`).join("")}</select></label>
          <label class="orc-field"><span>Status</span><select class="orc-select" id="orcListStatus"><option value="">Todos</option><option>Pendente</option><option>Aprovado</option><option>Recusado</option><option>Em negociação</option><option>Convertido</option></select></label>
          <label class="orc-field"><span>Tipo</span><select class="orc-select" id="orcListType"><option value="">Todos</option><option>Buffet</option><option>Evento</option><option>Serviço</option><option>Locação</option><option>Mesa</option></select></label>
          <label class="orc-field"><span>Data inicial</span><input class="orc-input" type="date"></label>
          <label class="orc-field"><span>Data final</span><input class="orc-input" type="date"></label>
          <div class="orc-actions" style="align-self:end"><button class="orc-btn orc-btn--primary" id="orcApplyFilter">Filtrar</button><button class="orc-btn" id="orcClearFilter">Limpar</button></div>
        </div>
      </section>`;
  }

  function renderBudgetTable(target, budgets) {
    const rows = budgets.map(function (b) {
      return `<tr data-row-id="${b.id}">
        <td><strong>#${b.id}</strong></td><td>${esc(b.cliente)}</td><td>${esc(b.tipo)}</td><td>${esc(b.data)}</td>
        <td class="orc-money">${money(b.valor)}</td><td>${esc(b.responsavel)}</td><td>${badge(b.status)}</td>
        <td><div class="orc-row-actions">
          <button class="orc-btn orc-btn--sm" data-budget-action="view" data-id="${b.id}" title="Visualizar proposta">Ver</button>
          <button class="orc-btn orc-btn--sm" data-budget-action="edit" data-id="${b.id}" title="Editar">Editar</button>
          <button class="orc-btn orc-btn--sm" data-budget-action="duplicate" data-id="${b.id}" title="Duplicar">Duplicar</button>
          <button class="orc-btn orc-btn--sm" data-budget-action="pdf" data-id="${b.id}" title="PDF">PDF</button>
          <button class="orc-btn orc-btn--sm" data-budget-action="whatsapp" data-id="${b.id}" title="WhatsApp">WhatsApp</button>
          <button class="orc-btn orc-btn--sm" data-budget-action="instagram" data-id="${b.id}" title="Instagram">Instagram</button>
          <button class="orc-btn orc-btn--sm" data-budget-action="email" data-id="${b.id}" title="E-mail">E-mail</button>
          <button class="orc-btn orc-btn--sm orc-btn--danger" data-budget-action="delete" data-id="${b.id}" title="Excluir">Excluir</button>
        </div></td>
      </tr>`;
    }).join("");
    target.innerHTML = rows || `<tr><td colspan="8" class="orc-muted" style="text-align:center">Nenhum orçamento fictício encontrado.</td></tr>`;
  }

  function loadLista() {
    const target = root("orcamentosListaRoot");
    skeleton(target, function () {
      target.innerHTML = shell(`
        ${header("Orçamentos", "Consulte, filtre e acompanhe todas as propostas.", "☷",
          `<button class="orc-btn orc-btn--primary" data-orc-go="${SECTIONS.novo}">＋ Novo</button>`, "Lista")}
        ${filtersList()}
        <div class="orc-table-wrap"><table class="orc-table">
          <thead><tr><th>Número</th><th>Cliente</th><th>Tipo</th><th>Data</th><th>Valor</th><th>Responsável</th><th>Situação</th><th>Ações</th></tr></thead>
          <tbody id="orcBudgetRows"></tbody>
        </table></div>
        <button class="orc-fab" data-orc-go="${SECTIONS.novo}" title="Novo orçamento">＋</button>`);
      bindCommon(target);
      const tbody = root("orcBudgetRows");
      renderBudgetTable(tbody, state.budgets);

      function bindRows() {
        tbody.querySelectorAll("[data-budget-action]").forEach(function (button) {
          button.addEventListener("click", function () {
            const action = button.dataset.budgetAction;
            const id = Number(button.dataset.id);
            state.selectedBudget = id;
            if (action === "delete") {
              if (!confirm("Excluir este orçamento fictício? A alteração dura somente nesta sessão.")) return;
              state.budgets = state.budgets.filter(b => b.id !== id);
              renderBudgetTable(tbody, state.budgets);
              bindRows();
              toast("Orçamento fictício removido.", "success");
              return;
            }
            if (action === "edit" || action === "duplicate") {
              state.wizardStep = 1;
              toast(action === "duplicate" ? "Cópia fictícia preparada." : "Modo de edição simulado.", "info");
              go(SECTIONS.novo);
              return;
            }
            if (["pdf", "whatsapp", "instagram", "email"].includes(action)) {
              toast(`Canal ${action} preparado para demonstração.`, "info");
            }
            go(SECTIONS.proposta);
          });
        });
      }
      bindRows();
      root("orcApplyFilter").addEventListener("click", function () {
        const search = root("orcListSearch").value.trim().toLowerCase();
        const client = root("orcListClient").value;
        const status = root("orcListStatus").value.toLowerCase();
        const type = root("orcListType").value;
        const filtered = state.budgets.filter(function (b) {
          const haystack = `${b.id} ${b.cliente} ${b.responsavel}`.toLowerCase();
          return (!search || haystack.includes(search)) && (!client || b.cliente === client) &&
            (!status || b.status.toLowerCase() === status) && (!type || b.tipo === type);
        });
        renderBudgetTable(tbody, filtered);
        bindRows();
        toast(`${filtered.length} orçamento(s) encontrado(s).`, "info");
      });
      root("orcClearFilter").addEventListener("click", function () {
        target.querySelectorAll("input").forEach(el => { el.value = ""; });
        target.querySelectorAll("select").forEach(el => { el.selectedIndex = 0; });
        renderBudgetTable(tbody, state.budgets);
        bindRows();
      });
    });
  }

  function tableEditor(title, rows, addLabel) {
    return `
      <div class="orc-card__head"><div><h3>${esc(title)}</h3><div class="orc-muted">Valores fictícios para visualização</div></div><button class="orc-btn orc-btn--primary" data-add-row="${esc(title)}">＋ ${esc(addLabel)}</button></div>
      <div class="orc-table-wrap"><table class="orc-table"><thead><tr>${rows.headers.map(h => `<th>${esc(h)}</th>`).join("")}</tr></thead>
      <tbody>${rows.data.map(row => `<tr>${row.map(cell => `<td>${cell}</td>`).join("")}</tr>`).join("")}</tbody></table></div>`;
  }

  function wizardContent(step) {
    if (step === 1) return `
      <div class="orc-card__head"><div><h3>Dados do cliente</h3><div class="orc-muted">Identificação e canais de contato</div></div>${badge("Passo 1 de 8")}</div>
      <div class="orc-form-grid">
        <label class="orc-field"><span>Nome</span><input class="orc-input" value="Mariana Lopes"></label>
        <label class="orc-field"><span>Telefone</span><input class="orc-input" value="(69) 99241-8802"></label>
        <label class="orc-field"><span>WhatsApp</span><input class="orc-input" value="(69) 99241-8802"></label>
        <label class="orc-field"><span>Instagram</span><input class="orc-input" value="@marianalopes"></label>
        <label class="orc-field"><span>E-mail</span><input class="orc-input" value="mariana@email.com"></label>
        <label class="orc-field"><span>CPF/CNPJ</span><input class="orc-input" value="123.456.789-00"></label>
        <label class="orc-field"><span>Empresa</span><input class="orc-input" placeholder="Empresa do cliente"></label>
        <label class="orc-field"><span>Origem</span><select class="orc-select"><option>Instagram</option><option>Indicação</option><option>WhatsApp</option><option>Site</option></select></label>
        <label class="orc-field orc-field--full"><span>Observações</span><textarea class="orc-textarea">Cliente deseja evento elegante e acolhedor.</textarea></label>
      </div>`;

    if (step === 2) {
      const types = [["Produto","◆"],["Serviço","◇"],["Equipamento","▣"],["Evento","★"],["Buffet","◉"],["Mesa","▦"],["Locação","⌂"],["Mão de obra","◎"],["Outro","＋"]];
      return `<div class="orc-card__head"><div><h3>Tipo do orçamento</h3><div class="orc-muted">Escolha a categoria principal</div></div>${badge("Passo 2 de 8")}</div>
        <div class="orc-choice-grid">${types.map(t => `<button class="orc-choice ${state.wizardType === t[0] ? "is-selected" : ""}" data-budget-type="${t[0]}"><span class="orc-choice__icon">${t[1]}</span><strong>${t[0]}</strong><span class="orc-muted">Estrutura pronta para ${t[0].toLowerCase()}.</span></button>`).join("")}</div>`;
    }

    if (step === 3) return tableEditor("Produtos e serviços", {
      headers: ["Produto", "Quantidade", "Valor", "Desconto", "Subtotal", "Ação"],
      data: [
        ["Buffet completo premium", "80", money(98), "5%", money(7448), '<button class="orc-btn orc-btn--sm orc-btn--danger" data-orc-demo="Item removido apenas da demonstração.">Remover</button>'],
        ["Mesa de doces", "1", money(1850), "—", money(1850), '<button class="orc-btn orc-btn--sm orc-btn--danger" data-orc-demo="Item removido apenas da demonstração.">Remover</button>'],
      ],
    }, "Adicionar item") + `<label class="orc-field" style="margin-top:1rem"><span>Pesquisa inteligente</span><input class="orc-input" placeholder="Digite produto, serviço ou código…"></label>`;

    if (step === 4) return tableEditor("Equipe do evento", {
      headers: ["Função", "Qtd.", "Horas", "Valor/hora", "Valor evento", "Subtotal"],
      data: [
        ["Garçom", "6", "8h", money(25), money(200), money(1200)],
        ["Cozinheiro", "2", "10h", money(35), money(350), money(700)],
        ["Supervisor", "1", "10h", money(42), money(420), money(420)],
      ],
    }, "Adicionar profissional") + `<div class="orc-summary" style="margin-top:1rem"><div class="orc-summary__row orc-summary__row--total"><span>Custo total da equipe</span><span>${money(2320)}</span></div></div>
      <p class="orc-muted">Funções disponíveis: Atendente, Garçom, Recepcionista, Caixa, Cozinheiro, Auxiliar, Supervisor, Segurança, Limpeza, Motorista, Montador, Desmontador e Outro.</p>`;

    if (step === 5) return tableEditor("Equipamentos", {
      headers: ["Equipamento", "Quantidade", "Dias", "Valor", "Subtotal"],
      data: [["Mesas redondas","10","1",money(55),money(550)],["Cadeiras","80","1",money(9),money(720)],["Rechaud","8","1",money(45),money(360)],["Tenda 5x5","2","1",money(380),money(760)]],
    }, "Adicionar equipamento") + `<p class="orc-muted">Catálogo: Mesas, Cadeiras, Toalhas, Pratos, Copos, Talheres, Rechaud, Caixa térmica, Tendas, Freezer e Outro.</p>`;

    if (step === 6) return tableEditor("Produtos consumidos", {
      headers: ["Produto", "Quantidade", "Valor", "Subtotal"],
      data: [["Arroz tipo 1","12 kg",money(7.9),money(94.8)],["Filé de frango","28 kg",money(21.5),money(602)],["Refrigerantes","35 un.",money(9.5),money(332.5)],["Descartáveis","80 kits",money(4.2),money(336)]],
    }, "Adicionar produto") + `<div class="orc-notice" style="margin-top:1rem">Consumo simulado. Não há consulta ou baixa no estoque.</div>`;

    if (step === 7) return `
      <div class="orc-card__head"><div><h3>Frete e logística</h3><div class="orc-muted">Defina entrega, montagem e retirada</div></div>${badge("Passo 7 de 8")}</div>
      <div class="orc-choice-grid">
        ${["Sem frete","Retirada","Entrega","Montagem","Desmontagem"].map((t, i) => `<button class="orc-choice ${i === 2 ? "is-selected" : ""}" data-freight="${t}"><span class="orc-choice__icon">${["×","↙","→","↑","↓"][i]}</span><strong>${t}</strong></button>`).join("")}
      </div>
      <div class="orc-form-grid" style="margin-top:1rem">
        <label class="orc-field"><span>Valor manual</span><input class="orc-input" value="R$ 450,00"></label>
        <label class="orc-field"><span>Distância</span><input class="orc-input" value="18 km"></label>
        <label class="orc-field orc-field--full"><span>Observações</span><textarea class="orc-textarea">Entrega duas horas antes do evento. Acesso pela portaria lateral.</textarea></label>
      </div>`;

    return `
      <div class="orc-card__head"><div><h3>Financeiro</h3><div class="orc-muted">Condições e resumo final da proposta</div></div>${badge("Passo 8 de 8")}</div>
      <div class="orc-grid-2">
        <div class="orc-form-grid">
          <label class="orc-field"><span>Desconto %</span><input class="orc-input" value="5"></label>
          <label class="orc-field"><span>Desconto R$</span><input class="orc-input" value="R$ 0,00"></label>
          <label class="orc-field"><span>Acréscimo</span><input class="orc-input" value="R$ 0,00"></label>
          <label class="orc-field"><span>Forma de pagamento</span><select class="orc-select"><option>PIX</option><option>Dinheiro</option><option>Cartão</option><option>Parcelado</option></select></label>
          <label class="orc-field"><span>Validade</span><input class="orc-input" type="date" value="2026-07-24"></label>
          <label class="orc-field orc-field--full"><span>Observações</span><textarea class="orc-textarea">50% na aprovação e 50% até 48 horas antes do evento.</textarea></label>
        </div>
        <div class="orc-summary">
          <h3>Resumo financeiro</h3>
          ${[["Subtotal",9298],["Equipe",2320],["Equipamentos",2390],["Produtos",1365.3],["Frete",450],["Desconto",-791.17]].map(r => `<div class="orc-summary__row"><span>${r[0]}</span><strong>${money(r[1])}</strong></div>`).join("")}
          <div class="orc-summary__row orc-summary__row--total"><span>Total</span><span>${money(15032.13)}</span></div>
          <div class="orc-summary__row"><span>Lucro estimado</span><strong style="color:var(--orc-success)">${money(4120)} (27,4%)</strong></div>
        </div>
      </div>`;
  }

  function renderWizard() {
    const target = root("orcamentosNovoRoot");
    if (!target) return;
    target.innerHTML = shell(`
      ${header("Novo Orçamento", "Monte a proposta em oito passos simples.", "＋",
        `<button class="orc-btn" data-orc-go="${SECTIONS.lista}">Cancelar</button>`, "Novo")}
      <div class="orc-wizard">
        <aside class="orc-wizard__steps">${wizardSteps.map((label, i) => `
          <button class="orc-step ${state.wizardStep === i + 1 ? "is-active" : ""} ${state.wizardStep > i + 1 ? "is-done" : ""}" data-wizard-step="${i + 1}">
            <span class="orc-step__num">${state.wizardStep > i + 1 ? "✓" : i + 1}</span><span class="orc-step__label">${label}</span>
          </button>`).join("")}</aside>
        <main class="orc-card orc-wizard__body">
          <div id="orcWizardContent">${wizardContent(state.wizardStep)}</div>
          <footer class="orc-wizard__footer">
            <button class="orc-btn" id="orcWizardPrev" ${state.wizardStep === 1 ? "disabled" : ""}>← Voltar</button>
            <button class="orc-btn orc-btn--primary" id="orcWizardNext">${state.wizardStep === 8 ? "Concluir e visualizar proposta" : "Continuar →"}</button>
          </footer>
        </main>
      </div>`);
    bindCommon(target);
    target.querySelectorAll("[data-wizard-step]").forEach(button => button.addEventListener("click", function () {
      state.wizardStep = Number(button.dataset.wizardStep);
      renderWizard();
    }));
    target.querySelectorAll("[data-budget-type]").forEach(button => button.addEventListener("click", function () {
      state.wizardType = button.dataset.budgetType;
      renderWizard();
    }));
    target.querySelectorAll("[data-freight]").forEach(button => button.addEventListener("click", function () {
      target.querySelectorAll("[data-freight]").forEach(el => el.classList.remove("is-selected"));
      button.classList.add("is-selected");
      toast(`${button.dataset.freight} selecionado.`, "info");
    }));
    target.querySelectorAll("[data-add-row]").forEach(button => button.addEventListener("click", function () {
      simulate(button, `${button.dataset.addRow}: item fictício adicionado.`);
    }));
    root("orcWizardPrev").addEventListener("click", function () { if (state.wizardStep > 1) { state.wizardStep--; renderWizard(); } });
    root("orcWizardNext").addEventListener("click", function () {
      if (state.wizardStep < 8) {
        simulate(this, "", function () { state.wizardStep++; renderWizard(); });
      } else {
        simulate(this, "Orçamento fictício concluído.", function () {
          state.selectedBudget = 1048;
          go(SECTIONS.proposta);
        });
      }
    });
  }

  function loadNovo() {
    state.loaded.add("orcamentosNovoRoot");
    renderWizard();
  }

  function selectedBudget() {
    return state.budgets.find(b => b.id === state.selectedBudget) || state.budgets[0] || {
      id: 1048, cliente: "Mariana Lopes", tipo: "Buffet", data: "16/07/2026", valor: 15032.13, responsavel: "Ana Paula", status: "pendente",
    };
  }

  function loadProposta() {
    const target = root("orcamentosPropostaRoot");
    skeleton(target, function () {
      const b = selectedBudget();
      target.innerHTML = shell(`
        ${header(`Proposta #${b.id}`, "Visualização simulada do documento final.", "▤",
          `<button class="orc-btn" data-orc-go="${SECTIONS.lista}">← Orçamentos</button>
           <button class="orc-btn orc-btn--primary" data-orc-demo="PDF simulado preparado.">Gerar PDF</button>`, "Proposta")}
        <article class="orc-proposal">
          <div class="orc-proposal__brand">
            <div style="display:flex;gap:1rem;align-items:center"><img src="imagens/logo.png" alt="Logo" class="orc-proposal__logo"><div><h2>Grupo Sabor Paraense</h2><div>Proposta Comercial</div></div></div>
            <div style="text-align:right"><strong>#${b.id}</strong><div>${esc(b.data)}</div>${badge(b.status)}</div>
          </div>
          <div class="orc-proposal__columns">
            <div><h4>Empresa</h4><p>Grupo Sabor Paraense<br>Porto Velho — RO<br>contato@gruposaborparaense.com.br</p></div>
            <div><h4>Cliente</h4><p>${esc(b.cliente)}<br>(69) 99241-8802<br>mariana@email.com</p></div>
          </div>
          <div class="orc-table-wrap"><table class="orc-table" style="min-width:0"><thead><tr><th>Descrição</th><th>Qtd.</th><th>Valor</th><th>Total</th></tr></thead>
            <tbody><tr><td>Buffet completo premium</td><td>80</td><td>${money(98)}</td><td>${money(7840)}</td></tr>
            <tr><td>Equipe do evento</td><td>9</td><td>—</td><td>${money(2320)}</td></tr>
            <tr><td>Equipamentos e estrutura</td><td>1</td><td>—</td><td>${money(2390)}</td></tr>
            <tr><td>Entrega e logística</td><td>1</td><td>${money(450)}</td><td>${money(450)}</td></tr></tbody></table></div>
          <div class="orc-proposal__columns">
            <div><h4>Condições</h4><p>50% na aprovação e 50% até 48 horas antes do evento.<br>Validade: 7 dias.</p><h4>Observações</h4><p>Montagem concluída duas horas antes do início.</p></div>
            <div class="orc-summary"><div class="orc-summary__row"><span>Subtotal</span><strong>${money(15823.3)}</strong></div><div class="orc-summary__row"><span>Desconto</span><strong>${money(791.17)}</strong></div><div class="orc-summary__row orc-summary__row--total"><span>Total</span><span>${money(15032.13)}</span></div></div>
          </div>
          <div class="orc-signature"><div>Grupo Sabor Paraense</div><div>${esc(b.cliente)}</div></div>
        </article>
        <div class="orc-card"><div class="orc-actions">
          <button class="orc-btn" data-orc-demo="Impressão simulada preparada.">Imprimir</button>
          <button class="orc-btn" data-orc-demo="Mensagem de WhatsApp preparada.">WhatsApp</button>
          <button class="orc-btn" data-orc-demo="Mensagem do Instagram copiada.">Instagram</button>
          <button class="orc-btn" data-orc-demo="E-mail fictício preparado.">E-mail</button>
          <button class="orc-btn" data-orc-demo="Link fictício copiado.">Copiar link</button>
          <button class="orc-btn orc-btn--success" data-orc-demo="Proposta aprovada na demonstração.">Aprovar</button>
          <button class="orc-btn orc-btn--danger" data-orc-demo="Proposta recusada na demonstração.">Recusar</button>
        </div></div>`);
      bindCommon(target);
    });
  }

  const models = [
    ["Buffet Completo","◉","Cardápio, equipe, equipamentos e logística"],
    ["Evento Corporativo","★","Estrutura completa para empresas"],
    ["Venda de Produtos","◆","Produtos, quantidades e descontos"],
    ["Prestação de Serviço","◇","Horas, profissionais e escopo"],
    ["Locação","⌂","Equipamentos por quantidade e dias"],
    ["Equipamentos","▣","Mesas, cadeiras, tendas e acessórios"],
    ["Mesa Posta","▦","Composição elegante e personalizada"],
  ];

  function loadModelos() {
    const target = root("orcamentosModelosRoot");
    skeleton(target, function () {
      target.innerHTML = shell(`
        ${header("Modelos", "Comece rapidamente com estruturas prontas.", "▣", `<button class="orc-btn orc-btn--primary" data-orc-demo="Novo modelo fictício aberto.">＋ Novo modelo</button>`, "Modelos")}
        <div class="orc-model-grid">${models.map((m, i) => `<article class="orc-model orc-clickable"><span class="orc-model__icon">${m[1]}</span><div><h3>${m[0]}</h3><p class="orc-muted">${m[2]}</p></div><div class="orc-actions">
          <button class="orc-btn orc-btn--primary orc-btn--sm" data-model-use="${i}">Usar modelo</button>
          <button class="orc-btn orc-btn--sm" data-orc-demo="Modelo duplicado apenas nesta demonstração.">Duplicar</button>
          <button class="orc-btn orc-btn--sm" data-orc-demo="Editor fictício do modelo aberto.">Editar</button>
        </div></article>`).join("")}</div>`);
      bindCommon(target);
      target.querySelectorAll("[data-model-use]").forEach(button => button.addEventListener("click", function () {
        state.wizardType = models[Number(button.dataset.modelUse)][0];
        state.wizardStep = 1;
        toast("Modelo aplicado ao novo orçamento.", "success");
        go(SECTIONS.novo);
      }));
    });
  }

  function loadClientes() {
    const target = root("orcamentosClientesRoot");
    skeleton(target, function () {
      target.innerHTML = shell(`
        ${header("Clientes", "Relacionamento e histórico comercial simulado.", "◎", `<button class="orc-btn orc-btn--primary" data-orc-demo="Formulário fictício de cliente aberto.">＋ Novo cliente</button>`, "Clientes")}
        <div class="orc-client-grid">${state.clients.map(function (c) {
          const initials = c.nome.split(" ").slice(0,2).map(n => n[0]).join("");
          return `<article class="orc-client orc-clickable"><div class="orc-avatar">${esc(initials)}</div><div><h3>${esc(c.nome)}</h3><div class="orc-muted">${esc(c.telefone)}<br>${esc(c.cidade)}</div></div>
            <div><div class="orc-muted">Último orçamento: ${esc(c.ultimo)}</div><div class="orc-money">${money(c.total)}</div></div>
            <button class="orc-btn orc-btn--primary" data-client-budget="${esc(c.nome)}">＋ Novo orçamento</button></article>`;
        }).join("")}</div>`);
      bindCommon(target);
      target.querySelectorAll("[data-client-budget]").forEach(button => button.addEventListener("click", function () {
        state.wizardStep = 1;
        toast(`Cliente ${button.dataset.clientBudget} selecionado.`, "success");
        go(SECTIONS.novo);
      }));
    });
  }

  const itemData = {
    Produtos: [["Buffet premium","unidade",money(98),"Ativo"],["Kit mesa posta","kit",money(145),"Ativo"],["Mesa de doces","serviço",money(1850),"Ativo"]],
    Serviços: [["Montagem de salão","evento",money(850),"Ativo"],["Cerimonial","hora",money(120),"Ativo"],["Limpeza pós-evento","evento",money(480),"Ativo"]],
    Equipe: [["Garçom","hora",money(25),"Ativo"],["Cozinheiro","hora",money(35),"Ativo"],["Supervisor","evento",money(420),"Ativo"]],
    Equipamentos: [["Mesa redonda","dia",money(55),"Ativo"],["Cadeira Tiffany","dia",money(9),"Ativo"],["Tenda 5x5","dia",money(380),"Ativo"]],
    Fretes: [["Entrega urbana","viagem",money(250),"Ativo"],["Montagem externa","evento",money(350),"Ativo"],["Desmontagem","evento",money(280),"Ativo"]],
  };

  function renderItemsTab(container) {
    const rows = itemData[state.activeItemTab] || [];
    container.innerHTML = `<div class="orc-table-wrap"><table class="orc-table"><thead><tr><th>Item/serviço</th><th>Unidade</th><th>Valor padrão</th><th>Status</th><th>Ações</th></tr></thead>
      <tbody>${rows.map(r => `<tr><td><strong>${r[0]}</strong></td><td>${r[1]}</td><td>${r[2]}</td><td>${badge(r[3])}</td><td><button class="orc-btn orc-btn--sm" data-orc-demo="Editor fictício aberto.">Editar</button></td></tr>`).join("")}</tbody></table></div>`;
    bindCommon(container);
  }

  function loadItens() {
    const target = root("orcamentosItensRoot");
    skeleton(target, function () {
      target.innerHTML = shell(`
        ${header("Itens e Serviços", "Catálogo fictício utilizado na montagem das propostas.", "◆", `<button class="orc-btn orc-btn--primary" data-orc-demo="Cadastro fictício de item aberto.">＋ Novo item</button>`, "Itens e Serviços")}
        <section class="orc-card"><div class="orc-tabs">${Object.keys(itemData).map(t => `<button class="orc-tab ${state.activeItemTab === t ? "is-active" : ""}" data-item-tab="${t}">${t}</button>`).join("")}</div></section>
        <div id="orcItemsTabContent"></div>`);
      bindCommon(target);
      const content = root("orcItemsTabContent");
      renderItemsTab(content);
      target.querySelectorAll("[data-item-tab]").forEach(button => button.addEventListener("click", function () {
        state.activeItemTab = button.dataset.itemTab;
        loadItens();
      }));
    });
  }

  function loadConfiguracoes() {
    const target = root("orcamentosConfiguracoesRoot");
    skeleton(target, function () {
      target.innerHTML = shell(`
        ${header("Configurações", "Personalize a aparência e as mensagens das propostas.", "⚙", "", "Configurações")}
        <div class="orc-grid-2">
          <section class="orc-card"><div class="orc-card__head"><h3>Padrões do orçamento</h3></div><div class="orc-form-grid">
            <label class="orc-field"><span>Percentual padrão</span><input class="orc-input" value="10%"></label>
            <label class="orc-field"><span>Validade padrão</span><input class="orc-input" value="7 dias"></label>
            <label class="orc-field orc-field--full"><span>Mensagem WhatsApp</span><textarea class="orc-textarea">Olá, {cliente}! Preparamos sua proposta {numero}. Confira todos os detalhes.</textarea></label>
            <label class="orc-field orc-field--full"><span>Mensagem E-mail</span><textarea class="orc-textarea">Olá, {cliente}. Segue a proposta comercial solicitada.</textarea></label>
            <label class="orc-field orc-field--full"><span>Mensagem Instagram</span><textarea class="orc-textarea">Oi, {cliente}! Sua proposta está pronta. Posso enviar o link?</textarea></label>
          </div></section>
          <section class="orc-card"><div class="orc-card__head"><h3>Identidade da proposta</h3></div><div class="orc-form-grid">
            <label class="orc-field orc-field--full"><span>Rodapé</span><textarea class="orc-textarea">Grupo Sabor Paraense · Qualidade e cuidado em cada detalhe.</textarea></label>
            <label class="orc-field"><span>Logo</span><input class="orc-input" type="file" accept="image/*"></label>
            <label class="orc-field"><span>Assinatura</span><input class="orc-input" type="file" accept="image/*"></label>
            <label class="orc-field"><span>Cor da proposta</span><input class="orc-input" type="color" value="#0047ab"></label>
          </div></section>
        </div>
        <div class="orc-actions"><button class="orc-btn orc-btn--primary" id="orcSaveSettings">Salvar configurações</button><button class="orc-btn" data-orc-go="${SECTIONS.proposta}">Visualizar proposta</button></div>
        <div class="orc-notice">Configurações demonstrativas: os valores não são gravados nem enviados.</div>`);
      bindCommon(target);
      root("orcSaveSettings").addEventListener("click", function () { simulate(this, "Configurações simuladas aplicadas apenas nesta tela."); });
    });
  }

  function loadRelatorios() {
    const target = root("orcamentosRelatoriosRoot");
    skeleton(target, function () {
      target.innerHTML = shell(`
        ${header("Relatórios", "Indicadores comerciais e rankings simulados.", "⌁", `<button class="orc-btn" data-orc-demo="Relatório fictício exportado.">Exportar</button>`, "Relatórios")}
        <div class="orc-kpis">
          ${kpi("Total vendido", money(318450), "R$", "+18%")}
          ${kpi("Total orçado", money(486920), "▤", "+12%")}
          ${kpi("Conversão", "65,4%", "↗", "+4,2 p.p.")}
          ${kpi("Clientes", "96", "◎", "18 novos")}
          ${kpi("Tipos ativos", "9", "◆", "Buffet lidera")}
          ${kpi("Funcionários", "24", "◉", "Equipe cadastrada")}
          ${kpi("Equipamentos", "312", "▣", "92% disponíveis")}
          ${kpi("Ticket médio", money(3804), "∅", "+6,8%")}
        </div>
        <div class="orc-grid-2">
          <section class="orc-card"><div class="orc-card__head"><h3>Orçado x convertido</h3>${badge("7 meses")}</div><div class="orc-chart">
            ${[40,55,48,72,65,82,96].map((h,i) => `<div class="orc-chart__bar" style="--h:${h}%"><span>${["Jan","Fev","Mar","Abr","Mai","Jun","Jul"][i]}</span></div>`).join("")}
          </div></section>
          <section class="orc-card"><div class="orc-card__head"><h3>Ranking por tipo</h3></div><div class="orc-list">
            ${[["Buffet",148200],["Evento",92400],["Locação",56800],["Serviço",44200],["Equipamentos",31900]].map((r,i) => `<div class="orc-list-row"><div class="orc-avatar">${i+1}</div><strong>${r[0]}</strong><span class="orc-money">${money(r[1])}</span></div>`).join("")}
          </div></section>
        </div>
        <div class="orc-grid-2"><section class="orc-card"><div class="orc-card__head"><h3>Funcionários com mais propostas</h3></div><div class="orc-list">
          ${[["Ana Paula",42],["Carlos Lima",31],["Thiago Souza",26]].map((r,i) => `<div class="orc-list-row"><div class="orc-avatar">${i+1}</div><strong>${r[0]}</strong><span>${r[1]} propostas</span></div>`).join("")}
        </div></section><section class="orc-card"><div class="orc-card__head"><h3>Equipamentos mais orçados</h3></div><div class="orc-list">
          ${[["Cadeiras",820],["Mesas",296],["Rechaud",184],["Tendas",71]].map((r,i) => `<div class="orc-list-row"><div class="orc-avatar">${i+1}</div><strong>${r[0]}</strong><span>${r[1]} unidades</span></div>`).join("")}
        </div></section></div>`);
      bindCommon(target);
    });
  }

  window.loadOrcamentosDashboard = loadDashboard;
  window.loadOrcamentosNovo = loadNovo;
  window.loadOrcamentosLista = loadLista;
  window.loadOrcamentosProposta = loadProposta;
  window.loadOrcamentosModelos = loadModelos;
  window.loadOrcamentosClientes = loadClientes;
  window.loadOrcamentosItens = loadItens;
  window.loadOrcamentosConfiguracoes = loadConfiguracoes;
  window.loadOrcamentosRelatorios = loadRelatorios;
})();
