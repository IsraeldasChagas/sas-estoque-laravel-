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
        const available = product.disponivel !== false && Number(product.estoque || 0) > 0;
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
              <span class="${available ? "is-available" : "is-unavailable"}">${available ? `${Number(product.estoque)} em estoque` : "Indisponível"}</span>
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

  const configCard = (title, description, body) => `<details class="vf-config-card" open>
    <summary><div><h3>${esc(title)}</h3><p>${esc(description)}</p></div><span aria-hidden="true">⌃</span></summary>
    <div class="vf-config-card__body">${body}</div>
  </details>`;

  async function loadDeliveryConfiguracoes() {
    const root = $("deliveryConfiguracoesRoot");
    if (!root) return;
    const config = await api("/configuracoes");
    const payments = new Set(String(config.formas_pagamento || "pix,cartao,dinheiro").split(",").map((item) => item.trim()).filter(Boolean));

    root.innerHTML = `<main class="vf-store vf-config">
      ${breadcrumb("Configurações")}
      ${heading("Configurações do delivery", "Defina a operação da vitrine, recebimento e entrega dos pedidos.")}
      <form id="vfConfigForm">
        ${configCard("Pedidos na vitrine", "Escolha como novos pedidos entram na operação.", `
          <label class="vf-setting-switch"><input name="confirmar_pedidos" type="checkbox" ${config.confirmar_pedidos ? "checked" : ""}>
          <span><strong>Confirmar pedidos manualmente</strong><small>Pedidos aguardam a confirmação da loja antes do preparo.</small></span></label>`)}
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
          </div>`)}
        ${configCard("Frete", "Configure entrega fixa, faixas de CEP e condições especiais.", `
          <div class="vf-store-form-grid">
            <label class="vf-store-field"><span>Cálculo do frete</span><select name="frete_modo">
              <option value="fixed" ${config.frete_modo === "fixed" ? "selected" : ""}>Taxa fixa</option>
              <option value="cep_band" ${config.frete_modo === "cep_band" ? "selected" : ""}>Faixas de CEP</option>
            </select></label>
            <label class="vf-store-field"><span>Taxa base (R$)</span><input name="frete_taxa_fixa" type="number" min="0" step="0.01" value="${esc(config.frete_taxa_fixa ?? 0)}"></label>
            <label class="vf-store-field"><span>Frete grátis acima de (R$)</span><input name="frete_gratis_acima" type="number" min="0" step="0.01" value="${esc(config.frete_gratis_acima ?? "")}"></label>
            <label class="vf-store-field"><span>Acréscimo de chuva (%)</span><input name="frete_acrescimo_chuva_percent" type="number" min="0" step="0.01" value="${esc(config.frete_acrescimo_chuva_percent ?? 0)}"></label>
          </div>
          <div class="vf-toggle-row">
            <label><input name="permite_retirada" type="checkbox" ${config.permite_retirada ? "checked" : ""}><span>Permitir retirada na loja</span></label>
            <label><input name="frete_chuva_ativa" type="checkbox" ${config.frete_chuva_ativa ? "checked" : ""}><span>Aplicar acréscimo de chuva</span></label>
          </div>
          <p class="vf-config-note">As faixas são cadastradas na tela Fretes quando o modo por CEP estiver selecionado.</p>`)}
        ${configCard("Formas de pagamento", "Marque as opções aceitas pela loja.", `
          <div class="vf-payment-grid">
            ${[["pix","PIX"],["cartao","Cartão"],["dinheiro","Dinheiro"]].map(([key, label]) =>
              `<label><input type="checkbox" name="payment" value="${key}" ${payments.has(key) ? "checked" : ""}><span>${label}</span></label>`).join("")}
          </div>`)}
        <div class="vf-config-save"><button class="vf-store-btn vf-store-btn--primary" type="submit">Salvar configurações</button></div>
      </form>
    </main>`;

    bindDashboard(root);
    const form = $("vfConfigForm");
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
        await api("/configuracoes", { method: "PUT", body: JSON.stringify({
          aberta: value(form, "aberta") === "1",
          confirmar_pedidos: checked(form, "confirmar_pedidos"),
          nome_loja: value(form, "nome_loja") || null,
          whatsapp: value(form, "whatsapp") || null,
          telefone: value(form, "telefone") || null,
          endereco_texto: value(form, "endereco_texto") || null,
          cor_primaria: value(form, "cor_primaria"),
          pix_tipo: value(form, "pix_tipo") || null,
          pix_chave: value(form, "pix_chave") || null,
          pix_beneficiario: value(form, "pix_beneficiario") || null,
          frete_modo: value(form, "frete_modo"),
          frete_taxa_fixa: Number(value(form, "frete_taxa_fixa") || 0),
          frete_gratis_acima: value(form, "frete_gratis_acima") === "" ? null : Number(value(form, "frete_gratis_acima")),
          frete_acrescimo_chuva_percent: Number(value(form, "frete_acrescimo_chuva_percent") || 0),
          permite_retirada: checked(form, "permite_retirada"),
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
