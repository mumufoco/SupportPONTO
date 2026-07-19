<?php

/**
 * Custom Helper Functions
 *
 * Funções auxiliares customizadas para o Sistema de Ponto Eletrônico
 */

if (!function_exists('format_cpf')) {
    /**
     * Formata CPF para exibição
     *
     * @param string $cpf CPF sem formatação
     * @return string CPF formatado (000.000.000-00)
     */
    function format_cpf(?string $cpf): string
    {
        if (empty($cpf)) {
            return '';
        }

        // Remove tudo que não for número
        $cpf = preg_replace('/[^0-9]/', '', $cpf);

        // Formata
        if (strlen($cpf) === 11) {
            return substr($cpf, 0, 3) . '.' .
                   substr($cpf, 3, 3) . '.' .
                   substr($cpf, 6, 3) . '-' .
                   substr($cpf, 9, 2);
        }

        return $cpf;
    }
}

if (!function_exists('format_phone_br')) {
    /**
     * Formata telefone brasileiro
     *
     * @param string $phone Telefone sem formatação
     * @return string Telefone formatado
     */
    function format_phone_br(?string $phone): string
    {
        if (empty($phone)) {
            return '';
        }

        // Remove tudo que não for número
        $phone = preg_replace('/[^0-9]/', '', $phone);

        $length = strlen($phone);

        // Celular com DDD: (00) 00000-0000
        if ($length === 11) {
            return '(' . substr($phone, 0, 2) . ') ' .
                   substr($phone, 2, 5) . '-' .
                   substr($phone, 7, 4);
        }

        // Telefone fixo com DDD: (00) 0000-0000
        if ($length === 10) {
            return '(' . substr($phone, 0, 2) . ') ' .
                   substr($phone, 2, 4) . '-' .
                   substr($phone, 6, 4);
        }

        return $phone;
    }
}

if (!function_exists('format_datetime_br')) {
    /**
     * Formata data/hora para padrão brasileiro
     *
     * @param string $datetime Data/hora
     * @param bool $showSeconds Mostrar segundos
     * @return string Data/hora formatada (dd/mm/YYYY HH:mm:ss)
     */
    function format_datetime_br(?string $datetime, bool $showSeconds = true): string
    {
        if (empty($datetime)) {
            return '';
        }

        try {
            $date = new DateTime($datetime);
            $format = $showSeconds ? 'd/m/Y H:i:s' : 'd/m/Y H:i';
            return $date->format($format);
        } catch (Exception $e) {
            return $datetime;
        }
    }
}

if (!function_exists('format_date_br')) {
    /**
     * Formata data para padrão brasileiro
     *
     * @param string $date Data
     * @return string Data formatada (dd/mm/YYYY)
     */
    function format_date_br(?string $date): string
    {
        if (empty($date)) {
            return '';
        }

        try {
            $dateObj = new DateTime($date);
            return $dateObj->format('d/m/Y');
        } catch (Exception $e) {
            return $date;
        }
    }
}

if (!function_exists('format_time')) {
    /**
     * Formata horário
     *
     * @param string $time Horário
     * @param bool $showSeconds Mostrar segundos
     * @return string Horário formatado (HH:mm:ss ou HH:mm)
     */
    function format_time(?string $time, bool $showSeconds = true): string
    {
        if (empty($time)) {
            return '';
        }

        try {
            $timeObj = new DateTime($time);
            $format = $showSeconds ? 'H:i:s' : 'H:i';
            return $timeObj->format($format);
        } catch (Exception $e) {
            return $time;
        }
    }
}

if (!function_exists('format_month_year_br')) {
    /**
     * Formata mês/ano para padrão brasileiro
     *
     * @param string $date Data
     * @return string Mês/ano formatado (Mês YYYY)
     */
    function format_month_year_br(?string $date): string
    {
        if (empty($date)) {
            return '';
        }

        try {
            $dateObj = new DateTime($date);
            $months = [
                'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
                'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'
            ];
            $month = $months[(int)$dateObj->format('n') - 1];
            return $month . ' ' . $dateObj->format('Y');
        } catch (Exception $e) {
            return $date;
        }
    }
}

if (!function_exists('get_day_of_week_br')) {
    /**
     * Retorna dia da semana em português
     *
     * @param string $date Data
     * @return string Dia da semana
     */
    function get_day_of_week_br(?string $date): string
    {
        if (empty($date)) {
            return '';
        }

        try {
            $dateObj = new DateTime($date);
            $days = [
                'Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira',
                'Quinta-feira', 'Sexta-feira', 'Sábado'
            ];
            return $days[(int)$dateObj->format('w')];
        } catch (Exception $e) {
            return '';
        }
    }
}

if (!function_exists('format_balance')) {
    /**
     * Formata saldo de horas (minutos para HH:mm)
     *
     * @param int $minutes Minutos
     * @return string Saldo formatado (+HH:mm ou -HH:mm)
     */
    function format_balance(?int $minutes): string
    {
        if ($minutes === null) {
            return '00:00';
        }

        $sign = $minutes < 0 ? '-' : '+';
        $minutes = abs($minutes);

        $hours = floor($minutes / 60);
        $mins = $minutes % 60;

        return $sign . sprintf('%02d:%02d', $hours, $mins);
    }
}

if (!function_exists('time_ago_br')) {
    /**
     * Retorna quanto tempo atrás uma data ocorreu
     *
     * @param string $datetime Data/hora
     * @return string Tempo decorrido (ex: "há 5 minutos", "há 2 horas")
     */
    function time_ago_br(?string $datetime): string
    {
        if (empty($datetime)) {
            return '';
        }

        try {
            $date = new DateTime($datetime);
            $now = new DateTime();
            $diff = $now->diff($date);

            if ($diff->y > 0) {
                return $diff->y === 1 ? 'há 1 ano' : "há {$diff->y} anos";
            }
            if ($diff->m > 0) {
                return $diff->m === 1 ? 'há 1 mês' : "há {$diff->m} meses";
            }
            if ($diff->d > 0) {
                return $diff->d === 1 ? 'há 1 dia' : "há {$diff->d} dias";
            }
            if ($diff->h > 0) {
                return $diff->h === 1 ? 'há 1 hora' : "há {$diff->h} horas";
            }
            if ($diff->i > 0) {
                return $diff->i === 1 ? 'há 1 minuto' : "há {$diff->i} minutos";
            }

            return 'agora';
        } catch (Exception $e) {
            return '';
        }
    }
}

if (!function_exists('get_client_ip')) {
    /**
     * Obtém IP do cliente
     *
     * @return string IP do cliente
     */
    function get_client_ip(): string
    {
        $request = \Config\Services::request();
        return $request->getIPAddress();
    }
}

if (!function_exists('get_user_agent')) {
    /**
     * Obtém User Agent do cliente
     *
     * @return string User Agent
     */
    function get_user_agent(): string
    {
        $request = \Config\Services::request();

        // Fora de um request HTTP real (worker de fila via CLI, spark, etc.),
        // $request é um CLIRequest — que não implementa getUserAgent() e
        // derrubava com fatal error qualquer processamento assíncrono que
        // passasse por aqui (ex.: cadastro facial, sempre processado pelo
        // worker `jobs:process`, nunca em contexto web).
        if (! ($request instanceof \CodeIgniter\HTTP\IncomingRequest)) {
            return $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        }

        return $request->getUserAgent()->getAgentString();
    }
}

if (!function_exists('money_br')) {
    /**
     * Formata valor monetário para padrão brasileiro
     *
     * @param float $value Valor
     * @param bool $showSymbol Mostrar símbolo R$
     * @return string Valor formatado
     */
    function money_br(?float $value, bool $showSymbol = true): string
    {
        if ($value === null) {
            return $showSymbol ? 'R$ 0,00' : '0,00';
        }

        $formatted = number_format($value, 2, ',', '.');
        return $showSymbol ? 'R$ ' . $formatted : $formatted;
    }
}

if (!function_exists('truncate_text')) {
    /**
     * Trunca texto com reticências
     *
     * @param string $text Texto
     * @param int $length Comprimento máximo
     * @param string $suffix Sufixo (padrão: '...')
     * @return string Texto truncado
     */
    function truncate_text(?string $text, int $length = 100, string $suffix = '...'): string
    {
        if (empty($text)) {
            return '';
        }

        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length) . $suffix;
    }
}

if (!function_exists('sanitize_filename')) {
    /**
     * Sanitiza nome de arquivo
     *
     * @param string $filename Nome do arquivo
     * @return string Nome sanitizado
     */
    function sanitize_filename(string $filename): string
    {
        // Remove caracteres especiais
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

        // Remove múltiplos underscores
        $filename = preg_replace('/_+/', '_', $filename);

        return $filename;
    }
}


if (!function_exists('system_setting')) {
    /**
     * Retorna configuração do sistema com cache em memória para a requisição atual.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function system_setting(string $key, $default = null)
    {
        static $cache = [];

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        try {
            $model = new \App\Models\SettingModel();
            $cache[$key] = $model->get($key, $default);
            return $cache[$key];
        } catch (\Throwable $e) {
            log_message('error', 'system_setting helper error: ' . $e->getMessage());
            return $default;
        }
    }
}


if (!function_exists('support_logo_url')) {
    /**
     * Resolve logo URL by variant: small|crop|original
     * Falls back through: company_logo_{variant} → company_logo → logo_path → SVG
     */
    function support_logo_url(string $variant = 'small'): string
    {
        // 'auth'    -> logo das paginas sem login (login, 2FA, recuperacao, kiosk)
        // 'sidebar' -> logo acima do menu lateral
        if ($variant === 'auth') {
            $primaryKey   = 'logo_auth_path';
            $fallbackKeys = ['company_logo_crop', 'company_logo', 'logo_path'];
        } elseif ($variant === 'sidebar') {
            $primaryKey   = 'logo_sidebar_path';
            $fallbackKeys = ['company_logo_small', 'company_logo', 'logo_path'];
        } else {
            $map = [
                'small'    => 'company_logo_small',
                'crop'     => 'company_logo_crop',
                'original' => 'company_logo_original',
            ];
            $primaryKey   = $map[$variant] ?? 'company_logo_small';
            $fallbackKeys = ['company_logo', 'logo_path'];
        }

        $resolveFile = static function (string $file): ?string {
            if (empty($file)) {
                return null;
            }
            if (preg_match('#^(https?:)?//#i', $file)) {
                return $file;
            }
            $normalizedFile = ltrim($file, '/');
            $sep = DIRECTORY_SEPARATOR;
            $normalizedForOs = str_replace(['/'], $sep, $normalizedFile);
            $absolutePath = FCPATH . $normalizedForOs;
            if (is_file($absolutePath)) {
                return base_url($normalizedFile) . '?v=' . filemtime($absolutePath);
            }
            $legacyPath = FCPATH . 'uploads' . $sep . 'branding' . $sep . basename($normalizedFile);
            if (is_file($legacyPath)) {
                return base_url('uploads/branding/' . basename($normalizedFile));
            }
            return null;
        };

        foreach (array_merge([$primaryKey], $fallbackKeys) as $key) {
            $file = (string) system_setting($key, '');
            $url  = $resolveFile($file);
            if ($url !== null) {
                return $url;
            }
        }

        return base_url('images/supportsolo-mark.svg');
    }
}

if (!function_exists('support_favicon_url')) {
    /**
     * Resolve favicon URL from settings, falls back to static asset.
     */
    function support_favicon_url(): string
    {
        $resolveFile = static function (string $file): ?string {
            if (empty($file)) { return null; }
            if (preg_match('#^(https?:)?//#i', $file)) { return $file; }
            $normalized = ltrim($file, '/');
            $abs = FCPATH . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
            if (is_file($abs)) {
                return base_url($normalized) . '?v=' . filemtime($abs);
            }
            return null;
        };

        foreach (['favicon_path', 'company_favicon'] as $key) {
            $url = $resolveFile((string) system_setting($key, ''));
            if ($url !== null) { return $url; }
        }

        return base_url('assets/img/favicon.png');
    }
}


if (!function_exists('support_login_background_url')) {
    /**
     * Resolve login background URL from settings.
     */
    function support_login_background_url(): string
    {
        foreach (['login_background_path', 'login_background'] as $key) {
            $raw = (string) system_setting($key, '');
            if (empty($raw)) { continue; }
            if (preg_match('#^(https?:)?//#i', $raw)) { return $raw; }
            $normalized = ltrim($raw, '/');
            $abs = FCPATH . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
            if (is_file($abs)) {
                return base_url($normalized) . '?v=' . filemtime($abs);
            }
        }
        return '';
    }
}

if (!function_exists('sp_consent_term_variables')) {
    /**
     * Mapa de variaveis (nome da variavel => valor atual) disponiveis para uso
     * no corpo dos termos de consentimento (settings/consent-terms), lidas de
     * Admin/Informacoes da Empresa. Sintaxe de uso no corpo do termo: {nome},
     * mesmo padrao usado pelos templates de e-mail (EmailTemplateCatalog).
     *
     * @return array<string,string>
     */
    function sp_consent_term_variables(): array
    {
        $get = static fn (string $key): string => (string) system_setting($key, '');

        $address = trim(implode(', ', array_filter([
            $get('company_address'),
            $get('company_city') !== '' && $get('company_state') !== ''
                ? $get('company_city') . '/' . $get('company_state')
                : ($get('company_city') ?: $get('company_state')),
        ])));
        if ($get('company_cep') !== '') {
            $address = trim($address . ' — CEP ' . $get('company_cep'), ' —');
        }

        return [
            'empresa_razao_social'        => $get('company_name'),
            'empresa_nome_fantasia'       => $get('company_trade_name'),
            'empresa_cnpj'                => $get('company_cnpj'),
            'empresa_ie'                  => $get('company_ie'),
            'empresa_cei'                 => $get('company_cei'),
            'empresa_inscricao_municipal' => $get('company_municipal_registration'),
            'empresa_endereco'            => $get('company_address'),
            'empresa_cep'                 => $get('company_cep'),
            'empresa_cidade'              => $get('company_city'),
            'empresa_uf'                  => $get('company_state'),
            'empresa_endereco_completo'   => $address,
            'empresa_telefone'            => $get('company_phone'),
            'empresa_whatsapp'            => $get('company_whatsapp'),
            'empresa_email'               => $get('company_email'),
            'empresa_site'                => $get('company_website'),
            'responsavel_legal_nome'      => $get('legal_rep_name'),
            'responsavel_legal_cargo'     => $get('legal_rep_position'),
            'responsavel_legal_telefone'  => $get('legal_rep_phone'),
            'responsavel_legal_email'     => $get('legal_rep_email'),
            'responsavel_legal_cpf'       => $get('legal_rep_cpf'),
            'responsavel_tecnico_nome'    => $get('tech_rep_name'),
            'responsavel_tecnico_cargo'   => $get('tech_rep_position'),
            'responsavel_tecnico_crea'    => $get('tech_rep_crea'),
            'responsavel_tecnico_cpf'     => $get('tech_rep_cpf'),
        ];
    }
}

if (!function_exists('sp_apply_consent_variables')) {
    /**
     * Substitui as variaveis {nome} (ver sp_consent_term_variables()) pelo
     * valor cadastrado em Admin/Informacoes da Empresa. Aplicado tanto na
     * pre-visualizacao do termo quanto no snapshot gravado em
     * user_consents.consent_text no momento do aceite -- o registro juridico
     * permanente deve conter os dados reais, nunca o placeholder.
     */
    function sp_apply_consent_variables(?string $text, bool $escapeValues = false): string
    {
        $text = (string) ($text ?? '');
        if ($text === '' || strpos($text, '{') === false) {
            return $text;
        }

        $vars = sp_consent_term_variables();

        return (string) preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', static function (array $m) use ($vars, $escapeValues): string {
            if (! array_key_exists($m[1], $vars)) {
                return $m[0];
            }

            return $escapeValues ? esc($vars[$m[1]]) : $vars[$m[1]];
        }, $text);
    }
}

if (!function_exists('sp_render_consent_body')) {
    /**
     * Renderiza o corpo de um termo de consentimento (LGPD) com seguranca.
     *
     * Termos redigidos como codigo-fonte HTML (Biometric\BiometricConsentController::
     * saveTerm()) ja chegam aqui sanitizados por ConsentTermSanitizerService
     * no momento do save -- seguro pra ecoar direto. Termos legados em texto
     * puro (sem nenhuma marcacao) continuam escapados + nl2br(), como sempre
     * foram. As variaveis {nome} (dados da empresa/responsaveis) sao
     * resolvidas antes de renderizar -- com os valores escapados no ramo HTML
     * (ecoado sem esc() global), e crus no ramo texto-puro (que passa pelo
     * esc() do corpo inteiro logo abaixo).
     */
    function sp_render_consent_body(?string $body): string
    {
        $raw = (string) ($body ?? '');
        if ($raw === '') {
            return '';
        }

        $isHtml = trim(strip_tags($raw)) !== trim($raw);

        if ($isHtml) {
            return sp_apply_consent_variables($raw, true);
        }

        return nl2br(esc(sp_apply_consent_variables($raw, false)));
    }
}

if (!function_exists('sp_pending_gate_consents')) {
    /**
     * Tipos de consentimento obrigatorios (ConsentGateCatalog::TYPES) que o
     * colaborador ainda nao aceitou. Usado pelo lembrete flutuante exibido no
     * dashboard (ver partials/consent_gate_modal) -- nao bloqueia navegacao,
     * so avisa enquanto houver pendencia.
     *
     * @return array<string,array{label:string,icon:string,description:string}>
     */
    function sp_pending_gate_consents(int $employeeId): array
    {
        if ($employeeId <= 0) {
            return [];
        }

        try {
            $model = model(\App\Models\UserConsentModel::class);
        } catch (\Throwable $e) {
            return [];
        }

        $pending = [];
        foreach (\App\Services\LGPD\ConsentGateCatalog::TYPES as $type => $meta) {
            if (! $model->hasConsent($employeeId, $type)) {
                $pending[$type] = $meta;
            }
        }

        return $pending;
    }
}
