<?php

namespace App\Enums;

enum LiveStreamStatus: string
{
    case READY = 'ready';
    case LIVE = 'live';
    case OFFLINE = 'offline';
    case STOPPED = 'stopped';
    case FAILED = 'failed';

    public function reservesChurchSlot(): bool
    {
        return in_array($this, [self::READY, self::LIVE, self::OFFLINE], true);
    }
}
