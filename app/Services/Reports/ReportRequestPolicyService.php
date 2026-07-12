<?php

namespace App\Services\Reports;

/**
 * Centraliza regras de segurança e performance para geração de relatórios.
 *
 * O objetivo é impedir que controllers, filas e exporters aceitem filtros soltos
 * que possam gerar consultas muito amplas, consumir memória excessiva ou vazar
 * escopo departamental de gestores.
 */
class ReportRequestPolicyService
{
    /** @var list<string> */
    private const DATE_RANGE_TYPES = [
        'folha-ponto',
        'horas-extras',
        'faltas-atrasos',
        'consolidado-mensal',
        'justificativas',
        'advertencias',
        'personalizado',
    ];

    /** @var list<string> */
    private const HEAVY_FILE_FORMATS = ['pdf', 'excel', 'csv', 'xml', 'txt', 'afd'];

    /** @var list<string> */
    private const EXPORT_ONLY_FORMATS = ['pdf', 'excel', 'csv', 'xml', 'txt', 'afd'];

    private const MAX_HTML_DAYS = 366;
    private const MAX_EXPORT_DAYS = 186;
    private const MAX_AFD_DAYS = 92;
    private const MAX_PREVIEW_LIMIT = 50;
    private const DEFAULT_PREVIEW_LIMIT = 10;
    private const MAX_SYNC_ROWS = 1000;

    /**
     * @return array{success:bool,filters?:array,error?:string,status?:int}
     */
    public function normalizeAndValidate(string $type, string $format, array $filters): array
    {
        $format = strtolower(trim($format));
        $filters = $this->normalizeFilters($filters);

        if ($type === 'banco-horas') {
            $filters['start_date'] ??= date('Y-m-01');
            $filters['end_date'] ??= date('Y-m-t');
        }

        if (in_array($type, self::DATE_RANGE_TYPES, true) || in_array($format, self::EXPORT_ONLY_FORMATS, true)) {
            if (empty($filters['start_date']) || empty($filters['end_date'])) {
                return $this->reject('Informe data inicial e data final para gerar este relatório.', 422);
            }
        }

        if (! empty($filters['start_date']) || ! empty($filters['end_date'])) {
            $start = $this->dateOrNull($filters['start_date'] ?? null);
            $end = $this->dateOrNull($filters['end_date'] ?? null);

            if ($start === null || $end === null) {
                return $this->reject('Período inválido. Use datas no formato AAAA-MM-DD.', 422);
            }

            if ($end < $start) {
                return $this->reject('A data final não pode ser anterior à data inicial.', 422);
            }

            $filters['start_date'] = $start;
            $filters['end_date'] = $end;

            $days = $this->daysBetween($start, $end);
            $maxDays = $this->maxDaysFor($format);
            if ($days > $maxDays) {
                return $this->reject("Período muito amplo para {$format}. Limite: {$maxDays} dia(s).", 422);
            }
        }

        if (! empty($filters['limit'])) {
            $filters['limit'] = min(self::MAX_PREVIEW_LIMIT, max(1, (int) $filters['limit']));
        } elseif ($format === 'html') {
            $filters['limit'] = self::MAX_SYNC_ROWS;
        }

        return ['success' => true, 'filters' => $filters];
    }

    /**
     * Aplica escopo departamental já resolvido pela camada de autorização.
     */
    public function applyDepartmentRestriction(array $filters, ?string $departmentRestriction): array
    {
        $departmentRestriction = trim((string) $departmentRestriction);
        if ($departmentRestriction === '') {
            return $filters;
        }

        $filters['department'] = $departmentRestriction;
        $filters['department_restriction_applied'] = true;

        return $filters;
    }

    /**
     * @return array<string,mixed>
     */
    private function normalizeFilters(array $filters): array
    {
        $normalized = [];
        foreach ($filters as $key => $value) {
            $key = trim((string) $key);
            if ($key === '') {
                continue;
            }

            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value === '' || $value === null) {
                continue;
            }

            $normalized[$key] = $value;
        }

        if (! empty($normalized['month']) && (empty($normalized['start_date']) || empty($normalized['end_date']))) {
            $month = preg_match('/^\d{4}-\d{2}$/', (string) $normalized['month']) ? (string) $normalized['month'] : date('Y-m');
            $normalized['start_date'] = $month . '-01';
            $normalized['end_date'] = date('Y-m-t', strtotime($normalized['start_date']));
        }

        if (! empty($normalized['employee_id']) && empty($normalized['employee_ids'])) {
            $normalized['employee_ids'] = [(int) $normalized['employee_id']];
        }

        if (! empty($normalized['employee_ids']) && is_array($normalized['employee_ids'])) {
            $normalized['employee_ids'] = array_values(array_unique(array_filter(array_map('intval', $normalized['employee_ids']))));
        }

        return $normalized;
    }

    private function maxDaysFor(string $format): int
    {
        if ($format === 'afd') {
            return self::MAX_AFD_DAYS;
        }

        if (in_array($format, self::HEAVY_FILE_FORMATS, true)) {
            return self::MAX_EXPORT_DAYS;
        }

        return self::MAX_HTML_DAYS;
    }

    private function dateOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $parts = array_map('intval', explode('-', $value));
        if (! checkdate($parts[1], $parts[2], $parts[0])) {
            return null;
        }

        return $value;
    }

    private function daysBetween(string $start, string $end): int
    {
        $startTs = strtotime($start . ' 00:00:00');
        $endTs = strtotime($end . ' 00:00:00');

        if ($startTs === false || $endTs === false) {
            return 0;
        }

        return (int) floor(($endTs - $startTs) / 86400) + 1;
    }

    /**
     * @return array{success:false,error:string,status:int}
     */
    private function reject(string $message, int $status): array
    {
        return ['success' => false, 'error' => $message, 'status' => $status];
    }
}
