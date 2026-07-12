<?php

namespace App\Filters;

use App\Filters\TwoFactorAuthFilter;
use App\Support\BootstrapEnv;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

/**
 * Auth Filter
 *
 * Verifies if user is authenticated before accessing protected routes
 * Complementar aos filtros Manager e Admin para permissões específicas
 */
class AuthFilter implements FilterInterface
{
    /**
     * Check if user is authenticated
     *
     * @param RequestInterface $request
     * @param array|null $arguments
     * @return mixed
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();
        $uri = $request->getUri()->getPath();

        // First check if user is authenticated
        if (!$session->get('user_id')) {
            if (in_array(strtolower($request->getMethod()), ['get', 'head'], true)) {
                $session->set('redirect_url', current_url());
            }
            return $this->deny($request, 401, 'Não autenticado.', sp_login_path(), 'Você precisa estar logado para acessar esta página.');
        }

        if ($this->isSessionExpired($session)) {
            $session->destroy();
            return $this->deny($request, 401, 'Sessão expirada.', sp_login_path(), 'Sua sessão expirou. Faça login novamente.');
        }

        if ($this->hasSessionFingerprintMismatch($request, $session)) {
            $session->destroy();
            return $this->deny($request, 401, 'Sessão inválida.', sp_login_path(), 'Sua sessão foi invalidada por segurança.');
        }

        $sessionSecurity = Services::sessionSecurityService();
        if (! $sessionSecurity->isCurrentSessionAllowed((int) $session->get('user_id'), $session)) {
            $session->destroy();
            return $this->deny($request, 401, 'Sessão revogada.', sp_login_path(), 'Sua sessão foi encerrada em outro dispositivo.');
        }

        if ($this->requiresPasswordChange($request, $session)) {
            return $this->deny(
                $request,
                423,
                'Troca de senha obrigatória no primeiro acesso.',
                '/auth/first-access-password',
                'Você precisa definir uma nova senha antes de continuar.'
            );
        }

        $twoFactorGate = (new TwoFactorAuthFilter())->before($request, $arguments);
        if ($twoFactorGate !== null) {
            return $twoFactorGate;
        }

        $session->set('last_activity', time());
        Services::sessionSecurityService()->touchCurrentSession((int) $session->get('user_id'), $session, $request);
        return null; // Allow request to proceed
    }

    /**
     * Do nothing after controller execution
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param array|null $arguments
     * @return mixed
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    /**
     * Check if request is an API request
     *
     * @param RequestInterface $request
     * @return bool
     */

    protected function isSessionExpired($session): bool
    {
        $lastActivity = (int) ($session->get('last_activity') ?? 0);
        $idleTimeout = (int) env('session.idleTimeout', 1800);

        return $lastActivity > 0 && $idleTimeout > 0 && (time() - $lastActivity) > $idleTimeout;
    }

    protected function hasSessionFingerprintMismatch(RequestInterface $request, $session): bool
    {
        $storedFingerprint = (string) ($session->get('session_fingerprint') ?? '');
        if ($storedFingerprint === '') {
            return false;
        }

        $userAgent = substr((string) $request->getUserAgent()->getAgentString(), 0, 255);
        $matchIp = BootstrapEnv::sessionMatchIp(false);
        $ip = $matchIp ? (string) ($request->getIPAddress() ?? '') : '';
        $currentFingerprint = hash('sha256', $userAgent . '|' . $ip);

        return ! hash_equals($storedFingerprint, $currentFingerprint);
    }

    protected function isApiRequest(RequestInterface $request): bool
    {
        $path = trim((string) $request->getUri()->getPath(), '/');
        if ($path === 'api' || str_starts_with($path, 'api/')) {
            return true;
        }

        $accept = strtolower($request->getHeaderLine('Accept'));
        if (str_contains($accept, 'application/json') || str_contains($accept, 'application/vnd.supportponto')) {
            return true;
        }

        $contentType = strtolower($request->getHeaderLine('Content-Type'));
        if (str_contains($contentType, 'application/json')) {
            return true;
        }

        return false;
    }


    protected function expectsJsonResponse(RequestInterface $request): bool
    {
        return $request->isAJAX() || $this->isApiRequest($request);
    }

    protected function requiresPasswordChange(RequestInterface $request, $session): bool
    {
        if (! $session->get('must_change_password')) {
            return false;
        }

        $path = trim($request->getUri()->getPath(), '/');
        $allowed = [
            'auth/first-access-password',
            'auth/logout',
        ];

        return ! in_array($path, $allowed, true);
    }

    protected function deny(
        RequestInterface $request,
        int $statusCode,
        string $apiError,
        string $redirectPath,
        ?string $flashError = null
    ) {
        if ($this->expectsJsonResponse($request)) {
            return service('response')
                ->setJSON([
                    'success' => false,
                    'error' => $apiError,
                ])
                ->setStatusCode($statusCode);
        }

        if ($statusCode === 403) {
            return service('response')
                ->setStatusCode(403)
                ->setBody(view('errors/html/error_403', [
                    'message' => $flashError ?? $apiError,
                    'backUrl' => $redirectPath,
                ]));
        }

        if ($flashError !== null) {
            session()->setFlashdata('error', $flashError);
        }

        return redirect()->to($redirectPath);
    }
}
