<?php

namespace App\Models;

use App\Services\Timesheet\NsrGeneratorService;
use CodeIgniter\Model;

/**
 * Declarações de ajuste de relógio do REP-P — alimentam o registro tipo "4" do AFD
 * (Portaria MTE 671/2021). Ver migração 2026-06-07-000487_CreateClockAdjustmentsTable
 * para o racional completo (por que é uma declaração manual, e não detecção automática).
 *
 * Cada registro consome um NSR da MESMA sequência canônica usada por time_punches —
 * gerado atomicamente por NsrGeneratorService — para preservar a ordenação global por
 * NSR exigida pelo leiaute do AFD (item 4: "Ordenar os registros pelo NSR").
 */
class ClockAdjustmentModel extends Model
{
    protected $table            = 'clock_adjustments';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'previous_datetime',
        'adjusted_datetime',
        'responsible_cpf',
        'declared_by',
        'reason',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    protected $validationRules = [
        'previous_datetime' => 'required|valid_date',
        'adjusted_datetime' => 'required|valid_date',
        'responsible_cpf'   => 'required|min_length[11]',
        'declared_by'       => 'required|integer',
        'reason'            => 'required|min_length[10]|max_length[2000]',
    ];

    protected $validationMessages = [
        'previous_datetime' => [
            'required'   => 'Informe a data e hora anteriores ao ajuste.',
            'valid_date' => 'Data/hora anterior inválida.',
        ],
        'adjusted_datetime' => [
            'required'   => 'Informe a data e hora ajustadas.',
            'valid_date' => 'Data/hora ajustada inválida.',
        ],
        'responsible_cpf' => [
            'required'   => 'O CPF do responsável pelo ajuste é obrigatório.',
            'min_length' => 'CPF inválido.',
        ],
        'reason' => [
            'required'   => 'Descreva o motivo do ajuste de relógio (mín. 10 caracteres).',
            'min_length' => 'Descreva o motivo do ajuste de relógio (mín. 10 caracteres).',
            'max_length' => 'Justificativa muito longa (máx. 2000 caracteres).',
        ],
    ];

    protected $allowCallbacks = true;
    protected $beforeInsert   = ['generateNSR', 'generateHash'];

    /**
     * Gera o NSR atomicamente na MESMA sequência canônica de time_punches — nunca um
     * contador paralelo, sob pena de quebrar a ordenação global exigida pelo AFD.
     */
    protected function generateNSR(array $data): array
    {
        if (isset($data['data']['nsr']) && $data['data']['nsr'] !== '') {
            return $data;
        }

        $generator = new NsrGeneratorService(\Config\Database::connect());
        $generator->assertReady();
        $data['data']['nsr'] = $generator->next();

        return $data;
    }

    /**
     * Hash de evidência de integridade — mesmo espírito (não a mesma fórmula) do
     * encadeamento de time_punches: garante que qualquer alteração de bytes do
     * registro seja detectável, complementando os gatilhos de imutabilidade do banco.
     */
    protected function generateHash(array $data): array
    {
        $payload = $data['data'];

        $material = implode('|', [
            $payload['nsr'] ?? '',
            $payload['previous_datetime'] ?? '',
            $payload['adjusted_datetime'] ?? '',
            $payload['responsible_cpf'] ?? '',
            $payload['declared_by'] ?? '',
            $payload['reason'] ?? '',
        ]);

        $data['data']['hash'] = hash('sha256', $material);

        return $data;
    }

    /**
     * Registra uma declaração de ajuste de relógio dentro de uma transação, com NSR
     * canônico e hash de integridade — caminho oficial e único de inserção.
     */
    public function declareAdjustment(array $payload): int
    {
        $db = \Config\Database::connect();
        $db->transBegin();

        try {
            $id = $this->insert($payload);
            if (! $id) {
                throw new \RuntimeException('Falha ao registrar ajuste de relógio: ' . json_encode($this->errors(), JSON_UNESCAPED_UNICODE));
            }

            $db->transCommit();

            return (int) $id;
        } catch (\Throwable $e) {
            $db->transRollback();

            throw $e;
        }
    }

    /**
     * @return object[] Declarações cujo `adjusted_datetime` cai dentro do período informado,
     *                  ordenadas por NSR — prontas para serem mescladas com as marcações de
     *                  ponto na geração do AFD (mesma ordenação exigida pelo leiaute).
     */
    public function findInPeriod(string $startDate, string $endDate): array
    {
        return $this->where('adjusted_datetime >=', $startDate)
            ->where('adjusted_datetime <=', $endDate)
            ->orderBy('nsr', 'ASC')
            ->findAll();
    }
}
