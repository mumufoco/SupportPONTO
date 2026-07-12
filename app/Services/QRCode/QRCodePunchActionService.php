<?php

namespace App\Services\QRCode;

use App\Models\GeofenceModel;
use App\Models\TimePunchModel;

class QRCodePunchActionService
{
    protected TimePunchModel $timePunchModel;

    public function __construct()
    {
        $this->timePunchModel = new TimePunchModel();
    }

    public function resolvePunchType(int $employeeId, ?string $requestedPunchType): string
    {
        $normalizedRequestedPunchType = $requestedPunchType ? $this->normalizePunchType($requestedPunchType) : null;
        $validPunchTypes = ['entrada', 'saida', 'intervalo_inicio', 'intervalo_fim'];

        if ($normalizedRequestedPunchType && in_array($normalizedRequestedPunchType, $validPunchTypes, true)) {
            return $normalizedRequestedPunchType;
        }

        return $this->determinePunchType($employeeId);
    }

    public function validateGeofenceWithCoords($latitude, $longitude): array
    {
        if (!$latitude || !$longitude) {
            return ['valid' => true];
        }

        $geofenceModel = new GeofenceModel();
        $geofences = $geofenceModel->where('active', true)->findAll();

        if (empty($geofences)) {
            return ['valid' => true];
        }

        foreach ($geofences as $geofence) {
            $distance = $this->calculateDistance(
                $latitude,
                $longitude,
                $geofence->latitude,
                $geofence->longitude
            );

            if ($distance <= $geofence->radius) {
                return ['valid' => true, 'geofence' => $geofence->name];
            }
        }

        return [
            'valid' => false,
            'error' => 'Você não está em uma área autorizada para registro de ponto',
        ];
    }

    public function punchTypeLabel(string $punchType): string
    {
        $labels = [
            'entrada' => 'Entrada',
            'intervalo_inicio' => 'Início do Intervalo',
            'intervalo_fim' => 'Fim do Intervalo',
            'saida' => 'Saída',
            'almoco_saida' => 'Início do Intervalo',
            'saida_intervalo' => 'Início do Intervalo',
            'inicio_intervalo' => 'Início do Intervalo',
            'almoco_retorno' => 'Fim do Intervalo',
            'volta_intervalo' => 'Fim do Intervalo',
            'fim_intervalo' => 'Fim do Intervalo',
        ];

        $normalizedPunchType = $this->normalizePunchType($punchType);

        return $labels[$normalizedPunchType] ?? $labels[$punchType] ?? $normalizedPunchType;
    }

    protected function determinePunchType(int $employeeId): string
    {
        [$todayStartAt, $tomorrowStartAt] = $this->timePunchModel->getDayBounds(date('Y-m-d'));

        $lastPunch = $this->timePunchModel
            ->where('employee_id', $employeeId)
            ->where('punch_time >=', $todayStartAt)
            ->where('punch_time <', $tomorrowStartAt)
            ->orderBy('punch_time', 'DESC')
            ->first();

        if (!$lastPunch) {
            return 'entrada';
        }

        $normalizedType = $this->normalizePunchType($lastPunch->punch_type);
        $sequence = ['entrada', 'intervalo_inicio', 'intervalo_fim', 'saida'];
        $currentIndex = array_search($normalizedType, $sequence, true);

        if ($currentIndex === false || $currentIndex >= count($sequence) - 1) {
            return 'entrada';
        }

        return $sequence[$currentIndex + 1];
    }

    protected function normalizePunchType(string $punchType): string
    {
        $legacyMap = [
            'almoco_saida' => 'intervalo_inicio',
            'saida_intervalo' => 'intervalo_inicio',
            'inicio_intervalo' => 'intervalo_inicio',
            'intervalo-inicio' => 'intervalo_inicio',
            'almoco_retorno' => 'intervalo_fim',
            'volta_intervalo' => 'intervalo_fim',
            'fim_intervalo' => 'intervalo_fim',
            'intervalo-fim' => 'intervalo_fim',
        ];

        return $legacyMap[$punchType] ?? $punchType;
    }

    protected function calculateDistance($lat1, $lon1, $lat2, $lon2): float
    {
        $earthRadius = 6371000;

        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLon = deg2rad($lon2 - $lon1);

        $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
             cos($lat1Rad) * cos($lat2Rad) *
             sin($deltaLon / 2) * sin($deltaLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
