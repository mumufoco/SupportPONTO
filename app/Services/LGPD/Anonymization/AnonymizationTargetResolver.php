<?php

namespace App\Services\LGPD\Anonymization;

use App\Models\EmployeeModel;

class AnonymizationTargetResolver
{
    private EmployeeModel $employeeModel;

    public function __construct(?EmployeeModel $employeeModel = null)
    {
        $this->employeeModel = $employeeModel ?? new EmployeeModel();
    }

    public function findEmployee(int $employeeId)
    {
        return $this->employeeModel->find($employeeId);
    }

    public function isAlreadyAnonymized(object $employee): bool
    {
        return ($employee->name ?? null) === 'Usuário Anonimizado'
            || !empty($employee->anonymized_at);
    }

    public function extractOriginalSnapshot(object $employee): array
    {
        return [
            'name' => $employee->name ?? null,
            'email' => $employee->email ?? null,
            'cpf' => $employee->cpf ?? null,
        ];
    }

    public function listScheduledForProcessing(string $today): array
    {
        return $this->employeeModel
            ->where('scheduled_anonymization_date <=', $today)
            ->where('scheduled_anonymization_date IS NOT NULL')
            ->where('anonymized_at', null)
            ->findAll();
    }

    public function scheduleDate(int $employeeId, string $anonymizationDate): bool
    {
        return $this->employeeModel->update($employeeId, [
            'scheduled_anonymization_date' => $anonymizationDate,
        ]);
    }

    public function statistics(): array
    {
        $totalAnonymized = $this->employeeModel
            ->where('anonymized_at IS NOT NULL')
            ->countAllResults();

        $scheduled = $this->employeeModel
            ->where('scheduled_anonymization_date IS NOT NULL')
            ->where('anonymized_at', null)
            ->countAllResults();

        $recentAnonymizations = $this->employeeModel
            ->where('anonymized_at >=', date('Y-m-d', strtotime('-30 days')))
            ->countAllResults();

        $upcomingAnonymizations = $this->employeeModel
            ->where('scheduled_anonymization_date >=', date('Y-m-d'))
            ->where('scheduled_anonymization_date <=', date('Y-m-d', strtotime('+30 days')))
            ->where('anonymized_at', null)
            ->countAllResults();

        return [
            'total_anonymized' => $totalAnonymized,
            'scheduled' => $scheduled,
            'recent_anonymizations' => $recentAnonymizations,
            'upcoming_anonymizations' => $upcomingAnonymizations,
        ];
    }
}
