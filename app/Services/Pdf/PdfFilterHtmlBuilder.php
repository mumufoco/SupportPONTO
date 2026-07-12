<?php

namespace App\Services\Pdf;

use App\Services\Reports\ReportFilterRenderer;

class PdfFilterHtmlBuilder
{
    public function __construct(private readonly ReportFilterRenderer $filterRenderer)
    {
    }

    public function build(array $filters): string
    {
        $html = $this->filterRenderer->renderHtmlFilters($filters);
        if ($html !== '') {
            return $html;
        }

        if (empty($filters)) {
            return '';
        }

        $html = '<div style="background-color: #f8f9fa; padding: 10px; margin-bottom: 15px; border: 1px solid #dee2e6;">';
        $html .= '<strong>Filtros Aplicados:</strong><br>';

        foreach ($filters as $key => $value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $label = ucfirst(str_replace('_', ' ', $key));
            $html .= '<span style="font-size: 8pt;">' . $label . ': ' . esc($value) . ' | </span>';
        }

        return $html . '</div>';
    }
}
