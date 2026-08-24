import type { HttpClient } from '../../platform/contracts.ts';
import type { CanonicalPayload } from './types.ts';

export class LibraryRepository {
    private readonly http: HttpClient;

    public constructor(http: HttpClient) {
        this.http = http;
    }

    public get(): Promise<CanonicalPayload> {
        return this.http.request('content/library');
    }

    public bible(): Promise<CanonicalPayload> {
        return this.http.request('content/library/bible');
    }
}
