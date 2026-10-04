<?php

namespace App\Support;

final class Geo
{
    /** Distance à vol d'oiseau en km (formule de haversine). */
    public static function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /** Estimation de la distance routière : distance à vol d'oiseau × 1,3 (coefficient de détour usuel en zone rurale). */
    public static function roadEstimateKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        return round(self::haversineKm($lat1, $lon1, $lat2, $lon2) * 1.3, 1);
    }
}
