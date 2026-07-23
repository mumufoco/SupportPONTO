<?php

namespace App\Services\LGPD;

use App\Models\AuditModel;
use App\Models\BiometricRecordModel;
use App\Models\EmployeeModel;
use App\Models\UserConsentModel;
use App\Models\WarningModel;

class DataExportService
{
    protected EmployeeModel $employeeModel;
    protected AuditModel $auditModel;
    protected DataExportCollectorService $collectorService;

    protected string $exportPath;
    protected int $expirationHours = 48;

    public function __construct()
    {
        $this->employeeModel = new EmployeeModel();
        $this->auditModel = new AuditModel();
        $this->collectorService = new DataExportCollectorService(
            new UserConsentModel(),
            new BiometricRecordModel(),
            new WarningModel(),
            $this->auditModel,
        );

        $this->exportPath = WRITEPATH . 'exports/lgpd/';
        if (!is_dir($this->exportPath)) {
            mkdir($this->exportPath, 0755, true);
        }
    }

    public function exportUserData(int $employeeId, ?string $requestedBy = null): array
    {
        try {
            $employee = $this->employeeModel->find($employeeId);
            if (!$employee) {
                return ['success' => false, 'message' => 'Colaborador não encontrado', 'export_id' => null];
            }

            $exportId = $this->generateExportId($employeeId);
            $exportDir = $this->exportPath . $exportId . '/';
            mkdir($exportDir, 0755, true);

            $data = [
                '@context' => 'https://schema.org',
                '@type' => 'Person',
                'identifier' => (string)$employee->id,
                'name' => $employee->name,
                'email' => $employee->email,
                'telephone' => $employee->phone ?? null,
                'jobTitle' => $employee->position ?? null,
                'worksFor' => ['@type' => 'Organization', 'name' => env('COMPANY_NAME', 'Empresa')],
                'exportDate' => date('c'),
                'exportPurpose' => 'LGPD Art. 19 - Portabilidade de Dados',
                'personalData' => $this->collectorService->collectPersonalData($employee),
                'consents' => $this->collectorService->collectConsents($employeeId),
                'attendanceRecords' => $this->collectorService->collectAttendance($employeeId),
                'biometricData' => $this->collectorService->collectBiometricData($employeeId),
                'dataInventory' => $this->collectorService->collectDataInventory(),
                'retentionPolicies' => $this->collectorService->collectRetentionPolicies(),
                'vacations' => $this->collectorService->collectVacations($employeeId),
                'warnings' => $this->collectorService->collectWarnings($employeeId),
                'auditLog' => $this->collectorService->collectAuditLog($employeeId),
            ];

            file_put_contents($exportDir . 'data.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->createReadme($exportDir, $employee);

            $password = $this->generatePassword();
            $zipFile = $this->exportPath . $exportId . '.zip';

            if (!$this->createEncryptedZip($exportDir, $zipFile, $password)) {
                return ['success' => false, 'message' => 'Erro ao criar arquivo ZIP', 'export_id' => null];
            }

            $this->deleteDirectory($exportDir);
            $this->storeExportMetadata($exportId, $employeeId, $requestedBy);
            $this->sendDownloadEmail($employee, $exportId);
            $this->sendPasswordEmail($employee, $password);

            $this->auditModel->log(
                $employeeId,
                'EXPORT',
                'employees',
                $employeeId,
                null,
                ['export_id' => $exportId, 'requested_by' => $requestedBy, 'file_size' => filesize($zipFile)],
                'Exportação de dados LGPD solicitada por ' . ($requestedBy ?? 'próprio colaborador'),
                'info'
            );

            return ['success' => true, 'message' => 'Exportação realizada com sucesso. Verifique seu e-mail.', 'export_id' => $exportId];
        } catch (\Exception $e) {
            log_message('error', 'DataExportService::exportUserData() error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao exportar dados. A ocorrência foi registrada para análise.', 'export_id' => null];
        }
    }

    protected function createReadme(string $dir, object $employee): void
    {
        $content = "# Exportação de Dados Pessoais - LGPD\n\n" .
            "## Informações do Titular\n" .
            "- **Nome:** {$employee->name}\n" .
            "- **E-mail:** {$employee->email}\n" .
            '- **Data da Exportação:** ' . date('d/m/Y H:i:s') . "\n\n" .
            "## Finalidade\nEsta exportação foi realizada em conformidade com a LGPD (Art. 19).\n\n" .
            "## Conteúdo\n- Dados pessoais\n- Consentimentos\n- Registros de ponto\n- Metadados biométricos\n- Férias\n- Advertências\n- Auditoria\n- Inventário de tratamento\n- Políticas de retenção\n\n" .
            "## Formato\nJSON-LD (schema.org).\n\n" .
            "## Validade\nDisponível por {$this->expirationHours} horas.\n\n" .
            "## Dúvidas\nDPO: " . env('DPO_EMAIL', 'dpo@empresa.com') . "\n";

        file_put_contents($dir . 'README.txt', $content);
    }

    protected function createEncryptedZip(string $sourceDir, string $zipFile, string $password): bool
    {
        try {
            $zip = new \ZipArchive();
            if ($zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                return false;
            }

            $zip->setPassword($password);
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourceDir), \RecursiveIteratorIterator::LEAVES_ONLY);

            foreach ($files as $file) {
                if ($file->isDir()) {
                    continue;
                }

                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($sourceDir));
                $zip->addFile($filePath, $relativePath);
                $zip->setEncryptionName($relativePath, \ZipArchive::EM_AES_256);
            }

            $zip->close();
            return true;
        } catch (\Exception $e) {
            log_message('error', 'Failed to create encrypted ZIP: ' . $e->getMessage());
            return false;
        }
    }

    protected function generateExportId(int $employeeId): string
    {
        return 'export_' . $employeeId . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(8));
    }

    protected function generatePassword(int $length = 16): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $password;
    }

    protected function storeExportMetadata(string $exportId, int $employeeId, ?string $requestedBy): void
    {
        \Config\Database::connect()->table('data_exports')->insert([
            'export_id' => $exportId,
            'employee_id' => $employeeId,
            'requested_by' => $requestedBy,
            'status' => 'completed',
            'expires_at' => date('Y-m-d H:i:s', strtotime("+{$this->expirationHours} hours")),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    protected function sendDownloadEmail(object $employee, string $exportId): void
    {
        $email = \Config\Services::email();
        $downloadUrl = base_url("lgpd/download-export/{$exportId}");
        $expiresAt = date('d/m/Y H:i', strtotime("+{$this->expirationHours} hours"));

        $email->setTo($employee->email);
        $email->setSubject('[LGPD] Sua exportação de dados está pronta');
        $email->setMessage("<h2>Exportação de Dados - LGPD</h2><p>Olá, " . htmlspecialchars((string) $employee->name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "!</p><p>Download: <a href=\"{$downloadUrl}\">{$downloadUrl}</a></p><p>Expira em {$expiresAt}</p>");

        try {
            $email->send();
        } catch (\Exception $e) {
            log_message('error', 'Failed to send download email: ' . $e->getMessage());
        }
    }

    protected function sendPasswordEmail(object $employee, string $password): void
    {
        $email = \Config\Services::email();
        $email->setTo($employee->email);
        $email->setSubject('[LGPD] Senha para sua exportação de dados');
        $email->setMessage("<h2>Senha de Acesso - Exportação LGPD</h2><p>Olá, " . htmlspecialchars((string) $employee->name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "!</p><p><strong>{$password}</strong></p>");

        try {
            $email->send();
        } catch (\Exception $e) {
            log_message('error', 'Failed to send password email: ' . $e->getMessage());
        }
    }

    protected function deleteDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        foreach (array_diff(scandir($dir), ['.', '..']) as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }

        return rmdir($dir);
    }

    public function cleanupExpiredExports(): int
    {
        $db = \Config\Database::connect();
        $expired = $db->table('data_exports')
            ->where('expires_at <', date('Y-m-d H:i:s'))
            ->where('status', 'completed')
            ->get()
            ->getResult();

        $deleted = 0;
        foreach ($expired as $export) {
            $zipFile = $this->exportPath . $export->export_id . '.zip';
            if (file_exists($zipFile)) {
                unlink($zipFile);
                $deleted++;
            }

            $db->table('data_exports')->where('id', $export->id)->update(['status' => 'expired']);
        }

        return $deleted;
    }
}
