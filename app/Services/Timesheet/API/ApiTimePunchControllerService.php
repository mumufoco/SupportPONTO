<?php

namespace App\Services\Timesheet\API;

use App\Enums\PunchMethod;
use App\Enums\PunchType;
use App\Services\Timesheet\ApiTimePunchService;
use CodeIgniter\HTTP\IncomingRequest;

/**
 * Regras de validação e payloads da API de marcação de ponto.
 */
class ApiTimePunchControllerService
{
    public function __construct(
        private readonly ApiTimePunchService $apiTimePunchService = new ApiTimePunchService()
    ) {}

    public function createValidationRules(): array
    {
        return [
            'punch_type' => 'required|in_list[' . PunchType::validationList() . ']',
            'method'     => 'required|in_list[' . PunchMethod::validationList() . ']',
            'latitude'   => 'permit_empty|valid_latitude',
            'longitude'  => 'permit_empty|valid_longitude',
            'photo'      => 'permit_empty|valid_base64_image|max_file_size[5242880]',
        ];
    }

    public function registerPunch(object $employee, IncomingRequest $request): array
    {
        return $this->apiTimePunchService->registerPunch(
            $employee,
            (string) $request->getPost('punch_type'),
            (string) $request->getPost('method'),
            $request->getPost('latitude'),
            $request->getPost('longitude'),
            $request->getPost('photo')
        );
    }

    public function todayPayload(object $employee): array
    {
        return ['success' => true, 'data' => $this->apiTimePunchService->today($employee)];
    }

    public function historyPayload(object $employee, ?string $month, int $page): array
    {
        $resolvedMonth = normalize_month_reference($month);
        $history       = $this->apiTimePunchService->history($employee, $resolvedMonth, $page, 50);

        return [
            'success'    => true,
            'data'       => $history['items'],
            'pagination' => $history['pagination'],
        ];
    }

    public function summaryPayload(object $employee, ?string $month): array
    {
        $resolvedMonth = normalize_month_reference($month);
        $timesheet     = $this->apiTimePunchService->summary($employee, $resolvedMonth);

        if (!($timesheet['success'] ?? false)) {
            return ['success' => false, 'status' => 400, 'message' => $timesheet['error'] ?? 'Erro ao gerar resumo.'];
        }

        return [
            'success' => true,
            'data'    => [
                'month'         => format_month_year_br($resolvedMonth),
                'summary'       => [
                    'total_hours'    => $timesheet['summary']['total_hours'],
                    'expected_hours' => $timesheet['summary']['expected_hours'],
                    'balance'        => $timesheet['summary']['balance'],
                    'days_worked'    => $timesheet['summary']['days_worked'],
                    'total_punches'  => $timesheet['summary']['total_punches'],
                ],
                'daily_records' => array_map(static fn($record) => [
                    'date'           => format_date_br($record['date']),
                    'day_of_week'    => get_day_of_week_br($record['date'], true),
                    'hours_worked'   => $record['hours_worked'],
                    'expected_hours' => $record['expected_hours'],
                    'balance'        => $record['balance'],
                    'punch_count'    => count($record['punches']),
                ], $timesheet['daily_records']),
            ],
        ];
    }

    public function verifyPayload(object $employee, int $id): array
    {
        return $this->apiTimePunchService->verify($employee, $id);
    }

    public function geofencesPayload($latitude, $longitude): array
    {
        return ['success' => true, 'data' => $this->apiTimePunchService->geofences($latitude, $longitude)];
    }
}
