<?php

namespace App\Services\Pdf;

class PdfReportContentComposer
{
    public function __construct(
        private readonly PdfOperationalReportContentBuilder $operational,
        private readonly PdfAdministrativeReportContentBuilder $administrative,
    ) {
    }

    public function compose(string $type, array $data, array $filters = []): array
    {
        return match ($type) {
            'folha-ponto' => $this->operational->timesheet($data, $filters),
            'horas-extras' => $this->operational->overtime($data, $filters),
            'faltas-atrasos' => $this->operational->absence($data, $filters),
            'banco-horas' => $this->operational->bankHours($data, $filters),
            'consolidado-mensal' => $this->operational->consolidated($data, $filters),
            'justificativas' => $this->administrative->justifications($data, $filters),
            'advertencias' => $this->administrative->warnings($data, $filters),
            'personalizado' => $this->administrative->custom($data, $filters),
            default => ['success' => false, 'error' => 'Tipo de relatório inválido'],
        };
    }
}
