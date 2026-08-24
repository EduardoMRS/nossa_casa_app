import type { HttpClient } from '../../platform/contracts.ts';
import type { CanonicalPayload } from './types.ts';

export class PortalRepository {
    private readonly http: HttpClient;

    public constructor(http: HttpClient) {
        this.http = http;
    }

    public get(): Promise<CanonicalPayload> {
        return this.http.request('portal');
    }
}
