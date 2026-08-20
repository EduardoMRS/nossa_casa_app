<?php

namespace App\Support;

final class GeoDistance
{
    public function between(
        float $fromLatitude,
        float $fromLongitude,
        float $toLatitude,
        float $toLongitude,
    ): float
    {
        $earthRadius = 6371;
        $latitudeDelta = deg2rad($toLatitude - $fromLatitude);
        $longitudeDelta = deg2rad($toLongitude - $fromLongitude);
        $value = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($fromLatitude)) * cos(deg2rad($toLatitude)) * sin($longitudeDelta / 2) ** 2;

        return round($earthRadius * 2 * atan2(sqrt($value), sqrt(1 - $value)), 1);
    }
}
