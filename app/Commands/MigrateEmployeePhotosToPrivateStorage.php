<?php

namespace App\Commands;

use App\Models\EmployeeModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Migra fotos de funcionário já enviadas do webroot público (FCPATH/store/employees/
 * photos) para o armazenamento privado (WRITEPATH/uploads/employees/photos), e
 * renomeia com um nome aleatório em vez de emp_{id}_{timestamp}.jpg.
 *
 * MED-09 (auditoria): antes do fix, toda foto de funcionário ficava acessível via URL
 * pública direta e previsível, sem nenhum controle de acesso. A correção de código
 * passa a gravar novos uploads direto em armazenamento privado e a servi-los só por
 * rota autenticada (EmployeeController::photo()) — mas fotos já enviadas antes da
 * correção continuam no local antigo até esta migração rodar.
 *
 * Uso:
 *   php spark employees:migrate-photos
 *   php spark employees:migrate-photos --dry-run
 */
class MigrateEmployeePhotosToPrivateStorage extends BaseCommand
{
    protected $group       = 'Operations';
    protected $name        = 'employees:migrate-photos';
    protected $description = 'Move fotos de funcionário do webroot público para armazenamento privado (MED-09).';
    protected $usage       = 'employees:migrate-photos [--dry-run]';
    protected $options     = [
        '--dry-run' => 'Exibe o que seria migrado sem mover arquivos nem atualizar o banco.',
    ];

    public function run(array $params): void
    {
        $dryRun = CLI::getOption('dry-run') !== null;
        $model  = new EmployeeModel();
        $targetDir = WRITEPATH . 'uploads/employees/photos/';

        if (!$dryRun && !is_dir($targetDir)) {
            mkdir($targetDir, 0750, true);
        }

        $employees = $model->where('photo_path IS NOT NULL', null, false)
            ->where('photo_path !=', '')
            ->findAll();

        CLI::write('[employees:migrate-photos] ' . ($dryRun ? '[DRY-RUN] ' : '') . date('Y-m-d H:i:s'), 'cyan');

        $migrated = 0;
        $skipped  = 0;

        foreach ($employees as $employee) {
            $photoPath = $employee->photo_path ?? '';
            if ($photoPath === '' || str_starts_with($photoPath, 'uploads/')) {
                // Já está no armazenamento novo (ou vazio) — nada a fazer.
                $skipped++;
                continue;
            }

            $sourcePath = FCPATH . ltrim($photoPath, '/');
            if (!is_file($sourcePath)) {
                CLI::write("  [skip] funcionário #{$employee->id}: arquivo de origem não encontrado ({$photoPath}).", 'dark_gray');
                $skipped++;
                continue;
            }

            $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'jpg');
            $newFilename = 'emp_' . $employee->id . '_' . bin2hex(random_bytes(16)) . '.' . $extension;
            $newRelativePath = 'uploads/employees/photos/' . $newFilename;

            CLI::write("  [migrate] funcionário #{$employee->id}: {$photoPath} -> {$newRelativePath}", 'yellow');

            if (!$dryRun) {
                $newAbsolutePath = $targetDir . $newFilename;
                if (@copy($sourcePath, $newAbsolutePath)) {
                    @chmod($newAbsolutePath, 0640);
                    $model->update((int) $employee->id, ['photo_path' => $newRelativePath]);
                    @unlink($sourcePath);
                } else {
                    CLI::write("    ERRO ao copiar arquivo do funcionário #{$employee->id}.", 'red');
                    continue;
                }
            }

            $migrated++;
        }

        CLI::write('');
        CLI::write("Fotos migradas : " . ($dryRun ? "0 (dry-run, {$migrated} seriam migradas)" : $migrated), 'green');
        CLI::write("Ignoradas      : {$skipped}", 'white');
    }
}
