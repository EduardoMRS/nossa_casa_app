export interface KeyValueStorage {
    get(key: string): Promise<string | null>;
    set(key: string, value: string): Promise<void>;
    remove(key: string): Promise<void>;
}

export interface NetworkStatus {
    isOnline(): boolean;
    subscribe(listener: (online: boolean) => void): () => void;
}

export interface HttpRequestOptions {
    method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
    body?: unknown;
    headers?: Record<string, string>;
    signal?: AbortSignal;
}

export interface HttpClient {
    request<T>(path: string, options?: HttpRequestOptions): Promise<T>;
}

export interface PlatformServices {
    http: HttpClient;
    storage: KeyValueStorage;
    network: NetworkStatus;
}
