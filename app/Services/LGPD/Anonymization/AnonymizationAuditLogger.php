<?php

namespace App\Services\LGPD\Anonymization;

use App\Models\AuditModel;
use App\Services\Audit\CanonicalAuditLogger;

class AnonymizationAuditLogger
{
    private AuditModel $auditModel;
    private CanonicalAuditLogger $auditLogger;

    public function __construct(?AuditModel $auditModel = null)
    {
        $this->auditModel = $auditModel ?? new AuditModel();
        $this->auditLogger = new CanonicalAuditLogger($this->auditModel);
    }

    public function logEmployeeAnonymization(
        int $employeeId,
        array $oldData,
        string $anonymizedEmail,
        ?string $requestedBy,
        ?string $reason
    ): void {
        $this->auditLogger->logEntityEvent(
            null,
            'ANONYMIZE_EMPLOYEE',
            'employees',
            $employeeId,
            $oldData,
            [
                'name' => 'Usuário Anonimizado',
                'email' => $anonymizedEmail,
                'anonymized_at' => date('Y-m-d H:i:s'),
            ],
            sprintf(
                'Anonimização de dados do funcionário #%d. Solicitado por: %s. Motivo: %s',
                $employeeId,
                $requestedBy ?? 'Sistema',
                $reason ?? 'Direito ao esquecimento (LGPD Art. 16)'
            ),
            'warning'
        );
    }

    public function logSchedule(int $employeeId, string $anonymizationDate, int $retentionDays): void
    {
        $this->auditLogger->logEntityEvent(
            null,
            'SCHEDULE_ANONYMIZATION',
            'employees',
            $employeeId,
            null,
            [
                'anonymization_date' => $anonymizationDate,
                'retention_days' => $retentionDays,
            ],
            sprintf('Anonimização agendada para %s (%d dias)', $anonymizationDate, $retentionDays),
            'info'
        );
    }

    public function logDataTypeAnonymization(string $dataType, int $employeeId): void
    {
        $this->auditLogger->logEntityEvent(
            null,
            'ANONYMIZE_DATA_TYPE',
            $dataType,
            $employeeId,
            null,
            null,
            sprintf('Anonimização de dados do tipo %s para funcionário #%d', $dataType, $employeeId),
            'warning'
        );
    }
}
