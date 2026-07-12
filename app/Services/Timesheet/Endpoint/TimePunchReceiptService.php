<?php

namespace App\Services\Timesheet\Endpoint;

use App\Models\AuditModel;
use App\Models\SettingModel;
use App\Models\TimePunchModel;

class TimePunchReceiptService
{
    public function __construct(
        private readonly TimePunchModel $timePunchModel,
        private readonly AuditModel $auditModel,
        private readonly SettingModel $settingModel,
        private readonly TimePunchEndpointResultFactory $resultFactory = new TimePunchEndpointResultFactory(),
    ) {
    }

    public function generateReceipt(int $punchId, int $actorEmployeeId, bool $canManage = false): array
    {
        if ($actorEmployeeId <= 0) {
            return $this->resultFactory->error('Usuário não autenticado.', 401);
        }

        if (!class_exists('\\TCPDF')) {
            return $this->resultFactory->error('TCPDF library not installed.', 500);
        }

        $punch = $this->findPunchById($punchId);
        if (!$punch) {
            return $this->resultFactory->error('Registro não encontrado.', 404);
        }

        $authorization = $this->authorizePunchAccess($punch, $actorEmployeeId, $canManage);
        if ($authorization !== null) {
            return $authorization;
        }

        $companyName = $this->settingModel->get('company_name', 'Empresa XYZ Ltda');
        $companyCNPJ = $this->settingModel->get('company_cnpj', '00.000.000/0001-00');
        $companyAddress = $this->settingModel->get('company_address', 'Rua Exemplo, 123 - São Paulo/SP');
        $inpiRegistry = $this->settingModel->get('inpi_registry', 'BR512024000000');

        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('Sistema de Ponto Eletrônico');
        $pdf->SetAuthor($companyName);
        $pdf->SetTitle('Comprovante de Registro de Ponto - NSR ' . str_pad((string) $punch->nsr, 10, '0', STR_PAD_LEFT));
        $pdf->SetSubject('Comprovante conforme Portaria MTE 671/2021');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();

        $logoPath = WRITEPATH . 'uploads/company_logo.png';
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 15, 15, 30, 0, 'PNG');
            $pdf->SetY(20);
        } else {
            $pdf->SetY(15);
        }

        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, $companyName, 0, 1, 'C');

        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5, 'CNPJ: ' . $companyCNPJ, 0, 1, 'C');
        $pdf->Cell(0, 5, $companyAddress, 0, 1, 'C');
        $pdf->Ln(5);

        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetFillColor(0, 102, 204);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(0, 10, 'COMPROVANTE DE REGISTRO DE PONTO ELETRÔNICO', 0, 1, 'C', true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(5);

        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 7, 'DADOS DO FUNCIONÁRIO', 0, 1);
        $pdf->SetFont('helvetica', '', 10);

        $pdf->Cell(50, 6, 'Nome:', 0, 0);
        $pdf->Cell(0, 6, $punch->name, 0, 1);
        $pdf->Cell(50, 6, 'CPF:', 0, 0);
        $pdf->Cell(0, 6, $punch->cpf, 0, 1);
        $pdf->Cell(50, 6, 'Matrícula:', 0, 0);
        $pdf->Cell(0, 6, $punch->unique_code, 0, 1);
        $pdf->Ln(3);

        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 7, 'DADOS DO REGISTRO', 0, 1);
        $pdf->SetFont('helvetica', '', 10);

        $pdf->Cell(50, 6, 'Data/Hora:', 0, 0);
        $pdf->Cell(0, 6, date('d/m/Y H:i:s', strtotime((string) $punch->punch_time)), 0, 1);

        $pdf->Cell(50, 6, 'Tipo de Marcação:', 0, 0);
        $pdf->Cell(0, 6, strtoupper($this->punchTypeLabel((string) $punch->punch_type)), 0, 1);

        $pdf->Cell(50, 6, 'Método:', 0, 0);
        $pdf->Cell(0, 6, $this->methodLabel((string) $punch->method), 0, 1);

        $pdf->Cell(50, 6, 'NSR:', 0, 0);
        $pdf->SetFont('courier', 'B', 10);
        $pdf->Cell(0, 6, str_pad((string) $punch->nsr, 10, '0', STR_PAD_LEFT), 0, 1);

        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(50, 6, 'Hash SHA-256:', 0, 0);
        $pdf->SetFont('courier', '', 8);
        $pdf->MultiCell(0, 6, $punch->hash, 0, 'L');
        $pdf->Ln(3);

        if (!empty($punch->location_lat) && !empty($punch->location_lng)) {
            $pdf->SetFont('helvetica', '', 10);
            $pdf->Cell(50, 6, 'Localização:', 0, 0);
            $pdf->Cell(0, 6, sprintf('%.6f, %.6f', $punch->location_lat, $punch->location_lng), 0, 1);
        }

        $pdf->Ln(5);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 7, 'QR CODE PARA VALIDAÇÃO', 0, 1, 'C');

        $qrData = json_encode([
            'nsr' => $punch->nsr,
            'employee_id' => $punch->employee_id,
            'punch_time' => $punch->punch_time,
            'hash' => $punch->hash,
            'validation_url' => base_url('validate-punch/public/' . $punch->nsr),
        ]);

        $pdf->write2DBarcode((string) $qrData, 'QRCODE,L', 70, $pdf->GetY(), 60, 60, null, 'N');

        $pdf->Ln(65);
        $pdf->SetFont('helvetica', 'I', 9);
        $pdf->MultiCell(0, 5, 'Escaneie o QR Code acima para validar a autenticidade deste comprovante online.', 0, 'C');
        $pdf->Ln(5);

        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->MultiCell(0, 4, 'Este documento é válido sem assinatura conforme Portaria MTE nº 671/2021.', 0, 'C');
        $pdf->MultiCell(0, 4, 'Registro INPI: ' . $inpiRegistry, 0, 'C');
        $pdf->MultiCell(0, 4, 'Validação online: ' . base_url('validate-punch/public/' . $punch->nsr), 0, 'C');
        $pdf->Ln(2);
        $pdf->SetFont('helvetica', 'I', 7);
        $pdf->MultiCell(0, 3, 'Sistema de Ponto Eletrônico - Emitido em ' . date('d/m/Y H:i:s'), 0, 'C');

        $year = date('Y', strtotime((string) $punch->punch_time));
        $month = date('m', strtotime((string) $punch->punch_time));

        $receiptDir = WRITEPATH . "receipts/{$year}/{$month}";
        if (!is_dir($receiptDir)) {
            mkdir($receiptDir, 0755, true);
        }

        $filename = $this->buildFilename((int) $punch->employee_id, (int) $punch->nsr);
        $filepath = $receiptDir . '/' . $filename;
        $pdf->Output($filepath, 'F');

        $this->auditModel->log(
            $punch->employee_id,
            'RECEIPT_GENERATED',
            'time_punches',
            $punchId,
            null,
            null,
            "Comprovante PDF gerado: NSR {$punch->nsr}",
            'info'
        );

        return [
            'success' => true,
            'status' => 200,
            'message' => 'Comprovante gerado com sucesso.',
            'data' => [
                'punch_id' => $punchId,
                'nsr' => $punch->nsr,
                'filename' => $filename,
                'download_url' => base_url("download-receipt/{$year}/{$month}/{$filename}"),
            ],
        ];
    }

    public function resolveReceiptPath(string $year, string $month, string $filename, int $actorEmployeeId, bool $canManage = false): array
    {
        if ($actorEmployeeId <= 0) {
            return $this->resultFactory->error('Usuário não autenticado.', 401);
        }

        $receiptIdentity = $this->parseFilename($filename);
        if ($receiptIdentity === null) {
            return $this->resultFactory->error('Comprovante inválido.', 404);
        }

        $punch = $this->findPunchByIdentity($receiptIdentity['employee_id'], $receiptIdentity['nsr']);
        if (!$punch) {
            return $this->resultFactory->error('Registro não encontrado.', 404);
        }

        $authorization = $this->authorizePunchAccess($punch, $actorEmployeeId, $canManage);
        if ($authorization !== null) {
            return $authorization;
        }

        $expectedYear = date('Y', strtotime((string) $punch->punch_time));
        $expectedMonth = date('m', strtotime((string) $punch->punch_time));
        $expectedFilename = $this->buildFilename((int) $punch->employee_id, (int) $punch->nsr);

        if ($year !== $expectedYear || $month !== $expectedMonth || $filename !== $expectedFilename) {
            return $this->resultFactory->error('Comprovante não encontrado.', 404);
        }

        return [
            'success' => true,
            'status' => 200,
            'message' => 'Comprovante autorizado.',
            'data' => [
                'filepath' => WRITEPATH . "receipts/{$year}/{$month}/{$filename}",
                'punch_id' => (int) $punch->id,
            ],
        ];
    }

    private function authorizePunchAccess(object $punch, int $actorEmployeeId, bool $canManage): ?array
    {
        if ($canManage) {
            return null;
        }

        if ((int) $punch->employee_id !== $actorEmployeeId) {
            return $this->resultFactory->error('Você não tem permissão para acessar este comprovante.', 403);
        }

        return null;
    }

    private function findPunchById(int $punchId): ?object
    {
        return $this->timePunchModel
            ->select('time_punches.*, employees.name, employees.cpf, employees.unique_code')
            ->join('employees', 'employees.id = time_punches.employee_id')
            ->find($punchId);
    }

    private function findPunchByIdentity(int $employeeId, int $nsr): ?object
    {
        return $this->timePunchModel
            ->select('time_punches.*, employees.name, employees.cpf, employees.unique_code')
            ->join('employees', 'employees.id = time_punches.employee_id')
            ->where('time_punches.employee_id', $employeeId)
            ->where('time_punches.nsr', $nsr)
            ->first();
    }

    private function parseFilename(string $filename): ?array
    {
        if (!preg_match('/^employee_(\d+)_nsr_(\d+)\.pdf$/', $filename, $matches)) {
            return null;
        }

        return [
            'employee_id' => (int) $matches[1],
            'nsr' => (int) $matches[2],
        ];
    }

    private function buildFilename(int $employeeId, int $nsr): string
    {
        return "employee_{$employeeId}_nsr_{$nsr}.pdf";
    }

    private function punchTypeLabel(string $punchType): string
    {
        return [
            'entrada' => 'ENTRADA',
            'saida' => 'SAÍDA',
            'intervalo_inicio' => 'INTERVALO - INÍCIO',
            'intervalo_fim' => 'INTERVALO - FIM',
        ][$punchType] ?? strtoupper($punchType);
    }

    private function methodLabel(string $method): string
    {
        return [
            'codigo' => 'Código Único',
            'cpf' => 'CPF',
            'qrcode' => 'QR Code',
            'facial' => 'Reconhecimento Facial',
            'biometria' => 'Biometria (Digital)',
        ][$method] ?? $method;
    }
}
