<?php
// ARQ-03 FIX: Este arquivo é o núcleo real do serviço.
// A nomenclatura "LegacyCore" é um artefato histórico — não indica código obsoleto.
// Roadmap: renomear para *ServiceCore e a subclasse pública para *Service
// na próxima refatoração maior. (Ver SECURITY_AUDIT_IMPLEMENTATION.md)


namespace App\Services\LGPD;

use App\Services\LGPD\Anonymization\AnonymizationAuditLogger;
use App\Services\LGPD\Anonymization\AnonymizationTargetResolver;
use App\Services\LGPD\Anonymization\EmployeeDataAnonymizationProcessor;

/**
 * Núcleo legado de anonimização LGPD.
 *
 * Mantém a API pública por retrocompatibilidade, delegando o fluxo para
 * componentes menores e coesos.
 */
class DataAnonymizationService
{
    private AnonymizationTargetResolver $targetResolver;
    private EmployeeDataAnonymizationProcessor $processor;
    private AnonymizationAuditLogger $auditLogger;

    public function __construct(
        ?AnonymizationTargetResolver $targetResolver = null,
        ?EmployeeDataAnonymizationProcessor $processor = null,
        ?AnonymizationAuditLogger $auditLogger = null,
    ) {
        $this->targetResolver = $targetResolver ?? new AnonymizationTargetResolver();
        $this->processor = $processor ?? new EmployeeDataAnonymizationProcessor();
        $this->auditLogger = $auditLogger ?? new AnonymizationAuditLogger();
    }

    /**
     * @return array{success:bool,message:string,anonymized_count:int}
     */
    public function anonymizeEmployee(int $employeeId, ?string $requestedBy = null, ?string $reason = null): array
    {
        $db = \Config\Database::connect();
        $db->transStart();

        try {
            $employee = $this->targetResolver->findEmployee($employeeId);
            if (! $employee) {
                $db->transRollback();

                return [
                    'success' => false,
                    'message' => 'Funcionário não encontrado',
                    'anonymized_count' => 0,
                ];
            }

            if ($this->targetResolver->isAlreadyAnonymized($employee)) {
                $db->transRollback();

                return [
                    'success' => false,
                    'message' => 'Funcionário já está anonimizado',
                    'anonymized_count' => 0,
                ];
            }

            $originalData = $this->targetResolver->extractOriginalSnapshot($employee);
            $result = $this->processor->anonymizeAllForEmployee($employeeId, $originalData);

            $this->auditLogger->logEmployeeAnonymization(
                $employeeId,
                $originalData,
                $result['employee_email'],
                $requestedBy,
                $reason
            );

            $db->transComplete();
            if ($db->transStatus() === false) {
                return [
                    'success' => false,
                    'message' => 'Erro ao processar anonimização',
                    'anonymized_count' => 0,
                ];
            }

            return [
                'success' => true,
                'message' => sprintf('Dados anonimizados com sucesso. %d registros afetados.', $result['count']),
                'anonymized_count' => $result['count'],
            ];
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Erro ao anonimizar funcionário: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Erro ao processar anonimização. A ocorrência foi registrada para análise.',
                'anonymized_count' => 0,
            ];
        }
    }

    public function scheduleAnonymization(int $employeeId, int $retentionDays = 3650): array
    {
        $employee = $this->targetResolver->findEmployee($employeeId);
        if (! $employee) {
            return [
                'success' => false,
                'message' => 'Funcionário não encontrado',
            ];
        }

        $anonymizationDate = date('Y-m-d', strtotime("+{$retentionDays} days"));
        $this->targetResolver->scheduleDate($employeeId, $anonymizationDate);
        $this->auditLogger->logSchedule($employeeId, $anonymizationDate, $retentionDays);

        return [
            'success' => true,
            'message' => sprintf('Anonimização agendada para %s', $anonymizationDate),
            'scheduled_date' => $anonymizationDate,
        ];
    }

    public function processScheduledAnonymizations(): array
    {
        $employees = $this->targetResolver->listScheduledForProcessing(date('Y-m-d'));
        $processed = 0;
        $failed = 0;

        foreach ($employees as $employee) {
            $result = $this->anonymizeEmployee(
                (int) $employee->id,
                'Sistema - Agendamento Automático',
                sprintf('Período de retenção expirado em %s', $employee->scheduled_anonymization_date)
            );

            if ($result['success']) {
                $processed++;
            } else {
                $failed++;
                log_message('error', sprintf('Falha ao anonimizar funcionário #%d: %s', $employee->id, $result['message']));
            }
        }

        return [
            'success' => true,
            'processed' => $processed,
            'failed' => $failed,
            'message' => sprintf('%d funcionários anonimizados, %d falharam', $processed, $failed),
        ];
    }

    /**
     * @return array{success:bool,message:string,anonymized_count:int}
     */
    public function anonymizeDataType(string $dataType, int $employeeId): array
    {
        try {
            $count = $this->processor->anonymizeByType($dataType, $employeeId);
        } catch (\InvalidArgumentException $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage(),
                'anonymized_count' => 0,
            ];
        }

        $this->auditLogger->logDataTypeAnonymization($dataType, $employeeId);

        return [
            'success' => true,
            'message' => sprintf('%d registros de %s anonimizados', $count, $dataType),
            'anonymized_count' => $count,
        ];
    }

    public function getAnonymizationStatistics(): array
    {
        return $this->targetResolver->statistics();
    }
}
