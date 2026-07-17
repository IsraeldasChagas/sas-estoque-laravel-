/**
 * VendaFácil-parity administration for Delivery categories and additionals.
 * Loaded after delivery.js so these functions intentionally replace its screens.
 */
(function () {
  "use strict";

  const categoryState = { items: [] };
  const additionalState = { items: [] };
  const $ = (id) => document.getElementById(id);
  const esc = (value) => String(value == null ? "" : value)
    .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;").replace(/'/g, "&#39;");
  const api = (path, options) => {
    if (typeof window.fetchJSON !== "function") throw new Error("Conexão com a API indisponível.");
    return window.fetchJSON(`/delivery${path}`, options || {});
  };
  const toast = (message, type) => window.showToast?.(message, type || "info");
  const money = (value) => Number(value || 0).toLocaleString("pt-BR", {
    style: "currency", currency: "BRL",
  });
  const plural = (count, singular, pluralText) => `${Number(count || 0)} ${Number(count) === 1 ? singular : pluralText}`;
  const checked = (value, fallback) => (value == null ? fallback : Number(value) === 1) ? "checked" : "";

  function imageUrl(path) {
    if (!path) return "";
    if (/^(https?:|data:)/i.test(path)) return path;
    const apiBase = String(window.APP_CONFIG?.API_URL || "").replace(/\/api\/?$/, "");
    return `${apiBase}${String(path).startsWith("/") ? "" : "/"}${path}`;
  }

  function breadcrumb(current, backLabel, backTarget) {
    return `<div class="vf-catalog__breadcrumb">
      <button type="button" data-vf-nav="${esc(backTarget || "deliveryDashboard")}">${esc(backLabel || "Dashboard")}</button>
      <span>/</span><span>${esc(current)}</span>
    </div>`;
  }

  function bindNavigation(root) {
    root.querySelectorAll("[data-vf-nav]").forEach((button) => {
      button.onclick = () => window.navigateTo?.(button.dataset.vfNav);
    });
  }

  function categoryRows(items) {
    if (!items.length) {
      return `<div class="vf-catalog-empty">
        <strong>Nenhuma categoria cadastrada.</strong>
        <span>Crie a primeira categoria para organizar seu cardápio.</span>
        <button class="vf-btn vf-btn--link" type="button" data-vf-new-category>Criar primeira categoria</button>
      </div>`;
    }

    return items.map((item) => `<div class="vf-catalog-row">
      <div class="vf-catalog-row__main">
        <strong>${esc(item.nome)}</strong>
        <span>${plural(item.product_count, "produto", "produtos")}</span>
      </div>
      <div class="vf-catalog-row__meta">
        ${Number(item.ativo) ? "" : '<span class="vf-badge vf-badge--inactive">Inativa</span>'}
        <span class="vf-order">Ordem #${Number(item.ordem || 0)}</span>
        <button class="vf-btn" type="button" data-vf-edit-category="${item.id}">Editar</button>
        <button class="vf-btn vf-btn--danger" type="button" data-vf-delete-category="${item.id}">Excluir</button>
      </div>
    </div>`).join("");
  }

  async function loadCategories() {
    const root = $("deliveryCategoriasRoot");
    if (!root) return;

    try {
      const data = await api("/categorias");
      categoryState.items = data.items || [];
      root.innerHTML = `<div class="vf-catalog">
        ${breadcrumb("Categorias")}
        <div class="vf-catalog__toolbar">
          <h2>Categorias do cardápio</h2>
          <div class="vf-catalog__actions">
            <button class="vf-btn" type="button" data-vf-nav="deliveryProdutos">Produtos</button>
            <button class="vf-btn vf-btn--primary" type="button" data-vf-new-category>＋ Nova categoria</button>
          </div>
        </div>
        <div class="vf-card vf-catalog-list">${categoryRows(categoryState.items)}</div>
      </div>`;
      bindNavigation(root);
      root.onclick = async (event) => {
        if (event.target.closest("[data-vf-new-category]")) {
          renderCategoryEditor(root, null);
          return;
        }
        const edit = event.target.closest("[data-vf-edit-category]");
        if (edit) {
          const item = categoryState.items.find((row) => Number(row.id) === Number(edit.dataset.vfEditCategory));
          if (item) renderCategoryEditor(root, item);
          return;
        }
        const remove = event.target.closest("[data-vf-delete-category]");
        if (!remove || !confirm("Excluir esta categoria?")) return;
        try {
          await api(`/categorias/${remove.dataset.vfDeleteCategory}`, { method: "DELETE" });
          toast("Categoria excluída.", "success");
          await loadCategories();
        } catch (error) {
          toast(error?.message || "Não foi possível excluir a categoria.", "error");
        }
      };
    } catch (error) {
      toast(error?.message || "Não foi possível carregar as categorias.", "error");
    }
  }

  function renderCategoryEditor(root, item) {
    const editing = !!item;
    root.innerHTML = `<div class="vf-catalog vf-catalog-editor">
      ${breadcrumb(editing ? "Editar categoria" : "Nova categoria", "Categorias", "deliveryCategorias")}
      <div class="vf-card vf-catalog-form-card">
        <h2>${editing ? "Editar categoria" : "Nova categoria"}</h2>
        <form id="vfCategoryForm">
          <label class="vf-field"><span>Nome</span><input name="nome" maxlength="255" value="${esc(item?.nome || "")}" required autofocus></label>
          <label class="vf-field"><span>Ordem</span><input name="ordem" type="number" min="0" max="65535" value="${Number(item?.ordem || 0)}" required></label>
          <label class="vf-check"><input name="ativo" type="checkbox" ${checked(item?.ativo, true)}> Ativa</label>
          <div class="vf-catalog-form__actions">
            <button class="vf-btn vf-btn--primary" type="submit">Salvar</button>
            <button class="vf-btn" type="button" data-vf-cancel-category>Cancelar</button>
          </div>
        </form>
      </div>
    </div>`;
    bindNavigation(root);
    root.querySelector("[data-vf-cancel-category]").onclick = loadCategories;
    $("vfCategoryForm").onsubmit = async (event) => {
      event.preventDefault();
      const form = event.currentTarget;
      const submit = form.querySelector('[type="submit"]');
      submit.disabled = true;
      try {
        await api(editing ? `/categorias/${item.id}` : "/categorias", {
          method: editing ? "PUT" : "POST",
          body: JSON.stringify({
            nome: form.elements.nome.value.trim(),
            ordem: Number(form.elements.ordem.value),
            ativo: form.elements.ativo.checked,
          }),
        });
        toast(editing ? "Categoria atualizada." : "Categoria criada.", "success");
        await loadCategories();
      } catch (error) {
        toast(error?.message || "Não foi possível salvar a categoria.", "error");
      } finally {
        submit.disabled = false;
      }
    };
  }

  function additionalThumb(item) {
    const url = imageUrl(item.foto_url);
    return `<span class="vf-additional-thumb">${url ? `<img src="${esc(url)}" alt="">` : "＋"}</span>`;
  }

  function additionalRows(items) {
    if (!items.length) {
      return `<div class="vf-catalog-empty">
        <strong>Nenhum adicional cadastrado.</strong>
        <span>Crie adicionais para oferecer opções aos seus clientes.</span>
        <button class="vf-btn vf-btn--link" type="button" data-vf-new-additional>Criar primeiro adicional</button>
      </div>`;
    }

    return items.map((item) => `<div class="vf-catalog-row vf-catalog-row--additional">
      ${additionalThumb(item)}
      <div class="vf-catalog-row__main">
        <strong>${esc(item.nome)}</strong>
        <span>${plural(item.product_count, "produto", "produtos")}</span>
      </div>
      <div class="vf-catalog-row__meta">
        <span class="vf-badge ${item.tipo === "retirar" ? "vf-badge--remove" : "vf-badge--price"}">${item.tipo === "retirar" ? "Retirar" : `+ ${money(item.preco)}`}</span>
        ${Number(item.ativo) ? "" : '<span class="vf-badge vf-badge--inactive">Inativo</span>'}
        <span class="vf-order">Ordem #${Number(item.ordem || 0)}</span>
        <button class="vf-btn" type="button" data-vf-edit-additional="${item.id}">Editar</button>
        <button class="vf-btn vf-btn--danger" type="button" data-vf-delete-additional="${item.id}">Excluir</button>
      </div>
    </div>`).join("");
  }

  async function loadAdditionals() {
    const root = $("deliveryAdicionaisRoot");
    if (!root) return;

    try {
      const data = await api("/adicionais");
      additionalState.items = data.items || [];
      root.innerHTML = `<div class="vf-catalog">
        ${breadcrumb("Adicionais")}
        <div class="vf-catalog__toolbar">
          <div>
            <h2>Adicionais</h2>
            <p>Cadastre opções para acrescentar ao produto ou ingredientes que o cliente pode retirar.</p>
          </div>
          <div class="vf-catalog__actions">
            <button class="vf-btn" type="button" data-vf-nav="deliveryProdutos">Produtos</button>
            <button class="vf-btn vf-btn--primary" type="button" data-vf-new-additional>＋ Novo adicional</button>
          </div>
        </div>
        <div class="vf-card vf-catalog-list">${additionalRows(additionalState.items)}</div>
      </div>`;
      bindNavigation(root);
      root.onclick = async (event) => {
        if (event.target.closest("[data-vf-new-additional]")) {
          renderAdditionalEditor(root, null);
          return;
        }
        const edit = event.target.closest("[data-vf-edit-additional]");
        if (edit) {
          try {
            const item = await api(`/adicionais/${edit.dataset.vfEditAdditional}`);
            renderAdditionalEditor(root, item);
          } catch (error) {
            toast(error?.message || "Não foi possível abrir o adicional.", "error");
          }
          return;
        }
        const remove = event.target.closest("[data-vf-delete-additional]");
        if (!remove || !confirm("Excluir este adicional? Os vínculos com produtos também serão removidos.")) return;
        try {
          await api(`/adicionais/${remove.dataset.vfDeleteAdditional}`, { method: "DELETE" });
          toast("Adicional excluído.", "success");
          await loadAdditionals();
        } catch (error) {
          toast(error?.message || "Não foi possível excluir o adicional.", "error");
        }
      };
    } catch (error) {
      toast(error?.message || "Não foi possível carregar os adicionais.", "error");
    }
  }

  function renderAdditionalEditor(root, item) {
    const editing = !!item;
    const photo = imageUrl(item?.foto_url);
    root.innerHTML = `<div class="vf-catalog vf-catalog-editor">
      ${breadcrumb(editing ? "Editar adicional" : "Novo adicional", "Adicionais", "deliveryAdicionais")}
      <div class="vf-card vf-catalog-form-card">
        <h2>${editing ? "Editar adicional" : "Novo adicional"}</h2>
        <form id="vfAdditionalForm">
          <label class="vf-field"><span>Nome</span><input name="nome" maxlength="120" value="${esc(item?.nome || "")}" required autofocus></label>
          <label class="vf-field"><span>Ordem</span><input name="ordem" type="number" min="0" max="9999" value="${Number(item?.ordem || 0)}" required></label>
          <label class="vf-field"><span>Tipo</span><select name="tipo">
            <option value="acrescentar" ${item?.tipo !== "retirar" ? "selected" : ""}>Acrescentar</option>
            <option value="retirar" ${item?.tipo === "retirar" ? "selected" : ""}>Retirar</option>
          </select></label>
          <label class="vf-field" data-vf-price-field ${item?.tipo === "retirar" ? "hidden" : ""}><span>Preço (R$)</span>
            <input name="preco" type="number" min="0" step="0.01" value="${esc(item?.tipo === "retirar" ? 0 : (item?.preco ?? 0))}" required>
          </label>
          <div class="vf-field">
            <span>Foto</span>
            <div class="vf-additional-photo">
              <span class="vf-additional-photo__preview ${photo ? "is-visible" : ""}" data-vf-photo-preview>${photo ? `<img src="${esc(photo)}" alt="Prévia da foto">` : "＋"}</span>
              <div>
                <input type="file" name="foto" accept="image/jpeg,image/png,image/webp,image/gif">
                <small>JPG, PNG, WebP ou GIF, até 2 MB.</small>
                ${photo ? '<button class="vf-btn vf-btn--link" type="button" data-vf-remove-photo>Remover foto</button>' : ""}
              </div>
            </div>
          </div>
          <label class="vf-check"><input name="ativo" type="checkbox" ${checked(item?.ativo, true)}> Ativo</label>
          <div class="vf-catalog-form__actions">
            <button class="vf-btn vf-btn--primary" type="submit">Salvar</button>
            <button class="vf-btn" type="button" data-vf-cancel-additional>Cancelar</button>
          </div>
        </form>
      </div>
    </div>`;
    bindNavigation(root);
    const form = $("vfAdditionalForm");
    let removePhoto = false;
    form.querySelector("[data-vf-cancel-additional]").onclick = loadAdditionals;
    form.elements.tipo.onchange = () => {
      const removing = form.elements.tipo.value === "retirar";
      form.querySelector("[data-vf-price-field]").hidden = removing;
      if (removing) form.elements.preco.value = "0";
    };
    form.elements.foto.onchange = () => {
      const file = form.elements.foto.files?.[0];
      if (!file) return;
      const preview = form.querySelector("[data-vf-photo-preview]");
      preview.innerHTML = `<img src="${esc(URL.createObjectURL(file))}" alt="Prévia da foto">`;
      preview.classList.add("is-visible");
      removePhoto = false;
    };
    form.querySelector("[data-vf-remove-photo]")?.addEventListener("click", (event) => {
      event.currentTarget.remove();
      form.elements.foto.value = "";
      const preview = form.querySelector("[data-vf-photo-preview]");
      preview.innerHTML = "＋";
      preview.classList.remove("is-visible");
      removePhoto = true;
    });
    form.onsubmit = async (event) => {
      event.preventDefault();
      const submit = form.querySelector('[type="submit"]');
      submit.disabled = true;
      try {
        const file = form.elements.foto.files?.[0];
        const type = form.elements.tipo.value;
        const payload = {
          nome: form.elements.nome.value.trim(),
          ordem: Number(form.elements.ordem.value),
          tipo: type,
          preco: type === "retirar" ? 0 : Number(form.elements.preco.value),
          ativo: form.elements.ativo.checked,
          remover_foto: removePhoto,
          foto_base64: file ? await fileToDataUrl(file) : null,
        };
        await api(editing ? `/adicionais/${item.id}` : "/adicionais", {
          method: editing ? "PUT" : "POST",
          body: JSON.stringify(payload),
        });
        toast(editing ? "Adicional atualizado." : "Adicional criado.", "success");
        await loadAdditionals();
      } catch (error) {
        toast(error?.message || "Não foi possível salvar o adicional.", "error");
      } finally {
        submit.disabled = false;
      }
    };
  }

  function fileToDataUrl(file) {
    const allowed = ["image/jpeg", "image/png", "image/webp", "image/gif"];
    if (!allowed.includes(file.type)) return Promise.reject(new Error("A foto deve ser JPG, PNG, WebP ou GIF."));
    if (file.size > 2 * 1024 * 1024) return Promise.reject(new Error("A foto não pode exceder 2 MB."));
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = () => resolve(String(reader.result));
      reader.onerror = () => reject(new Error("Não foi possível ler a foto."));
      reader.readAsDataURL(file);
    });
  }

  window.loadDeliveryCategorias = loadCategories;
  window.loadDeliveryAdicionais = loadAdditionals;
})();
