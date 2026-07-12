<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Autentica chamadas de callback vindas do SupportCHECK.
 * Usa o token definido em SUPPORTCHECK_CALLBACK_SECRET no .env do SupportPONTO.
 */
class SupportCheckCallbackFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $secret = env('SUPPORTCHECK_CALLBACK_SECRET', '');

        if ($secret === '') {
            return service('response')
                ->setStatusCode(503)
                ->setJSON(['message' => 'Callback do SupportCHECK não configurado neste servidor.']);
        }

        $authHeader = $request->getHeaderLine('Authorization');
        if (! preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON(['message' => 'Token de callback não informado.']);
        }

        if (! hash_equals($secret, trim($matches[1]))) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON(['message' => 'Token de callback inválido.']);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
