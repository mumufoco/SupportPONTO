<?php

declare(strict_types=1);

namespace App\Services\Email;

/**
 * Catálogo único dos templates de e-mail administráveis — fonte compartilhada
 * entre a UI de administração (EmailTemplatesController) e o motor de envio
 * (EmailTemplateRenderer), para que editar um template no admin realmente
 * mude o e-mail que é enviado.
 *
 * 'welcome', 'password_reset', 'punch_receipt' e 'warning_notification' têm
 * variáveis alinhadas com o array $data real passado por EmailService — os
 * demais (lgpd_*, report_ready, 2fa_code, alert_monitoring, punch_reminder)
 * ainda não têm um fluxo de envio real conectado a EmailTemplateRenderer;
 * ficam editáveis para uso futuro, mas não afetam nenhum e-mail hoje.
 */
final class EmailTemplateCatalog
{
    public const TEMPLATES = [
        'welcome' => [
            'label'           => 'Boas-vindas ao colaborador',
            'description'     => 'Enviado quando um novo colaborador é cadastrado no sistema.',
            'variables'       => ['employee_name', 'email', 'temporary_password', 'login_url', 'company_name'],
            'default_subject' => 'Bem-vindo(a) ao {company_name}',
            'default_body'    => <<<'HTML'
<h2>Bem-vindo, {employee_name}!</h2>
<p>Sua conta no {company_name} foi criada com sucesso.</p>
<p><strong>E-mail de acesso:</strong> {email}<br>
<strong>Senha temporária:</strong> {temporary_password}</p>
<p><a href="{login_url}">Fazer login</a></p>
<p><small>Por segurança, altere sua senha assim que possível após o primeiro acesso.</small></p>
HTML,
        ],
        'password_reset' => [
            'label'           => 'Redefinição de senha',
            'description'     => 'Enviado quando o colaborador solicita redefinição de senha.',
            'variables'       => ['employee_name', 'reset_url', 'expires_in'],
            'default_subject' => 'Redefinição de senha — {company_name}',
            'default_body'    => <<<'HTML'
<h2>Redefinição de senha</h2>
<p>Olá, {employee_name}.</p>
<p>Recebemos uma solicitação para redefinir sua senha. Clique no link abaixo para continuar:</p>
<p><a href="{reset_url}">Redefinir minha senha</a></p>
<p><small>Este link expira em {expires_in}. Se você não solicitou esta alteração, ignore este e-mail.</small></p>
HTML,
        ],
        'punch_receipt' => [
            'label'           => 'Comprovante de ponto registrado',
            'description'     => 'Enviado após cada registro de ponto eletrônico.',
            'variables'       => ['employee_name', 'punch_time', 'punch_type', 'nsr', 'hash'],
            'default_subject' => 'Comprovante de ponto — {punch_time}',
            'default_body'    => <<<'HTML'
<h2>Comprovante de registro de ponto</h2>
<p>Olá, {employee_name}.</p>
<p>Seu registro de ponto foi processado com sucesso:</p>
<ul>
    <li><strong>Data/hora:</strong> {punch_time}</li>
    <li><strong>Tipo:</strong> {punch_type}</li>
    <li><strong>NSR:</strong> {nsr}</li>
</ul>
<p><small>Hash de verificação: {hash}</small></p>
HTML,
        ],
        'punch_reminder' => [
            'label'           => 'Lembrete de registro de ponto',
            'description'     => 'Enviado para colaboradores que não registraram o ponto.',
            'variables'       => ['name', 'punch_type', 'company_name'],
            'default_subject' => 'Lembrete: registre seu ponto — {company_name}',
            'default_body'    => <<<'HTML'
<h2>Lembrete de registro de ponto</h2>
<p>Olá, {name}.</p>
<p>Notamos que você ainda não registrou o ponto ({punch_type}) hoje. Não se esqueça de registrar assim que possível.</p>
<p><small>{company_name}</small></p>
HTML,
        ],
        'warning_notification' => [
            'label'           => 'Notificação de advertência',
            'description'     => 'Enviado quando uma advertência é emitida ao colaborador.',
            'variables'       => ['employee_name', 'warning_type', 'warning_number', 'occurrence_date', 'issuer_name', 'reason', 'sign_url', 'show_url', 'company_name', 'support_email'],
            'default_subject' => 'Advertência registrada — {company_name}',
            'default_body'    => <<<'HTML'
<h2>Advertência disciplinar</h2>
<p>Olá, {employee_name}.</p>
<p>Uma advertência ({warning_type}) foi registrada em seu nome em {occurrence_date}, emitida por {issuer_name}.</p>
<p><strong>Motivo:</strong> {reason}</p>
<p><a href="{sign_url}">Assinar / dar ciência</a> &nbsp;|&nbsp; <a href="{show_url}">Ver detalhes</a></p>
<p><small>Dúvidas: {support_email} — {company_name}</small></p>
HTML,
        ],
        'lgpd_consent' => [
            'label'           => 'Consentimento LGPD concedido',
            'description'     => 'Enviado ao DPO quando colaborador concede consentimento LGPD.',
            'variables'       => ['name', 'email', 'purpose', 'date', 'company_name'],
            'default_subject' => '[LGPD] Consentimento concedido — {name}',
            'default_body'    => <<<'HTML'
<h2>Consentimento LGPD concedido</h2>
<p>O colaborador {name} ({email}) concedeu consentimento para a finalidade "{purpose}" em {date}.</p>
<p><small>{company_name}</small></p>
HTML,
        ],
        'lgpd_revoke' => [
            'label'           => 'Revogação de consentimento LGPD',
            'description'     => 'Enviado ao DPO quando colaborador revoga o consentimento LGPD.',
            'variables'       => ['name', 'email', 'purpose', 'revoked_at', 'company_name'],
            'default_subject' => '[LGPD] Consentimento revogado — ação necessária',
            'default_body'    => <<<'HTML'
<h2>Consentimento LGPD revogado</h2>
<p>O colaborador {name} ({email}) revogou o consentimento para a finalidade "{purpose}" em {revoked_at}.</p>
<p>Uma ação pode ser necessária para adequar o tratamento de dados deste colaborador.</p>
<p><small>{company_name}</small></p>
HTML,
        ],
        'lgpd_export' => [
            'label'           => 'Exportação de dados LGPD disponível',
            'description'     => 'Enviado ao colaborador quando a exportação de dados está pronta.',
            'variables'       => ['name', 'download_link', 'expiry_hours', 'company_name'],
            'default_subject' => '[LGPD] Sua exportação de dados está pronta',
            'default_body'    => <<<'HTML'
<h2>Exportação de dados pronta</h2>
<p>Olá, {name}.</p>
<p>Sua exportação de dados pessoais está pronta para download:</p>
<p><a href="{download_link}">Baixar meus dados</a></p>
<p><small>Este link expira em {expiry_hours} horas. {company_name}</small></p>
HTML,
        ],
        'report_ready' => [
            'label'           => 'Relatório gerado',
            'description'     => 'Enviado quando um relatório é gerado para o gestor ou colaborador.',
            'variables'       => ['name', 'report_name', 'period', 'download_link', 'company_name'],
            'default_subject' => 'Relatório disponível: {report_name}',
            'default_body'    => <<<'HTML'
<h2>Relatório disponível</h2>
<p>Olá, {name}.</p>
<p>O relatório "{report_name}" referente a {period} está pronto:</p>
<p><a href="{download_link}">Baixar relatório</a></p>
<p><small>{company_name}</small></p>
HTML,
        ],
        '2fa_code' => [
            'label'           => 'Código de verificação 2FA',
            'description'     => 'Enviado quando a autenticação de dois fatores via e-mail está ativa.',
            'variables'       => ['name', 'code', 'expiry_minutes', 'company_name'],
            'default_subject' => 'Código de verificação — {code}',
            'default_body'    => <<<'HTML'
<h2>Código de verificação</h2>
<p>Olá, {name}.</p>
<p>Seu código de verificação é:</p>
<p style="font-size:28px;font-weight:700;letter-spacing:4px">{code}</p>
<p><small>Válido por {expiry_minutes} minutos. Se você não solicitou este código, ignore este e-mail. {company_name}</small></p>
HTML,
        ],
        'alert_monitoring' => [
            'label'           => 'Alerta de monitoramento do sistema',
            'description'     => 'Enviado aos administradores em eventos críticos do sistema.',
            'variables'       => ['level', 'message', 'context', 'timestamp', 'company_name'],
            'default_subject' => '[ALERTA {level}] {message}',
            'default_body'    => <<<'HTML'
<h2>Alerta de monitoramento</h2>
<p><strong>Nível:</strong> {level}<br>
<strong>Mensagem:</strong> {message}<br>
<strong>Quando:</strong> {timestamp}</p>
<p><strong>Contexto:</strong> {context}</p>
<p><small>{company_name}</small></p>
HTML,
        ],
    ];

    /** @return array{label:string,description:string,variables:list<string>,default_subject:string,default_body:string}|null */
    public static function get(string $key): ?array
    {
        $entry = self::TEMPLATES[$key] ?? null;
        if ($entry === null) {
            return null;
        }

        // company_logo fica disponível em todo template (ver
        // EmailTemplateRenderer::render(), que injeta o valor
        // automaticamente) -- adicionado aqui, e não em cada entrada acima,
        // para não precisar repetir isso 11 vezes nem esquecer em templates novos.
        if (! in_array('company_logo', $entry['variables'], true)) {
            $entry['variables'][] = 'company_logo';
        }

        return $entry;
    }

    public static function bodySettingKey(string $key): string
    {
        return 'email_template_' . $key;
    }

    public static function subjectSettingKey(string $key): string
    {
        return 'email_subject_' . $key;
    }

    /**
     * Substitui tokens {variavel} pelo valor correspondente em $data, escapando
     * como HTML — uso exclusivo para CORPO de e-mail (HTML). Nunca usar para
     * assunto (texto puro): ver interpolatePlain().
     */
    public static function interpolate(string $template, array $data): string
    {
        return (string) preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', static function (array $m) use ($data): string {
            $key = $m[1];
            if (! array_key_exists($key, $data) || $data[$key] === null) {
                return '';
            }

            return htmlspecialchars((string) $data[$key], ENT_QUOTES, 'UTF-8');
        }, $template);
    }

    /**
     * Substitui tokens {variavel} sem escapar HTML — uso exclusivo para
     * ASSUNTO de e-mail (texto puro). Também remove quebras de linha, que
     * não são válidas em um cabeçalho Subject.
     */
    public static function interpolatePlain(string $template, array $data): string
    {
        $result = (string) preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', static function (array $m) use ($data): string {
            $key = $m[1];
            if (! array_key_exists($key, $data) || $data[$key] === null) {
                return '';
            }

            return (string) $data[$key];
        }, $template);

        return trim((string) preg_replace('/[\r\n]+/', ' ', $result));
    }
}
