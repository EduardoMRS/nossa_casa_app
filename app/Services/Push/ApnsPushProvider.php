<?php

namespace App\Services\Push;

use App\Contracts\NativePushProvider;
use App\Data\PushMessage;
use App\Data\PushResult;
use App\Enums\PushTransport;
use App\Models\DevicePushToken;
use Illuminate\Support\Facades\Http;

final class ApnsPushProvider implements NativePushProvider
{
    public function supports(PushTransport $transport): bool
    {
        return $transport === PushTransport::APNS
            && filled(config('services.native_push.apns.bundle_id'))
            && filled(config('services.native_push.apns.bearer_token'));
    }

    public function send(PushMessage $message, DevicePushToken $device): PushResult
    {
        $response = Http::withToken((string) config('services.native_push.apns.bearer_token'))
            ->withHeaders([
                'apns-topic' => (string) config('services.native_push.apns.bundle_id'),
                'apns-push-type' => 'alert',
                'apns-priority' => '10',
            ])
            ->timeout(15)
            ->post(rtrim((string) config('services.native_push.apns.endpoint'), '/').'/3/device/'.$device->token, [
                'aps' => [
                    'alert' => ['title' => $message->title, 'body' => $message->body],
                    'sound' => 'default',
                ],
                ...$message->dataPayload(),
            ]);

        if ($response->successful()) {
            return PushResult::sent($response->header('apns-id'));
        }

        $reason = (string) $response->json('reason', 'APNS_FAILED');

        return PushResult::failed($reason, in_array($reason, [
            'BadDeviceToken',
            'DeviceTokenNotForTopic',
            'Unregistered',
        ], true));
    }
}
