import type { HttpClient } from '../../platform/contracts.ts';
import { appendQuery } from './types.ts';
import type { CanonicalPayload, RepositoryQuery } from './types.ts';

export class PostRepository {
    private readonly http: HttpClient;

    public constructor(http: HttpClient) {
        this.http = http;
    }

    public list<T extends CanonicalPayload = CanonicalPayload>(
        query?: RepositoryQuery,
    ): Promise<T> {
        return this.http.request(appendQuery('content/posts', query));
    }

    public get<T extends CanonicalPayload = CanonicalPayload>(
        slug: string,
    ): Promise<T> {
        return this.http.request(
            `content/posts/${encodeURIComponent(slug)}`,
        );
    }
}
