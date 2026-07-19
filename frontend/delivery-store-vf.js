/**
 * VendaFácil parity for Delivery consultation, storefront and configuration.
 * Loaded after delivery.js so these functions intentionally replace its screens.
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
  const value = (form, name) => form.elements[name]?.value ?? "";
  const checked = (form, name) => !!form.elements[name]?.checked;
  const apiOrigin = () => {
    const fromConfig = String(window.APP_CONFIG?.API_URL || "").replace(/\/api\/?$/, "");
    if (fromConfig) return fromConfig;
    if (typeof window.API_URL === "string" && window.API_URL) {
      return String(window.API_URL).replace(/\/api\/?$/, "");
    }
    return "https://api.gruposaborparaense.com.br";
  };
  const imageUrl = (url) => {
    if (!url) return "";
    if (/^(?:https?:|data:|blob:)/i.test(url)) return url;
    const base = apiOrigin();
    return `${base}${String(url).startsWith("/") ? "" : "/"}${url}`;
  };
  /** Loja pública vive no host da API (Laravel), não no frontend. */
  const publicStoreUrl = (config) => {
    const path = config?.preview_path || (config?.slug ? `/loja/${config.slug}` : "");
    if (!path) return "";
    return `${apiOrigin()}${String(path).startsWith("/") ? path : `/${path}`}`;
  };
  const breadcrumb = (section) => `<div class="vf-store__breadcrumb"><button type="button" data-vfs-dashboard>Dashboard</button><span>/</span>${esc(section)}</div>`;
  const heading = (title, subtitle) => `<div class="vf-store__heading"><h2>${esc(title)}</h2><p>${esc(subtitle)}</p></div>`;

  function bindDashboard(root) {
    root.querySelector("[data-vfs-dashboard]")?.addEventListener("click", () => window.navigateTo?.("deliveryDashboard"));
  }

  async function loadDeliveryCatalogo() {
    const root = $("deliveryCatalogoRoot");
    if (!root) return;
    const data = await api("/catalogo");
    let selected = null;
    const groups = data.categorias || [];
    const products = data.produtos || [];

    root.innerHTML = `<main class="vf-store vf-catalog">
      ${breadcrumb("Cardápio")}
      ${heading("Cardápio do salão", "Consulte os produtos ativos disponíveis para venda. Esta tela é somente para visualização.")}
      <nav class="vf-category-filter" aria-label="Categorias">
        <button class="is-active" type="button" data-category="">Todas</button>
        ${groups.filter((group) => group.id !== null).map((group) =>
          `<button type="button" data-category="${group.id}">${esc(group.nome)}</button>`).join("")}
      </nav>
      <div class="vf-catalog-grid" id="vfCatalogGrid"></div>
    </main>`;

    const render = () => {
      const shown = selected === null ? products : products.filter((product) => Number(product.categoria_id) === selected);
      $("vfCatalogGrid").innerHTML = shown.length ? shown.map((product) => {
        const photo = imageUrl(product.foto_url);
        const category = groups.find((group) => Number(group.id) === Number(product.categoria_id));
        const available = product.disponivel !== false;
        return `<article class="vf-catalog-product">
          <div class="vf-catalog-product__photo">${photo
            ? `<img src="${esc(photo)}" alt="${esc(product.nome)}">`
            : `<span aria-hidden="true">▧</span>`}</div>
          <div class="vf-catalog-product__body">
            <small>${esc(category?.nome || "Sem categoria")}</small>
            <h3>${esc(product.nome)}</h3>
            ${product.sku ? `<code>${esc(product.sku)}</code>` : ""}
            <div class="vf-catalog-product__footer">
              <strong>${money(product.preco)}</strong>
              <span class="${available ? "is-available" : "is-unavailable"}">${available ? "Disponível" : "Indisponível"}</span>
            </div>
          </div>
        </article>`;
      }).join("") : `<div class="vf-store-empty"><strong>Cardápio vazio</strong><p>Nenhum produto ativo nesta categoria.</p>
        <button class="vf-store-btn vf-store-btn--primary" type="button" data-vfs-products>Ir para Produtos</button></div>`;
      root.querySelector("[data-vfs-products]")?.addEventListener("click", () => window.navigateTo?.("deliveryProdutos"));
    };

    root.querySelector(".vf-category-filter").onclick = (event) => {
      const button = event.target.closest("[data-category]");
      if (!button) return;
      selected = button.dataset.category === "" ? null : Number(button.dataset.category);
      root.querySelectorAll("[data-category]").forEach((item) => item.classList.toggle("is-active", item === button));
      render();
    };
    bindDashboard(root);
    render();
  }

  function previewImageMarkup(url, kind, fallback) {
    return url
      ? `<img src="${esc(imageUrl(url))}" alt="Prévia de ${kind}">`
      : `<span>${esc(fallback)}</span>`;
  }

  async function fileData(file, maxBytes, label) {
    if (!file) return null;
    if (file.size > maxBytes) throw new Error(`${label} excede o tamanho permitido.`);
    if (!["image/jpeg", "image/png", "image/webp", "image/gif"].includes(file.type)) {
      throw new Error(`${label} deve ser JPG, PNG, WebP ou GIF.`);
    }
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = () => resolve(String(reader.result));
      reader.onerror = () => reject(new Error(`Não foi possível ler ${label.toLowerCase()}.`));
      reader.readAsDataURL(file);
    });
  }

  async function loadDeliveryVitrine() {
    const root = $("deliveryVitrineRoot");
    if (!root) return;
    const config = await api("/vitrine");
    const primary = /^#[0-9a-f]{6}$/i.test(config.cor_primaria || "") ? config.cor_primaria : "#2563eb";

    root.innerHTML = `<main class="vf-store">
      ${breadcrumb("Vitrine")}
      ${heading("Vitrine da loja", "Personalize como sua loja será apresentada aos clientes.")}
      <div class="vf-vitrine-layout">
        <form class="vf-store-card vf-vitrine-form" id="vfVitrineForm">
          <div class="vf-store-card__title"><h3>Identidade da loja</h3><p>Informações, imagens e disponibilidade.</p></div>
          <div class="vf-store-form-grid">
            <label class="vf-store-field vf-span-2"><span>Nome da loja</span><input name="nome_loja" maxlength="160" value="${esc(config.nome_loja || "")}"></label>
            <label class="vf-store-field"><span>Slug</span><input name="slug" maxlength="120" value="${esc(config.slug || "")}"></label>
            <label class="vf-store-field"><span>Cor principal</span><input name="cor_primaria" type="color" value="${esc(primary)}"></label>
            <label class="vf-store-field"><span>WhatsApp</span><input name="whatsapp" maxlength="30" value="${esc(config.whatsapp || "")}"></label>
            <label class="vf-store-field"><span>Telefone</span><input name="telefone" maxlength="30" value="${esc(config.telefone || "")}"></label>
            <label class="vf-store-field"><span>Instagram (URL)</span><input name="instagram_url" maxlength="255" placeholder="https://instagram.com/sua_loja" value="${esc(config.instagram_url || "")}"></label>
            <label class="vf-store-field"><span>Facebook (URL)</span><input name="facebook_url" maxlength="255" placeholder="https://facebook.com/sua_loja" value="${esc(config.facebook_url || "")}"></label>
            <label class="vf-store-field vf-span-2"><span>Descrição</span><textarea name="descricao" rows="3">${esc(config.descricao || "")}</textarea></label>
            <label class="vf-store-field vf-span-2"><span>Endereço</span><textarea name="endereco_texto" rows="2">${esc(config.endereco_texto || "")}</textarea></label>
            <label class="vf-store-field"><span>Outra unidade (nome)</span><input name="filial_nome" maxlength="160" value="${esc(config.filial_nome || "")}"></label>
            <label class="vf-store-field"><span>Link da outra unidade</span><input name="filial_link_url" maxlength="255" placeholder="https://..." value="${esc(config.filial_link_url || "")}"></label>
            <label class="vf-store-field vf-span-2"><span>Texto da barra de entrega</span><input name="entrega_texto" maxlength="180" placeholder="Entrega em até 45 min · Pagamento na entrega ou online" value="${esc(config.entrega_texto || "")}"></label>
            <div class="vf-store-upload">
              <span>Logo</span><div class="vf-store-upload__preview vf-store-upload__preview--logo" data-logo-preview>${previewImageMarkup(config.logo_url, "logo", "Logo")}</div>
              <input name="logo" type="file" accept="image/jpeg,image/png,image/webp,image/gif">
              <label><input name="logo_clear" type="checkbox"> Remover logo atual</label><small>Até 3 MB.</small>
            </div>
            <div class="vf-store-upload vf-span-2 vf-banners-field">
              <span>Banners da vitrine <em>(0 a 10 · centralizados na loja)</em></span>
              <div class="vf-banners-grid" data-banners-grid>${(config.banners || []).map((banner) => `
                <figure class="vf-banner-thumb" data-banner-id="${banner.id ?? ""}">
                  <img src="${esc(imageUrl(banner.url))}" alt="Banner">
                  <button type="button" class="vf-banner-thumb__remove" data-banner-remove="${banner.id ?? ""}" ${banner.id == null ? "disabled" : ""}>Remover</button>
                </figure>`).join("") || `<div class="vf-banners-empty">Nenhum banner ainda. A loja funciona sem banners.</div>`}
              </div>
              <input name="banners" type="file" accept="image/jpeg,image/png,image/webp,image/gif" multiple>
              <small data-banners-hint>Até 10 imagens · 6 MB cada · ${(config.banners || []).length}/10</small>
            </div>
          </div>
          <div class="vf-toggle-row">
            <label><input name="ativo" type="checkbox" ${config.ativo ? "checked" : ""}><span>Vitrine ativa</span></label>
            <label><input name="aberta" type="checkbox" ${config.aberta ? "checked" : ""}><span>Loja aberta</span></label>
          </div>
          <button class="vf-store-btn vf-store-btn--primary" type="submit">Salvar vitrine</button>
        </form>
        <aside class="vf-store-card vf-vitrine-preview" style="--store-primary:${esc(primary)}">
          <div class="vf-vitrine-preview__banner" data-public-banner>${(config.banners || []).length
            ? `<img src="${esc(imageUrl(config.banners[0].url))}" alt="Banner">`
            : previewImageMarkup(null, "banner", "Seu banner")}</div>
          <div class="vf-vitrine-preview__identity">
            <div class="vf-vitrine-preview__logo" data-public-logo>${previewImageMarkup(config.logo_url, "logo", "Logo")}</div>
            <div><h3 data-public-name>${esc(config.nome_loja || "Sua loja")}</h3><span class="${config.aberta ? "is-open" : "is-closed"}" data-public-status>${config.aberta ? "Aberta agora" : "Loja fechada"}</span></div>
          </div>
          <p data-public-description>${esc(config.descricao || "A descrição da loja aparecerá aqui.")}</p>
          <div class="vf-preview-products"><span></span><span></span><span></span></div>
          <div class="vf-preview-path"><small>${config.public_route_available ? "Endereço público" : "Caminho configurado (rota pública ainda indisponível)"}</small>
            <code>${esc(publicStoreUrl(config) || config.preview_path)}</code>
            <div><button class="vf-store-btn" type="button" data-copy-path>Copiar link</button>
            <button class="vf-store-btn" type="button" data-open-path ${config.public_route_available ? "" : "disabled"}>Abrir</button></div>
          </div>
        </aside>
      </div>
    </main>`;

    const form = $("vfVitrineForm");
    const publicUrl = publicStoreUrl(config);
    const bannersRemove = new Set();
    const pendingBannerFiles = [];
    const maxBanners = Number(config.banners_max || 10);
    bindDashboard(root);
    form.oninput = () => {
      root.querySelector("[data-public-name]").textContent = value(form, "nome_loja") || "Sua loja";
      root.querySelector("[data-public-description]").textContent = value(form, "descricao") || "A descrição da loja aparecerá aqui.";
      const open = checked(form, "aberta");
      const status = root.querySelector("[data-public-status]");
      status.textContent = open ? "Aberta agora" : "Loja fechada";
      status.className = open ? "is-open" : "is-closed";
      root.querySelector(".vf-vitrine-preview").style.setProperty("--store-primary", value(form, "cor_primaria"));
    };
    const refreshBannerHint = () => {
      const kept = (config.banners || []).filter((banner) => banner.id == null || !bannersRemove.has(Number(banner.id))).length;
      const total = kept + pendingBannerFiles.length;
      const hint = root.querySelector("[data-banners-hint]");
      if (hint) hint.textContent = `Até 10 imagens · 6 MB cada · ${total}/10`;
      form.elements.banners.disabled = total >= maxBanners;
    };
    root.querySelector("[data-banners-grid]")?.addEventListener("click", (event) => {
      const button = event.target.closest("[data-banner-remove]");
      if (!button || button.disabled) return;
      const id = Number(button.dataset.bannerRemove);
      if (!id) return;
      bannersRemove.add(id);
      button.closest(".vf-banner-thumb")?.remove();
      const grid = root.querySelector("[data-banners-grid]");
      if (grid && !grid.querySelector(".vf-banner-thumb")) {
        grid.innerHTML = `<div class="vf-banners-empty">Nenhum banner ainda. A loja funciona sem banners.</div>`;
      }
      refreshBannerHint();
    });
    form.onchange = async (event) => {
      const file = event.target.files?.[0];
      if (event.target.name === "logo" && file) {
        const url = URL.createObjectURL(file);
        root.querySelector("[data-logo-preview]").innerHTML = `<img src="${esc(url)}" alt="Prévia do logo">`;
        root.querySelector("[data-public-logo]").innerHTML = `<img src="${esc(url)}" alt="Logo">`;
        form.elements.logo_clear.checked = false;
        return;
      }
      if (event.target.name !== "banners") return;
      const files = [...(event.target.files || [])];
      event.target.value = "";
      const kept = (config.banners || []).filter((banner) => banner.id == null || !bannersRemove.has(Number(banner.id))).length;
      const room = maxBanners - kept - pendingBannerFiles.length;
      if (room <= 0) {
        toast("Limite de 10 banners atingido.", "error");
        return;
      }
      const accepted = files.slice(0, room);
      for (const bannerFile of accepted) {
        if (bannerFile.size > 6 * 1024 * 1024) {
          toast(`${bannerFile.name} excede 6 MB.`, "error");
          continue;
        }
        pendingBannerFiles.push(bannerFile);
        const url = URL.createObjectURL(bannerFile);
        const grid = root.querySelector("[data-banners-grid]");
        grid?.querySelector(".vf-banners-empty")?.remove();
        grid?.insertAdjacentHTML("beforeend", `<figure class="vf-banner-thumb is-pending">
          <img src="${esc(url)}" alt="Novo banner"><span class="vf-banner-thumb__badge">Novo</span></figure>`);
        root.querySelector("[data-public-banner]").innerHTML = `<img src="${esc(url)}" alt="Banner">`;
      }
      refreshBannerHint();
    };
    root.querySelector("[data-copy-path]").onclick = async () => {
      try {
        await navigator.clipboard.writeText(publicUrl);
        toast("Link da loja copiado.", "success");
      } catch (_) {
        toast("Não foi possível copiar o link.", "error");
      }
    };
    root.querySelector("[data-open-path]").onclick = () => {
      if (config.public_route_available) window.open(publicUrl, "_blank", "noopener");
    };
    form.onsubmit = async (event) => {
      event.preventDefault();
      const submit = form.querySelector('[type="submit"]');
      submit.disabled = true;
      try {
        const bannersBase64 = [];
        for (const bannerFile of pendingBannerFiles) {
          bannersBase64.push(await fileData(bannerFile, 6 * 1024 * 1024, "Banner"));
        }
        const payload = {
          nome_loja: value(form, "nome_loja") || null,
          slug: value(form, "slug") || null,
          cor_primaria: value(form, "cor_primaria"),
          whatsapp: value(form, "whatsapp") || null,
          telefone: value(form, "telefone") || null,
          instagram_url: value(form, "instagram_url") || null,
          facebook_url: value(form, "facebook_url") || null,
          filial_nome: value(form, "filial_nome") || null,
          filial_link_url: value(form, "filial_link_url") || null,
          entrega_texto: value(form, "entrega_texto") || null,
          descricao: value(form, "descricao") || null,
          endereco_texto: value(form, "endereco_texto") || null,
          ativo: checked(form, "ativo"),
          aberta: checked(form, "aberta"),
          logo_clear: checked(form, "logo_clear"),
          logo_base64: await fileData(form.elements.logo.files?.[0], 3 * 1024 * 1024, "Logo"),
          banners_base64: bannersBase64,
          banners_remove: [...bannersRemove],
        };
        await api("/vitrine", { method: "PUT", body: JSON.stringify(payload) });
        toast("Vitrine atualizada.", "success");
        await loadDeliveryVitrine();
      } catch (error) {
        toast(error?.message || "Não foi possível salvar a vitrine.", "error");
      } finally {
        submit.disabled = false;
      }
    };
  }

  const configCard = (title, description, body, cardId = "") => `<details class="vf-config-card" open${cardId ? ` id="${esc(cardId)}"` : ""}>
    <summary><div><h3>${esc(title)}</h3><p>${esc(description)}</p></div><span aria-hidden="true">⌃</span></summary>
    <div class="vf-config-card__body">${body}</div>
  </details>`;

  const renderGatewayConfigBody = (config) => `
          <div class="vf-store-form-grid">
            <label class="vf-store-field"><span>Modo PIX</span><select name="pix_modo">
              ${[["manual","Manual (chave/QR da loja)"],["automatico","Automático (gateway confirma)"],["hibrido","Híbrido (gateway + fallback manual)"]].map(([val, label]) =>
                `<option value="${val}" ${(config.pix_modo || config.gateway?.pix_modo || "manual") === val ? "selected" : ""}>${label}</option>`).join("")}
            </select></label>
            <label class="vf-store-field"><span>Provedor</span><select name="pagamento_gateway">
              <option value="">Nenhum</option>
              ${[["mercado_pago","Mercado Pago"],["asaas","Asaas"],["pagbank","PagBank / PagSeguro"]].map(([val, label]) =>
                `<option value="${val}" ${(config.pagamento_gateway || config.gateway?.pagamento_gateway || "") === val ? "selected" : ""}>${label}</option>`).join("")}
            </select></label>
            <label class="vf-store-field"><span>Token / Access token</span><input name="pagamento_gateway_token" type="password" autocomplete="new-password" placeholder="${config.gateway?.pagamento_gateway_token_configurado ? "Deixe em branco para manter o token atual" : "Cole o token do provedor"}"></label>
            <label class="vf-store-field"><span>Chave pública (opcional)</span><input name="pagamento_gateway_public_key" maxlength="255" value="${esc(config.pagamento_gateway_public_key || "")}" placeholder="Para cartão online no futuro"></label>
            <label class="vf-store-field"><span>Segredo do webhook</span><input name="pagamento_gateway_webhook_secret" type="password" autocomplete="new-password" placeholder="${config.gateway?.pagamento_gateway_webhook_secret_configurado ? "Deixe em branco para manter" : "Opcional"}"></label>
            <label class="vf-store-field"><span>Expiração PIX (minutos)</span><input name="pix_expiracao_minutos" type="number" min="5" max="1440" value="${esc(String(config.pix_expiracao_minutos || config.gateway?.pix_expiracao_minutos || 30))}"></label>
            <label class="vf-setting-switch vf-span-2"><input name="pagamento_gateway_sandbox" type="checkbox" ${(config.pagamento_gateway_sandbox ?? config.gateway?.pagamento_gateway_sandbox ?? true) ? "checked" : ""}>
            <span><strong>Ambiente sandbox / testes</strong><small>Use credenciais de homologação enquanto configura.</small></span></label>
            <label class="vf-setting-switch vf-span-2"><input name="pagamento_online_ativo" type="checkbox" ${(config.pagamento_online_ativo ?? config.gateway?.pagamento_online_ativo) ? "checked" : ""}>
            <span><strong>Cartão online na vitrine</strong><small>Exibe Cartão online no checkout quando o gateway estiver configurado.</small></span></label>
            ${config.gateway?.webhook_url ? `<p class="vf-help vf-span-2"><strong>URL do webhook:</strong> <code>${esc(config.gateway.webhook_url)}</code><br><small>Cadastre no painel do provedor para confirmar PIX automaticamente.</small></p>` : `<p class="vf-help vf-span-2 muted">Selecione um provedor e salve para ver a URL do webhook.</p>`}
          </div>`;

  const freteModoEfetivo = (modo) => {
    const value = String(modo || "faixas_cep");
    if (["fixed", "padrao_unico"].includes(value)) return "padrao_unico";
    if (["cep_band", "faixas_cep"].includes(value)) return "faixas_cep";
    return value;
  };

  function renderFreteChecklist(config) {
    const modo = freteModoEfetivo(config.frete_modo);
    const google = config.frete_google_checklist || {};
    const osrm = config.frete_osrm_checklist || {};
    if (modo === "google_distancia") {
      if (google.pronto) {
        return `<div class="vf-frete-alert vf-frete-alert--ok">✓ Frete por distância pronto para usar.</div>`;
      }
      const missing = [];
      if (!google.api_configurada) missing.push("Configure <code>GOOGLE_MAPS_API_KEY</code> no servidor.");
      if (!google.rs_por_km) missing.push("Preencha <strong>R$ por km</strong> abaixo.");
      if (!google.origem) missing.push("Informe endereço da loja ou <strong>Saída das entregas</strong>.");
      return `<div class="vf-frete-alert vf-frete-alert--warn"><strong>Falta configurar:</strong> ${missing.join(" ")}</div>`;
    }
    if (modo === "osrm_distancia") {
      if (osrm.pronto) {
        return `<div class="vf-frete-alert vf-frete-alert--ok">✓ Frete OSRM / OpenStreetMap pronto para usar.</div>`;
      }
      const missing = [];
      if (!osrm.origem) missing.push("Informe <strong>latitude/longitude de origem</strong> ou endereço da loja.");
      if (!osrm.user_agent) missing.push("Configure <code>OSM_HTTP_USER_AGENT</code> no <code>.env</code>.");
      return `<div class="vf-frete-alert vf-frete-alert--warn"><strong>Falta configurar:</strong> ${missing.join(" ")}</div>`;
    }
    return "";
  }

  function renderFreteConfigBody(config) {
    const modo = freteModoEfetivo(config.frete_modo);
    const preview = config.frete_preview_mapa_origem;
    const distKm = modo === "google_distancia" || modo === "osrm_distancia";
    return `
      <p class="vf-config-note vf-config-note--lead">Defina o valor base e, abaixo, <strong>como</strong> o sistema calcula na vitrine.</p>
      <div class="vf-store-form-grid vf-frete-base-grid">
        <label class="vf-store-field"><span>Taxa de entrega (R$)</span><input name="frete_taxa_fixa" type="number" min="0" step="0.01" value="${esc(config.frete_taxa_fixa ?? 0)}"><small>Valor base (fixo ou taxa base no modo por km).</small></label>
        <label class="vf-store-field"><span>Entrega grátis acima de (R$)</span><input name="frete_gratis_acima" type="number" min="0" step="0.01" value="${esc(config.frete_gratis_acima ?? "")}" placeholder="Opcional"><small>Vazio = sem frete grátis automático.</small></label>
        <label class="vf-store-field"><span>Cliente pode retirar na loja?</span><select name="permite_retirada">
          <option value="1" ${config.permite_retirada ? "selected" : ""}>Sim, sem taxa</option>
          <option value="0" ${config.permite_retirada ? "" : "selected"}>Não, só entrega</option>
        </select></label>
      </div>
      <div class="vf-frete-chuva">
        <span class="vf-frete-chuva__title">Chuva — acréscimo no frete de entrega</span>
        <label class="vf-setting-switch"><input name="frete_chuva_ativa" type="checkbox" ${config.frete_chuva_ativa ? "checked" : ""}>
        <span><strong>Aplicar acréscimo de chuva</strong><small>Percentual sobre o frete já calculado (faixa, fixo, Google ou OSRM).</small></span></label>
        <label class="vf-store-field vf-frete-chuva__pct"><span>Acréscimo (%)</span><input name="frete_acrescimo_chuva_percent" type="number" min="0" step="0.01" value="${esc(config.frete_acrescimo_chuva_percent ?? 0)}"></label>
      </div>
      <label class="vf-store-field vf-span-2"><span>Como calcular o frete na vitrine</span><select name="frete_modo" data-vf-frete-modo required>
        <option value="padrao_unico" ${modo === "padrao_unico" ? "selected" : ""}>Taxa fixa (padrão único)</option>
        <option value="faixas_cep" ${modo === "faixas_cep" ? "selected" : ""}>Faixas de CEP</option>
        <option value="google_distancia" ${modo === "google_distancia" ? "selected" : ""}>Google Maps (distância × R$/km)</option>
        <option value="osrm_distancia" ${modo === "osrm_distancia" ? "selected" : ""}>OpenStreetMap / OSRM (rota)</option>
      </select></label>
      <div class="vf-frete-modo-ajuda" data-vf-frete-ajuda>
        <p class="vf-config-note" data-vf-frete-help-faixas ${modo === "faixas_cep" ? "" : "hidden"}>Cadastre faixas em <button type="button" class="vf-link-btn" data-vf-open-fretes>Fretes</button>. Fora das faixas usa a taxa acima.</p>
        <p class="vf-config-note" data-vf-frete-help-padrao ${modo === "padrao_unico" ? "" : "hidden"}>Todo pedido com entrega usa só o valor em <strong>Taxa de entrega</strong>.</p>
        <p class="vf-config-note" data-vf-frete-help-google ${modo === "google_distancia" ? "" : "hidden"}>O sistema calcula km pela rota e multiplica pelo valor por km. Confira o endereço da loja em <strong>Dados da loja</strong>.</p>
        <p class="vf-config-note" data-vf-frete-help-osrm ${modo === "osrm_distancia" ? "" : "hidden"}>Geocoding (Nominatim) + rota OSRM entre coordenadas de origem e o endereço do cliente. Taxa: valor base + km acima do incluso.</p>
      </div>
      ${renderFreteChecklist(config)}
      <details class="vf-frete-tech"><summary>Ajuda técnica — Google Maps no servidor</summary>
        <p>Chave <code>GOOGLE_MAPS_API_KEY</code> no <code>.env</code>; API <strong>Distance Matrix</strong> ativa no Google Cloud.</p>
        <p>${config.google_maps_configured ? '<span class="vf-frete-alert vf-frete-alert--ok vf-frete-alert--inline">Neste servidor a chave está configurada.</span>' : '<span class="vf-frete-alert vf-frete-alert--warn vf-frete-alert--inline">Neste servidor a chave ainda não está configurada.</span>'}</p>
      </details>
      <details class="vf-frete-tech"><summary>Ajuda técnica — OSRM + OpenStreetMap + Leaflet</summary>
        <p>No servidor: <code>OSM_OSRM_BASE_URL</code>, <code>OSM_NOMINATIM_BASE_URL</code>, <code>OSM_HTTP_USER_AGENT</code> (obrigatório). O mapa abaixo usa tiles OSM só como visual da origem.</p>
        <p>${config.osm_user_agent_configured ? '<span class="vf-frete-alert vf-frete-alert--ok vf-frete-alert--inline">User-Agent OSM configurado.</span>' : '<span class="vf-frete-alert vf-frete-alert--warn vf-frete-alert--inline">Configure <code>OSM_HTTP_USER_AGENT</code> no <code>.env</code>.</span>'}</p>
      </details>
      <div class="vf-frete-km-campos ${distKm ? "" : "hidden"}" data-vf-frete-km-campos>
        <h4 class="vf-frete-km-campos__title">Frete por quilômetro rodado</h4>
        <p class="vf-config-note">No modo <strong>Google Maps</strong> use R$ por km. No modo <strong>OSRM</strong> use taxa base + km incluso + valor por km extra.</p>
        <div class="vf-store-form-grid">
          <label class="vf-store-field" data-vf-google-only><span>R$ por km <em class="vf-rs-km-obr">*</em> <small>(Google)</small></span><input name="frete_google_rs_por_km" type="number" min="0" step="0.01" data-vf-google-rs value="${esc(config.frete_google_rs_por_km ?? "")}"></label>
          <label class="vf-store-field" data-vf-google-only><span>Nunca cobrar menos que (R$) <small>(Google)</small></span><input name="frete_google_taxa_minima" type="number" min="0" step="0.01" value="${esc(config.frete_google_taxa_minima ?? "")}"></label>
          <label class="vf-store-field" data-vf-km-only><span>Até quantos km entrega</span><input name="frete_google_km_max" type="number" min="0" step="0.1" value="${esc(config.frete_google_km_max ?? "")}"><small>Vazio = sem limite. Acima desse valor, o pedido é bloqueado.</small></label>
          <label class="vf-store-field vf-span-2" data-vf-km-only><span>Saída das entregas <small>(opcional)</small></span><input name="frete_origem_endereco" maxlength="500" placeholder="Deixe em branco para usar o endereço em Dados da loja" value="${esc(config.frete_origem_endereco || "")}"></label>
        </div>
        <div class="vf-frete-osrm-origin" data-vf-osrm-only>
          <h5>Origem no mapa (OSRM) — recomendado</h5>
          <p class="vf-config-note">Defina as coordenadas do restaurante para o cálculo da rota sem depender só do geocode do endereço.</p>
          <div class="vf-store-form-grid">
            <label class="vf-store-field"><span>Latitude origem</span><input name="frete_entrega_lat_origem" type="number" step="any" data-vf-map-lat value="${esc(config.frete_entrega_lat_origem ?? "")}" placeholder="Ex.: -8.7619"></label>
            <label class="vf-store-field"><span>Longitude origem</span><input name="frete_entrega_lng_origem" type="number" step="any" data-vf-map-lng value="${esc(config.frete_entrega_lng_origem ?? "")}" placeholder="Ex.: -63.9039"></label>
            <label class="vf-store-field"><span>Km inclusos na taxa base</span><input name="frete_km_incluso" type="number" min="0" step="0.1" value="${esc(config.frete_km_incluso ?? "")}" placeholder="Padrão 3 km"><small>Se vazio, o sistema usa 3 km.</small></label>
            <label class="vf-store-field"><span>R$ por km acima do incluso</span><input name="frete_valor_km_extra" type="number" min="0" step="0.01" value="${esc(config.frete_valor_km_extra ?? "")}" placeholder="Padrão R$ 2,00"><small>Se vazio, o sistema usa R$ 2,00.</small></label>
          </div>
          <div class="vf-frete-map-tools">
            <button class="vf-store-btn" type="button" data-vf-geocode-origem>Localizar endereço no mapa</button>
            <button class="vf-store-btn" type="button" data-vf-use-my-location>Usar minha localização</button>
            <small data-vf-geocode-status></small>
          </div>
          <div class="vf-frete-osrm-mapa-wrap ${modo === "osrm_distancia" ? "" : "hidden"}" data-vf-osrm-mapa-wrap>
            <p class="vf-config-note">Mapa de referência — origem (Leaflet + tiles © OpenStreetMap). Clique no mapa para ajustar lat/lng.</p>
            <div id="vfFreteOsrmMapa" class="vf-frete-osrm-mapa" data-vf-lat="${esc(preview?.lat ?? "")}" data-vf-lon="${esc(preview?.lon ?? "")}"></div>
          </div>
        </div>
      </div>
      <p class="vf-config-note"><button type="button" class="vf-link-btn" data-vf-open-fretes>Frete por CEP</button> — cadastro de faixas (modo “Por CEP”).</p>`;
  }

  function ensureLeaflet() {
    if (window.L) return Promise.resolve();
    if (!document.querySelector('link[data-vf-leaflet-css]')) {
      const link = document.createElement("link");
      link.rel = "stylesheet";
      link.href = "https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css";
      link.setAttribute("data-vf-leaflet-css", "1");
      document.head.appendChild(link);
    }
    return new Promise((resolve, reject) => {
      if (document.querySelector('script[data-vf-leaflet-js]')) {
        const wait = setInterval(() => {
          if (window.L) { clearInterval(wait); resolve(); }
        }, 50);
        setTimeout(() => { clearInterval(wait); window.L ? resolve() : reject(new Error("Leaflet indisponível.")); }, 5000);
        return;
      }
      const script = document.createElement("script");
      script.src = "https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js";
      script.crossOrigin = "";
      script.setAttribute("data-vf-leaflet-js", "1");
      script.onload = () => resolve();
      script.onerror = () => reject(new Error("Não foi possível carregar o mapa."));
      document.body.appendChild(script);
    });
  }

  function bindFreteConfigTools(form, config) {
    let map = null;
    let marker = null;
    const mapEl = form.querySelector("#vfFreteOsrmMapa");
    const statusEl = form.querySelector("[data-vf-geocode-status]");
    const setCoords = (lat, lon, fit = true) => {
      if (Number.isNaN(lat) || Number.isNaN(lon)) return;
      form.elements.frete_entrega_lat_origem.value = String(Number(lat).toFixed(6));
      form.elements.frete_entrega_lng_origem.value = String(Number(lon).toFixed(6));
      if (map && marker) {
        marker.setLatLng([lat, lon]);
        if (fit) map.setView([lat, lon], 15);
      }
    };
    const initMap = async () => {
      if (!mapEl || freteModoEfetivo(value(form, "frete_modo")) !== "osrm_distancia") return;
      await ensureLeaflet();
      if (map) { map.remove(); map = null; marker = null; }
      const lat = parseFloat(mapEl.dataset.vfLat || form.elements.frete_entrega_lat_origem.value);
      const lon = parseFloat(mapEl.dataset.vfLon || form.elements.frete_entrega_lng_origem.value);
      const center = (!Number.isNaN(lat) && !Number.isNaN(lon)) ? [lat, lon] : [-8.7619, -63.9039];
      map = window.L.map(mapEl).setView(center, (!Number.isNaN(lat) && !Number.isNaN(lon)) ? 15 : 4);
      window.L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        maxZoom: 19,
        attribution: "&copy; OpenStreetMap",
      }).addTo(map);
      marker = window.L.marker(center, { draggable: true }).addTo(map);
      marker.on("dragend", () => {
        const pos = marker.getLatLng();
        setCoords(pos.lat, pos.lng, false);
      });
      map.on("click", (event) => setCoords(event.latlng.lat, event.latlng.lng, false));
      setTimeout(() => map.invalidateSize(), 300);
    };
    const syncFreteModeUi = () => {
      const modo = freteModoEfetivo(value(form, "frete_modo"));
      const googleOnly = modo === "google_distancia";
      const kmOnly = googleOnly || modo === "osrm_distancia";
      const osrmOnly = modo === "osrm_distancia";
      form.querySelector("[data-vf-frete-km-campos]")?.classList.toggle("hidden", !kmOnly);
      form.querySelectorAll("[data-vf-google-only]").forEach((el) => { el.hidden = !googleOnly; });
      form.querySelectorAll("[data-vf-km-only]").forEach((el) => { el.hidden = !kmOnly; });
      form.querySelectorAll("[data-vf-osrm-only]").forEach((el) => { el.hidden = !osrmOnly; });
      form.querySelector("[data-vf-frete-help-faixas]").hidden = modo !== "faixas_cep";
      form.querySelector("[data-vf-frete-help-padrao]").hidden = modo !== "padrao_unico";
      form.querySelector("[data-vf-frete-help-google]").hidden = !googleOnly;
      form.querySelector("[data-vf-frete-help-osrm]").hidden = !osrmOnly;
      form.querySelector("[data-vf-osrm-mapa-wrap]")?.classList.toggle("hidden", !osrmOnly);
      const rs = form.querySelector("[data-vf-google-rs]");
      if (rs) rs.required = googleOnly;
      form.querySelector(".vf-rs-km-obr")?.classList.toggle("hidden", !googleOnly);
      if (osrmOnly) initMap().catch((error) => { if (statusEl) statusEl.textContent = error.message; });
    };
    form.querySelector("[data-vf-frete-modo]")?.addEventListener("change", syncFreteModeUi);
    form.querySelectorAll("[data-vf-open-fretes]").forEach((btn) => {
      btn.onclick = () => window.navigateTo?.("deliveryFretes");
    });
    form.querySelector("[data-vf-geocode-origem]")?.addEventListener("click", async () => {
      if (statusEl) statusEl.textContent = "Localizando…";
      try {
        const result = await api("/configuracoes/frete/geocode-origem", { method: "POST", body: JSON.stringify({
          endereco_texto: value(form, "endereco_texto") || null,
          frete_origem_endereco: value(form, "frete_origem_endereco") || null,
          frete_entrega_lat_origem: value(form, "frete_entrega_lat_origem") === "" ? null : Number(value(form, "frete_entrega_lat_origem")),
          frete_entrega_lng_origem: value(form, "frete_entrega_lng_origem") === "" ? null : Number(value(form, "frete_entrega_lng_origem")),
        }) });
        if (mapEl) {
          mapEl.dataset.vfLat = String(result.lat);
          mapEl.dataset.vfLon = String(result.lon);
        }
        setCoords(result.lat, result.lon);
        await initMap();
        if (statusEl) statusEl.textContent = result.display_name ? String(result.display_name).slice(0, 120) : "Origem localizada no mapa.";
      } catch (error) {
        if (statusEl) statusEl.textContent = error?.message || "Não foi possível localizar.";
      }
    });
    form.querySelector("[data-vf-use-my-location]")?.addEventListener("click", () => {
      if (!navigator.geolocation) {
        toast("Geolocalização indisponível neste navegador.", "error");
        return;
      }
      navigator.geolocation.getCurrentPosition(async (pos) => {
        if (mapEl) {
          mapEl.dataset.vfLat = String(pos.coords.latitude);
          mapEl.dataset.vfLon = String(pos.coords.longitude);
        }
        setCoords(pos.coords.latitude, pos.coords.longitude);
        await initMap();
        if (statusEl) statusEl.textContent = "Coordenadas da sua localização aplicadas.";
      }, () => toast("Não foi possível obter sua localização.", "error"), { enableHighAccuracy: true, timeout: 10000 });
    });
    form.querySelectorAll("[data-vf-map-lat],[data-vf-map-lng]").forEach((input) => {
      input.addEventListener("change", () => {
        const lat = parseFloat(form.elements.frete_entrega_lat_origem.value);
        const lon = parseFloat(form.elements.frete_entrega_lng_origem.value);
        if (!Number.isNaN(lat) && !Number.isNaN(lon)) setCoords(lat, lon);
      });
    });
    syncFreteModeUi();
  }

  async function loadDeliveryConfiguracoes() {
    const root = $("deliveryConfiguracoesRoot");
    if (!root) return;
    const config = await api("/configuracoes");
    const payments = new Set(String(config.formas_pagamento || "pix,cartao,dinheiro").split(",").map((item) => item.trim()).filter(Boolean));

    root.innerHTML = `<main class="vf-store vf-config">
      ${breadcrumb("Configurações")}
      ${heading("Configurações do delivery", "Defina a operação da vitrine, recebimento e entrega dos pedidos.")}
      <p class="vf-config-hint vf-help">Recebimento online: configure o PIX manual abaixo e, para PIX automático ou cartão online, use a seção <a href="#vf-pagamento-gateway">Pagamento online / Gateway</a> (antes de salvar, role a página).</p>
      <form id="vfConfigForm">
        ${configCard("Pedidos na vitrine", "Escolha como novos pedidos entram na operação.", `
          <label class="vf-setting-switch"><input name="confirmar_pedidos" type="checkbox" ${config.confirmar_pedidos ? "checked" : ""}>
          <span><strong>Confirmar pedidos manualmente</strong><small>Pedidos aguardam a confirmação da loja antes do preparo.</small></span></label>
          <label class="vf-setting-switch"><input name="exigir_pix_confirmado" type="checkbox" ${config.exigir_pix_confirmado ? "checked" : ""}>
          <span><strong>Exigir PIX confirmado antes de aceitar</strong><small>Pedidos PIX só entram no preparo depois que você confirmar o pagamento no painel.</small></span></label>`)}
        ${configCard("Loja aberta ou fechada", "Controle imediatamente se a loja aceita novos pedidos.", `
          <div class="vf-large-radios">
            <label class="${config.aberta ? "is-selected" : ""}"><input type="radio" name="aberta" value="1" ${config.aberta ? "checked" : ""}><span><strong>Loja aberta</strong><small>Aceitando pedidos na vitrine.</small></span></label>
            <label class="${config.aberta ? "" : "is-selected"}"><input type="radio" name="aberta" value="0" ${config.aberta ? "" : "checked"}><span><strong>Loja fechada</strong><small>Novos pedidos ficam indisponíveis.</small></span></label>
          </div>`)}
        ${configCard("Dados da loja", "Informações principais exibidas e usadas no atendimento.", `
          <div class="vf-store-form-grid">
            <label class="vf-store-field"><span>Nome da loja</span><input name="nome_loja" maxlength="160" value="${esc(config.nome_loja || "")}"></label>
            <label class="vf-store-field"><span>WhatsApp</span><input name="whatsapp" maxlength="30" value="${esc(config.whatsapp || "")}"></label>
            <label class="vf-store-field"><span>Telefone</span><input name="telefone" maxlength="30" value="${esc(config.telefone || "")}"></label>
            <label class="vf-store-field"><span>Cor principal</span><input name="cor_primaria" type="color" value="${esc(/^#[0-9a-f]{6}$/i.test(config.cor_primaria || "") ? config.cor_primaria : "#2563eb")}"></label>
            <label class="vf-store-field vf-span-2"><span>Endereço</span><textarea name="endereco_texto" rows="2">${esc(config.endereco_texto || "")}</textarea></label>
          </div>`)}
        ${configCard("PIX", "Cadastre os dados apresentados para pagamento via PIX.", `
          <div class="vf-store-form-grid">
            <label class="vf-store-field"><span>Tipo de chave</span><select name="pix_tipo">
              <option value="">Selecione</option>${["cpf","cnpj","email","telefone","aleatoria"].map((type) =>
                `<option value="${type}" ${config.pix_tipo === type ? "selected" : ""}>${type === "aleatoria" ? "Chave aleatória" : type.toUpperCase()}</option>`).join("")}
            </select></label>
            <label class="vf-store-field"><span>Chave PIX</span><input name="pix_chave" maxlength="180" value="${esc(config.pix_chave || "")}"></label>
            <label class="vf-store-field vf-span-2"><span>Nome do beneficiário</span><input name="pix_beneficiario" maxlength="160" value="${esc(config.pix_beneficiario || "")}"></label>
            <label class="vf-store-field"><span>Banco</span><input name="pix_banco" maxlength="120" value="${esc(config.pix_banco || "")}"></label>
            <label class="vf-store-field vf-span-2"><span>Texto para o cliente</span><textarea name="pix_instrucoes" rows="3" maxlength="4000" placeholder="Ex.: Nome na chave, telefone para envio do comprovante…">${esc(config.pix_instrucoes || "")}</textarea></label>
            <label class="vf-store-field vf-span-2"><span>Pix copia e cola</span><textarea name="pix_copia_cola" rows="3" maxlength="8192" placeholder="Payload do app do banco (gera QR Code no checkout)">${esc(config.pix_copia_cola || "")}</textarea></label>
          </div>`)}
        ${configCard("Frete na loja online", "Taxa base, modos de cálculo, mapa de origem e ferramentas de distância (VendaFácil).", renderFreteConfigBody(config))}
        ${configCard("Formas de pagamento", "Marque as opções aceitas pela loja.", `
          <div class="vf-payment-grid">
            ${[["pix","PIX"],["cartao_credito","Cartão crédito (maquininha)"],["cartao_debito","Cartão débito (maquininha)"],["cartao_online","Cartão online (gateway)"],["dinheiro","Dinheiro"]].map(([key, label]) =>
              `<label><input type="checkbox" name="payment" value="${key}" ${payments.has(key) || (key.startsWith("cartao_") && key !== "cartao_online" && payments.has("cartao")) ? "checked" : ""}><span>${label}</span></label>`).join("")}
          </div>`)}
        ${configCard("Pagamento online / Gateway", "PIX automático + cartão online no checkout (Mercado Pago). Preencha o token e ative Cartão online na vitrine.", renderGatewayConfigBody(config), "vf-pagamento-gateway")}
        <div class="vf-config-save"><button class="vf-store-btn vf-store-btn--primary" type="submit">Salvar configurações</button></div>
      </form>
    </main>`;

    bindDashboard(root);
    const form = $("vfConfigForm");
    bindFreteConfigTools(form, config);
    form.onchange = (event) => {
      if (event.target.name === "aberta") {
        form.querySelectorAll(".vf-large-radios label").forEach((label) =>
          label.classList.toggle("is-selected", label.contains(event.target)));
      }
    };
    form.onsubmit = async (event) => {
      event.preventDefault();
      const submit = form.querySelector('[type="submit"]');
      const selectedPayments = [...form.querySelectorAll('input[name="payment"]:checked')].map((item) => item.value);
      if (!selectedPayments.length) {
        toast("Selecione ao menos uma forma de pagamento.", "error");
        return;
      }
      submit.disabled = true;
      try {
        const numOrNull = (name) => {
          const raw = value(form, name);
          return raw === "" ? null : Number(raw);
        };
        await api("/configuracoes", { method: "PUT", body: JSON.stringify({
          aberta: value(form, "aberta") === "1",
          confirmar_pedidos: checked(form, "confirmar_pedidos"),
          exigir_pix_confirmado: checked(form, "exigir_pix_confirmado"),
          nome_loja: value(form, "nome_loja") || null,
          whatsapp: value(form, "whatsapp") || null,
          telefone: value(form, "telefone") || null,
          endereco_texto: value(form, "endereco_texto") || null,
          cor_primaria: value(form, "cor_primaria"),
          pix_tipo: value(form, "pix_tipo") || null,
          pix_chave: value(form, "pix_chave") || null,
          pix_beneficiario: value(form, "pix_beneficiario") || null,
          pix_banco: value(form, "pix_banco") || null,
          pix_instrucoes: value(form, "pix_instrucoes") || null,
          pix_copia_cola: value(form, "pix_copia_cola") || null,
          pix_modo: value(form, "pix_modo") || "manual",
          pagamento_gateway: value(form, "pagamento_gateway") || null,
          pagamento_gateway_token: value(form, "pagamento_gateway_token") || null,
          pagamento_gateway_public_key: value(form, "pagamento_gateway_public_key") || null,
          pagamento_gateway_webhook_secret: value(form, "pagamento_gateway_webhook_secret") || null,
          pagamento_gateway_sandbox: checked(form, "pagamento_gateway_sandbox"),
          pagamento_online_ativo: checked(form, "pagamento_online_ativo"),
          pix_expiracao_minutos: Number(value(form, "pix_expiracao_minutos") || 30),
          frete_modo: value(form, "frete_modo"),
          frete_taxa_fixa: Number(value(form, "frete_taxa_fixa") || 0),
          frete_gratis_acima: value(form, "frete_gratis_acima") === "" ? null : Number(value(form, "frete_gratis_acima")),
          frete_acrescimo_chuva_percent: Number(value(form, "frete_acrescimo_chuva_percent") || 0),
          frete_google_rs_por_km: numOrNull("frete_google_rs_por_km"),
          frete_google_taxa_minima: numOrNull("frete_google_taxa_minima"),
          frete_google_km_max: numOrNull("frete_google_km_max"),
          frete_origem_endereco: value(form, "frete_origem_endereco") || null,
          frete_entrega_lat_origem: numOrNull("frete_entrega_lat_origem"),
          frete_entrega_lng_origem: numOrNull("frete_entrega_lng_origem"),
          frete_km_incluso: numOrNull("frete_km_incluso"),
          frete_valor_km_extra: numOrNull("frete_valor_km_extra"),
          permite_retirada: value(form, "permite_retirada") === "1",
          frete_chuva_ativa: checked(form, "frete_chuva_ativa"),
          formas_pagamento: selectedPayments.join(","),
        }) });
        toast("Configurações salvas.", "success");
        await loadDeliveryConfiguracoes();
      } catch (error) {
        toast(error?.message || "Não foi possível salvar as configurações.", "error");
      } finally {
        submit.disabled = false;
      }
    };
  }

  window.loadDeliveryCatalogo = loadDeliveryCatalogo;
  window.loadDeliveryVitrine = loadDeliveryVitrine;
  window.loadDeliveryConfiguracoes = loadDeliveryConfiguracoes;
})();
