<?php

namespace App\Contracts;

use App\Data\PushMessage;
use App\Data\PushResult;
use App\Enums\PushTransport;
use App\Models\DevicePushToken;

interface NativePushProvider
{
    public function supports(PushTransport $transport): bool;

    public function send(PushMessage $message, DevicePushToken $device): PushResult;
}
