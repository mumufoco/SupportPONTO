<?php

namespace App\Controllers\Auth;

use App\Controllers\BaseController;
use App\Services\Auth\PasswordResetService;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class ResetPasswordController extends BaseController
{
    protected PasswordResetService $passwordResetService;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->passwordResetService = new PasswordResetService();
    }

    public function index(string $token)
    {
        if ($this->isAuthenticated()) {
            return redirect()->to(site_url('dashboard'));
        }

        $employee = $this->passwordResetService->validateToken($token);

        if (!$employee) {
            return redirect()->to(sp_login_url())->with('error', 'Token inválido ou expirado.');
        }

        return view('auth/reset_password', ['token' => $token]);
    }

    public function reset()
    {
        $rules = [
            'token'            => 'required',
            'password'         => 'required|strong_password|matches[confirm_password]',
            'confirm_password' => 'required',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $token    = $this->request->getPost('token');
        $password = $this->request->getPost('password');

        if (! $this->passwordResetService->resetPassword((string) $token, (string) $password)) {
            return redirect()->to(sp_login_url())->with('error', 'Token inválido ou expirado.');
        }

        return redirect()->to(sp_login_url())->with('success', 'Senha redefinida com sucesso!');
    }
}
