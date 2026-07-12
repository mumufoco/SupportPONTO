<?php

namespace App\Services\Geolocation;

class CoordinateValidationService
{
    public function validate(float $latitude, float $longitude): array
    {
        if ($latitude < -90 || $latitude > 90) {
            return ['valid' => false, 'error' => 'Latitude inválida. Deve estar entre -90 e 90.'];
        }

        if ($longitude < -180 || $longitude > 180) {
            return ['valid' => false, 'error' => 'Longitude inválida. Deve estar entre -180 e 180.'];
        }

        if ($latitude === 0.0 && $longitude === 0.0) {
            return ['valid' => false, 'error' => 'Coordenadas inválidas (0, 0).'];
        }

        return ['valid' => true, 'latitude' => $latitude, 'longitude' => $longitude];
    }
}
