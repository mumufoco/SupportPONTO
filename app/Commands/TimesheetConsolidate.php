<?php

namespace App\Commands;

use App\Models\TimesheetConsolidatedModel;
use App\Services\Timesheet\TimesheetDailyConsolidationService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class TimesheetConsolidate extends BaseCommand
{
    protected $group = 'Timesheet';
    protected $name = 'timesheet:consolidate';
    protected $description = 'Recalcula a consolidação diária de ponto por colaborador e período.';
    protected $usage = 'timesheet:consolidate [--employee 1] [--date 2026-05-12] [--from 2026-05-01 --to 2026-05-31] [--pending]';
    protected $options = [
        '--employee' => 'ID do colaborador. Obrigatório salvo em --pending.',
        '--date' => 'Data única a consolidar no formato YYYY-MM-DD.',
        '--from' => 'Data inicial no formato YYYY-MM-DD.',
        '--to' => 'Data final no formato YYYY-MM-DD.',
        '--pending' => 'Processa linhas marcadas como needs_recalculation.',
        '--limit' => 'Limite de linhas pendentes a processar. Padrão: 100.',
    ];

    public function run(array $params)
    {
        $service = new TimesheetDailyConsolidationService();

        if (CLI::getOption('pending') !== null) {
            $limit = max(1, (int) (CLI::getOption('limit') ?? 100));
            $model = new TimesheetConsolidatedModel();
            $pending = $model->where('needs_recalculation', true)
                ->orderBy('date', 'ASC')
                ->findAll($limit);

            $processed = 0;
            foreach ($pending as $row) {
                $result = $service->consolidateDay((int) $row->employee_id, (string) $row->date);
                if ($result['success'] ?? false) {
                    $processed++;
                } else {
                    CLI::write('Falha: colaborador ' . $row->employee_id . ' em ' . $row->date . ' — ' . ($result['message'] ?? 'erro'), 'yellow');
                }
            }

            CLI::write('Dias pendentes processados: ' . $processed, 'green');
            return;
        }

        $employeeId = (int) (CLI::getOption('employee') ?? 0);
        if ($employeeId <= 0) {
            CLI::error('Informe --employee ou use --pending.');
            return EXIT_ERROR;
        }

        $date = CLI::getOption('date');
        $from = CLI::getOption('from');
        $to = CLI::getOption('to');

        if (is_string($date) && $date !== '') {
            $result = $service->consolidateDay($employeeId, $date);
            CLI::write(($result['message'] ?? 'Processado') . ' [' . $date . ']', ($result['success'] ?? false) ? 'green' : 'yellow');
            return ($result['success'] ?? false) ? null : EXIT_ERROR;
        }

        if (! is_string($from) || $from === '') {
            $from = date('Y-m-01');
        }
        if (! is_string($to) || $to === '') {
            $to = date('Y-m-d');
        }

        $processed = 0;
        $current = $from;
        while ($current <= $to) {
            $result = $service->consolidateDay($employeeId, $current);
            if ($result['success'] ?? false) {
                $processed++;
            } else {
                CLI::write('Falha em ' . $current . ': ' . ($result['message'] ?? 'erro'), 'yellow');
            }
            $current = date('Y-m-d', strtotime($current . ' +1 day'));
        }

        CLI::write('Dias consolidados: ' . $processed, 'green');
    }
}
