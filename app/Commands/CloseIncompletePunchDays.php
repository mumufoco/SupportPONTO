<?php

namespace App\Commands;

use App\Enums\PunchType;
use App\Models\EmployeeModel;
use App\Models\PendingPunchModel;
use App\Models\TimePunchModel;
use App\Services\NotificationService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Fecha automaticamente dias com sequência de ponto incompleta na virada do dia.
 *
 * Achado na simulação de fluxo completo: TimePunchModel::getNextPunchType() não
 * escopava a checagem de sequência por dia (corrigido separadamente) — na
 * prática, um colaborador que batesse "início do intervalo" e não completasse
 * "fim do intervalo" ficava bloqueado no dia seguinte, sendo cobrado por essa
 * marcação pendente do dia anterior antes de conseguir registrar qualquer
 * ponto novo.
 *
 * Este comando, executado uma vez por dia (recomendado logo após a virada,
 * ex.: 00:10), detecta esses pares incompletos do dia anterior e cria uma
 * pendência (pending_punches, status='awaiting_employee') para que o
 * colaborador explique o ocorrido — a mesma fila e tela que o gestor já usa
 * para aprovar/rejeitar justificativas de falha técnica (PendingPunchController).
 *
 * Uso:
 *   php spark punches:close-incomplete-days
 *   php spark punches:close-incomplete-days --dry-run
 *
 * Cron recomendado (ver docs/operations/JOBS_FILAS_PROCESSAMENTO_PESADO.md):
 *   10 0 * * * cd /caminho/do/projeto && php spark punches:close-incomplete-days
 */
class CloseIncompletePunchDays extends BaseCommand
{
    protected $group       = 'Operations';
    protected $name        = 'punches:close-incomplete-days';
    protected $description = 'Cria pendência de justificativa para colaboradores com par de marcação de ponto incompleto no dia anterior.';
    protected $usage       = 'punches:close-incomplete-days [--dry-run]';
    protected $options     = [
        '--dry-run' => 'Exibe o que seria criado sem gravar nada.',
    ];

    public function run(array $params): void
    {
        $dryRun = CLI::getOption('dry-run') !== null;

        $employeeModel       = new EmployeeModel();
        $timePunchModel      = new TimePunchModel();
        $pendingModel        = new PendingPunchModel();
        $notificationService = new NotificationService();

        CLI::write('[punches:close-incomplete-days] ' . ($dryRun ? '[DRY-RUN] ' : '') . date('Y-m-d H:i:s'), 'cyan');

        $today   = date('Y-m-d');
        $created = 0;

        foreach ($employeeModel->getActive() as $employee) {
            $employeeId = (int) $employee->id;

            $lastPunch = $timePunchModel->getLastPunch($employeeId);
            if (!$lastPunch) {
                continue;
            }

            $lastPunchDate = date('Y-m-d', strtotime((string) $lastPunch->punch_time));
            if ($lastPunchDate >= $today) {
                continue; // última marcação é de hoje (ou o relógio do servidor está atrasado) — nada a fechar
            }

            $lastType = PunchType::tryFrom($timePunchModel->normalizePunchType((string) $lastPunch->punch_type));
            if (!$lastType) {
                continue;
            }

            $expectedType = $lastType->nextExpected();
            if ($expectedType === PunchType::Entrada) {
                continue; // dia anterior fechado corretamente (última marcação foi "saída")
            }

            if ($pendingModel->hasOpenPendingForDay($employeeId, $expectedType->value, $lastPunchDate)) {
                continue; // já existe pendência aberta para este colaborador/dia/tipo (execução anterior do cron)
            }

            $intendedTime = $lastPunchDate . ' 23:59:59';
            $placeholder  = sprintf(
                '[Gerado automaticamente pelo sistema] O colaborador registrou "%s" em %s mas não completou a marcação seguinte ("%s") até o fim do expediente.',
                mb_strtolower($lastType->label()),
                date('d/m/Y', strtotime($lastPunchDate)),
                mb_strtolower($expectedType->label())
            );

            CLI::write("  [pendencia] {$employee->name} - faltou \"{$expectedType->label()}\" em {$lastPunchDate}", 'yellow');

            if ($dryRun) {
                $created++;
                continue;
            }

            // Isolado por colaborador: sem isso, um insert() com falha (blip de
            // conexao, linha invalida) interrompia o foreach inteiro e os
            // colaboradores seguintes na lista simplesmente nao eram
            // processados naquela execucao -- sem nenhum aviso, ja que o cron
            // roda desatendido a noite (mesmo padrao de risco ja corrigido em
            // LGPDRetentionCleanup nesta sessao).
            try {
                $pendingModel->insert([
                    'employee_id'         => $employeeId,
                    'intended_punch_type' => $expectedType->value,
                    'intended_time'       => $intendedTime,
                    'justification_text'  => $placeholder,
                    'situation_type'      => 'missing_checkout',
                    'status'              => 'awaiting_employee',
                ]);
            } catch (\Throwable $e) {
                log_message('error', 'Falha ao criar pendencia de ponto para colaborador #{id}: {msg}', ['id' => $employeeId, 'msg' => $e->getMessage()]);
                continue;
            }

            try {
                $notificationService->notify(
                    $employeeId,
                    'Marcação de ponto incompleta',
                    sprintf(
                        'Você não completou a marcação "%s" em %s. Acesse "Bater ponto > Pendências" para justificar.',
                        mb_strtolower($expectedType->label()),
                        date('d/m/Y', strtotime($lastPunchDate))
                    ),
                    'pending_punch_awaiting_employee',
                    '/timesheet/punch/pendencias'
                );
            } catch (\Throwable $e) {
                log_message('error', 'Falha ao notificar colaborador #{id} sobre pendencia de ponto: {msg}', ['id' => $employeeId, 'msg' => $e->getMessage()]);
            }

            $created++;
        }

        CLI::write('');
        CLI::write("Pendencias criadas: {$created}" . ($dryRun ? ' (dry-run, nada foi gravado)' : ''), $created > 0 ? 'green' : 'white');
    }
}
