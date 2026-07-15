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
        if ($method === 'sms') {
            return $this->resolveSmsSignature($actor, $input);
        }

        return ['success' => false, 'message' => 'Método de assinatura inválido.'];
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
