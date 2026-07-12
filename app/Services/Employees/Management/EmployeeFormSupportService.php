<?php

namespace App\Services\Employees\Management;

use App\Models\RoleModel;
use App\Models\SettingModel;
use Config\Database;

/**
 * Centraliza dados auxiliares e normalização do formulário de colaboradores.
 *
 * O cadastro aceita tanto IDs vindos dos catálogos administrativos quanto texto
 * livre em ambientes ainda não totalmente configurados. Isso evita que a tela de
 * colaboradores fique inutilizável em uma instalação limpa, mantendo validação
 * dos IDs quando eles forem informados.
 */
class EmployeeFormSupportService
{
    public function __construct(
        private readonly SettingModel $settingModel,
        private readonly RoleModel $roleModel,
    ) {
    }

    public function loadFormData(): array
    {
        try {
            $options = $this->settingModel->loadSelectOptions();

            return [
                'workUnits'      => $options['workUnits'] ?? [],
                'departments'    => $options['departments'] ?? [],
                'positions'      => $options['positions'] ?? [],
                'roles'          => $options['roles'] ?? [],
                'workShifts'     => $this->loadActiveItems('work_shifts'),
                'warnings'       => $options['warnings'] ?? [],
                'workLocations'  => ['sede' => 'Sede Principal'],
                'systemSettings' => [],
            ];
        } catch (\Throwable $e) {
            log_message('error', '[EmployeeFormSupportService] loadFormData failed: ' . $e->getMessage());

            return [
                'workUnits'      => [],
                'departments'    => [],
                'positions'      => [],
                'roles'          => [],
                'workShifts'     => [],
                'warnings'       => ['Não foi possível carregar os catálogos administrativos. O cadastro aceita preenchimento manual dos campos equivalentes.'],
                'workLocations'  => ['sede' => 'Sede Principal'],
                'systemSettings' => [],
            ];
        }
    }

    public function resolveRoleInput(mixed $roleInput): ?string
    {
        if ($roleInput === null || $roleInput === '') {
            return null;
        }

        if (is_numeric($roleInput)) {
            $role = $this->roleModel->find((int) $roleInput);
            if (!$role) {
                return null;
            }

            return is_array($role) ? ($role['name'] ?? null) : ($role->name ?? null);
        }

        return is_string($roleInput) ? trim($roleInput) : null;
    }

    public function getPositionsByDepartment(?string $departmentId): array
    {
        if ($departmentId === null || trim($departmentId) === '') {
            return [];
        }

        try {
            $db = Database::connect();
            if (!$db->tableExists('positions')) {
                return [];
            }

            return $db->table('positions')
                ->where('department_id', (int) $departmentId)
                ->where('active', true)
                ->orderBy('name', 'ASC')
                ->get()
                ->getResultArray();
        } catch (\Throwable $e) {
            log_message('error', '[EmployeeFormSupportService] getPositionsByDepartment failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Normaliza aliases enviados pela interface para os nomes canônicos usados
     * pelo Model e pelas regras de validação do cadastro.
     */
    public function normalizeCatalogFields(array $postData): array
    {
        $postData = $this->normalizeAliasPair($postData, 'employee_code', 'unique_code');
        $postData = $this->normalizeAliasPair($postData, 'pis', 'pis_pasep');
        $postData = $this->normalizeAliasPair($postData, 'work_unit_id', 'work_unit');
        $postData = $this->normalizeAliasPair($postData, 'department_id', 'department');
        $postData = $this->normalizeAliasPair($postData, 'position_id', 'position');

        $catalogMappings = [
            'work_unit_id'  => ['work_units', 'work_unit'],
            'department_id' => ['departments', 'department'],
            'position_id'   => ['positions', 'position'],
        ];

        foreach ($catalogMappings as $idField => [$table, $textField]) {
            $id = $postData[$idField] ?? null;
            if ($id === null || $id === '') {
                continue;
            }

            $name = $this->resolveCatalogName($table, $id);
            if ($name !== null && $name !== '') {
                $postData[$textField] = $name;
            }
        }

        if (empty($postData['cargo']) && !empty($postData['position'])) {
            $postData['cargo'] = $postData['position'];
        }

        if (empty($postData['setor']) && !empty($postData['department'])) {
            $postData['setor'] = $postData['department'];
        }

        if (empty($postData['jornada_trabalho'])) {
            $postData['jornada_trabalho'] = $this->buildWorkJourneyLabel($postData);
        }

        if (empty($postData['expected_hours_daily']) && !empty($postData['daily_hours'])) {
            $postData['expected_hours_daily'] = $postData['daily_hours'];
        }

        if (empty($postData['daily_hours']) && !empty($postData['expected_hours_daily'])) {
            $postData['daily_hours'] = $postData['expected_hours_daily'];
        }

        if (empty($postData['work_schedule_start']) && !empty($postData['horario_entrada'])) {
            $postData['work_schedule_start'] = $postData['horario_entrada'];
        }

        if (empty($postData['work_schedule_end']) && !empty($postData['horario_saida'])) {
            $postData['work_schedule_end'] = $postData['horario_saida'];
        }

        return $postData;
    }

    public function clearCache(): void
    {
        $this->settingModel->clearCache();
    }

    public function validateBeforeSave(array $payload): array
    {
        return $this->settingModel->validateCatalogIds($payload);
    }

    private function normalizeAliasPair(array $postData, string $alias, string $canonical): array
    {
        if (!empty($postData[$alias]) && empty($postData[$canonical])) {
            $postData[$canonical] = $postData[$alias];
        }

        if (!empty($postData[$canonical]) && empty($postData[$alias])) {
            $postData[$alias] = $postData[$canonical];
        }

        return $postData;
    }

    private function resolveCatalogName(string $table, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = trim((string) $value);
        if (!ctype_digit($value)) {
            return $value;
        }

        try {
            $db = Database::connect();
            if (!$db->tableExists($table)) {
                return $value;
            }

            $row = $db->table($table)
                ->select('name')
                ->where('id', (int) $value)
                ->where('active', true)
                ->get()
                ->getRowArray();

            return $row['name'] ?? $value;
        } catch (\Throwable $e) {
            log_message('warning', "[EmployeeFormSupportService] Catalog {$table} lookup skipped: " . $e->getMessage());
            return $value;
        }
    }

    private function loadActiveItems(string $table): array
    {
        try {
            $db = Database::connect();
            if (!$db->tableExists($table)) {
                return [];
            }

            return $db->table($table)
                ->where('active', true)
                ->orderBy('name', 'ASC')
                ->get()
                ->getResultArray();
        } catch (\Throwable $e) {
            log_message('warning', "[EmployeeFormSupportService] {$table} options unavailable: " . $e->getMessage());
            return [];
        }
    }

    private function buildWorkJourneyLabel(array $postData): ?string
    {
        $start = $postData['horario_entrada'] ?? $postData['work_schedule_start'] ?? null;
        $end = $postData['horario_saida'] ?? $postData['work_schedule_end'] ?? null;

        if ($start && $end) {
            return trim((string) $start) . ' às ' . trim((string) $end);
        }

        return null;
    }
}
