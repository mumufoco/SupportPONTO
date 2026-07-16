<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\Admin\AuthenticationSettingsService;
use App\Services\Auth\SessionSecurityService;
use App\Services\Settings\SettingsSafetyService;
use Config\Services;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Authentication Settings Controller
 *
 * Manages session and login-protection settings.
 */
class AuthenticationController extends BaseController
{
    protected AuthenticationSettingsService $authenticationService;
    protected SessionSecurityService $sessionSecurityService;
    protected SettingsSafetyService $settingsSafetyService;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        $this->authenticationService = Services::authenticationSettingsService(false);
        $this->sessionSecurityService = Services::sessionSecurityService();
        $this->settingsSafetyService = new SettingsSafetyService();
    }

    /**
     * Authentication settings page
     */
    public function index()
    {
        $pageData = $this->authenticationService->pageData();

        $data = [
            'title' => 'Configurações de Autenticação',
            'breadcrumbs' => [
                ['label' => 'Configurações', 'url' => 'settings'],
                ['label' => 'Autenticação', 'url' => '']
            ],
            'settings' => $pageData['settings']
        ];

        return view('admin/settings/authentication', $data);
    }

    /**
     * Update authentication settings
     */
    public function update()
    {
        if (!$this->request->is('post')) {
            return redirect()->back()->with('error', 'Método inválido');
        }

        if (!$this->validate($this->authenticationService->rules())) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $result = $this->authenticationService->update(
            security_sanitize($this->request->getPost() ?? []),
            session()->get('user_id') ? (int) session()->get('user_id') : null
        );

        if (!($result['success'] ?? false)) {
            return redirect()->back()->withInput()->with('error', $result['message']);
        }

        return redirect()->back()->with('success', $result['message']);
    }

    /**
     * Reset authentication settings to defaults
     */
    public function reset()
    {
        if (!$this->request->is('post')) {
            return redirect()->back()->with('error', 'Método inválido');
        }

        $guard = $this->sessionSecurityService->ensureCriticalActionAllowed($this->currentUser, $this->request, session());
        if (! ($guard['success'] ?? false)) {
            return redirect()->back()->with('error', $guard['message'] ?? 'Confirme sua senha para resetar autenticação.');
        }

        $snapshot = $this->settingsSafetyService->createPreDestructiveSnapshot(
            'authentication_reset',
            (int) ($this->currentUser->id ?? session()->get('user_id') ?? 0) ?: null,
            ['authentication'],
            ['controller' => 'Admin\AuthenticationController']
        );

        $result = $this->authenticationService->resetDefaults();

        if (!($result['success'] ?? false)) {
            return redirect()->back()->with('error', $result['message']);
        }

        $message = $result['message'];
        if (($snapshot['success'] ?? false) && ! empty($snapshot['relative_path'])) {
            $message .= ' Snapshot preventivo: ' . $snapshot['relative_path'];
        }

        return redirect()->back()->with('success', $message);
    }
}
