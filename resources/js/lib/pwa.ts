import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';

interface BeforeInstallPromptEvent extends Event {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
}

const installPrompt = ref<BeforeInstallPromptEvent | null>(null);
const installed = ref(false);
const notificationPermission = ref<NotificationPermission>(
    typeof Notification === 'undefined' ? 'default' : Notification.permission,
);
const pwaChurchStorageKey = 'ncapp.pwa_church_id';
const pwaChurchExpiresAtKey = 'ncapp.pwa_church_expires_at';
const pwaChurchDefaultDays = 15;

export const isStandalonePwa = (): boolean =>
    window.matchMedia('(display-mode: standalone)').matches ||
    (navigator as Navigator & { standalone?: boolean }).standalone === true;

export const isPortalOrigin = (): boolean => {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return true;
    }

    const portalHost = document
        .querySelector('meta[name="portal-host"]')
        ?.getAttribute('content');

    return !portalHost || window.location.hostname === portalHost;
};

export const currentPwaChurchId = (): string | null => {
    const churchId = window.localStorage.getItem(pwaChurchStorageKey);
    const expiresAt = Number(
        window.localStorage.getItem(pwaChurchExpiresAtKey),
    );

    if (!churchId) {
        return null;
    }

    if (Number.isFinite(expiresAt) && expiresAt <= Date.now()) {
        clearPwaChurchId();

        return null;
    }

    if (!Number.isFinite(expiresAt)) {
        persistPwaChurchId(churchId);
    }

    return churchId;
};

export const pwaChurchDaysRemaining = (): number | null => {
    currentPwaChurchId();
    const expiresAt = Number(
        window.localStorage.getItem(pwaChurchExpiresAtKey),
    );

    return Number.isFinite(expiresAt)
        ? Math.max(0, Math.ceil((expiresAt - Date.now()) / 86400000))
        : null;
};

export const persistPwaChurchId = (
    churchId: string,
    days = pwaChurchDefaultDays,
): void => {
    const maxAge = Math.max(1, days) * 86400;
    window.localStorage.setItem(pwaChurchStorageKey, churchId);
    window.localStorage.setItem(
        pwaChurchExpiresAtKey,
        String(Date.now() + maxAge * 1000),
    );
    document.cookie = `ncapp_pwa_church_id=${encodeURIComponent(churchId)}; Path=/; SameSite=Lax; Max-Age=${maxAge}`;
};

export const clearPwaChurchId = (): void => {
    window.localStorage.removeItem(pwaChurchStorageKey);
    window.localStorage.removeItem(pwaChurchExpiresAtKey);
    document.cookie = 'ncapp_pwa_church_id=; Path=/; SameSite=Lax; Max-Age=0';
};

export const navigatePwaUrl = (value: string): void => {
    const target = new URL(value, window.location.href);

    if (isStandalonePwa() && target.origin !== window.location.origin) {
        window.open(target.toString(), '_blank', 'noopener,noreferrer');

        return;
    }

    window.location.assign(target.toString());
};

export const navigatePwaChurchUrl = (value: string, churchId: string): void => {
    const target = new URL(value, window.location.href);

    if (!isStandalonePwa() || target.origin === window.location.origin) {
        navigatePwaUrl(value);

        return;
    }

    persistPwaChurchId(churchId);
    const portalUrl = new URL(window.location.href);
    const locale = portalUrl.pathname.split('/').filter(Boolean)[0];

    portalUrl.pathname =
        target.pathname === '/' ? `/${locale || ''}` : target.pathname;
    portalUrl.search = target.search;
    portalUrl.searchParams.set('church_id', churchId);
    portalUrl.searchParams.set('pwa', '1');
    portalUrl.hash = target.hash;
    window.location.assign(portalUrl.toString());
};

const handleStandaloneExternalNavigation = (event: MouseEvent): void => {
    if (
        event.defaultPrevented ||
        event.button !== 0 ||
        event.metaKey ||
        event.ctrlKey ||
        event.shiftKey ||
        event.altKey ||
        !isStandalonePwa()
    ) {
        return;
    }

    const target = event.target;

    if (!(target instanceof Element)) {
        return;
    }

    const anchor = target.closest<HTMLAnchorElement>('a[href]');

    if (
        !anchor ||
        anchor.target === '_blank' ||
        anchor.hasAttribute('download')
    ) {
        return;
    }

    if (anchor.hasAttribute('data-pwa-clear-church')) {
        clearPwaChurchId();

        return;
    }

    const url = new URL(anchor.href, window.location.href);

    if (url.origin === window.location.origin) {
        return;
    }

    event.preventDefault();
    const churchId = anchor.dataset.churchId;

    if (churchId) {
        navigatePwaChurchUrl(url.toString(), churchId);
    } else {
        navigatePwaUrl(url.toString());
    }
};

export const initializePwa = (): void => {
    if (typeof window === 'undefined') {
        return;
    }

    if (!isPortalOrigin()) {
        return;
    }

    window.addEventListener('beforeinstallprompt', (event) => {
        installPrompt.value = event as BeforeInstallPromptEvent;
    });
    window.addEventListener('appinstalled', () => {
        installed.value = true;
        installPrompt.value = null;
    });

    if ('serviceWorker' in navigator && window.isSecureContext) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js', { scope: '/' });
        });
    }

    window.addEventListener('click', handleStandaloneExternalNavigation, true);

    router.on('before', (event) => {
        if (!isStandalonePwa()) {
            return;
        }

        const churchId = currentPwaChurchId();

        if (!churchId) {
            return;
        }

        const visit = event.detail.visit as typeof event.detail.visit & {
            headers?: Record<string, string>;
        };
        visit.headers = {
            ...visit.headers,
            'X-Church-ID': churchId,
            'X-PWA-APP': '1',
        };
    });

    axios.interceptors.request.use((config) => {
        if (isStandalonePwa()) {
            const churchId = currentPwaChurchId();

            if (churchId) {
                config.headers.set('X-Church-ID', churchId);
            }

            config.headers.set('X-PWA-APP', '1');
        }

        return config;
    });

    const originalFetch = window.fetch.bind(window);
    window.fetch = (input, init = {}) => {
        const requestUrl = new URL(
            input instanceof Request ? input.url : input,
            window.location.href,
        );

        if (
            !isStandalonePwa() ||
            requestUrl.origin !== window.location.origin
        ) {
            return originalFetch(input, init);
        }

        const headers = new Headers(
            init.headers ??
                (input instanceof Request ? input.headers : undefined),
        );
        const churchId = currentPwaChurchId();

        headers.set('X-PWA-APP', '1');

        if (churchId) {
            headers.set('X-Church-ID', churchId);
        }

        return originalFetch(input, { ...init, headers });
    };
};

export const usePwa = () => {
    const canInstall = computed(
        () => installPrompt.value !== null && !installed.value,
    );

    const install = async (): Promise<boolean> => {
        const deferredPrompt = installPrompt.value;

        if (!deferredPrompt) {
            return false;
        }

        try {
            await deferredPrompt.prompt();
            const choice = await deferredPrompt.userChoice;

            return choice.outcome === 'accepted';
        } catch {
            return false;
        } finally {
            installPrompt.value = null;
        }
    };

    const subscribeToPush = async (publicKey: string): Promise<boolean> => {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            return false;
        }

        const permission = await Notification.requestPermission();
        notificationPermission.value = permission;

        if (permission !== 'granted') {
            return false;
        }

        const registration = await navigator.serviceWorker.ready;
        const subscription =
            (await registration.pushManager.getSubscription()) ??
            (await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToArrayBuffer(publicKey),
            }));

        await axios.post('/api/push-subscriptions', {
            ...subscription.toJSON(),
            content_encoding: 'aes128gcm',
        });

        return true;
    };

    return {
        canInstall,
        install,
        notificationPermission,
        subscribeToPush,
    };
};

const urlBase64ToArrayBuffer = (value: string): ArrayBuffer => {
    const padding = '='.repeat((4 - (value.length % 4)) % 4);
    const base64 = (value + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = window.atob(base64);

    return Uint8Array.from([...raw].map((character) => character.charCodeAt(0)))
        .buffer;
};
