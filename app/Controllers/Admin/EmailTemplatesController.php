<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\SettingModel;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class EmailTemplatesController extends BaseController
{
    protected SettingModel $settingModel;

    private const TEMPLATES = [
        'welcome' => [
            'key'             => 'email_template_welcome',
            'subject_key'     => 'email_subject_welcome',
            'label'           => 'Boas-vindas ao colaborador',
            'description'     => 'Enviado quando um novo colaborador é cadastrado no sistema.',
            'variables'       => '{name}, {email}, {temporary_password}, {login_url}, {company_name}',
            'default_subject' => 'Bem-vindo(a) ao {company_name}',
        ],
        'password_reset' => [
            'key'             => 'email_template_password_reset',
            'subject_key'     => 'email_subject_password_reset',
            'label'           => 'Redefinição de senha',
            'description'     => 'Enviado quando o colaborador solicita redefinição de senha.',
            'variables'       => '{name}, {reset_link}, {expiry_minutes}, {company_name}',
            'default_subject' => 'Redefinição de senha — {company_name}',
        ],
        'punch_receipt' => [
            'key'             => 'email_template_punch_receipt',
            'subject_key'     => 'email_subject_punch_receipt',
            'label'           => 'Comprovante de ponto registrado',
            'description'     => 'Enviado após cada registro de ponto eletrônico.',
            'variables'       => '{name}, {punch_time}, {punch_type}, {nsr}, {company_name}',
            'default_subject' => 'Comprovante de ponto — {punch_time}',
        ],
        'punch_reminder' => [
            'key'             => 'email_template_punch_reminder',
            'subject_key'     => 'email_subject_punch_reminder',
            'label'           => 'Lembrete de registro de ponto',
            'description'     => 'Enviado para colaboradores que não registraram o ponto.',
            'variables'       => '{name}, {punch_type}, {company_name}',
            'default_subject' => 'Lembrete: registre seu ponto — {company_name}',
        ],
        'warning_notification' => [
            'key'             => 'email_template_warning_notification',
            'subject_key'     => 'email_subject_warning_notification',
            'label'           => 'Notificação de advertência',
            'description'     => 'Enviado quando uma advertência é emitida ao colaborador.',
            'variables'       => '{name}, {warning_type}, {reason}, {date}, {company_name}',
            'default_subject' => 'Advertência registrada — {company_name}',
        ],
        'lgpd_consent' => [
            'key'             => 'email_template_lgpd_consent',
            'subject_key'     => 'email_subject_lgpd_consent',
            'label'           => 'Consentimento LGPD concedido',
            'description'     => 'Enviado ao DPO quando colaborador concede consentimento LGPD.',
            'variables'       => '{name}, {email}, {purpose}, {date}, {company_name}',
            'default_subject' => '[LGPD] Consentimento concedido — {name}',
        ],
        'lgpd_revoke' => [
            'key'             => 'email_template_lgpd_revoke',
            'subject_key'     => 'email_subject_lgpd_revoke',
            'label'           => 'Revogação de consentimento LGPD',
            'description'     => 'Enviado ao DPO quando colaborador revoga o consentimento LGPD.',
            'variables'       => '{name}, {email}, {purpose}, {revoked_at}, {company_name}',
            'default_subject' => '[LGPD] Consentimento revogado — ação necessária',
        ],
        'lgpd_export' => [
            'key'             => 'email_template_lgpd_export',
            'subject_key'     => 'email_subject_lgpd_export',
            'label'           => 'Exportação de dados LGPD disponível',
            'description'     => 'Enviado ao colaborador quando a exportação de dados está pronta.',
            'variables'       => '{name}, {download_link}, {expiry_hours}, {company_name}',
            'default_subject' => '[LGPD] Sua exportação de dados está pronta',
        ],
        'report_ready' => [
            'key'             => 'email_template_report_ready',
            'subject_key'     => 'email_subject_report_ready',
            'label'           => 'Relatório gerado',
            'description'     => 'Enviado quando um relatório é gerado para o gestor ou colaborador.',
            'variables'       => '{name}, {report_name}, {period}, {download_link}, {company_name}',
            'default_subject' => 'Relatório disponível: {report_name}',
        ],
        '2fa_code' => [
            'key'             => 'email_template_2fa_code',
            'subject_key'     => 'email_subject_2fa_code',
            'label'           => 'Código de verificação 2FA',
            'description'     => 'Enviado quando a autenticação de dois fatores via e-mail está ativa.',
            'variables'       => '{name}, {code}, {expiry_minutes}, {company_name}',
            'default_subject' => 'Código de verificação — {code}',
        ],
        'alert_monitoring' => [
            'key'             => 'email_template_alert_monitoring',
            'subject_key'     => 'email_subject_alert_monitoring',
            'label'           => 'Alerta de monitoramento do sistema',
            'description'     => 'Enviado aos administradores em eventos críticos do sistema.',
            'variables'       => '{level}, {message}, {context}, {timestamp}, {company_name}',
            'default_subject' => '[ALERTA {level}] {message}',
        ],
    ];

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->settingModel = model(SettingModel::class);
    }

    public function index()
    {
        $this->requireRole('admin');

        $settings  = $this->settingModel->getByGroupMap('notifications') ?? [];
        $templates = self::TEMPLATES;

        foreach ($templates as &$tmpl) {
            $tmpl['current_body']    = (string) ($settings[$tmpl['key']] ?? '');
            $tmpl['current_subject'] = (string) ($settings[$tmpl['subject_key']] ?? '');
        }
        unset($tmpl);

        return view('admin/settings/email_templates', [
            'title'       => 'Modelos de E-mail',
            'breadcrumbs' => [
                ['label' => 'Configurações', 'url' => sp_admin_settings_index_url()],
                ['label' => 'E-mail',        'url' => route_to('admin.settings.email')],
                ['label' => 'Modelos',       'url' => ''],
            ],
            'templates'  => $templates,
            'settings'   => $settings,
        ]);
    }

    public function update()
    {
        $this->requireRole('admin');

        if (! $this->request->is('post')) {
            return redirect()->back()->with('error', 'Método inválido');
        }

        $data = security_sanitize($this->request->getPost() ?? []);

        try {
            $save = [];
            foreach (self::TEMPLATES as $tmpl) {
                if (array_key_exists($tmpl['key'], $data)) {
                    $save[$tmpl['key']] = $data[$tmpl['key']];
                }
                if (array_key_exists($tmpl['subject_key'], $data)) {
                    $save[$tmpl['subject_key']] = $data[$tmpl['subject_key']];
                }
            }

            if (! empty($save)) {
                $this->settingModel->setMultiple($save, 'notifications');
            }
            $this->settingModel->clearCache();

            return redirect()->back()->with('success', 'Modelos de e-mail salvos com sucesso.');
        } catch (\Throwable $e) {
            log_message('error', 'EmailTemplatesController::update ' . $e->getMessage());
            return redirect()->back()->withInput()->with('error', 'Erro ao salvar modelos.');
        }
    }

    public function preview()
    {
        $this->requireRole('admin');

        if (! $this->request->is('post')) {
            return $this->response->setJSON(['success' => false, 'message' => 'Método inválido']);
        }

        $body         = (string) ($this->request->getPost('body') ?? '');
        $companyName  = (string) $this->settingModel->get('company_name', 'Empresa');
        $primaryColor = (string) $this->settingModel->get('primary_color', '#4fa14f');

        $rendered = '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><style>'
            . 'body{font-family:Arial,sans-serif;color:#333;background:#f5f5f5;margin:0;padding:20px}'
            . '.wrap{max-width:600px;margin:auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.12)}'
            . '.hdr{background:' . htmlspecialchars($primaryColor) . ';color:#fff;padding:24px;text-align:center}'
            . '.body{padding:24px;line-height:1.7}'
            . '.ftr{background:#f9f9f9;padding:16px;text-align:center;font-size:12px;color:#888}'
            . '</style></head><body><div class="wrap">'
            . '<div class="hdr"><strong>' . htmlspecialchars($companyName) . '</strong><br><small>SupportPONTO</small></div>'
            . '<div class="body">' . nl2br(htmlspecialchars($body)) . '</div>'
            . '<div class="ftr">&copy; ' . date('Y') . ' ' . htmlspecialchars($companyName) . '. Todos os direitos reservados.</div>'
            . '</div></body></html>';

        return $this->response->setJSON(['success' => true, 'html' => $rendered]);
    }
}
