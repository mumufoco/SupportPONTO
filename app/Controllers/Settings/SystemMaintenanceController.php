<?php

namespace App\Controllers\Settings;

use App\Services\Auth\SessionSecurityService;
use App\Services\Settings\SettingsSafetyService;
use App\Services\Queue\AsyncJobService;
use App\Services\Settings\SettingsTransferService;

class SystemMaintenanceController extends BaseSettingsController
{
    protected AsyncJobService $asyncJobService;
    protected SessionSecurityService $sessionSecurityService;
    protected SettingsSafetyService $settingsSafetyService;
    protected SettingsTransferService $settingsTransferService;

    public function __construct()
    {
        parent::__construct();
        $this->asyncJobService = new AsyncJobService();
        $this->sessionSecurityService = service('sessionSecurityService');
        $this->settingsTransferService = new SettingsTransferService($this->settingModel);
    }

    public function downloadBackup()
    {
        $this->requireAdminAccess();

        $guard = $this->sessionSecurityService->ensureCriticalActionAllowed($this->currentUser, $this->request, session());
        if (! ($guard['success'] ?? false)) {
            return $this->response->setJSON($guard)->setStatusCode(422);
        }

        try {
            $job = $this->asyncJobService->enqueue(AsyncJobService::TYPE_DATABASE_BACKUP, [], [
                'employee_id' => (int) ($this->currentUser->id ?? session()->get('user_id')),
                'queue' => 'admin',
                'priority' => 50,
                'max_attempts' => 2,
            ]);

            return $this->response->setJSON([
                'success' => true,
                'queued' => true,
                'message' => 'Backup enfileirado para processamento em background.',
                'job_id' => $job['job_id'],
                'status_url' => sp_async_job_status_url((string) $job['job_id']),
                'download_url' => sp_async_job_download_url((string) $job['job_id']),
            ])->setStatusCode(202);
        } catch (\Throwable $e) {
            log_message('error', 'Backup queue error: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => 'Erro ao enfileirar backup'])->setStatusCode(500);
        }
    }

    public function testSmtp()
    {
        @file_put_contents('/tmp/smtp_debug.log', date('c') . " step=start\n", FILE_APPEND);
        $this->requireAdminAccess();
        @file_put_contents('/tmp/smtp_debug.log', date('c') . " step=after_admin_check\n", FILE_APPEND);
        helper('observability');
        @file_put_contents('/tmp/smtp_debug.log', date('c') . " step=after_helper\n", FILE_APPEND);

        try {
            @file_put_contents('/tmp/smtp_debug.log', date('c') . " step=inside_try\n", FILE_APPEND);
            // Aceita valores ainda não salvos vindos do formulário (POST ou JSON) para
            // testar antes de gravar — cai para a configuração já salva quando o campo
            // não é enviado, mantendo compatibilidade com quem só manda 'test_email'.
            $posted = $this->request->getPost() ?? [];
            if ($posted === [] && str_contains((string) $this->request->getHeaderLine('Content-Type'), 'json')) {
                $posted = (array) ($this->request->getJSON(true) ?? []);
            }

            $smtpHost = trim((string) ($posted['host'] ?? $this->settingModel->getSetting('smtp_host', '')));
            $smtpUser = trim((string) ($posted['username'] ?? $this->settingModel->getSetting('smtp_user', '')));
            $smtpFrom = trim((string) ($posted['from_address'] ?? $this->settingModel->getSetting('smtp_from_email', '')));
            $fromName = trim((string) ($posted['from_name'] ?? $this->settingModel->getSetting('smtp_from_name', 'SupportPONTO')));
            $smtpPort = (int) ($posted['port'] ?? $this->settingModel->getSetting('smtp_port', 587));
            $smtpCrypto = trim((string) ($posted['encryption'] ?? $this->settingModel->getSetting('smtp_secure', 'tls')));
            // Senha em branco no formulário de teste (usuário não digitou de novo) mantém a já salva.
            $smtpPass = (string) ($posted['password'] ?? '') !== ''
                ? (string) $posted['password']
                : (string) $this->settingModel->getSetting('smtp_password', '');

            if ($smtpHost === '' || $smtpUser === '') {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Configure o Host SMTP e o Usuário SMTP antes de testar.',
                ]);
            }

            $toEmail = trim((string) ($posted['test_email'] ?? $posted['to'] ?? ''));
            if ($toEmail === '' || ! filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
                $toEmail = $smtpFrom !== '' ? $smtpFrom : $smtpUser;
            }

            $email = \Config\Services::email();
            $email->initialize([
                'protocol'    => 'smtp',
                'SMTPHost'    => $smtpHost,
                'SMTPPort'    => $smtpPort,
                'SMTPUser'    => $smtpUser,
                'SMTPPass'    => $smtpPass,
                'SMTPCrypto'  => $smtpCrypto,
                'mailType'    => 'html',
                'charset'     => 'utf-8',
                'newline'     => "\r\n",
                'SMTPTimeout' => 15,
            ]);

            $email->setFrom($smtpFrom !== '' ? $smtpFrom : $smtpUser, $fromName);
            $email->setTo($toEmail);
            $email->setSubject('Teste de Conexão SMTP — SupportPONTO');
            $email->setMessage(
                '<h2 style="color:#2d7d46">Teste de E-mail</h2>'
                . '<p>Este e-mail foi enviado pelo <strong>SupportPONTO</strong> para validar a configuração SMTP.</p>'
                . '<p>Se você está lendo esta mensagem, a configuração está correta.</p>'
                . '<hr><small>Enviado em: ' . date('d/m/Y H:i:s') . ' | Host: ' . htmlspecialchars($smtpHost) . '</small>'
            );

            @file_put_contents('/tmp/smtp_debug.log', date('c') . " step=before_send host={$smtpHost} port={$smtpPort} crypto={$smtpCrypto}\n", FILE_APPEND);
            $sendResult = $email->send();
            @file_put_contents('/tmp/smtp_debug.log', date('c') . " step=after_send result=" . ($sendResult ? '1' : '0') . "\n", FILE_APPEND);
            if ($sendResult) {
                log_message('info', "SMTP test succeeded to {$toEmail} via {$smtpHost}");
                return $this->response->setJSON([
                    'success' => true,
                    'message' => "E-mail de teste enviado para {$toEmail}. Verifique sua caixa de entrada.",
                ]);
            }

            $debug = $email->printDebugger(['headers']);
            log_message('warning', 'SMTP test failed', ['host' => $smtpHost, 'debug' => $debug]);

            return $this->response->setJSON([
                'success' => false,
                'message' => 'Falha ao enviar. Verifique host, porta, usuário e senha SMTP.',
                'debug'   => ENVIRONMENT !== 'production' ? $debug : null,
            ]);

        } catch (\Throwable $e) {
            supportponto_log_exception('settings', 'smtp_test', $e);
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Erro ao conectar ao servidor SMTP: ' . $e->getMessage(),
            ]);
        }
    }

    public function clearCache()
    {
        $this->requireAdminAccess();

        cache()->delete('design_system_css');
        cache()->delete('config_options');
        cache()->delete('design_system');

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Cache limpo com sucesso',
        ]);
    }

    public function export()
    {
        $this->requireAdminAccess();
        helper('observability');

        try {
            $payload = $this->settingsTransferService->exportPayload();

            return $this->response->download(
                'settings-export-' . date('Y-m-d') . '.json',
                json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
        } catch (\Throwable $e) {
            supportponto_log_exception('settings', 'export', $e);

            return redirect()->back()->with('error', supportponto_public_error_message('Erro ao exportar configurações.'));
        }
    }

    public function import()
    {
        $this->requireAdminAccess();

        if (! $this->request->is('post')) {
            return redirect()->back()->with('error', 'Método inválido');
        }

        $file = $this->request->getFile('settings_file');
        if (! $file || ! $file->isValid()) {
            return redirect()->back()->with('error', 'Arquivo inválido');
        }

        helper('file_upload');
        $realMime = supportponto_detect_real_mime((string) $file->getTempName());
        $jsonMimes = ['application/json', 'text/plain'];
        if (strtolower((string) $file->getExtension()) !== 'json' || ($file->getSize() ?? 0) > 2097152 || $realMime === null || !in_array($realMime, $jsonMimes, true)) {
            log_message('warning', '[UploadSecurity] settings_import_blocked ' . json_encode(['mime_type' => $realMime, 'extension' => $file->getExtension()], JSON_UNESCAPED_UNICODE));
            return redirect()->back()->with('error', 'Envie um arquivo JSON válido de até 2 MB.');
        }

        $guard = $this->sessionSecurityService->ensureCriticalActionAllowed($this->currentUser, $this->request, session());
        if (! ($guard['success'] ?? false)) {
            return redirect()->back()->with('error', $guard['message'] ?? 'Confirme sua senha para importar configurações.');
        }

        helper('observability');

        try {
            $content = file_get_contents($file->getTempName());
            $payload = json_decode($content ?: '', true, 512, JSON_THROW_ON_ERROR);
            $settingsPayload = is_array($payload) ? ($payload['settings'] ?? $payload) : [];
            $targetGroups = is_array($settingsPayload) ? array_values(array_filter(array_map(static fn ($group): string => is_string($group) ? trim($group) : '', array_keys($settingsPayload)))) : [];

            $snapshot = $this->settingsSafetyService->createPreDestructiveSnapshot(
                'settings_import',
                (int) ($this->currentUser->id ?? session()->get('user_id') ?? 0) ?: null,
                $targetGroups,
                ['source' => 'system_maintenance_import']
            );

            $result = $this->settingsTransferService->importPayload(is_array($payload) ? $payload : []);

            $message = (string) ($result['message'] ?? 'Configurações importadas com sucesso.');
            if (($snapshot['success'] ?? false) && ! empty($snapshot['relative_path'])) {
                $message .= ' Snapshot preventivo: ' . $snapshot['relative_path'];
            }

            return redirect()->back()->with('success', $message);
        } catch (\Throwable $e) {
            supportponto_log_exception('settings', 'import', $e);

            return redirect()->back()->with('error', supportponto_public_error_message('Erro ao importar configurações.'));
        }
    }

    public function reset()
    {
        $this->requireAdminAccess();

        if (! $this->request->is('post')) {
            return redirect()->back()->with('error', 'Método inválido');
        }

        $group = (string) $this->request->getPost('group');

        $guard = $this->sessionSecurityService->ensureCriticalActionAllowed($this->currentUser, $this->request, session());
        if (! ($guard['success'] ?? false)) {
            return redirect()->back()->with('error', $guard['message'] ?? 'Confirme sua senha para resetar configurações.');
        }

        try {
            $targetGroups = $group !== '' ? [$group] : [];
            $this->settingsSafetyService->assertWebResetAllowed($targetGroups);

            $snapshot = $this->settingsSafetyService->createPreDestructiveSnapshot(
                'settings_reset',
                (int) ($this->currentUser->id ?? session()->get('user_id') ?? 0) ?: null,
                $targetGroups,
                ['group' => $group !== '' ? $group : 'web_reset_blocked']
            );

            $this->settingModel->deleteGroup($group);
            $this->settingModel->clearCache();

            $message = "Configurações de {$group} resetadas com sucesso";
            if (($snapshot['success'] ?? false) && ! empty($snapshot['relative_path'])) {
                $message .= ' Snapshot preventivo: ' . $snapshot['relative_path'];
            }

            return redirect()->back()->with('success', $message);
        } catch (\Throwable $e) {
            supportponto_log_exception('settings', 'reset', $e, ['group' => $group]);

            return redirect()->back()->with('error', supportponto_public_error_message($e->getMessage() ?: 'Falha ao resetar configurações.'));
        }
    }

    public function testDatabase()
    {
        $this->requireAdminAccess();
        helper('observability');

        try {
            $db = \Config\Database::connect();
            $db->query('SELECT 1');

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Conexão com banco de dados OK',
                'database' => $db->database,
            ]);
        } catch (\Throwable $e) {
            supportponto_log_exception('settings', 'database_test', $e);

            return $this->response->setJSON([
                'success' => false,
                'message' => supportponto_public_error_message('Falha ao validar conexão com o banco de dados.'),
            ]);
        }
    }

    public function systemInfo()
    {
        $this->requireAdminAccess();

        return $this->response->setJSON([
            'success' => true,
            'info' => [
                'php_version' => PHP_VERSION,
                'codeigniter_version' => \CodeIgniter\CodeIgniter::CI_VERSION,
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'max_upload_size' => ini_get('upload_max_filesize'),
                'max_post_size' => ini_get('post_max_size'),
                'memory_limit' => ini_get('memory_limit'),
                'timezone' => date_default_timezone_get(),
                'environment' => ENVIRONMENT,
            ],
        ]);
    }
}
