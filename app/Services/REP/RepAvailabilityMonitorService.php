<?php

namespace App\Services\REP;

use App\Models\RepAvailabilityEventModel;
use Config\Database;

/**
 * Detecta e registra eventos sensíveis do REP-P (registro tipo "6" do AFD, códigos
 * "07"/"08" — Portaria MTE 671/2021) por meio de um heartbeat periódico.
 *
 * POR QUE DETECÇÃO E NÃO DECLARAÇÃO MANUAL (diferente dos tipos "2"/"4"):
 * o sistema não pode logar sua própria indisponibilidade enquanto está fora do ar —
 * por definição, nada roda nesse intervalo. A única estratégia viável é COMPARAR
 * execuções de um heartbeat periódico (ver RepHeartbeatCommand, agendado via cron a
 * cada minuto): se o "último sinal de vida" registrado é mais antigo do que o
 * intervalo esperado por uma margem de segurança, conclui-se que houve uma janela de
 * indisponibilidade entre os dois instantes.
 *
 * Quando uma lacuna é detectada, registra o PAR de eventos imutáveis em uma única
 * transação:
 *   "08" (indisponibilidade) — instante estimado em que o serviço parou de responder
 *        (o último heartbeat confirmado, `last_seen_at`);
 *   "07" (disponibilidade)   — instante em que o serviço voltou a responder (agora).
 *
 * Cada chamada de heartbeat também atualiza `rep_heartbeat.last_seen_at` para o
 * instante atual, fechando o ciclo de detecção.
 */
class RepAvailabilityMonitorService
{
    /**
     * Intervalo esperado entre heartbeats (cron a cada 1 minuto).
     */
    private const EXPECTED_INTERVAL_SECONDS = 60;

    /**
     * Margem de tolerância antes de considerar uma lacuna como indisponibilidade real
     * (evita falsos positivos por atraso momentâneo do cron/SO — exige ao menos
     * ~3x o intervalo esperado sem sinal de vida).
     */
    private const GAP_THRESHOLD_SECONDS = 180;

    public function __construct(private ?RepAvailabilityEventModel $availabilityModel = null)
    {
        $this->availabilityModel = $availabilityModel ?? new RepAvailabilityEventModel();
    }

    /**
     * Executa um ciclo de heartbeat: compara o "último sinal de vida" com o instante
     * atual, registra o par 08/07 se uma lacuna significativa for detectada, e
     * atualiza `rep_heartbeat.last_seen_at`.
     *
     * @return array{gap_detected:bool, gap_seconds:int, last_seen_at:?string, now:string}
     */
    public function heartbeat(): array
    {
        $db  = Database::connect();
        $now = date('Y-m-d H:i:s');

        $row = $db->table('rep_heartbeat')->where('id', 1)->get()->getRow();

        $lastSeenAt = $row->last_seen_at ?? null;
        $gapSeconds = 0;
        $gapDetected = false;

        if ($lastSeenAt !== null && $lastSeenAt !== '') {
            $gapSeconds = max(0, strtotime($now) - strtotime($lastSeenAt));

            if ($gapSeconds > self::GAP_THRESHOLD_SECONDS) {
                $gapDetected = true;

                $this->availabilityModel->recordOutageWindow(
                    unavailableAt: $lastSeenAt,
                    availableAt: $now,
                    detectedAt: $now,
                    gapSeconds: $gapSeconds
                );

                log_message(
                    'warning',
                    "REP heartbeat: lacuna de indisponibilidade detectada ({$gapSeconds}s) entre {$lastSeenAt} e {$now}. "
                    . 'Registros tipo "6" (08/07) gravados no AFD.'
                );
            }
        }

        $db->table('rep_heartbeat')
            ->where('id', 1)
            ->update(['last_seen_at' => $now, 'updated_at' => $now]);

        return [
            'gap_detected' => $gapDetected,
            'gap_seconds'  => $gapSeconds,
            'last_seen_at' => $lastSeenAt,
            'now'          => $now,
        ];
    }
}
