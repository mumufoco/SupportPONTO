<?php

namespace App\Services\Biometric;

use App\Models\AuditModel;
use App\Models\BiometricTemplateModel;
use App\Models\EmployeeModel;

class BiometricDataPurgeService
{
    private BiometricTemplateModel $biometricModel;
    private EmployeeModel $employeeModel;
    private DeepFaceService $deepFaceService;
    private AuditModel $auditModel;

    public function __construct()
    {
        $this->biometricModel = new BiometricTemplateModel();
        $this->employeeModel = new EmployeeModel();
        $this->deepFaceService = new DeepFaceService();
        $this->auditModel = new AuditModel();
    }

    public function purgeEmployee(int $employeeId, array $types = ['face', 'fingerprint'], string $reason = 'manual'): array
    {
        $types = array_values(array_intersect($types, ['face', 'fingerprint']));
        if ($types === []) {
            return ['success' => false, 'message' => 'Nenhum tipo biométrico válido informado.'];
        }

        $templates = $this->biometricModel
            ->where('employee_id', $employeeId)
            ->whereIn('biometric_type', $types)
            ->findAll();

        $deletedFiles = 0;
        $deletedRecords = 0;
        $deepFaceSyncErrors = [];

        foreach ($templates as $template) {
            if (($template->biometric_type ?? null) === 'face' && !empty($template->image_hash)) {
                $externalResult = $this->deepFaceService->deleteFaceByHash((string) $template->image_hash);
                if (($externalResult['success'] ?? false) !== true) {
                    $deepFaceSyncErrors[] = [
                        'template_id' => (int) $template->id,
                        'image_hash' => (string) $template->image_hash,
                        'error' => $externalResult['error'] ?? 'Falha ao sincronizar exclusão externa.',
                    ];
                }
            }

            if (!empty($template->file_path) && is_string($template->file_path) && is_file($template->file_path)) {
                if (@unlink($template->file_path)) {
                    $deletedFiles++;
                }
            }
        }

        if ($templates !== []) {
            $this->biometricModel
                ->where('employee_id', $employeeId)
                ->whereIn('biometric_type', $types)
                ->delete();
            $deletedRecords = count($templates);
        }

        $update = [];
        if (in_array('face', $types, true)) {
            $update['has_face_biometric'] = false;
            $update['face_encoding'] = null;
            $this->purgeFaceDirectory($employeeId);
        }

        if (in_array('fingerprint', $types, true)) {
            $update['has_fingerprint_biometric'] = false;
        }

        if ($update !== []) {
            $this->employeeModel->update($employeeId, $update);
        }

        $this->auditModel->log(
            $employeeId,
            'BIOMETRIC_DATA_PURGED',
            'biometric_templates',
            $employeeId,
            null,
            [
                'types' => $types,
                'reason' => $reason,
                'deleted_files' => $deletedFiles,
                'deleted_records' => $deletedRecords,
                'deepface_sync_errors' => count($deepFaceSyncErrors),
            ],
            'Expurgo biométrico executado por política LGPD/consentimento.',
            'warning'
        );

        return [
            'success' => true,
            'message' => 'Dados biométricos removidos com sucesso.',
            'reason' => $reason,
            'deleted_files' => $deletedFiles,
            'deleted_records' => $deletedRecords,
            'types' => $types,
            'deepface_sync_errors' => $deepFaceSyncErrors,
        ];
    }

    private function purgeFaceDirectory(int $employeeId): void
    {
        $paths = [
            rtrim(WRITEPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'biometric' . DIRECTORY_SEPARATOR . 'faces' . DIRECTORY_SEPARATOR . $employeeId,
            rtrim(WRITEPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'faces' . DIRECTORY_SEPARATOR . $employeeId,
        ];

        foreach ($paths as $path) {
            if (!is_dir($path)) {
                continue;
            }

            foreach (glob($path . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }

            @rmdir($path);
        }
    }
}
