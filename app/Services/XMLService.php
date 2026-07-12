<?php

namespace App\Services;

use SimpleXMLElement;

/**
 * XML Service
 *
 * Generates XML reports with proper structure and validation
 * Compatible with external systems and provides valid, well-formed XML
 */
class XMLService
{
    protected $companyName;
    protected $companyCNPJ;

    public function __construct()
    {
        // Load settings safely with fallback to defaults
        try {
            $settingModel = new \App\Models\SettingModel();
            $this->companyName = $settingModel->get('company_name', 'Sistema de Ponto Eletrônico');
            $this->companyCNPJ = $settingModel->get('company_cnpj', '00000000000000');
        } catch (\Throwable $e) {
            log_message('warning', 'Could not load settings in XMLService, using defaults: ' . $e->getMessage());
            $this->companyName = 'Sistema de Ponto Eletrônico';
            $this->companyCNPJ = '00000000000000';
        }
    }

    /**
     * Generate report XML based on type
     *
     * @param string $type Report type
     * @param array $data Report data
     * @param array $filters Applied filters
     * @return array Result with file path or error
     */
    public function generateReport(string $type, array $data, array $filters = []): array
    {
        try {
            switch ($type) {
                case 'folha-ponto':
                    return $this->generateTimesheetXML($data, $filters);
                case 'horas-extras':
                    return $this->generateOvertimeXML($data, $filters);
                case 'faltas-atrasos':
                    return $this->generateAbsenceXML($data, $filters);
                case 'banco-horas':
                    return $this->generateBankHoursXML($data, $filters);
                case 'consolidado-mensal':
                    return $this->generateConsolidatedXML($data, $filters);
                case 'justificativas':
                    return $this->generateJustificationsXML($data, $filters);
                case 'advertencias':
                    return $this->generateWarningsXML($data, $filters);
                case 'personalizado':
                    return $this->generateCustomXML($data, $filters);
                default:
                    return [
                        'success' => false,
                        'error' => 'Tipo de relatório inválido'
                    ];
            }
        } catch (\Throwable $e) {
            log_message('error', 'XML generation error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Erro ao gerar XML',
                'details' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate timesheet XML
     */
    protected function generateTimesheetXML(array $data, array $filters): array
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><relatorio></relatorio>');
        
        // Metadata
        $metadata = $xml->addChild('metadados');
        $metadata->addChild('tipo', 'folha-ponto');
        $metadata->addChild('data_geracao', date('Y-m-d\TH:i:s'));
        $metadata->addChild('empresa', htmlspecialchars($this->companyName));
        $metadata->addChild('cnpj', $this->companyCNPJ);
        
        // Applied filters
        $filtrosNode = $metadata->addChild('filtros');
        foreach ($filters as $key => $value) {
            if (is_scalar($value)) {
                $filtrosNode->addChild($key, htmlspecialchars((string)$value));
            }
        }
        
        // Data
        $dadosNode = $xml->addChild('dados');
        $dadosNode->addAttribute('total_registros', count($data));
        
        foreach ($data as $record) {
            $registroNode = $dadosNode->addChild('registro');
            foreach ($record as $field => $value) {
                if (is_scalar($value)) {
                    $registroNode->addChild($field, htmlspecialchars((string)$value));
                }
            }
        }
        
        // Summary statistics
        $summary = $xml->addChild('resumo');
        $totalWorked = array_sum(array_column($data, 'total_worked'));
        $totalExpected = array_sum(array_column($data, 'expected'));
        $totalBalance = $totalWorked - $totalExpected;
        
        $summary->addChild('total_trabalhado', number_format($totalWorked, 2));
        $summary->addChild('total_esperado', number_format($totalExpected, 2));
        $summary->addChild('saldo_total', number_format($totalBalance, 2));
        
        return $this->saveXML($xml, 'folha_ponto');
    }

    /**
     * Generate overtime XML
     */
    protected function generateOvertimeXML(array $data, array $filters): array
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><relatorio></relatorio>');
        
        $metadata = $xml->addChild('metadados');
        $metadata->addChild('tipo', 'horas-extras');
        $metadata->addChild('data_geracao', date('Y-m-d\TH:i:s'));
        $metadata->addChild('empresa', htmlspecialchars($this->companyName));
        $metadata->addChild('cnpj', $this->companyCNPJ);
        
        $this->addFiltersToXML($metadata, $filters);
        
        $dadosNode = $xml->addChild('dados');
        foreach ($data as $record) {
            $registroNode = $dadosNode->addChild('registro');
            $this->addRecordToXML($registroNode, $record);
        }
        
        return $this->saveXML($xml, 'horas_extras');
    }

    /**
     * Generate absence XML
     */
    protected function generateAbsenceXML(array $data, array $filters): array
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><relatorio></relatorio>');
        
        $metadata = $xml->addChild('metadados');
        $metadata->addChild('tipo', 'faltas-atrasos');
        $metadata->addChild('data_geracao', date('Y-m-d\TH:i:s'));
        $metadata->addChild('empresa', htmlspecialchars($this->companyName));
        $metadata->addChild('cnpj', $this->companyCNPJ);
        
        $this->addFiltersToXML($metadata, $filters);
        
        $dadosNode = $xml->addChild('dados');
        foreach ($data as $record) {
            $registroNode = $dadosNode->addChild('registro');
            $this->addRecordToXML($registroNode, $record);
        }
        
        return $this->saveXML($xml, 'faltas_atrasos');
    }

    /**
     * Generate bank hours XML
     */
    protected function generateBankHoursXML(array $data, array $filters): array
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><relatorio></relatorio>');
        
        $metadata = $xml->addChild('metadados');
        $metadata->addChild('tipo', 'banco-horas');
        $metadata->addChild('data_geracao', date('Y-m-d\TH:i:s'));
        $metadata->addChild('empresa', htmlspecialchars($this->companyName));
        $metadata->addChild('cnpj', $this->companyCNPJ);
        
        $this->addFiltersToXML($metadata, $filters);
        
        $dadosNode = $xml->addChild('dados');
        foreach ($data as $record) {
            $registroNode = $dadosNode->addChild('registro');
            $this->addRecordToXML($registroNode, $record);
        }
        
        return $this->saveXML($xml, 'banco_horas');
    }

    /**
     * Generate consolidated XML
     */
    protected function generateConsolidatedXML(array $data, array $filters): array
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><relatorio></relatorio>');
        
        $metadata = $xml->addChild('metadados');
        $metadata->addChild('tipo', 'consolidado-mensal');
        $metadata->addChild('data_geracao', date('Y-m-d\TH:i:s'));
        $metadata->addChild('empresa', htmlspecialchars($this->companyName));
        $metadata->addChild('cnpj', $this->companyCNPJ);
        
        $this->addFiltersToXML($metadata, $filters);
        
        $dadosNode = $xml->addChild('dados');
        foreach ($data as $record) {
            $registroNode = $dadosNode->addChild('registro');
            $this->addRecordToXML($registroNode, $record);
        }
        
        return $this->saveXML($xml, 'consolidado_mensal');
    }

    /**
     * Generate justifications XML
     */
    protected function generateJustificationsXML(array $data, array $filters): array
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><relatorio></relatorio>');
        
        $metadata = $xml->addChild('metadados');
        $metadata->addChild('tipo', 'justificativas');
        $metadata->addChild('data_geracao', date('Y-m-d\TH:i:s'));
        $metadata->addChild('empresa', htmlspecialchars($this->companyName));
        $metadata->addChild('cnpj', $this->companyCNPJ);
        
        $this->addFiltersToXML($metadata, $filters);
        
        $dadosNode = $xml->addChild('dados');
        foreach ($data as $record) {
            $registroNode = $dadosNode->addChild('registro');
            $this->addRecordToXML($registroNode, $record);
        }
        
        return $this->saveXML($xml, 'justificativas');
    }

    /**
     * Generate warnings XML
     */
    protected function generateWarningsXML(array $data, array $filters): array
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><relatorio></relatorio>');
        
        $metadata = $xml->addChild('metadados');
        $metadata->addChild('tipo', 'advertencias');
        $metadata->addChild('data_geracao', date('Y-m-d\TH:i:s'));
        $metadata->addChild('empresa', htmlspecialchars($this->companyName));
        $metadata->addChild('cnpj', $this->companyCNPJ);
        
        $this->addFiltersToXML($metadata, $filters);
        
        $dadosNode = $xml->addChild('dados');
        foreach ($data as $record) {
            $registroNode = $dadosNode->addChild('registro');
            $this->addRecordToXML($registroNode, $record);
        }
        
        return $this->saveXML($xml, 'advertencias');
    }

    /**
     * Generate custom XML
     */
    protected function generateCustomXML(array $data, array $filters): array
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><relatorio></relatorio>');
        
        $metadata = $xml->addChild('metadados');
        $metadata->addChild('tipo', 'personalizado');
        $metadata->addChild('data_geracao', date('Y-m-d\TH:i:s'));
        $metadata->addChild('empresa', htmlspecialchars($this->companyName));
        $metadata->addChild('cnpj', $this->companyCNPJ);
        
        $this->addFiltersToXML($metadata, $filters);
        
        $dadosNode = $xml->addChild('dados');
        foreach ($data as $record) {
            $registroNode = $dadosNode->addChild('registro');
            $this->addRecordToXML($registroNode, $record);
        }
        
        return $this->saveXML($xml, 'personalizado');
    }

    /**
     * Add filters to XML node
     */
    protected function addFiltersToXML(SimpleXMLElement $metadata, array $filters): void
    {
        if (!empty($filters)) {
            $filtrosNode = $metadata->addChild('filtros');
            foreach ($filters as $key => $value) {
                if (is_scalar($value)) {
                    $filtrosNode->addChild($key, htmlspecialchars((string)$value));
                }
            }
        }
    }

    /**
     * Add record data to XML node
     */
    protected function addRecordToXML(SimpleXMLElement $node, array $record): void
    {
        foreach ($record as $field => $value) {
            if (is_scalar($value)) {
                $node->addChild($field, htmlspecialchars((string)$value));
            }
        }
    }

    /**
     * Save XML to file
     */
    protected function saveXML(SimpleXMLElement $xml, string $type): array
    {
        // Format XML with proper indentation
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());
        
        // Generate filename
        $filename = 'relatorio_' . $type . '_' . date('Y-m-d_His') . '.xml';
        $filepath = WRITEPATH . 'uploads/reports/' . $filename;
        
        // Ensure directory exists
        if (!is_dir(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }
        
        // Save file
        $dom->save($filepath);
        
        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath,
            'size' => filesize($filepath),
        ];
    }
}
