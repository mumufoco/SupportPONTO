<?php

declare(strict_types=1);

namespace App\Services\Timesheet;

use App\Models\EmployeeModel;
use App\Models\JustificationModel;
use App\Models\TimePunchModel;
use App\Models\TimesheetConsolidatedModel;

/**
 * Consolida um dia de ponto em uma linha única e auditável.
 *
 * O serviço é propositalmente pequeno e independente de controller para poder ser
 * chamado pelo listener, por comandos CLI, por jobs futuros e por testes de
 * release sem duplicar regra de cálculo.
 */
class TimesheetDailyConsolidationService
{
    public function __construct(
        private readonly TimePunchModel $timePunchModel = new TimePunchModel(),
        private readonly TimesheetConsolidatedModel $consolidatedModel = new TimesheetConsolidatedModel(),
        private readonly EmployeeModel $employeeModel = new EmployeeModel(),
        private readonly JustificationModel $justificationModel = new JustificationModel(),
        private readonly TimesheetComputationService $computationService = new TimesheetComputationService(),
    ) {
    }

    /**
     * Recalcula e grava a consolidação diária.
     *
     * @return array<string,mixed>
     */
    public function consolidateDay(int $employeeId, string $date, bool $markProcessed = true): array
    {
        if (! $this->consolidatedModel->tableExists()) {
            return [
                'success' => false,
                'status' => 503,
                'message' => 'Tabela de consolidação de ponto indisponível.',
            ];
        }

        $employee = $this->employeeModel->find($employeeId);
        if (! $employee) {
            return [
                'success' => false,
                'status' => 404,
                'message' => 'Funcionário não encontrado para consolidação.',
            ];
        }

        [$dayStartAt, $dayEndAt] = $this->timePunchModel->getDayBounds($date);
        $punches = $this->timePunchModel
            ->where('employee_id', $employeeId)
            ->where('punch_time >=', $dayStartAt)
            ->where('punch_time <', $dayEndAt)
            ->orderBy('punch_time', 'ASC')
            ->findAll();

        // Turno que atravessa a meia-noite: um par entrada/saída (ou início/fim de intervalo)
        // dividido estritamente por dia civil fica com metade "aberta" em cada dia, gerando
        // 0h trabalhadas e débito integral duplicado nos dois dias (ver auditoria CRIT-04). O
        // turno inteiro é atribuído ao dia em que começou.
        $punches = $this->appendCrossMidnightClosingPunch($employeeId, $dayEndAt, $punches);
        $punches = $this->stripCrossMidnightOpeningConsumedByPreviousDay($employeeId, $dayStartAt, $punches);

        $calculation = $this->computationService->calculateDailyHours($punches, ['employee' => $employee]);
        $validation = $this->computationService->validatePunchPairs($punches);
        $justification = $this->findApprovedJustification($employeeId, $date);
        $expected = (float) ($calculation['expected_hours'] ?? $this->expectedDailyHours($employee));
        $worked = (float) ($calculation['total_hours'] ?? 0.0);
        $extra = (float) ($calculation['extra_hours'] ?? max(0.0, $worked - $expected));
        $owed = (float) ($calculation['owed_hours'] ?? max(0.0, $expected - $worked));
        $incomplete = ! (bool) ($calculation['complete'] ?? false) || ! ($validation['valid'] ?? false);

        if ($justification !== null) {
            $owed = 0.0;
        }

        $payload = [
            'employee_id' => $employeeId,
            'date' => $date,
            'total_worked' => round($worked, 2),
            'expected' => round($expected, 2),
            'extra' => round($extra, 2),
            'owed' => round($owed, 2),
            'interval_violation' => round((float) ($calculation['interval_violation_hours'] ?? 0.0), 2),
            'justified' => $justification !== null,
            'incomplete' => $incomplete,
            'justification_id' => $justification->id ?? null,
            'punches_count' => count($punches),
            'first_punch' => $this->firstPunchTime($punches),
            'last_punch' => $this->lastPunchTime($punches),
            'total_interval' => round((float) ($calculation['break_hours'] ?? 0.0), 2),
            'notes' => $this->buildNotes($validation, $calculation),
            'needs_recalculation' => ! $markProcessed,
            'processed_at' => $markProcessed ? date('Y-m-d H:i:s') : null,
        ];

        $existing = $this->consolidatedModel
            ->where('employee_id', $employeeId)
            ->where('date', $date)
            ->first();

        if ($existing) {
            $this->consolidatedModel->update((int) $existing->id, $payload);
            $id = (int) $existing->id;
        } else {
            $id = (int) $this->consolidatedModel->insert($payload, true);
        }

        if ($id <= 0) {
            return [
                'success' => false,
                'status' => 500,
                'message' => 'Falha ao gravar consolidação diária.',
                'errors' => $this->consolidatedModel->errors(),
            ];
        }

        return [
            'success' => true,
            'status' => 200,
            'message' => 'Dia consolidado com sucesso.',
            'data' => [
                'id' => $id,
                'employee_id' => $employeeId,
                'date' => $date,
                'total_worked' => $payload['total_worked'],
                'expected' => $payload['expected'],
                'extra' => $payload['extra'],
                'owed' => $payload['owed'],
                'incomplete' => $payload['incomplete'],
                'justified' => $payload['justified'],
            ],
        ];
    }

    /**
     * Apenas marca a linha como pendente. Se ela ainda não existir, cria uma
     * linha mínima para o job/CLI recalcular depois.
     */
    public function markDayForRecalculation(int $employeeId, string $date): void
    {
        if (! $this->consolidatedModel->tableExists()) {
            return;
        }

        $existing = $this->consolidatedModel
            ->where('employee_id', $employeeId)
            ->where('date', $date)
            ->first();

        if ($existing) {
            $this->consolidatedModel->update((int) $existing->id, [
                'needs_recalculation' => true,
                'processed_at' => null,
            ]);
            return;
        }

        $this->consolidatedModel->insert([
            'employee_id' => $employeeId,
            'date' => $date,
            'total_worked' => 0.0,
            'expected' => $this->expectedDailyHours($this->employeeModel->find($employeeId)),
            'extra' => 0.0,
            'owed' => 0.0,
            'interval_violation' => 0.0,
            'justified' => false,
            'incomplete' => true,
            'punches_count' => 0,
            'total_interval' => 0.0,
            'notes' => 'Pendente de recálculo após registro de ponto.',
            'needs_recalculation' => true,
            'processed_at' => null,
        ]);
    }

    /**
     * Se o dia termina com uma abertura sem fechamento (entrada ou início de intervalo sem o
     * par correspondente), busca a primeira marcação após o fim do dia civil — não o dia
     * seguinte inteiro, para não puxar acidentalmente o início de um turno novo — e a inclui
     * neste cálculo, desde que seja exatamente o fechamento esperado para o tipo em aberto.
     *
     * @param list<object> $punches
     * @return list<object>
     */
    private function appendCrossMidnightClosingPunch(int $employeeId, string $dayEndAt, array $punches): array
    {
        if ($punches === []) {
            return $punches;
        }

        $lastType = $this->timePunchModel->normalizePunchType((string) $punches[array_key_last($punches)]->punch_type);
        if (! in_array($lastType, ['entrada', 'intervalo_inicio'], true)) {
            return $punches;
        }

        $closing = $this->timePunchModel
            ->where('employee_id', $employeeId)
            ->where('punch_time >=', $dayEndAt)
            ->orderBy('punch_time', 'ASC')
            ->first();

        if ($closing === null) {
            return $punches;
        }

        $closingType = $this->timePunchModel->normalizePunchType((string) $closing->punch_type);
        $expectedClosing = $lastType === 'entrada' ? 'saida' : 'intervalo_fim';

        if ($closingType !== $expectedClosing) {
            return $punches;
        }

        $punches[] = $closing;

        return $punches;
    }

    /**
     * Se o dia começa com um fechamento (saída/fim de intervalo) sem abertura correspondente
     * dentro do próprio dia, e a marcação imediatamente anterior ao início do dia é a abertura
     * esperada, esse par já foi consumido por appendCrossMidnightClosingPunch() ao consolidar
     * o dia anterior — remove daqui para não contar o mesmo turno duas vezes.
     *
     * @param list<object> $punches
     * @return list<object>
     */
    private function stripCrossMidnightOpeningConsumedByPreviousDay(int $employeeId, string $dayStartAt, array $punches): array
    {
        if ($punches === []) {
            return $punches;
        }

        $firstType = $this->timePunchModel->normalizePunchType((string) $punches[0]->punch_type);
        if (! in_array($firstType, ['saida', 'intervalo_fim'], true)) {
            return $punches;
        }

        $previous = $this->timePunchModel
            ->where('employee_id', $employeeId)
            ->where('punch_time <', $dayStartAt)
            ->orderBy('punch_time', 'DESC')
            ->first();

        if ($previous === null) {
            return $punches;
        }

        $previousType = $this->timePunchModel->normalizePunchType((string) $previous->punch_type);
        $expectedOpening = $firstType === 'saida' ? 'entrada' : 'intervalo_inicio';

        if ($previousType !== $expectedOpening) {
            return $punches;
        }

        array_shift($punches);

        return $punches;
    }

    private function expectedDailyHours(?object $employee): float
    {
        $candidate = $employee->daily_hours
            ?? $employee->expected_daily_hours
            ?? $employee->jornada_diaria
            ?? null;

        if (is_numeric($candidate) && (float) $candidate > 0) {
            return round((float) $candidate, 2);
        }

        return 8.0;
    }

    private function findApprovedJustification(int $employeeId, string $date): ?object
    {
        return $this->justificationModel
            ->where('employee_id', $employeeId)
            ->groupStart()
                ->where('justification_date', $date)
                ->orWhere('date', $date)
            ->groupEnd()
            ->groupStart()
                ->where('status', 'approved')
                ->orWhere('status', 'aprovado')
            ->groupEnd()
            ->first();
    }

    /** @param list<object> $punches */
    private function hasIncompleteDay(array $punches): bool
    {
        if ($punches === []) {
            return true;
        }

        $types = array_map(static fn (object $punch): string => (string) ($punch->punch_type ?? ''), $punches);

        return in_array('entrada', $types, true) && ! in_array('saida', $types, true);
    }

    /** @param list<object> $punches */
    private function firstPunchTime(array $punches): ?string
    {
        if ($punches === []) {
            return null;
        }

        return date('H:i:s', strtotime((string) $punches[0]->punch_time));
    }

    /** @param list<object> $punches */
    private function lastPunchTime(array $punches): ?string
    {
        if ($punches === []) {
            return null;
        }

        $last = $punches[array_key_last($punches)];
        return date('H:i:s', strtotime((string) $last->punch_time));
    }

    /** @param array<string,mixed> $validation */
    private function buildNotes(array $validation, array $calculation = []): ?string
    {
        $items = array_merge($validation['errors'] ?? [], $validation['warnings'] ?? []);

        if ((float) ($calculation['interval_violation_hours'] ?? 0.0) > 0) {
            $items[] = 'Intervalo mínimo não cumprido: ' . round((float) $calculation['interval_violation_hours'], 2) . 'h.';
        }

        if ((float) ($calculation['night_hours'] ?? 0.0) > 0) {
            $items[] = 'Horas em período noturno: ' . round((float) $calculation['night_hours'], 2) . 'h.';
        }

        if ($items === []) {
            return null;
        }

        return implode(' | ', array_map('strval', $items));
    }
}
