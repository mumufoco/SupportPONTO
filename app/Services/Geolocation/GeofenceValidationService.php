<?php

namespace App\Services\Geolocation;

use App\Models\GeofenceModel;
use App\Models\SettingModel;

class GeofenceValidationService
{
    private GeofenceModel $geofenceModel;
    private SettingModel $settingModel;
    private DistanceService $distanceService;

    public function __construct()
    {
        $this->geofenceModel = new GeofenceModel();
        $this->settingModel = new SettingModel();
        $this->distanceService = new DistanceService();
    }

    public function validateGeofence(float $latitude, float $longitude, array $coordinateValidation, ?float $accuracyMeters = null): array
    {
        if (!($coordinateValidation['valid'] ?? false)) {
            return $coordinateValidation;
        }

        $requireGeofence = $this->settingModel->get('require_geofence', false);
        if (!$requireGeofence) {
            return ['valid' => true, 'message' => 'Geofencing não é obrigatório.', 'geofence_required' => false];
        }

        // MED-02 (auditoria): sem isto, location_accuracy era aceita e gravada mas nunca
        // validada — bastava enviar latitude/longitude idênticas ao centro da cerca (ex.:
        // via "fake GPS") para passar, mesmo com uma precisão declarada grosseiramente
        // incompatível com um GPS real. Uma precisão pior que o limite configurado
        // significa que a posição informada não é confiável o suficiente para confirmar
        // presença dentro de uma cerca — geralmente bem menor que a margem de erro.
        $maxAccuracy = (float) $this->settingModel->get('geofence_max_accuracy_meters', 150);
        if ($accuracyMeters !== null && $maxAccuracy > 0 && $accuracyMeters > $maxAccuracy) {
            return [
                'valid' => false,
                'geofence_matched' => false,
                'error' => 'Precisão da localização insuficiente para confirmar presença na área permitida. Aproxime-se de uma janela ou área aberta e tente novamente.',
                'accuracy_meters' => $accuracyMeters,
                'max_accuracy_meters' => $maxAccuracy,
            ];
        }

        $geofences = $this->geofenceModel->where('active', true)->findAll();
        if (empty($geofences)) {
            return [
                'valid' => true,
                'message' => 'Nenhuma cerca virtual configurada.',
                'geofence_required' => true,
                'geofences_configured' => false,
            ];
        }

        $matchedGeofence = null;
        $nearestGeofence = null;
        $nearestDistance = PHP_FLOAT_MAX;

        foreach ($geofences as $geofence) {
            $distance = $this->distanceService->calculate($latitude, $longitude, (float) $geofence->latitude, (float) $geofence->longitude);

            if ($distance < $nearestDistance) {
                $nearestDistance = $distance;
                $nearestGeofence = $geofence;
            }

            if ($distance <= (float) $geofence->radius_meters) {
                $matchedGeofence = $geofence;
                break;
            }
        }

        if ($matchedGeofence) {
            return [
                'valid' => true,
                'geofence_matched' => true,
                'geofence' => [
                    'id' => $matchedGeofence->id,
                    'name' => $matchedGeofence->name,
                    'distance_meters' => $this->distanceService->calculate($latitude, $longitude, (float) $matchedGeofence->latitude, (float) $matchedGeofence->longitude),
                ],
            ];
        }

        return [
            'valid' => false,
            'geofence_matched' => false,
            'error' => 'Você está fora da área permitida para registro de ponto.',
            'nearest_geofence' => [
                'name' => $nearestGeofence->name,
                'distance_meters' => round($nearestDistance, 2),
                'distance_readable' => $this->distanceService->format($nearestDistance),
            ],
        ];
    }

    public function isWithinGeofence(float $latitude, float $longitude, int $geofenceId): array
    {
        $geofence = $this->geofenceModel->find($geofenceId);
        if (!$geofence) {
            return ['within' => false, 'error' => 'Cerca virtual não encontrada.'];
        }

        $distance = $this->distanceService->calculate($latitude, $longitude, (float) $geofence->latitude, (float) $geofence->longitude);
        $within = $distance <= (float) $geofence->radius_meters;

        return [
            'within' => $within,
            'geofence' => [
                'id' => $geofence->id,
                'name' => $geofence->name,
                'center' => [
                    'latitude' => $geofence->latitude,
                    'longitude' => $geofence->longitude,
                ],
                'radius_meters' => $geofence->radius_meters,
            ],
            'distance_meters' => round($distance, 2),
            'distance_readable' => $this->distanceService->format($distance),
        ];
    }

    public function geofencesWithDistance(float $latitude, float $longitude): array
    {
        $geofences = $this->geofenceModel->where('active', true)->findAll();
        $result = [];

        foreach ($geofences as $geofence) {
            $distance = $this->distanceService->calculate($latitude, $longitude, (float) $geofence->latitude, (float) $geofence->longitude);
            $result[] = [
                'id' => $geofence->id,
                'name' => $geofence->name,
                'description' => $geofence->description,
                'center' => ['latitude' => $geofence->latitude, 'longitude' => $geofence->longitude],
                'radius_meters' => $geofence->radius_meters,
                'distance_meters' => round($distance, 2),
                'distance_readable' => $this->distanceService->format($distance),
                'within' => $distance <= (float) $geofence->radius_meters,
            ];
        }

        usort($result, static fn($a, $b) => $a['distance_meters'] <=> $b['distance_meters']);

        return $result;
    }

    public function nearestGeofence(float $latitude, float $longitude): ?array
    {
        $all = $this->geofencesWithDistance($latitude, $longitude);
        return !empty($all) ? $all[0] : null;
    }

    public function statistics(): array
    {
        $totalGeofences = $this->geofenceModel->countAllResults(false);
        $activeGeofences = $this->geofenceModel->where('active', true)->countAllResults();

        return [
            'total_geofences' => $totalGeofences,
            'active_geofences' => $activeGeofences,
            'inactive_geofences' => $totalGeofences - $activeGeofences,
            'geofencing_required' => $this->settingModel->get('require_geofence', false),
            'geolocation_required' => $this->settingModel->get('require_geolocation', false),
        ];
    }
}
