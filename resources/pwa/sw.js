const CACHE_VERSION = __CACHE_VERSION__;
const CACHE_SCOPE = __CACHE_SCOPE__;
const APP_NAME = __APP_NAME__;
const NOTIFICATION_FALLBACK = __NOTIFICATION_FALLBACK__;
const OPEN_ACTION = __OPEN_ACTION__;
const OFFLINE_TITLE = __OFFLINE_TITLE__;
const OFFLINE_MESSAGE = __OFFLINE_MESSAGE__;
const CACHE_ROOT = 'nossa-casa';
const CACHE_SCHEMA_VERSION = '3';
const CACHE_PREFIX = `${CACHE_ROOT}-v${CACHE_SCHEMA_VERSION}-${CACHE_SCOPE}`;
const STATIC_CACHE = `${CACHE_PREFIX}-static-${CACHE_VERSION}`;
const BIBLE_CACHE = `${CACHE_PREFIX}-bible-${CACHE_VERSION}`;
const PAGE_CACHE = `${CACHE_PREFIX}-pages-${CACHE_VERSION}`;
const INERTIA_CACHE = `${CACHE_PREFIX}-inertia-${CACHE_VERSION}`;
const ACTIVE_CACHES = [STATIC_CACHE, BIBLE_CACHE, PAGE_CACHE, INERTIA_CACHE];
const CORE_ASSETS = [
    '/manifest.webmanifest',
    '/branding/icon.svg',
    '/branding/logo',
    '/favicon.ico',
    '/favicon.svg',
    '/',
];

async function cleanResponse(response) {
    if (!response) {
        return response;
    }

    try {
        const blob = await response.clone().blob();
        const headers = new Headers(response.headers);

        return new Response(blob, {
            status: response.status,
            statusText: response.statusText,
            headers,
        });
    } catch {
        return response;
    }
}

async function broadcastMessage(payload, preferredClient = null) {
    if (preferredClient && typeof preferredClient.postMessage === 'function') {
        try {
            preferredClient.postMessage(payload);
        } catch {
            // Ignore failure posting to preferred client
        }
    }

    try {
        const clients = await self.clients.matchAll({
            type: 'window',
            includeUncontrolled: true,
        });

        for (const client of clients) {
            if (client !== preferredClient) {
                try {
                    client.postMessage(payload);
                } catch {
                    // Ignore client postMessage failure
                }
            }
        }
    } catch {
        // Ignore matchAll failure
    }
}

self.addEventListener('install', (event) => {
    event.waitUntil(precacheCoreAssets().then(() => self.skipWaiting()));
});

async function precacheCoreAssets() {
    const staticCache = await caches.open(STATIC_CACHE);
    const pageCache = await caches.open(PAGE_CACHE);
    const inertiaCache = await caches.open(INERTIA_CACHE);

    await Promise.allSettled(
        CORE_ASSETS.map(async (asset) => {
            try {
                const response = await fetch(asset);
                if (response.ok) {
                    const cleaned = await cleanResponse(response);
                    await staticCache.put(asset, cleaned);
                }
            } catch {
                // Ignore failure to precache single static asset
            }
        }),
    );

    const initialPages = [
        '/?pwa=1',
        '/',
        '/pt?pwa=1',
        '/pt',
        '/en?pwa=1',
        '/en',
        '/library/bible',
        '/biblioteca/biblia',
        '/pt/library/bible',
        '/pt/biblioteca/biblia',
        '/en/library/bible',
        '/en/biblioteca/biblia',
    ];
    await Promise.allSettled(
        initialPages.map(async (url) => {
            try {
                const response = await fetch(url, {
                    credentials: 'same-origin',
                });
                if (response.ok && isHtmlPageResponse(response)) {
                    const cleaned = await cleanResponse(response);
                    await pageCache.put(url, cleaned.clone());
                    if (response.url && response.url !== url) {
                        await pageCache.put(response.url, cleaned.clone());
                    }
                }

                const inertiaResponse = await fetch(url, {
                    headers: {
                        'X-Inertia': 'true',
                        'X-PWA-APP': '1',
                        Accept: 'text/html, application/xhtml+xml',
                    },
                    credentials: 'same-origin',
                });
                if (isInertiaResponse(inertiaResponse)) {
                    const cleanedInertia = await cleanResponse(inertiaResponse);
                    await inertiaCache.put(url, cleanedInertia.clone());
                    if (inertiaResponse.url && inertiaResponse.url !== url) {
                        await inertiaCache.put(
                            inertiaResponse.url,
                            cleanedInertia.clone(),
                        );
                    }
                }
            } catch {
                // Ignore if network is unavailable during install
            }
        }),
    );
}

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
                broadcastMessage(
                    {
                        type: 'BIBLE_CACHE_STATUS',
                        readyVersions,
                    },
                    event.source,
                ),
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
                const sameOriginTarget =
                    new URL(targetUrl).origin === self.location.origin;
                const matchingClient = sameOriginTarget
                    ? clients.find((client) =>
                          client.url.startsWith(self.location.origin),
                      )
                    : null;

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

    if (request.headers.get('X-Inertia') === 'true') {
        event.respondWith(networkFirstInertia(request));

        return;
    }

    if (
        url.pathname.startsWith('/build/') ||
        url.pathname.startsWith('/branding/') ||
        url.pathname === '/manifest.webmanifest' ||
        url.pathname === '/favicon.ico' ||
        url.pathname === '/favicon.svg' ||
        url.pathname === '/apple-touch-icon.png'
    ) {
        event.respondWith(cacheFirst(request));

        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(networkFirstPage(request));

        return;
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
        return await cleanResponse(cached);
    }

    try {
        const response = await fetch(request);

        if (response.ok) {
            const cache = await caches.open(STATIC_CACHE);
            const cleaned = await cleanResponse(response);

            await cache.put(request, cleaned.clone());
            return cleaned;
        }

        return await cleanResponse(response);
    } catch {
        return new Response('', { status: 408, statusText: 'Request Timeout' });
    }
}

async function cacheFirstBible(request) {
    const cache = await caches.open(BIBLE_CACHE);
    const cached = await cache.match(request);

    if (cached) {
        return await cleanResponse(cached);
    }

    try {
        const response = await fetch(request);

        if (
            response.ok &&
            response.headers.get('X-Bible-Offline-Allowed') === '1'
        ) {
            const cleaned = await cleanResponse(response);
            await cache.put(request, cleaned.clone());
            return cleaned;
        }

        return await cleanResponse(response);
    } catch {
        const url = new URL(request.url);
        const decodedPath = decodeURIComponent(url.pathname);
        const matched = await cache.match(decodedPath);
        if (matched) {
            return await cleanResponse(matched);
        }

        return new Response(JSON.stringify({ error: 'Offline' }), {
            status: 503,
            headers: { 'Content-Type': 'application/json; charset=UTF-8' },
        });
    }
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
                await broadcastMessage(
                    {
                        type: 'BIBLE_CACHE_PROGRESS',
                        completed: index + 1,
                        total: versions.length,
                    },
                    client,
                );
            }
        }

        await cacheReaderPage(readerUrl);
        const readyVersions = await bibleCacheStatus(versions);
        await broadcastMessage(
            { type: 'BIBLE_CACHE_READY', readyVersions },
            client,
        );
    } catch {
        await broadcastMessage({ type: 'BIBLE_CACHE_ERROR' }, client);
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
        await broadcastMessage(
            {
                type: 'BIBLE_CACHE_PROGRESS',
                completed: versionIndex + versionProgress,
                total: versionCount,
            },
            client,
        );
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

    const pathWithoutLocale = readerUrl.replace(/^\/(?:pt|en)/, '');
    const urlsToCache = new Set([
        readerUrl,
        pathWithoutLocale,
        `/pt${pathWithoutLocale}`,
        `/en${pathWithoutLocale}`,
        '/library/bible',
        '/biblioteca/biblia',
        '/pt/library/bible',
        '/pt/biblioteca/biblia',
    ]);

    const pageCache = await caches.open(PAGE_CACHE);
    const inertiaCache = await caches.open(INERTIA_CACHE);

    for (const url of urlsToCache) {
        try {
            const htmlRequest = new Request(url, {
                headers: { Accept: 'text/html' },
                credentials: 'same-origin',
            });
            const htmlResponse = await fetch(htmlRequest);

            if (isHtmlPageResponse(htmlResponse)) {
                const cleanedHtml = await cleanResponse(htmlResponse);
                await pageCache.put(htmlRequest, cleanedHtml.clone());
                if (htmlResponse.url && htmlResponse.url !== htmlRequest.url) {
                    await pageCache.put(htmlResponse.url, cleanedHtml.clone());
                }
            }

            const inertiaRequest = new Request(url, {
                headers: {
                    'X-Inertia': 'true',
                    'X-PWA-APP': '1',
                    Accept: 'text/html, application/xhtml+xml',
                },
                credentials: 'same-origin',
            });
            const inertiaResponse = await fetch(inertiaRequest);

            if (isInertiaResponse(inertiaResponse)) {
                const cleanedInertia = await cleanResponse(inertiaResponse);
                await inertiaCache.put(inertiaRequest, cleanedInertia.clone());
                if (inertiaResponse.url && inertiaResponse.url !== inertiaRequest.url) {
                    await inertiaCache.put(inertiaResponse.url, cleanedInertia.clone());
                }
            }
        } catch {
            // Silently continue if offline
        }
    }
}

function isHtmlPageResponse(response) {
    const contentType =
        response?.headers.get('Content-Type')?.toLowerCase() ?? '';

    return (
        response?.ok &&
        contentType.includes('text/html') &&
        response.headers.get('X-Inertia') !== 'true'
    );
}

function isInertiaResponse(response) {
    return response?.ok && response.headers.get('X-Inertia') === 'true';
}

async function cachedHtmlPage(cache, request, options = undefined) {
    const cached = await cache.match(request, options);

    if (!cached) {
        return null;
    }

    if (isHtmlPageResponse(cached)) {
        return await cleanResponse(cached);
    }

    await cache.delete(request);

    return null;
}

async function networkFirstInertia(request) {
    try {
        const response = await fetch(withPwaHeaders(request));

        if (isInertiaResponse(response)) {
            const cache = await caches.open(INERTIA_CACHE);
            const cleaned = await cleanResponse(response);

            await cache.put(request, cleaned.clone());

            if (response.url && response.url !== request.url) {
                await cache.put(response.url, cleaned.clone());
            }

            return cleaned;
        }

        return await cleanResponse(response);
    } catch {
        const cache = await caches.open(INERTIA_CACHE);
        let cached = await cache.match(request);

        if (!cached) {
            cached = await cache.match(request, { ignoreSearch: true });
        }

        if (cached) {
            return await cleanResponse(cached);
        }

        const url = new URL(request.url);
        const path = url.pathname;
        const strippedPath = path.replace(/^\/(?:pt|en)/, '');
        const fallbackUrls = [
            path,
            strippedPath,
            `/pt${strippedPath}`,
            `/en${strippedPath}`,
            '/library/bible',
            '/biblioteca/biblia',
            '/pt/library/bible',
            '/pt/biblioteca/biblia',
            '/?pwa=1',
            '/',
            '/pt?pwa=1',
            '/pt',
        ];

        for (const fallbackUrl of fallbackUrls) {
            const matched = await cache.match(fallbackUrl);
            if (matched) {
                return await cleanResponse(matched);
            }
        }

        // Fall back to the page cache for any cached HTML shell
        const pageCache = await caches.open(PAGE_CACHE);
        const pageFallbackUrls = [
            '/?pwa=1',
            '/',
            '/pt?pwa=1',
            '/pt',
            '/en?pwa=1',
            '/en',
        ];
        for (const fallbackUrl of pageFallbackUrls) {
            const htmlFallback = await cachedHtmlPage(pageCache, fallbackUrl);
            if (htmlFallback) {
                return await cleanResponse(htmlFallback);
            }
        }

        return offlineFallbackResponse();
    }
}

async function networkFirstPage(request) {
    try {
        const response = await fetch(withPwaHeaders(request));

        if (isHtmlPageResponse(response)) {
            const cache = await caches.open(PAGE_CACHE);
            const cleaned = await cleanResponse(response);

            await cache.put(request, cleaned.clone());

            if (response.url && response.url !== request.url) {
                await cache.put(response.url, cleaned.clone());
            }

            return cleaned;
        }

        return await cleanResponse(response);
    } catch {
        const pageCache = await caches.open(PAGE_CACHE);
        let cached = await cachedHtmlPage(pageCache, request);

        if (!cached) {
            cached = await cachedHtmlPage(pageCache, request, {
                ignoreSearch: true,
            });
        }

        if (cached) {
            return await cleanResponse(cached);
        }

        const fallbackUrls = [
            '/?pwa=1',
            '/',
            '/pt?pwa=1',
            '/pt',
            '/en?pwa=1',
            '/en',
            '/library/bible',
            '/pt/library/bible',
            '/biblioteca/biblia',
            '/pt/biblioteca/biblia',
        ];
        for (const fallbackUrl of fallbackUrls) {
            cached = await cachedHtmlPage(pageCache, fallbackUrl);
            if (cached) {
                return await cleanResponse(cached);
            }
        }

        const keys = await pageCache.keys();
        for (const key of keys) {
            cached = await cachedHtmlPage(pageCache, key);
            if (cached) {
                return await cleanResponse(cached);
            }
        }

        const staticCache = await caches.open(STATIC_CACHE);
        cached = await cachedHtmlPage(staticCache, '/');
        if (cached) {
            return await cleanResponse(cached);
        }

        return offlineFallbackResponse();
    }
}

function offlineFallbackResponse() {
    return new Response(
        `<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>${escapeHtml(OFFLINE_TITLE)}</title><style>body{font-family:system-ui,sans-serif;display:grid;min-height:100vh;place-items:center;margin:0;background:#f8fafc;color:#172554}main{max-width:32rem;padding:2rem;text-align:center}h1{font-size:1.5rem}p{line-height:1.7;color:#475569}</style><main><h1>${escapeHtml(OFFLINE_TITLE)}</h1><p>${escapeHtml(OFFLINE_MESSAGE)}</p></main>`,
        {
            status: 503,
            headers: { 'Content-Type': 'text/html; charset=UTF-8' },
        },
    );
}

function withPwaHeaders(request) {
    if (request.mode === 'navigate') {
        return request;
    }

    try {
        const headers = new Headers(request.headers);
        headers.set('X-PWA-APP', '1');

        return new Request(request, { headers });
    } catch {
        return request;
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
