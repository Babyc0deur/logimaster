// Service worker LogiMaster Convoyeur : coque de l'application hors réseau + notifications push.
// La version du cache change à chaque déploiement de l'application : les téléphones reprennent la nouvelle coque.
const CACHE = 'lm-shell-{{ $version }}';
@verbatim
const SHELL = ['/m', '/m/manifest.webmanifest', '/pwa/icon-192.png', '/pwa/icon-512.png'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((c) => c.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    const url = new URL(req.url);
    if (req.method === 'GET' && (url.hostname === 'fonts.googleapis.com' || url.hostname === 'fonts.gstatic.com')) {
        event.respondWith(caches.match(req).then((hit) => hit || fetch(req).then((res) => {
            const copy = res.clone();
            caches.open(CACHE).then((c) => c.put(req, copy));
            return res;
        })));
        return;
    }
    if (req.method !== 'GET' || url.origin !== location.origin || url.pathname.startsWith('/api/')) {
        return; // l'API n'est jamais mise en cache : les données hors réseau sont gérées par l'application
    }
    if (req.mode === 'navigate' && url.pathname.startsWith('/m')) {
        // page : le réseau d'abord (version à jour), la copie en cache si pas de réseau
        event.respondWith(
            fetch(req).then((res) => {
                const copy = res.clone();
                caches.open(CACHE).then((c) => c.put('/m', copy));
                return res;
            }).catch(() => caches.match('/m'))
        );
        return;
    }
    if (url.pathname.startsWith('/pwa/') || url.pathname.startsWith('/m/')) {
        event.respondWith(caches.match(req).then((hit) => hit || fetch(req).then((res) => {
            const copy = res.clone();
            caches.open(CACHE).then((c) => c.put(req, copy));
            return res;
        })));
    }
});

self.addEventListener('push', (event) => {
    let data = {};
    try { data = event.data ? event.data.json() : {}; } catch (e) { data = { title: 'LogiMaster', body: event.data ? event.data.text() : '' }; }
    event.waitUntil(self.registration.showNotification(data.title || 'LogiMaster', {
        body: data.body || '',
        icon: '/pwa/icon-192.png',
        badge: '/pwa/icon-192.png',
        tag: data.tag || 'logimaster',
        data: { url: data.url || '/m' },
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = (event.notification.data && event.notification.data.url) || '/m';
    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((wins) => {
        for (const w of wins) {
            if (w.url.includes('/m') && 'focus' in w) { w.postMessage({ type: 'refresh' }); return w.focus(); }
        }
        return self.clients.openWindow(target);
    }));
});
@endverbatim
