<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\Admin\AppearanceSettingsService;
use App\Services\Auth\SessionSecurityService;
use App\Services\Settings\SettingsSafetyService;
use App\Controllers\BaseController;
use Config\Services;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class PersonalizationController extends BaseController
{
    /**
     * Catálogo curado de fontes (Google Fonts) oferecidas na Tipografia.
     * Chave = nome exibido/valor salvo; valor = spec da API css2 do Google Fonts.
     */
    private const AVAILABLE_FONTS = [
        'Inter'         => 'Inter:wght@300;400;500;600;700',
        'Open Sans'     => 'Open+Sans:wght@300;400;500;600;700',
        'Roboto'        => 'Roboto:wght@300;400;500;700',
        'Nunito'        => 'Nunito:wght@300;400;500;600;700',
        'Poppins'       => 'Poppins:wght@300;400;500;600;700',
        'Montserrat'    => 'Montserrat:wght@300;400;500;600;700',
        'Lato'          => 'Lato:wght@300;400;700',
        'Work Sans'     => 'Work+Sans:wght@300;400;500;600;700',
        'Rubik'         => 'Rubik:wght@300;400;500;600;700',
        'Source Sans 3' => 'Source+Sans+3:wght@300;400;600;700',
    ];

    protected AppearanceSettingsService $appearanceService;
    protected SessionSecurityService    $sessionSecurityService;
    protected SettingsSafetyService     $settingsSafetyService;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->appearanceService      = Services::appearanceSettingsService(false);
        $this->sessionSecurityService = Services::sessionSecurityService();
        $this->settingsSafetyService  = new SettingsSafetyService();
    }

    public function index()
    {
        $this->requireRole('admin');

        $pageData = $this->appearanceService->pageData();

        return view('admin/settings/personalization', [
            'title'         => 'Personalização',
            'breadcrumbs'   => [
                ['label' => 'Configurações', 'url' => sp_admin_settings_index_url()],
                ['label' => 'Personalização', 'url' => ''],
            ],
            'settings'      => $pageData['settings'],
            'currentConfig' => $pageData['currentConfig'],
            'availableFonts' => self::AVAILABLE_FONTS,
        ]);
    }

    public function update()
    {
        $this->requireRole('admin');

        if (! $this->request->is('post')) {
            return redirect()->back()->with('error', 'Método inválido');
        }

        if (! $this->validate($this->appearanceService->rules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $result = $this->appearanceService->update(
            security_sanitize($this->request->getPost() ?? []),
            $this->request->getFiles(),
            session()->get('user_id') ? (int) session()->get('user_id') : null
        );

        if (! ($result['success'] ?? false)) {
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

    public function uploadLogoAuth()
    {
        return $this->handleUpload('logo_auth', fn($file) => $this->appearanceService->uploadLogoAuth($file));
    }

    public function uploadLogoSidebar()
    {
        return $this->handleUpload('logo_sidebar', fn($file) => $this->appearanceService->uploadLogoSidebar($file));
    }

    public function removeImage(string $field)
    {
        $this->requireRole('admin');

        if (! $this->request->is('post')) {
            return $this->response->setJSON(['success' => false, 'message' => 'Método inválido']);
        }

        return $this->response->setJSON($this->appearanceService->removeImage($field));
    }

    public function reset()
    {
        $this->requireRole('admin');

        if (! $this->request->is('post')) {
            return redirect()->back()->with('error', 'Método inválido');
        }

        $guard = $this->sessionSecurityService->ensureCriticalActionAllowed($this->currentUser, $this->request, session());
        if (! ($guard['success'] ?? false)) {
            return redirect()->back()->with('error', $guard['message'] ?? 'Confirme sua senha.');
        }

        try {
            $snapshot = $this->settingsSafetyService->createPreDestructiveSnapshot(
                'personalization_reset',
                (int) ($this->currentUser->id ?? session()->get('user_id') ?? 0) ?: null,
                ['appearance'],
                ['controller' => 'Admin\\PersonalizationController']
            );
            $result = $this->appearanceService->reset();
            return redirect()->back()->with(($result['success'] ?? false) ? 'success' : 'error', $result['message']);
        } catch (\Throwable $e) {
            helper('observability');
            supportponto_log_exception('admin.personalization', 'reset', $e);
            return redirect()->back()->with('error', supportponto_public_error_message('Erro ao resetar personalização.'));
        }
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
        if (! $this->request->is('post')) {
            return $this->response->setJSON(['success' => false, 'message' => 'Método inválido']);
        }
        $file = $this->request->getFile($field);
        if (! $file || ! $file->isValid()) {
            return $this->response->setJSON(['success' => false, 'message' => 'Arquivo inválido']);
        }
        return $this->response->setJSON($uploadAction($file));
    }
}
