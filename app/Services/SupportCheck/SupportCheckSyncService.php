<?php

namespace App\Services\SupportCheck;

use App\Libraries\SupportCheck\SupportCheckApiClient;
use App\Models\EmployeeModel;
use App\Models\SettingModel;

/**
 * Sincroniza dados estruturais e de funcionários do SupportPONTO para o SupportCHECK.
 *
 * Fase 1 + Fase 2 da integração.
 */
class SupportCheckSyncService
{
    public function __construct(
        private readonly SupportCheckApiClient $client = new SupportCheckApiClient(),
        private readonly EmployeeModel $employeeModel  = new EmployeeModel(),
        private readonly SettingModel $settingModel     = new SettingModel(),
    ) {
    }

    // -------------------------------------------------------------------------
    // Estrutura organizacional
    // -------------------------------------------------------------------------

    public function syncStructure(): array
    {
        $results = [];

        $results['company']      = $this->syncCompany();
        $results['positions']    = $this->syncPositions();
        $results['departments']  = $this->syncDepartments();
        $results['work_units']   = $this->syncWorkUnits();

        return $results;
    }

    public function syncCompany(): array
    {
        $companyName    = $this->settingModel->get('company_name') ?? 'SupportPONTO';
        $companyCnpj    = $this->settingModel->get('company_cnpj') ?? null;
        $companyAddress = $this->settingModel->get('company_address') ?? null;

        return $this->client->syncCompany([
            'external_id' => '1',
            'name'        => $companyName,
            'document'    => $companyCnpj,
        ]);
    }

    public function syncPositions(): array
    {
        $result = $this->employeeModel->db
            ->table('positions')
            ->select('id, name')
            ->where('active', true)
            ->get();

        $rows  = $result ? $result->getResultArray() : [];
        $items = array_map(fn ($r) => [
            'external_id' => (string) $r['id'],
            'name'        => $r['name'],
        ], $rows);

        if (empty($items)) {
            return ['message' => 'Nenhum cargo ativo para sincronizar.'];
        }

        return $this->client->syncPositions(['items' => $items]);
    }

    public function syncDepartments(): array
    {
        $result = $this->employeeModel->db
            ->table('departments')
            ->select('id, name')
            ->where('active', true)
            ->get();

        $rows  = $result ? $result->getResultArray() : [];
        $items = array_map(fn ($r) => [
            'external_id' => (string) $r['id'],
            'name'        => $r['name'],
        ], $rows);

        if (empty($items)) {
            return ['message' => 'Nenhum departamento ativo para sincronizar.'];
        }

        return $this->client->syncDepartments(['items' => $items]);
    }

    public function syncWorkUnits(): array
    {
        $result = $this->employeeModel->db
            ->table('work_units')
            ->select('id, name')
            ->where('active', true)
            ->get();

        $rows  = $result ? $result->getResultArray() : [];
        $items = array_map(fn ($r) => [
            'external_id' => (string) $r['id'],
            'name'        => $r['name'],
        ], $rows);

        if (empty($items)) {
            return ['message' => 'Nenhuma unidade ativa para sincronizar.'];
        }

        return $this->client->syncWorkUnits(['items' => $items]);
    }

    // -------------------------------------------------------------------------
    // Funcionários
    // -------------------------------------------------------------------------

    /**
     * Sincroniza todos os funcionários ativos.
     *
     * @return array{synced: int, failed: int, errors: array<string>}
     */
    public function syncAllEmployees(): array
    {
        $employees = $this->employeeModel
            ->where('active', true)
            ->findAll();

        $synced = 0;
        $failed = 0;
        $errors = [];

        foreach ($employees as $employee) {
            try {
                $this->syncEmployee($employee);
                $synced++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = 'Funcionário #' . $employee->id . ' (' . $employee->name . '): ' . $e->getMessage();
            }
        }

        return compact('synced', 'failed', 'errors');
    }

    public function syncEmployee(object $employee): array
    {
        return $this->client->syncEmployee([
            'external_employee_id' => (string) $employee->id,
            'name'                 => $employee->name,
            'cpf'                  => $employee->cpf ?? null,
            'email'                => $employee->email ?? null,
            'phone'                => $employee->phone ?? null,
            'position_id'          => $employee->position_id ? (string) $employee->position_id : null,
            'position_name'        => $employee->position ?? null,
            'department_id'        => $employee->department_id ? (string) $employee->department_id : null,
            'department_name'      => $employee->department ?? null,
            'work_unit_id'         => $employee->work_unit_id ? (string) $employee->work_unit_id : null,
            'work_unit_name'       => $employee->work_unit ?? null,
            'admission_date'       => $employee->admission_date ?? null,
            'status'               => $employee->active ? 'active' : 'inactive',
        ]);
    }
}
