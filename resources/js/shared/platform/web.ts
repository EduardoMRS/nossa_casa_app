import { FetchHttpClient } from '../http/FetchHttpClient.ts';
import { HydratedStore } from '../stores/HydratedStore.ts';
import type {
    KeyValueStorage,
    NetworkStatus,
    PlatformServices,
} from './contracts.ts';

export class WebStorage implements KeyValueStorage {
    private readonly fallback = new Map<string, string>();
    private readonly storage?: Storage;

    public constructor(storage?: Storage) {
        this.storage = storage;
    }

    public async get(key: string): Promise<string | null> {
        return this.storage?.getItem(key) ?? this.fallback.get(key) ?? null;
    }

    public async set(key: string, value: string): Promise<void> {
        if (this.storage) {
            this.storage.setItem(key, value);

            return;
        }

        this.fallback.set(key, value);
    }

    public async remove(key: string): Promise<void> {
        if (this.storage) {
            this.storage.removeItem(key);

            return;
        }

        this.fallback.delete(key);
    }
}

export class WebNetworkStatus implements NetworkStatus {
    public isOnline(): boolean {
        return globalThis.navigator?.onLine ?? true;
    }

    public subscribe(listener: (online: boolean) => void): () => void {
        if (typeof window === 'undefined') {
            return () => undefined;
        }

        const online = (): void => listener(true);
        const offline = (): void => listener(false);
        window.addEventListener('online', online);
        window.addEventListener('offline', offline);

        return (): void => {
            window.removeEventListener('online', online);
            window.removeEventListener('offline', offline);
        };
    }
}

export function createWebPlatform(apiBaseUrl = '/api'): PlatformServices {
    return {
        http: new FetchHttpClient({
            apiBaseUrl,
            defaultHeaders: async () => {
                const csrfToken = globalThis.document
                    ?.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
                    ?.getAttribute('content');
                const headers: Record<string, string> = {};

                if (csrfToken) {
                    headers['X-CSRF-TOKEN'] = csrfToken;
                }

                return headers;
            },
        }),
        storage: new WebStorage(globalThis.localStorage),
        network: new WebNetworkStatus(),
    };
}

export function createWebHydratedStore<T>(
    key: string,
    ttlMilliseconds: number,
): HydratedStore<T> {
    const platform = createWebPlatform();

    return new HydratedStore<T>(
        platform.storage,
        platform.network,
        'public:' + key,
        ttlMilliseconds,
    );
}

export function replaceWebQuery(query: URLSearchParams): void {
    if (typeof window === 'undefined') {
        return;
    }

    const url = new URL(window.location.href);
    url.search = query.toString();
    window.history.replaceState(window.history.state, '', url);
}
