<?php

declare(strict_types=1);

namespace App\Services\Timesheet;

use App\Services\Timesheet\Calculation\TimesheetCalculationEngine;

class TimesheetComputationService
{
    public function __construct(
        private readonly TimesheetCalculationEngine $calculationEngine = new TimesheetCalculationEngine(),
    ) {
    }

    /**
     * Calcula o dia usando o motor canônico da Fase 10.
     *
     * @param list<object|array<string,mixed>> $punches
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function calculateDailyHours(array $punches, array $context = []): array
    {
        return $this->calculationEngine->calculateDay($punches, $context);
    }

    /**
     * @param list<object|array<string,mixed>> $punches
     * @return array{valid:bool,errors:list<string>,warnings:list<string>,complete?:bool}
     */
    public function validatePunchPairs(array $punches): array
    {
        $calculation = $this->calculationEngine->calculateDay($punches);

        return [
            'valid' => (bool) ($calculation['valid'] ?? false),
            'complete' => (bool) ($calculation['complete'] ?? false),
            'errors' => array_values(array_map('strval', $calculation['errors'] ?? [])),
            'warnings' => array_values(array_map('strval', $calculation['warnings'] ?? [])),
        ];
    }

    /** @param list<object|array<string,mixed>> $punches */
    public function getNSRRange(array $punches): array
    {
        if (empty($punches)) {
            return ['first' => null, 'last' => null];
        }

        $nsrs = [];
        foreach ($punches as $punch) {
            $nsr = is_array($punch) ? ($punch['nsr'] ?? null) : ($punch->nsr ?? null);
            if ($nsr !== null && $nsr !== '') {
                $nsrs[] = (int) $nsr;
            }
        }

        if ($nsrs === []) {
            return ['first' => null, 'last' => null];
        }

        return ['first' => min($nsrs), 'last' => max($nsrs)];
    }
}
