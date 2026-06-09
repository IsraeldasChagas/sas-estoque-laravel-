/**
 * Painel de Configurações do Sistema — SAS-Estoque
 */
(function () {
  "use strict";

  let cfgPodeEditar = false;

  function cfgToast(msg, type = "info") {
    const fn = typeof showToast === "function" ? showToast : window.showToast;
    if (typeof fn === "function") fn(msg, type);
  }

  async function cfgFetch(path, opts = {}) {
    if (typeof window.fetchJSON === "function") return window.fetchJSON(path, opts);
    throw new Error("fetchJSON não disponível");
  }

  function cfgCard(icon, title, text, btnLabel, action) {
    const btn = btnLabel
      ? `<button type="button" class="btn secondary cfg-go" data-cfg-action="${action}">${btnLabel}</button>`
      : "";
    return `<article class="cfg-card">
      <span class="cfg-card__icon" aria-hidden="true">${icon}</span>
      <h3 class="cfg-card__title">${title}</h3>
      <p class="cfg-card__text">${text}</p>
      ${btn}
    </article>`;
  }

  async function cfgBaixarMapaPdf() {
    if (typeof API_URL === "undefined" || !window.currentUser && typeof getUser === "function") {
      try { window.currentUser = getUser(); } catch (_) {}
    }
    const u = window.currentUser || (typeof getUser === "function" ? getUser() : null);
    try {
      const res = await fetch(`${API_URL}/admin/mapa-sistema.pdf`, {
        headers: {
          ...(u?.token ? { Authorization: `Bearer ${u.token}` } : {}),
          ...(u?.id != null ? { "X-Usuario-Id": String(u.id) } : {}),
        },
      });
      if (!res.ok) throw new Error("Não foi possível gerar o PDF");
      const blob = await res.blob();
      const url = URL.createObjectURL(blob);
      window.open(url, "_blank");
      setTimeout(() => URL.revokeObjectURL(url), 60000);
    } catch (e) {
      cfgToast(e?.message || "Erro ao abrir mapa do sistema", "error");
    }
  }

  function cfgPreencherForm(config) {
    const map = {
      cfgEmpresaNome: "empresa_nome",
      cfgEmpresaCnpj: "empresa_cnpj",
      cfgEmpresaEmail: "empresa_email",
      cfgEmpresaTelefone: "empresa_telefone",
      cfgEmpresaEndereco: "empresa_endereco",
      cfgSuporteEmail: "suporte_email",
      cfgObservacoes: "observacoes_sistema",
    };
    Object.entries(map).forEach(([id, key]) => {
      const el = document.getElementById(id);
      if (el) el.value = config?.[key] ?? "";
    });
    const btn = document.getElementById("cfgSalvarBtn");
    if (btn) {
      btn.disabled = !cfgPodeEditar;
      btn.title = cfgPodeEditar ? "" : "Somente administrador pode salvar";
    }
    document.querySelectorAll("#cfgForm input, #cfgForm textarea").forEach((el) => {
      if (!cfgPodeEditar) el.setAttribute("readonly", "readonly");
      else el.removeAttribute("readonly");
    });
  }

  function cfgPreencherStats(resumo) {
    const el = document.getElementById("cfgStats");
    if (!el || !resumo) return;
    const items = [
      ["Usuários", resumo.usuarios],
      ["Unidades", resumo.unidades],
      ["Produtos", resumo.produtos],
      ["Funcionários", resumo.funcionarios],
      ["Backups", resumo.backups],
    ];
    el.innerHTML = items.map(([l, v]) =>
      `<div class="cfg-stat"><span>${l}</span><strong>${v ?? 0}</strong></div>`
    ).join("");
  }

  function cfgMontarCards() {
    const grid = document.getElementById("cfgCardsGrid");
    if (!grid) return;
    const perfil = (window.currentUser?.perfil || "").toString().trim().toUpperCase();
    const isAdmin = perfil === "ADMIN";
    let html = [
      cfgCard("👥", "Usuários", "Cadastro, perfis e permissões de acesso ao sistema.", "Abrir usuários", "usuarios"),
      cfgCard("🏢", "Unidades", "Filiais e locais de operação do grupo.", "Abrir unidades", "unidades"),
      cfgCard("📋", "Logs e auditoria", "Rastreio de ações importantes no sistema.", "Ver logs", "logs"),
      cfgCard("📁", "Documentos empresa", "Alvarás, licenças e documentos corporativos.", "Abrir documentos", "alvara"),
      cfgCard("👤", "Minha conta", "Alterar senha e dados da sua conta.", "Minha conta", "minhaConta"),
      cfgCard("🗺️", "Mapa do sistema", "Diagrama UML e visão dos módulos (PDF).", "Baixar PDF", "mapaPdf"),
    ].join("");
    if (isAdmin) {
      html += cfgCard("🗄️", "Backup / Restaurar", "Gerar backup completo ou restaurar dados do servidor.", "Abrir backup", "backup");
    }
    grid.innerHTML = html;
  }

  async function loadConfiguracoesPainel() {
    cfgMontarCards();
    try {
      const data = await cfgFetch("/configuracoes-sistema/resumo");
      cfgPodeEditar = !!data?.usuario?.pode_editar;
      cfgPreencherStats(data?.resumo);
      cfgPreencherForm(data?.config || {});
      const badge = document.getElementById("cfgPerfilBadge");
      if (badge && data?.usuario) {
        badge.textContent = `${data.usuario.nome || "—"} · ${data.usuario.perfil || ""}`;
      }
    } catch (e) {
      cfgToast(e?.message || "Falha ao carregar configurações", "error");
    }
  }

  async function cfgSalvar(e) {
    e?.preventDefault();
    if (!cfgPodeEditar) {
      cfgToast("Somente administrador pode salvar.", "error");
      return;
    }
    const payload = {
      empresa_nome: document.getElementById("cfgEmpresaNome")?.value?.trim() || "",
      empresa_cnpj: document.getElementById("cfgEmpresaCnpj")?.value?.trim() || "",
      empresa_email: document.getElementById("cfgEmpresaEmail")?.value?.trim() || "",
      empresa_telefone: document.getElementById("cfgEmpresaTelefone")?.value?.trim() || "",
      empresa_endereco: document.getElementById("cfgEmpresaEndereco")?.value?.trim() || "",
      suporte_email: document.getElementById("cfgSuporteEmail")?.value?.trim() || "",
      observacoes_sistema: document.getElementById("cfgObservacoes")?.value?.trim() || "",
    };
    try {
      await cfgFetch("/configuracoes-sistema", { method: "POST", body: JSON.stringify({ config: payload }) });
      cfgToast("Configurações salvas.", "success");
    } catch (err) {
      cfgToast(err?.message || "Erro ao salvar", "error");
    }
  }

  function cfgBind() {
    if (window.__cfgPainelBound) return;
    window.__cfgPainelBound = true;

    document.getElementById("cfgForm")?.addEventListener("submit", (e) => cfgSalvar(e));
    document.getElementById("cfgCardsGrid")?.addEventListener("click", (ev) => {
      const btn = ev.target.closest("[data-cfg-action]");
      if (!btn) return;
      const action = btn.getAttribute("data-cfg-action");
      if (action === "backup") {
        if (typeof abrirBackupModal === "function") abrirBackupModal();
        return;
      }
      if (action === "mapaPdf") {
        cfgBaixarMapaPdf();
        return;
      }
      if (action && typeof navigateTo === "function") navigateTo(action);
    });
  }

  cfgBind();
  window.loadConfiguracoesPainel = loadConfiguracoesPainel;
})();
