<?php

namespace App\Features\GeoDiscovery\Services;

final class DistanceCalculator
{
    private const float EARTH_RADIUS_KM = 6371.0;

    /**
     * Haversine distance in kilometres between two WGS84 points.
     */
    public function haversineKm(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2,
    ): float {
        $phi1 = deg2rad($lat1);
        $phi2 = deg2rad($lat2);
        $dPhi = deg2rad($lat2 - $lat1);
        $dLambda = deg2rad($lon2 - $lon1);

        $a = sin($dPhi / 2) ** 2
            + cos($phi1) * cos($phi2) * sin($dLambda / 2) ** 2;

        return 2 * self::EARTH_RADIUS_KM * asin(min(1.0, sqrt($a)));
    }

    public function withinRadius(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2,
        float $radiusKm,
    ): bool {
        return $this->haversineKm($lat1, $lon1, $lat2, $lon2) <= $radiusKm;
    }

    public function formatKm(float $distanceKm, int $decimals = 1): float
    {
        return round($distanceKm, $decimals);
    }
}
