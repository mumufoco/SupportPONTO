<?php

namespace App\Database\Migrations;

use App\Services\Security\EncryptionService;
use CodeIgniter\Database\Migration;

/**
 * Criptografa o CPF dos funcionários em repouso (MED-11 na auditoria).
 *
 * O sistema já investe em criptografia versionada para dados biométricos, mas o CPF —
 * dado pessoal sensível sob a LGPD, e exportado em claro nos relatórios PDF/Excel/CSV —
 * ficava protegido só por controle de acesso ao banco. Qualquer vazamento de banco
 * (backup mal protegido, dump exposto) expunha todos os CPFs sem esforço adicional.
 *
 * Estratégia (compatível com a busca exata por CPF já usada em produção, ex.:
 * PunchService::findEmployeeByCpf() para ponto por CPF em terminais):
 *   - `cpf_hash` (novo, CHAR(64), único): SHA-256 do CPF normalizado (só dígitos) —
 *     permanece pesquisável diretamente em SQL, sem revelar o CPF em si.
 *   - `cpf` (existente, alargado para VARCHAR(255)): passa a guardar o valor
 *     criptografado (EncryptionService, XChaCha20-Poly1305, nonce aleatório por
 *     registro) em vez do CPF em claro. EmployeeModel decripta automaticamente em
 *     afterFind(), então todo o resto do sistema (relatórios, AFD, telas) continua
 *     lendo `$employee->cpf` normalmente, sem mudança de código.
 *
 * A constraint/índice único original em `cpf` é removida (dinamicamente, sem assumir
 * um nome fixo) porque criptografia com nonce aleatório nunca produz o mesmo texto
 * cifrado duas vezes — mesmo o mesmo CPF, criptografado de novo, resultaria em um
 * valor diferente. A unicidade de verdade passa a ser garantida por `cpf_hash`.
 */
class EncryptEmployeeCpf extends Migration
{
    public function up()
    {
        if (!$this->db->fieldExists('cpf_hash', 'employees')) {
            $this->forge->addColumn('employees', [
                'cpf_hash' => ['type' => 'CHAR', 'constraint' => 64, 'null' => true, 'after' => 'cpf'],
            ]);
        }

        $this->db->query('ALTER TABLE employees ALTER COLUMN cpf TYPE VARCHAR(255)');

        $this->backfillEncryptedCpf();

        $this->dropUniqueOnColumn('employees', 'cpf');

        $this->db->query('CREATE UNIQUE INDEX IF NOT EXISTS employees_cpf_hash_unique ON employees(cpf_hash) WHERE cpf_hash IS NOT NULL');
    }

    public function down()
    {
        // Reversão best-effort: decripta de volta para texto puro. Não recria a
        // constraint UNIQUE original em `cpf` automaticamente — nesse ponto o
        // operador já está revertendo uma migração de segurança deliberadamente e
        // deve reavaliar manualmente se isso é mesmo desejado.
        $this->decryptCpfBack();

        $this->db->query('DROP INDEX IF EXISTS employees_cpf_hash_unique');

        if ($this->db->fieldExists('cpf_hash', 'employees')) {
            $this->forge->dropColumn('employees', 'cpf_hash');
        }
    }

    private function backfillEncryptedCpf(): void
    {
        $encryption = new EncryptionService();

        $rows = $this->db->table('employees')
            ->select('id, cpf')
            ->where('cpf IS NOT NULL', null, false)
            ->where('cpf !=', '')
            ->where('cpf_hash IS NULL', null, false)
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            $plainCpf = (string) $row['cpf'];
            $digits = preg_replace('/\D/', '', $plainCpf) ?: '';
            if ($digits === '') {
                continue;
            }

            $hash = hash('sha256', $digits);
            $encrypted = $encryption->encrypt($plainCpf);

            $this->db->table('employees')
                ->where('id', $row['id'])
                ->update(['cpf' => $encrypted, 'cpf_hash' => $hash]);
        }
    }

    private function decryptCpfBack(): void
    {
        $encryption = new EncryptionService();

        $rows = $this->db->table('employees')
            ->select('id, cpf')
            ->where('cpf_hash IS NOT NULL', null, false)
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            try {
                $plainCpf = $encryption->decrypt((string) $row['cpf']);
            } catch (\Throwable $e) {
                continue;
            }

            $this->db->table('employees')->where('id', $row['id'])->update(['cpf' => $plainCpf]);
        }
    }

    /** Remove qualquer constraint/índice UNIQUE existente na coluna informada, sem assumir o nome. */
    private function dropUniqueOnColumn(string $table, string $column): void
    {
        $constraints = $this->db->query(
            "SELECT con.conname
                FROM pg_constraint con
                JOIN pg_class rel ON rel.oid = con.conrelid
                WHERE rel.relname = ? AND con.contype = 'u'
                  AND con.conkey = ARRAY[(SELECT attnum FROM pg_attribute WHERE attrelid = rel.oid AND attname = ?)]",
            [$table, $column]
        )->getResultArray();

        foreach ($constraints as $c) {
            $this->db->query('ALTER TABLE ' . $table . ' DROP CONSTRAINT "' . $c['conname'] . '"');
        }

        $indexes = $this->db->query(
            "SELECT indexname FROM pg_indexes
                WHERE tablename = ? AND indexdef ILIKE '%UNIQUE%' AND indexdef ILIKE ?",
            [$table, '%(' . $column . ')%']
        )->getResultArray();

        foreach ($indexes as $i) {
            $this->db->query('DROP INDEX IF EXISTS "' . $i['indexname'] . '"');
        }
    }
}
