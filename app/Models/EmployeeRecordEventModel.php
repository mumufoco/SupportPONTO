<?php

namespace App\Models;

use App\Services\Timesheet\NsrGeneratorService;
use CodeIgniter\Model;

/**
 * Registros imutáveis do tipo "5" do AFD — "Inclusão/Alteração/Exclusão de
 * empregados no REP" (Portaria MTE 671/2021).
 *
 * Ver migração 2026-06-07-000491_CreateEmployeeRecordEventsTable para o racional
 * completo do leiaute (118 bytes, com CRC-16) e da imutabilidade via gatilhos.
 *
 * Cada operação consome UM NSR canônico da MESMA sequência atômica usada por
 * time_punches/clock_adjustments/company_record_events/rep_availability_events
 * (NsrGeneratorService) — preservando a ordenação global por NSR exigida pelo AFD.
 *
 * Caminho único e oficial de inserção: chamado exclusivamente por
 * EmployeeAfdEventRecorderService a partir dos pontos de injeção identificados em
 * EmployeeController/EmployeeControllerActionService/EmployeeChangeRequestController.
 */
class EmployeeRecordEventModel extends Model
{
    public const OPERATION_INCLUSION   = 'I';
    public const OPERATION_ALTERATION  = 'A';
    public const OPERATION_EXCLUSION   = 'E';

    protected $table            = 'employee_record_events';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'nsr',
        'operation_type',
        'employee_cpf',
        'employee_name',
        'responsible_cpf',
        'recorded_at',
        'hash',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    protected $allowCallbacks = true;
    protected $beforeInsert   = ['generateHash'];

    /**
     * Hash de evidência de integridade — mesmo espírito (não a mesma fórmula) do
     * encadeamento de time_punches/clock_adjustments/company_record_events/
     * rep_availability_events.
     */
    protected function generateHash(array $data): array
    {
        $payload = $data['data'];

        $material = implode('|', [
            $payload['nsr'] ?? '',
            $payload['operation_type'] ?? '',
            $payload['employee_cpf'] ?? '',
            $payload['employee_name'] ?? '',
            $payload['responsible_cpf'] ?? '',
            $payload['recorded_at'] ?? '',
        ]);

        $data['data']['hash'] = hash('sha256', $material);

        return $data;
    }

    /**
     * Registra um evento de ciclo de vida do empregado ("I"/"A"/"E"), consumindo
     * um NSR canônico — caminho único e oficial de inserção destes registros.
     *
     * @return int ID do registro inserido
     */
    public function recordEvent(
        string $operationType,
        string $employeeCpf,
        string $employeeName,
        string $responsibleCpf,
        string $recordedAt
    ): int {
        $db = \Config\Database::connect();
        $db->transBegin();

        try {
            $generator = new NsrGeneratorService($db);
            $generator->assertReady();

            $nsr = $generator->next('employee_record_events');

            $id = $this->insert([
                'nsr'             => $nsr,
                'operation_type'  => $operationType,
                'employee_cpf'    => $employeeCpf,
                'employee_name'   => $employeeName,
                'responsible_cpf' => $responsibleCpf,
                'recorded_at'     => $recordedAt,
            ]);

            if (! $id) {
                throw new \RuntimeException('Falha ao registrar evento de empregado (' . $operationType . '): ' . json_encode($this->errors(), JSON_UNESCAPED_UNICODE));
            }

            $db->transCommit();

            return (int) $id;
        } catch (\Throwable $e) {
            $db->transRollback();

            throw $e;
        }
    }

    /**
     * @return object[] Eventos de ciclo de vida cujo `recorded_at` cai dentro do
     *                  período informado, ordenados por NSR — prontos para serem
     *                  mesclados com os demais registros na geração do AFD.
     */
    public function findInPeriod(string $startDate, string $endDate): array
    {
        return $this->where('recorded_at >=', $startDate)
            ->where('recorded_at <=', $endDate)
            ->orderBy('nsr', 'ASC')
            ->findAll();
    }
}
