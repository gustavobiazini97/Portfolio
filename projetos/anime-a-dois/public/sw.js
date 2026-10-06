// Service worker do Anime a Dois.
// Guarda só ficheiros estáticos (CSS, JS, ícones, fontes). As páginas vêm sempre do servidor,
// porque têm dados pessoais e tokens de sessão; sem rede mostra-se a página offline.html.

// Mudar a versão força a atualização da cache nas apps instaladas
const CACHE = 'anime-a-dois-v8';

const ESTATICOS = [
  './css/app.css',
  './js/app.js',
  './js/tema.js',
  './offline.html',
  './manifest.json',
  './icons/icon-192.png',
  './icons/icon-512.png',
  './img/logo.svg'
];

// Instalação: guarda os estáticos e ativa logo
self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(ESTATICOS)).then(() => self.skipWaiting()));
});

// Ativação: apaga caches de versões antigas
self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((chaves) => Promise.all(chaves.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (e) => {
  const pedido = e.request;
  if (pedido.method !== 'GET') return;            // POST (marcar, entrar...) vai direto ao servidor

  const url = new URL(pedido.url);

  // Páginas: sempre da rede; sem ligação, a página offline
  if (pedido.mode === 'navigate') {
    e.respondWith(fetch(pedido).catch(() => caches.match('./offline.html')));
    return;
  }

  // CSS/JS/ícones do próprio site: rede primeiro (assim a app atualiza logo ao abrir);
  // sem ligação, serve o que está guardado
  if (url.origin === self.location.origin && /\.(css|js|png|json|html)$/.test(url.pathname)) {
    e.respondWith(
      fetch(pedido)
        .then((r) => {
          if (r.ok) caches.open(CACHE).then((c) => c.put(pedido, r.clone()));
          return r;
        })
        .catch(() => caches.match(pedido))
    );
    return;
  }

  // Fontes do Google: cache primeiro, atualizada em segundo plano
  const fonte = url.hostname === 'fonts.googleapis.com' || url.hostname === 'fonts.gstatic.com';
  const estatico = url.origin === self.location.origin && /\.(css|js|png|json|html)$/.test(url.pathname);
  if (!fonte && !estatico) return;

  e.respondWith(
    caches.open(CACHE).then((c) =>
      c.match(pedido).then((guardado) => {
        const rede = fetch(pedido)
          .then((r) => {
            if (r.ok || r.type === 'opaque') c.put(pedido, r.clone());   // CSS do Google chega "opaco"
            return r;
          })
          .catch(() => guardado);
        return guardado || rede;
      })
    )
  );
});

// ---------- Notificações (Web Push) ----------

// Chega uma notificação do servidor: mostra-a (título, texto, ícone; tocar abre o episódio)
self.addEventListener('push', (e) => {
  let msg = { titulo: 'Anime a Dois', corpo: '', url: './', tag: 'geral' };
  try { msg = Object.assign(msg, e.data.json()); } catch (erro) { /* payload inválido: mostra o genérico */ }

  e.waitUntil(self.registration.showNotification(msg.titulo, {
    body: msg.corpo,
    icon: './icons/icon-192.png',
    badge: './icons/icon-192.png',
    image: msg.imagem || undefined,   // capa da série (séries novas e propostas), em grande no Android
    tag: msg.tag,          // a mesma tag substitui a anterior em vez de empilhar
    renotify: true,        // mas volta a vibrar/tocar
    data: { url: msg.url }
  }));
});

// Tocar na notificação: abre a app nesse episódio (reaproveita uma janela já aberta)
self.addEventListener('notificationclick', (e) => {
  e.notification.close();
  const url = (e.notification.data && e.notification.data.url) || './';
  e.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((janelas) => {
      for (const j of janelas) {
        if ('navigate' in j) { return j.focus().then(() => j.navigate(url)); }
      }
      return self.clients.openWindow(url);
    })
  );
});
