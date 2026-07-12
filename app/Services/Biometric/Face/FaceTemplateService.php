<?php

namespace App\Services\Biometric\Face;

use App\Models\BiometricTemplateModel;
use App\Models\EmployeeModel;

class FaceTemplateService
{
    private BiometricTemplateModel $biometricModel;
    private EmployeeModel $employeeModel;

    public function __construct(BiometricTemplateModel $biometricModel, EmployeeModel $employeeModel)
    {
        $this->biometricModel = $biometricModel;
        $this->employeeModel = $employeeModel;
    }

    public function activeTemplate(int $employeeId): mixed
    {
        return $this->biometricModel
            ->where('employee_id', $employeeId)
            ->where('biometric_type', 'face')
            ->where('active', true)
            ->first();
    }

    public function persistEnrollment(int $employeeId, array $descriptor, string $filePath, string $imageHash, float $quality, bool $force = false): array
    {
        $existingTemplate = $this->activeTemplate($employeeId);

        if ($existingTemplate) {
            if (! $force) {
                @unlink($filePath);
                return ['success' => false, 'error' => 'Cadastro facial já existente. Exclua o cadastro atual ou solicite recadastro forçado.'];
            }

            if ($existingTemplate->file_path && file_exists($existingTemplate->file_path)) {
                @unlink($existingTemplate->file_path);
            }

            $this->biometricModel->update($existingTemplate->id, [
                'file_path' => $filePath,
                'template_data' => json_encode($descriptor),
                'image_hash' => $imageHash,
                'enrollment_quality' => $quality,
                'model_used' => 'DeepFace',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $templateId = (int) $existingTemplate->id;
        } else {
            $templateId = (int) $this->biometricModel->insert([
                'employee_id' => $employeeId,
                'biometric_type' => 'face',
                'template_data' => json_encode($descriptor),
                'file_path' => $filePath,
                'image_hash' => $imageHash,
                'enrollment_quality' => $quality,
                'model_used' => 'DeepFace',
                'active' => true,
            ]);
        }

        if (!$templateId) {
            @unlink($filePath);
            return ['success' => false, 'error' => 'Erro ao salvar cadastro facial.'];
        }

        $this->employeeModel->update($employeeId, ['has_face_biometric' => true]);

        return ['success' => true, 'template_id' => $templateId];
    }

    public function deleteEnrollment(int $employeeId): array
    {
        $templates = $this->biometricModel
            ->where('employee_id', $employeeId)
            ->where('biometric_type', 'face')
            ->findAll();

        if (empty($templates)) {
            return ['success' => true, 'message' => 'Nenhum cadastro facial encontrado.'];
        }

        $deletedFiles = 0;
        foreach ($templates as $template) {
            if ($template->file_path && file_exists($template->file_path) && @unlink($template->file_path)) {
                $deletedFiles++;
            }
        }

        $this->biometricModel
            ->where('employee_id', $employeeId)
            ->where('biometric_type', 'face')
            ->delete();

        $this->employeeModel->update($employeeId, ['has_face_biometric' => false]);

        return [
            'success' => true,
            'deleted_files' => $deletedFiles,
            'deleted_records' => count($templates),
        ];
    }
}
