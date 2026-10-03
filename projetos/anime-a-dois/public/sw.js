// Service worker do Anime a Dois.
// Guarda só ficheiros estáticos (CSS, JS, ícones, fontes). As páginas vêm sempre do servidor,
// porque têm dados pessoais e tokens de sessão; sem rede mostra-se a página offline.html.

// Mudar a versão força a atualização da cache nas apps instaladas
const CACHE = 'anime-a-dois-v2';

const ESTATICOS = [
  './css/app.css',
  './js/app.js',
  './js/tema.js',
  './offline.html',
  './manifest.json',
  './icons/icon-192.png',
  './icons/icon-512.png'
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

  // Estáticos do próprio site e fontes do Google: cache primeiro, atualizada em segundo plano
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
