<?php

namespace App\ValueObjects;

/**
 * MELHORIA 8: Value Object para coordenadas GPS.
 *
 * Garante que latitude e longitude são geograficamente válidos.
 * Elimina validações de coordenadas espalhadas por services de geolocalização.
 *
 * Uso:
 *   $coords = new Coordinates(-15.7801, -47.9292); // Brasília
 *   echo $coords->latitude;
 *   $distanceKm = $coords->distanceTo($geofenceCenter);
 */
final readonly class Coordinates
{
    public function __construct(
        public readonly float $latitude,
        public readonly float $longitude,
    ) {
        if ($latitude < -90.0 || $latitude > 90.0) {
            throw new \App\Exceptions\ValidationException(
                ['latitude' => "Latitude inválida: {$latitude}. Deve estar entre -90 e 90."],
                'INVALID_LATITUDE'
            );
        }
        if ($longitude < -180.0 || $longitude > 180.0) {
            throw new \App\Exceptions\ValidationException(
                ['longitude' => "Longitude inválida: {$longitude}. Deve estar entre -180 e 180."],
                'INVALID_LONGITUDE'
            );
        }
    }

    /**
     * Distância em metros entre dois pontos usando a fórmula de Haversine.
     */
    public function distanceTo(Coordinates $other): float
    {
        $earthRadius = 6371000; // metros

        $lat1 = deg2rad($this->latitude);
        $lat2 = deg2rad($other->latitude);
        $dLat = deg2rad($other->latitude - $this->latitude);
        $dLon = deg2rad($other->longitude - $this->longitude);

        $a = sin($dLat / 2) ** 2
           + cos($lat1) * cos($lat2) * sin($dLon / 2) ** 2;

        return 2 * $earthRadius * asin(sqrt($a));
    }

    /**
     * Verifica se está dentro de um raio (em metros).
     */
    public function isWithinRadius(Coordinates $center, float $radiusMeters): bool
    {
        return $this->distanceTo($center) <= $radiusMeters;
    }

    public function __toString(): string
    {
        return "{$this->latitude},{$this->longitude}";
    }

    /** Tenta criar sem lançar exceção */
    public static function tryFrom(float|string|null $lat, float|string|null $lon): ?self
    {
        if ($lat === null || $lon === null || $lat === '' || $lon === '') {
            return null;
        }
        try {
            return new self((float) $lat, (float) $lon);
        } catch (\Throwable) {
            return null;
        }
    }
}
