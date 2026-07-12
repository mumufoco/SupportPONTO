<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Pacote 431 — Alinha os campos usados pelos Models com o schema instalado.
 *
 * Esta migration é idempotente para ser segura em instalações novas e em bases
 * que passaram por pacotes anteriores parcialmente aplicados. Ela não remove
 * colunas legadas; apenas garante que os campos gravados pelos Models existem.
 */
class AlignModelsWithDatabaseSchema extends Migration
{
    public function up(): void
    {
        $this->alignEmployees();
        $this->alignBiometricTemplates();
        $this->alignTimePunches();
        $this->alignJustifications();
        $this->alignWarnings();
        $this->alignSettingsAliases();
        $this->alignUniqueCodeLength();
    }

    public function down(): void
    {
        // Intencionalmente não destrutivo. Este pacote corrige compatibilidade
        // schema/model e não deve remover dados trabalhistas, biométricos ou LGPD.
    }

    private function alignEmployees(): void
    {
        $this->addMissingColumns('employees', [
            'work_unit' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'phone' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'admission_date' => ['type' => 'DATE', 'null' => true],
            'photo_path' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'face_encoding' => ['type' => 'TEXT', 'null' => true],
            'remember_token' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'remember_token_expires' => ['type' => 'DATETIME', 'null' => true],

            // Dados pessoais e documentais exigidos pelo cadastro corporativo/MTE.
            'rg' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'rg_orgao_emissor' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'rg_data_expedicao' => ['type' => 'DATE', 'null' => true],
            'nacionalidade' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'logradouro' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'numero' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'complemento' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'bairro' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'municipio' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'uf' => ['type' => 'CHAR', 'constraint' => 2, 'null' => true],
            'cep' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'telefone' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'ctps_numero' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'ctps_serie' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'ctps_uf' => ['type' => 'CHAR', 'constraint' => 2, 'null' => true],
            'ctps_data_emissao' => ['type' => 'DATE', 'null' => true],
            'pis_pasep' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],

            // Dados profissionais e financeiros.
            'cargo' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'salario_base' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => true],
            'jornada_trabalho' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'setor' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'horario_entrada' => ['type' => 'TIME', 'null' => true],
            'horario_saida' => ['type' => 'TIME', 'null' => true],
            'dependentes' => ['type' => 'TEXT', 'null' => true],
            'banco' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'agencia' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'conta' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'demission_date' => ['type' => 'DATE', 'null' => true],
            'estado_civil' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'sexo' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'cor_raca' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'grau_instrucao' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'tipo_contrato' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'deficiencia' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
        ]);

        $this->safeQuery('CREATE UNIQUE INDEX IF NOT EXISTS idx_employees_pis_pasep_unique ON employees(pis_pasep) WHERE pis_pasep IS NOT NULL AND pis_pasep <> \'\'');
        $this->safeQuery('CREATE INDEX IF NOT EXISTS idx_employees_work_unit ON employees(work_unit)');
        $this->safeQuery('CREATE INDEX IF NOT EXISTS idx_employees_setor ON employees(setor)');
    }

    private function alignBiometricTemplates(): void
    {
        $this->addMissingColumns('biometric_templates', [
            'finger_position' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'template_format' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'sourceafis_proprietary'],
            'quality_score' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true],
            'capture_method' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'capture_device' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'algorithm_version' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'is_active' => ['type' => 'BOOLEAN', 'default' => true],
            'enrolled_at' => ['type' => 'TIMESTAMP', 'null' => true],
            'last_used_at' => ['type' => 'TIMESTAMP', 'null' => true],
            'usage_count' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'metadata' => ['type' => 'TEXT', 'null' => true],
        ]);
    }

    private function alignTimePunches(): void
    {
        $this->addMissingColumns('time_punches', [
            'location_lat' => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
            'location_lng' => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
            'location_accuracy' => ['type' => 'DECIMAL', 'constraint' => '8,2', 'null' => true],
            'geofence_name' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'face_similarity' => ['type' => 'DECIMAL', 'constraint' => '7,4', 'null' => true],
            'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'user_agent' => ['type' => 'TEXT', 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'approved', 'null' => false],
        ]);

        if ($this->db->tableExists('time_punches')) {
            $fields = $this->db->getFieldNames('time_punches');
            if (in_array('latitude', $fields, true) && in_array('location_lat', $fields, true)) {
                $this->safeQuery('UPDATE time_punches SET location_lat = COALESCE(location_lat, latitude) WHERE latitude IS NOT NULL');
            }
            if (in_array('longitude', $fields, true) && in_array('location_lng', $fields, true)) {
                $this->safeQuery('UPDATE time_punches SET location_lng = COALESCE(location_lng, longitude) WHERE longitude IS NOT NULL');
            }
        }

        $this->safeQuery('CREATE INDEX IF NOT EXISTS idx_time_punches_employee_location ON time_punches(employee_id, location_lat, location_lng)');
        $this->safeQuery('CREATE INDEX IF NOT EXISTS idx_time_punches_within_geofence ON time_punches(within_geofence)');
    }

    private function alignJustifications(): void
    {
        $this->addMissingColumns('justifications', [
            'date' => ['type' => 'DATE', 'null' => true],
            'type' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'attachment_path' => ['type' => 'TEXT', 'null' => true],
            'reviewed_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'reviewed_at' => ['type' => 'DATETIME', 'null' => true],
            'review_notes' => ['type' => 'TEXT', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        if ($this->db->tableExists('justifications')) {
            $fields = $this->db->getFieldNames('justifications');
            if (in_array('justification_date', $fields, true) && in_array('date', $fields, true)) {
                $this->safeQuery('UPDATE justifications SET date = COALESCE(date, justification_date) WHERE justification_date IS NOT NULL');
            }
            if (in_array('justification_type', $fields, true) && in_array('type', $fields, true)) {
                $this->safeQuery('UPDATE justifications SET type = COALESCE(type, justification_type) WHERE justification_type IS NOT NULL');
            }
            if (in_array('attachments', $fields, true) && in_array('attachment_path', $fields, true)) {
                $this->safeQuery('UPDATE justifications SET attachment_path = COALESCE(attachment_path, attachments::text) WHERE attachments IS NOT NULL');
            }
        }

        $this->safeQuery('CREATE INDEX IF NOT EXISTS idx_justifications_date_alias ON justifications(date)');
        $this->safeQuery('CREATE INDEX IF NOT EXISTS idx_justifications_type_alias ON justifications(type)');
    }

    private function alignWarnings(): void
    {
        $this->addMissingColumns('warnings', [
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
    }

    private function alignSettingsAliases(): void
    {
        $this->addMissingColumns('settings', [
            'setting_key' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'setting_value' => ['type' => 'TEXT', 'null' => true],
            'setting_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'setting_group' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
        ]);

        if ($this->db->tableExists('settings')) {
            $fields = $this->db->getFieldNames('settings');
            if (in_array('key', $fields, true) && in_array('setting_key', $fields, true)) {
                $this->safeQuery('UPDATE settings SET setting_key = COALESCE(setting_key, key) WHERE key IS NOT NULL');
            }
            if (in_array('value', $fields, true) && in_array('setting_value', $fields, true)) {
                $this->safeQuery('UPDATE settings SET setting_value = COALESCE(setting_value, value) WHERE value IS NOT NULL');
            }
            if (in_array('type', $fields, true) && in_array('setting_type', $fields, true)) {
                $this->safeQuery('UPDATE settings SET setting_type = COALESCE(setting_type, type) WHERE type IS NOT NULL');
            }
            if (in_array('group', $fields, true) && in_array('setting_group', $fields, true)) {
                $this->safeQuery('UPDATE settings SET setting_group = COALESCE(setting_group, "group") WHERE "group" IS NOT NULL');
            }
        }
    }

    private function alignUniqueCodeLength(): void
    {
        if (! $this->db->tableExists('employees')) {
            return;
        }

        $this->safeQuery('ALTER TABLE employees ALTER COLUMN unique_code TYPE VARCHAR(10)');
    }

    /** @param array<string,array<string,mixed>> $columns */
    private function addMissingColumns(string $table, array $columns): void
    {
        if (! $this->db->tableExists($table)) {
            return;
        }

        $existing = $this->db->getFieldNames($table);
        foreach ($columns as $name => $definition) {
            if (in_array($name, $existing, true)) {
                continue;
            }

            $this->forge->addColumn($table, [$name => $definition]);
            $existing[] = $name;
        }
    }

    private function safeQuery(string $sql): void
    {
        try {
            $this->db->query($sql);
        } catch (\Throwable $e) {
            log_message('warning', '[Package431] Schema alignment SQL skipped: ' . $e->getMessage());
        }
    }
}
