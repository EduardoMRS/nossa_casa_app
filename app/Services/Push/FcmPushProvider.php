<?php

namespace App\Services\Push;

use App\Contracts\NativePushProvider;
use App\Data\PushMessage;
use App\Data\PushResult;
use App\Enums\PushTransport;
use App\Models\DevicePushToken;
use Illuminate\Support\Facades\Http;

final class FcmPushProvider implements NativePushProvider
{
    public function supports(PushTransport $transport): bool
    {
        return $transport === PushTransport::FCM
            && filled(config('services.native_push.fcm.project_id'))
            && filled(config('services.native_push.fcm.access_token'));
    }

    public function send(PushMessage $message, DevicePushToken $device): PushResult
    {
        $projectId = (string) config('services.native_push.fcm.project_id');
        $response = Http::acceptJson()
            ->withToken((string) config('services.native_push.fcm.access_token'))
            ->timeout(15)
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                'message' => [
                    'token' => $device->token,
                    'notification' => ['title' => $message->title, 'body' => $message->body],
                    'data' => $message->dataPayload(),
                    'android' => ['priority' => 'high'],
                ],
            ]);

        if ($response->successful()) {
            return PushResult::sent($response->json('name'));
        }

        $status = (string) $response->json('error.status', 'FCM_FAILED');

        return PushResult::failed($status, in_array($status, ['NOT_FOUND', 'UNREGISTERED'], true));
    }
}
