<?php

namespace App\Models\Concerns;

trait SettingModelCatalogTrait
{
    public function loadSelectOptions(): array
    {
        $cached = cache()->get('config_options');

        if ($cached) {
            return $cached;
        }

        $db      = \Config\Database::connect();
        $options = [
            'workUnits'   => $this->loadActiveItems($db, 'work_units'),
            'departments' => $this->loadActiveItems($db, 'departments'),
            'positions'   => $this->loadActiveItems($db, 'positions'),
            'roles'       => $this->loadActiveItems($db, 'roles'),
            'warnings'    => $this->loadCatalogWarnings($db),
        ];

        cache()->save('config_options', $options, 3600);

        return $options;
    }
    public function validateCatalogIds(array $data): array
    {
        $db     = \Config\Database::connect();
        $errors = [];

        $checks = [
            'work_unit_id'  => ['work_units',   'Unidade de Trabalho'],
            'department_id' => ['departments',  'Departamento'],
            'position_id'   => ['positions',    'Cargo'],
            'role_id'       => ['roles',        'Nível de Acesso'],
        ];

        foreach ($checks as $field => [$table, $label]) {
            if (!empty($data[$field])) {
                $exists = $db->table($table)
                             ->where('id', (int) $data[$field])
                             ->where('active', true)
                             ->countAllResults() > 0;
                if (!$exists) {
                    $errors[] = "{$label} inválido(a) ou inativo(a).";
                }
            }
        }

        return $errors;
    }
    private function loadActiveItems(\CodeIgniter\Database\BaseConnection $db, string $table): array
    {
        try {
            return $db->table($table)
                      ->where('active', true)
                      ->orderBy('name', 'ASC')
                      ->get()
                      ->getResultArray();
        } catch (\Throwable $e) {
            log_message('error', "[SettingModel] Erro ao carregar catálogo {$table}: " . $e->getMessage());
            return [];
        }
    }
    private function loadCatalogWarnings(\CodeIgniter\Database\BaseConnection $db): array
    {
        $warnings = [];
        $catalogs = [
            'work_units'  => 'Unidade de Trabalho',
            'departments' => 'Departamento',
            'positions'   => 'Cargo',
            'roles'       => 'Nível de Acesso',
        ];

        foreach ($catalogs as $table => $label) {
            try {
                if ($db->tableExists($table) &&
                    $db->table($table)->where('active', true)->countAllResults() === 0) {
                    $warnings[] = "Nenhum(a) {$label} configurado(a).";
                }
            } catch (\Throwable $e) {
                // Tabela pode não existir em ambiente de testes — ignorar silenciosamente
            }
        }

        return $warnings;
    }

}
