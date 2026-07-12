<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AllowControlledAuditMaintenance extends Migration
{
    private string $auditTable = 'audit_logs';

    public function up(): void
    {
        $db = \Config\Database::connect();

        if ($db->DBDriver !== 'Postgre') {
            return;
        }

        $db->query("
            CREATE OR REPLACE FUNCTION fn_audit_logs_immutable()
            RETURNS TRIGGER AS \\$\$
            BEGIN
                IF current_setting('app.audit_maintenance_mode', true) = 'on' THEN
                    IF TG_OP = 'DELETE' THEN
                        RETURN OLD;
                    END IF;

                    RETURN NEW;
                END IF;

                RAISE EXCEPTION
                    'VIOLAÇÃO DE INTEGRIDADE: audit_logs é imutável. Registro id=% não pode ser % conforme Portaria MTE 671/2021.',
                    OLD.id, TG_OP;
                RETURN NULL;
            END;
            \\$\$ LANGUAGE plpgsql SECURITY DEFINER;
        ");

        $appUser = env('DB_USERNAME', 'supportponto');
        $userExists = $db->query("SELECT 1 FROM pg_roles WHERE rolname = ?", [$appUser])->getRow();

        if ($userExists) {
            $db->query("GRANT UPDATE, DELETE ON {$this->auditTable} TO \"{$appUser}\"");
        }
    }

    public function down(): void
    {
        $db = \Config\Database::connect();

        if ($db->DBDriver !== 'Postgre') {
            return;
        }

        $db->query("
            CREATE OR REPLACE FUNCTION fn_audit_logs_immutable()
            RETURNS TRIGGER AS \\$\$
            BEGIN
                RAISE EXCEPTION
                    'VIOLAÇÃO DE INTEGRIDADE: audit_logs é imutável. Registro id=% não pode ser % conforme Portaria MTE 671/2021.',
                    OLD.id, TG_OP;
                RETURN NULL;
            END;
            \\$\$ LANGUAGE plpgsql SECURITY DEFINER;
        ");

        $appUser = env('DB_USERNAME', 'supportponto');
        $userExists = $db->query("SELECT 1 FROM pg_roles WHERE rolname = ?", [$appUser])->getRow();

        if ($userExists) {
            $db->query("REVOKE UPDATE, DELETE ON {$this->auditTable} FROM \"{$appUser}\"");
        }
    }
}
