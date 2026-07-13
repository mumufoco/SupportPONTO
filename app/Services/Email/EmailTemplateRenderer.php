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
        $catalogEntry = EmailTemplateCatalog::get($templateName);

        // 1) Customização salva pelo admin (Configurações > E-mail > Templates) tem prioridade.
        if ($catalogEntry !== null) {
            $override = (string) $this->settings->get(EmailTemplateCatalog::bodySettingKey($templateName), '');
            if ($override !== '') {
                return $this->wrap($templateName, EmailTemplateCatalog::interpolate($override, $data));
            }
        }

        // 2) View dedicada em app/Views/emails/{nome}.php, se existir.
        $templatePath = APPPATH . "Views/emails/{$templateName}.php";
        if (file_exists($templatePath)) {
            return view("emails/{$templateName}", $data);
        }

        // 3) Corpo padrão do catálogo (mesmo texto que o admin vê ao editar pela primeira vez).
        if ($catalogEntry !== null) {
            return $this->wrap($templateName, EmailTemplateCatalog::interpolate($catalogEntry['default_body'], $data));
        }

        // 4) Nenhum template conhecido: fallback legado (mantém compatibilidade com nomes
        // fora do catálogo), nunca expondo um dump bruto de $data ao destinatário.
        return $this->basicTemplate($templateName, $data);
    }

    /**
     * Envolve o corpo (já interpolado) no wrapper visual padrão de e-mail
     * (cabeçalho com nome/cor da empresa, rodapé) — o mesmo wrapper usado
     * pelo preview client-side na tela de edição de templates.
     */
    private function wrap(string $templateName, string $bodyHtml): string
    {
        $companyName = (string) $this->settings->get('company_name', 'Empresa');
        $primaryColor = (string) $this->settings->get('primary_color', '#1f9d57');

        return '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">'
            . '<title>' . htmlspecialchars($templateName, ENT_QUOTES, 'UTF-8') . '</title>'
            . '<style>'
            . 'body{font-family:Arial,sans-serif;line-height:1.6;color:#333;margin:0;background:#f4f4f4}'
            . '.container{max-width:600px;margin:0 auto;padding:20px}'
            . '.header{background-color:' . htmlspecialchars($primaryColor, ENT_QUOTES, 'UTF-8') . ';color:#fff;padding:20px;text-align:center;border-radius:8px 8px 0 0}'
            . '.content{padding:24px;background-color:#fff}'
            . '.footer{padding:16px 20px;text-align:center;font-size:12px;color:#888;background:#fff;border-radius:0 0 8px 8px}'
            . 'a{color:' . htmlspecialchars($primaryColor, ENT_QUOTES, 'UTF-8') . '}'
            . '</style></head><body><div class="container">'
            . '<div class="header"><strong>' . htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') . '</strong><br><small>Sistema de Ponto Eletrônico</small></div>'
            . '<div class="content">' . $bodyHtml . '</div>'
            . '<div class="footer">&copy; ' . date('Y') . ' ' . htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') . '. Todos os direitos reservados.<br>Este é um e-mail automático, não responda.</div>'
            . '</div></body></html>';
    }

    /**
     * Fallback legado para nomes de template fora do EmailTemplateCatalog.
     * Nunca despeja $data em bruto (json_encode) no corpo enviado ao
     * destinatário — passa a listar os campos de forma legível.
     */
    private function basicTemplate(string $templateName, array $data): string
    {
        $body = '<h2>' . htmlspecialchars(ucwords(str_replace('_', ' ', $templateName)), ENT_QUOTES, 'UTF-8') . '</h2>';

        if ($data !== []) {
            $body .= '<ul>';
            foreach ($data as $key => $value) {
                if (! is_scalar($value) && $value !== null) {
                    continue;
                }
                $body .= '<li><strong>' . htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8') . ':</strong> '
                    . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '</li>';
            }
            $body .= '</ul>';
        }

        return $this->wrap($templateName, $body);
    }
}
