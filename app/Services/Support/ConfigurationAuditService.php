<?php

declare(strict_types=1);

namespace App\Services\Support;

use Config\ProcessSafety;

class ConfigurationAuditService
{
    public function build(): array
    {
        return [
            'generated_at' => date(DATE_ATOM),
            'status' => $this->overallStatus($checks = [
                $this->cacheCheck(),
                $this->baseUrlCheck(),
                $this->proxyCheck(),
                $this->sessionCheck(),
                $this->backupRuntimeCheck(),
                $this->settingsSnapshotCheck(),
            ]),
            'checks' => $checks,
        ];
    }

    private function cacheCheck(): array
    {
        $cache = config('Cache');
        $handler = (string) ($cache->handler ?? 'unknown');
        $backup = (string) ($cache->backupHandler ?? 'unknown');

        $severity = 'ok';
        $details = 'Configuração de cache coerente para o ambiente atual.';

        if ($handler === 'redis' && empty($cache->redis['host'])) {
            $severity = 'blocker';
            $details = 'Redis configurado como handler principal sem host definido.';
        } elseif ($handler !== 'redis' && filter_var(env('cache.requireRedis', false), FILTER_VALIDATE_BOOL)) {
            $severity = 'warning';
            $details = 'Redis exigido por ambiente, mas handler ativo não é redis.';
        }

        return [
            'key' => 'cache_handler',
            'label' => 'Cache / Redis',
            'severity' => $severity,
            'value' => $handler . ' (backup: ' . $backup . ')',
            'details' => $details,
        ];
    }


    private function baseUrlCheck(): array
    {
        $app = config('App');
        $baseUrl = (string) ($app->baseURL ?? '');
        $allowedHostnames = is_array($app->allowedHostnames ?? null) ? $app->allowedHostnames : [];

        $severity = 'ok';
        $details = 'Base URL e hostnames coerentes para o ambiente atual.';

        if ($baseUrl === '') {
            $severity = 'blocker';
            $details = 'baseURL vazio após a resolução do App config.';
        } elseif (ENVIRONMENT === 'production' && ! str_starts_with($baseUrl, 'https://')) {
            $severity = 'warning';
            $details = 'Em produção, baseURL deveria resolver para HTTPS.';
        }

        if (ENVIRONMENT === 'production' && $allowedHostnames === []) {
            $severity = $severity === 'blocker' ? 'blocker' : 'warning';
            $details = 'Em produção, app.allowedHostnames deveria ser explícito para evitar geração de URL baseada em host não confiável.';
        }

        foreach ($allowedHostnames as $hostname) {
            if (is_string($hostname) && str_contains(strtolower($hostname), 'change-me.example.com')) {
                $severity = 'blocker';
                $details = 'app.allowedHostnames ainda contém placeholder CHANGE-ME.example.com.';
                break;
            }
        }

        return [
            'key' => 'base_url_hostnames',
            'label' => 'Base URL / Hostnames',
            'severity' => $severity,
            'value' => sprintf('baseURL=%s hostnames=%d', $baseUrl !== '' ? $baseUrl : '[vazio]', count($allowedHostnames)),
            'details' => $details,
        ];
    }
    private function proxyCheck(): array
    {
        $app = config('App');
        $forceSecure = (bool) ($app->forceGlobalSecureRequests ?? false);
        $proxyIps = is_array($app->proxyIPs ?? null) ? $app->proxyIPs : [];
        $cookieSecure = (bool) ($app->cookieSecure ?? false);

        $severity = 'ok';
        $details = 'Política de proxy/HTTPS consistente.';

        if ($forceSecure && $proxyIps === []) {
            $severity = 'warning';
            $details = 'HTTPS global ativo sem app.proxyIPs configurado. Isso só é aceitável quando o PHP termina TLS diretamente.';
        } elseif ($cookieSecure && $proxyIps === [] && ENVIRONMENT === 'production') {
            $severity = 'warning';
            $details = 'cookieSecure ativo em produção sem proxies confiáveis definidos. Se houver proxy reverso, declare app.proxyIPs com IPs ou CIDRs confiáveis.';
        }

        $legacyCookieSecure = env('session.cookieSecure');
        $legacyCookieFlag = env('cookie.secure');
        $appCookieSecure = env('app.cookieSecure');

        if (is_string($appCookieSecure) && $appCookieSecure !== '') {
            $legacyValues = array_filter([$legacyCookieSecure, $legacyCookieFlag], static fn ($value) => is_string($value) && trim($value) !== '');
            foreach ($legacyValues as $legacyValue) {
                if (strtolower(trim((string) $legacyValue)) !== strtolower(trim((string) $appCookieSecure))) {
                    $severity = 'warning';
                    $details = 'Valores legados de cookieSecure divergem de app.cookieSecure e podem causar comportamento inconsistente em instalação/bootstrap.';
                    break;
                }
            }
        }

        return [
            'key' => 'proxy_https',
            'label' => 'HTTPS / Proxy',
            'severity' => $severity,
            'value' => sprintf('forceSecure=%s cookieSecure=%s proxies=%d', $forceSecure ? 'true' : 'false', $cookieSecure ? 'true' : 'false', count($proxyIps)),
            'details' => $details,
        ];
    }

    private function sessionCheck(): array
    {
        $savePath = (string) ini_get('session.save_path');
        $session = config('Session');
        $app = config('App');
        $matchIp = (bool) ($session->matchIP ?? false);
        $proxyIps = is_array($app->proxyIPs ?? null) ? $app->proxyIPs : [];

        $severity = 'ok';
        $details = 'Configuração de sessão coerente para o ambiente atual.';

        if ($savePath === '') {
            $severity = 'warning';
            $details = 'session.save_path vazio; o bootstrap tentará fallback para writable/session.';
        }

        if (ENVIRONMENT === 'production' && $matchIp && $proxyIps !== []) {
            $severity = 'warning';
            $details = 'session.matchIP está ativo em produção atrás de proxies confiáveis. Isso pode invalidar sessões legítimas quando o IP variar entre hops, CGNAT ou redes móveis.';
        } elseif (ENVIRONMENT === 'production' && $matchIp) {
            $severity = 'warning';
            $details = 'session.matchIP está ativo em produção. Confirme que a topologia real não troca IP de clientes durante a sessão.';
        }

        return [
            'key' => 'session_policy',
            'label' => 'Session policy',
            'severity' => $severity,
            'value' => sprintf('savePath=%s matchIP=%s regenerateDestroy=%s', $savePath !== '' ? $savePath : '[vazio]', $matchIp ? 'true' : 'false', (bool) ($session->regenerateDestroy ?? true) ? 'true' : 'false'),
            'details' => $details,
        ];
    }

    private function backupRuntimeCheck(): array
    {
        $policy = config(ProcessSafety::class);
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        $required = ['exec', 'shell_exec', 'proc_open', 'popen'];
        $blocked = array_values(array_intersect($required, $disabled));

        $severity = 'ok';
        $details = 'Execução de shell isolada em CLI por padrão; runtime web permanece bloqueado para backup e automações perigosas.';

        if ($policy->allowCliShellExecution && $blocked !== []) {
            $severity = 'warning';
            $details = 'Funções desabilitadas podem afetar backup e comandos CLI: ' . implode(', ', $blocked);
        }

        if (ENVIRONMENT === 'production' && $policy->allowWebShellExecution) {
            $severity = 'blocker';
            $details = 'PROCESS_ALLOW_WEB_SHELL está habilitado em produção; isso expõe superfície operacional sensível no runtime web.';
        } elseif ($policy->allowWebDatabaseBackup) {
            $severity = $severity === 'blocker' ? 'blocker' : 'warning';
            $details = 'BACKUP_ALLOW_WEB_RUNTIME está habilitado; prefira manter backups apenas via fila/worker CLI.';
        } elseif ($policy->allowWebInstallerAutomation) {
            $severity = $severity === 'blocker' ? 'blocker' : 'warning';
            $details = 'INSTALLER_ALLOW_WEB_AUTOMATION está habilitado; prefira automações do instalador apenas via CLI segura.';
        }

        return [
            'key' => 'backup_runtime',
            'label' => 'Runtime de backup / CLI',
            'severity' => $severity,
            'value' => sprintf(
                'cli_shell=%s web_shell=%s blocked=%s',
                $policy->allowCliShellExecution ? 'true' : 'false',
                $policy->allowWebShellExecution ? 'true' : 'false',
                $blocked === [] ? 'nenhum' : implode(',', $blocked)
            ),
            'details' => $details,
        ];
    }


    private function settingsSnapshotCheck(): array
    {
        $dir = rtrim(WRITEPATH, '/\\') . DIRECTORY_SEPARATOR . 'settings-snapshots';
        $exists = is_dir($dir);
        $writable = $exists && is_writable($dir);

        $severity = 'ok';
        $details = 'Snapshots preventivos de configuração podem ser gravados quando uma ação destrutiva for executada.';

        if ($exists && ! $writable) {
            $severity = 'warning';
            $details = 'Diretório de snapshots existe, mas não está gravável.';
        } elseif (! $exists) {
            $severity = 'ok';
            $details = 'Diretório de snapshots ainda não foi criado; será provisionado sob demanda no primeiro reset/import destrutivo.';
        }

        return [
            'key' => 'settings_snapshots',
            'label' => 'Snapshots preventivos de configuração',
            'severity' => $severity,
            'value' => $exists ? ($writable ? 'ready' : 'read-only') : 'on-demand',
            'details' => $details,
        ];
    }

    private function overallStatus(array $checks): string
    {
        foreach ($checks as $check) {
            if (($check['severity'] ?? 'ok') === 'blocker') {
                return 'blocker';
            }
        }

        foreach ($checks as $check) {
            if (($check['severity'] ?? 'ok') === 'warning') {
                return 'warning';
            }
        }

        return 'ok';
    }
}
