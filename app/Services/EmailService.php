<?php

namespace App\Services;

use App\Models\EmployeeModel;
use App\Models\SettingModel;
use App\Models\AuditModel;
use App\Services\Email\EmailAuditLogger;
use App\Services\Email\EmailDeliveryService;
use App\Services\Email\EmailTemplateRenderer;
use App\Services\Email\EmailTransportConfigurator;

class EmailService
{
    protected EmployeeModel $employeeModel;
    protected SettingModel $settingModel;

    protected EmailDeliveryService $delivery;
    protected EmailTemplateRenderer $templates;
    protected EmailAuditLogger $audit;

    protected string $fromEmail;
    protected string $fromName;

    public function __construct()
    {
        $this->employeeModel = new EmployeeModel();
        $this->settingModel = new SettingModel();

        $email = \Config\Services::email();
        $transport = new EmailTransportConfigurator($this->settingModel);
        $config = $transport->configure($email);

        $this->fromEmail = $config['from_email'];
        $this->fromName = $config['from_name'];

        $this->delivery = new EmailDeliveryService($email);
        $this->templates = new EmailTemplateRenderer($this->settingModel);
        $this->audit = new EmailAuditLogger(new AuditModel(), \Config\Database::connect());
    }

    /**
     * @param string|array $to
     */
    public function send($to, string $subject, string $message, array $options = []): bool
    {
        $result = $this->delivery->send($to, $subject, $message, $options, $this->fromEmail, $this->fromName);

        if (! $result['sent']) {
            log_message('error', 'Email send failed: ' . ($result['details'] ?? 'unknown error'));
        }

        $this->audit->logSend($to, $subject, (bool) $result['sent'], $result['details'] ?? null);
        return (bool) $result['sent'];
    }

    public function sendToEmployee(int $employeeId, string $subject, string $message, array $options = []): bool
    {
        helper('operational_link');

        $employee = $this->employeeModel->find($employeeId);

        if (! $employee || empty($employee->email)) {
            log_message('warning', "Cannot send email to employee #{$employeeId}: no valid email");
            return false;
        }

        return $this->send($employee->email, $subject, $message, $options);
    }

    public function sendToEmployees(array $employeeIds, string $subject, string $message, array $options = []): int
    {
        if (empty($employeeIds)) {
            return 0;
        }

        // Batch-fetch all employees in one query — eliminates N+1 (was 1 find() per employee)
        $intIds = array_values(array_unique(array_map('intval', $employeeIds)));
        $employees = $this->employeeModel->whereIn('id', $intIds)->findAll();

        $sent = 0;
        foreach ($employees as $employee) {
            if (empty($employee->email)) {
                log_message('warning', "Cannot send email to employee #{$employee->id}: no valid email");
                continue;
            }
            if ($this->send($employee->email, $subject, $message, $options)) {
                $sent++;
            }
        }

        return $sent;
    }

    public function sendToAdmins(string $subject, string $message, array $options = []): int
    {
        $admins = $this->employeeModel
            ->where('role', 'admin')
            ->where('active', true)
            ->findAll();

        $emails = array_filter(array_map(static fn($admin) => $admin->email, $admins));

        if (empty($emails)) {
            return 0;
        }

        return $this->send($emails, $subject, $message, $options) ? count($emails) : 0;
    }

    public function sendWelcomeEmail(int $employeeId, string $temporaryPassword): bool
    {
        $employee = $this->employeeModel->find($employeeId);
        if (! $employee) {
            return false;
        }

        $subject = 'Bem-vindo ao Sistema de Ponto Eletrônico';
        $message = $this->templates->render('welcome', [
            'employee_name' => $employee->name,
            'email' => $employee->email,
            'temporary_password' => $temporaryPassword,
            'login_url' => sp_login_url(),
            'company_name' => $this->settingModel->get('company_name', 'Empresa'),
        ]);

        return $this->sendToEmployee($employeeId, $subject, $message);
    }

    public function sendPasswordResetEmail(int $employeeId, string $resetToken): bool
    {
        $employee = $this->employeeModel->find($employeeId);
        if (! $employee) {
            return false;
        }

        $subject = 'Redefinição de Senha - Sistema de Ponto';
        $message = $this->templates->render('password_reset', [
            'employee_name' => $employee->name,
            'reset_url' => base_url("auth/reset-password/{$resetToken}"),
            'expires_in' => '24 horas',
        ]);

        return $this->sendToEmployee($employeeId, $subject, $message);
    }

    public function sendPunchReceipt(int $employeeId, array $punchData, ?string $pdfPath = null): bool
    {
        $employee = $this->employeeModel->find($employeeId);
        if (! $employee) {
            return false;
        }

        $message = $this->templates->render('punch_receipt', [
            'employee_name' => $employee->name,
            'punch_time' => $punchData['punch_time'] ?? '',
            'punch_type' => $punchData['punch_type'] ?? '',
            'nsr' => $punchData['nsr'] ?? '',
            'hash' => $punchData['hash'] ?? '',
        ]);

        $options = [];
        if ($pdfPath && file_exists($pdfPath)) {
            $options['attachments'] = [$pdfPath];
        }

        return $this->sendToEmployee($employeeId, 'Comprovante de Registro de Ponto', $message, $options);
    }

    public function sendJustificationStatus(int $employeeId, string $status, ?string $reason = null): bool
    {
        $employee = $this->employeeModel->find($employeeId);
        if (! $employee) {
            return false;
        }

        $subject = $status === 'approved' ? 'Justificativa Aprovada' : 'Justificativa Rejeitada';
        $message = $this->templates->render('justification_status', [
            'employee_name' => $employee->name,
            'status' => $status,
            'reason' => $reason,
        ]);

        return $this->sendToEmployee($employeeId, $subject, $message);
    }

    public function sendMonthlyTimesheet(int $employeeId, string $month, ?string $pdfPath = null): bool
    {
        $employee = $this->employeeModel->find($employeeId);
        if (! $employee) {
            return false;
        }

        $formattedMonth = date('m/Y', strtotime($month . '-01'));
        $message = $this->templates->render('monthly_timesheet', [
            'employee_name' => $employee->name,
            'month' => $formattedMonth,
            'download_url' => sp_reports_timesheet_month_url($month),
        ]);

        $options = [];
        if ($pdfPath && file_exists($pdfPath)) {
            $options['attachments'] = [$pdfPath];
        }

        return $this->sendToEmployee($employeeId, "Espelho de Ponto - {$formattedMonth}", $message, $options);
    }

    public function sendWarningNotification(int $employeeId, string $warningType, int $warningId): bool
    {
        helper('operational_link');

        $employee = $this->employeeModel->find($employeeId);
        if (! $employee) {
            return false;
        }

        $warningModel = new \App\Models\WarningModel();
        $warning = $warningModel->find($warningId);
        $issuer = $warning ? $this->employeeModel->find((int) $warning->issued_by) : null;

        $message = $this->templates->render('warning_notification', [
            'employee_name' => $employee->name,
            'warning_type' => $warningType,
            'warning_number' => $warningId,
            'occurrence_date' => $warning->occurrence_date ?? null,
            'issuer_name' => $issuer->name ?? null,
            'reason' => $warning->reason ?? null,
            'sign_url' => sp_warning_sign_url($warningId),
            'show_url' => sp_warning_show_url($warningId),
            'company_name' => $this->settingModel->get('company_name', 'Support Solo Sondagens'),
            'support_email' => $this->settingModel->get('contact_email', 'contato@supportsondagens.com.br'),
        ]);

        return $this->sendToEmployee($employeeId, 'Advertência disciplinar - ciência e assinatura', $message);
    }

    public function sendReminder(int $employeeId, string $subject, string $reminderMessage): bool
    {
        $employee = $this->employeeModel->find($employeeId);
        if (! $employee) {
            return false;
        }

        $message = $this->templates->render('reminder', [
            'employee_name' => $employee->name,
            'reminder_message' => $reminderMessage,
        ]);

        return $this->sendToEmployee($employeeId, $subject, $message);
    }

    public function testConfiguration(string $testEmail): array
    {
        $subject = 'Teste de Configuração de E-mail';
        $message = '<h2>Teste de E-mail</h2><p>Se você recebeu este e-mail, a configuração está correta!</p>';
        $result = $this->delivery->send($testEmail, $subject, $message, [], $this->fromEmail, $this->fromName);

        $this->audit->logSend($testEmail, $subject, (bool) $result['sent'], $result['details'] ?? null);

        return [
            'success' => (bool) $result['sent'],
            'message' => $result['sent']
                ? 'E-mail de teste enviado com sucesso!'
                : 'Falha ao enviar e-mail de teste. Verifique as configurações SMTP.',
            'debug' => $result['debug'] ?? null,
        ];
    }

    public function getStatistics(): array
    {
        return $this->audit->statistics();
    }
}
