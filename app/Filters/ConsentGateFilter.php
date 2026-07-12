<?php

namespace App\Filters;

use App\Models\UserConsentModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * ConsentGateFilter
 *
 * Bloqueia o acesso a rotas protegidas enquanto o colaborador não aceitar
 * todos os termos principais de consentimento LGPD.
 *
 * Biometria facial e digital NÃO são verificadas aqui — são tratadas
 * exclusivamente no fluxo de cadastro biométrico.
 */
class ConsentGateFilter implements FilterInterface
{
    /**
     * Tipos de consentimento obrigatórios para uso do sistema.
     * Biometria facial e digital são intencionalmente excluídos.
     */
    private const REQUIRED_TYPES = [
        'data_processing',
        'data_sharing',
        'geolocation',
        'marketing',
    ];

    /**
     * Prefixos de URI que nunca são bloqueados por este filtro.
     */
    private const BYPASS_PREFIXES = [
        'consent-gate',
        'auth',
        'api',
    ];

    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();
        $userId  = (int) ($session->get('user_id') ?? 0);

        // Sem sessão autenticada — AuthFilter já trata este caso.
        if ($userId === 0) {
            return null;
        }

        // Rotas liberadas: consent-gate, auth/*, api/*
        $path = ltrim((string) $request->getUri()->getPath(), '/');
        foreach (self::BYPASS_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return null;
            }
        }

        // Verifica se todos os consentimentos obrigatórios foram aceitos.
        $model = model(UserConsentModel::class);
        foreach (self::REQUIRED_TYPES as $type) {
            if (!$model->hasConsent($userId, $type)) {
                return redirect()->to(site_url('consent-gate'));
            }
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
    }
}
