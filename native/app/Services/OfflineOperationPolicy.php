<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class OfflineOperationPolicy
{
    /** @param array<string, mixed> $payload */
    public function idempotencyKey(string $instanceId, string $operation, array $payload): string
    {
        ksort($payload);

        return hash('sha256', $instanceId.'|'.$operation.'|'.json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function canQueue(string $operation): bool
    {
        return false;
    }

    public function ensureOnline(bool $online, string $operation): void
    {
        if ($online) {
            return;
        }

        throw ValidationException::withMessages([
            Str::snake($operation) => [__('native.errors.online_required')],
        ]);
    }
}
