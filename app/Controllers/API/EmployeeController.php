<?php

namespace App\Controllers\API;

use App\Services\Employees\API\EmployeeApiService;
use CodeIgniter\HTTP\ResponseInterface;

class EmployeeController extends BaseApiController
{
    protected $modelName = 'App\Models\EmployeeModel';
    protected $format = 'json';

    public function __construct(private readonly EmployeeApiService $employeeApiService = new EmployeeApiService())
    {
        parent::__construct();
        helper(['format', 'datetime']);
    }

    public function profile()
    {
        return $this->withAuthenticatedEmployee(fn (object $employee) => $this->respond([
            'success' => true,
            'data' => $this->employeeApiService->profileData($employee),
        ], 200));
    }

    public function balance()
    {
        return $this->withAuthenticatedEmployee(fn (object $employee) => $this->respond([
            'success' => true,
            'data' => $this->employeeApiService->balanceData($employee),
        ], 200));
    }

    public function statistics()
    {
        return $this->withAuthenticatedEmployee(function (object $employee) {
            $month = normalize_month_reference($this->request->getGet('month'));

            return $this->respond([
                'success' => true,
                'data' => $this->employeeApiService->statisticsData($employee, $month),
            ], 200);
        });
    }

    public function updateProfile()
    {
        return $this->withAuthenticatedEmployee(function (object $employee) {
            if (! $this->validate(['phone' => 'permit_empty|valid_phone_br'])) {
                return $this->fail($this->validator->getErrors(), 400);
            }

            $updated = $this->employeeApiService->updateProfilePhone((int) $employee->id, $this->request->getPost('phone'));
            if (! $updated) {
                return $this->fail('Erro ao atualizar perfil.', 500);
            }

            return $this->respond([
                'success' => true,
                'message' => 'Perfil atualizado com sucesso.',
            ], 200);
        });
    }

    public function team()
    {
        return $this->withAuthenticatedEmployee(function (object $employee) {
            $result = $this->employeeApiService->teamData($employee);
            if (! ($result['success'] ?? false)) {
                return $this->fail($result['message'] ?? 'Acesso negado.', (int) ($result['status'] ?? 403));
            }

            return $this->respond([
                'success' => true,
                'data' => $result['data'],
            ], 200);
        });
    }

    public function byCode($code = null)
    {
        return $this->withAuthenticatedEmployee(function (object $employee) use ($code) {
            $result = $this->employeeApiService->employeeByCode($employee, $code);
            if (! ($result['success'] ?? false)) {
                return $this->fail($result['message'] ?? 'Erro ao consultar funcionário.', (int) ($result['status'] ?? 400));
            }

            return $this->respond([
                'success' => true,
                'data' => $result['data'],
            ], 200);
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
