<?php

namespace App\Support;

use Illuminate\Support\Str;

class RecordingStoragePath
{
    public static function forDelivery(string $deliveryId, string $filename): string
    {
        $safeFilename = Str::afterLast(str_replace('\\', '/', $filename), '/');

        return 'live-streams/recordings/'.$deliveryId.'/'.$safeFilename;
    }
}
