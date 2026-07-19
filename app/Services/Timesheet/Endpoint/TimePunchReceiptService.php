<?php

namespace App\Services\Timesheet\Endpoint;

use App\Models\AuditModel;
use App\Models\EmployeeModel;
use App\Models\SettingModel;
use App\Models\TimePunchModel;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Common\Version;
use chillerlan\QRCode\Output\QRGdImagePNG;

class TimePunchReceiptService
{
    public function __construct(
        private readonly TimePunchModel $timePunchModel,
        private readonly AuditModel $auditModel,
        private readonly SettingModel $settingModel,
        private readonly TimePunchEndpointResultFactory $resultFactory = new TimePunchEndpointResultFactory(),
        private readonly EmployeeModel $employeeModel = new EmployeeModel(),
    ) {
    }

    public function generateReceipt(int $punchId, int $actorEmployeeId, string $actorRole = '', string $actorDepartment = ''): array
    {
        if ($actorEmployeeId <= 0) {
            return $this->resultFactory->error('Usuário não autenticado.', 401);
        }

        $punch = $this->findPunchById($punchId);
        if (!$punch) {
            return $this->resultFactory->error('Registro não encontrado.', 404);
        }

        $authorization = $this->authorizePunchAccess($punch, $actorEmployeeId, $actorRole, $actorDepartment);
        if ($authorization !== null) {
            return $authorization;
        }

        $companyName = $this->settingModel->get('company_name', 'Empresa XYZ Ltda');
        $companyCNPJ = $this->settingModel->get('company_cnpj', '00.000.000/0001-00');
        $companyAddress = $this->settingModel->get('company_address', 'Rua Exemplo, 123 - São Paulo/SP');
        $inpiRegistry = $this->settingModel->get('inpi_registry', 'BR512024000000');

        $html = $this->buildReceiptHtml($punch, $companyName, $companyCNPJ, $companyAddress, $inpiRegistry);

        $pdf = new \App\Services\Pdf\GotenbergPdfDocument('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('Sistema de Ponto Eletrônico');
        $pdf->SetAuthor($companyName);
        $pdf->SetTitle('Comprovante de Registro de Ponto - NSR ' . str_pad((string) $punch->nsr, 10, '0', STR_PAD_LEFT));
        $pdf->SetSubject('Comprovante conforme Portaria MTE 671/2021');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();
        $pdf->writeHTML($html, true, false, true, false, '');

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

    public function resolveReceiptPath(string $year, string $month, string $filename, int $actorEmployeeId, string $actorRole = '', string $actorDepartment = ''): array
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

        $authorization = $this->authorizePunchAccess($punch, $actorEmployeeId, $actorRole, $actorDepartment);
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

    /**
     * ALTO-xx (auditoria): antes bastava ser admin/gestor/rh (qualquer
     * departamento) para baixar o comprovante -- nome + CPF descriptografado
     * -- de QUALQUER colaborador. Mesmo padrao de escopo por departamento ja
     * usado em TimePunchIntegrityService::canActorAccessPunch().
     */
    private function authorizePunchAccess(object $punch, int $actorEmployeeId, string $actorRole, string $actorDepartment): ?array
    {
        $normalizedRole = strtolower(trim($actorRole));

        if (in_array($normalizedRole, ['admin', 'rh'], true)) {
            return null;
        }

        if ($normalizedRole === 'gestor') {
            $targetEmployee = $this->employeeModel->find((int) $punch->employee_id);
            if ($targetEmployee
                && (string) ($targetEmployee->department ?? '') !== ''
                && (string) ($targetEmployee->department ?? '') === $actorDepartment
            ) {
                return null;
            }

            return $this->resultFactory->error('Você não tem permissão para acessar este comprovante.', 403);
        }

        if ((int) $punch->employee_id !== $actorEmployeeId) {
            return $this->resultFactory->error('Você não tem permissão para acessar este comprovante.', 403);
        }

        return null;
    }

    private function findPunchById(int $punchId): ?object
    {
        return $this->decryptPunchCpf($this->timePunchModel
            ->select('time_punches.*, employees.name, employees.cpf, employees.unique_code')
            ->join('employees', 'employees.id = time_punches.employee_id')
            ->find($punchId));
    }

    private function findPunchByIdentity(int $employeeId, int $nsr): ?object
    {
        return $this->decryptPunchCpf($this->timePunchModel
            ->select('time_punches.*, employees.name, employees.cpf, employees.unique_code')
            ->join('employees', 'employees.id = time_punches.employee_id')
            ->where('time_punches.employee_id', $employeeId)
            ->where('time_punches.nsr', (string) $nsr)
            ->first());
    }

    /**
     * MED-11 (auditoria): employees.cpf agora fica criptografado — os JOINs acima
     * não passam por EmployeeModel::afterFind(), então precisam decriptar aqui antes
     * do comprovante de ponto (documento com validade legal) ser montado.
     */
    private function decryptPunchCpf(?object $punch): ?object
    {
        if ($punch !== null && isset($punch->cpf)) {
            $punch->cpf = \App\Models\EmployeeModel::decryptCpfValue($punch->cpf);
        }

        return $punch;
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

    /**
     * Monta o HTML do comprovante — equivalente ao layout antes desenhado
     * célula-a-célula via TCPDF (Cell/MultiCell), agora renderizado pelo
     * Gotenberg (ver GotenbergPdfDocument). O QR code é gerado como PNG
     * base64 embutido (mesma lib/opções usadas em QRCodeService), já que o
     * write2DBarcode nativo do TCPDF não existe nesse novo motor.
     */
    private function buildReceiptHtml(object $punch, string $companyName, string $companyCNPJ, string $companyAddress, string $inpiRegistry): string
    {
        $logoPath = WRITEPATH . 'uploads/company_logo.png';
        $logoHtml = '';
        if (file_exists($logoPath)) {
            $logoData = base64_encode((string) file_get_contents($logoPath));
            $logoHtml = '<img src="data:image/png;base64,' . $logoData . '" style="height:22mm;display:block;margin:0 auto 3mm;">';
        }

        $validationUrl = base_url('validate-punch/public/' . $punch->nsr);
        $qrData = json_encode([
            'nsr' => $punch->nsr,
            'employee_id' => $punch->employee_id,
            'punch_time' => $punch->punch_time,
            'hash' => $punch->hash,
            'validation_url' => $validationUrl,
        ]);

        $qrOptions = new QROptions([
            'version' => Version::AUTO,
            'outputInterface' => QRGdImagePNG::class,
            'eccLevel' => 'H',
            'scale' => 6,
            'outputBase64' => true,
            'addQuietzone' => true,
            'quietzoneSize' => 2,
        ]);
        $qrImage = (new QRCode($qrOptions))->render((string) $qrData);

        $locationRow = '';
        if (!empty($punch->location_lat) && !empty($punch->location_lng)) {
            $locationRow = '<tr><td class="lbl">Localização:</td><td>' . sprintf('%.6f, %.6f', $punch->location_lat, $punch->location_lng) . '</td></tr>';
        }

        $h = '<style>';
        $h .= 'body{font-family:helvetica,sans-serif;font-size:10pt;color:#111;text-align:center;}';
        $h .= 'table{width:100%;border-collapse:collapse;text-align:left;margin-bottom:3mm;}';
        $h .= 'td{padding:1.2mm 0;font-size:10pt;vertical-align:top;}';
        $h .= '.lbl{width:45mm;color:#333;}';
        $h .= '.section{font-weight:bold;font-size:11pt;text-align:left;margin:3mm 0 1mm;}';
        $h .= '.title-bar{background:#0066cc;color:#fff;font-weight:bold;font-size:14pt;padding:4mm;margin:3mm 0;}';
        $h .= '.mono{font-family:courier,monospace;}';
        $h .= '.hash{font-family:courier,monospace;font-size:8pt;word-break:break-all;text-align:left;}';
        $h .= '.muted{color:#666;font-size:8pt;}';
        $h .= '.small{font-size:7pt;color:#666;}';
        $h .= '</style>';

        $h .= $logoHtml;
        $h .= '<div style="font-size:16pt;font-weight:bold;">' . esc($companyName) . '</div>';
        $h .= '<div style="font-size:10pt;">CNPJ: ' . esc($companyCNPJ) . '</div>';
        $h .= '<div style="font-size:10pt;margin-bottom:3mm;">' . esc($companyAddress) . '</div>';

        $h .= '<div class="title-bar">COMPROVANTE DE REGISTRO DE PONTO ELETRÔNICO</div>';

        $h .= '<div class="section">DADOS DO FUNCIONÁRIO</div>';
        $h .= '<table>';
        $h .= '<tr><td class="lbl">Nome:</td><td>' . esc($punch->name) . '</td></tr>';
        $h .= '<tr><td class="lbl">CPF:</td><td>' . esc($punch->cpf) . '</td></tr>';
        $h .= '<tr><td class="lbl">Matrícula:</td><td>' . esc($punch->unique_code) . '</td></tr>';
        $h .= '</table>';

        $h .= '<div class="section">DADOS DO REGISTRO</div>';
        $h .= '<table>';
        $h .= '<tr><td class="lbl">Data/Hora:</td><td>' . date('d/m/Y H:i:s', strtotime((string) $punch->punch_time)) . '</td></tr>';
        $h .= '<tr><td class="lbl">Tipo de Marcação:</td><td>' . esc(strtoupper($this->punchTypeLabel((string) $punch->punch_type))) . '</td></tr>';
        $h .= '<tr><td class="lbl">Método:</td><td>' . esc($this->methodLabel((string) $punch->method)) . '</td></tr>';
        $h .= '<tr><td class="lbl">NSR:</td><td class="mono">' . esc(str_pad((string) $punch->nsr, 10, '0', STR_PAD_LEFT)) . '</td></tr>';
        $h .= '<tr><td class="lbl">Hash SHA-256:</td><td class="hash">' . esc((string) $punch->hash) . '</td></tr>';
        $h .= $locationRow;
        $h .= '</table>';

        $h .= '<div class="section" style="text-align:center;">QR CODE PARA VALIDAÇÃO</div>';
        $h .= '<img src="' . $qrImage . '" style="width:50mm;height:50mm;margin:2mm auto;display:block;">';
        $h .= '<p style="font-style:italic;font-size:9pt;">Escaneie o QR Code acima para validar a autenticidade deste comprovante online.</p>';

        $h .= '<p class="muted">Este documento é válido sem assinatura conforme Portaria MTE nº 671/2021.</p>';
        $h .= '<p class="muted">Registro INPI: ' . esc($inpiRegistry) . '</p>';
        $h .= '<p class="muted">Validação online: ' . esc($validationUrl) . '</p>';
        $h .= '<p class="small">Sistema de Ponto Eletrônico - Emitido em ' . date('d/m/Y H:i:s') . '</p>';

        return $h;
    }
}
