<?php

namespace App\Services\Geolocation;

class DistanceService
{
    private const EARTH_RADIUS_METERS = 6371000;

    public function calculate(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $lat1Rad = deg2rad($lat1);
        $lon1Rad = deg2rad($lon1);
        $lat2Rad = deg2rad($lat2);
        $lon2Rad = deg2rad($lon2);

        $deltaLat = $lat2Rad - $lat1Rad;
        $deltaLon = $lon2Rad - $lon1Rad;

        $a = sin($deltaLat / 2) * sin($deltaLat / 2)
            + cos($lat1Rad) * cos($lat2Rad) * sin($deltaLon / 2) * sin($deltaLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_METERS * $c;
    }

    public function format(float $meters): string
    {
        if ($meters < 1000) {
            return round($meters, 0) . ' metros';
        }

        return round($meters / 1000, 2) . ' km';
    }

    public function area(float $radiusMeters): array
    {
        $areaSquareMeters = pi() * pow($radiusMeters, 2);

        return [
            'radius_meters' => $radiusMeters,
            'area_square_meters' => round($areaSquareMeters, 2),
            'area_square_kilometers' => round($areaSquareMeters / 1000000, 4),
            'diameter_meters' => $radiusMeters * 2,
        ];
    }
}
