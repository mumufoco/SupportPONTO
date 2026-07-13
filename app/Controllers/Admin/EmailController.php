<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\SettingModel;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class EmailController extends BaseController
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

        return view('admin/settings/email', [
            'title'       => 'E-mail',
            'breadcrumbs' => [
                ['label' => 'Configurações', 'url' => sp_admin_settings_index_url()],
                ['label' => 'E-mail',        'url' => ''],
            ],
            'settings' => $this->settingModel->getByGroupMap('notifications') ?? [],
        ]);
    }

    public function update()
    {
        $this->requireRole('admin');

        if (! $this->request->is('post')) {
            return redirect()->back()->with('error', 'Método inválido');
        }

        $rules = [
            'smtp_host'      => 'required|max_length[255]',
            'smtp_port'      => 'required|is_natural_no_zero|less_than_equal_to[65535]',
            'smtp_secure'    => 'permit_empty|in_list[tls,ssl,starttls,none]',
            'smtp_from_email' => 'required|valid_email',
            'smtp_from_name' => 'required|max_length[100]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data      = security_sanitize($this->request->getPost() ?? []);
        $fields    = ['smtp_host', 'smtp_port', 'smtp_secure', 'smtp_user', 'smtp_from_email', 'smtp_from_name'];
        $encrypted = ['smtp_password'];

        try {
            $save = [];
            foreach ($fields as $f) {
                if (array_key_exists($f, $data)) {
                    $save[$f] = $data[$f];
                }
            }
            if (! empty($save)) {
                $this->settingModel->setMultiple($save, 'notifications');
            }
            foreach ($encrypted as $f) {
                if (! empty($data[$f])) {
                    $this->settingModel->setSetting($f, $data[$f], 'string', 'notifications', true);
                }
            }
            $this->settingModel->clearCache();

            return redirect()->back()->with('success', 'Configurações de e-mail salvas.');
        } catch (\Throwable $e) {
            log_message('error', 'EmailController::update ' . $e->getMessage());
            return redirect()->back()->withInput()->with('error', 'Erro ao salvar.');
        }
    }
}
