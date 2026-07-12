<?php

namespace App\Services;

use App\Services\SMS\SMSCodeRepository;
use App\Services\SMS\SMSMessageComposer;
use App\Services\SMS\SMSProviderTransport;

class SMSService
{
    protected int $codeExpiry = 300; // 5 minutos

    private SMSCodeRepository $codeRepository;
    private SMSMessageComposer $messageComposer;
    private SMSProviderTransport $providerTransport;

    public function __construct(
        ?SMSCodeRepository $codeRepository = null,
        ?SMSMessageComposer $messageComposer = null,
        ?SMSProviderTransport $providerTransport = null,
    ) {
        $this->codeRepository = $codeRepository ?? new SMSCodeRepository();
        $this->messageComposer = $messageComposer ?? new SMSMessageComposer();
        $this->providerTransport = $providerTransport ?? new SMSProviderTransport();
    }

    /**
     * @return array{success:bool,message:string,expires_in?:int}
     */
    public function sendVerificationCode(int $employeeId, string $phone): array
    {
        try {
            $code = $this->messageComposer->generateCode();
            $this->codeRepository->storeCode($employeeId, $code, $this->codeExpiry);

            $message = $this->messageComposer->verificationMessage($code);
            $sendResult = $this->providerTransport->sendVerificationCode($phone, $code, $message, 1);

            if (($sendResult['success'] ?? false) === true) {
                return [
                    'success' => true,
                    'message' => 'Código de verificação enviado para ' . $this->messageComposer->maskPhone($phone),
                    'expires_in' => $this->codeExpiry,
                ];
            }

            return [
                'success' => false,
                'message' => 'Erro ao enviar SMS: ' . ($sendResult['error'] ?? 'Erro desconhecido'),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Erro ao enviar código: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{success:bool,message:string}
     */
    public function verifyCode(int $employeeId, string $code): array
    {
        $storedData = $this->codeRepository->getCode($employeeId);

        if (! $storedData) {
            return [
                'success' => false,
                'message' => 'Nenhum código encontrado. Solicite um novo código.',
            ];
        }

        if (time() > (int) ($storedData['expiry'] ?? 0)) {
            $this->codeRepository->deleteCode($employeeId);

            return [
                'success' => false,
                'message' => 'Código expirado. Solicite um novo código.',
            ];
        }

        if (($storedData['code'] ?? null) !== $code) {
            return [
                'success' => false,
                'message' => 'Código inválido. Verifique e tente novamente.',
            ];
        }

        $this->codeRepository->deleteCode($employeeId);

        return [
            'success' => true,
            'message' => 'Código verificado com sucesso.',
        ];
    }

    /**
     * @return array{attempts:int,can_send:bool,wait_seconds:int}
     */
    public function getRateLimitInfo(int $employeeId): array
    {
        return $this->codeRepository->getRateLimitInfo($employeeId, 3, 3600);
    }

    protected function incrementRateLimit(int $employeeId): void
    {
        $this->codeRepository->incrementRateLimit($employeeId, 3600);
    }
}
