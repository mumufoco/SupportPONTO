<?php

namespace App\Controllers\Auth;

use App\Controllers\BaseController;
use App\Services\Auth\WebAuthService;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class LogoutController extends BaseController
{
    protected WebAuthService $webAuthService;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->webAuthService = new WebAuthService();
    }

    /**
     * Process logout
     */
    public function logout()
    {
        if (strtoupper($this->request->getMethod()) !== 'POST') {
            return redirect()->to(sp_login_url());
        }

        // Check if user is authenticated
        if (!$this->isAuthenticated()) {
            return redirect()->to(sp_login_url());
        }

        helper('session_context');
        $userId = sp_session_user_id();
        $userName = sp_session_user()['name'] ?? null;

        $this->webAuthService->logout($userId, is_string($userName) ? $userName : null, $this->session);

        session()->setFlashdata('success', 'Você saiu do sistema com sucesso.');

        return redirect()->to(sp_login_url());
    }

}
