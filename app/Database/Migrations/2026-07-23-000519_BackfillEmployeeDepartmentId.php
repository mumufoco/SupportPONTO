<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Correção do bug "filtro de Departamento não carrega os registros
 * cadastrados": employees tem duas colunas de departamento coexistindo —
 * `department` (texto legado, nunca eliminado) e `department_id` (FK real
 * para `departments.id`). Vários pontos do sistema (relatórios, escopo de
 * gestor) ainda comparam pelo texto, que só reflete o nome no momento do
 * cadastro/última edição e não é atualizado quando o departamento é
 * renomeado em Configurações — por isso "some" silenciosamente.
 *
 * Este backfill casa o texto de `department` com `departments.name`
 * (case/trim-insensitive) e preenche `department_id` onde ainda estiver
 * nulo, para que os pontos migrados nesta mesma leva (ver
 * AuthorizationService, ReportViewService, WarningAccessService etc.) já
 * encontrem o dado correto. Linhas sem correspondência exata no catálogo
 * (texto livre, digitado fora do fluxo canônico) ficam de fora — precisam
 * de correção manual reabrindo o cadastro do colaborador.
 */
class BackfillEmployeeDepartmentId extends Migration
{
    public function up(): void
    {
        $db = \Config\Database::connect();

        if ($db->DBDriver !== 'Postgre') {
            return;
        }

        if (! $db->tableExists('employees') || ! $db->tableExists('departments')) {
            log_message('warning', 'Migration BackfillEmployeeDepartmentId ignorada: tabela employees ou departments ainda não existe.');
            return;
        }

        $matched = $db->query("
            UPDATE employees
            SET department_id = d.id
            FROM departments d
            WHERE employees.department_id IS NULL
              AND employees.department IS NOT NULL
              AND TRIM(employees.department) <> ''
              AND LOWER(TRIM(employees.department)) = LOWER(TRIM(d.name))
        ");

        $matchedCount = $db->affectedRows();

        $unmatchedCount = (int) ($db->query("
            SELECT COUNT(*) AS total
            FROM employees
            WHERE department_id IS NULL
              AND department IS NOT NULL
              AND TRIM(department) <> ''
        ")->getRow()->total ?? 0);

        log_message(
            'info',
            "BackfillEmployeeDepartmentId: {$matchedCount} colaborador(es) tiveram department_id preenchido a partir do texto. "
                . "{$unmatchedCount} colaborador(es) continuam com department_id nulo (texto sem correspondência exata em departments.name — corrigir manualmente reabrindo o cadastro)."
        );
    }

    public function down(): void
    {
        // Backfill de dados não é revertido — desfazer apagaria uma correção
        // legítima sem meio de saber quais valores foram setados por este
        // passo versus já corretos por outro caminho (formulário de edição).
    }
}
