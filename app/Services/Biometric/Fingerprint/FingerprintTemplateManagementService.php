<?php

namespace App\Services\Biometric\Fingerprint;

class FingerprintTemplateManagementService
{
    public function __construct(private FingerprintTemplateStateService $stateService)
    {
    }

    public function removeTemplate(int $templateId, int $employeeId): array
    {
        $template = $this->stateService->findTemplate($templateId);
        if (!$template) {
            return ['success' => false, 'message' => 'Template não encontrado'];
        }

        if ((int) $template->employee_id !== $employeeId) {
            return ['success' => false, 'message' => 'Acesso negado'];
        }

        if (!$this->stateService->deactivateTemplate($templateId)) {
            return ['success' => false, 'message' => 'Erro ao excluir template'];
        }

        if ($this->stateService->activeTemplatesCount($employeeId) === 0) {
            $this->stateService->setEmployeeFingerprintFlag($employeeId, false);
        }

        return ['success' => true, 'message' => 'Template excluído com sucesso'];
    }
}
