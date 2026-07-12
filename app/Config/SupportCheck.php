<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class SupportCheck extends BaseConfig
{
    /**
     * URL base do SupportCHECK (sem barra final).
     * Ex: https://check.supportsondagens.com.br
     */
    public string $baseUrl = '';

    /**
     * Token Bearer gerado no SupportCHECK (ExternalSource token).
     */
    public string $apiToken = '';

    /**
     * Timeout em segundos para chamadas HTTP ao SupportCHECK.
     */
    public int $timeout = 30;

    /**
     * Habilita/desabilita toda a integração sem remover as configurações.
     */
    public bool $enabled = false;

    public function __construct()
    {
        parent::__construct();

        $this->baseUrl  = env('SUPPORTCHECK_BASE_URL', '');
        $this->apiToken = env('SUPPORTCHECK_API_TOKEN', '');
        $this->timeout  = (int) env('SUPPORTCHECK_TIMEOUT', 30);
        $this->enabled  = (bool) env('SUPPORTCHECK_ENABLED', false);
    }
}
