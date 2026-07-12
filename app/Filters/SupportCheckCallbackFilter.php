<?php

namespace App\Filters;

use App\Models\PunchTerminalNonceModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Autentica chamadas de callback vindas do SupportCHECK.
 *
 * MED-06 (auditoria): antes, este filtro só checava um Bearer token estático — sem
 * assinatura do corpo (o payload podia ser adulterado em trânsito sem detecção) e sem
 * proteção contra replay (o mesmo request capturado podia ser reenviado
 * indefinidamente). Agora exige um HMAC-SHA256 do corpo + nonce persistido (mesmo
 * padrão já usado em PublicTerminalSecurityService/PunchTerminalNonceModel para os
 * terminais de ponto), além de suportar rotação de segredo sem downtime.
 *
 * O SupportCHECK precisa enviar, em cada chamada:
 *   X-SupportCheck-Timestamp: unix timestamp da requisição
 *   X-SupportCheck-Nonce:     valor aleatório único por requisição (mín. 16 bytes hex)
 *   X-SupportCheck-Signature: hash_hmac('sha256', "{timestamp}.{nonce}.{corpo bruto}", $secret)
 *
 * usando SUPPORTCHECK_CALLBACK_SECRET (ou SUPPORTCHECK_CALLBACK_SECRET_PREVIOUS durante
 * uma janela de rotação — ambos são aceitos até a rotação ser concluída).
 */
class SupportCheckCallbackFilter implements FilterInterface
{
    private const MAX_CLOCK_SKEW_SECONDS = 300;

    public function before(RequestInterface $request, $arguments = null)
    {
        $secrets = array_values(array_filter([
            (string) env('SUPPORTCHECK_CALLBACK_SECRET', ''),
            (string) env('SUPPORTCHECK_CALLBACK_SECRET_PREVIOUS', ''),
        ], static fn (string $s): bool => $s !== ''));

        if ($secrets === []) {
            return service('response')
                ->setStatusCode(503)
                ->setJSON(['message' => 'Callback do SupportCHECK não configurado neste servidor.']);
        }

        $timestamp = trim($request->getHeaderLine('X-SupportCheck-Timestamp'));
        $nonce = trim($request->getHeaderLine('X-SupportCheck-Nonce'));
        $signature = trim($request->getHeaderLine('X-SupportCheck-Signature'));

        if ($timestamp === '' || $nonce === '' || $signature === '') {
            return $this->deny(401, 'Cabeçalhos de assinatura do callback ausentes (timestamp/nonce/signature).');
        }

        if (! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > self::MAX_CLOCK_SKEW_SECONDS) {
            return $this->deny(401, 'Timestamp do callback expirado ou inválido.');
        }

        if (strlen($nonce) < 16) {
            return $this->deny(401, 'Nonce do callback inválido.');
        }

        $rawBody = (string) $request->getBody();
        $payload = $timestamp . '.' . $nonce . '.' . $rawBody;

        $validSecretFound = false;
        foreach ($secrets as $secret) {
            $expectedSignature = hash_hmac('sha256', $payload, $secret);
            if (hash_equals($expectedSignature, $signature)) {
                $validSecretFound = true;
                break;
            }
        }

        if (! $validSecretFound) {
            return $this->deny(401, 'Assinatura do callback inválida.');
        }

        $nonceModel = new PunchTerminalNonceModel();
        $nonceHash = hash('sha256', $nonce);

        if ($nonceModel->nonceExists('supportcheck_webhook', $nonceHash, 'callback')) {
            return $this->deny(409, 'Reuso de requisição detectado (replay).');
        }

        try {
            $nonceModel->insert([
                'terminal_id' => 'supportcheck_webhook',
                'nonce_hash' => $nonceHash,
                'purpose' => 'callback',
                'ip_address' => $request->getIPAddress(),
                'expires_at' => date('Y-m-d H:i:s', (int) $timestamp + self::MAX_CLOCK_SKEW_SECONDS),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Falha ao registrar nonce de callback do SupportCHECK: ' . $e->getMessage());
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    private function deny(int $status, string $message)
    {
        return service('response')->setStatusCode($status)->setJSON(['message' => $message]);
    }
}
