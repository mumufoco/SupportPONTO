<?php

namespace App\Commands;

use App\Libraries\SupportCheck\SupportCheckApiClient;
use App\Services\SupportCheck\SupportCheckAttendanceReportService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SupportCheckAttendanceReport extends BaseCommand
{
    protected $group       = 'SupportCheck';
    protected $name        = 'supportcheck:attendance-report';
    protected $description = 'Envia o relatorio mensal de ponto ao SupportCHECK. Por padrao envia o mes anterior.';
    protected $usage       = 'supportcheck:attendance-report [--year 2026] [--month 6] [--employee 42]';
    protected $options     = [
        '--year'     => 'Ano do relatorio (padrao: mes anterior).',
        '--month'    => 'Mes do relatorio 1-12 (padrao: mes anterior).',
        '--employee' => 'ID de um funcionario especifico. Se omitido, processa todos os ativos.',
    ];

    public function run(array $params): void
    {
        $client = new SupportCheckApiClient();

        if (! $client->isEnabled()) {
            CLI::write('SupportCHECK nao esta habilitado. Verifique SUPPORTCHECK_ENABLED, SUPPORTCHECK_BASE_URL e SUPPORTCHECK_API_TOKEN no .env.', 'yellow');
            return;
        }

        $service = new SupportCheckAttendanceReportService($client);

        // Resolve ano/mes alvo
        $yearOpt  = CLI::getOption('year');
        $monthOpt = CLI::getOption('month');

        if ($yearOpt !== null && $monthOpt !== null) {
            $year  = (int) $yearOpt;
            $month = (int) $monthOpt;
        } else {
            $ts    = strtotime('first day of last month');
            $year  = (int) date('Y', $ts);
            $month = (int) date('n', $ts);
        }

        $label = sprintf('%04d/%02d', $year, $month);
        $employeeId = CLI::getOption('employee') !== null ? (int) CLI::getOption('employee') : null;

        if ($employeeId !== null) {
            CLI::write("Enviando relatorio de {$label} para funcionario #{$employeeId}...", 'cyan');
            try {
                $result = $service->sendMonthForEmployee($employeeId, $year, $month);
                CLI::write('Relatorio enviado: ' . ($result['data']['period'] ?? $label), 'green');
            } catch (\Throwable $e) {
                CLI::write('Erro: ' . $e->getMessage(), 'red');
            }
        } else {
            CLI::write("Enviando relatorio de {$label} para todos os funcionarios ativos...", 'cyan');
            try {
                $result = $service->sendMonthForAll($year, $month);
                CLI::write('Enviados com sucesso: ' . $result['sent'], 'green');
                if ($result['failed'] > 0) {
                    CLI::write('Falhas: ' . $result['failed'], 'red');
                    foreach ($result['errors'] as $err) {
                        CLI::write('  - ' . $err, 'yellow');
                    }
                }
            } catch (\Throwable $e) {
                CLI::write('Erro: ' . $e->getMessage(), 'red');
            }
        }

        CLI::write('Concluido.', 'green');
    }
}
