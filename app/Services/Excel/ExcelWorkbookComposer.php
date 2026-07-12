<?php

namespace App\Services\Excel;

class ExcelWorkbookComposer
{
    public function __construct(
        private readonly ExcelOperationalReportBuilder $operationalBuilder,
        private readonly ExcelAdministrativeReportBuilder $administrativeBuilder,
    ) {
    }

    public function compose(string $type, array $data, array $filters = []): array
    {
        return match ($type) {
            'folha-ponto' => $this->operationalBuilder->timesheet($data, $filters),
            'horas-extras' => $this->operationalBuilder->overtime($data, $filters),
            'faltas-atrasos' => $this->operationalBuilder->absence($data, $filters),
            'banco-horas' => $this->operationalBuilder->bankHours($data, $filters),
            'consolidado-mensal' => $this->operationalBuilder->consolidated($data, $filters),
            'justificativas' => $this->administrativeBuilder->justifications($data, $filters),
            'advertencias' => $this->administrativeBuilder->warnings($data, $filters),
            'personalizado' => $this->administrativeBuilder->custom($data, $filters),
            default => ['success' => false, 'error' => 'Tipo de relatório inválido'],
        };
    }
}
