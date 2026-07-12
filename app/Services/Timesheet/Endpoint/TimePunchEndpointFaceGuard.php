<?php

namespace App\Services\Timesheet\Endpoint;

use App\Services\Timesheet\TimePunchFlowService;
use CodeIgniter\HTTP\RequestInterface;

class TimePunchEndpointFaceGuard
{
    public function __construct(
        private readonly TimePunchFlowService $flowService,
        private readonly TimePunchEndpointResultFactory $resultFactory = new TimePunchEndpointResultFactory(),
    ) {
    }

    public function validate(RequestInterface $request, string $uri): ?array
    {
        if ($request->isSecure() === false) {
            return $this->resultFactory->error('Dados biométricos devem ser transmitidos via HTTPS.', 403);
        }

        // Fase 11: a validação anti-replay do token de terminal é feita pelo
        // PublicTerminalSecurityFilter antes do controller. O guard facial não
        // revalida o token para não consumir o mesmo nonce duas vezes.

        $throttler = \Config\Services::throttler();
        $throttleKey = 'facial_punch_' . md5($request->getIPAddress() . '|' . ($request->getHeaderLine('X-Terminal-Id') ?: 'browser'));
        if ($throttler->check($throttleKey, 5, MINUTE) === false) {
            return $this->resultFactory->error('Muitas tentativas de reconhecimento facial. Aguarde 1 minuto antes de tentar novamente.', 429);
        }

        return null;
    }
}
