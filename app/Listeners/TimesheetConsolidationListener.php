<?php

namespace App\Listeners;

use App\Events\TimePunchRegistered;
use App\Services\Timesheet\TimesheetDailyConsolidationService;

/**
 * Marca ou recalcula a consolidação diária quando um ponto é registrado.
 *
 * Falhas neste listener nunca bloqueiam o registro do ponto: a marcação já foi
 * gravada e o sistema pode recalcular o dia posteriormente por CLI/job.
 */
class TimesheetConsolidationListener
{
    public function handle(TimePunchRegistered $event): void
    {
        try {
            $day = date('Y-m-d', strtotime($event->punchTime));
            TimesheetDailyConsolidationService::class;
            (new TimesheetDailyConsolidationService())->markDayForRecalculation($event->employeeId, $day);
        } catch (\Throwable $e) {
            log_message('warning', '[TimesheetConsolidationListener] ' . $e->getMessage());
        }
    }
}
