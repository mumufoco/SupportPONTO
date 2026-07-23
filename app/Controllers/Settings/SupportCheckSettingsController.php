<?php

namespace App\Controllers\Settings;

use App\Libraries\SupportCheck\SupportCheckApiClient;
use App\Services\SupportCheck\SupportCheckAttendanceReportService;
use App\Services\SupportCheck\SupportCheckSyncService;
use CodeIgniter\HTTP\ResponseInterface;

class SupportCheckSettingsController extends BaseSettingsController
{
    public function index(): string
    {
        $this->requireAdminAccess();

        $client        = new SupportCheckApiClient();
        $employeeModel = new \App\Models\EmployeeModel();
        $employees     = $employeeModel->getActiveEmployees();

        return view('settings/supportcheck/index', [
            'enabled'   => $client->isEnabled(),
            'base_url'  => env('SUPPORTCHECK_BASE_URL', ''),
            'employees' => $employees,
        ]);
    }

    public function ping(): \CodeIgniter\HTTP\ResponseInterface
    {
        $this->requireAdminAccess();
        $client = new SupportCheckApiClient();
        $online = $client->ping();
        return $this->response->setJSON([
            'online'     => $online,
            'checked_at' => date('d/m/Y H:i:s'),
        ]);
    }

    public function sendReport(): ResponseInterface
    {
        $this->requireAdminAccess();

        $year       = (int) ($this->request->getPost('year') ?? 0);
        $month      = (int) ($this->request->getPost('month') ?? 0);
        $employeeId = $this->request->getPost('employee_id');

        if ($year < 2020 || $year > 2099 || $month < 1 || $month > 12) {
            return $this->jsonSettingsResponse(false, 'Ano ou mês inválido.', 422);
        }

        $client = new SupportCheckApiClient();
        if (! $client->isEnabled()) {
            return $this->jsonSettingsResponse(false, 'SupportCHECK não está habilitado. Verifique as configurações de integração.', 503);
        }

        $service = new SupportCheckAttendanceReportService($client);

        try {
            if ($employeeId !== null && $employeeId !== '') {
                $result = $service->sendMonthForEmployee((int) $employeeId, $year, $month);
                $message = 'Relatório enviado com sucesso para o colaborador.';
                $data = ['sent' => 1, 'failed' => 0, 'detail' => $result];
            } else {
                $result  = $service->sendMonthForAll($year, $month);
                $message = "Enviados: {$result['sent']} | Falhas: {$result['failed']}";
                $data    = $result;
            }

            return $this->jsonSettingsResponse(true, $message, 200, ['data' => $data]);
        } catch (\Throwable $e) {
            return $this->jsonSettingsResponse(false, 'Erro ao enviar: '.$e->getMessage(), 500);
        }
    }

    public function syncAll(): ResponseInterface
    {
        $this->requireAdminAccess();

        $client = new SupportCheckApiClient();
        if (! $client->isEnabled()) {
            return $this->jsonSettingsResponse(false, 'SupportCHECK não está habilitado.', 503);
        }

        try {
            $sync = new SupportCheckSyncService($client);
            $structureResult  = $sync->syncStructure();
            $employeesResult  = $sync->syncAllEmployees();

            return $this->jsonSettingsResponse(true, 'Sincronização concluída.', 200, [
                'data' => [
                    'structure' => $structureResult,
                    'employees' => $employeesResult,
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->jsonSettingsResponse(false, 'Erro na sincronização: '.$e->getMessage(), 500);
        }
    }
}
