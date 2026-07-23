<?php

namespace App\Services;

use App\Enums\Role;

/**
 * Centraliza normalização de papéis e decisões de autorização do backend.
 * MELHORIA 2: Usa o enum Role como fonte única da verdade.
 */
class AuthorizationService
{
    /**
     * @var array<string, list<string>>
     */
    private array $permissionMatrix = [
        Role::Admin->value => ['*'],
        Role::RH->value => [
            'employees.manage',
            'employees.approve',
            'reports.view',
            'reports.export',
            'warnings.manage',
            'justifications.approve',
            'biometric.manage',
        ],
        Role::Gestor->value => [
            'employees.manage.team',
            'reports.view',
            'reports.export',
            'warnings.manage.team',
            'justifications.approve.team',
            'biometric.manage.team',
            // FIX OBS-2 (v1.1.279): audit.view.limited permite gestor listar logs do seu departamento
            // audit.view.details permite ver o detalhe de um log específico
            'audit.view.limited',
            'audit.view.details',
        ],
        Role::Funcionario->value => [
            'profile.self',
            'timesheet.self',
            'warnings.view.self',
            'justifications.self',
            'biometric.self',
        ],
        Role::DPO->value => [
            'lgpd.view',
            'lgpd.manage',
            'audit.view',
            'audit.export',
            'reports.audit',
            'audit.view.details',
            'compliance.view',
        ],
        Role::Auditor->value => [
            'audit.view',
            'audit.export',
            'reports.audit',
            'audit.view.details',
            'compliance.view',
        ],
    ];


    /**
     * Retorna a matriz canônica de permissões para telas, filtros e testes.
     *
     * @return array<string, list<string>>
     */
    public function permissionMatrix(): array
    {
        return $this->permissionMatrix;
    }

    /**
     * @return list<string>
     */
    public function allowedRolesFor(string $permission): array
    {
        $allowed = [];

        foreach ($this->permissionMatrix as $role => $permissions) {
            if (in_array('*', $permissions, true) || in_array($permission, $permissions, true)) {
                $allowed[] = $role;
            }
        }

        return $allowed;
    }

    public function normalizeRole(?string $role): string
    {
        if (empty($role)) {
            return Role::Funcionario->value;
        }

        // MELHORIA 2: Delegar ao enum Role::normalize() — fonte única da verdade.
        try {
            return Role::normalize($role)->value;
        } catch (\ValueError) {
            // Role desconhecido — padrão seguro é funcionario (menor privilégio)
            return Role::Funcionario->value;
        }
    }

    /**
     * @param array|object|null $actor
     */
    public function getRole($actor): string
    {
        if (is_object($actor)) {
            return $this->normalizeRole($actor->role ?? null);
        }

        if (is_array($actor)) {
            return $this->normalizeRole($actor['role'] ?? null);
        }

        return 'funcionario';
    }

    /**
     * @param array|object|null $actor
     * @param string|list<string> $roles
     */
    public function hasRole($actor, string|array $roles): bool
    {
        $currentRole = $this->getRole($actor);
        $allowed = array_map(fn ($role) => $this->normalizeRole($role), (array) $roles);

        return in_array($currentRole, $allowed, true);
    }

    /**
     * @param array|object|null $actor
     */
    public function can($actor, string $permission): bool
    {
        $role = $this->getRole($actor);
        $permissions = $this->permissionMatrix[$role] ?? [];

        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }

    /**
     * @param array|object|null $actor
     * @param array|object|null $targetEmployee
     */
    public function canAccessEmployee($actor, $targetEmployee, bool $allowSelf = true): bool
    {
        if ($actor === null || $targetEmployee === null) {
            return false;
        }

        $actorId = $this->getValue($actor, 'id');
        $targetId = $this->getValue($targetEmployee, 'id');

        if ($allowSelf && $actorId !== null && $targetId !== null && (int) $actorId === (int) $targetId) {
            return true;
        }

        if ($this->hasRole($actor, ['admin', 'rh'])) {
            return true;
        }

        if ($this->hasRole($actor, 'gestor')) {
            return $this->belongsToSameDepartment($actor, $targetEmployee);
        }

        return false;
    }

    /**
     * @param array|object|null $actor
     * @param array|object|null $targetEmployee
     */
    public function canManageEmployee($actor, $targetEmployee): bool
    {
        return $this->canAccessEmployee($actor, $targetEmployee, false)
            && ($this->can($actor, 'employees.manage') || $this->can($actor, 'employees.manage.team'));
    }


    /**
     * @param array|object|null $actor
     * @param array|object|null $jobContext Contexto do job assíncrono.
     *                                      Aceita owner_id/employee_id, owner_department/department
     *                                      e, opcionalmente, owner/employee como entidade resolvida.
     */
    public function canAccessAsyncJob($actor, $jobContext): bool
    {
        if ($actor === null || $jobContext === null) {
            return false;
        }

        $actorId = $this->getValue($actor, 'id');
        $ownerId = $this->normalizeInt(
            $this->getValue($jobContext, 'owner_id')
                ?? $this->getValue($jobContext, 'employee_id')
                ?? $this->getValue($this->getValue($jobContext, 'owner') ?? $this->getValue($jobContext, 'employee'), 'id')
                ?? $this->getValue($jobContext, 'id')
        );

        if ($actorId !== null && $ownerId !== null && (int) $actorId === (int) $ownerId) {
            return true;
        }

        if ($this->hasRole($actor, ['admin', 'rh'])) {
            return true;
        }

        if (! $this->hasRole($actor, 'gestor')) {
            return false;
        }

        $actorDepartmentId = $this->normalizeInt($this->getValue($actor, 'department_id'));
        $ownerDepartmentId = $this->normalizeInt(
            $this->getValue($jobContext, 'owner_department_id')
                ?? $this->getValue($jobContext, 'department_id')
                ?? $this->getValue($this->getValue($jobContext, 'owner') ?? $this->getValue($jobContext, 'employee'), 'department_id')
        );

        // Preferimos a FK department_id (imune a renomeação/case/espaço no
        // cadastro de departamentos) quando disponível dos dois lados. Só cai
        // para o texto legado quando algum dos dois não tiver o id — não
        // regride nenhum caso que já funcionava antes desta migração.
        if ($actorDepartmentId !== null && $ownerDepartmentId !== null) {
            return $actorDepartmentId === $ownerDepartmentId;
        }

        $actorDepartment = $this->normalizeScalar($this->getValue($actor, 'department'));
        if ($actorDepartment === '') {
            return false;
        }

        $ownerDepartment = $this->normalizeScalar(
            $this->getValue($jobContext, 'owner_department')
                ?? $this->getValue($jobContext, 'department')
                ?? $this->getValue($this->getValue($jobContext, 'owner') ?? $this->getValue($jobContext, 'employee'), 'department')
        );

        return $ownerDepartment !== '' && strtolower($ownerDepartment) === strtolower($actorDepartment);
    }

    /**
     * @param array|object|null $actor
     * @param array|object|null $fallbackEntity
     */
    /**
     * Continua devolvendo o NOME do departamento (texto), não o id -- é
     * consumido pelo pipeline de geração de relatórios/AFD
     * (ReportRequestPolicyService::applyDepartmentRestriction() ->
     * ReportExecutionGeneratorsTrait::applyDepartmentFilter(), que ainda
     * filtra por employees.department texto). Mudar esse retorno para id
     * quebraria esse pipeline sem migrá-lo junto -- fora do escopo desta
     * correção (ver auditoria de "filtro de departamento"; belongsToSameDepartment()
     * e canAccessAsyncJob() acima já usam department_id, que é o que resolve
     * o bug relatado nos demais pontos).
     */
    public function resolveDepartmentRestriction($actor, $fallbackEntity = null): ?string
    {
        if ($this->hasRole($actor, ['admin', 'rh'])) {
            return null;
        }

        if (! $this->hasRole($actor, 'gestor')) {
            return null;
        }

        $actorDepartment = $this->normalizeScalar($this->getValue($actor, 'department'));
        if ($actorDepartment !== '') {
            return $actorDepartment;
        }

        $fallbackDepartment = $this->normalizeScalar($this->getValue($fallbackEntity, 'department'));

        return $fallbackDepartment !== '' ? $fallbackDepartment : null;
    }


    /**
     * @param array|object|null $actor
     */
    public function canAccessManagerArea($actor): bool
    {
        return $this->hasRole($actor, ['admin', 'gestor', 'rh']);
    }

    /**
     * @param array|object|null $actor
     */
    public function canAccessBiometricArea($actor): bool
    {
        return $this->can($actor, 'biometric.manage') || $this->can($actor, 'biometric.manage.team');
    }

    /**
     * @param array|object|null $actor
     * @param array|object|null $targetEmployee
     */
    public function canManageBiometricEmployee($actor, $targetEmployee): bool
    {
        return $this->canAccessBiometricArea($actor) && $this->canAccessEmployee($actor, $targetEmployee, false);
    }

    /**
     * @param array|object|null $actor
     */
    public function canViewOperationalReports($actor): bool
    {
        return $this->can($actor, 'reports.view');
    }

    /**
     * @param array|object|null $actor
     */
    public function canGenerateOperationalReports($actor): bool
    {
        return $this->canViewOperationalReports($actor);
    }

    /**
     * @param array|object|null $actor
     */
    public function canExportOperationalReports($actor): bool
    {
        return $this->can($actor, 'reports.export');
    }

    /**
     * Compatibilidade com chamadas antigas.
     *
     * @param array|object|null $actor
     */
    public function canViewReports($actor): bool
    {
        return $this->canViewOperationalReports($actor);
    }

    /**
     * Compatibilidade com chamadas antigas.
     *
     * @param array|object|null $actor
     */
    public function canExportReports($actor): bool
    {
        return $this->canExportOperationalReports($actor);
    }

    /**
     * @param array|object|null $actor
     */
    public function canViewAuditReports($actor): bool
    {
        return $this->can($actor, 'reports.audit') || $this->canViewAudit($actor);
    }

    /**
     * @param array|object|null $actor
     */
    public function canExportAuditReports($actor): bool
    {
        return $this->can($actor, 'reports.audit') || $this->canExportAudit($actor);
    }

    /**
     * @param array|object|null $actor
     */
    public function canViewAudit($actor): bool
    {
        return $this->can($actor, 'audit.view');
    }

    /**
     * Gestor pode ver logs de auditoria filtrados pelo seu departamento.
     * @param array|object|null $actor
     */
    public function canViewAuditLimited($actor): bool
    {
        return $this->can($actor, 'audit.view') || $this->can($actor, 'audit.view.limited');
    }

    /**
     * @param array|object|null $actor
     */
    public function canExportAudit($actor): bool
    {
        return $this->can($actor, 'audit.export');
    }


    /**
     * @param array|object|null $actor
     */
    public function canViewCompliance($actor): bool
    {
        return $this->can($actor, 'compliance.view');
    }

    /**
     * @param array|object|null $actor
     */
    public function canManageLgpd($actor): bool
    {
        return $this->can($actor, 'lgpd.manage');
    }

    /**
     * @param array|object|null $actor
     */
    public function canViewLgpd($actor): bool
    {
        return $this->can($actor, 'lgpd.view') || $this->canManageLgpd($actor);
    }

    /**
     * @param array|object|null $left
     * @param array|object|null $right
     */
    public function belongsToSameDepartment($left, $right): bool
    {
        $leftDepartmentId = $this->normalizeInt($this->getValue($left, 'department_id'));
        $rightDepartmentId = $this->normalizeInt($this->getValue($right, 'department_id'));

        // FK primeiro (não regride com renomeação de departamento); cai para
        // o texto legado só quando um dos dois lados não tem department_id.
        if ($leftDepartmentId !== null && $rightDepartmentId !== null) {
            return $leftDepartmentId === $rightDepartmentId;
        }

        $leftDepartment = $this->normalizeScalar($this->getValue($left, 'department'));
        $rightDepartment = $this->normalizeScalar($this->getValue($right, 'department'));

        return $leftDepartment !== '' && $leftDepartment === $rightDepartment;
    }

    /**
     * @param array|object|null $entity
     */
    private function getValue($entity, string $field): mixed
    {
        if (is_object($entity)) {
            return $entity->{$field} ?? null;
        }

        if (is_array($entity)) {
            return $entity[$field] ?? null;
        }

        return null;
    }


    private function normalizeInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    private function normalizeScalar(mixed $value): string
    {
        return strtolower(trim((string) $value));
    }
}
