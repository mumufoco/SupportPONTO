<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\Admin\AppearanceSettingsService;
use App\Services\Auth\SessionSecurityService;
use App\Services\Settings\SettingsSafetyService;
use Config\Services;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class AppearanceController extends BaseController
{
    protected AppearanceSettingsService $appearanceService;
    protected SessionSecurityService $sessionSecurityService;
    protected SettingsSafetyService $settingsSafetyService;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->appearanceService = Services::appearanceSettingsService(false);
        $this->sessionSecurityService = Services::sessionSecurityService();
        $this->settingsSafetyService = new SettingsSafetyService();
    }

    public function index()
    {
        $pageData = $this->appearanceService->pageData();

        return view('admin/settings/appearance', [
            'title' => 'Configurações de Aparência',
            'breadcrumbs' => [
                ['label' => 'Configurações', 'url' => 'settings'],
                ['label' => 'Aparência', 'url' => ''],
            ],
            'settings' => $pageData['settings'],
            'currentConfig' => $pageData['currentConfig'],
        ]);
    }

    public function update()
    {
        if (!$this->request->is('post')) {
            return redirect()->back()->with('error', 'Método inválido');
        }

        if (!$this->validate($this->appearanceService->rules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $result = $this->appearanceService->update(
            security_sanitize($this->request->getPost() ?? []),
            $this->request->getFiles(),
            session()->get('user_id') ? (int) session()->get('user_id') : null
        );

        if (!($result['success'] ?? false)) {
            return redirect()->back()->withInput()->with('error', $result['message']);
        }

        return redirect()->back()->with('success', $result['message']);
    }

    public function uploadLogo()
    {
        return $this->handleUpload('logo', fn($file) => $this->appearanceService->uploadLogo($file));
    }

    public function uploadFavicon()
    {
        return $this->handleUpload('favicon', fn($file) => $this->appearanceService->uploadFavicon($file));
    }

    public function uploadLoginBackground()
    {
        return $this->handleUpload('login_background', fn($file) => $this->appearanceService->uploadLoginBackground($file));
    }

    public function reset()
    {
        if (!$this->request->is('post')) {
            return redirect()->back()->with('error', 'Método inválido');
        }

        $guard = $this->sessionSecurityService->ensureCriticalActionAllowed($this->currentUser, $this->request, session());
        if (! ($guard['success'] ?? false)) {
            return redirect()->back()->with('error', $guard['message'] ?? 'Confirme sua senha para resetar aparência.');
        }

        try {
            $snapshot = $this->settingsSafetyService->createPreDestructiveSnapshot(
                'appearance_reset',
                (int) ($this->currentUser->id ?? session()->get('user_id') ?? 0) ?: null,
                ['appearance'],
                ['controller' => 'Admin\AppearanceController']
            );

            $result = $this->appearanceService->reset();
            if (($result['success'] ?? false) && ($snapshot['success'] ?? false) && ! empty($snapshot['relative_path'])) {
                $result['message'] .= ' Snapshot preventivo: ' . $snapshot['relative_path'];
            }

            return redirect()->back()->with(($result['success'] ?? false) ? 'success' : 'error', $result['message']);
        } catch (\Throwable $e) {
            helper('observability');
            supportponto_log_exception('admin.appearance', 'reset', $e);
            return redirect()->back()->with('error', supportponto_public_error_message('Erro ao resetar aparência.'));
        }
    }

    public function preview()
    {
        return $this->response->setJSON($this->appearanceService->preview($this->request->getGet()));
    }

    public function uploadCropped()
    {
        if (!$this->request->is('post')) {
            return $this->response->setJSON(['success' => false, 'message' => 'Método inválido']);
        }

        $body = (array) $this->request->getJSON(true);
        $type = (string) ($body['type'] ?? '');
        $data = (string) ($body['data'] ?? '');

        if (!in_array($type, ['logo', 'favicon'], true) || empty($data)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Parâmetros inválidos']);
        }

        return $this->response->setJSON(
            $this->appearanceService->saveCroppedImage($type, $data)
        );
    }

    private function handleUpload(string $field, callable $uploadAction)
    {
        if (!$this->request->is('post')) {
            return $this->response->setJSON(['success' => false, 'message' => 'Método inválido']);
        }

        $file = $this->request->getFile($field);
        if (!$file || !$file->isValid()) {
            return $this->response->setJSON(['success' => false, 'message' => 'Arquivo inválido']);
        }

        return $this->response->setJSON($uploadAction($file));
    }
}
