<?php

namespace App\Services\Email;

use App\Models\SettingModel;

class EmailTemplateRenderer
{
    public function __construct(private readonly SettingModel $settings)
    {
    }

    public function render(string $templateName, array $data = []): string
    {
        $templatePath = APPPATH . "Views/emails/{$templateName}.php";

        if (file_exists($templatePath)) {
            return view("emails/{$templateName}", $data);
        }

        return $this->basicTemplate($templateName, $data);
    }

    private function basicTemplate(string $templateName, array $data): string
    {
        $companyName = $this->settings->get('company_name', 'Empresa');
        $primaryColor = $this->settings->get('primary_color', '#9DB89D');

        $html = '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($templateName) . '</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: ' . $primaryColor . '; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background-color: #f4f4f4; }
        .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
        .button { display: inline-block; padding: 10px 20px; background-color: ' . $primaryColor . '; color: white; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>' . htmlspecialchars($companyName) . '</h1>
            <p>Sistema de Ponto Eletrônico</p>
        </div>
        <div class="content">';

        switch ($templateName) {
            case 'welcome':
                $html .= '<h2>Bem-vindo, ' . htmlspecialchars((string) ($data['employee_name'] ?? '')) . '!</h2>';
                $html .= '<p>Sua conta foi criada com sucesso.</p>';
                $html .= '<p><strong>E-mail:</strong> ' . htmlspecialchars((string) ($data['email'] ?? '')) . '</p>';
                $html .= '<p><strong>Senha Temporária:</strong> ' . htmlspecialchars((string) ($data['temporary_password'] ?? '')) . '</p>';
                $html .= '<p><a href="' . (string) ($data['login_url'] ?? '#') . '" class="button">Fazer Login</a></p>';
                $html .= '<p><small>Por favor, altere sua senha após o primeiro login.</small></p>';
                break;

            case 'punch_receipt':
                $html .= '<h2>Comprovante de Registro de Ponto</h2>';
                $html .= '<p>Olá, ' . htmlspecialchars((string) ($data['employee_name'] ?? '')) . '</p>';
                $html .= '<p>Seu registro de ponto foi registrado com sucesso:</p>';
                $html .= '<ul>';
                $html .= '<li><strong>Data/Hora:</strong> ' . htmlspecialchars((string) ($data['punch_time'] ?? '')) . '</li>';
                $html .= '<li><strong>Tipo:</strong> ' . htmlspecialchars((string) ($data['punch_type'] ?? '')) . '</li>';
                $html .= '<li><strong>NSR:</strong> ' . htmlspecialchars((string) ($data['nsr'] ?? '')) . '</li>';
                $html .= '</ul>';
                break;

            default:
                $html .= '<p>' . htmlspecialchars((string) json_encode($data)) . '</p>';
        }

        $html .= '
        </div>
        <div class="footer">
            <p>&copy; ' . date('Y') . ' ' . htmlspecialchars($companyName) . '. Todos os direitos reservados.</p>
            <p><small>Este é um email automático. Por favor, não responda.</small></p>
        </div>
    </div>
</body>
</html>';

        return $html;
    }
}
