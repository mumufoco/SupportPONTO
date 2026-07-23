<?php

namespace App\Policies;

use App\Enums\Role;

/**
 * MELHORIA 9: Policy Object para Justificativas.
 *
 * Centraliza as regras de autorização de justificativas em um único lugar.
 * Elimina condicionais de autorização espalhadas em controllers, services e views.
 *
 * Uso no controller:
 *   $policy = new JustificationPolicy();
 *   if (!$policy->approve($this->currentUser, $justification)) {
 *       throw new ForbiddenException('aprovar esta justificativa');
 *   }
 *
 * Uso na view:
 *   <?php $policy = new \App\Policies\JustificationPolicy(); ?>
 *   <?php if ($policy->view($currentUser, $justification)): ?>
 */
final class JustificationPolicy
{
    /**
     * Pode visualizar a justificativa?
     * O próprio colaborador, seu gestor, RH e admin podem ver.
     */
    public function view(object $actor, object $justification): bool
    {
        if ($this->isAdminOrRH($actor)) {
            return true;
        }

        // Próprio colaborador
        if ((int) $actor->id === (int) $justification->employee_id) {
            return true;
        }

        // Gestor do mesmo departamento
        return $this->isSameDepartmentManager($actor, $justification);
    }

    /**
     * Pode aprovar a justificativa?
     * Gestor (mesmo departamento), RH e admin podem aprovar.
     * O próprio colaborador NÃO pode aprovar a própria justificativa.
     */
    public function approve(object $actor, object $justification): bool
    {
        if ((int) $actor->id === (int) $justification->employee_id) {
            return false; // Não pode aprovar a própria justificativa
        }

        if ($this->isAdminOrRH($actor)) {
            return true;
        }

        return $this->isSameDepartmentManager($actor, $justification);
    }

    /**
     * Pode rejeitar a justificativa?
     * Mesmas regras de aprovação.
     */
    public function reject(object $actor, object $justification): bool
    {
        return $this->approve($actor, $justification);
    }

    /**
     * Pode criar uma justificativa?
     * Qualquer colaborador pode criar para si mesmo.
     */
    public function create(object $actor): bool
    {
        return !empty($actor->id);
    }

    /**
     * Pode deletar a justificativa?
     * Apenas admin e RH (e o próprio, se ainda pendente).
     */
    public function delete(object $actor, object $justification): bool
    {
        if ($this->isAdminOrRH($actor)) {
            return true;
        }

        $isPending = in_array($justification->status ?? '', ['pendente', 'pending', ''], true);
        return $isPending && (int) $actor->id === (int) $justification->employee_id;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function isAdminOrRH(object $actor): bool
    {
        $role = $actor->role ?? '';
        return $role === Role::Admin->value || $role === Role::RH->value;
    }

    private function isSameDepartmentManager(object $actor, object $justification): bool
    {
        if (($actor->role ?? '') !== Role::Gestor->value) {
            return false;
        }

        // Verifica se o gestor é do mesmo departamento que o colaborador
        return !empty($actor->department)
            && !empty($justification->employee_department)
            && $actor->department === $justification->employee_department;
    }
}
