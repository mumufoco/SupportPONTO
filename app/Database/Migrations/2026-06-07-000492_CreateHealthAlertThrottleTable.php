<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tabela de controle de "silêncio" (throttle) para alertas operacionais de saúde
 * do sistema (ver HealthCheckCommand / SystemHealthCheckService / OperationalAlertService).
 *
 * CONTEXTO: em 07/06/2026, durante uma auditoria manual, descobrimos um pico de
 * erros de "banco de dados inacessível" que só foi percebido porque alguém foi
 * checar os logs manualmente — não havia nenhum alerta automático. Para resolver
 * isso de forma definitiva, ligamos `SystemHealthCheckService::detailedHealth()`
 * (que já detecta DB/queue/storage/migrations/etc fora do normal) a um cron
 * (`health:check`, a cada 5 minutos) que dispara alertas reais (e-mail + notificação
 * in-app) via OperationalAlertService::raiseWithDelivery().
 *
 * PROBLEMA A EVITAR: rodando a cada 5 minutos, se o banco ficar fora do ar por
 * 2 horas, sem controle isso geraria 24 e-mails idênticos ("alert storm"). Esta
 * tabela resolve isso: para cada par (module, severity), guardamos o instante do
 * último alerta efetivamente ENVIADO (e-mail/notificação), e só permitimos um novo
 * envio depois que um intervalo mínimo (throttle, hoje 30 minutos — ver
 * HealthCheckCommand::ALERT_THROTTLE_MINUTES) tiver passado — mesmo que o problema
 * persista e o `raise()` (gravação local em NDJSON, sempre incondicional, para fins
 * de auditoria/telemetria) continue registrando cada execução.
 *
 * Mesmo espírito de "estado interno mutável de controle" que `rep_heartbeat`
 * (não é um registro AFD, não tem proteção de imutabilidade — é só um contador
 * de "quando foi a última vez que avisamos sobre isso").
 */
class CreateHealthAlertThrottleTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'module' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => false,
                'comment'    => 'Nome do módulo de saúde (ex.: "database", "queue", "storage") — mesma chave usada em SystemHealthCheckService::detailedHealth()[modules]',
            ],
            'severity' => [
                'type'       => 'VARCHAR',
                'constraint' => 16,
                'null'       => false,
                'comment'    => '"warning" ou "critical" — mesma severidade usada por OperationalAlertService::raise()',
            ],
            'last_alerted_at' => [
                'type'    => 'DATETIME',
                'null'    => false,
                'comment' => 'Instante do último alerta efetivamente ENVIADO (e-mail/notificação) para este (module, severity) — usado para decidir se um novo envio deve ocorrer agora',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['module', 'severity']);

        $this->forge->createTable('health_alert_throttle', true);
    }

    public function down()
    {
        $this->forge->dropTable('health_alert_throttle', true);
    }
}
