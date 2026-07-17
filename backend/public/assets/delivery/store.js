(function () {
  'use strict';
  const key = 'delivery-cart:' + window.deliveryStore.slug;
  const read = () => {
    try {
      const value = JSON.parse(localStorage.getItem(key) || '[]');
      return Array.isArray(value) ? value.filter(item => item && item.produto_id) : [];
    } catch (_) {
      return [];
    }
  };
  const write = items => {
    localStorage.setItem(key, JSON.stringify(items));
    render();
  };
  const escape = value => String(value ?? '').replace(/[&<>"']/g, char => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
  })[char]);
  const money = value => Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  const unit = item => Number(item.preco) + (item.opcoes?.adicionais || [])
    .reduce((sum, addition) => sum + Number(addition.preco) * Number(addition.quantidade), 0);
  const total = items => items.reduce((sum, item) => sum + unit(item) * Number(item.quantidade), 0);

  function render() {
    const items = read();
    document.querySelectorAll('[data-cart-count]').forEach(el => {
      el.textContent = items.reduce((sum, item) => sum + Number(item.quantidade), 0);
    });
    const target = document.querySelector('[data-cart-items]');
    if (!target) return;
    target.innerHTML = items.length ? items.map((item, index) => {
      const options = [
        ...(item.opcoes?.adicionais || []).map(a => `${a.quantidade}× ${a.nome}`),
        ...(item.opcoes?.retiradas || []).map(r => `Sem ${r.nome}`)
      ].map(escape).join(', ');
      const photo = item.foto
        ? `<img src="${escape(item.foto)}" alt="">`
        : '<span class="cart-thumb"></span>';
      return `<div class="cart-line">${photo}<div><strong>${escape(item.nome)}</strong>` +
        `<small>${options}</small><b>${money(unit(item) * item.quantidade)}</b>` +
        `<div class="cart-controls"><button data-cart-minus="${index}">−</button><span>${item.quantidade}</span>` +
        `<button data-cart-plus="${index}">+</button><button class="remove" data-cart-remove="${index}">Remover</button></div>` +
        '</div></div>';
    }).join('') : '<p class="empty">Seu carrinho está vazio.</p>';
    document.querySelector('[data-cart-total]').textContent = money(total(items));
    target.querySelectorAll('[data-cart-minus]').forEach(button => button.onclick = () => {
      const current = read(), index = Number(button.dataset.cartMinus);
      current[index].quantidade = Math.max(1, Number(current[index].quantidade) - 1);
      write(current);
    });
    target.querySelectorAll('[data-cart-plus]').forEach(button => button.onclick = () => {
      const current = read(), index = Number(button.dataset.cartPlus);
      current[index].quantidade = Math.min(99, Number(current[index].quantidade) + 1);
      write(current);
    });
    target.querySelectorAll('[data-cart-remove]').forEach(button => button.onclick = () => {
      const current = read();
      current.splice(Number(button.dataset.cartRemove), 1);
      write(current);
    });
  }

  const drawer = document.querySelector('[data-cart-drawer]');
  document.querySelectorAll('[data-cart-open]').forEach(button => button.onclick = () => {
    drawer.classList.add('open');
    drawer.setAttribute('aria-hidden', 'false');
  });
  document.querySelectorAll('[data-cart-close]').forEach(button => button.onclick = () => {
    drawer.classList.remove('open');
    drawer.setAttribute('aria-hidden', 'true');
  });
  const dialog = document.querySelector('[data-track-dialog]');
  document.querySelectorAll('[data-track-open]').forEach(button => button.onclick = () => dialog.showModal());
  document.querySelectorAll('[data-track-close]').forEach(button => button.onclick = () => dialog.close());

  const storeInfo = document.querySelector('[data-store-info]');
  const contactToggle = document.querySelector('[data-contact-toggle]');
  if (storeInfo && contactToggle) {
    contactToggle.addEventListener('click', () => {
      const collapsed = storeInfo.classList.toggle('is-collapsed');
      contactToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    });
  }

  window.DeliveryCart = {
    items: read,
    add(item) { const items = read(); items.push(item); write(items); },
    clear() { localStorage.removeItem(key); render(); },
    money,
    escape
  };
  render();
})();
