<?php

namespace App\Commands;

use App\Services\Health\SystemHealthCheckService;
use App\Services\Observability\OperationalAlertService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Health-check periódico com alerta automático — agendado via cron a cada 5
 * minutos (ver docs/operations/CRON_JOBS_REGISTRY.md, mesma convenção de
 * `rep:heartbeat`/`timesheet:consolidate`/`lgpd:retention`/`backup:database`).
 *
 * CONTEXTO (07/06/2026): durante uma auditoria manual, encontramos um pico de
 * erros de "banco de dados inacessível" que só foi percebido porque alguém foi
 * checar os logs manualmente — não havia nenhum alerta automático ligando os
 * sinais que `SystemHealthCheckService` já é capaz de detectar (banco, fila de
 * jobs, storage, migrations, logs, etc) a uma notificação real para os
 * administradores.
 *
 * Este comando fecha esse ciclo: roda `SystemHealthCheckService::detailedHealth()`
 * (a mesma verificação usada pelo painel `Admin\HealthController`), e para cada
 * módulo fora do estado "ok" dispara `OperationalAlertService::raiseWithDelivery()`
 * — que grava localmente (auditoria, sempre) E tenta entregar de verdade
 * (e-mail aos admins + notificação in-app), respeitando um intervalo mínimo de
 * "silêncio" entre envios repetidos do mesmo módulo (ver HealthAlertThrottleModel)
 * para não gerar uma tempestade de e-mails idênticos enquanto o problema persiste.
 *
 * Nunca lança exceção nem interrompe o cron por falha de entrega — ver o
 * tratamento defensivo em OperationalAlertService::raiseWithDelivery().
 */
class HealthCheckCommand extends BaseCommand
{
    protected $group       = 'Observability';
    protected $name        = 'health:check';
    protected $description = 'Executa o health-check completo do sistema e dispara alertas operacionais (e-mail + notificação in-app) quando algum módulo sai do estado normal.';
    protected $usage       = 'health:check';

    /**
     * Intervalo mínimo (em minutos) entre alertas repetidos do mesmo
     * (módulo, severidade) — ver HealthAlertThrottleModel::shouldAlert().
     */
    private const ALERT_THROTTLE_MINUTES = 30;

    public function run(array $params)
    {
        $service = new SystemHealthCheckService();
        $alerter = new OperationalAlertService();

        $result = $service->detailedHealth();
        $status = (string) ($result['status'] ?? 'unknown');
        $alerts = $result['alerts'] ?? [];

        if (empty($alerts)) {
            CLI::write('Health-check OK — todos os módulos normais (status geral: ' . $status . ').', 'green');

            return;
        }

        CLI::write(
            sprintf('Health-check detectou %d módulo(s) fora do normal (status geral: %s):', count($alerts), $status),
            $status === 'unhealthy' ? 'red' : 'yellow'
        );

        foreach ($alerts as $alert) {
            $module   = (string) ($alert['module'] ?? 'unknown');
            $severity = (string) ($alert['severity'] ?? 'warning');
            $message  = (string) ($alert['message'] ?? ('Anomalia em ' . $module));

            $delivered = $alerter->raiseWithDelivery(
                $severity,
                $module,
                $message,
                [
                    'source'       => 'health:check',
                    'overall_status' => $status,
                    'detected_at'  => gmdate(DATE_ATOM),
                ],
                self::ALERT_THROTTLE_MINUTES
            );

            $line  = sprintf('  - [%s] %s: %s', strtoupper($severity), $module, $message);
            $line .= $delivered
                ? ' → alerta ENVIADO (e-mail/notificação aos admins).'
                : ' → registrado localmente (envio silenciado pelo throttle ou já notificado recentemente).';

            CLI::write($line, $severity === 'critical' || $severity === 'emergency' ? 'red' : 'yellow');
        }
    }
}
