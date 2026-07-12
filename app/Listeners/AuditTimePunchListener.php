<?php

namespace App\Listeners;

use App\Events\TimePunchRegistered;
use App\Models\AuditModel;
use App\Enums\AuditLevel;

/**
 * MELHORIA 3: Listener de auditoria para ponto registrado.
 *
 * Registra o evento no audit_log de forma desacoplada do PunchService.
 */
class AuditTimePunchListener
{
    public function handle(TimePunchRegistered $event): void
    {
        try {
            $audit = new AuditModel();
            $audit->log(
                $event->employeeId,
                'PUNCH_REGISTERED',
                'time_punches',
                $event->punchId,
                null,
                [
                    'punch_type'     => $event->punchType->value,
                    'method'         => $event->method->value,
                    'nsr'            => $event->nsr,
                    'within_geofence'=> $event->withinGeofence,
                    'geofence_name'  => $event->geofenceName,
                ],
                "Ponto registrado: {$event->punchType->label()} via {$event->method->label()} — NSR {$event->nsr}",
                AuditLevel::Info->value
            );
        } catch (\Throwable $e) {
            log_message('error', '[AuditListener] Falha ao auditar ponto: ' . $e->getMessage());
        }
    }
}
