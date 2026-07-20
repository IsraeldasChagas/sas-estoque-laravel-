/**
 * Delivery Logistics — VendaFácil parity for freight and drivers.
 * Load after delivery.js so these two page loaders take precedence.
 */
(function () {
  "use strict";

  const $ = (id) => document.getElementById(id);
  const esc = (value) => String(value == null ? "" : value)
    .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;").replace(/'/g, "&#39;");
  const api = (path, options) => {
    if (typeof window.fetchJSON !== "function") throw new Error("Conexão com a API indisponível.");
    return window.fetchJSON(`/delivery${path}`, options || {});
  };
  const toast = (message, type) => window.showToast?.(message, type || "info");
  const money = (value) => Number(value || 0).toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
  const digits = (value) => String(value || "").replace(/\D/g, "");
  const value = (form, name) => form.elements[name]?.value ?? "";
  const checked = (form, name) => !!form.elements[name]?.checked;

  function imageUrl(url) {
    if (!url) return "";
    if (/^(https?:|data:)/i.test(url)) return url;
    const base = String(window.APP_CONFIG?.API_URL || "").replace(/\/api\/?$/, "");
    return `${base}${String(url).startsWith("/") ? "" : "/"}${url}`;
  }

  function cepMask(raw) {
    const number = digits(raw).slice(0, 8);
    return number.length > 5 ? `${number.slice(0, 5)}-${number.slice(5)}` : number;
  }

  function whatsappMask(raw) {
    const number = digits(raw).slice(0, 13);
    if (number.length <= 2) return number;
    const country = number.length > 11 ? `+${number.slice(0, 2)} ` : "";
    const local = number.length > 11 ? number.slice(2) : number;
    if (local.length <= 2) return `${country}(${local}`;
    if (local.length <= 7) return `${country}(${local.slice(0, 2)}) ${local.slice(2)}`;
    return `${country}(${local.slice(0, 2)}) ${local.slice(2, -4)}-${local.slice(-4)}`;
  }

  function bindMasks(root) {
    root.querySelectorAll("[data-vfl-cep]").forEach((input) => {
      input.addEventListener("input", () => { input.value = cepMask(input.value); });
    });
    root.querySelectorAll("[data-vfl-phone]").forEach((input) => {
      input.addEventListener("input", () => { input.value = whatsappMask(input.value); });
    });
  }

  function freightResult(state, result) {
    if (state === "waiting") {
      return `<span class="vfl-status vfl-status--waiting">Aguardando cálculo</span>
        <div class="vfl-result-price">R$ —</div>
        <p class="vfl-result-rule">Informe o CEP do cliente para consultar a regra de entrega.</p>`;
    }
    if (state === "error") {
      return `<span class="vfl-status vfl-status--error">Erro</span>
        <div class="vfl-result-price">Não calculado</div>
        <p class="vfl-result-rule">${esc(result?.message || "Não foi possível calcular o frete.")}</p>`;
    }

    const blocked = !!result.bloqueado;
    const label = result.label || (blocked ? "Entrega indisponível" : "Frete calculado");
    const message = result.message || result.mensagem || "";
    return `<span class="vfl-status ${blocked ? "vfl-status--blocked" : "vfl-status--ok"}">${blocked ? "Bloqueado" : "OK"}</span>
      <div class="vfl-route-metrics">
        <span><small>Distância</small><strong>${esc(result.distancia || result.distancia_km ? `${result.distancia || result.distancia_km} km` : "—")}</strong></span>
        <span><small>Tempo estimado</small><strong>${esc(result.tempo || result.tempo_min ? `${result.tempo || result.tempo_min} min` : "—")}</strong></span>
      </div>
      <div class="vfl-result-price">${blocked ? "Indisponível" : money(result.frete_valor)}</div>
      <strong class="vfl-result-label">${esc(label)}</strong>
      <p class="vfl-result-rule">${esc(message)}</p>
      <div class="vfl-result-actions">
        <button class="vfl-btn vfl-btn--success" type="button" data-vfl-copy>Copiar mensagem</button>
        <button class="vfl-btn" type="button" data-vfl-whatsapp>WhatsApp do cliente</button>
        <button class="vfl-btn vfl-btn--ghost" type="button" data-vfl-reset>Limpar</button>
      </div>`;
  }

  function freightMessage(result, address) {
    if (result.bloqueado) {
      return `Olá! No momento não realizamos entrega para o CEP ${address.cep}. ${result.message || result.mensagem || ""}`.trim();
    }
    return `Olá! O frete para ${address.cep}${address.street ? `, ${address.street}` : ""} ficou em ${money(result.frete_valor)}. ${result.message || result.mensagem || ""}`.trim();
  }

  async function loadDeliveryFretes() {
    const root = $("deliveryFretesRoot");
    if (!root) return;
    const [bandsResponse, config] = await Promise.all([api("/fretes/faixas"), api("/configuracoes")]);
    const bands = bandsResponse.items || [];
    const modeLabels = {
      padrao_unico: "Taxa fixa",
      fixed: "Taxa fixa",
      faixas_cep: "Faixas de CEP",
      cep_band: "Faixas de CEP",
      google_distancia: "Google Maps",
      osrm_distancia: "OSRM / OpenStreetMap",
    };
    const modeName = modeLabels[config.frete_modo] || "Taxa fixa";

    root.innerHTML = `<div class="vfl-page">
      <nav class="vfl-breadcrumb"><button type="button" data-vfl-dashboard>Pedidos</button><span>/</span><strong>Calcular frete</strong></nav>
      <header class="vfl-heading">
        <div><h2>Calculadora de frete</h2><p>Consulte rapidamente a taxa de entrega para o endereço do cliente.</p></div>
        <div class="vfl-heading-actions"><span class="vfl-mode">Modo atual: <strong>${esc(modeName)}</strong></span><button class="vfl-btn" type="button" data-vfl-settings>⚙ Configurações</button></div>
      </header>
      <div class="vfl-calculator">
        <form id="vflFreightForm" class="vfl-panel vfl-panel--blue">
          <div class="vfl-panel-title"><span class="vfl-panel-icon">⌖</span><div><h3>Endereço do cliente</h3><p>O CEP é suficiente para calcular. Complete o endereço se desejar.</p></div></div>
          <label class="vfl-field"><span>CEP <b>*</b></span><input name="cep" data-vfl-cep inputmode="numeric" maxlength="9" placeholder="00000-000" required></label>
          <details class="vfl-address-more"><summary>＋ Informar endereço completo (opcional)</summary>
            <div class="vfl-form-grid">
              <label class="vfl-field vfl-span-8"><span>Rua / logradouro</span><input name="logradouro" maxlength="180"></label>
              <label class="vfl-field vfl-span-4"><span>Número</span><input name="numero" maxlength="40"></label>
              <label class="vfl-field vfl-span-6"><span>Bairro</span><input name="bairro" maxlength="120"></label>
              <label class="vfl-field vfl-span-6"><span>Complemento</span><input name="complemento" maxlength="255"></label>
              <label class="vfl-field vfl-span-8"><span>Cidade</span><input name="cidade" maxlength="120"></label>
              <label class="vfl-field vfl-span-4"><span>UF</span><input name="uf" maxlength="2"></label>
            </div>
          </details>
          <div class="vfl-form-grid">
            <label class="vfl-field vfl-span-6"><span>Valor do pedido (opcional)</span><input name="subtotal" type="number" min="0" step="0.01" placeholder="R$ 0,00"></label>
            <label class="vfl-field vfl-span-6"><span>WhatsApp do cliente (opcional)</span><input name="telefone" data-vfl-phone inputmode="tel" maxlength="20"></label>
          </div>
          <button class="vfl-btn vfl-btn--primary vfl-btn--wide" type="submit">Calcular frete</button>
        </form>
        <section class="vfl-panel vfl-panel--green">
          <div class="vfl-panel-title"><span class="vfl-panel-icon">✓</span><div><h3>Resultado</h3><p>Taxa e disponibilidade conforme as configurações atuais.</p></div></div>
          <div id="vflFreightResult" class="vfl-result">${freightResult("waiting")}</div>
        </section>
      </div>
      <section class="vfl-management">
        <button class="vfl-management-toggle" type="button" data-vfl-bands-toggle aria-expanded="false">
          <span><strong>Gerenciar faixas de CEP</strong><small>Cadastre regiões e taxas para o modo Faixas de CEP.</small></span><span data-vfl-chevron>⌄</span>
        </button>
        <div class="vfl-management-body" id="vflBandsBody" hidden>
          <form id="vflBandForm" class="vfl-band-form">
            <input name="id" type="hidden">
            <label class="vfl-field"><span>Identificação</span><input name="label" maxlength="120" placeholder="Ex.: Centro"></label>
            <label class="vfl-field"><span>CEP inicial</span><input name="cep_inicio" data-vfl-cep maxlength="9" required></label>
            <label class="vfl-field"><span>CEP final</span><input name="cep_fim" data-vfl-cep maxlength="9" required></label>
            <label class="vfl-field"><span>Taxa (R$)</span><input name="taxa" type="number" min="0" step="0.01" required></label>
            <label class="vfl-field"><span>Ordem</span><input name="ordem" type="number" min="0" value="0"></label>
            <label class="vfl-check"><input name="ativo" type="checkbox" checked> Ativa</label>
            <div class="vfl-band-actions"><button class="vfl-btn vfl-btn--primary" type="submit">Salvar faixa</button><button class="vfl-btn" type="button" data-vfl-band-cancel hidden>Cancelar</button></div>
          </form>
          <div class="vfl-table-wrap"><table class="vfl-table"><thead><tr><th>Região</th><th>Faixa de CEP</th><th>Taxa</th><th>Status</th><th>Ações</th></tr></thead>
            <tbody>${renderBandRows(bands)}</tbody></table></div>
        </div>
      </section>
    </div>`;

    bindMasks(root);
    root.querySelector("[data-vfl-dashboard]").onclick = () => window.navigateTo?.("deliveryPedidos");
    root.querySelector("[data-vfl-settings]").onclick = () => window.navigateTo?.("deliveryConfiguracoes");
    root.querySelector("[data-vfl-bands-toggle]").onclick = (event) => {
      const button = event.currentTarget;
      const body = $("vflBandsBody");
      const open = body.hidden;
      body.hidden = !open;
      button.setAttribute("aria-expanded", String(open));
      button.querySelector("[data-vfl-chevron]").textContent = open ? "⌃" : "⌄";
    };

    let lastResult = null;
    let lastAddress = null;
    $("vflFreightForm").onsubmit = async (event) => {
      event.preventDefault();
      const form = event.currentTarget;
      const submit = form.querySelector('[type="submit"]');
      const cep = digits(value(form, "cep"));
      if (cep.length !== 8) return toast("Informe um CEP com 8 dígitos.", "error");
      submit.disabled = true;
      try {
        lastAddress = {
          cep: cepMask(cep), street: value(form, "logradouro").trim(),
          numero: value(form, "numero").trim(), bairro: value(form, "bairro").trim(),
          cidade: value(form, "cidade").trim(), uf: value(form, "uf").trim().toUpperCase(),
        };
        lastResult = await api("/fretes/calcular", {
          method: "POST",
          body: JSON.stringify({
            cep, subtotal: value(form, "subtotal") === "" ? 0 : Number(value(form, "subtotal")),
            fulfillment: "entrega", telefone: digits(value(form, "telefone")),
            logradouro: lastAddress.street, numero: lastAddress.numero, bairro: lastAddress.bairro,
            cidade: lastAddress.cidade, uf: lastAddress.uf, complemento: value(form, "complemento").trim(),
          }),
        });
        $("vflFreightResult").innerHTML = freightResult("done", lastResult);
      } catch (error) {
        lastResult = null;
        $("vflFreightResult").innerHTML = freightResult("error", error);
      } finally {
        submit.disabled = false;
      }
    };

    $("vflFreightResult").onclick = async (event) => {
      if (event.target.closest("[data-vfl-copy]") && lastResult) {
        await navigator.clipboard.writeText(freightMessage(lastResult, lastAddress));
        toast("Mensagem copiada.", "success");
      }
      if (event.target.closest("[data-vfl-whatsapp]") && lastResult) {
        const phone = digits(value($("vflFreightForm"), "telefone"));
        if (!phone) return toast("Informe o WhatsApp do cliente.", "error");
        const destination = phone.length <= 11 ? `55${phone}` : phone;
        window.open(`https://wa.me/${destination}?text=${encodeURIComponent(freightMessage(lastResult, lastAddress))}`, "_blank", "noopener");
      }
      if (event.target.closest("[data-vfl-reset]")) {
        $("vflFreightForm").reset();
        $("vflFreightResult").innerHTML = freightResult("waiting");
        lastResult = null;
      }
    };

    bindBandManagement(root, bands);
  }

  function renderBandRows(bands) {
    if (!bands.length) return `<tr><td colspan="5" class="vfl-empty">Nenhuma faixa de CEP cadastrada.</td></tr>`;
    return bands.map((band) => `<tr>
      <td><strong>${esc(band.label || "Faixa")}</strong><small>Ordem ${Number(band.ordem || 0)}</small></td>
      <td>${esc(cepMask(band.cep_inicio))} até ${esc(cepMask(band.cep_fim))}</td>
      <td><strong>${money(band.taxa)}</strong></td>
      <td><span class="vfl-pill ${Number(band.ativo) ? "is-active" : "is-inactive"}">${Number(band.ativo) ? "Ativa" : "Inativa"}</span></td>
      <td><button class="vfl-btn vfl-btn--small" type="button" data-vfl-band-edit="${band.id}">Editar</button>
        <button class="vfl-btn vfl-btn--small vfl-btn--danger" type="button" data-vfl-band-delete="${band.id}">Excluir</button></td>
    </tr>`).join("");
  }

  function bindBandManagement(root, bands) {
    const form = $("vflBandForm");
    const reset = () => {
      form.reset(); form.elements.id.value = ""; form.elements.ativo.checked = true;
      form.querySelector("[data-vfl-band-cancel]").hidden = true;
    };
    form.querySelector("[data-vfl-band-cancel]").onclick = reset;
    form.onsubmit = async (event) => {
      event.preventDefault();
      const id = value(form, "id");
      await api(id ? `/fretes/faixas/${id}` : "/fretes/faixas", {
        method: id ? "PUT" : "POST",
        body: JSON.stringify({
          label: value(form, "label").trim() || null,
          cep_inicio: digits(value(form, "cep_inicio")), cep_fim: digits(value(form, "cep_fim")),
          taxa: Number(value(form, "taxa")), ordem: Number(value(form, "ordem") || 0), ativo: checked(form, "ativo"),
        }),
      });
      toast(id ? "Faixa atualizada." : "Faixa cadastrada.", "success");
      await loadDeliveryFretes();
    };
    root.addEventListener("click", async (event) => {
      const edit = event.target.closest("[data-vfl-band-edit]");
      const remove = event.target.closest("[data-vfl-band-delete]");
      if (edit) {
        const band = bands.find((item) => Number(item.id) === Number(edit.dataset.vflBandEdit));
        if (!band) return;
        form.elements.id.value = band.id;
        form.elements.label.value = band.label || "";
        form.elements.cep_inicio.value = cepMask(band.cep_inicio);
        form.elements.cep_fim.value = cepMask(band.cep_fim);
        form.elements.taxa.value = band.taxa;
        form.elements.ordem.value = band.ordem || 0;
        form.elements.ativo.checked = !!Number(band.ativo);
        form.querySelector("[data-vfl-band-cancel]").hidden = false;
        $("vflBandsBody").hidden = false;
        form.scrollIntoView({ behavior: "smooth", block: "center" });
      }
      if (remove && confirm("Excluir esta faixa de CEP?")) {
        await api(`/fretes/faixas/${remove.dataset.vflBandDelete}`, { method: "DELETE" });
        toast("Faixa excluída.", "success");
        await loadDeliveryFretes();
      }
    });
  }

  async function loadDeliveryEntregadores() {
    const root = $("deliveryEntregadoresRoot");
    if (!root) return;
    const items = (await api("/entregadores")).items || [];
    root.innerHTML = `<div class="vfl-page vfl-drivers">
      <nav class="vfl-breadcrumb"><button type="button" data-vfl-orders>Pedidos</button><span>/</span><strong>Meus entregadores</strong></nav>
      <header class="vfl-heading"><div><h2>Meus entregadores</h2><p>Cadastre o motoboy e envie o link do app pelo WhatsApp dele. Ele instala no celular e acompanha as entregas.</p></div>
        <button class="vfl-btn vfl-btn--primary" type="button" data-vfl-new-driver>＋ Novo entregador</button></header>
      <div class="vfl-table-card"><div class="vfl-table-wrap"><table class="vfl-table vfl-driver-table">
        <thead><tr><th>Entregador</th><th>Moto</th><th>Placa</th><th>Ordem</th><th>Status</th><th>Ações</th></tr></thead>
        <tbody>${renderDriverRows(items)}</tbody></table></div></div>
    </div>`;
    root.querySelector("[data-vfl-orders]").onclick = () => window.navigateTo?.("deliveryPedidos");
    root.onclick = (event) => {
      if (event.target.closest("[data-vfl-new-driver]")) renderDriverEditor(root, null);
      const edit = event.target.closest("[data-vfl-driver-edit]");
      if (edit) openDriverEditor(root, Number(edit.dataset.vflDriverEdit));
    };
  }

  function renderDriverRows(items) {
    if (!items.length) return `<tr><td colspan="6" class="vfl-empty">Nenhum entregador cadastrado.</td></tr>`;
    return items.map((driver) => {
      const photo = imageUrl(driver.foto_url);
      const initials = String(driver.nome || "?").trim().split(/\s+/).slice(0, 2).map((part) => part[0]).join("").toUpperCase();
      const waApp = driver.pin_disponivel && driver.url_app_whatsapp ? driver.url_app_whatsapp : "";
      return `<tr><td><div class="vfl-driver"><span class="vfl-avatar">${photo ? `<img src="${esc(photo)}" alt="">` : esc(initials)}</span>
          <span><strong>${esc(driver.nome)}</strong><small>${esc(driver.whatsapp || "Sem WhatsApp")}</small></span></div></td>
        <td><strong>${esc(driver.moto_modelo || "—")}</strong><small>${esc(driver.moto_cor || "Cor não informada")}</small></td>
        <td><span class="vfl-plate">${esc(driver.moto_placa || "—")}</span></td><td>${Number(driver.ordem || 0)}</td>
        <td><span class="vfl-pill ${Number(driver.ativo) ? "is-active" : "is-inactive"}">${Number(driver.ativo) ? "Ativo" : "Inativo"}</span></td>
        <td style="white-space:nowrap">
          ${waApp ? `<a class="vfl-btn vfl-btn--small vfl-btn--success" href="${esc(waApp)}" target="_blank" rel="noopener noreferrer" title="Enviar app no WhatsApp">WhatsApp app</a> ` : ""}
          <button class="vfl-btn vfl-btn--small" type="button" data-vfl-driver-edit="${driver.id}">Editar</button>
        </td></tr>`;
    }).join("");
  }

  async function openDriverEditor(root, id) {
    renderDriverEditor(root, await api(`/entregadores/${id}`));
  }

  function renderDriverPinBlock(driver) {
    const pin = driver?.acesso_pin || "";
    const disponivel = !!driver?.pin_disponivel;
    const usado = !!driver?.pin_usado;
    let status = "Sem PIN — gere um agora.";
    let statusColor = "#b45309";
    if (disponivel) {
      status = "PIN ativo (uso único). Envie só para o WhatsApp cadastrado.";
      statusColor = "#166534";
    } else if (usado) {
      status = "PIN já usado. Gere outro para o motoboy entrar de novo.";
      statusColor = "#b45309";
    }
    return `<div style="margin:8px 0 12px;padding:14px;border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc">
      <strong style="display:block;margin-bottom:6px">🔐 PIN do app (6 dígitos · uso único)</strong>
      <p style="margin:0 0 10px;font-size:13px;color:${statusColor}">${status}</p>
      <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <input readonly style="flex:0 0 120px;font-size:22px;letter-spacing:.2em;text-align:center;font-weight:700" id="vflDriverPin" value="${esc(pin || "——————")}">
        <button class="vfl-btn vfl-btn--primary" type="button" data-vfl-gerar-pin>Gerar novo PIN</button>
        ${pin ? `<button class="vfl-btn" type="button" data-vfl-copy-pin>Copiar PIN</button>` : ""}
      </div>
    </div>`;
  }

  function renderDriverEditor(root, driver) {
    const editing = !!driver;
    const photo = imageUrl(driver?.foto_url);
    root.innerHTML = `<div class="vfl-page vfl-driver-editor">
      <nav class="vfl-breadcrumb"><button type="button" data-vfl-back-drivers>Meus entregadores</button><span>/</span><strong>${editing ? "Editar" : "Novo entregador"}</strong></nav>
      <section class="vfl-driver-form-card">
        <header><h2>${editing ? "Editar entregador" : "Novo entregador"}</h2><p>Preencha os dados usados na operação de entrega.</p></header>
        <form id="vflDriverForm">
          <div class="vfl-photo-field">
            <span class="vfl-photo-preview ${photo ? "has-photo" : ""}" id="vflDriverPhoto">${photo ? `<img src="${esc(photo)}" alt="Foto do entregador">` : "Foto"}</span>
            <div><label class="vfl-btn" for="vflDriverFile">Escolher foto</label><input id="vflDriverFile" name="foto" type="file" accept="image/jpeg,image/png,image/webp,image/gif" hidden>
              <button class="vfl-btn vfl-btn--ghost" type="button" data-vfl-remove-photo ${photo ? "" : "hidden"}>Remover foto</button><small>Opcional. JPG, PNG, WebP ou GIF, até 2 MB.</small></div>
          </div>
          <div class="vfl-form-grid">
            <label class="vfl-field vfl-span-12"><span>Nome <b>*</b></span><input name="nome" maxlength="255" value="${esc(driver?.nome || "")}" required></label>
            <label class="vfl-field vfl-span-12"><span>WhatsApp <b>*</b></span><input name="whatsapp" data-vfl-phone maxlength="20" value="${esc(whatsappMask(driver?.whatsapp || ""))}" required></label>
            <label class="vfl-field vfl-span-6"><span>Modelo da moto</span><input name="moto_modelo" maxlength="120" value="${esc(driver?.moto_modelo || "")}"></label>
            <label class="vfl-field vfl-span-6"><span>Cor</span><input name="moto_cor" maxlength="64" value="${esc(driver?.moto_cor || "")}"></label>
            <label class="vfl-field vfl-span-6"><span>Placa</span><input name="moto_placa" maxlength="16" value="${esc(driver?.moto_placa || "")}"></label>
            <label class="vfl-field vfl-span-6"><span>Ordem</span><input name="ordem" type="number" min="0" max="99999" value="${Number(driver?.ordem || 0)}"></label>
            <label class="vfl-check vfl-span-12"><input name="ativo" type="checkbox" ${driver ? (Number(driver.ativo) ? "checked" : "") : "checked"}> Entregador ativo</label>
          </div>
          ${editing ? renderDriverPinBlock(driver) : `<div style="margin:8px 0 12px;padding:12px;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc"><strong>PIN do app</strong><p style="margin:6px 0 0;font-size:13px;color:#64748b">Ao salvar, o sistema gera um PIN de 6 dígitos (uso único) para enviar no WhatsApp cadastrado.</p></div>`}
          ${editing && driver?.url_app ? `<div style="margin:8px 0 12px;padding:14px;border:1px solid #bbf7d0;border-radius:12px;background:#f0fdf4">
            <strong style="display:block;margin-bottom:6px">📱 App do motoboy</strong>
            <p style="margin:0 0 10px;font-size:13px;color:#166534">Envie só para o WhatsApp cadastrado. O PIN funciona <strong>uma vez</strong>; se ele sair do app ou precisar entrar de novo, gere outro PIN.</p>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px">
              <input readonly style="flex:1;min-width:180px" id="vflDriverAppUrl" value="${esc(driver.url_app)}">
              <button class="vfl-btn" type="button" data-vfl-copy-app>Copiar link</button>
            </div>
            ${driver.pin_disponivel && driver.url_app_whatsapp
              ? `<a class="vfl-btn vfl-btn--primary" style="background:#16a34a;border-color:#16a34a;color:#fff" href="${esc(driver.url_app_whatsapp)}" target="_blank" rel="noopener noreferrer">Enviar link + PIN no WhatsApp</a>`
              : `<p style="margin:0;font-size:13px;color:#b45309">${driver.pin_usado ? "PIN já usado — gere outro para enviar." : "Gere um PIN para liberar o envio no WhatsApp."}</p>`}
          </div>` : ""}
          <div class="vfl-form-actions"><button class="vfl-btn vfl-btn--primary" type="submit">Salvar</button><button class="vfl-btn" type="button" data-vfl-back-drivers>Cancelar</button></div>
        </form>
        ${editing ? `<div class="vfl-delete-zone"><button class="vfl-btn vfl-btn--danger" type="button" data-vfl-delete-driver>Excluir entregador</button></div>` : ""}
      </section>
    </div>`;
    bindMasks(root);
    let removePhoto = false;
    let selectedFile = null;
    root.querySelectorAll("[data-vfl-back-drivers]").forEach((button) => { button.onclick = () => loadDeliveryEntregadores(); });
    root.querySelector("[data-vfl-copy-app]")?.addEventListener("click", async () => {
      const url = $("vflDriverAppUrl")?.value || driver?.url_app || "";
      try {
        await navigator.clipboard.writeText(url);
        toast("Link do app copiado.", "success");
      } catch (_) {
        window.prompt("Copie o link do app:", url);
      }
    });
    root.querySelector("[data-vfl-copy-pin]")?.addEventListener("click", async () => {
      const pin = driver?.acesso_pin || "";
      if (!pin) return;
      try {
        await navigator.clipboard.writeText(pin);
        toast("PIN copiado.", "success");
      } catch (_) {
        window.prompt("Copie o PIN:", pin);
      }
    });
    root.querySelector("[data-vfl-gerar-pin]")?.addEventListener("click", async () => {
      if (!driver?.id) return;
      if (!confirm("Gerar um novo PIN de 6 dígitos? O PIN anterior deixa de valer.")) return;
      try {
        const updated = await api(`/entregadores/${driver.id}/gerar-pin`, { method: "POST", body: "{}" });
        toast("Novo PIN gerado. Envie no WhatsApp cadastrado.", "success");
        renderDriverEditor(root, updated);
      } catch (error) {
        toast(error?.message || "Não foi possível gerar o PIN.", "error");
      }
    });
    $("vflDriverFile").onchange = (event) => {
      selectedFile = event.target.files?.[0] || null;
      if (!selectedFile) return;
      if (selectedFile.size > 2 * 1024 * 1024) {
        selectedFile = null; event.target.value = "";
        return toast("A foto não pode exceder 2 MB.", "error");
      }
      $("vflDriverPhoto").innerHTML = `<img src="${esc(URL.createObjectURL(selectedFile))}" alt="Prévia">`;
      $("vflDriverPhoto").classList.add("has-photo");
      root.querySelector("[data-vfl-remove-photo]").hidden = false;
      removePhoto = false;
    };
    root.querySelector("[data-vfl-remove-photo]").onclick = () => {
      selectedFile = null; $("vflDriverFile").value = ""; removePhoto = true;
      $("vflDriverPhoto").textContent = "Foto"; $("vflDriverPhoto").classList.remove("has-photo");
      root.querySelector("[data-vfl-remove-photo]").hidden = true;
    };
    $("vflDriverForm").onsubmit = async (event) => {
      event.preventDefault();
      const form = event.currentTarget;
      const submit = form.querySelector('[type="submit"]');
      submit.disabled = true;
      try {
        const payload = {
          nome: value(form, "nome").trim(), whatsapp: digits(value(form, "whatsapp")),
          moto_modelo: value(form, "moto_modelo").trim() || null, moto_cor: value(form, "moto_cor").trim() || null,
          moto_placa: value(form, "moto_placa").trim().toUpperCase() || null,
          ordem: Number(value(form, "ordem") || 0), ativo: checked(form, "ativo"), remover_foto: removePhoto,
          foto_base64: selectedFile ? await fileDataUrl(selectedFile) : null,
        };
        const saved = await api(editing ? `/entregadores/${driver.id}` : "/entregadores", {
          method: editing ? "PUT" : "POST", body: JSON.stringify(payload),
        });
        toast(editing ? "Entregador atualizado." : "Entregador cadastrado. PIN gerado — envie no WhatsApp.", "success");
        const id = Number(saved?.id || driver?.id || 0);
        if (id) {
          await openDriverEditor(root, id);
          return;
        }
        await loadDeliveryEntregadores();
      } catch (error) {
        toast(error?.message || "Não foi possível salvar o entregador.", "error");
      } finally {
        submit.disabled = false;
      }
    };
    root.querySelector("[data-vfl-delete-driver]")?.addEventListener("click", async () => {
      if (!confirm("Excluir este entregador?")) return;
      await api(`/entregadores/${driver.id}`, { method: "DELETE" });
      toast("Entregador excluído.", "success");
      await loadDeliveryEntregadores();
    });
  }

  function fileDataUrl(file) {
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = () => resolve(String(reader.result));
      reader.onerror = () => reject(new Error("Não foi possível ler a foto."));
      reader.readAsDataURL(file);
    });
  }

  window.loadDeliveryFretes = loadDeliveryFretes;
  window.loadDeliveryEntregadores = loadDeliveryEntregadores;
})();
