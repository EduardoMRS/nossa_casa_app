<?php

namespace App\Services;

use App\Models\ServerProfile;
use Illuminate\Support\Str;
use Native\Mobile\Facades\SecureStorage;
use RuntimeException;

final class NativeSessionService
{
    /** @param array<string, mixed> $session */
    public function put(ServerProfile $server, array $session): void
    {
        $encoded = json_encode($session, JSON_THROW_ON_ERROR);

        if (! SecureStorage::set($this->key($server), $encoded)) {
            throw new RuntimeException('Secure storage rejected the mobile session.');
        }
    }

    /** @return array<string, mixed>|null */
    public function get(ServerProfile $server): ?array
    {
        $encoded = SecureStorage::get($this->key($server));

        if ($encoded === null) {
            return null;
        }

        $session = json_decode($encoded, true);

        return is_array($session) ? $session : null;
    }

    public function forget(ServerProfile $server): void
    {
        SecureStorage::delete($this->key($server));
    }

    public function key(ServerProfile $server): string
    {
        return "servers/{$server->instance_id}/session";
    }

    public function deviceId(ServerProfile $server): string
    {
        $key = "servers/{$server->instance_id}/device_id";
        $deviceId = SecureStorage::get($key);

        if (is_string($deviceId) && Str::isUlid($deviceId)) {
            return $deviceId;
        }

        $deviceId = (string) Str::ulid();

        if (! SecureStorage::set($key, $deviceId)) {
            throw new RuntimeException('Secure storage rejected the device identity.');
        }

        return $deviceId;
    }
}
