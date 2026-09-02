<?php

namespace App\Services;

use App\Models\AppInstance;
use Illuminate\Support\Str;
use RuntimeException;

final class InstanceIdentity
{
    private ?string $resolvedId = null;

    public function id(): string
    {
        if ($this->resolvedId !== null) {
            return $this->resolvedId;
        }

        $configuredId = trim((string) config('native.instance_id'));

        if ($configuredId !== '') {
            if (! Str::isUlid($configuredId)) {
                throw new RuntimeException(__('api.errors.invalid_instance_id'));
            }

            return $this->resolvedId = $configuredId;
        }

        AppInstance::query()->insertOrIgnore([
            'id' => (string) Str::ulid(),
            'key' => 'primary',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->resolvedId = AppInstance::query()
            ->where('key', 'primary')
            ->valueOrFail('id');
    }
}
