<?php

namespace App\Services\Email;

use App\Models\SettingModel;
use CodeIgniter\Email\Email;

class EmailTransportConfigurator
{
    public function __construct(private readonly SettingModel $settings)
    {
    }

    public function configure(Email $email): array
    {
        $smtpHost  = (string) $this->settings->get('smtp_host',       '');
        $smtpUser  = (string) $this->settings->get('smtp_user',       '');
        $fromEmail = (string) $this->settings->get('smtp_from_email', 'noreply@empresa.com.br');
        $fromName  = (string) $this->settings->get('smtp_from_name',  'Sistema de Ponto');

        // Consider SMTP enabled when host and user are configured (aligns with the form/testSmtp)
        $smtpEnabled = $smtpHost !== '' && $smtpUser !== '';

        if ($smtpEnabled) {
            $email->initialize([
                'protocol'   => 'smtp',
                'SMTPHost'   => $smtpHost,
                'SMTPPort'   => (int) $this->settings->get('smtp_port',     587),
                'SMTPUser'   => $smtpUser,
                'SMTPPass'   => (string) $this->settings->get('smtp_password', ''),
                'SMTPCrypto' => (string) $this->settings->get('smtp_secure',   'tls'),
                'mailType'   => 'html',
                'charset'    => 'utf-8',
                'newline'    => "\r\n",
            ]);
        }

        return [
            'smtp_enabled' => $smtpEnabled,
            'from_email'   => $fromEmail,
            'from_name'    => $fromName,
        ];
    }
}
