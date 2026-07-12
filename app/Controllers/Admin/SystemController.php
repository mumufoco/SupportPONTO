<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\Admin\SystemSettingsService;
use App\Services\Auth\SessionSecurityService;
use App\Services\Settings\SettingsSafetyService;
use Config\Services;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class SystemController extends BaseController
{
    protected SystemSettingsService $systemService;
    protected SessionSecurityService $sessionSecurityService;
    protected SettingsSafetyService $settingsSafetyService;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->systemService = Services::systemSettingsService(false);
        $this->sessionSecurityService = Services::sessionSecurityService();
        $this->settingsSafetyService = new SettingsSafetyService();
    }

    public function index()
    {
        $pageData = $this->systemService->pageData();

        return view('admin/settings/system', [
            'title' => 'Configurações do Sistema',
            'breadcrumbs' => [
                ['label' => 'Configurações', 'url' => 'settings'],
                ['label' => 'Sistema', 'url' => ''],
            ],
            'settings' => $pageData['settings'],
            'timezones' => $pageData['timezones'],
            'languages' => $pageData['languages'],
        ]);
    }

    public function update()
    {
        if (!$this->request->is('post')) {
            return redirect()->back()->with('error', 'Método inválido');
        }

        if (!$this->validate($this->systemService->rules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $result = $this->systemService->update(
            security_sanitize($this->request->getPost() ?? []),
            session()->get('user_id') ? (int) session()->get('user_id') : null
        );

        if (!($result['success'] ?? false)) {
            return redirect()->back()->withInput()->with('error', $result['message']);
        }

        return redirect()->back()->with('success', $result['message']);
    }

    public function testTimezone()
    {
        if (!$this->request->is('post')) {
            return $this->response->setJSON(['success' => false, 'message' => 'Método inválido']);
        }

        return $this->response->setJSON($this->systemService->testTimezone((string) $this->request->getPost('timezone')));
    }

    public function reset()
    {
        if (!$this->request->is('post')) {
            return redirect()->back()->with('error', 'Método inválido');
        }

        $guard = $this->sessionSecurityService->ensureCriticalActionAllowed($this->currentUser, $this->request, session());
        if (! ($guard['success'] ?? false)) {
            return redirect()->back()->with('error', $guard['message'] ?? 'Confirme sua senha para resetar o sistema.');
        }

        try {
            $snapshot = $this->settingsSafetyService->createPreDestructiveSnapshot(
                'system_reset',
                (int) ($this->currentUser->id ?? session()->get('user_id') ?? 0) ?: null,
                ['system'],
                ['controller' => 'Admin\SystemController']
            );

            $result = $this->systemService->reset();
            if (($result['success'] ?? false) && ($snapshot['success'] ?? false) && ! empty($snapshot['relative_path'])) {
                $result['message'] .= ' Snapshot preventivo: ' . $snapshot['relative_path'];
            }

            return redirect()->back()->with(($result['success'] ?? false) ? 'success' : 'error', $result['message']);
        } catch (\Throwable $e) {
            helper('observability');
            supportponto_log_exception('admin.system', 'reset', $e);
            return redirect()->back()->with('error', supportponto_public_error_message('Erro ao resetar configurações do sistema.'));
        }
    }
}
