<?php

namespace App\Filters;

use App\Services\Auth\OAuth2Service;
use App\Traits\ObservabilityTrait;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

/**
 * OAuth 2.0 Authentication Filter
 *
 * Valida Bearer tokens para endpoints da API e injeta o contexto autenticado
 * no escopo da requisição atual, sem depender de sessão PHP.
 */
class OAuth2Filter implements FilterInterface
{
    use ObservabilityTrait;

    protected OAuth2Service $oauth2Service;

    protected array $publicEndpoints = [
        'api/oauth/token',
        'api/oauth/refresh',
        'api/docs',
        'api/health',
        'api/ping',
    ];

    public function __construct()
    {
        $this->oauth2Service = new OAuth2Service();
    }

    public function before(RequestInterface $request, $arguments = null)
    {
        $path = trim($request->getUri()->getPath(), '/');

        // BAIXO-02 (auditoria): str_starts_with sem checar limite de segmento fazia
        // "api/oauth/token" casar também com "api/oauth/tokens" (rota real que lista
        // tokens ativos), e qualquer rota futura prefixada por "api/health"/"api/ping"
        // herdaria bypass de autenticação. Hoje isso já é neutralizado por revalidação
        // nos controllers, mas é uma fragilidade de implementação — exige igualdade
        // exata ou fim de segmento ("/" ou "?").
        foreach ($this->publicEndpoints as $publicEndpoint) {
            if ($path === $publicEndpoint || str_starts_with($path, $publicEndpoint . '/')) {
                return null;
            }
        }

        $authHeader = $request->getHeaderLine('Authorization');
        if ($authHeader === '' || ! preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $this->unauthorizedResponse('Token de acesso ausente ou inválido.');
        }

        $accessToken = trim($matches[1]);
        if ($accessToken === '') {
            return $this->unauthorizedResponse('Token de acesso ausente ou inválido.');
        }

        $deviceFingerprint = OAuth2Service::generateDeviceFingerprint();
        $tokenData = $this->oauth2Service->validateAccessToken($accessToken, $deviceFingerprint);

        if (! is_array($tokenData)) {
            log_message('warning', "Invalid OAuth access token attempt from IP: {$request->getIPAddress()}");
            return $this->unauthorizedResponse('Token de acesso ausente, inválido ou expirado.');
        }

        if ($arguments && isset($arguments[0])) {
            $requiredScope = (string) $arguments[0];

            if (! $this->oauth2Service->hasScope($tokenData, $requiredScope)) {
                log_message('warning', "Insufficient scope for employee ID: {$tokenData['employee_id']}, required: {$requiredScope}");
                return $this->forbiddenResponse('Permissões insuficientes para este endpoint.');
            }
        }

        Services::apiRequestAuthContext()->setContext($tokenData, $deviceFingerprint, $accessToken);

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        Services::apiRequestAuthContext()->clear();
        return null;
    }

    protected function unauthorizedResponse(string $message): ResponseInterface
    {
        $response = service('response');

        return $this->attachResponseContext(
            $response
                ->setStatusCode(401, 'Unauthorized')
                ->setJSON($this->apiErrorPayload('unauthorized', $message)),
            true
        );
    }

    protected function forbiddenResponse(string $message): ResponseInterface
    {
        $response = service('response');

        return $this->attachResponseContext(
            $response
                ->setStatusCode(403, 'Forbidden')
                ->setJSON($this->apiErrorPayload('forbidden', $message)),
            true
        );
    }

    public function addPublicEndpoint(string $endpoint): void
    {
        if (! in_array($endpoint, $this->publicEndpoints, true)) {
            $this->publicEndpoints[] = $endpoint;
        }
    }

    public function getPublicEndpoints(): array
    {
        return $this->publicEndpoints;
    }
}
