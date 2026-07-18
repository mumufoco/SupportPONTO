<?php

namespace App\Listeners;

use App\Events\TimePunchRegistered;
use App\Services\Timesheet\PunchComplianceService;

/**
 * Roda a detecção de irregularidades trabalhistas (CLT) quando um ponto é
 * registrado.
 *
 * Falhas neste listener nunca bloqueiam o registro do ponto — a marcação já
 * foi gravada; mesmo padrão de app/Listeners/TimesheetConsolidationListener.php.
 */
class PunchComplianceListener
{
    public function handle(TimePunchRegistered $event): void
    {
        try {
            $date = date('Y-m-d', strtotime($event->punchTime));
            (new PunchComplianceService())->evaluateAndPersist($event->employeeId, $date);
        } catch (\Throwable $e) {
            log_message('warning', '[PunchComplianceListener] ' . $e->getMessage());
        }
    }
}
