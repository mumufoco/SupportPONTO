<?php

namespace App\Services\Warning\Workflow;

use App\Services\SMSService;
use App\Services\WarningPDFService;

class WarningSignatureService
{
    public function __construct(
        private readonly WarningPDFService $pdfService,
        private readonly SMSService $smsService
    ) {
    }

    public function resolveSignature(array $actor, object $warning, string $method, array $input, $certificateFile): array
    {
        if ($method === 'icp') {
            return $this->resolveIcpSignature($actor, $warning, $input, $certificateFile);
        }

        if ($method === 'sms') {
            return $this->resolveSmsSignature($actor, $input);
        }

        return ['success' => false, 'message' => 'Método de assinatura inválido.'];
    }

    private function resolveIcpSignature(array $actor, object $warning, array $input, $certificateFile): array
    {
        if (!$certificateFile || !$certificateFile->isValid()) {
            return ['success' => false, 'message' => 'Certificado ICP-Brasil inválido.'];
        }

        $signResult = $this->pdfService->signPDFWithICPUpload(
            $warning->pdf_path,
            $certificateFile,
            (string) ($input['certificate_password'] ?? ''),
            $actor['id']
        );

        if (!($signResult['success'] ?? false)) {
            return ['success' => false, 'message' => 'Erro ao assinar com certificado ICP: ' . ($signResult['error'] ?? 'erro desconhecido')];
        }

        return ['success' => true, 'signature' => 'ICP-Brasil: ' . $signResult['certificate_name']];
    }

    private function resolveSmsSignature(array $actor, array $input): array
    {
        $smsCode = $input['sms_code'] ?? null;
        if (!$smsCode) {
            return ['success' => false, 'message' => 'Código SMS é obrigatório.'];
        }

        $verifyResult = $this->smsService->verifyCode($actor['id'], $smsCode);
        if (!($verifyResult['success'] ?? false)) {
            return ['success' => false, 'message' => 'Código SMS inválido ou expirado.'];
        }

        return [
            'success' => true,
            'signature' => 'Assinatura Eletrônica (SMS) - Código verificado em ' . date('Y-m-d H:i:s'),
        ];
    }
}
