export type ServerDiscovery = {
    protocol: 'nossa-casa';
    protocol_version: number;
    instance_id: string;
    instance_name: string;
    api_base_url: string;
    web_base_url: string;
    auth_driver: 'sanctum';
    capabilities: string[];
    api_version: number;
    minimum_app_version: string;
    privacy_url: string | null;
    terms_url: string | null;
};

export type ServerConnection = ServerDiscovery & {
    discovery_url: string;
    api_origin: string;
};

export type ServerInputOptions = {
    allowInsecure?: boolean;
};

export type ServerDiscoveryOptions = ServerInputOptions & {
    fetcher?: typeof fetch;
};

export type ServerRequestOptions = {
    accessToken?: string;
    fetcher?: typeof fetch;
    init?: RequestInit;
};

export type KeyValueStorage = {
    getItem(key: string): Promise<string | null> | string | null;
    setItem(key: string, value: string): Promise<void> | void;
    removeItem(key: string): Promise<void> | void;
};

export class ServerDiscoveryError extends Error {
    readonly code: string;

    constructor(code: string) {
        super(code);
        this.name = 'ServerDiscoveryError';
        this.code = code;
    }
}

const ulidPattern = /^[0-9A-HJKMNP-TV-Z]{26}$/i;

export function normalizeServerInput(
    input: string,
    options: ServerInputOptions = {},
): URL {
    const extractedInput = extractServerInput(input);
    const candidate = /^[a-z][a-z\d+.-]*:\/\//i.test(extractedInput)
        ? extractedInput
        : `https://${extractedInput}`;
    let url: URL;

    try {
        url = new URL(candidate);
    } catch {
        throw new ServerDiscoveryError('INVALID_SERVER_URL');
    }

    if (url.username || url.password || url.search || url.hash) {
        throw new ServerDiscoveryError('UNSAFE_SERVER_URL');
    }

    if (url.pathname !== '/' && url.pathname !== '') {
        throw new ServerDiscoveryError('SERVER_ROOT_REQUIRED');
    }

    if (
        url.protocol !== 'https:' &&
        !(options.allowInsecure && url.protocol === 'http:')
    ) {
        throw new ServerDiscoveryError('HTTPS_REQUIRED');
    }

    return new URL(url.origin);
}

export function discoveryUrlFor(
    input: string,
    options: ServerInputOptions = {},
): URL {
    return new URL(
        '/.well-known/nossa-casa.json',
        normalizeServerInput(input, options),
    );
}

export async function discoverServer(
    input: string,
    options: ServerDiscoveryOptions = {},
): Promise<ServerConnection> {
    const discoveryUrl = discoveryUrlFor(input, options);
    const response = await (options.fetcher ?? fetch)(discoveryUrl, {
        method: 'GET',
        headers: { Accept: 'application/json' },
        credentials: 'omit',
        redirect: 'error',
    });

    if (!response.ok) {
        throw new ServerDiscoveryError('DISCOVERY_REQUEST_FAILED');
    }

    return validateServerDiscovery(
        await response.json(),
        discoveryUrl,
        options,
    );
}

export function validateServerDiscovery(
    payload: unknown,
    discoveryUrl: URL,
    options: ServerInputOptions = {},
): ServerConnection {
    if (!isRecord(payload)) {
        throw new ServerDiscoveryError('INVALID_DISCOVERY');
    }

    const requiredStrings = [
        'instance_id',
        'instance_name',
        'api_base_url',
        'web_base_url',
        'minimum_app_version',
    ] as const;

    if (requiredStrings.some((field) => typeof payload[field] !== 'string')) {
        throw new ServerDiscoveryError('INVALID_DISCOVERY');
    }

    if (
        payload.protocol !== 'nossa-casa' ||
        payload.auth_driver !== 'sanctum' ||
        !Number.isInteger(payload.protocol_version) ||
        !Number.isInteger(payload.api_version) ||
        !Array.isArray(payload.capabilities) ||
        !payload.capabilities.every(
            (capability) => typeof capability === 'string',
        ) ||
        !ulidPattern.test(payload.instance_id)
    ) {
        throw new ServerDiscoveryError('INVALID_DISCOVERY');
    }

    const apiUrl = validateCanonicalUrl(payload.api_base_url, options);
    const webUrl = validateCanonicalUrl(payload.web_base_url, options);

    if (apiUrl.origin !== discoveryUrl.origin) {
        throw new ServerDiscoveryError('DISCOVERY_HOST_MISMATCH');
    }

    if (!apiUrl.pathname.endsWith('/api')) {
        throw new ServerDiscoveryError('INVALID_API_BASE_URL');
    }

    return {
        protocol: 'nossa-casa',
        protocol_version: payload.protocol_version,
        instance_id: payload.instance_id,
        instance_name: payload.instance_name,
        api_base_url: apiUrl.toString().replace(/\/$/, ''),
        web_base_url: webUrl.toString().replace(/\/$/, ''),
        auth_driver: 'sanctum',
        capabilities: [...payload.capabilities],
        api_version: payload.api_version,
        minimum_app_version: payload.minimum_app_version,
        privacy_url: nullableUrl(payload.privacy_url, options),
        terms_url: nullableUrl(payload.terms_url, options),
        discovery_url: discoveryUrl.toString(),
        api_origin: apiUrl.origin,
    };
}

export function assertCredentialTarget(
    server: ServerConnection,
    requestUrl: string | URL,
): void {
    const target = new URL(requestUrl, server.api_base_url);
    const apiBase = new URL(server.api_base_url);
    const basePath = apiBase.pathname.replace(/\/$/, '');

    if (
        target.origin !== server.api_origin ||
        (target.pathname !== basePath &&
            !target.pathname.startsWith(`${basePath}/`))
    ) {
        throw new ServerDiscoveryError('CREDENTIAL_HOST_MISMATCH');
    }
}

export async function requestServer(
    server: ServerConnection,
    path: string,
    options: ServerRequestOptions = {},
): Promise<Response> {
    const target = new URL(path.replace(/^\//, ''), `${server.api_base_url}/`);

    assertCredentialTarget(server, target);

    const headers = new Headers(options.init?.headers);
    headers.set('Accept', 'application/json');
    headers.set('X-Nossa-Casa-API-Version', String(server.api_version));

    if (options.accessToken) {
        headers.set('Authorization', `Bearer ${options.accessToken}`);
    }

    return (options.fetcher ?? fetch)(target, {
        ...options.init,
        headers,
        redirect: 'error',
    });
}

export function serverStorageKey(
    instanceId: string,
    namespace: 'session' | 'selected_church' | 'cache' | 'devices',
): string {
    if (!ulidPattern.test(instanceId)) {
        throw new ServerDiscoveryError('INVALID_INSTANCE_ID');
    }

    return `servers/${instanceId}/${namespace}`;
}

export class InstanceScopedStorage {
    readonly instanceId: string;
    readonly storage: KeyValueStorage;

    constructor(instanceId: string, storage: KeyValueStorage) {
        serverStorageKey(instanceId, 'cache');
        this.instanceId = instanceId;
        this.storage = storage;
    }

    async get<T>(
        namespace: 'session' | 'selected_church' | 'cache' | 'devices',
    ): Promise<T | null> {
        const value = await this.storage.getItem(
            serverStorageKey(this.instanceId, namespace),
        );

        return value === null ? null : (JSON.parse(value) as T);
    }

    async set(
        namespace: 'session' | 'selected_church' | 'cache' | 'devices',
        value: unknown,
    ): Promise<void> {
        await this.storage.setItem(
            serverStorageKey(this.instanceId, namespace),
            JSON.stringify(value),
        );
    }

    async remove(
        namespace: 'session' | 'selected_church' | 'cache' | 'devices',
    ): Promise<void> {
        await this.storage.removeItem(
            serverStorageKey(this.instanceId, namespace),
        );
    }
}

function extractServerInput(input: string): string {
    const trimmedInput = input.trim();

    if (!trimmedInput.startsWith('{')) {
        return trimmedInput;
    }

    try {
        const payload: unknown = JSON.parse(trimmedInput);

        if (isRecord(payload)) {
            const value =
                payload.server_url ?? payload.web_base_url ?? payload.url;

            if (typeof value === 'string') {
                return value.trim();
            }
        }
    } catch {
        throw new ServerDiscoveryError('INVALID_QR_PAYLOAD');
    }

    throw new ServerDiscoveryError('INVALID_QR_PAYLOAD');
}

function validateCanonicalUrl(value: string, options: ServerInputOptions): URL {
    let url: URL;

    try {
        url = new URL(value);
    } catch {
        throw new ServerDiscoveryError('INVALID_DISCOVERY_URL');
    }

    if (
        url.username ||
        url.password ||
        url.search ||
        url.hash ||
        (url.protocol !== 'https:' &&
            !(options.allowInsecure && url.protocol === 'http:'))
    ) {
        throw new ServerDiscoveryError('INVALID_DISCOVERY_URL');
    }

    return url;
}

function nullableUrl(
    value: unknown,
    options: ServerInputOptions,
): string | null {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    if (typeof value !== 'string') {
        throw new ServerDiscoveryError('INVALID_DISCOVERY_URL');
    }

    return validateCanonicalUrl(value, options).toString();
}

function isRecord(value: unknown): value is Record<string, any> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}
