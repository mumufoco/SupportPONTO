<?php

namespace App\Controllers\API;

use App\Services\Timesheet\API\ApiTimePunchControllerService;
use CodeIgniter\HTTP\ResponseInterface;

class TimePunchController extends BaseApiController
{
    protected $modelName = 'App\\Models\\TimePunchModel';
    protected $format = 'json';

    public function __construct(private readonly ApiTimePunchControllerService $controllerService = new ApiTimePunchControllerService())
    {
        parent::__construct();
        helper(['security', 'format', 'datetime']);
    }

    public function create()
    {
        return $this->withAuthenticatedEmployee(function (object $employee) {
            if (! $this->validate($this->controllerService->createValidationRules())) {
                return $this->fail($this->validator->getErrors(), 400);
            }

            $result = $this->controllerService->registerPunch($employee, $this->request);
            if (! ($result['success'] ?? false)) {
                return $this->respond($result, (int) ($result['status'] ?? 400));
            }

            return $this->respondCreated($result);
        });
    }

    public function today()
    {
        return $this->withAuthenticatedEmployee(fn (object $employee) => $this->respond($this->controllerService->todayPayload($employee), 200));
    }

    public function history()
    {
        return $this->withAuthenticatedEmployee(function (object $employee) {
            $payload = $this->controllerService->historyPayload(
                $employee,
                normalize_month_reference($this->request->getGet('month')),
                (int) ($this->request->getGet('page') ?: 1)
            );

            return $this->respond($payload, 200);
        });
    }

    public function summary()
    {
        return $this->withAuthenticatedEmployee(function (object $employee) {
            $result = $this->controllerService->summaryPayload($employee, normalize_month_reference($this->request->getGet('month')));
            if (! ($result['success'] ?? false)) {
                return $this->fail($result['message'] ?? 'Erro ao gerar resumo.', (int) ($result['status'] ?? 400));
            }

            return $this->respond($result, 200);
        });
    }

    public function verify($id = null)
    {
        return $this->withAuthenticatedEmployee(function (object $employee) use ($id) {
            $result = $this->controllerService->verifyPayload($employee, (int) $id);
            if (! ($result['success'] ?? false)) {
                return $this->fail($result['message'] ?? 'Erro ao verificar registro.', (int) ($result['status'] ?? 400));
            }

            return $this->respond([
                'success' => true,
                'data' => $result['data'],
            ], 200);
        });
    }

    public function geofences()
    {
        return $this->withAuthenticatedEmployee(function () {
            $payload = $this->controllerService->geofencesPayload(
                $this->request->getGet('latitude'),
                $this->request->getGet('longitude')
            );

            return $this->respond($payload, 200);
        });
    }

    private function withAuthenticatedEmployee(callable $callback): ResponseInterface
    {
        $employee = $this->requireAuth();
        if ($employee instanceof ResponseInterface) {
            return $employee;
        }

        return $callback($employee);
    }
}
