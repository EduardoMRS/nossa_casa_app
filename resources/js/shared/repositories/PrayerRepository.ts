import type { HttpClient } from '../platform/contracts.ts';

export interface PrayerInput {
    content: string;
    isAnonymous: boolean;
}

export class PrayerRepository {
    private readonly http: HttpClient;

    public constructor(http: HttpClient) {
        this.http = http;
    }

    public create<T>(input: PrayerInput): Promise<T> {
        return this.http.request('prayer-requests', {
            method: 'POST',
            body: {
                content: input.content,
                is_anonymous: input.isAnonymous,
            },
        });
    }
}
