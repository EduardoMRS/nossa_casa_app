import assert from 'node:assert/strict';
import test from 'node:test';

import {
    assertCredentialTarget,
    discoverServer,
    discoveryUrlFor,
    InstanceScopedStorage,
    normalizeServerInput,
    requestServer,
    serverStorageKey,
    validateServerDiscovery,
} from '../../resources/js/lib/serverDiscovery.ts';

const instanceId = '01JABCDEF123456789ABCDEFGH';

function discoveryPayload(overrides: Record<string, unknown> = {}) {
    return {
        protocol: 'nossa-casa',
        protocol_version: 1,
        instance_id: instanceId,
        instance_name: 'Nossa Casa',
        api_base_url: 'https://church.test/api',
        web_base_url: 'https://church.test',
        auth_driver: 'sanctum',
        capabilities: ['posts', 'events'],
        api_version: 1,
        minimum_app_version: '1.0.0',
        privacy_url: null,
        terms_url: null,
        ...overrides,
    };
}

test('normalizes manual domains and QR payloads to the discovery endpoint', () => {
    assert.equal(
        normalizeServerInput('church.test').toString(),
        'https://church.test/',
    );
    assert.equal(
        discoveryUrlFor('{"server_url":"https://church.test"}').toString(),
        'https://church.test/.well-known/nossa-casa.json',
    );
});

test('rejects unsafe and insecure server input by default', () => {
    assert.throws(() =>
        normalizeServerInput('https://user:secret@church.test'),
    );
    assert.throws(() =>
        normalizeServerInput('https://church.test?redirect=evil'),
    );
    assert.throws(() => normalizeServerInput('http://church.test'));
    assert.equal(
        normalizeServerInput('http://localhost:8000', { allowInsecure: true })
            .origin,
        'http://localhost:8000',
    );
});

test('validates discovery protocol and canonical host', () => {
    const discoveryUrl = discoveryUrlFor('church.test');
    const server = validateServerDiscovery(discoveryPayload(), discoveryUrl);

    assert.equal(server.instance_id, instanceId);
    assert.equal(server.api_origin, 'https://church.test');
    assert.throws(() =>
        validateServerDiscovery(
            discoveryPayload({ api_base_url: 'https://attacker.test/api' }),
            discoveryUrl,
        ),
    );
});

test('isolates storage namespaces by instance id', () => {
    assert.equal(
        serverStorageKey(instanceId, 'session'),
        `servers/${instanceId}/session`,
    );
    assert.equal(
        serverStorageKey(instanceId, 'cache'),
        `servers/${instanceId}/cache`,
    );
});

test('never allows credentials outside the canonical api origin and path', () => {
    const server = validateServerDiscovery(
        discoveryPayload(),
        discoveryUrlFor('church.test'),
    );

    assert.doesNotThrow(() => assertCredentialTarget(server, '/api/auth/me'));
    assert.throws(() =>
        assertCredentialTarget(server, 'https://attacker.test/api/auth/me'),
    );
    assert.throws(() =>
        assertCredentialTarget(server, 'https://church.test/private'),
    );
});

test('discovers manual or QR servers without credentials or redirects', async () => {
    let requestedUrl = '';
    let requestedInit: RequestInit | undefined;
    const fetcher = async (
        input: string | URL | Request,
        init?: RequestInit,
    ): Promise<Response> => {
        requestedUrl = input.toString();
        requestedInit = init;

        return new Response(JSON.stringify(discoveryPayload()), {
            status: 200,
            headers: { 'Content-Type': 'application/json' },
        });
    };
    const server = await discoverServer(
        '{"server_url":"https://church.test"}',
        { fetcher },
    );

    assert.equal(
        requestedUrl,
        'https://church.test/.well-known/nossa-casa.json',
    );
    assert.equal(requestedInit?.credentials, 'omit');
    assert.equal(requestedInit?.redirect, 'error');
    assert.equal(server.instance_id, instanceId);
});

test('server requests attach bearer only to the validated api target', async () => {
    const server = validateServerDiscovery(
        discoveryPayload(),
        discoveryUrlFor('church.test'),
    );
    let authorization = '';
    const fetcher = async (
        _input: string | URL | Request,
        init?: RequestInit,
    ): Promise<Response> => {
        authorization = new Headers(init?.headers).get('Authorization') ?? '';

        return new Response(null, { status: 204 });
    };

    await requestServer(server, 'auth/me', {
        accessToken: 'secret-token',
        fetcher,
    });

    assert.equal(authorization, 'Bearer secret-token');
    await assert.rejects(() =>
        requestServer(server, 'https://attacker.test/api/auth/me', {
            accessToken: 'secret-token',
            fetcher,
        }),
    );
});

test('instance scoped storage never shares values between servers', async () => {
    const values = new Map<string, string>();
    const storage = {
        getItem: (key: string) => values.get(key) ?? null,
        setItem: (key: string, value: string) => {
            values.set(key, value);
        },
        removeItem: (key: string) => {
            values.delete(key);
        },
    };
    const otherInstanceId = `${instanceId.slice(0, -1)}Z`;
    const first = new InstanceScopedStorage(instanceId, storage);
    const second = new InstanceScopedStorage(otherInstanceId, storage);

    await first.set('selected_church', 'church-a');
    await second.set('selected_church', 'church-b');

    assert.equal(await first.get('selected_church'), 'church-a');
    assert.equal(await second.get('selected_church'), 'church-b');
});
