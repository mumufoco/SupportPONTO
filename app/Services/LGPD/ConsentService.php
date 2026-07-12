<?php
// ARQ-03 FIX: Este arquivo é o núcleo real do serviço.
// A nomenclatura "LegacyCore" é um artefato histórico — não indica código obsoleto.
// Roadmap: renomear para *ServiceCore e a subclasse pública para *Service
// na próxima refatoração maior. (Ver SECURITY_AUDIT_IMPLEMENTATION.md)


namespace App\Services\LGPD;

use App\Models\UserConsentModel;
use App\Models\AuditModel;
use App\Models\EmployeeModel;
use App\Models\BiometricRecordModel;
use CodeIgniter\I18n\Time;
use Config\Services;
use App\Services\LGPD\Concerns\ConsentServiceLifecycleTrait;
use App\Services\LGPD\Concerns\ConsentServiceNotificationTrait;
use App\Services\LGPD\Concerns\ConsentServiceAuditTrait;

/**
 * ConsentService
 *
 * Serviço para gestão de consentimentos LGPD
 */
class ConsentService
{
    use ConsentServiceLifecycleTrait;
    use ConsentServiceAuditTrait;
    use ConsentServiceNotificationTrait;
    protected UserConsentModel $consentModel;
    protected AuditModel $auditModel;
    protected EmployeeModel $employeeModel;
    protected BiometricRecordModel $biometricModel;

    public function __construct(
        ?UserConsentModel $consentModel = null,
        ?AuditModel $auditModel = null,
        ?EmployeeModel $employeeModel = null,
        ?BiometricRecordModel $biometricModel = null
    ) {
        $this->consentModel = $consentModel ?? Services::userConsentModel(false);
        $this->auditModel = $auditModel ?? Services::auditModel(false);
        $this->employeeModel = $employeeModel ?? Services::employeeModel(false);
        $this->biometricModel = $biometricModel ?? Services::biometricRecordModel(false);
    }








    /**
     * Grant consent with full audit trail
     *
     * @param int $employeeId Employee ID
     * @param string $consentType Type of consent
     * @param string $purpose Purpose of data processing
     * @param string $consentText Full consent text presented to user
     * @param string|null $legalBasis LGPD legal basis (e.g., "Art. 11, II")
     * @param string $version Version of consent term
     * @return array ['success' => bool, 'message' => string, 'consent_id' => int|null]
     */


    /**
     * Revoke consent with biometric data deletion
     *
     * @param int $employeeId Employee ID
     * @param string $consentType Type of consent to revoke
     * @param string|null $reason Reason for revocation
     * @return array ['success' => bool, 'message' => string, 'deleted_records' => int]
     */


    /**
     * Delete biometric data for employee
     * LGPD Art. 16 - Right to deletion
     *
     * @param int $employeeId Employee ID
     * @param string $consentType Type of biometric consent
     * @return int Number of records deleted
     */


    /**
     * Get all consents for employee
     *
     * @param int $employeeId Employee ID
     * @return array List of consents with status
     */
    public function getEmployeeConsents(int $employeeId): array
    {
        $consents = $this->consentModel->getByEmployee($employeeId);
        $pending = $this->consentModel->getPending($employeeId);

        return [
            'active' => array_filter($consents, fn($c) => $c->granted && !$c->revoked_at),
            'revoked' => array_filter($consents, fn($c) => !$c->granted || $c->revoked_at),
            'pending' => $pending,
            'all' => $consents,
        ];
    }

    /**
     * Check if employee has specific consent
     *
     * @param int $employeeId Employee ID
     * @param string $consentType Type of consent
     * @return bool True if consent is active
     */
    public function hasConsent(int $employeeId, string $consentType): bool
    {
        return $this->consentModel->hasConsent($employeeId, $consentType);
    }

    /**
     * Generate ANPD compliance report
     * Relatório para Autoridade Nacional de Proteção de Dados
     *
     * @param string|null $startDate Start date (Y-m-d)
     * @param string|null $endDate End date (Y-m-d)
     * @return array Report data
     */
    public function generateANPDReport(?string $startDate = null, ?string $endDate = null): array
    {
        $startDate = $startDate ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $endDate ?? date('Y-m-d');

        // Get all consent activities in period
        $db = \Config\Database::connect();

        // Consents granted
        $granted = $db->table('user_consents')
            ->where('granted_at >=', $startDate . ' 00:00:00')
            ->where('granted_at <=', $endDate . ' 23:59:59')
            ->where('granted', true)
            ->get()
            ->getResult();

        // Consents revoked
        $revoked = $db->table('user_consents')
            ->where('revoked_at >=', $startDate . ' 00:00:00')
            ->where('revoked_at <=', $endDate . ' 23:59:59')
            ->where('granted', false)
            ->get()
            ->getResult();

        // Group by consent type
        $grantedByType = [];
        $revokedByType = [];

        foreach ($granted as $consent) {
            $type = $consent->consent_type;
            $grantedByType[$type] = ($grantedByType[$type] ?? 0) + 1;
        }

        foreach ($revoked as $consent) {
            $type = $consent->consent_type;
            $revokedByType[$type] = ($revokedByType[$type] ?? 0) + 1;
        }

        // Get audit logs for data access/export
        $accessLogs = $this->auditModel->search([
            'action' => 'EXPORT',
            'start_date' => $startDate . ' 00:00:00',
            'end_date' => $endDate . ' 23:59:59',
            'limit' => 1000,
        ]);

        return [
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'consents' => [
                'granted' => [
                    'total' => count($granted),
                    'by_type' => $grantedByType,
                ],
                'revoked' => [
                    'total' => count($revoked),
                    'by_type' => $revokedByType,
                ],
            ],
            'data_access' => [
                'exports' => count($accessLogs),
            ],
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Send notification when consent is granted
     *
     * @param object $employee Employee data
     * @param string $consentType Type of consent
     * @param object $consent Consent record
     * @return void
     */


    /**
     * Send notification when consent is revoked
     *
     * @param object $employee Employee data
     * @param string $consentType Type of consent
     * @param string|null $reason Revocation reason
     * @param int $deletedRecords Number of deleted records
     * @return void
     */

}
