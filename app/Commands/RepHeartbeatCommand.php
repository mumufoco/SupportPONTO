<?php

namespace App\Commands;

use App\Services\REP\RepAvailabilityMonitorService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Heartbeat do REP-P — agendado via cron a cada minuto (ver crontab do usuário `www`,
 * mesma convenção de `timesheet:consolidate`/`lgpd:retention`/`backup:database`).
 *
 * Cada execução registra um "sinal de vida" e, comparando com o sinal anterior,
 * detecta lacunas que indicam indisponibilidade do serviço — gerando os registros
 * imutáveis tipo "6" (códigos "07"/"08") exigidos pela Portaria MTE 671/2021 quando
 * uma lacuna significativa é encontrada.
 *
 * Ver App\Services\REP\RepAvailabilityMonitorService para o racional completo de
 * por que esta é a única estratégia viável de registrar a própria indisponibilidade
 * do sistema (não é possível logar algo enquanto o sistema está fora do ar).
 */
class RepHeartbeatCommand extends BaseCommand
{
    protected $group       = 'REP';
    protected $name        = 'rep:heartbeat';
    protected $description = 'Registra o sinal de vida do REP-P e detecta janelas de indisponibilidade (registro tipo "6" do AFD).';

    public function run(array $params)
    {
        $service = new RepAvailabilityMonitorService();
        $result  = $service->heartbeat();

        if ($result['gap_detected']) {
            CLI::write(
                sprintf(
                    'Lacuna de indisponibilidade detectada: %ds (entre %s e %s). Registros tipo "6" (08/07) gravados.',
                    $result['gap_seconds'],
                    $result['last_seen_at'],
                    $result['now']
                ),
                'yellow'
            );
        } else {
            CLI::write('Heartbeat OK em ' . $result['now'] . ' (sem lacunas).', 'green');
        }
    }
}
