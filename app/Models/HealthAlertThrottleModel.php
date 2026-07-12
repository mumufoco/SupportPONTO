<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Controle de "silêncio" entre alertas operacionais repetidos do mesmo
 * (module, severity) — ver migração 2026-06-07-000492_CreateHealthAlertThrottleTable
 * para o racional completo (evitar "alert storm" quando um problema persiste
 * por várias execuções consecutivas do health-check).
 */
class HealthAlertThrottleModel extends Model
{
    protected $table            = 'health_alert_throttle';
    protected $primaryKey       = 'id';
    protected $returnType       = 'object';
    protected $useTimestamps    = false;
    protected $allowedFields    = ['module', 'severity', 'last_alerted_at', 'created_at', 'updated_at'];

    /**
     * Decide se um novo alerta (e-mail/notificação) deve ser efetivamente
     * disparado agora para este (module, severity), com base em quanto tempo
     * se passou desde o último envio.
     *
     * Retorna `true` quando:
     *  - nunca alertamos sobre este (module, severity) antes; OU
     *  - o último alerta foi enviado há mais de `$throttleMinutes` minutos.
     */
    public function shouldAlert(string $module, string $severity, int $throttleMinutes): bool
    {
        $row = $this->where('module', $module)->where('severity', $severity)->first();

        if ($row === null || empty($row->last_alerted_at)) {
            return true;
        }

        $lastAlertedAt = strtotime((string) $row->last_alerted_at);
        if ($lastAlertedAt === false) {
            return true;
        }

        $elapsedMinutes = (time() - $lastAlertedAt) / 60;

        return $elapsedMinutes >= $throttleMinutes;
    }

    /**
     * Registra que um alerta acabou de ser efetivamente enviado para este
     * (module, severity) — "reseta o relógio" do throttle.
     */
    public function markAlerted(string $module, string $severity): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $row = $this->where('module', $module)->where('severity', $severity)->first();

        if ($row !== null) {
            $this->update($row->id, [
                'last_alerted_at' => $now,
                'updated_at'      => $now,
            ]);

            return;
        }

        $this->insert([
            'module'          => $module,
            'severity'        => $severity,
            'last_alerted_at' => $now,
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);
    }

    /**
     * Limpa o registro de throttle de um (module, severity) — usado quando o
     * módulo volta ao normal ("ok"), para que, se o problema ocorrer de novo
     * no futuro, o primeiro alerta seja disparado imediatamente (sem esperar
     * o throttle de uma ocorrência antiga e já resolvida).
     */
    public function clearThrottle(string $module, string $severity): void
    {
        $this->where('module', $module)->where('severity', $severity)->delete();
    }
}
