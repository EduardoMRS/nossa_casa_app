<?php

namespace App\Enums;

enum PushTransport: string
{
    case APNS = 'apns';
    case FCM = 'fcm';
    case UNIFIED = 'unified';
    case WEB = 'web';
}
