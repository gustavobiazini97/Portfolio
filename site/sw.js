// Service worker do portfólio: guarda o site em cache para abrir offline
// e permite que o Chrome o ofereça como app instalável.

// Mudar a versão obriga a recriar a cache (e apaga a antiga no activate)
const CACHE = 'portfolio-v2';

// Ficheiros base, guardados logo na instalação
const SHELL = [
  './',
  './index.html',
  './style.css',
  './script.js',
  './manifest.json',
  './icons/icon-192.png',
  './icons/icon-512.png'
];

// Instalação: pré-carrega a "casca" do site e ativa logo a nova versão
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE)
      .then(cache => cache.addAll(SHELL))
      .then(() => self.skipWaiting())
  );
});

// Ativação: limpa caches de versões anteriores e assume o controlo das páginas abertas
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys()
      .then(keys => Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

// Pedidos: só GET; o resto (ex.: mailto, POST) passa direto
self.addEventListener('fetch', event => {
  const req = event.request;
  if (req.method !== 'GET') return;

  const url = new URL(req.url);
  const sameOrigin = url.origin === self.location.origin;
  const isFont = url.hostname === 'fonts.googleapis.com' || url.hostname === 'fonts.gstatic.com';

  // Navegação (abrir a página): rede primeiro, para mostrar sempre a versão mais recente;
  // sem rede, usa a cópia guardada
  if (req.mode === 'navigate') {
    event.respondWith(
      fetch(req)
        .then(res => {
          const copy = res.clone();
          caches.open(CACHE).then(cache => cache.put(req, copy));
          return res;
        })
        .catch(() => caches.match(req).then(hit => hit || caches.match('./index.html')))
    );
    return;
  }

  // CSS, JS, imagens e fontes: responde já com a cache e atualiza-a em segundo plano
  if (sameOrigin || isFont) {
    event.respondWith(
      caches.open(CACHE).then(cache =>
        cache.match(req).then(hit => {
          const network = fetch(req)
            .then(res => {
              // Só guarda respostas válidas; o CSS do Google Fonts chega como resposta
              // "opaca" (outro domínio, sem CORS), por isso também é aceite
              if (res.ok || res.type === 'opaque') cache.put(req, res.clone());
              return res;
            })
            .catch(() => hit);
          return hit || network;
        })
      )
    );
  }
});
