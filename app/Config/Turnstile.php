<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Turnstile extends BaseConfig
{
    /**
     * Site key (pública, vai no HTML do widget).
     */
    public string $siteKey = '';

    /**
     * Secret key (privada, usada só na verificação server-side).
     */
    public string $secretKey = '';

    public function __construct()
    {
        parent::__construct();

        $this->siteKey   = env('TURNSTILE_SITE_KEY', '');
        $this->secretKey = env('TURNSTILE_SECRET_KEY', '');
    }

    /**
     * Sem as duas chaves configuradas, o Turnstile fica desativado
     * automaticamente: o widget não aparece e a verificação é pulada.
     */
    public function isConfigured(): bool
    {
        return $this->siteKey !== '' && $this->secretKey !== '';
    }
}
