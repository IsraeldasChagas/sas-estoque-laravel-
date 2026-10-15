(function () {
  function instalado() {
    return window.matchMedia("(display-mode: standalone)").matches || window.navigator.standalone === true;
  }

  function botoes(mostrar) {
    document.querySelectorAll("[data-pwa-install]").forEach((btn) => {
      btn.hidden = !mostrar;
    });
  }

  let aguardando = null;

  window.addEventListener("beforeinstallprompt", (event) => {
    event.preventDefault();
    aguardando = event;
    if (!instalado()) botoes(true);
  });

  window.addEventListener("appinstalled", () => {
    aguardando = null;
    botoes(false);
    document.querySelectorAll("[data-pwa-ios]").forEach((el) => {
      el.hidden = true;
    });
  });

  document.addEventListener("click", (event) => {
    const btn = event.target.closest("[data-pwa-install]");
    if (!btn || !aguardando) return;
    event.preventDefault();
    const prompt = aguardando;
    aguardando = null;
    botoes(false);
    prompt.prompt();
  });

  function iniciar() {
    botoes(false);
    const ios = /iphone|ipad|ipod/i.test(navigator.userAgent);
    document.querySelectorAll("[data-pwa-ios]").forEach((el) => {
      el.hidden = !(ios && !instalado());
    });
    if ("serviceWorker" in navigator) {
      navigator.serviceWorker.register("./sw.js", { scope: "./" }).catch(() => {});
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", iniciar);
  } else {
    iniciar();
  }
})();
