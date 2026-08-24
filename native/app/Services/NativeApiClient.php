<?php

namespace App\Services;

use App\Models\ServerProfile;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use NativePHP\Vibe\Facades\Vibe;
use RuntimeException;

final readonly class NativeApiClient
{
    public function __construct(private NativeSessionService $sessions) {}

    /** @param array<string, mixed> $credentials
     * @return array<string, mixed>
     */
    public function login(ServerProfile $server, array $credentials): array
    {
        $response = $this->request($server, 'POST', 'auth/login', [
            ...$credentials,
            'platform' => 'android',
            'app_version' => (string) config('nativephp.version'),
        ], authenticated: false, refreshOnUnauthorized: false);
        $session = $this->payload($response);
        $this->sessions->put($server, $session);
        $this->pushRealtimeToken($session);

        return $session;
    }

    /** @return array<string, mixed> */
    public function get(ServerProfile $server, string $path): array
    {
        return $this->payload($this->request($server, 'GET', $path));
    }

    /** @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function post(ServerProfile $server, string $path, array $body = []): array
    {
        return $this->payload($this->request($server, 'POST', $path, $body));
    }

    public function delete(ServerProfile $server, string $path): void
    {
        $this->request($server, 'DELETE', $path);
    }

    public function logout(ServerProfile $server): void
    {
        try {
            $this->request($server, 'POST', 'auth/logout', refreshOnUnauthorized: false);
        } finally {
            $this->sessions->forget($server);
            $server->forceFill(['selected_church_id' => null])->save();
        }
    }

    /** @param array<string, mixed> $body */
    private function request(
        ServerProfile $server,
        string $method,
        string $path,
        array $body = [],
        bool $authenticated = true,
        bool $refreshOnUnauthorized = true,
    ): Response {
        $url = $this->url($server, $path);
        $session = $authenticated ? $this->sessions->get($server) : null;
        $request = $this->http($server, $session);
        $response = $request->send($method, $url, $body === [] ? [] : ['json' => $body]);

        if ($response->status() === 401 && $authenticated && $refreshOnUnauthorized) {
            $session = $this->refresh($server, force: true);
            $response = $this->http($server, $session)->send($method, $url, $body === [] ? [] : ['json' => $body]);
        }

        if ($response->failed()) {
            $error = $response->json();
            throw ValidationException::withMessages([
                'api' => [is_array($error) && is_string($error['message'] ?? null)
                    ? $error['message']
                    : __('native.errors.request_failed')],
            ]);
        }

        return $response;
    }

    /** @return array<string, mixed> */
    private function refresh(ServerProfile $server, bool $force = false): array
    {
        return Cache::lock("native-refresh:{$server->instance_id}", 10)->block(5, function () use ($server, $force): array {
            $current = $this->sessions->get($server);

            if (! is_array($current) || ! is_string($current['refresh_token'] ?? null) || ! is_string($current['device_id'] ?? null)) {
                throw new RuntimeException('A refreshable mobile session is required.');
            }

            $expiresAt = strtotime((string) ($current['access_token_expires_at'] ?? ''));

            if (! $force && $expiresAt !== false && $expiresAt > time() + 30) {
                return $current;
            }

            $response = $this->request($server, 'POST', 'auth/refresh', [
                'refresh_token' => $current['refresh_token'],
                'device_id' => $current['device_id'],
            ], authenticated: false, refreshOnUnauthorized: false);
            $rotated = $this->payload($response);
            $this->sessions->put($server, $rotated);
            $this->pushRealtimeToken($rotated);

            return $rotated;
        });
    }

    /** @param array<string, mixed>|null $session */
    private function http(ServerProfile $server, ?array $session): PendingRequest
    {
        $request = Http::acceptJson()
            ->timeout((int) config('nossa_casa.request_timeout'))
            ->withoutRedirecting()
            ->withHeaders([
                'X-Nossa-Casa-API-Version' => (string) $server->api_version,
            ]);

        if (is_array($session) && is_string($session['access_token'] ?? null)) {
            $request = $request->withToken($session['access_token']);
        }

        if ($server->selected_church_id) {
            $request = $request->withHeader('X-Church-ID', $server->selected_church_id);
        }

        return $request;
    }

    private function url(ServerProfile $server, string $path): string
    {
        $path = ltrim($path, '/');

        if (
            $path === ''
            || str_contains($path, '://')
            || str_contains($path, '\\')
            || preg_match('~(^|/)\.\.?(?:/|$)~', rawurldecode($path)) === 1
        ) {
            throw new RuntimeException('Invalid native API path blocked.');
        }

        $url = rtrim($server->api_base_url, '/').'/'.$path;
        $api = parse_url($server->api_base_url);
        $target = parse_url($url);

        if (
            ! is_array($api)
            || ! is_array($target)
            || ($api['scheme'] ?? null) !== 'https'
            || ($target['scheme'] ?? null) !== ($api['scheme'] ?? null)
            || ($target['host'] ?? null) !== ($api['host'] ?? null)
            || ($target['port'] ?? null) !== ($api['port'] ?? null)
            || ! str_starts_with($target['path'] ?? '/', rtrim($api['path'] ?? '/api', '/').'/')
        ) {
            throw new RuntimeException('Cross-origin native API request blocked.');
        }

        return $url;
    }

    /** @return array<string, mixed> */
    private function payload(Response $response): array
    {
        $payload = $response->json();

        if (! is_array($payload)) {
            return [];
        }

        return is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
    }

    /** @param array<string, mixed> $session */
    private function pushRealtimeToken(array $session): void
    {
        if (is_string($session['access_token'] ?? null)) {
            Vibe::withToken($session['access_token']);
        }
    }
}
