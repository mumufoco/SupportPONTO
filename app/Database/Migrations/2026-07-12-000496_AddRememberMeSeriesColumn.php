<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adiciona employees.remember_token_series — habilita detecção de reuso de token
 * "lembrar-me" (BAIXO-03 na auditoria).
 *
 * O esquema anterior guardava um único remember_token (hash) por funcionário,
 * rotacionado a cada uso. Isso mitiga replay simples, mas não permite distinguir "o
 * cookie está simplesmente inválido/expirado" de "alguém está reapresentando um token
 * antigo que já foi rotacionado" — o segundo caso é o indício clássico de cookie
 * roubado (RememberMeService::resolveUserFromCookie(), ver ambos os cenários davam o
 * mesmo resultado: acesso negado, sem alerta).
 *
 * O padrão série+validador (Barry Jaspan) resolve isso: remember_token_series
 * identifica o "dispositivo logado" e permanece estável entre rotações;
 * remember_token (o validador) muda a cada uso. Se um validador não bater para uma
 * série que EXISTE, é reuso de token antigo — sinal de roubo — e a série inteira é
 * revogada e auditada, em vez de tratado como um cookie comum inválido.
 */
class AddRememberMeSeriesColumn extends Migration
{
    public function up()
    {
        if (!$this->db->fieldExists('remember_token_series', 'employees')) {
            $this->forge->addColumn('employees', [
                'remember_token_series' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true, 'after' => 'remember_token'],
            ]);
        }

        $this->db->query('CREATE INDEX IF NOT EXISTS idx_employees_remember_token_series ON employees(remember_token_series)');
    }

    public function down()
    {
        $this->db->query('DROP INDEX IF EXISTS idx_employees_remember_token_series');

        if ($this->db->fieldExists('remember_token_series', 'employees')) {
            $this->forge->dropColumn('employees', 'remember_token_series');
        }
    }
}
