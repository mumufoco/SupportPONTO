<?php

namespace App\Services\LGPD;

use App\Models\AuditModel;
use App\Models\BiometricRecordModel;
use App\Models\UserConsentModel;
use App\Models\WarningModel;

class DataExportCollectorService
{
    public function __construct(
        protected UserConsentModel $consentModel,
        protected BiometricRecordModel $biometricModel,
        protected WarningModel $warningModel,
        protected AuditModel $auditModel,
        protected ?BiometricPrivacyGuardService $biometricPrivacyGuard = null,
        protected ?PersonalDataInventoryService $inventoryService = null,
        protected ?DataRetentionPolicyService $retentionService = null,
    ) {
        $this->biometricPrivacyGuard = $this->biometricPrivacyGuard ?? new BiometricPrivacyGuardService();
        $this->inventoryService = $this->inventoryService ?? new PersonalDataInventoryService();
        $this->retentionService = $this->retentionService ?? new DataRetentionPolicyService();
    }

    public function collectPersonalData(object $employee): array
    {
        return [
            'id' => $employee->id,
            'name' => $employee->name,
            'email' => $employee->email,
            'cpf' => $employee->cpf ?? null,
            'rg' => $employee->rg ?? null,
            'birth_date' => $employee->birth_date ?? null,
            'phone' => $employee->phone ?? null,
            'address' => $employee->address ?? null,
            'city' => $employee->city ?? null,
            'state' => $employee->state ?? null,
            'postal_code' => $employee->postal_code ?? null,
            'position' => $employee->position ?? null,
            'department' => $employee->department ?? null,
            'admission_date' => $employee->admission_date ?? null,
            'status' => $employee->status ?? null,
            'created_at' => $employee->created_at ?? null,
            'updated_at' => $employee->updated_at ?? null,
        ];
    }

    public function collectConsents(int $employeeId): array
    {
        $consents = $this->consentModel->getByEmployee($employeeId);
        $result = [];

        foreach ($consents as $consent) {
            $result[] = [
                '@type' => 'ConsentAction',
                'consentType' => $consent->consent_type,
                'purpose' => $consent->purpose,
                'legalBasis' => $consent->legal_basis,
                'granted' => $consent->granted,
                'grantedAt' => $consent->granted_at,
                'revokedAt' => $consent->revoked_at,
                'version' => $consent->version,
                'ipAddress' => $consent->ip_address,
            ];
        }

        return $result;
    }

    public function collectAttendance(int $employeeId): array
    {
        $db = \Config\Database::connect();

        if (!$db->tableExists('time_punches')) {
            return [];
        }

        $records = $db->table('time_punches')
            ->where('employee_id', $employeeId)
            ->orderBy('punched_at', 'DESC')
            ->limit(1000)
            ->get()
            ->getResult();

        $result = [];
        foreach ($records as $record) {
            $result[] = [
                '@type' => 'WorkAttendance',
                'id' => $record->id,
                'date' => $record->punch_date ?? $record->date ?? null,
                'punchedAt' => $record->punched_at ?? null,
                'type' => $record->type ?? $record->punch_type ?? null,
                'source' => $record->source ?? null,
                'status' => $record->status ?? null,
            ];
        }

        return $result;
    }

    public function collectBiometricData(int $employeeId): array
    {
        $records = $this->biometricModel
            ->where('employee_id', $employeeId)
            ->orderBy('created_at', 'DESC')
            ->findAll();

        return $this->biometricPrivacyGuard->redactForExport($records);
    }

    public function collectVacations(int $employeeId): array
    {
        $db = \Config\Database::connect();

        if (!$db->tableExists('vacations')) {
            return [];
        }

        $vacations = $db->table('vacations')
            ->where('employee_id', $employeeId)
            ->orderBy('start_date', 'DESC')
            ->get()
            ->getResult();

        return array_map(static fn($vacation): array => [
            '@type' => 'VacationRecord',
            'id' => $vacation->id ?? null,
            'startDate' => $vacation->start_date ?? null,
            'endDate' => $vacation->end_date ?? null,
            'days' => $vacation->days ?? null,
            'status' => $vacation->status ?? null,
        ], $vacations);
    }

    public function collectWarnings(int $employeeId): array
    {
        $warnings = $this->warningModel
            ->where('employee_id', $employeeId)
            ->orderBy('created_at', 'DESC')
            ->findAll();

        return array_map(static fn($warning): array => [
            '@type' => 'WarningRecord',
            'id' => $warning->id,
            'type' => $warning->type ?? null,
            'description' => $warning->description ?? null,
            'status' => $warning->status ?? null,
            'issuedAt' => $warning->created_at ?? null,
        ], $warnings);
    }

    public function collectDataInventory(): array
    {
        return $this->inventoryService->summary();
    }

    public function collectRetentionPolicies(): array
    {
        return $this->retentionService->policies();
    }

    public function collectAuditLog(int $employeeId): array
    {
        $audit = $this->auditModel
            ->where('user_id', $employeeId)
            ->orderBy('created_at', 'DESC')
            ->limit(1000)
            ->findAll();

        return array_map(static fn($entry): array => [
            '@type' => 'AuditEntry',
            'id' => $entry->id,
            'action' => $entry->action ?? null,
            'entity' => $entry->entity_type ?? null,
            'description' => $entry->description ?? null,
            'createdAt' => $entry->created_at ?? null,
            'ipAddress' => $entry->ip_address ?? null,
        ], $audit);
    }
}

