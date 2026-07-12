<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * MELHORIA 6: Auditoria Imutável para Conformidade MTE 671/2021
 *
 * FIX MED-2 (v1.1.279): Usa current_user em vez de env('DB_USERNAME') para garantir
 * que o REVOKE é aplicado ao usuário correto mesmo em ambientes sem .env configurado.
 * Loga explicitamente se o REVOKE foi pulado por qualquer motivo.
 */
class MakeAuditLogsImmutable extends Migration
{
    private string $auditTable = 'audit_logs';

    public function up(): void
    {
        $db = \Config\Database::connect();

        if ($db->DBDriver !== 'Postgre') {
            return;
        }

        // ── 1. Adicionar coluna checksum encadeado ─────────────────────────────
        if (!in_array('row_checksum', $db->getFieldNames($this->auditTable), true)) {
            $this->forge->addColumn($this->auditTable, [
                'row_checksum' => [
                    'type'       => 'CHAR',
                    'constraint' => 64,
                    'null'       => true,
                    'comment'    => 'SHA-256 encadeado — cadeia de integridade',
                ],
            ]);
        }

        // ── 2. Trigger de imutabilidade ────────────────────────────────────────
        $db->query("
            CREATE OR REPLACE FUNCTION fn_audit_logs_immutable()
            RETURNS TRIGGER AS \$\$
            BEGIN
                IF current_setting('app.audit_maintenance_mode', true) = 'on' THEN
                    IF TG_OP = 'DELETE' THEN
                        RETURN OLD;
                    END IF;
                    RETURN NEW;
                END IF;

                RAISE EXCEPTION
                    'VIOLAÇÃO DE INTEGRIDADE: audit_logs é imutável. '
                    'Registro id=% não pode ser % conforme Portaria MTE 671/2021.',
                    OLD.id, TG_OP;
                RETURN NULL;
            END;
            \$\$ LANGUAGE plpgsql SECURITY DEFINER;
        ");

        $db->query("DROP TRIGGER IF EXISTS trg_audit_logs_immutable ON {$this->auditTable};");

        $db->query("
            CREATE TRIGGER trg_audit_logs_immutable
            BEFORE UPDATE OR DELETE ON {$this->auditTable}
            FOR EACH ROW EXECUTE FUNCTION fn_audit_logs_immutable();
        ");

        // ── 3. FIX MED-2: usar current_user em vez de env() ──────────────────
        // Usar o usuário da conexão ativa garante que revogamos as permissões
        // do usuário correto, independente de variáveis de ambiente.
        $currentUserRow = $db->query("SELECT current_user AS u")->getRow();
        $appUser        = $currentUserRow ? (string) $currentUserRow->u : '';

        if ($appUser === '') {
            log_message('warning', '[MakeAuditLogsImmutable] Não foi possível determinar current_user — REVOKE não aplicado. Aplique manualmente: REVOKE UPDATE, DELETE ON audit_logs FROM <app_user>;');
        } else {
            // Verificar que não é superusuário (superusuário ignora REVOKE)
            $isSuperRow = $db->query("SELECT rolsuper FROM pg_roles WHERE rolname = ?", [$appUser])->getRow();
            $isSuperuser = (bool) ($isSuperRow->rolsuper ?? false);

            if ($isSuperuser) {
                log_message('warning', "[MakeAuditLogsImmutable] O usuário '{$appUser}' é superusuário — REVOKE não tem efeito. Use um usuário de aplicação sem privilégios de superusuário para maior segurança.");
            } else {
                $db->query("REVOKE UPDATE, DELETE ON {$this->auditTable} FROM \"{$appUser}\"");
                log_message('info', "[MakeAuditLogsImmutable] REVOKE UPDATE, DELETE aplicado com sucesso ao usuário '{$appUser}'.");
            }
        }

        // ── 4. Índices de performance ──────────────────────────────────────────
        $db->query("CREATE INDEX IF NOT EXISTS idx_audit_logs_user_level ON {$this->auditTable} (user_id, level, created_at DESC)");
        $db->query("CREATE INDEX IF NOT EXISTS idx_audit_logs_action_table ON {$this->auditTable} (action, table_name, created_at DESC)");
        $db->query("CREATE INDEX IF NOT EXISTS idx_audit_logs_entity ON {$this->auditTable} (entity_type, entity_id, created_at DESC) WHERE entity_type IS NOT NULL");
    }

    public function down(): void
    {
        $db = \Config\Database::connect();

        if ($db->DBDriver !== 'Postgre') {
            return;
        }

        $db->query("DROP TRIGGER IF EXISTS trg_audit_logs_immutable ON {$this->auditTable}");
        $db->query("DROP FUNCTION IF EXISTS fn_audit_logs_immutable()");

        $currentUserRow = $db->query("SELECT current_user AS u")->getRow();
        $appUser        = $currentUserRow ? (string) $currentUserRow->u : '';

        if ($appUser !== '') {
            $db->query("GRANT UPDATE, DELETE ON {$this->auditTable} TO \"{$appUser}\"");
        }

        if (in_array('row_checksum', $db->getFieldNames($this->auditTable), true)) {
            $this->forge->dropColumn($this->auditTable, 'row_checksum');
        }

        $db->query("DROP INDEX IF EXISTS idx_audit_logs_user_level");
        $db->query("DROP INDEX IF EXISTS idx_audit_logs_action_table");
        $db->query("DROP INDEX IF EXISTS idx_audit_logs_entity");
    }
}
