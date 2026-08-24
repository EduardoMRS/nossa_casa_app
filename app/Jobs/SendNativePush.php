<?php

namespace App\Jobs;

use App\Data\PushMessage;
use App\Models\DevicePushToken;
use App\Services\NativePushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

final class SendNativePush implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300, 900];

    public function __construct(public string $deviceId, public PushMessage $message)
    {
        $this->onQueue('push');
    }

    /**
     * Execute the job.
     */
    public function handle(NativePushService $push): void
    {
        $device = DevicePushToken::query()->find($this->deviceId);

        if (! $device || $device->invalidated_at !== null) {
            return;
        }

        $result = $push->send($device, $this->message);

        if ($result->invalidToken) {
            $device->forceFill(['invalidated_at' => now()])->save();

            return;
        }

        if (! $result->sent && $result->error !== 'PUSH_DISABLED') {
            throw new RuntimeException($result->error ?? 'PUSH_FAILED');
        }
    }
}
