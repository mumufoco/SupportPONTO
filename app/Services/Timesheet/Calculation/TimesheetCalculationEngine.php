<?php

declare(strict_types=1);

namespace App\Services\Timesheet\Calculation;

/**
 * Motor canônico de cálculo de jornada.
 *
 * Centraliza pareamento, intervalo, tolerância, extra/débito e adicional noturno
 * para substituir cálculos simplificados espalhados por models/services.
 */
class TimesheetCalculationEngine
{
    public function __construct(
        private readonly WorkPairingPolicy $pairingPolicy = new WorkPairingPolicy(),
        private readonly JornadaPolicy $jornadaPolicy = new JornadaPolicy(),
        private readonly IntervalPolicy $intervalPolicy = new IntervalPolicy(),
        private readonly TolerancePolicy $tolerancePolicy = new TolerancePolicy(),
        private readonly OvertimePolicy $overtimePolicy = new OvertimePolicy(),
        private readonly NightShiftPolicy $nightShiftPolicy = new NightShiftPolicy(),
    ) {
    }

    /**
     * @param list<object|array<string,mixed>> $punches
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function calculateDay(array $punches, array $context = []): array
    {
        $paired = $this->pairingPolicy->pair($punches);
        $pairs = $paired['pairs'];

        $workHours = 0.0;
        $breakHours = 0.0;
        $workPairs = [];

        foreach ($pairs as $pair) {
            if (($pair['type'] ?? '') === 'work') {
                $workHours += (float) ($pair['hours'] ?? 0.0);
                $workPairs[] = $pair;
            } elseif (($pair['type'] ?? '') === 'break') {
                $breakHours += (float) ($pair['hours'] ?? 0.0);
            }
        }

        $expectedHours = $this->jornadaPolicy->expectedDailyHours($context);
        $toleranceHours = $this->tolerancePolicy->toleranceHours($context);
        $overtime = $this->overtimePolicy->calculate($workHours, $expectedHours, $toleranceHours);
        $intervalViolation = $this->intervalPolicy->violationHours($workHours, $breakHours, $context);
        $nightHours = $this->nightShiftPolicy->calculateNightHours($workPairs);
        $errors = $paired['errors'] ?? [];
        $warnings = $paired['warnings'] ?? [];

        return [
            'total_hours' => round($workHours, 2),
            'work_hours' => round($workHours, 2),
            'break_hours' => round($breakHours, 2),
            'net_work_hours' => round($workHours, 2),
            'expected_hours' => $overtime['expected_hours'],
            'extra_hours' => $overtime['extra_hours'],
            'owed_hours' => $overtime['owed_hours'],
            'delta_hours' => $overtime['delta_hours'],
            'tolerance_hours' => round($toleranceHours, 4),
            'interval_violation_hours' => $intervalViolation,
            'night_hours' => $nightHours,
            'complete' => (bool) ($paired['complete'] ?? false),
            'valid' => $errors === [],
            'errors' => $errors,
            'warnings' => $warnings,
            'pairs' => $pairs,
            'punches' => array_map(static fn ($p): array => [
                'time' => is_array($p) ? ($p['punch_time'] ?? null) : ($p->punch_time ?? null),
                'type' => is_array($p) ? ($p['punch_type'] ?? null) : ($p->punch_type ?? null),
                'method' => is_array($p) ? ($p['method'] ?? null) : ($p->method ?? null),
                'nsr' => is_array($p) ? ($p['nsr'] ?? null) : ($p->nsr ?? null),
            ], $paired['ordered_punches'] ?? $punches),
        ];
    }
}
