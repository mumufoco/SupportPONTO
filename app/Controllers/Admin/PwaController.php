<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\SettingModel;
use App\Services\Pwa\ManifestGeneratorService;
use App\Services\Upload\SafeUploadService;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class PwaController extends BaseController
{
    /** Tamanhos de ícone geridos pela tela — os mínimos para um PWA ser instalável. */
    private const ICON_SIZES = [192, 512];

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
            'pwa_app_name'         => $app['company_name'] ?? 'SupportPONTO',
            'pwa_short_name'       => 'PONTO',
            'pwa_theme_color'      => $app['primary_color'] ?? '#4fa14f',
            'pwa_background_color' => '#ffffff',
            'pwa_display'          => 'standalone',
            'pwa_start_url'        => '/',
            'pwa_description'      => 'Sistema de Ponto Eletrônico',
            'pwa_orientation'      => 'any',
        ];
        $settings = array_merge($defaults, $pwa);

        $icons = [];
        foreach (self::ICON_SIZES as $size) {
            $path = (string) ($pwa['pwa_icon_' . $size] ?? '');
            $icons[$size] = $path !== '' && is_file(FCPATH . str_replace('/', DIRECTORY_SEPARATOR, $path))
                ? ['path' => $path, 'url' => base_url($path) . '?v=' . filemtime(FCPATH . str_replace('/', DIRECTORY_SEPARATOR, $path))]
                : null;
        }

        return view('admin/settings/pwa', [
            'title'       => 'PWA',
            'breadcrumbs' => [
                ['label' => 'Configurações', 'url' => sp_admin_settings_index_url()],
                ['label' => 'PWA',           'url' => ''],
            ],
            'settings' => $settings,
            'icons'    => $icons,
        ]);
    }

    public function update()
    {
        $this->requireRole('admin');

        if (! $this->request->is('post')) {
            return redirect()->back()->with('error', 'Método inválido');
        }

        $hexRule = 'permit_empty|regex_match[/^#[0-9A-Fa-f]{6}$/]';
        $rules = [
            'pwa_app_name'         => 'required|max_length[80]',
            'pwa_short_name'       => 'required|max_length[30]',
            'pwa_description'      => 'permit_empty|max_length[200]',
            'pwa_theme_color'      => $hexRule,
            'pwa_background_color' => $hexRule,
            'pwa_display'          => 'permit_empty|in_list[standalone,fullscreen,minimal-ui,browser]',
            'pwa_orientation'      => 'permit_empty|in_list[any,portrait,landscape]',
            'pwa_start_url'        => 'permit_empty|max_length[255]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data   = security_sanitize($this->request->getPost() ?? []);
        $fields = ['pwa_app_name', 'pwa_short_name', 'pwa_theme_color', 'pwa_background_color', 'pwa_display', 'pwa_start_url', 'pwa_description', 'pwa_orientation'];

        try {
            $save = [];
            foreach ($fields as $f) {
                if (array_key_exists($f, $data)) {
                    $save[$f] = $data[$f];
                }
            }
            if ($save) {
                $this->settingModel->setMultiple($save, 'pwa');
            }
            $this->settingModel->clearCache();
            (new ManifestGeneratorService($this->settingModel))->regenerate();

            return redirect()->back()->with('success', 'Configurações de PWA salvas.');
        } catch (\Throwable $e) {
            log_message('error', 'PwaController::update ' . $e->getMessage());
            return redirect()->back()->withInput()->with('error', 'Erro ao salvar.');
        }
    }

    public function uploadIcon(int $size)
    {
        $this->requireRole('admin');

        if (! $this->request->is('post')) {
            return $this->response->setJSON(['success' => false, 'message' => 'Método inválido']);
        }
        if (! in_array($size, self::ICON_SIZES, true)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Tamanho de ícone inválido']);
        }

        $file = $this->request->getFile('icon');
        if (! $file) {
            return $this->response->setJSON(['success' => false, 'message' => 'Arquivo inválido']);
        }

        $safeUpload = new SafeUploadService();
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        $allowedMimes = $safeUpload->allowedMimesForGroups(['image_public']);
        $validation = $safeUpload->validateUploadedFile($file, $allowedExtensions, $allowedMimes, 4_194_304);
        if (! $validation['success']) {
            return $this->response->setJSON(['success' => false, 'message' => $validation['message']]);
        }

        $dir = FCPATH . 'assets/uploads/pwa/';
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Não foi possível preparar o diretório de upload.']);
        }

        $settingKey = 'pwa_icon_' . $size;
        $oldPath = (string) $this->settingModel->get($settingKey, '');
        $this->deleteStoredIcon($oldPath);

        $name = 'icon_' . $size . '_' . bin2hex(random_bytes(8)) . '.' . $validation['extension'];

        try {
            $file->move($dir, $name);
        } catch (\Throwable $e) {
            log_message('error', 'PwaController::uploadIcon move failed: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => 'Erro ao salvar o arquivo.']);
        }

        $targetPath = $dir . $name;
        $realMime = $safeUpload->detectRealMime($targetPath);
        if ($realMime === null || ! in_array($realMime, $allowedMimes, true)) {
            @unlink($targetPath);
            $safeUpload->audit('pwa_icon_post_move_mime_blocked', ['mime_type' => $realMime, 'size' => $size]);
            return $this->response->setJSON(['success' => false, 'message' => 'Tipo de arquivo não permitido.']);
        }

        $path = 'assets/uploads/pwa/' . $name;
        $this->settingModel->setSetting($settingKey, $path, 'string', 'pwa');
        $this->settingModel->clearCache();
        (new ManifestGeneratorService($this->settingModel))->regenerate();

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Ícone enviado com sucesso.',
            'url' => base_url($path) . '?v=' . time(),
        ]);
    }

    public function deleteIcon(int $size)
    {
        $this->requireRole('admin');

        if (! $this->request->is('post')) {
            return $this->response->setJSON(['success' => false, 'message' => 'Método inválido']);
        }
        if (! in_array($size, self::ICON_SIZES, true)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Tamanho de ícone inválido']);
        }

        $settingKey = 'pwa_icon_' . $size;
        $current = (string) $this->settingModel->get($settingKey, '');
        if ($current === '') {
            return $this->response->setJSON(['success' => false, 'message' => 'Não há ícone para remover.']);
        }

        $this->deleteStoredIcon($current);
        $this->settingModel->setSetting($settingKey, '', 'string', 'pwa');
        $this->settingModel->clearCache();
        (new ManifestGeneratorService($this->settingModel))->regenerate();

        return $this->response->setJSON(['success' => true, 'message' => 'Ícone removido com sucesso.']);
    }

    private function deleteStoredIcon(string $relativePath): void
    {
        if ($relativePath === '' || preg_match('#^(https?:)?//#i', $relativePath)) {
            return;
        }

        $absolute = FCPATH . str_replace('/', DIRECTORY_SEPARATOR, ltrim($relativePath, '/'));
        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }
}
