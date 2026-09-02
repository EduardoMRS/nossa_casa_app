<?php

namespace App\Services;

use App\Contracts\NativePushProvider;
use App\Data\PushMessage;
use App\Data\PushResult;
use App\Jobs\SendNativePush;
use App\Models\DevicePushToken;
use App\Models\User;
use InvalidArgumentException;

final readonly class NativePushService
{
    /** @param array<int, NativePushProvider> $providers */
    public function __construct(private array $providers) {}

    public function send(DevicePushToken $device, PushMessage $message): PushResult
    {
        $this->validateMessage($message);

        if (! $device->accepts($message->category) || $device->invalidated_at !== null) {
            return PushResult::disabled();
        }

        if (config('services.native_push.default') === 'null') {
            return PushResult::disabled();
        }

        foreach ($this->providers as $provider) {
            if ($provider->supports($device->transport)) {
                return $provider->send($message, $device);
            }
        }

        return PushResult::disabled();
    }

    public function dispatchToUser(User $user, PushMessage $message): int
    {
        $deviceIds = $user->devicePushTokens()
            ->whereNull('invalidated_at')
            ->get()
            ->filter(fn (DevicePushToken $device): bool => $device->accepts($message->category))
            ->pluck('id');

        $deviceIds->each(fn (string $deviceId) => SendNativePush::dispatch($deviceId, $message));

        return $deviceIds->count();
    }

    private function validateMessage(PushMessage $message): void
    {
        if (! str_starts_with($message->deepLink, '/') && ! str_starts_with($message->deepLink, 'nossacasa://')) {
            throw new InvalidArgumentException(__('api.push.invalid_deep_link'));
        }

        $encoded = json_encode([
            'title' => $message->title,
            'body' => $message->body,
            'data' => $message->dataPayload(),
        ], JSON_THROW_ON_ERROR);

        if (strlen($encoded) > 2048) {
            throw new InvalidArgumentException(__('api.push.payload_too_large'));
        }
    }
}
