<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\SettingModel;
use App\Services\Email\EmailTemplateCatalog;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class EmailTemplatesController extends BaseController
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

        $settings = $this->settingModel->getByGroupMap('notifications') ?? [];

        $templates = [];
        foreach (EmailTemplateCatalog::TEMPLATES as $key => $tmpl) {
            $currentSubject = (string) ($settings[EmailTemplateCatalog::subjectSettingKey($key)] ?? '');
            $currentBody    = (string) ($settings[EmailTemplateCatalog::bodySettingKey($key)] ?? '');

            $templates[$key] = [
                'key'          => $key,
                'name'         => $tmpl['label'],
                'subject'      => $currentSubject !== '' ? $currentSubject : $tmpl['default_subject'],
                'variables'    => $tmpl['variables'],
                'has_override' => $currentBody !== '' || $currentSubject !== '',
            ];
        }

        return view('admin/settings/email_templates/index', [
            'title'       => 'Templates de E-mail',
            'breadcrumbs' => [
                ['label' => 'Configurações', 'url' => sp_admin_settings_index_url()],
                ['label' => 'E-mail',        'url' => route_to('admin.settings.email')],
                ['label' => 'Templates de E-mail', 'url' => ''],
            ],
            'templates' => $templates,
        ]);
    }

    public function edit(string $key)
    {
        $this->requireRole('admin');

        $catalogEntry = EmailTemplateCatalog::get($key);
        if ($catalogEntry === null) {
            return redirect()->to(route_to('admin.settings.email-templates'))->with('error', 'Template não encontrado.');
        }

        $settings = $this->settingModel->getByGroupMap('notifications') ?? [];
        $currentSubject = (string) ($settings[EmailTemplateCatalog::subjectSettingKey($key)] ?? '');
        $currentBody    = (string) ($settings[EmailTemplateCatalog::bodySettingKey($key)] ?? '');
        $hasOverride    = $currentSubject !== '' || $currentBody !== '';

        return view('admin/settings/email_templates/edit', [
            'title'       => 'Template: ' . $catalogEntry['label'],
            'breadcrumbs' => [
                ['label' => 'Configurações', 'url' => sp_admin_settings_index_url()],
                ['label' => 'E-mail',        'url' => route_to('admin.settings.email')],
                ['label' => 'Templates de E-mail', 'url' => route_to('admin.settings.email-templates')],
                ['label' => $catalogEntry['label'], 'url' => ''],
            ],
            'key'      => $key,
            'template' => [
                'name'         => $catalogEntry['label'],
                'description'  => $catalogEntry['description'],
                'variables'    => $catalogEntry['variables'],
                'subject'      => $currentSubject !== '' ? $currentSubject : $catalogEntry['default_subject'],
                'content'      => $currentBody !== '' ? $currentBody : $catalogEntry['default_body'],
                'has_override' => $hasOverride,
            ],
        ]);
    }

    public function update(string $key)
    {
        $this->requireRole('admin');

        if (! $this->request->is('post')) {
            return redirect()->back()->with('error', 'Método inválido');
        }

        $catalogEntry = EmailTemplateCatalog::get($key);
        if ($catalogEntry === null) {
            return redirect()->to(route_to('admin.settings.email-templates'))->with('error', 'Template não encontrado.');
        }

        $subject = (string) ($this->request->getPost('subject') ?? '');
        $content = (string) ($this->request->getPost('content') ?? '');

        if (trim($subject) === '' || trim($content) === '') {
            return redirect()->back()->withInput()->with('error', 'Assunto e conteúdo são obrigatórios.');
        }

        try {
            $this->settingModel->setMultiple([
                // Assunto é texto puro: security_sanitize_text() (strip_tags incluso) é seguro aqui.
                EmailTemplateCatalog::subjectSettingKey($key) => security_sanitize_text($subject, 300),
                // Conteúdo é HTML de propósito (links, listas, negrito) — strip_tags destruiria o
                // template. Sanitização própria: remove bytes de controle e esquemas perigosos
                // (javascript:/data:/vbscript:) sem tocar nas tags legítimas.
                EmailTemplateCatalog::bodySettingKey($key)    => $this->sanitizeTemplateHtml($content),
            ], 'notifications');
            $this->settingModel->clearCache();

            return redirect()->to(route_to('admin.settings.email-templates.edit', $key))
                ->with('success', 'Template salvo com sucesso.');
        } catch (\Throwable $e) {
            log_message('error', 'EmailTemplatesController::update ' . $e->getMessage());
            return redirect()->back()->withInput()->with('error', 'Erro ao salvar template.');
        }
    }

    public function reset(string $key)
    {
        $this->requireRole('admin');

        if (! $this->request->is('post')) {
            return redirect()->back()->with('error', 'Método inválido');
        }

        $catalogEntry = EmailTemplateCatalog::get($key);
        if ($catalogEntry === null) {
            return redirect()->to(route_to('admin.settings.email-templates'))->with('error', 'Template não encontrado.');
        }

        try {
            $this->settingModel->setSetting(EmailTemplateCatalog::subjectSettingKey($key), '', 'string', 'notifications');
            $this->settingModel->setSetting(EmailTemplateCatalog::bodySettingKey($key), '', 'string', 'notifications');
            $this->settingModel->clearCache();

            return redirect()->to(route_to('admin.settings.email-templates.edit', $key))
                ->with('success', 'Template redefinido para o padrão.');
        } catch (\Throwable $e) {
            log_message('error', 'EmailTemplatesController::reset ' . $e->getMessage());
            return redirect()->back()->with('error', 'Erro ao redefinir template.');
        }
    }

    /**
     * Sanitização própria para HTML de template de e-mail: remove bytes nulos,
     * caracteres de controle e esquemas de URL perigosos (javascript:/data:/
     * vbscript:), sem usar strip_tags() — que apagaria toda a formatação
     * (links, listas, negrito) que o admin está tentando salvar.
     */
    private function sanitizeTemplateHtml(string $value): string
    {
        $value = str_replace("\0", '', $value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
        $value = preg_replace('/(?:javascript|data|vbscript)\s*:/iu', '', $value) ?? $value;

        return trim(mb_substr($value, 0, 20000));
    }
}
