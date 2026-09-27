// Service worker: a statikus fájlokat gyorsítótárazza (gyors indulás, app-élmény),
// az oldalakat hálózatról tölti, és csak internet nélkül ad offline üzenetet.
// Az API-hívásokat soha nem gyorsítótárazzuk — a beszélgetés mindig élő.
const CACHE = 'chatbot-v1';
const ASSETS = ['assets/style.css', 'assets/chat.js', 'assets/admin.js', 'assets/offline.html'];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE).then((c) => c.addAll(ASSETS)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== location.origin || url.pathname.includes('/api/')) return;

  if (req.mode === 'navigate') {
    event.respondWith(fetch(req).catch(() => caches.match('assets/offline.html')));
    return;
  }

  if (url.pathname.includes('/assets/') || url.pathname.endsWith('icon.php')) {
    // stale-while-revalidate: azonnal a tárolt, háttérben frissítjük
    event.respondWith(
      caches.open(CACHE).then(async (cache) => {
        const cached = await cache.match(req);
        const fresh = fetch(req).then((res) => {
          if (res.ok) cache.put(req, res.clone());
          return res;
        }).catch(() => cached);
        return cached || fresh;
      })
    );
  }
});
