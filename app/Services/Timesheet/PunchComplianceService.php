<?php

namespace App\Services\Timesheet;

use App\Enums\PunchViolationType;
use App\Models\EmployeeModel;
use App\Models\PunchRuleViolationModel;
use App\Models\SettingModel;
use App\Models\TimePunchModel;

/**
 * Detecta automaticamente irregularidades trabalhistas (CLT) a partir das
 * marcações de ponto de um colaborador em uma data. Roda de forma reativa
 * a cada ponto registrado (ver app/Listeners/PunchComplianceListener.php),
 * nunca bloqueia o registro do ponto em si (best-effort, exceções são
 * logadas e engolidas).
 *
 * Regras 1 (intervalo intrajornada) e 4 (jornada extraordinária) reaproveitam
 * o motor de cálculo já existente (TimesheetComputationService) — não
 * reimplementam a matemática de intervalo/hora extra, só interpretam o
 * resultado já calculado (interval_violation_hours / extra_hours).
 *
 * Regras 2 (interjornada) e 3 (descanso semanal) usam fronteira de dia
 * corrido (calendário), não uma janela de 24h exata a partir do horário de
 * cada marcação — pode divergir em turnos que cruzam a meia-noite. Aceito
 * como simplificação de uma primeira versão.
 */
class PunchComplianceService
{
    public function __construct(
        private readonly TimePunchModel $timePunchModel = new TimePunchModel(),
        private readonly EmployeeModel $employeeModel = new EmployeeModel(),
        private readonly PunchRuleViolationModel $violationModel = new PunchRuleViolationModel(),
        private readonly TimesheetComputationService $computationService = new TimesheetComputationService(),
        private readonly SettingModel $settingModel = new SettingModel(),
    ) {
    }

    public function evaluateAndPersist(int $employeeId, string $date): void
    {
        try {
            $employee = $this->employeeModel->find($employeeId);
            if (! $employee) {
                return;
            }

            // Janela de 7 dias (regra do descanso semanal) + 1 dia extra para
            // conseguir comparar com o turno anterior (regra da interjornada).
            $windowStart = date('Y-m-d', strtotime($date . ' -7 days'));
            $grouped = $this->punchesGroupedByDate($employeeId, $windowStart, $date);

            $this->evaluateDailyRules($employee, $date, $grouped[$date] ?? []);
            $this->evaluateInterjornada($employeeId, $date, $grouped);
            $this->evaluateDescansoSemanal($employeeId, $date, $grouped);
        } catch (\Throwable $e) {
            log_message('warning', '[PunchComplianceService] ' . $e->getMessage());
        }
    }

    /** Regras 1 (Art. 71 CLT) e 4 (Art. 58/59 CLT). */
    private function evaluateDailyRules(object $employee, string $date, array $todayPunches): void
    {
        $employeeId = (int) $employee->id;

        if ($todayPunches === []) {
            $this->violationModel->clearIfPending($employeeId, PunchViolationType::IntervaloIntrajornada, $date);
            $this->violationModel->clearIfPending($employeeId, PunchViolationType::JornadaExtraordinaria, $date);
            return;
        }

        $toleranceMinutes = (int) $this->settingModel->get('tolerance_minutes', 10);
        $result = $this->computationService->calculateDailyHours($todayPunches, [
            'employee' => $employee,
            'tolerance_minutes' => $toleranceMinutes,
        ]);

        $intervalViolationHours = (float) ($result['interval_violation_hours'] ?? 0);
        if ($intervalViolationHours > 0) {
            $this->violationModel->upsertPending($employeeId, PunchViolationType::IntervaloIntrajornada, $date, [
                'intervalo_faltando_minutos' => (int) round($intervalViolationHours * 60),
                'horas_trabalhadas' => $result['work_hours'] ?? null,
            ]);
        } else {
            $this->violationModel->clearIfPending($employeeId, PunchViolationType::IntervaloIntrajornada, $date);
        }

        $maxDailyOvertimeHours = (float) $this->settingModel->get('max_daily_overtime_hours', 2);
        $extraHours = (float) ($result['extra_hours'] ?? 0);
        if ($extraHours > $maxDailyOvertimeHours) {
            $this->violationModel->upsertPending($employeeId, PunchViolationType::JornadaExtraordinaria, $date, [
                'horas_extras' => $extraHours,
                'limite_diario_horas' => $maxDailyOvertimeHours,
            ]);
        } else {
            $this->violationModel->clearIfPending($employeeId, PunchViolationType::JornadaExtraordinaria, $date);
        }
    }

    /** Regra 2 (Art. 66 CLT) — mínimo de horas consecutivas entre o fim de um turno e o início do próximo. */
    private function evaluateInterjornada(int $employeeId, string $date, array $grouped): void
    {
        $previousDate = date('Y-m-d', strtotime($date . ' -1 day'));
        $previousDayPunches = $grouped[$previousDate] ?? [];
        $todayPunches = $grouped[$date] ?? [];

        if ($previousDayPunches === [] || $todayPunches === []) {
            $this->violationModel->clearIfPending($employeeId, PunchViolationType::Interjornada, $date);
            return;
        }

        $lastPunchPrevDay = end($previousDayPunches);
        $firstPunchToday = $todayPunches[0];

        $gapHours = (strtotime((string) $firstPunchToday->punch_time) - strtotime((string) $lastPunchPrevDay->punch_time)) / 3600;
        $minInterjornadaHours = (float) $this->settingModel->get('min_interjornada_hours', 11);

        if ($gapHours < $minInterjornadaHours) {
            $this->violationModel->upsertPending($employeeId, PunchViolationType::Interjornada, $date, [
                'descanso_horas' => round($gapHours, 2),
                'minimo_exigido_horas' => $minInterjornadaHours,
                'fim_turno_anterior' => $lastPunchPrevDay->punch_time,
                'inicio_turno_atual' => $firstPunchToday->punch_time,
            ]);
        } else {
            $this->violationModel->clearIfPending($employeeId, PunchViolationType::Interjornada, $date);
        }
    }

    /** Regra 3 (Art. 67 CLT) — ao menos 1 dia livre de marcação a cada 7 dias corridos. */
    private function evaluateDescansoSemanal(int $employeeId, string $date, array $grouped): void
    {
        $windowStart = date('Y-m-d', strtotime($date . ' -6 days'));
        $hasRestDay = false;

        for ($cursor = $windowStart; $cursor <= $date; $cursor = date('Y-m-d', strtotime($cursor . ' +1 day'))) {
            if (empty($grouped[$cursor])) {
                $hasRestDay = true;
                break;
            }
        }

        if (! $hasRestDay) {
            $this->violationModel->upsertPending($employeeId, PunchViolationType::DescansoSemanal, $date, [
                'janela_inicio' => $windowStart,
                'janela_fim' => $date,
            ]);
        } else {
            $this->violationModel->clearIfPending($employeeId, PunchViolationType::DescansoSemanal, $date);
        }
    }

    /** @return array<string,list<object>> marcações agrupadas por data (Y-m-d) */
    private function punchesGroupedByDate(int $employeeId, string $startDate, string $endDate): array
    {
        [$start] = $this->timePunchModel->getDayBounds($startDate);
        [, $end] = $this->timePunchModel->getDayBounds($endDate);

        $punches = $this->timePunchModel
            ->where('employee_id', $employeeId)
            ->where('punch_time >=', $start)
            ->where('punch_time <', $end)
            ->orderBy('punch_time', 'ASC')
            ->findAll();

        $grouped = [];
        foreach ($punches as $punch) {
            $grouped[date('Y-m-d', strtotime((string) $punch->punch_time))][] = $punch;
        }

        return $grouped;
    }
}
