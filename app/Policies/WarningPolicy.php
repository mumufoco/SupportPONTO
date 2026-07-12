<?php

namespace App\Policies;

use App\Enums\Role;

/**
 * MELHORIA 9: Policy Object para Advertências.
 *
 * Centraliza as regras de quem pode emitir, ver, assinar e contestar
 * advertências trabalhistas.
 */
final class WarningPolicy
{
    /**
     * Pode criar/emitir uma advertência?
     * Apenas gestores (do mesmo departamento), RH e admin.
     */
    public function create(object $actor, ?string $targetDepartment = null): bool
    {
        if ($this->isAdminOrRH($actor)) {
            return true;
        }

        if (($actor->role ?? '') === Role::Gestor->value) {
            // Gestor só pode emitir advertência para seu departamento
            if ($targetDepartment === null) {
                return true; // Sem restrição de departamento especificada
            }
            return !empty($actor->department) && $actor->department === $targetDepartment;
        }

        return false;
    }

    /**
     * Pode visualizar a advertência?
     */
    public function view(object $actor, object $warning): bool
    {
        if ($this->isAdminOrRH($actor)) {
            return true;
        }

        // O próprio funcionário pode ver suas advertências
        if ((int) $actor->id === (int) $warning->employee_id) {
            return true;
        }

        // Gestor do mesmo departamento
        return $this->isSameDepartmentManager($actor, $warning);
    }

    /**
     * Pode assinar (confirmar ciência) da advertência?
     * Apenas o próprio funcionário.
     */
    public function sign(object $actor, object $warning): bool
    {
        return (int) $actor->id === (int) $warning->employee_id
            && empty($warning->signed_at);
    }

    /**
     * Pode contestar a advertência?
     * Apenas o próprio funcionário, enquanto no prazo.
     */
    public function contest(object $actor, object $warning): bool
    {
        if ((int) $actor->id !== (int) $warning->employee_id) {
            return false;
        }

        // Prazo de contestação: 10 dias corridos após a emissão
        $issuedAt = strtotime((string) ($warning->issued_at ?? ''));
        $deadline = strtotime('+10 days', $issuedAt);

        return time() <= $deadline;
    }

    /**
     * Pode cancelar/excluir a advertência?
     * Apenas admin e RH.
     */
    public function cancel(object $actor): bool
    {
        return $this->isAdminOrRH($actor);
    }

    /**
     * Pode exportar a advertência como PDF?
     */
    public function export(object $actor, object $warning): bool
    {
        return $this->view($actor, $warning);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function isAdminOrRH(object $actor): bool
    {
        $role = $actor->role ?? '';
        return $role === Role::Admin->value || $role === Role::RH->value;
    }

    private function isSameDepartmentManager(object $actor, object $warning): bool
    {
        if (($actor->role ?? '') !== Role::Gestor->value) {
            return false;
        }

        return !empty($actor->department)
            && !empty($warning->employee_department)
            && $actor->department === $warning->employee_department;
    }
}
