<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\Auth\SessionSecurityService;
use App\Services\Admin\ControlsSettingsService;
use App\Services\Queue\AsyncJobService;
use App\Services\Settings\SettingsSafetyService;
use Config\Services;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Controles (Segurança + Autenticação)
 *
 * Une as antigas telas /admin/settings/security e /admin/settings/authentication
 * (App\Controllers\Admin\SecurityController e AuthenticationController) em uma
 * única página/controller, a pedido do admin.
 */
class ControlsController extends BaseController
{
    protected ControlsSettingsService $controlsService;
    protected SessionSecurityService $sessionSecurityService;
    protected AsyncJobService $asyncJobService;
    protected SettingsSafetyService $settingsSafetyService;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->controlsService = Services::controlsSettingsService(false);
        $this->sessionSecurityService = Services::sessionSecurityService();
        $this->asyncJobService = new AsyncJobService();
        $this->settingsSafetyService = new SettingsSafetyService();
    }

    public function index()
    {
        $pageData = $this->controlsService->pageData();

        return view('admin/settings/controls', [
            'title' => 'Controles',
            'breadcrumbs' => [
                ['label' => 'Configurações', 'url' => 'settings'],
                ['label' => 'Controles', 'url' => ''],
            ],
            'settings' => $pageData['settings'],
        ]);
    }

    public function update()
    {
        if (!$this->request->is('post')) {
            return redirect()->back()->with('error', 'Método inválido');
        }

        if (!$this->validate($this->controlsService->rules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $result = $this->controlsService->update(
            security_sanitize($this->request->getPost() ?? []),
            session()->get('user_id') ? (int) session()->get('user_id') : null
        );

        if (!($result['success'] ?? false)) {
            return redirect()->back()->withInput()->with('error', $result['message']);
        }

        return redirect()->back()->with('success', $result['message']);
    }

    public function auditLogs()
    {
        return $this->response->setJSON($this->controlsService->auditLogs([
            'page' => $this->request->getGet('page'),
            'per_page' => $this->request->getGet('per_page'),
            'q' => $this->request->getGet('q'),
            'level' => $this->request->getGet('level'),
            'action' => $this->request->getGet('action'),
            'user_id' => $this->request->getGet('user_id'),
        ]));
    }

    public function backup()
    {
        if (!$this->request->is('post')) {
            return $this->response->setJSON(['success' => false, 'message' => 'Método inválido']);
        }

        $guard = $this->sessionSecurityService->ensureCriticalActionAllowed($this->currentUser, $this->request, $this->session);
        if (! ($guard['success'] ?? false)) {
            return $this->response->setJSON($guard)->setStatusCode(422);
        }

        helper('observability');
        helper('operational_link');

        try {
            $job = $this->asyncJobService->enqueue(AsyncJobService::TYPE_DATABASE_BACKUP, [], [
                'employee_id' => (int) ($this->currentUser->id ?? session()->get('user_id')),
                'queue' => 'maintenance',
                'priority' => 60,
                'max_attempts' => 2,
            ]);

            return $this->response->setJSON([
                'success' => true,
                'queued' => true,
                'message' => 'Backup enfileirado para processamento seguro via worker CLI.',
                'job_id' => $job['job_id'],
                'status_url' => sp_async_job_status_url((string) $job['job_id']),
                'download_url' => sp_async_job_download_url((string) $job['job_id']),
            ])->setStatusCode(202);
        } catch (\Throwable $e) {
            supportponto_log_exception('admin.controls', 'backup', $e);
            return $this->response->setJSON([
                'success' => false,
                'message' => supportponto_public_error_message('Erro ao enfileirar backup seguro.'),
            ])->setStatusCode(500);
        }
    }

    public function testPassword()
    {
        if (!$this->request->is('post')) {
            return $this->response->setJSON(['success' => false, 'message' => 'Método inválido']);
        }

        return $this->response->setJSON($this->controlsService->evaluatePassword((string) $this->request->getPost('password')));
    }

    public function reset()
    {
        if (!$this->request->is('post')) {
            return redirect()->back()->with('error', 'Método inválido');
        }

        $guard = $this->sessionSecurityService->ensureCriticalActionAllowed($this->currentUser, $this->request, $this->session);
        if (! ($guard['success'] ?? false)) {
            return redirect()->back()->with('error', $guard['message'] ?? 'Confirme sua senha para restaurar os controles.');
        }

        try {
            $snapshot = $this->settingsSafetyService->createPreDestructiveSnapshot(
                'controls_reset',
                (int) ($this->currentUser->id ?? session()->get('user_id') ?? 0) ?: null,
                ['security', 'authentication'],
                ['controller' => 'Admin\ControlsController']
            );

            $result = $this->controlsService->resetDefaults();
            if (($result['success'] ?? false) && ($snapshot['success'] ?? false) && ! empty($snapshot['relative_path'])) {
                $result['message'] .= ' Snapshot preventivo: ' . $snapshot['relative_path'];
            }

            return redirect()->back()->with(($result['success'] ?? false) ? 'success' : 'error', $result['message']);
        } catch (\Throwable $e) {
            helper('observability');
            supportponto_log_exception('admin.controls', 'reset', $e);
            return redirect()->back()->with('error', supportponto_public_error_message('Erro ao restaurar os controles.'));
        }
    }
}
