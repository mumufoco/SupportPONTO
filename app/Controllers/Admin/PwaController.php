<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\SettingModel;
use App\Services\Pwa\ManifestGeneratorService;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class PwaController extends BaseController
{
    protected SettingModel $settingModel;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->settingModel = model(SettingModel::class);
    }

    public function index()
    {
        $this->requireRole('admin');

        $pwa = $this->settingModel->getByGroupMap('pwa')       ?? [];
        $app = $this->settingModel->getByGroupMap('appearance') ?? [];

        $defaults = [
            'pwa_enabled'          => '1',
            'pwa_app_name'         => $app['company_name'] ?? 'SupportPONTO',
            'pwa_short_name'       => 'PONTO',
            'pwa_theme_color'      => $app['primary_color'] ?? '#4fa14f',
            'pwa_background_color' => '#ffffff',
            'pwa_display'          => 'standalone',
            'pwa_start_url'        => '/',
            'pwa_description'      => 'Sistema de Ponto Eletrônico',
            'pwa_orientation'      => 'any',
        ];

        return view('admin/settings/pwa', [
            'title'       => 'PWA',
            'breadcrumbs' => [
                ['label' => 'Configurações', 'url' => sp_admin_settings_index_url()],
                ['label' => 'PWA',           'url' => ''],
            ],
            'settings' => array_merge($defaults, $pwa),
        ]);
    }

    public function update()
    {
        $this->requireRole('admin');

        if (! $this->request->is('post')) {
            return redirect()->back()->with('error', 'Método inválido');
        }

        $data   = security_sanitize($this->request->getPost() ?? []);
        $fields = ['pwa_enabled', 'pwa_app_name', 'pwa_short_name', 'pwa_theme_color', 'pwa_background_color', 'pwa_display', 'pwa_start_url', 'pwa_description', 'pwa_orientation'];

        try {
            $save = [];
            foreach ($fields as $f) {
                if (array_key_exists($f, $data)) $save[$f] = $data[$f];
            }
            if ($save) $this->settingModel->setMultiple($save, 'pwa');
            $this->settingModel->clearCache();
            (new ManifestGeneratorService($this->settingModel))->regenerate();

            return redirect()->back()->with('success', 'Configurações de PWA salvas.');
        } catch (\Throwable $e) {
            log_message('error', 'PwaController::update ' . $e->getMessage());
            return redirect()->back()->withInput()->with('error', 'Erro ao salvar.');
        }
    }

    public function uploadPwaImage()
    {
        $this->requireRole('admin');
        if (!$this->request->is('post')) {
            return $this->response->setJSON(['success' => false, 'message' => 'Método inválido']);
        }
        $type = (string)($this->request->getPost('type') ?? '');
        $allowedTypes = ['pwa_icon', 'pwa_splash', 'pwa_shortcut_icon'];
        if (!in_array($type, $allowedTypes, true)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Tipo inválido']);
        }
        $file = $this->request->getFile('image');
        if (!$file || !$file->isValid()) {
            return $this->response->setJSON(['success' => false, 'message' => 'Arquivo inválido']);
        }
        $dir = FCPATH . 'assets/uploads/pwa/';
        if (!is_dir($dir)) { mkdir($dir, 0755, true); }
        $name = date('Ymd_His') . '_' . $type . '.' . strtolower($file->getExtension());
        $file->move($dir, $name);
        $path = 'assets/uploads/pwa/' . $name;
        $sm = model(\App\Models\SettingModel::class);
        $sm->setSetting($type, $path, 'string', 'pwa');
        $sm->clearCache();
        (new ManifestGeneratorService($sm))->regenerate();
        return $this->response->setJSON(['success' => true, 'message' => 'Imagem enviada', 'url' => base_url($path)]);
    }

}