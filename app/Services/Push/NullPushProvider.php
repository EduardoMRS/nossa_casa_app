<?php

namespace App\Services\Push;

use App\Contracts\NativePushProvider;
use App\Data\PushMessage;
use App\Data\PushResult;
use App\Enums\PushTransport;
use App\Models\DevicePushToken;

final class NullPushProvider implements NativePushProvider
{
    public function supports(PushTransport $transport): bool
    {
        return true;
    }

    public function send(PushMessage $message, DevicePushToken $device): PushResult
    {
        return PushResult::disabled();
    }
}
