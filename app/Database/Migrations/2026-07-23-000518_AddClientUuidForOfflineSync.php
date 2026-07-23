<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Suporte a registro de ponto offline (PWA): client_uuid é a chave de
 * idempotência da sincronização — o dispositivo gera um UUID ao capturar a
 * marcação localmente e reenvia sempre com o mesmo valor até confirmar
 * sucesso. Sem essa coluna, um retry de rede (timeout, conexão caindo no
 * meio da resposta) duplicaria a marcação ao reenviar.
 */
class AddClientUuidForOfflineSync extends Migration
{
    public function up(): void
    {
        $this->addClientUuidColumn('time_punches');
        $this->addClientUuidColumn('pending_punches');
    }

    public function down(): void
    {
        foreach (['time_punches', 'pending_punches'] as $table) {
            if (! $this->db->tableExists($table)) {
                continue;
            }

            $this->safeQuery("DROP INDEX IF EXISTS idx_{$table}_client_uuid_unique");

            if ($this->db->fieldExists('client_uuid', $table)) {
                $this->forge->dropColumn($table, 'client_uuid');
            }
        }
    }

    private function addClientUuidColumn(string $table): void
    {
        if (! $this->db->tableExists($table)) {
            log_message('warning', "Migration AddClientUuidForOfflineSync ignorada para {$table}: tabela ainda não existe.");
            return;
        }

        if (! $this->db->fieldExists('client_uuid', $table)) {
            $this->forge->addColumn($table, [
                'client_uuid' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                    'null'       => true,
                    'comment'    => 'UUID gerado pelo dispositivo ao capturar a marcação offline; chave de idempotência da sincronização.',
                ],
            ]);
        }

        $this->safeQuery("CREATE UNIQUE INDEX IF NOT EXISTS idx_{$table}_client_uuid_unique ON {$table}(client_uuid) WHERE client_uuid IS NOT NULL");
    }

    private function safeQuery(string $sql): void
    {
        try {
            $this->db->query($sql);
        } catch (\Throwable $e) {
            log_message('error', 'AddClientUuidForOfflineSync safeQuery falhou: ' . $e->getMessage());
        }
    }
}
