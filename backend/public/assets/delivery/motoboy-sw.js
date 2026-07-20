/* Motoboy PWA — cache leve + notificações */
const CACHE = "sas-motoboy-v9";
const ASSETS = [
  "/assets/delivery/motoboy-app.css?v=20260720-m9",
  "/assets/delivery/motoboy-app.js?v=20260720-m9",
  "/assets/delivery/motoboy-icon-192.png?v=20260720-esp",
];

self.addEventListener("install", (event) => {
  event.waitUntil(caches.open(CACHE).then((c) => c.addAll(ASSETS).catch(() => {})));
  self.skipWaiting();
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))
    )
  );
  self.clients.claim();
});

self.addEventListener("notificationclick", (event) => {
  event.notification.close();
  const target = (event.notification && event.notification.data && event.notification.data.url)
    ? event.notification.data.url
    : self.registration.scope;
  event.waitUntil(
    clients.matchAll({ type: "window", includeUncontrolled: true }).then((list) => {
      for (const client of list) {
        if ("focus" in client) {
          client.focus();
          if (client.url && client.navigate) {
            try { client.navigate(target); } catch (_) {}
          }
          return;
        }
      }
      if (clients.openWindow) return clients.openWindow(target);
    })
  );
});

self.addEventListener("fetch", (event) => {
  const req = event.request;
  if (req.method !== "GET") return;
  const url = new URL(req.url);
  if (url.pathname.includes("/ofertas.json") || url.pathname.includes("/aceitar/") || url.pathname.includes("/recusar/")) {
    return;
  }
  event.respondWith(
    caches.match(req).then((cached) => cached || fetch(req).then((res) => {
      const copy = res.clone();
      if (res.ok && (url.pathname.includes("/assets/delivery/") || url.pathname.includes("/motoboy/"))) {
        caches.open(CACHE).then((c) => c.put(req, copy)).catch(() => {});
      }
      return res;
    }).catch(() => cached))
  );
});
