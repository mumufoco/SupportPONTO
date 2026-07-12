<?php

namespace App\Services;

use App\Services\Geolocation\CoordinateValidationService;
use App\Services\Geolocation\DistanceService;
use App\Services\Geolocation\GeocodingService;
use App\Services\Geolocation\GeofenceValidationService;

/**
 * Geolocation Service (Facade)
 *
 * Mantém a API pública legada delegando para componentes especializados.
 */
class GeolocationService
{
    private CoordinateValidationService $coordinateValidationService;
    private DistanceService $distanceService;
    private GeofenceValidationService $geofenceValidationService;
    private GeocodingService $geocodingService;

    public function __construct()
    {
        $this->coordinateValidationService = new CoordinateValidationService();
        $this->distanceService = new DistanceService();
        $this->geofenceValidationService = new GeofenceValidationService();
        $this->geocodingService = new GeocodingService();
    }

    public function validateCoordinates(float $latitude, float $longitude): array
    {
        return $this->coordinateValidationService->validate($latitude, $longitude);
    }

    public function validateGeofence(float $latitude, float $longitude, ?float $accuracyMeters = null): array
    {
        $validation = $this->validateCoordinates($latitude, $longitude);
        return $this->geofenceValidationService->validateGeofence($latitude, $longitude, $validation, $accuracyMeters);
    }

    public function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        return $this->distanceService->calculate($lat1, $lon1, $lat2, $lon2);
    }

    public function isWithinGeofence(float $latitude, float $longitude, int $geofenceId): array
    {
        return $this->geofenceValidationService->isWithinGeofence($latitude, $longitude, $geofenceId);
    }

    public function getGeofencesWithDistance(float $latitude, float $longitude): array
    {
        return $this->geofenceValidationService->geofencesWithDistance($latitude, $longitude);
    }

    public function getNearestGeofence(float $latitude, float $longitude): ?array
    {
        return $this->geofenceValidationService->nearestGeofence($latitude, $longitude);
    }

    public function formatDistance(float $meters): string
    {
        return $this->distanceService->format($meters);
    }

    public function reverseGeocode(float $latitude, float $longitude): array
    {
        return $this->geocodingService->reverseGeocode($latitude, $longitude);
    }

    public function geocode(string $address): array
    {
        return $this->geocodingService->geocode($address);
    }

    public function calculateGeofenceArea(float $radiusMeters): array
    {
        return $this->distanceService->area($radiusMeters);
    }

    public function getStatistics(): array
    {
        return $this->geofenceValidationService->statistics();
    }
}
