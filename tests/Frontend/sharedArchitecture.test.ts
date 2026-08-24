import assert from 'node:assert/strict';
import test from 'node:test';
import {
    FetchHttpClient,
    ApiError,
} from '../../resources/js/shared/http/FetchHttpClient.ts';
import type {
    KeyValueStorage,
    NetworkStatus,
} from '../../resources/js/shared/platform/contracts.ts';
import { queryFromPaginationLink } from '../../resources/js/shared/repositories/content/types.ts';
import { createRepositories } from '../../resources/js/shared/repositories/createRepositories.ts';
import { InteractionRepository } from '../../resources/js/shared/repositories/InteractionRepository.ts';
import { PublicContentRepository } from '../../resources/js/shared/repositories/PublicContentRepository.ts';
import { HydratedStore } from '../../resources/js/shared/stores/HydratedStore.ts';
import {
    createRequestState,
    runRequest,
} from '../../resources/js/shared/stores/RequestState.ts';

test('repository delegates canonical paths to the shared client', async () => {
    const paths: string[] = [];
    const repository = new PublicContentRepository({
        async request<T>(path: string): Promise<T> {
            paths.push(path);

            return { path } as T;
        },
    });

    await repository.portal();
    await repository.posts('?page=2');
    await repository.event('summer camp');
    await repository.gallery('?view=transmissions');

    assert.deepEqual(paths, [
        'portal',
        'content/posts?page=2',
        'content/events/summer%20camp',
        'content/gallery?view=transmissions',
    ]);
});

test('domain repositories serialize filters and canonical resource paths', async () => {
    const paths: string[] = [];
    const platform = {
        http: {
            async request<T>(path: string): Promise<T> {
                paths.push(path);

                return { path } as T;
            },
        },
        storage: {
            get: async () => null,
            set: async () => undefined,
            remove: async () => undefined,
        },
        network: {
            isOnline: () => true,
            subscribe: () => () => undefined,
        },
    };
    const repositories = createRepositories(platform);

    await repositories.portal.get();
    await repositories.posts.list({ page: 2, category: 'news', empty: null });
    await repositories.events.get('summer camp');
    await repositories.gallery.list(new URLSearchParams({ view: 'videos' }));
    await repositories.library.bible();
    await repositories.liveStreams.get('stream/id');

    assert.deepEqual(paths, [
        'portal',
        'content/posts?page=2&category=news',
        'content/events/summer%20camp',
        'content/gallery?view=videos',
        'content/library/bible',
        'content/live-streams/stream%2Fid',
    ]);
});

test('pagination links are reduced to repository query parameters', () => {
    const query = queryFromPaginationLink(
        'https://church.test/api/content/posts?page=3&category=news',
    );

    assert.equal(query.get('page'), '3');
    assert.equal(query.get('category'), 'news');
    assert.equal(query.has('api'), false);
});

test('request state standardizes loading and errors', async () => {
    const state = createRequestState();
    let applied = '';

    assert.equal(
        await runRequest(
            state,
            async () => 'loaded',
            (payload) => {
                applied = payload;
            },
            'failed',
        ),
        true,
    );
    assert.deepEqual(state, { loading: false, error: null });
    assert.equal(applied, 'loaded');

    assert.equal(
        await runRequest(
            state,
            async () => {
                throw new Error('network');
            },
            () => undefined,
            'failed',
        ),
        false,
    );
    assert.deepEqual(state, { loading: false, error: 'failed' });
});

test('interaction repository maps shared DTOs to the existing API contract', async () => {
    const requests: Array<{ path: string; options?: unknown }> = [];
    const repository = new InteractionRepository({
        async request<T>(path: string, options?: unknown): Promise<T> {
            requests.push({ path, options });

            return { id: '01COMMENT' } as T;
        },
    });

    await repository.createComment({
        commentableType: 'post',
        commentableId: '01POST',
        content: 'Amen',
    });
    await repository.createReaction({
        reactionableType: 'comment',
        reactionableId: '01COMMENT',
        content: '❤️',
    });
    await repository.updateCommentPin('comment/id', true);
    await repository.deleteComment('comment/id');
    await repository.deleteReaction('reaction/id');

    assert.deepEqual(requests, [
        {
            path: 'comments',
            options: {
                method: 'POST',
                body: {
                    commentable_type: 'post',
                    commentable_id: '01POST',
                    content: 'Amen',
                },
            },
        },
        {
            path: 'reactions',
            options: {
                method: 'POST',
                body: {
                    reactionable_type: 'comment',
                    reactionable_id: '01COMMENT',
                    content: '❤️',
                    type: 'emoji',
                },
            },
        },
        {
            path: 'comments/comment%2Fid/pin',
            options: { method: 'PUT', body: { is_pinned: true } },
        },
        {
            path: 'comments/comment%2Fid',
            options: { method: 'DELETE' },
        },
        {
            path: 'reactions/reaction%2Fid',
            options: { method: 'DELETE' },
        },
    ]);
});

test('form and prayer repositories map application payloads without browser dependencies', async () => {
    const requests: Array<{ path: string; options?: unknown }> = [];
    const platform = {
        http: {
            async request<T>(path: string, options?: unknown): Promise<T> {
                requests.push({ path, options });

                return {} as T;
            },
        },
        storage: {
            get: async () => null,
            set: async () => undefined,
            remove: async () => undefined,
        },
        network: {
            isOnline: () => true,
            subscribe: () => () => undefined,
        },
    };
    const repositories = createRepositories(platform);

    await repositories.forms.submit('form/id', { name: 'Maria' });
    await repositories.prayers.create({
        content: 'Please pray for my family.',
        isAnonymous: true,
    });

    assert.deepEqual(requests, [
        {
            path: 'forms/form%2Fid/responses',
            options: {
                method: 'POST',
                body: { answers: { name: 'Maria' } },
            },
        },
        {
            path: 'prayer-requests',
            options: {
                method: 'POST',
                body: {
                    content: 'Please pray for my family.',
                    is_anonymous: true,
                },
            },
        },
    ]);
});

test('http client attaches bearer and church only to its configured api origin', async () => {
    const captured: { request?: Request } = {};
    const client = new FetchHttpClient({
        apiBaseUrl: 'https://church.test/api',
        accessToken: async () => 'access-secret',
        churchId: async () => '01KCHURCH',
        fetcher: async (input, init) => {
            captured.request = new Request(input, init);

            return new Response(JSON.stringify({ ok: true }), {
                headers: { 'content-type': 'application/json' },
            });
        },
    });

    await client.request('content/posts');

    assert.equal(
        captured.request?.url,
        'https://church.test/api/content/posts',
    );
    assert.equal(
        captured.request?.headers.get('authorization'),
        'Bearer access-secret',
    );
    assert.equal(
        captured.request?.headers.get('x-church-id'),
        '01KCHURCH',
    );
    await assert.rejects(
        () => client.request('https://attacker.test/api/posts'),
        (error: unknown) =>
            error instanceof ApiError &&
            error.code === 'CROSS_ORIGIN_REQUEST_BLOCKED',
    );
});

test('http client applies adapter headers to mutations', async () => {
    let request: Request | undefined;
    const client = new FetchHttpClient({
        apiBaseUrl: 'https://church.test/api',
        defaultHeaders: async () => ({ 'X-CSRF-TOKEN': 'csrf-secret' }),
        fetcher: async (input, init) => {
            request = new Request(input, init);

            return new Response(JSON.stringify({ id: '01COMMENT' }), {
                headers: { 'content-type': 'application/json' },
            });
        },
    });

    await client.request('comments', {
        method: 'POST',
        body: { content: 'Amen' },
    });

    assert.equal(request?.headers.get('x-csrf-token'), 'csrf-secret');
    assert.equal(request?.headers.get('content-type'), 'application/json');
    assert.equal(request?.credentials, 'same-origin');
});

test('hydrated store keeps initial props and reports stale offline cache', async () => {
    const values = new Map<string, string>();
    const storage: KeyValueStorage = {
        get: async (key) => values.get(key) ?? null,
        set: async (key, value) => void values.set(key, value),
        remove: async (key) => void values.delete(key),
    };
    let online = true;
    const network: NetworkStatus = {
        isOnline: () => online,
        subscribe: () => () => undefined,
    };
    const store = new HydratedStore(storage, network, 'portal', -1);

    await store.hydrate({ title: 'Initial props' });
    online = false;
    const cached = await new HydratedStore(
        storage,
        network,
        'portal',
        -1,
    ).hydrate();

    assert.deepEqual(cached.data, { title: 'Initial props' });
    assert.equal(cached.offline, true);
    assert.equal(cached.stale, true);
});
