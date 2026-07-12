<?php

namespace App\Services\Geolocation;

/**
 * Serviço de Geolocalização e Cerca Virtual (Geofence)
 *
 * Implementa cálculos de distância usando fórmula de Haversine
 * e validação de cercas virtuais para controle de ponto geolocalizado.
 */
class GeofenceService
{
    /**
     * Raio da Terra em metros
     */
    private const EARTH_RADIUS = 6371000;

    /**
     * Calcula a distância entre duas coordenadas usando a fórmula de Haversine
     *
     * @param float $lat1 Latitude do ponto 1
     * @param float $lon1 Longitude do ponto 1
     * @param float $lat2 Latitude do ponto 2
     * @param float $lon2 Longitude do ponto 2
     * @return float Distância em metros
     * @throws \InvalidArgumentException Se coordenadas forem inválidas
     */
    public function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        // Validar coordenadas
        if ($lat1 < -90 || $lat1 > 90 || $lat2 < -90 || $lat2 > 90) {
            throw new \InvalidArgumentException('Latitude must be between -90 and 90 degrees');
        }

        if ($lon1 < -180 || $lon1 > 180 || $lon2 < -180 || $lon2 > 180) {
            throw new \InvalidArgumentException('Longitude must be between -180 and 180 degrees');
        }

        // Converter graus para radianos
        $lat1Rad = deg2rad($lat1);
        $lon1Rad = deg2rad($lon1);
        $lat2Rad = deg2rad($lat2);
        $lon2Rad = deg2rad($lon2);

        // Diferenças
        $deltaLat = $lat2Rad - $lat1Rad;
        $deltaLon = $lon2Rad - $lon1Rad;

        // Fórmula de Haversine
        $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
             cos($lat1Rad) * cos($lat2Rad) *
             sin($deltaLon / 2) * sin($deltaLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        // Distância em metros (retornar sem arredondamento para maior precisão)
        $distance = self::EARTH_RADIUS * $c;

        return $distance;
    }

    /**
     * Verifica se um ponto está dentro de uma cerca virtual (geofence)
     *
     * @param float $testLat Latitude do ponto a testar
     * @param float $testLon Longitude do ponto a testar
     * @param float $centerLat Latitude do centro da cerca
     * @param float $centerLon Longitude do centro da cerca
     * @param float $radius Raio da cerca em metros
     * @return bool True se o ponto está dentro da cerca
     * @throws \InvalidArgumentException Se raio for negativo
     */
    public function isWithinGeofence(
        float $testLat,
        float $testLon,
        float $centerLat,
        float $centerLon,
        float $radius
    ): bool {
        if ($radius < 0) {
            throw new \InvalidArgumentException('Radius cannot be negative');
        }

        $distance = $this->calculateDistance($testLat, $testLon, $centerLat, $centerLon);

        return $distance <= $radius;
    }

    /**
     * Formata distância para exibição legível
     *
     * @param float $distanceInMeters Distância em metros
     * @return string Distância formatada (ex: "500m" ou "2.8km")
     */
    public function formatDistance(float $distanceInMeters): string
    {
        if ($distanceInMeters < 1000) {
            return round($distanceInMeters) . 'm';
        }

        $kilometers = $distanceInMeters / 1000;
        return number_format($kilometers, 1, '.', '') . 'km';
    }

    /**
     * Calcula o bearing (direção) entre dois pontos em graus
     *
     * @param float $lat1 Latitude do ponto inicial
     * @param float $lon1 Longitude do ponto inicial
     * @param float $lat2 Latitude do ponto final
     * @param float $lon2 Longitude do ponto final
     * @return float Bearing em graus (0-360)
     */
    public function getBearing(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLon = deg2rad($lon2 - $lon1);

        $y = sin($deltaLon) * cos($lat2Rad);
        $x = cos($lat1Rad) * sin($lat2Rad) -
             sin($lat1Rad) * cos($lat2Rad) * cos($deltaLon);

        $bearingRad = atan2($y, $x);
        $bearingDeg = rad2deg($bearingRad);

        // Normalizar para 0-360
        return fmod(($bearingDeg + 360), 360);
    }

    /**
     * Verifica múltiplas cercas virtuais e retorna quais contêm o ponto
     *
     * @param float $testLat Latitude do ponto a testar
     * @param float $testLon Longitude do ponto a testar
     * @param array $geofences Array de cercas [['lat' => ..., 'lon' => ..., 'radius' => ..., 'name' => ...]]
     * @return array Array de cercas que contêm o ponto
     */
    public function checkMultipleGeofences(float $testLat, float $testLon, array $geofences): array
    {
        $matches = [];

        foreach ($geofences as $geofence) {
            $isInside = $this->isWithinGeofence(
                $testLat,
                $testLon,
                $geofence['lat'],
                $geofence['lon'],
                $geofence['radius']
            );

            if ($isInside) {
                $matches[] = $geofence;
            }
        }

        return $matches;
    }
}
