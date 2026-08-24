import type { HttpClient, HttpRequestOptions } from '../platform/contracts.ts';

export interface FetchHttpClientOptions {
    apiBaseUrl: string;
    accessToken?: () => Promise<string | null>;
    churchId?: () => Promise<string | null>;
    defaultHeaders?: () => Promise<Record<string, string>>;
    fetcher?: typeof fetch;
}

export class ApiError extends Error {
    public readonly status: number;
    public readonly code: string;
    public readonly details?: unknown;

    public constructor(
        status: number,
        code: string,
        message: string,
        details?: unknown,
    ) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.code = code;
        this.details = details;
    }
}

export class FetchHttpClient implements HttpClient {
    private readonly baseUrl: URL;
    private readonly fetcher: typeof fetch;
    private readonly options: FetchHttpClientOptions;

    public constructor(options: FetchHttpClientOptions) {
        this.options = options;
        const origin = globalThis.location?.origin ?? 'http://localhost';
        this.baseUrl = new URL(
            `${options.apiBaseUrl.replace(/\/$/, '')}/`,
            origin,
        );
        this.fetcher = options.fetcher ?? fetch;
    }

    public async request<T>(
        path: string,
        options: HttpRequestOptions = {},
    ): Promise<T> {
        const url = this.resolvePath(path);
        const headers = new Headers(options.headers);
        headers.set('Accept', 'application/json');
        headers.set('X-Nossa-Casa-API-Version', '1');

        const defaultHeaders = await this.options.defaultHeaders?.();

        Object.entries(defaultHeaders ?? {}).forEach(([name, value]) => {
            headers.set(name, value);
        });

        const accessToken = await this.options.accessToken?.();
        const churchId = await this.options.churchId?.();

        if (accessToken) {
            headers.set('Authorization', `Bearer ${accessToken}`);
        }

        if (churchId) {
            headers.set('X-Church-ID', churchId);
        }

        if (options.body !== undefined) {
            headers.set('Content-Type', 'application/json');
        }

        const response = await this.fetcher(url, {
            method: options.method ?? 'GET',
            headers,
            body:
                options.body === undefined
                    ? undefined
                    : JSON.stringify(options.body),
            credentials: accessToken ? 'omit' : 'same-origin',
            redirect: 'error',
            signal: options.signal,
        });
        const payload = await this.readPayload(response);

        if (!response.ok) {
            const error = this.asRecord(payload);

            throw new ApiError(
                response.status,
                typeof error.code === 'string' ? error.code : 'HTTP_ERROR',
                typeof error.message === 'string'
                    ? error.message
                    : response.statusText,
                error.errors,
            );
        }

        return payload as T;
    }

    private resolvePath(path: string): URL {
        const url = new URL(path.replace(/^\//, ''), this.baseUrl);

        if (
            url.origin !== this.baseUrl.origin ||
            !url.pathname.startsWith(this.baseUrl.pathname)
        ) {
            throw new ApiError(
                0,
                'CROSS_ORIGIN_REQUEST_BLOCKED',
                'Cross-origin API request blocked.',
            );
        }

        return url;
    }

    private async readPayload(response: Response): Promise<unknown> {
        if (response.status === 204) {
            return null;
        }

        const contentType = response.headers.get('content-type') ?? '';

        return contentType.includes('application/json')
            ? response.json()
            : response.text();
    }

    private asRecord(value: unknown): Record<string, unknown> {
        return typeof value === 'object' && value !== null
            ? (value as Record<string, unknown>)
            : {};
    }
}
