<?php

declare(strict_types=1);

namespace App\Services\Support;

use App\Services\Installer\InstallationDoctorService;
use App\Services\Monitoring\LogMonitorService;
use App\Services\Monitoring\SystemObservabilityService;

class SupportDiagnosticsService
{
    private SystemObservabilityService $observability;
    private InstallationDoctorService $installationDoctor;
    private LogMonitorService $logMonitor;

    public function __construct()
    {
        $this->observability = new SystemObservabilityService();
        $this->installationDoctor = new InstallationDoctorService();
        $this->logMonitor = new LogMonitorService();
    }

    public function build(bool $withConnections = true): array
    {
        $health = $this->observability->detailedHealth();
        $installation = $this->installationDoctor->inspect($withConnections);
        $logReport = $this->logMonitor->generateReport(7);
        $recentAlerts = $this->logMonitor->monitorLogs();
        $release = $this->releaseMetadata();
        $configAudit = (new ConfigurationAuditService())->build();

        return [
            'status' => $this->aggregateStatus($health, $installation, $recentAlerts),
            'generated_at' => date(DATE_ATOM),
            'release' => $release,
            'health' => $health,
            'installation' => $installation,
            'logs' => [
                'report' => $logReport,
                'active_alerts' => $recentAlerts,
            ],
            'configuration' => $configAudit,
            'support' => [
                'recommended_commands' => [
                    'php spark install:doctor --json',
                    'php spark biometric:doctor',
                    'php spark monitor:logs --report',
                    'php spark support:migrations-audit --json --save',
                    'php spark support:schema-audit --json --save',
                    'php spark support:config-audit --json --save',
                    'bash scripts/migrate-safe.sh --status-only',
                    'bash scripts/testing/smoke-install-first-use.sh',
                ],
            ],
        ];
    }

    private function aggregateStatus(array $health, array $installation, array $alerts): string
    {
        $healthStatus = (string) ($health['status'] ?? 'unhealthy');
        $installStatus = (string) ($installation['status'] ?? 'blocker');

        if (in_array($healthStatus, ['unhealthy'], true) || in_array($installStatus, ['blocker'], true)) {
            return 'critical';
        }

        if ($healthStatus === 'degraded' || $installStatus === 'warning' || ! empty($alerts)) {
            return 'warning';
        }

        return 'ok';
    }

    private function releaseMetadata(): array
    {
        if (function_exists('app_release_metadata')) {
            return app_release_metadata();
        }

        $path = ROOTPATH . 'release.json';
        if (is_file($path)) {
            $data = json_decode((string) file_get_contents($path), true);
            if (is_array($data)) {
                return $data;
            }
        }

        return [
            'version' => 'unknown',
            'release' => 'unknown',
            'package' => null,
        ];
    }
}
