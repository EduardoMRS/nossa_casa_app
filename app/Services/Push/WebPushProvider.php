<?php

namespace App\Services\Push;

use App\Contracts\NativePushProvider;
use App\Data\PushMessage;
use App\Data\PushResult;
use App\Enums\PushTransport;
use App\Models\DevicePushToken;
use App\Models\PushSubscription;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

final class WebPushProvider implements NativePushProvider
{
    public function supports(PushTransport $transport): bool
    {
        return $transport === PushTransport::WEB
            && filled(config('services.webpush.public_key'))
            && filled(config('services.webpush.private_key'));
    }

    public function send(PushMessage $message, DevicePushToken $device): PushResult
    {
        $stored = PushSubscription::query()->where('endpoint_hash', $device->token_hash)->first();

        if (! $stored) {
            return PushResult::failed('WEB_SUBSCRIPTION_NOT_FOUND', true);
        }

        $webPush = new WebPush(['VAPID' => [
            'subject' => (string) config('services.webpush.subject'),
            'publicKey' => (string) config('services.webpush.public_key'),
            'privateKey' => (string) config('services.webpush.private_key'),
        ]]);
        $subscription = Subscription::create([
            'endpoint' => $stored->endpoint,
            'publicKey' => $stored->public_key,
            'authToken' => $stored->auth_token,
            'contentEncoding' => $stored->content_encoding,
        ]);
        $report = $webPush->sendOneNotification($subscription, json_encode([
            'title' => $message->title,
            'body' => $message->body,
            'category' => $message->category->value,
            'url' => $message->deepLink,
        ], JSON_THROW_ON_ERROR));

        return $report->isSuccess()
            ? PushResult::sent()
            : PushResult::failed($report->getReason(), $report->isSubscriptionExpired());
    }
}
