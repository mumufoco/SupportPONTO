<?php

namespace App\Services\Timesheet;

use App\DTO\Timesheet\PunchRegistrationCommand;
use App\Models\AuditModel;
use App\Models\EmployeeModel;
use App\Models\GeofenceModel;
use App\Models\TimePunchModel;
use App\Services\AuthorizationService;
use App\Services\GeolocationService;
use App\Services\TimesheetService;

class ApiTimePunchService
{
    public function __construct(
        private readonly TimePunchModel $timePunchModel = new TimePunchModel(),
        private readonly EmployeeModel $employeeModel = new EmployeeModel(),
        private readonly GeofenceModel $geofenceModel = new GeofenceModel(),
        private readonly AuditModel $auditModel = new AuditModel(),
        private readonly TimesheetService $timesheetService = new TimesheetService(),
        private readonly AuthorizationService $authorizationService = new AuthorizationService(),
        private readonly GeolocationService $geolocationService = new GeolocationService(),
        private readonly TimesheetPunchRegistrationService $registrationService = new TimesheetPunchRegistrationService(),
    ) {
    }

    public function registerPunch(object $employee, string $punchType, string $method, $latitude, $longitude, $photo): array
    {
        $command = new PunchRegistrationCommand(
            employeeId: (int) $employee->id,
            punchType: $punchType,
            method: $method,
            latitude: $latitude,
            longitude: $longitude,
            ipAddress: function_exists('get_client_ip') ? get_client_ip() : null,
            userAgent: function_exists('get_user_agent') ? get_user_agent() : null,
            photo: $photo ? (string) $photo : null,
            source: 'api',
        );

        return $this->registrationService->register($command);
    }

    public function today(object $employee): array
    {
        $today = date('Y-m-d');
        [$todayStartAt, $tomorrowStartAt] = $this->timePunchModel->getDayBounds($today);
        $punches = $this->timePunchModel
            ->where('employee_id', $employee->id)
            ->where('punch_time >=', $todayStartAt)
            ->where('punch_time <', $tomorrowStartAt)
            ->orderBy('punch_time', 'ASC')
            ->findAll();

        $calculation = $this->timesheetService->calculateDailyHours($punches);

        return [
            'date' => format_date_br($today),
            'punches' => array_map(static function ($punch) {
                return [
                    'id' => $punch->id,
                    'nsr' => $punch->nsr,
                    'time' => format_time($punch->punch_time),
                    'punch_type' => $punch->punch_type,
                    'method' => $punch->method,
                    'latitude' => $punch->latitude,
                    'longitude' => $punch->longitude,
                ];
            }, $punches),
            'summary' => [
                'total_hours' => $calculation['total_hours'],
                'work_hours' => $calculation['work_hours'],
                'break_hours' => $calculation['break_hours'],
                'total_punches' => count($punches),
            ],
        ];
    }

    public function history(object $employee, string $month, int $page, int $perPage = 50): array
    {
        $monthRange = get_month_datetime_range($month);

        $punches = $this->timePunchModel
            ->where('employee_id', $employee->id)
            ->where('punch_time >=', $monthRange['start_at'])
            ->where('punch_time <', $monthRange['end_at'])
            ->orderBy('punch_time', 'DESC')
            ->paginate($perPage, 'default', $page);

        $pager = $this->timePunchModel->pager;

        return [
            'items' => array_map(static function ($punch) {
                return [
                    'id' => $punch->id,
                    'nsr' => $punch->nsr,
                    'date' => format_date_br($punch->punch_time),
                    'time' => format_time($punch->punch_time),
                    'punch_type' => $punch->punch_type,
                    'method' => $punch->method,
                    'latitude' => $punch->latitude,
                    'longitude' => $punch->longitude,
                ];
            }, $punches),
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $pager->getTotal(),
                'last_page' => $pager->getPageCount(),
            ],
        ];
    }

    public function summary(object $employee, string $month): array
    {
        return $this->timesheetService->generateMonthlyTimesheet((int) $employee->id, $month);
    }

    public function verify(object $employee, int $id): array
    {
        $punch = $this->timePunchModel->find($id);
        if (!$punch) {
            return ['success' => false, 'status' => 404, 'message' => 'Registro não encontrado.'];
        }

        $targetEmployee = $this->employeeModel->find((int) $punch->employee_id);
        if (!$targetEmployee || !$this->authorizationService->canAccessEmployee($employee, $targetEmployee, true)) {
            return ['success' => false, 'status' => 403, 'message' => 'Acesso negado.'];
        }

        return [
            'success' => true,
            'status' => 200,
            'data' => [
                'punch_id' => $punch->id,
                'nsr' => $punch->nsr,
                'hash' => $punch->hash,
                'is_valid' => $this->timePunchModel->verifyHash($punch),
                'punch_time' => format_datetime_br($punch->punch_time),
            ],
        ];
    }

    public function geofences($latitude, $longitude): array
    {
        $geofences = $this->geofenceModel->where('active', true)->findAll();

        $geofencesData = array_map(function ($geofence) use ($latitude, $longitude) {
            $data = [
                'id' => $geofence->id,
                'name' => $geofence->name,
                'description' => $geofence->description,
                'latitude' => $geofence->latitude,
                'longitude' => $geofence->longitude,
                'radius_meters' => $geofence->radius_meters,
            ];

            if ($latitude && $longitude) {
                $distance = $this->geolocationService->calculateDistance((float) $latitude, (float) $longitude, (float) $geofence->latitude, (float) $geofence->longitude);
                $data['distance_meters'] = round($distance, 2);
                $data['distance_readable'] = format_distance($distance);
                $data['within'] = $distance <= $geofence->radius_meters;
            }

            return $data;
        }, $geofences);

        if ($latitude && $longitude) {
            usort($geofencesData, static fn($a, $b) => $a['distance_meters'] <=> $b['distance_meters']);
        }

        return $geofencesData;
    }
}
