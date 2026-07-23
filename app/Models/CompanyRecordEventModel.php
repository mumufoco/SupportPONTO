<?php

namespace App\Models;

use App\Services\Timesheet\NsrGeneratorService;
use CodeIgniter\Model;

/**
 * Declarações de alteração cadastral da empresa no REP-P — alimentam o registro tipo "2"
 * do AFD (Portaria MTE 671/2021). Ver migração 2026-06-07-000489_CreateCompanyRecordEventsTable
 * para o racional completo (por que é uma declaração manual, e não detecção automática).
 *
 * Cada registro consome um NSR da MESMA sequência canônica usada por time_punches e
 * clock_adjustments — gerado atomicamente por NsrGeneratorService — para preservar a
 * ordenação global por NSR exigida pelo leiaute do AFD (item 4: "Ordenar os registros
 * pelo NSR").
 */
class CompanyRecordEventModel extends Model
{
    protected $table            = 'company_record_events';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'recorded_at',
        'responsible_cpf',
        'employer_doc_type',
        'employer_doc',
        'cno_caepf',
        'company_name',
        'service_location',
        'reason',
        'declared_by',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    protected $validationRules = [
        'recorded_at'       => 'required|valid_date',
        'responsible_cpf'   => 'required|min_length[11]',
        'employer_doc_type' => 'required|in_list[1,2]',
        'employer_doc'      => 'required|min_length[11]',
        'company_name'      => 'required|max_length[150]',
        'service_location'  => 'permit_empty|max_length[100]',
        'reason'            => 'required|min_length[10]|max_length[2000]',
        'declared_by'       => 'required|integer',
    ];

    protected $validationMessages = [
        'recorded_at' => [
            'required'   => 'Informe a data e hora do registro da alteração.',
            'valid_date' => 'Data/hora inválida.',
        ],
        'responsible_cpf' => [
            'required'   => 'O CPF do responsável pela alteração é obrigatório.',
            'min_length' => 'CPF inválido.',
        ],
        'employer_doc_type' => [
            'required' => 'Informe o tipo de documento do empregador (CNPJ ou CPF).',
            'in_list'  => 'Tipo de documento do empregador inválido.',
        ],
        'employer_doc' => [
            'required'   => 'Informe o CNPJ ou CPF do empregador.',
            'min_length' => 'Documento do empregador inválido.',
        ],
        'company_name' => [
            'required'   => 'Informe a razão social/nome do empregador.',
            'max_length' => 'Razão social muito longa (máx. 150 caracteres).',
        ],
        'reason' => [
            'required'   => 'Descreva o motivo da alteração cadastral (mín. 10 caracteres).',
            'min_length' => 'Descreva o motivo da alteração cadastral (mín. 10 caracteres).',
            'max_length' => 'Justificativa muito longa (máx. 2000 caracteres).',
        ],
    ];

    protected $allowCallbacks = true;
    protected $beforeInsert   = ['generateNSR', 'generateHash'];

    /**
     * Gera o NSR atomicamente na MESMA sequência canônica de time_punches/clock_adjustments —
     * nunca um contador paralelo, sob pena de quebrar a ordenação global exigida pelo AFD.
     */
    protected function generateNSR(array $data): array
    {
        if (isset($data['data']['nsr']) && $data['data']['nsr'] !== '') {
            return $data;
        }

        $generator = new NsrGeneratorService(\Config\Database::connect());
        $generator->assertReady();
        $data['data']['nsr'] = $generator->next('company_record_events');

        return $data;
    }

    /**
     * Hash de evidência de integridade — mesmo espírito (não a mesma fórmula) do
     * encadeamento de time_punches/clock_adjustments: garante que qualquer alteração de
     * bytes do registro seja detectável, complementando os gatilhos de imutabilidade do banco.
     */
    protected function generateHash(array $data): array
    {
        $payload = $data['data'];

        $material = implode('|', [
            $payload['nsr'] ?? '',
            $payload['recorded_at'] ?? '',
            $payload['responsible_cpf'] ?? '',
            $payload['employer_doc_type'] ?? '',
            $payload['employer_doc'] ?? '',
            $payload['cno_caepf'] ?? '',
            $payload['company_name'] ?? '',
            $payload['service_location'] ?? '',
            $payload['declared_by'] ?? '',
            $payload['reason'] ?? '',
        ]);

        $data['data']['hash'] = hash('sha256', $material);

        return $data;
    }

    /**
     * Registra uma declaração de alteração cadastral dentro de uma transação, com NSR
     * canônico e hash de integridade — caminho oficial e único de inserção.
     */
    public function declareCompanyRecordEvent(array $payload): int
    {
        $db = \Config\Database::connect();
        $db->transBegin();

        try {
            $id = $this->insert($payload);
            if (! $id) {
                throw new \RuntimeException('Falha ao registrar alteração cadastral: ' . json_encode($this->errors(), JSON_UNESCAPED_UNICODE));
            }

            $db->transCommit();

            return (int) $id;
        } catch (\Throwable $e) {
            $db->transRollback();

            throw $e;
        }
    }

    /**
     * @return object[] Declarações cujo `recorded_at` cai dentro do período informado,
     *                  ordenadas por NSR — prontas para serem mescladas com as marcações de
     *                  ponto na geração do AFD (mesma ordenação exigida pelo leiaute).
     */
    public function findInPeriod(string $startDate, string $endDate): array
    {
        return $this->where('recorded_at >=', $startDate)
            ->where('recorded_at <=', $endDate)
            ->orderBy('nsr', 'ASC')
            ->findAll();
    }
}
