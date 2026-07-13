<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adiciona a coluna `reason` a facial_fraud_alerts: até aqui, a tabela só
 * cobria o caso de "foto não bate com o cadastro" (mismatch). Agora também
 * gera alerta quando a foto não chega por falha técnica (sem câmera,
 * permissão negada) ou quando o serviço de verificação facial está
 * indisponível — casos que passaram a NÃO bloquear mais o registro de
 * ponto (antes travavam 100% dos registros quando a captura falhava). A
 * coluna deixa claro, na revisão de gestor/RH, qual foi o motivo do alerta.
 */
class AddReasonToFacialFraudAlerts extends Migration
{
    public function up()
    {
        $this->forge->addColumn('facial_fraud_alerts', [
            'reason' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'null' => false,
                'default' => 'mismatch',
                'comment' => 'mismatch|no_photo|service_error',
                'after' => 'method',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('facial_fraud_alerts', 'reason');
    }
}
