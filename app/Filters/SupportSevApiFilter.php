<?php

namespace App\Filters;

use App\Models\SettingModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Autentica chamadas do SupportSEV via token estático (Authorization: Bearer
 * <token>), comparado contra supportsev_api_token (gerado em Admin >
 * Integrações). Endpoint só de leitura (lista de colaboradores), então não
 * precisa da machinery de HMAC+nonce do SupportCheckCallbackFilter (feita
 * para proteger corpo de webhook contra adulteração/replay) -- rate limit
 * já em vigor na rota cobre o risco de força bruta do token.
 *
 * Substitui o login por usuário/senha de uma conta de colaborador dedicada
 * ("Integracao SupportSEV") que existia só para autenticar essa integração
 * via OAuth2 -- token com escopo restrito a este endpoint, sem depender de
 * um registro de colaborador nem de política de senha/2FA de conta humana.
 */
class SupportSevApiFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $configuredToken = (string) (new SettingModel())->get('supportsev_api_token', '');

        if ($configuredToken === '') {
            return service('response')
                ->setStatusCode(503)
                ->setJSON(['success' => false, 'message' => 'Integração com o SupportSEV não configurada neste servidor.']);
        }

        $header = trim((string) $request->getHeaderLine('Authorization'));
        if (! str_starts_with($header, 'Bearer ')) {
            return $this->deny();
        }

        $providedToken = trim(substr($header, 7));

        if ($providedToken === '' || ! hash_equals($configuredToken, $providedToken)) {
            return $this->deny();
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    private function deny()
    {
        return service('response')
            ->setStatusCode(401)
            ->setJSON(['success' => false, 'message' => 'Token de integração inválido ou ausente.']);
    }
}
