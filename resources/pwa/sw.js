const CACHE_VERSION = __CACHE_VERSION__;
const APP_NAME = __APP_NAME__;
const NOTIFICATION_FALLBACK = __NOTIFICATION_FALLBACK__;
const OPEN_ACTION = __OPEN_ACTION__;
const OFFLINE_TITLE = __OFFLINE_TITLE__;
const OFFLINE_MESSAGE = __OFFLINE_MESSAGE__;
const CACHE_PREFIX = 'nossa-casa';
const STATIC_CACHE = `${CACHE_PREFIX}-static-${CACHE_VERSION}`;
const CORE_ASSETS = [
    '/manifest.webmanifest',
    '/favicon.ico',
    '/apple-touch-icon.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(STATIC_CACHE)
            .then((cache) =>
                Promise.allSettled(
                    CORE_ASSETS.map((asset) => cache.add(asset)),
                ),
            )
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter(
                            (key) =>
                                key.startsWith(`${CACHE_PREFIX}-`) &&
                                key !== STATIC_CACHE,
                        )
                        .map((key) => caches.delete(key)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('message', (event) => {
    if (event.data?.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }

    if (event.data?.type === 'SHOW_NOTIFICATION') {
        event.waitUntil(showNotification(event.data.notification));
    }
});

self.addEventListener('push', (event) => {
    let notification = {};

    try {
        notification = event.data?.json() ?? {};
    } catch {
        notification = { body: event.data?.text() };
    }

    event.waitUntil(showNotification(notification));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const targetUrl = new URL(
        event.notification.data?.url ?? '/',
        self.location.origin,
    ).href;

    event.waitUntil(
        self.clients
            .matchAll({ type: 'window', includeUncontrolled: true })
            .then((clients) => {
                const matchingClient = clients.find((client) =>
                    client.url.startsWith(self.location.origin),
                );

                if (matchingClient) {
                    matchingClient.navigate(targetUrl);

                    return matchingClient.focus();
                }

                return self.clients.openWindow(targetUrl);
            }),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (
        request.method !== 'GET' ||
        url.origin !== self.location.origin ||
        url.pathname.startsWith('/api/')
    ) {
        return;
    }

    if (url.pathname.startsWith('/build/')) {
        event.respondWith(cacheFirst(request));

        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(networkFirstPage(request));
    }
});

async function showNotification(notification) {
    return self.registration.showNotification(notification.title || APP_NAME, {
        body: notification.body || NOTIFICATION_FALLBACK,
        icon: notification.icon || '/apple-touch-icon.png',
        badge: notification.badge || '/favicon.ico',
        tag: notification.tag || 'nossa-casa',
        data: { url: notification.url || '/' },
        actions: [{ action: 'open', title: OPEN_ACTION }],
    });
}

async function cacheFirst(request) {
    const cached = await caches.match(request);

    if (cached) {
        return cached;
    }

    const response = await fetch(request);

    if (response.ok) {
        const cache = await caches.open(STATIC_CACHE);

        await cache.put(request, response.clone());
    }

    return response;
}

async function networkFirstPage(request) {
    try {
        return await fetch(request);
    } catch {
        return new Response(
            `<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>${escapeHtml(OFFLINE_TITLE)}</title><style>body{font-family:system-ui,sans-serif;display:grid;min-height:100vh;place-items:center;margin:0;background:#f8fafc;color:#172554}main{max-width:32rem;padding:2rem;text-align:center}h1{font-size:1.5rem}p{line-height:1.7;color:#475569}</style><main><h1>${escapeHtml(OFFLINE_TITLE)}</h1><p>${escapeHtml(OFFLINE_MESSAGE)}</p></main>`,
            {
                status: 503,
                headers: { 'Content-Type': 'text/html; charset=UTF-8' },
            },
        );
    }
}

function escapeHtml(value) {
    return String(value).replace(
        /[&<>"']/g,
        (character) =>
            ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;',
            })[character],
    );
}
