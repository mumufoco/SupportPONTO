<?php

namespace App\Controllers\Auth;

use App\Controllers\BaseController;
use App\Services\Auth\PasswordResetService;
use App\Services\Auth\RegisterPolicyService;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class ForgotPasswordController extends BaseController
{
    protected PasswordResetService $passwordResetService;
    protected RegisterPolicyService $registerPolicyService;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->passwordResetService  = new PasswordResetService();
        $this->registerPolicyService = new RegisterPolicyService();
    }

    public function index()
    {
        if ($this->isAuthenticated()) {
            return redirect()->to(site_url('dashboard'));
        }

        return view('auth/forgot_password', [
            'selfRegistrationEnabled' => $this->registerPolicyService->selfRegistrationEnabled(),
        ]);
    }

    public function sendResetLink()
    {
        $rules = [
            'email' => 'required|valid_email'
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $email = mb_strtolower(trim((string) $this->request->getPost('email')));
        $this->passwordResetService->requestReset($email);

        return redirect()->back()->with('success', 'Se o e-mail existir em nossa base, enviaremos instruções em instantes.');
    }
}
