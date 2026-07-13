/**
 * Configurações — Agentes de IA (CRUD + vínculo por módulo)
 */
(function () {
  "use strict";

  var state = { agents: [], modules: {}, editId: null, models: [] };

  function el(id) { return document.getElementById(id); }

  function esc(s) {
    if (typeof escapeHtml === "function") return escapeHtml(s);
    var d = document.createElement("div");
    d.textContent = s == null ? "" : String(s);
    return d.innerHTML;
  }

  function api(path, opts) {
    if (typeof fetchJSON === "function") return fetchJSON(path, opts);
    return fetch((window.APP_CONFIG && window.APP_CONFIG.API_URL) || "/api" + path, opts).then(function (r) { return r.json(); });
  }

  function apiForm(path, method, fd) {
    if (typeof fetchForm === "function") return fetchForm(path, method, fd);
    throw new Error("fetchForm indisponível");
  }

  function toast(msg, type) {
    if (typeof showToast === "function") showToast(msg, type);
    else alert(msg);
  }

  function avatarUrl(agent) {
    if (!agent) return null;
    if (agent.avatar_url) {
      var u = String(agent.avatar_url);
      if (u.indexOf("http://") === 0 || u.indexOf("https://") === 0) return u;
      if (typeof window.sasUsuarioFotoUrl === "function") return window.sasUsuarioFotoUrl(u.replace(/^\//, ""));
    }
    if (typeof window.sasUsuarioFotoUrl === "function" && agent.avatar) {
      return window.sasUsuarioFotoUrl(agent.avatar);
    }
    var p = agent.avatar;
    if (!p) return null;
    var api = (window.APP_CONFIG && window.APP_CONFIG.API_URL) || "https://api.gruposaborparaense.com.br/api";
    var base = api.replace(/\/api\/?$/, "") || "https://api.gruposaborparaense.com.br";
    var path = String(p).replace(/^\//, "");
    if (path.indexOf("uploads/") === 0) return base + "/" + path;
    if (path.indexOf("storage/") === 0) return base + "/" + path;
    return base + "/storage/" + path;
  }

  function previewAvatarFile(file) {
    var preview = el("aiAgentAvatarPreview");
    if (!preview || !file) return;
    var url = URL.createObjectURL(file);
    preview.innerHTML = '<img src="' + esc(url) + '" alt="" class="ai-agent-card__avatar-img" />';
    el("aiAgentFormRemoverAvatar").checked = false;
  }

  function renderCards() {
    var grid = el("aiAgentsGrid");
    if (!grid) return;
    if (!state.agents.length) {
      grid.innerHTML = '<p class="ai-agents-empty">Nenhum agente cadastrado. Clique em <strong>Novo agente</strong>.</p>';
      return;
    }
    grid.innerHTML = state.agents.map(function (a) {
      var foto = avatarUrl(a);
      var avatarHtml = foto
        ? '<img src="' + esc(foto) + '" alt="" class="ai-agent-card__avatar-img" />'
        : '<span class="ai-agent-card__avatar-fallback">🤖</span>';
      return (
        '<article class="ai-agent-card' + (a.is_active ? "" : " ai-agent-card--inactive") + '" data-id="' + a.id + '">' +
          '<div class="ai-agent-card__top">' +
            '<div class="ai-agent-card__avatar">' + avatarHtml + "</div>" +
            '<div class="ai-agent-card__meta">' +
              "<h3>" + esc(a.name) + "</h3>" +
              '<p class="ai-agent-card__role">' + esc(a.role || "—") + "</p>" +
              '<span class="ai-agent-card__badge ' + (a.is_active ? "is-on" : "is-off") + '">' + (a.is_active ? "Ativo" : "Inativo") + "</span>" +
            "</div>" +
          "</div>" +
          '<p class="ai-agent-card__desc">' + esc(a.description || "Sem descrição") + "</p>" +
          '<div class="ai-agent-card__tech">' +
            "<span>Modelo: <strong>" + esc(a.model || "—") + "</strong></span>" +
            "<span>Temp: <strong>" + Number(a.temperature).toFixed(2) + "</strong></span>" +
          "</div>" +
          '<div class="ai-agent-card__actions">' +
            '<button type="button" class="btn secondary btn-sm ai-agent-view" data-id="' + a.id + '">Ver</button>' +
            '<button type="button" class="btn primary btn-sm ai-agent-edit" data-id="' + a.id + '">Editar</button>' +
            '<button type="button" class="btn neutral btn-sm ai-agent-toggle" data-id="' + a.id + '">' + (a.is_active ? "Desativar" : "Ativar") + "</button>" +
            '<button type="button" class="btn danger btn-sm ai-agent-del" data-id="' + a.id + '">Excluir</button>' +
          "</div>" +
        "</article>"
      );
    }).join("");
  }

  function renderModuleBindings() {
    var box = el("aiAgentsModulesGrid");
    if (!box) return;
    var opts = '<option value="">— Nenhum —</option>' + state.agents.map(function (a) {
      return '<option value="' + a.id + '">' + esc(a.name) + (a.is_active ? "" : " (inativo)") + "</option>";
    }).join("");

    var labels = {
      atendimento: "Atendimento",
      rh: "RH",
      financeiro: "Financeiro",
      restaurante: "Restaurante",
      pericia: "Perícia",
      administrativo: "Administrativo",
    };

    box.innerHTML = Object.keys(labels).map(function (key) {
      var m = state.modules[key] || {};
      var sel = '<select class="ai-agents-module-select" data-module="' + key + '">' + opts + "</select>";
      return (
        '<label class="ai-agents-module-card">' +
          '<span class="ai-agents-module-card__label">' + esc(labels[key]) + "</span>" +
          sel +
          (m.agent_name ? '<small class="ai-agents-module-card__hint">Atual: ' + esc(m.agent_name) + "</small>" : "") +
        "</label>"
      );
    }).join("");

    Object.keys(labels).forEach(function (key) {
      var sel = box.querySelector('[data-module="' + key + '"]');
      var m = state.modules[key];
      if (sel && m && m.agent_id) sel.value = String(m.agent_id);
    });
  }

  function fillModelSelect(selected) {
    var sel = el("aiAgentFormModel");
    if (!sel) return;
    var models = state.models.length ? state.models : ["gpt-4o-mini", "gpt-4o", "gpt-4.1-mini", "gpt-4.1"];
    sel.innerHTML = models.map(function (m) {
      return '<option value="' + esc(m) + '">' + esc(m) + "</option>";
    }).join("");
    if (selected) sel.value = selected;
  }

  function resetForm() {
    state.editId = null;
    var form = el("aiAgentForm");
    if (!form) return;
    form.reset();
    el("aiAgentModalTitle").textContent = "Novo agente";
    el("aiAgentFormActive").checked = true;
    el("aiAgentFormTemp").value = "0.65";
    fillModelSelect("gpt-4o-mini");
    el("aiAgentFormPrompt").value = "";
    el("aiAgentFormRemoverAvatar").checked = false;
    el("aiAgentAvatarPreview").innerHTML = '<span class="ai-agent-card__avatar-fallback">🤖</span>';
    var fi = el("aiAgentFormAvatar");
    if (fi) fi.value = "";
  }

  function openModalNovo() {
    resetForm();
    el("aiAgentModal").classList.add("active");
  }

  async function openModalEditar(id, viewOnly) {
    try {
      var a = await api("/ai-agents/" + id);
      state.editId = id;
      el("aiAgentModalTitle").textContent = viewOnly ? "Visualizar agente" : "Editar agente";
      el("aiAgentFormName").value = a.name || "";
      el("aiAgentFormRole").value = a.role || "";
      el("aiAgentFormDesc").value = a.description || "";
      el("aiAgentFormPrompt").value = a.system_prompt || "";
      fillModelSelect(a.model || "gpt-4o-mini");
      el("aiAgentFormTemp").value = String(a.temperature ?? 0.65);
      el("aiAgentFormActive").checked = !!a.is_active;
      var url = avatarUrl(a);
      el("aiAgentAvatarPreview").innerHTML = url
        ? '<img src="' + esc(url) + '" alt="" class="ai-agent-card__avatar-img" />'
        : '<span class="ai-agent-card__avatar-fallback">🤖</span>';
      var disabled = !!viewOnly;
      el("aiAgentForm").querySelectorAll("input,textarea,select,button[type=submit]").forEach(function (inp) {
        if (inp.id === "aiAgentFormCancel") return;
        if (inp.type === "file" && disabled) inp.disabled = true;
        else if (inp.tagName === "BUTTON" && inp.type === "submit") inp.disabled = disabled;
        else if (!disabled) { inp.disabled = false; inp.readOnly = false; }
        else if (inp.type === "checkbox") inp.disabled = true;
        else { inp.readOnly = true; }
      });
      el("aiAgentModal").classList.add("active");
    } catch (e) {
      toast(e?.message || "Erro ao carregar agente", "error");
    }
  }

  function closeModal() {
    el("aiAgentModal")?.classList.remove("active");
    state.editId = null;
    el("aiAgentForm")?.querySelectorAll("input,textarea,select").forEach(function (inp) {
      inp.disabled = false;
      inp.readOnly = false;
    });
    var sub = el("aiAgentForm")?.querySelector('button[type="submit"]');
    if (sub) sub.disabled = false;
  }

  async function salvarAgente(e) {
    e.preventDefault();
    var fd = new FormData();
    fd.set("name", el("aiAgentFormName").value.trim());
    fd.set("role", el("aiAgentFormRole").value.trim());
    fd.set("description", el("aiAgentFormDesc").value.trim());
    fd.set("system_prompt", el("aiAgentFormPrompt").value.trim());
    fd.set("model", el("aiAgentFormModel").value);
    fd.set("temperature", el("aiAgentFormTemp").value);
    fd.set("is_active", el("aiAgentFormActive").checked ? "1" : "0");
    if (el("aiAgentFormRemoverAvatar").checked) fd.set("remover_avatar", "1");
    var file = el("aiAgentFormAvatar")?.files?.[0];
    if (file) fd.append("avatar", file);

    try {
      if (state.editId) {
        await apiForm("/ai-agents/" + state.editId, "POST", fd);
        toast("Agente atualizado.", "success");
      } else {
        await apiForm("/ai-agents", "POST", fd);
        toast("Agente criado.", "success");
      }
      closeModal();
      await loadIaAgentes();
      if (typeof window.sasIaLoadBranding === "function") window.sasIaLoadBranding().catch(function () {});
    } catch (err) {
      toast(err?.message || "Erro ao salvar", "error");
    }
  }

  async function salvarModulos() {
    var bindings = {};
    document.querySelectorAll(".ai-agents-module-select").forEach(function (sel) {
      bindings[sel.getAttribute("data-module")] = sel.value || null;
    });
    try {
      await api("/ai-agents/modules", {
        method: "PUT",
        body: JSON.stringify({ bindings: bindings }),
      });
      toast("Módulos vinculados aos agentes.", "success");
      await loadIaAgentes();
    } catch (e) {
      toast(e?.message || "Erro ao salvar módulos", "error");
    }
  }

  async function loadIaAgentes() {
    try {
      var data = await api("/ai-agents");
      state.agents = data.agents || [];
      state.modules = data.modules && typeof data.modules === "object" ? data.modules : {};
      state.models = data.models_sugeridos || [];
      renderCards();
      renderModuleBindings();
    } catch (e) {
      toast(e?.message || "Erro ao carregar agentes", "error");
    }
  }

  window.loadIaAgentes = loadIaAgentes;

  window.setupIaAgentesModule = function () {
    el("aiAgentsNovoBtn")?.addEventListener("click", openModalNovo);
    el("aiAgentsRecarregarBtn")?.addEventListener("click", function () { loadIaAgentes(); });
    el("aiAgentFormCancel")?.addEventListener("click", closeModal);
    el("aiAgentModalClose")?.addEventListener("click", closeModal);
    el("aiAgentForm")?.addEventListener("submit", salvarAgente);
    el("aiAgentFormAvatar")?.addEventListener("change", function (ev) {
      var file = ev.target.files && ev.target.files[0];
      if (file) previewAvatarFile(file);
    });
    el("aiAgentsSalvarModulosBtn")?.addEventListener("click", salvarModulos);
    el("aiAgentModal")?.addEventListener("click", function (ev) {
      if (ev.target === el("aiAgentModal")) closeModal();
    });

    el("aiAgentsGrid")?.addEventListener("click", function (ev) {
      var btn = ev.target.closest("button");
      if (!btn) return;
      var id = btn.getAttribute("data-id");
      if (btn.classList.contains("ai-agent-edit")) openModalEditar(id, false);
      if (btn.classList.contains("ai-agent-view")) openModalEditar(id, true);
      if (btn.classList.contains("ai-agent-toggle")) {
        api("/ai-agents/" + id + "/toggle-active", { method: "POST" })
          .then(function () { toast("Status atualizado.", "success"); loadIaAgentes(); })
          .catch(function (e) { toast(e?.message || "Erro", "error"); });
      }
      if (btn.classList.contains("ai-agent-del") && confirm("Excluir este agente?")) {
        api("/ai-agents/" + id, { method: "DELETE" })
          .then(function () { toast("Excluído.", "success"); loadIaAgentes(); })
          .catch(function (e) { toast(e?.message || "Erro", "error"); });
      }
    });
  };
})();
