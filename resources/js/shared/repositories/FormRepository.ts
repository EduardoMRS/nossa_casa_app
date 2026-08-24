import type { HttpClient } from '../platform/contracts.ts';

export class FormRepository {
    private readonly http: HttpClient;

    public constructor(http: HttpClient) {
        this.http = http;
    }

    public create<T>(payload: unknown): Promise<T> {
        return this.http.request('forms', { method: 'POST', body: payload });
    }

    public update<T>(formId: string, payload: unknown): Promise<T> {
        return this.http.request('forms/' + encodeURIComponent(formId), {
            method: 'PUT',
            body: payload,
        });
    }

    public submit<T>(formId: string, answers: Record<string, unknown>): Promise<T> {
        return this.http.request(
            'forms/' + encodeURIComponent(formId) + '/responses',
            { method: 'POST', body: { answers } },
        );
    }
}
