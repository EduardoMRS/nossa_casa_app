import type { HttpClient } from '../../platform/contracts.ts';
import type { CanonicalPayload } from './types.ts';

export class LiveStreamRepository {
    private readonly http: HttpClient;

    public constructor(http: HttpClient) {
        this.http = http;
    }

    public get(id: string): Promise<CanonicalPayload> {
        return this.http.request(
            `content/live-streams/${encodeURIComponent(id)}`,
        );
    }
}
