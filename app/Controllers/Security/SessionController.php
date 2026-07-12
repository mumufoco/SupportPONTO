<?php

declare(strict_types=1);

namespace App\Controllers\Security;

use App\Controllers\BaseController;
use App\Services\Auth\SessionSecurityService;
use Config\Services;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class SessionController extends BaseController
{
    protected SessionSecurityService $sessionSecurity;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->sessionSecurity = Services::sessionSecurityService();
    }

    public function index()
    {
        $this->requireAuth();

        return view('profile/security', [
            'title' => 'Segurança da conta',
            'sessions' => $this->sessionSecurity->listSessionsForUser((int) $this->currentUser->id, (string) ($this->session->get('session_key') ?? '')),
            'step_up_ttl' => (int) env('security.stepUpTtl', 900),
            'step_up_active' => $this->sessionSecurity->hasFreshStepUp($this->session),
        ]);
    }

    public function confirmPassword()
    {
        $this->requireAuth();

        $result = $this->sessionSecurity->confirmPasswordForCriticalAction(
            $this->currentUser,
            (string) $this->request->getPost('current_password'),
            $this->session
        );

        return $this->response->setJSON($result)->setStatusCode(($result['success'] ?? false) ? 200 : 422);
    }

    public function revokeOthers()
    {
        $this->requireAuth();

        $guard = $this->sessionSecurity->ensureCriticalActionAllowed($this->currentUser, $this->request, $this->session);
        if (! ($guard['success'] ?? false)) {
            return $this->response->setJSON($guard)->setStatusCode(422);
        }

        $count = $this->sessionSecurity->revokeOtherSessions((int) $this->currentUser->id, $this->session);

        return $this->response->setJSON([
            'success' => true,
            'message' => $count > 0 ? "{$count} outra(s) sessão(ões) foram encerradas." : 'Nenhuma outra sessão ativa encontrada.',
            'revoked_count' => $count,
        ]);
    }

    public function revoke(string $sessionKey)
    {
        $this->requireAuth();

        if ($sessionKey === (string) ($this->session->get('session_key') ?? '')) {
            return $this->response->setJSON(['success' => false, 'message' => 'Use a opção de logout para encerrar a sessão atual.'])->setStatusCode(422);
        }

        $guard = $this->sessionSecurity->ensureCriticalActionAllowed($this->currentUser, $this->request, $this->session);
        if (! ($guard['success'] ?? false)) {
            return $this->response->setJSON($guard)->setStatusCode(422);
        }

        $success = $this->sessionSecurity->revokeSessionByKey((int) $this->currentUser->id, $sessionKey, (string) ($this->currentUser->email ?? ''));

        return $this->response->setJSON([
            'success' => $success,
            'message' => $success ? 'Sessão encerrada com sucesso.' : 'Sessão não encontrada.',
        ])->setStatusCode($success ? 200 : 404);
    }
}
