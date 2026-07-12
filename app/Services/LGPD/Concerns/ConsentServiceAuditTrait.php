<?php

namespace App\Services\LGPD\Concerns;

trait ConsentServiceAuditTrait
{
    private function employeeAuditContext(int $employeeId): array
    {
        $employee = $this->employeeModel->find($employeeId);

        return [
            'employee' => $employee,
            'employee_code' => $employee->employee_code ?? null,
            'employee_name' => $employee->full_name ?? null,
        ];
    }
    private function logConsentEvent(string $action, int $employeeId, array $details): void
    {
        $context = $this->employeeAuditContext($employeeId);

        $this->auditModel->log(
            $employeeId,
            $action,
            sprintf('Consentimento LGPD: %s', $action),
            array_merge($context, $details)
        );
    }
    private function writeAudit(
        int $employeeId,
        string $action,
        string $entity,
        $entityId,
        ?array $oldValues,
        ?array $newValues,
        string $description,
        string $level = 'info'
    ): void {
        $this->auditModel->log(
            $employeeId,
            $action,
            $entity,
            $entityId,
            $oldValues,
            $newValues,
            $description,
            $level
        );
    }

}
