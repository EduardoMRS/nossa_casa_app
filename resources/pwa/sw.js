const CACHE_VERSION = __CACHE_VERSION__;
const CACHE_SCOPE = __CACHE_SCOPE__;
const APP_NAME = __APP_NAME__;
const NOTIFICATION_FALLBACK = __NOTIFICATION_FALLBACK__;
const OPEN_ACTION = __OPEN_ACTION__;
const OFFLINE_TITLE = __OFFLINE_TITLE__;
const OFFLINE_MESSAGE = __OFFLINE_MESSAGE__;
const CACHE_ROOT = 'nossa-casa';
const CACHE_PREFIX = `${CACHE_ROOT}-${CACHE_SCOPE}`;
const STATIC_CACHE = `${CACHE_PREFIX}-static-${CACHE_VERSION}`;
const BIBLE_CACHE = `${CACHE_PREFIX}-bible-${CACHE_VERSION}`;
const PAGE_CACHE = `${CACHE_PREFIX}-pages-${CACHE_VERSION}`;
const ACTIVE_CACHES = [STATIC_CACHE, BIBLE_CACHE, PAGE_CACHE];
const CORE_ASSETS = [
    '/manifest.webmanifest',
    '/branding/icon.svg',
    '/branding/logo',
    '/',
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
                                key.startsWith(`${CACHE_ROOT}-`) &&
                                !ACTIVE_CACHES.includes(key),
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

    if (event.data?.type === 'CHECK_BIBLE_CACHE') {
        event.waitUntil(
            bibleCacheStatus(event.data.versions).then((readyVersions) =>
                event.source?.postMessage({
                    type: 'BIBLE_CACHE_STATUS',
                    readyVersions,
                }),
            ),
        );
    }

    if (event.data?.type === 'CACHE_BIBLES') {
        event.waitUntil(
            cacheBibleVersions(
                event.data.versions,
                event.data.readerUrl,
                event.source,
            ),
        );
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

    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    if (url.pathname.startsWith('/api/bible/')) {
        event.respondWith(cacheFirstBible(request));

        return;
    }

    if (url.pathname.startsWith('/api/')) {
        return;
    }

    if (url.pathname.startsWith('/build/')) {
        event.respondWith(cacheFirst(request));

        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(networkFirstPage(request));

        return;
    }

    if (request.headers.get('X-Inertia') === 'true') {
        event.respondWith(networkFirstPage(request));
    }
});

async function showNotification(notification) {
    return self.registration.showNotification(notification.title || APP_NAME, {
        body: notification.body || NOTIFICATION_FALLBACK,
        icon: notification.icon || '/branding/logo',
        badge: notification.badge || '/branding/icon.svg',
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

async function cacheFirstBible(request) {
    const cache = await caches.open(BIBLE_CACHE);
    const cached = await cache.match(request);

    if (cached) {
        return cached;
    }

    const response = await fetch(request);

    if (
        response.ok &&
        response.headers.get('X-Bible-Offline-Allowed') === '1'
    ) {
        await cache.put(request, response.clone());
    }

    return response;
}

async function bibleCacheStatus(versions) {
    if (!Array.isArray(versions)) {
        return [];
    }

    const cache = await caches.open(BIBLE_CACHE);
    const statuses = await Promise.all(
        versions.map(async (version) => {
            if (!version?.id) {
                return null;
            }

            const marker = await cache.match(bibleMarkerUrl(version.id));

            return marker ? version.id : null;
        }),
    );

    return statuses.filter(Boolean);
}

async function cacheBibleVersions(versions, readerUrl, client) {
    if (!Array.isArray(versions) || !versions.length) {
        return;
    }

    try {
        const cache = await caches.open(BIBLE_CACHE);

        for (let index = 0; index < versions.length; index += 1) {
            const version = versions[index];
            const cachedMarker = version?.id
                ? await cache.match(bibleMarkerUrl(version.id))
                : null;

            if (!cachedMarker) {
                await cacheBibleVersion(
                    version,
                    index,
                    versions.length,
                    client,
                );
            } else {
                client?.postMessage({
                    type: 'BIBLE_CACHE_PROGRESS',
                    completed: index + 1,
                    total: versions.length,
                });
            }
        }

        await cacheReaderPage(readerUrl);
        const readyVersions = await bibleCacheStatus(versions);
        client?.postMessage({ type: 'BIBLE_CACHE_READY', readyVersions });
    } catch {
        client?.postMessage({ type: 'BIBLE_CACHE_ERROR' });
    }
}

async function cacheBibleVersion(version, versionIndex, versionCount, client) {
    if (!version?.id || !version?.url) {
        throw new Error('Invalid Bible cache request.');
    }

    const response = await fetch(version.url, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error('The Bible bundle could not be downloaded.');
    }

    const bundle = await response.json();

    if (!Array.isArray(bundle?.books)) {
        throw new Error('The Bible bundle is invalid.');
    }

    const cache = await caches.open(BIBLE_CACHE);
    const entries = bibleCacheEntries(version.id, bundle.books);
    const batchSize = 40;

    for (let offset = 0; offset < entries.length; offset += batchSize) {
        await Promise.all(
            entries
                .slice(offset, offset + batchSize)
                .map(([url, payload]) => cache.put(url, jsonResponse(payload))),
        );

        const versionProgress = Math.min(
            1,
            (offset + batchSize) / entries.length,
        );
        client?.postMessage({
            type: 'BIBLE_CACHE_PROGRESS',
            completed: versionIndex + versionProgress,
            total: versionCount,
        });
    }

    await cache.put(version.url, jsonResponse(bundle));
    await cache.put(bibleMarkerUrl(version.id), jsonResponse({ ready: true }));
}

function bibleCacheEntries(version, books) {
    const encodedVersion = encodeURIComponent(version);
    const entries = [
        [
            `/api/bible/${encodedVersion}/books`,
            {
                books: books.map((book) => ({
                    slug: book.slug,
                    name: book.name,
                })),
            },
        ],
    ];

    books.forEach((book) => {
        const encodedBook = encodeURIComponent(book.slug);
        const chapters = Array.isArray(book.chapters) ? book.chapters : [];
        entries.push([
            `/api/bible/${encodedVersion}/books/${encodedBook}/chapters`,
            { chapters: chapters.map((chapter) => chapter.chapter) },
        ]);

        chapters.forEach((chapter) => {
            entries.push([
                `/api/bible/${encodedVersion}/books/${encodedBook}/chapters/${chapter.chapter}`,
                { verses: chapter.verses },
            ]);
        });
    });

    return entries;
}

function bibleMarkerUrl(version) {
    return `/api/bible/${encodeURIComponent(version)}/offline-ready`;
}

function jsonResponse(payload) {
    return new Response(JSON.stringify(payload), {
        status: 200,
        headers: { 'Content-Type': 'application/json; charset=UTF-8' },
    });
}

async function cacheReaderPage(readerUrl) {
    if (!readerUrl) {
        return;
    }

    const request = new Request(readerUrl, {
        headers: { Accept: 'text/html' },
        credentials: 'same-origin',
    });
    const response = await fetch(request);

    if (response.ok) {
        const cache = await caches.open(PAGE_CACHE);

        await cache.put(request, response);
    }
}

async function networkFirstPage(request) {
    try {
        const response = await fetch(request);

        if (response.ok) {
            const cache = await caches.open(PAGE_CACHE);

            await cache.put(request, response.clone());
        }

        return response;
    } catch {
        const cache = await caches.open(PAGE_CACHE);
        const cached = await cache.match(request, { ignoreVary: true });

        if (cached) {
            return cached;
        }

        if (request.mode === 'navigate') {
            const cachedHome = await cache.match('/', { ignoreVary: true });

            if (cachedHome) {
                return cachedHome;
            }
        }

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
