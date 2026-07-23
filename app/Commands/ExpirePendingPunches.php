<?php

namespace App\Commands;

use App\Models\PendingPunchModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Expira registros de ponto pendentes de aprovação vencidos há mais de N horas.
 *
 * MED-03 (auditoria): antes, PendingPunchModel::expireStale() só era chamado de forma
 * "preguiçosa" — quando o próprio colaborador submetia uma nova justificativa
 * (PendingPunchService::evaluateEligibility) ou quando um gestor abria o painel de
 * pendências (listPendingForManager). Sem cron dedicado, se nenhuma dessas duas rotas
 * fosse acionada, registros vencidos ficavam presos com status='pending'
 * indefinidamente — contando contra a cota mensal do colaborador mesmo já expirados,
 * apesar do prazo de 24h comunicado a ele na submissão.
 *
 * Uso:
 *   php spark punches:expire-stale
 *   php spark punches:expire-stale --hours 24
 *
 * Cron recomendado (ver docs/operations/JOBS_FILAS_PROCESSAMENTO_PESADO.md):
 *   a cada 15 minutos, executar: php spark punches:expire-stale
 */
class ExpirePendingPunches extends BaseCommand
{
    protected $group       = 'Operations';
    protected $name        = 'punches:expire-stale';
    protected $description = 'Expira registros de ponto pendentes de aprovação vencidos há mais de N horas.';
    protected $usage       = 'punches:expire-stale [--hours 24]';
    protected $options     = [
        '--hours' => 'Prazo em horas após o qual uma pendência ainda em status "pending" é considerada vencida (padrão: 24).',
    ];

    public function run(array $params): void
    {
        $hours = (int) (CLI::getOption('hours') ?? 24);
        if ($hours <= 0) {
            $hours = 24;
        }

        $expired = (new PendingPunchModel())->expireStale($hours);

        CLI::write('[punches:expire-stale] ' . date('Y-m-d H:i:s'), 'cyan');
        CLI::write("Pendências expiradas (>{$hours}h sem revisão): {$expired}", $expired > 0 ? 'yellow' : 'green');
    }
}
