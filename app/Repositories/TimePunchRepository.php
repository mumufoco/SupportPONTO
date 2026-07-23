<?php

namespace App\Repositories;

use App\Models\TimePunchModel;

/**
 * MELHORIA 7: Repository Pattern para registros de ponto.
 *
 * Separa queries de domínio do schema do Model.
 * TimePunchModel fica responsável por: schema, validação, callbacks (NSR, hash).
 * TimePunchRepository fica responsável por: queries de negócio e relatórios.
 *
 * Uso:
 *   $repo = new TimePunchRepository();
 *   $punches = $repo->findDailyPunches($employeeId, '2026-01-15');
 *   $pairs   = $repo->findWorkingPairs($employeeId, '2026-01-15');
 */
class TimePunchRepository
{
    public function __construct(
        private readonly TimePunchModel $model = new TimePunchModel()
    ) {}

    /**
     * Registros de ponto de um colaborador em uma data específica.
     *
     * Quando a migration OptimizePunchTimeIndexes estiver aplicada no PostgreSQL,
     * esta consulta passa a aproveitar filtros por intervalo no campo punch_time.
     */
    public function findDailyPunches(int $employeeId, string $date): array
    {
        $db = \Config\Database::connect();

        [$dayStartAt, $dayEndAt] = $this->model->getDayBounds($date);

        return $db->table('time_punches')
                  ->where('employee_id', $employeeId)
                  ->where('punch_time >=', $dayStartAt)
                  ->where('punch_time <', $dayEndAt)
                  ->orderBy('punch_time', 'ASC')
                  ->get()
                  ->getResult();
    }

    /**
     * Registros dos últimos N dias.
     *
     * Quando a migration OptimizePunchTimeIndexes estiver aplicada no PostgreSQL,
     * esta consulta passa a aproveitar o índice parcial idx_tp_recent_by_employee.
     */
    public function findRecentPunches(int $employeeId, int $days = 30): array
    {
        $db   = \Config\Database::connect();
        $from = date('Y-m-d', strtotime("-{$days} days"));

        return $db->table('time_punches')
                  ->where('employee_id', $employeeId)
                  ->where('punch_time >=', $from . ' 00:00:00')
                  ->orderBy('punch_time', 'DESC')
                  ->get()
                  ->getResult();
    }

    /**
     * Registros mensais para um colaborador.
     *
     * Quando a migration OptimizePunchTimeIndexes estiver aplicada no PostgreSQL,
     * esta consulta passa a aproveitar o índice idx_tp_month_trunc.
     */
    public function findMonthlyPunches(int $employeeId, string $yearMonth): array
    {
        $db    = \Config\Database::connect();
        $start = $yearMonth . '-01 00:00:00';
        $end   = date('Y-m-d H:i:s', strtotime($yearMonth . '-01 +1 month'));

        return $db->table('time_punches')
                  ->where('employee_id', $employeeId)
                  ->where('punch_time >=', $start)
                  ->where('punch_time <', $end)
                  ->orderBy('punch_time', 'ASC')
                  ->get()
                  ->getResult();
    }

    /**
     * Último ponto de um colaborador.
     */
    public function findLastPunch(int $employeeId, ?string $date = null): ?object
    {
        $db = \Config\Database::connect();
        $qb = $db->table('time_punches')->where('employee_id', $employeeId);

        if ($date) {
            [$dayStartAt, $dayEndAt] = $this->model->getDayBounds($date);
            $qb->where('punch_time >=', $dayStartAt)
               ->where('punch_time <', $dayEndAt);
        }

        return $qb->orderBy('punch_time', 'DESC')->limit(1)->get()->getRow() ?: null;
    }

    /**
     * Pares entrada/saída de um dia.
     * Delega a lógica de agrupamento ao Model (que contém a regra de negócio MTE).
     */
    public function findWorkingPairs(int $employeeId, string $date): array
    {
        $punches = $this->findDailyPunches($employeeId, $date);
        return $this->groupIntoPairs($punches);
    }

    /**
     * Conta registros por método de autenticação em um período.
     */
    public function countByMethod(string $startDate, string $endDate): array
    {
        $db = \Config\Database::connect();

        $rows = $db->table('time_punches')
                   ->select('method, COUNT(*) as total')
                   ->where('punch_time >=', $startDate . ' 00:00:00')
                   ->where('punch_time <=', $endDate . ' 23:59:59')
                   ->groupBy('method')
                   ->get()
                   ->getResult();

        return array_column($rows, 'total', 'method');
    }

    /**
     * Verifica se há ponto duplicado nos últimos 60 segundos.
     */
    public function hasDuplicateInWindow(int $employeeId, int $windowSeconds = 60): bool
    {
        $db       = \Config\Database::connect();
        $cutoff   = date('Y-m-d H:i:s', time() - $windowSeconds);

        $count = $db->table('time_punches')
                    ->where('employee_id', $employeeId)
                    ->where('punch_time >=', $cutoff)
                    ->countAllResults();

        return $count > 0;
    }

    /**
     * Busca registro pelo NSR para verificação de integridade.
     */
    public function findByNsr(int $nsr): ?object
    {
        return $this->model->where('nsr', (string) $nsr)->first();
    }

    // ── Helpers privados ────────────────────────────────────────────────────

    private function groupIntoPairs(array $punches): array
    {
        $pairs = [];
        $entry = null;

        foreach ($punches as $punch) {
            $type = (string) $punch->punch_type;

            if (in_array($type, ['entrada', 'intervalo_inicio'], true)) {
                $entry = $punch;
            } elseif ($entry && in_array($type, ['saida', 'intervalo_fim'], true)) {
                $pairs[] = ['entry' => $entry, 'exit' => $punch];
                $entry   = null;
            }
        }

        // Entrada sem saída (ponto aberto)
        if ($entry) {
            $pairs[] = ['entry' => $entry, 'exit' => null];
        }

        return $pairs;
    }
}
