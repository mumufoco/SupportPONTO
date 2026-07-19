<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\SettingModel;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class InformationController extends BaseController
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

        $sys = $this->settingModel->getByGroupMap('system')     ?? [];
        $app = $this->settingModel->getByGroupMap('appearance') ?? [];
        // merge: system takes precedence for shared keys
        $settings = array_merge($app, $sys);

        return view('admin/settings/information', [
            'title'       => 'Informações da Empresa',
            'breadcrumbs' => [
                ['label' => 'Configurações', 'url' => sp_admin_settings_index_url()],
                ['label' => 'Informações',   'url' => ''],
            ],
            'settings' => $settings,
        ]);
    }

    public function update()
    {
        $this->requireRole('admin');

        if (! $this->request->is('post')) {
            return redirect()->back()->with('error', 'Método inválido');
        }

        $data = security_sanitize($this->request->getPost() ?? []);

        $fields = [
            'company_name', 'company_trade_name', 'company_cnpj',
            'company_ie', 'company_cei', 'company_municipal_registration',
            'company_address', 'company_cep', 'company_city', 'company_state',
            'company_phone', 'company_whatsapp', 'company_email', 'company_website',
            'company_code', 'timezone', 'date_format', 'time_format',
            'default_language', 'currency',
            'legal_rep_name', 'legal_rep_position', 'legal_rep_phone',
            'legal_rep_email', 'legal_rep_cpf',
            'tech_rep_name', 'tech_rep_position', 'tech_rep_crea', 'tech_rep_cpf',
        ];

        try {
            $save = [];
            foreach ($fields as $f) {
                if (array_key_exists($f, $data)) {
                    $save[$f] = $data[$f];
                }
            }
            if (! empty($save)) {
                $this->settingModel->setMultiple($save, 'system');
            }
            $this->settingModel->clearCache();

            return redirect()->back()->with('success', 'Informações salvas com sucesso.');
        } catch (\Throwable $e) {
            log_message('error', 'InformationController::update ' . $e->getMessage());
            return redirect()->back()->withInput()->with('error', 'Erro ao salvar.');
        }
    }
}
