<?php

namespace App\Models;

use App\Enums\PunchMethod;
use App\Enums\PunchType;
use App\Services\Timesheet\NsrGeneratorService;
use App\Services\Timesheet\TimePunchConcurrencyService;
use App\Services\Timesheet\TimePunchIntegrityChainService;
use CodeIgniter\Model;

class TimePunchModel extends Model
{
    private const NSR_COUNTER_ID              = 1;
    private const MIN_SECONDS_BETWEEN_PUNCHES = 60;

    protected $table            = 'time_punches';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'employee_id',
        'punch_time',
        'punch_type',
        'method',
        'nsr',
        'hash',
        'previous_hash',
        'chain_hash',
        'hash_algorithm',
        'hash_version',
        'integrity_key_id',
        'integrity_signed_at',
        // Campos canônicos atuais.
        'location_lat',
        'location_lng',
        'location_accuracy',
        'within_geofence',
        'geofence_name',
        'face_similarity',
        'ip_address',
        'user_agent',
        'status',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'notes',
        // Campos legados ainda enviados por endpoints/API e já existentes no schema antigo.
        'latitude',
        'longitude',
        'address',
        'device_info',
        'photo_path',
        'validation_method',
        'is_valid',
        // Sincronização offline (PWA): chave de idempotência gerada pelo dispositivo.
        'client_uuid',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    /**
     * MELHORIA 2: Regras de validação geradas a partir dos enums.
     * in_list é derivado de PunchType::validationList() e PunchMethod::validationList()
     * — nunca mais diverge entre model e enum.
     */
    protected $validationRules = [];

    public function __construct()
    {
        parent::__construct();

        // Regras derivadas dos enums — fonte única da verdade
        $this->validationRules = [
            'employee_id' => 'required|integer',
            'punch_time'  => 'required|valid_date',
            'punch_type'  => 'required|in_list[' . PunchType::validationList() . ']',
            'method'      => 'required|in_list[' . PunchMethod::validationList() . ']',
        ];
    }

    protected $validationMessages = [
        'employee_id' => [
            'required' => 'O ID do funcionário é obrigatório.',
            'integer'  => 'O ID do funcionário deve ser um número.',
        ],
        'punch_type' => [
            'required' => 'O tipo de marcação é obrigatório.',
            'in_list'  => 'Tipo de marcação inválido.',
        ],
        'method' => [
            'required' => 'O método de registro é obrigatório.',
            'in_list'  => 'Método de registro inválido.',
        ],
    ];

    protected $skipValidation       = false;
    protected $cleanValidationRules = true;
    protected $allowCallbacks = true;
    protected $beforeInsert   = ['syncLocationAliases', 'generateNSR', 'generateHash'];
    protected $beforeUpdate   = ['syncLocationAliases'];
    protected $afterFind      = ['castBooleans'];

    /**
     * O driver Postgres retorna colunas boolean como string 't'/'f', que
     * filter_var($v, FILTER_VALIDATE_BOOLEAN) não reconhece, fazendo
     * registros válidos/dentro-da-geofence aparecerem como inválidos/fora.
     * Mesmo padrão de fix já usado em RoleModel/GeofenceModel::castBooleans().
     */
    protected function castBooleans(array $data): array
    {
        if (empty($data['data'])) {
            return $data;
        }

        $rows = $data['singleton'] ? [$data['data']] : $data['data'];

        foreach ($rows as $row) {
            if (is_object($row)) {
                if (property_exists($row, 'is_valid')) {
                    $row->is_valid = in_array($row->is_valid, [true, 't', '1', 1, 'true'], true);
                }
                if (property_exists($row, 'within_geofence')) {
                    $row->within_geofence = in_array($row->within_geofence, [true, 't', '1', 1, 'true'], true);
                }
            }
        }

        return $data;
    }

    protected function syncLocationAliases(array $data): array
    {
        $payload = $data['data'] ?? [];

        $pairs = [
            'location_lat' => 'latitude',
            'location_lng' => 'longitude',
        ];

        foreach ($pairs as $canonical => $legacy) {
            $hasCanonical = array_key_exists($canonical, $payload) && $payload[$canonical] !== null && $payload[$canonical] !== '';
            $hasLegacy = array_key_exists($legacy, $payload) && $payload[$legacy] !== null && $payload[$legacy] !== '';

            if ($hasCanonical && ! $hasLegacy) {
                $payload[$legacy] = $payload[$canonical];
            }

            if ($hasLegacy && ! $hasCanonical) {
                $payload[$canonical] = $payload[$legacy];
            }
        }

        $data['data'] = $payload;

        return $data;
    }

    protected function generateNSR(array $data): array
    {
        if (isset($data['data']['nsr']) && $data['data']['nsr'] !== '') {
            return $data;
        }

        $generator = new NsrGeneratorService(\Config\Database::connect());
        $generator->assertReady();
        $data['data']['nsr'] = $generator->next('time_punches');

        return $data;
    }

    protected function generateHash(array $data): array
    {
        if (!isset($data['data']['employee_id'], $data['data']['punch_time'], $data['data']['nsr'])) {
            return $data;
        }

        $data['data']['punch_type'] = $this->normalizePunchType((string) ($data['data']['punch_type'] ?? ''));
        $data['data'] = (new TimePunchIntegrityChainService(\Config\Database::connect()))->enrichPayload($data['data']);

        return $data;
    }

    public function insertWithIntegrityLock(array $punchData): int
    {
        $employeeId = (int) ($punchData['employee_id'] ?? 0);
        if ($employeeId <= 0) {
            throw new \InvalidArgumentException('employee_id é obrigatório para registro de ponto com integridade.');
        }

        // CI4 4.6+ requer chaves string. Remove qualquer chave numérica antes do insert.
        $punchData = array_filter($punchData, static fn($k) => is_string($k), ARRAY_FILTER_USE_KEY);

        $db = \Config\Database::connect();
        $db->transBegin();

        try {
            (new TimePunchConcurrencyService($db))->lockEmployeeForPunch($employeeId);

            if (! $this->canPunch($employeeId)) {
                throw new \RuntimeException('Registro bloqueado por cooldown transacional. Aguarde antes de marcar novamente.');
            }

            $id = $this->insert($punchData);
            if (! $id) {
                $errors = $this->errors();
                throw new \RuntimeException('Falha ao inserir ponto: ' . json_encode($errors, JSON_UNESCAPED_UNICODE));
            }

            $db->transCommit();
            return (int) $id;
        } catch (\Throwable $e) {
            if ($db->transStatus() !== false) {
                $db->transRollback();
            } else {
                $db->transRollback();
            }

            throw $e;
        }
    }

    public function getPunchesByDate(int $employeeId, string $date): array
    {
        [$startAt, $endAt] = $this->getDayBounds($date);

        return $this->getPunchesBetween($employeeId, $startAt, $endAt);
    }

    public function getLastPunch(int $employeeId, ?string $date = null): ?object
    {
        $builder = $this->where('employee_id', $employeeId);

        if ($date) {
            [$startAt, $endAt] = $this->getDayBounds($date);
            $builder->where('punch_time >=', $startAt)
                ->where('punch_time <', $endAt);
        }

        return $builder->orderBy('punch_time', 'DESC')->first();
    }

    public function getPunchesByDateRange(int $employeeId, string $startDate, string $endDate): array
    {
        [$startAt, $endAt] = $this->getDateRangeBounds($startDate, $endDate);

        return $this->getPunchesBetween($employeeId, $startAt, $endAt);
    }

    public function getDayBounds(string $date): array
    {
        return [$date . ' 00:00:00', date('Y-m-d H:i:s', strtotime($date . ' +1 day'))];
    }

    public function getDateRangeBounds(string $startDate, string $endDate): array
    {
        return [$startDate . ' 00:00:00', date('Y-m-d H:i:s', strtotime($endDate . ' +1 day'))];
    }

    /**
     * Sincronização offline (PWA): localiza uma marcação já gravada pelo mesmo
     * client_uuid, para que um reenvio (timeout/retry) devolva o resultado já
     * processado em vez de duplicar a marcação.
     */
    public function findByClientUuid(string $clientUuid): ?object
    {
        return $this->where('client_uuid', $clientUuid)->first();
    }

    public function canPunch(int $employeeId): bool
    {
        $lastPunch = $this->getLastPunch($employeeId);

        if (!$lastPunch) {
            return true;
        }

        $lastPunchTime = strtotime((string) $lastPunch->punch_time);
        $now = time();

        return ($now - $lastPunchTime) >= self::MIN_SECONDS_BETWEEN_PUNCHES;
    }

    /**
     * O parâmetro $date é mantido apenas por compatibilidade de assinatura
     * (TimePunchServiceInterface) e não restringe mais a busca da última marcação.
     *
     * PunchType::nextExpected() já fecha o ciclo corretamente (Saída -> Entrada), então
     * restringir a busca ao dia civil da nova marcação só introduzia um bug real: um turno
     * que atravessa a meia-noite (ex.: entrada 23h, saída 07h do dia seguinte) tinha sua
     * saída rejeitada, porque a busca "esquecia" a entrada registrada no dia anterior (ver
     * auditoria CRIT-03).
     */
    public function getNextPunchType(int $employeeId, ?string $date = null): PunchType
    {
        $lastPunch = $this->getLastPunch($employeeId, $date);

        if (!$lastPunch) {
            return PunchType::Entrada;
        }

        $currentType = PunchType::tryFrom($this->normalizePunchType((string) $lastPunch->punch_type))
            ?? PunchType::Saida;

        return $currentType->nextExpected();
    }

    public function normalizePunchType(string $punchType): string
    {
        // MELHORIA 2: legacyMap para compatibilidade retroativa com registros antigos
        $legacyMap = [
            'almoco_saida'      => PunchType::IntervaloInicio->value,
            'saida_intervalo'   => PunchType::IntervaloInicio->value,
            'inicio_intervalo'  => PunchType::IntervaloInicio->value,
            'intervalo-inicio'  => PunchType::IntervaloInicio->value,
            'almoco_retorno'    => PunchType::IntervaloFim->value,
            'volta_intervalo'   => PunchType::IntervaloFim->value,
            'fim_intervalo'     => PunchType::IntervaloFim->value,
            'intervalo-fim'     => PunchType::IntervaloFim->value,
        ];

        return $legacyMap[$punchType] ?? $punchType;
    }

    public function validatePairs(int $employeeId, string $date): array
    {
        $punches = $this->getPunchesByDate($employeeId, $date);

        $validation = [
            'complete'  => false,
            'pairs'     => [],
            'missing'   => [],
            'total'     => count($punches),
        ];

        if (empty($punches)) {
            $validation['missing'][] = 'Nenhuma marcação encontrada';
            return $validation;
        }

        $types = array_map(
            fn ($punch) => $this->normalizePunchType((string) $punch->punch_type),
            $punches
        );

        if (in_array('entrada', $types, true) && !in_array('saida', $types, true)) {
            $validation['missing'][] = 'Falta marcação de saída';
        }

        if (in_array('intervalo_inicio', $types, true) && !in_array('intervalo_fim', $types, true)) {
            $validation['missing'][] = 'Falta marcação de fim de intervalo';
        }

        $validation['complete'] = empty($validation['missing']);
        $validation['pairs'] = $this->groupPairs($punches);

        return $validation;
    }

    public function calculateHours(int $employeeId, string $date): array
    {
        $calculation = (new \App\Services\Timesheet\TimesheetComputationService())
            ->calculateDailyHours($this->getPunchesByDate($employeeId, $date));

        return [
            'total_work'     => round((float) ($calculation['work_hours'] ?? 0.0), 2),
            'total_interval' => round((float) ($calculation['break_hours'] ?? 0.0), 2),
            'net_work'       => round((float) ($calculation['net_work_hours'] ?? $calculation['work_hours'] ?? 0.0), 2),
            'extra_hours'    => round((float) ($calculation['extra_hours'] ?? 0.0), 2),
            'owed_hours'     => round((float) ($calculation['owed_hours'] ?? 0.0), 2),
            'night_hours'    => round((float) ($calculation['night_hours'] ?? 0.0), 2),
            'pairs'          => count($calculation['pairs'] ?? []),
        ];
    }

    public function getOutsideGeofence(int $employeeId, string $startDate, string $endDate): array
    {
        [$startAt, $endAt] = $this->getDateRangeBounds($startDate, $endDate);

        return $this->where('employee_id', $employeeId)
            ->where('punch_time >=', $startAt)
            ->where('punch_time <', $endAt)
            ->where('within_geofence', false)
            ->findAll();
    }

    public function getByMethod(string $method, ?int $employeeId = null, ?string $date = null): array
    {
        $builder = $this->where('method', $method);

        if ($employeeId !== null) {
            $builder->where('employee_id', $employeeId);
        }

        if ($date !== null) {
            [$startAt, $endAt] = $this->getDayBounds($date);
            $builder->where('punch_time >=', $startAt)
                ->where('punch_time <', $endAt);
        }

        return $builder->orderBy('punch_time', 'ASC')->findAll();
    }

    public function getTodayPunchesCount(): int
    {
        [$startAt, $endAt] = $this->getDayBounds(date('Y-m-d'));

        return $this->where('punch_time >=', $startAt)
            ->where('punch_time <', $endAt)
            ->countAllResults();
    }

    public function verifyHash(object $punch): bool
    {
        return (new TimePunchIntegrityChainService(\Config\Database::connect()))->verify($punch);
    }

    private function getPunchesBetween(int $employeeId, string $startAt, string $endAt): array
    {
        return $this->where('employee_id', $employeeId)
            ->where('punch_time >=', $startAt)
            ->where('punch_time <', $endAt)
            ->orderBy('punch_time', 'ASC')
            ->findAll();
    }

    private function groupPairs(array $punches): array
    {
        $pairs = [];
        $temp = null;

        foreach ($punches as $punch) {
            $normalizedType = $this->normalizePunchType((string) $punch->punch_type);

            if (in_array($normalizedType, ['entrada', 'intervalo_inicio'], true)) {
                $temp = $punch;
                continue;
            }

            if ($temp && in_array($normalizedType, ['saida', 'intervalo_fim'], true)) {
                $pairs[] = [
                    'start' => $temp,
                    'end'   => $punch,
                    'type'  => $normalizedType === 'intervalo_fim' ? 'interval' : 'work',
                ];
                $temp = null;
            }
        }

        return $pairs;
    }
}
