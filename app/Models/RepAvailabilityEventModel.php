<?php

namespace App\Models;

use App\Services\Timesheet\NsrGeneratorService;
use CodeIgniter\Model;

/**
 * Registros imutáveis do tipo "6" do AFD — "Eventos sensíveis do REP" (Portaria MTE
 * 671/2021), restritos aos códigos aplicáveis ao REP-P:
 *   "07": disponibilidade de serviço (o sistema voltou a responder);
 *   "08": indisponibilidade de serviço (o sistema parou de responder).
 *
 * Ver migração 2026-06-07-000490_CreateRepAvailabilityEventsTable para o racional
 * completo de por que esses eventos são DETECTADOS por heartbeat (e não declarados
 * manualmente, como os tipos "2"/"4") — o sistema não pode logar sua própria queda
 * enquanto está fora do ar.
 *
 * Cada par 08/07 detectado consome DOIS NSRs consecutivos da MESMA sequência canônica
 * usada por time_punches/clock_adjustments/company_record_events — gerados atomicamente
 * por NsrGeneratorService — preservando a ordenação global por NSR exigida pelo AFD.
 */
class RepAvailabilityEventModel extends Model
{
    public const EVENT_AVAILABLE   = '07';
    public const EVENT_UNAVAILABLE = '08';

    protected $table            = 'rep_availability_events';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'nsr',
        'event_code',
        'recorded_at',
        'detected_at',
        'gap_seconds',
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
     * encadeamento de time_punches/clock_adjustments/company_record_events.
     */
    protected function generateHash(array $data): array
    {
        $payload = $data['data'];

        $material = implode('|', [
            $payload['nsr'] ?? '',
            $payload['event_code'] ?? '',
            $payload['recorded_at'] ?? '',
            $payload['detected_at'] ?? '',
            $payload['gap_seconds'] ?? '',
        ]);

        $data['data']['hash'] = hash('sha256', $material);

        return $data;
    }

    /**
     * Registra, em uma única transação, o par de eventos "08" (início da janela de
     * indisponibilidade) e "07" (fim da janela / retorno da disponibilidade), cada um
     * consumindo um NSR canônico consecutivo — "08" primeiro (cronologicamente anterior),
     * depois "07", preservando a ordenação global exigida pelo leiaute do AFD.
     *
     * Caminho único e oficial de inserção destes registros — chamado exclusivamente por
     * RepAvailabilityMonitorService quando uma lacuna no heartbeat é detectada.
     *
     * @return array{unavailable_id:int, available_id:int}
     */
    public function recordOutageWindow(string $unavailableAt, string $availableAt, string $detectedAt, int $gapSeconds): array
    {
        $db = \Config\Database::connect();
        $db->transBegin();

        try {
            $generator = new NsrGeneratorService($db);
            $generator->assertReady();

            $unavailableNsr = $generator->next('rep_availability_events:unavailable');
            $availableNsr   = $generator->next('rep_availability_events:available');

            $unavailableId = $this->insert([
                'nsr'         => $unavailableNsr,
                'event_code'  => self::EVENT_UNAVAILABLE,
                'recorded_at' => $unavailableAt,
                'detected_at' => $detectedAt,
                'gap_seconds' => $gapSeconds,
            ]);

            if (! $unavailableId) {
                throw new \RuntimeException('Falha ao registrar evento de indisponibilidade (08): ' . json_encode($this->errors(), JSON_UNESCAPED_UNICODE));
            }

            $availableId = $this->insert([
                'nsr'         => $availableNsr,
                'event_code'  => self::EVENT_AVAILABLE,
                'recorded_at' => $availableAt,
                'detected_at' => $detectedAt,
                'gap_seconds' => $gapSeconds,
            ]);

            if (! $availableId) {
                throw new \RuntimeException('Falha ao registrar evento de disponibilidade (07): ' . json_encode($this->errors(), JSON_UNESCAPED_UNICODE));
            }

            $db->transCommit();

            return ['unavailable_id' => (int) $unavailableId, 'available_id' => (int) $availableId];
        } catch (\Throwable $e) {
            $db->transRollback();

            throw $e;
        }
    }

    /**
     * @return object[] Eventos sensíveis cujo `recorded_at` cai dentro do período informado,
     *                  ordenados por NSR — prontos para serem mesclados com os demais
     *                  registros na geração do AFD (mesma ordenação exigida pelo leiaute).
     */
    public function findInPeriod(string $startDate, string $endDate): array
    {
        return $this->where('recorded_at >=', $startDate)
            ->where('recorded_at <=', $endDate)
            ->orderBy('nsr', 'ASC')
            ->findAll();
    }
}
