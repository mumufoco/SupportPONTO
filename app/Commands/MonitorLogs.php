<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Services\Monitoring\LogMonitorService;

/**
 * Monitor Logs Command
 *
 * Comando para monitorar logs e enviar alertas
 *
 * Uso:
 * php spark monitor:logs
 * php spark monitor:logs --clean
 * php spark monitor:logs --report
 */
class MonitorLogs extends BaseCommand
{
    protected $group       = 'Monitoring';
    protected $name        = 'monitor:logs';
    protected $description = 'Monitora logs do sistema e envia alertas para eventos críticos';

    protected $usage = 'monitor:logs [options]';
    protected $arguments = [];
    protected $options = [
        '--clean'  => 'Limpa logs antigos (>30 dias)',
        '--report' => 'Gera relatório dos últimos 7 dias',
    ];

    public function run(array $params)
    {
        CLI::write('===========================================', 'blue');
        CLI::write('  Sistema de Monitoramento de Logs', 'blue');
        CLI::write('===========================================', 'blue');
        CLI::newLine();

        $monitor = new LogMonitorService();

        // Limpar logs antigos
        if (CLI::getOption('clean')) {
            CLI::write('Limpando logs antigos...', 'yellow');
            $deleted = $monitor->cleanOldLogs(30);
            CLI::write("✓ {$deleted} arquivo(s) de log antigos removidos", 'green');
            CLI::newLine();
            return;
        }

        // Gerar relatório
        if (CLI::getOption('report')) {
            CLI::write('Gerando relatório de logs...', 'yellow');
            $report = $monitor->generateReport(7);

            CLI::newLine();
            CLI::write('Relatório dos últimos ' . $report['period'], 'cyan');
            CLI::write('─────────────────────────────────────────', 'cyan');
            CLI::write('Total de Erros:           ' . $report['total_errors'], 'white');
            CLI::write('Erros Críticos:           ' . $report['critical_errors'], ($report['critical_errors'] > 0 ? 'red' : 'white'));
            CLI::write('Avisos:                   ' . $report['warnings'], 'white');
            CLI::write('Falhas de Login:          ' . $report['login_failures'], ($report['login_failures'] > 10 ? 'yellow' : 'white'));
            CLI::write('Acessos Não Autorizados:  ' . $report['unauthorized_attempts'], ($report['unauthorized_attempts'] > 10 ? 'yellow' : 'white'));
            CLI::write('Erros de Banco de Dados:  ' . $report['database_errors'], ($report['database_errors'] > 0 ? 'red' : 'white'));
            CLI::newLine();
            return;
        }

        // Monitorar logs
        CLI::write('Analisando logs...', 'yellow');

        $alerts = $monitor->monitorLogs();

        if (empty($alerts)) {
            CLI::write('✓ Nenhum alerta detectado - Sistema operando normalmente', 'green');
        } else {
            CLI::write('⚠ ' . count($alerts) . ' alerta(s) detectado(s)!', 'red');
            CLI::newLine();

            foreach ($alerts as $alert) {
                $color = match ($alert['severity']) {
                    'critical' => 'red',
                    'high' => 'yellow',
                    default => 'white'
                };

                CLI::write('─────────────────────────────────────────', $color);
                CLI::write("[{$alert['severity']}] {$alert['message']}", $color);
                CLI::write("Ocorrências: {$alert['count']}", 'white');
                CLI::write("Período: {$alert['first_occurrence']} → {$alert['last_occurrence']}", 'white');
                CLI::write('Alertas enviados para: ' . env('ALERT_EMAILS', 'admin@supportsondagens.com.br'), 'white');
                CLI::newLine();
            }
        }

        CLI::newLine();
        CLI::write('Monitoramento concluído.', 'blue');
    }
}
