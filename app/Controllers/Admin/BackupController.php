<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\SettingModel;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class BackupController extends BaseController
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

        return view('admin/settings/backup', [
            'title'       => 'Backup',
            'breadcrumbs' => [
                ['label' => 'Configurações', 'url' => sp_admin_settings_index_url()],
                ['label' => 'Backup',        'url' => ''],
            ],
            'settings' => $this->settingModel->getByGroupMap('backup') ?? [],
        ]);
    }

    public function update()
    {
        $this->requireRole('admin');

        if (! $this->request->is('post')) {
            return redirect()->back()->with('error', 'Método inválido');
        }

        $data   = security_sanitize($this->request->getPost() ?? []);
        $fields = ['backup_enabled', 'backup_frequency', 'backup_retention_days', 'backup_storage'];

        try {
            $save = [];
            foreach ($fields as $f) {
                if (array_key_exists($f, $data)) $save[$f] = $data[$f];
            }
            if ($save) $this->settingModel->setMultiple($save, 'backup');
            $this->settingModel->clearCache();

            return redirect()->back()->with('success', 'Configurações de backup salvas.');
        } catch (\Throwable $e) {
            log_message('error', 'BackupController::update ' . $e->getMessage());
            return redirect()->back()->withInput()->with('error', 'Erro ao salvar.');
        }
    }
}
