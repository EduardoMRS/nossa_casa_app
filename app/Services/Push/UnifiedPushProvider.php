<?php

namespace App\Services\Push;

use App\Contracts\NativePushProvider;
use App\Data\PushMessage;
use App\Data\PushResult;
use App\Enums\PushTransport;
use App\Models\DevicePushToken;
use Illuminate\Support\Facades\Http;

final class UnifiedPushProvider implements NativePushProvider
{
    public function supports(PushTransport $transport): bool
    {
        return $transport === PushTransport::UNIFIED
            && config('services.native_push.unified.enabled') === true;
    }

    public function send(PushMessage $message, DevicePushToken $device): PushResult
    {
        $endpoint = $device->token;
        $host = parse_url($endpoint, PHP_URL_HOST);
        $allowedHosts = config('services.native_push.unified.allowed_hosts', []);

        if (! is_string($host) || parse_url($endpoint, PHP_URL_SCHEME) !== 'https' || ! in_array($host, $allowedHosts, true)) {
            return PushResult::failed('UNIFIED_ENDPOINT_BLOCKED', true);
        }

        $response = Http::asJson()->timeout(15)->post($endpoint, [
            'notification' => [
                'title' => $message->title,
                'body' => $message->body,
                'category' => $message->category->value,
                'deep_link' => $message->deepLink,
            ],
        ]);

        return $response->successful()
            ? PushResult::sent()
            : PushResult::failed('UNIFIED_FAILED', in_array($response->status(), [404, 410], true));
    }
}
