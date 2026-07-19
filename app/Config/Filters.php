<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\Honeypot;
use CodeIgniter\Filters\InvalidChars;
use CodeIgniter\Filters\SecureHeaders;

class Filters extends BaseConfig
{
    /**
     * Configura os aliases para as classes de filtros para
     * facilitar a leitura e manter o código limpo.
     *
     * @var array<string, string>
     */
    public array $aliases = [
        'csrf'            => CSRF::class,
        'toolbar'         => DebugToolbar::class,
        'honeypot'        => Honeypot::class,
        'invalidchars'    => InvalidChars::class,
        'secureheaders'   => SecureHeaders::class,
        'forcehttps'      => \CodeIgniter\Filters\ForceHTTPS::class,
        'pagecache'       => \CodeIgniter\Filters\PageCache::class,
        'performance'     => \CodeIgniter\Filters\PerformanceMetrics::class,

        // Filtros customizados para empregados e sistema (conforme Portaria MTE)
        'auth'               => \App\Filters\AuthFilter::class,
        'admin'              => \App\Filters\AdminFilter::class,
        'manager'            => \App\Filters\ManagerFilter::class,
        'role'               => \App\Filters\RoleFilter::class,
        'api-role'           => \App\Filters\ApiRoleFilter::class,
        'api-json'           => \App\Filters\ApiJsonRequestFilter::class,
        'cors'               => \App\Filters\CorsFilter::class,
        'ratelimit'          => \App\Filters\RateLimitFilter::class,
        'throttle'           => \App\Filters\ThrottleFilter::class,
        'securityheaders'    => \App\Filters\SecurityHeadersFilter::class,
        'security-headers'   => \App\Filters\SecurityHeaders::class,
        'log404'             => \App\Filters\Log404Filter::class,
        'biometric-rate-limit' => \App\Filters\BiometricRateLimitFilter::class,
        'oauth2'               => \App\Filters\OAuth2Filter::class,
        'twofa'                => \App\Filters\TwoFactorAuthFilter::class,
        'stepup'               => \App\Filters\StepUpFilter::class,
        // MELHORIA 4: Filtro de versionamento da API
        'api-version'          => \App\Filters\ApiVersionFilter::class,
        // MELHORIA 5: Correlation ID para rastreamento de requisições
        'correlation-id'       => \App\Filters\CorrelationIdFilter::class,
        'terminal-security'       => \App\Filters\PublicTerminalSecurityFilter::class,
        'supportcheck-callback'   => \App\Filters\SupportCheckCallbackFilter::class,
    ];

    /**
     * Lista de filtros executados globalmente antes e depois de toda request.
     *
     * @var array<string, array<string, array<string, string>>>|array<string, list<string>>
     */
    public array $globals = [
        'before' => [
            // MELHORIA 5: Correlation ID aplicado antes de tudo — disponível em todos os logs
            'correlation-id',
            'invalidchars',
            'secureheaders',
            // SEC-08 FIX: CSRF ativado globalmente, excluindo rotas de API (que usam Bearer token)
            // e terminais públicos (que não têm sessão de browser).
            'csrf' => [
                'except' => [
                    'api',
                    'api/*',
                    'face-terminal',
                    'face-terminal/*',
                    'kiosk/*',
                    'timesheet/punch/face/kiosk',
                    'punch-terminal',
                    'punch-terminal/*',
                    'qrcode/validate',
                ],
            ],
            // O filtro 'auth' é aplicado em rotas/grupos específicos.
        ],
        'after' => [
            'toolbar',
            'security-headers',
            'log404',
        ],
    ];

    /**
     * Lista de filtros por método HTTP.
     *
     * @var array<string, list<string>>
     */
    public array $methods = [];

    /**
     * Lista de filtros em rotas específicas "before" e "after".
     * Filtros específicos para rotas que não estão cobertas pelos filtros de grupo.
     *
     * @var array<string, array<string, list<string>>>
     */
    public array $filters = [
        // Filtros para rotas de empregados (conforme Portaria MTE)
        'twofa' => [
            'before' => [
                'auth/2fa/setup',
                'auth/2fa/enable',
                'auth/2fa/backup-codes',
                'auth/2fa/manage',
                'auth/2fa/disable',
                'auth/2fa/regenerate-backup-codes',
            ],
        ],

        'auth' => [
            'before' => [
                'employees',
                'employees/*',
                'profile',
                'profile/*',
                'dashboard',
                'dashboard/*',
                'timesheet',
                'timesheet/*',
                'notifications',
                'notifications/*',
                'chat',
                'chat/*',
                'warnings',
                'warnings/*',
                'justifications',
                'justifications/*',
                'reports',
                'reports/*',
                'my-schedules',
                'my-schedules/*',
                'shifts',
                'shifts/*',
                'schedules',
                'schedules/*',
                'qrcode/my',
                'qrcode/download',
                'biometric',
                'biometric/*',
                'lgpd',
                'lgpd/*',
                'audit',
                'audit/*',
                'auth/first-access-password',
            ],
        ],
        'manager' => [
            'before' => [
                // 'employees'/'employees/*' removidos deste wildcard global: toda rota de
                // employees/* que exige gestor/admin ja declara ['auth','manager'] (ou
                // ['auth','admin']) diretamente no proprio grupo de rota em
                // Routes/30_employees.php -- protecao redundante aqui. O problema e que o
                // wildcard tambem capturava rotas de autoatendimento deliberadamente mais
                // permissivas (filter => 'auth' apenas), como employees/change-request/* e
                // employees/(:num)/photo, bloqueando com 403 um colaborador tentando
                // solicitar alteracao cadastral ou trocar a propria foto de perfil.
                'shifts',
                'shifts/*',
                'schedules',
                'schedules/*',
                'warnings/create',
                'warnings/store',
                'warnings/add-witness',
                'warnings/*/add-witness',
                'warnings/*/refuse',
                'warnings/*/refuse-signature',
            ],
        ],

        // Restrição para admin (configurações avançadas) - manter pois é além da auth básica
        'admin' => [
            'before' => [
                'settings',
                'settings/*',
                'admin',
                'admin/*',
                'geofence',
                'geofence/*',
                'audit-logs',
                'audit-logs/*',
            ],
        ],

        // CORS para rotas da API
        'cors' => [
            'before' => [
            ],
            'after' => [
            ],
        ],

        // Rate limiting para endpoints sensíveis
        'ratelimit' => [
            'before' => [
                'auth/login',
                'auth/register',
                'auth/positions-by-department',
                // ALTO-01 (auditoria): auth/2fa/verify não tinha nenhum rate limit — dava
                // para tentar força bruta o código TOTP (6 dígitos) ou os backup codes
                // (8 dígitos) sem qualquer bloqueio, mesmo já existindo o bucket
                // '2fa_verify' dedicado em RateLimitPolicyService (5 tentativas/10min).
                'auth/2fa/verify',
                // RateLimitFilter::$endpointLimits ja mapeia estas duas rotas pro
                // bucket 'password_reset', mas o filtro nunca era aplicado a elas
                // aqui -- a unica protecao real vinha de um limitador em nivel de
                // aplicacao (PasswordResetService), nao do filtro HTTP. Adicionado
                // como defesa em profundidade, consistente com o mapeamento que
                // ja existia (e nunca era alcancado).
                'auth/forgot-password',
                'auth/reset-password',
                'timesheet/punch',
                'timesheet/punch/*',
            ],
        ],

        // Throttle para terminais públicos
        'throttle' => [
            'before' => [
                'face-terminal',
                'kiosk/token',
                'timesheet/punch/face/kiosk',
                'punch-terminal',
                'punch-terminal/*',
                'qrcode/validate',
            ],
        ],

        // Fase 11: endpoints públicos de terminal exigem token v2 com anti-replay.
        'terminal-security' => [
            'before' => [
                'punch-terminal/code',
                'punch-terminal/cpf',
                'punch-terminal/face',
                'punch-terminal/fingerprint',
                'timesheet/punch/face/kiosk',
            ],
        ],
    ];

    public array $required = [
        'before' => [
            'forcehttps',
            'pagecache',
        ],
        'after' => [
            'pagecache',
            'performance',
            'toolbar',
        ],
    ];
}

