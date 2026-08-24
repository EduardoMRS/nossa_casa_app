<?php

namespace App\Enums;

enum NotificationCategory: string
{
    case CONTENT = 'content';
    case EVENT = 'event';
    case LIVE_STREAM = 'live_stream';
    case KIDS = 'kids';
    case SYSTEM = 'system';
}
