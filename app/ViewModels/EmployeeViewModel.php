<?php

namespace App\ViewModels;

use App\Enums\Role;

/**
 * MELHORIA 3: ViewModel para a tela de funcionários.
 *
 * Prepara dados de exibição antes de enviar para a view, eliminando
 * lógica de negócio e formatação dos templates PHP.
 *
 * Antes (na view edit.php — 139 PHP tags):
 *   <?php $badge = $role_badges[$employee->role] ?? [...]; ?>
 *   <?php if (in_array($employee->role, ['admin', 'gestor'])): ?>
 *   <?= format_cpf($employee->cpf) ?>
 *   <?= number_format($employee->salario_base, 2, ',', '.') ?>
 *
 * Depois (na view):
 *   <?= $vm->formattedCpf ?>
 *   <?php if ($vm->canEditRole): ?>
 *   <span style="color: <?= $vm->roleBadgeColor ?>">
 *
 * Uso no controller:
 *   return view('employees/edit', [
 *       'vm' => new EmployeeViewModel($employee, $currentUser),
 *   ]);
 */
final class EmployeeViewModel
{
    // Dados formatados para exibição
    public readonly string  $formattedCpf;
    public readonly string  $formattedPhone;
    public readonly string  $formattedSalary;
    public readonly string  $formattedAdmissionDate;
    public readonly string  $roleLabel;
    public readonly string  $roleBadgeColor;
    public readonly string  $roleBadgeBg;
    public readonly string  $statusLabel;
    public readonly string  $statusBadgeColor;
    public readonly string  $avatarInitials;
    public readonly string  $fullAddress;

    // Permissões pré-calculadas para uso na view
    public readonly bool    $canEdit;
    public readonly bool    $canEditRole;
    public readonly bool    $canViewSalary;
    public readonly bool    $canApprove;
    public readonly bool    $canDisable;
    public readonly bool    $canDelete;
    public readonly bool    $canViewBiometric;

    // Dados raw (quando a view realmente precisa)
    public readonly object  $employee;

    public function __construct(object $employee, object $currentUser)
    {
        $this->employee = $employee;

        $role   = Role::tryFrom((string) ($employee->role ?? '')) ?? Role::Funcionario;
        $active = (bool) ($employee->active ?? true);

        // ── Formatação ──────────────────────────────────────────────────────
        $this->formattedCpf = $this->formatCpf((string) ($employee->cpf ?? ''));
        $this->formattedPhone = $this->formatPhone((string) ($employee->phone ?? ''));
        $this->formattedSalary = $this->formatSalary((float) ($employee->salario_base ?? 0));
        $this->formattedAdmissionDate = $this->formatDate((string) ($employee->admission_date ?? ''));
        $this->roleLabel = $role->label();
        $this->roleBadgeColor = $this->roleBadgeColor($role);
        $this->roleBadgeBg    = $this->roleBadgeBg($role);
        $this->statusLabel     = $active ? 'Ativo' : 'Inativo';
        $this->statusBadgeColor = $active ? '#198754' : '#6c757d';
        $this->avatarInitials  = $this->buildInitials((string) ($employee->name ?? ''));
        $this->fullAddress     = $this->buildAddress($employee);

        // ── Permissões ──────────────────────────────────────────────────────
        $actorRole = Role::tryFrom((string) ($currentUser->role ?? '')) ?? Role::Funcionario;
        $isAdmin   = $actorRole === Role::Admin;
        $isRH      = $actorRole === Role::RH;
        $isGestor  = $actorRole === Role::Gestor;
        $isSelf    = (int) $currentUser->id === (int) $employee->id;

        $sameDept = !empty($currentUser->department)
                 && !empty($employee->department)
                 && $currentUser->department === $employee->department;

        $this->canEdit         = $isAdmin || $isRH || ($isGestor && $sameDept) || $isSelf;
        $this->canEditRole     = $isAdmin || $isRH;
        $this->canViewSalary   = $isAdmin || $isRH;
        $this->canApprove      = $isAdmin || $isRH || ($isGestor && $sameDept);
        $this->canDisable      = $isAdmin || $isRH;
        $this->canDelete       = $isAdmin;
        $this->canViewBiometric = $isAdmin || $isRH;
    }

    // ── Helpers privados ────────────────────────────────────────────────────

    private function formatCpf(string $cpf): string
    {
        $d = preg_replace('/\D/', '', $cpf);
        if (strlen($d) !== 11) return $cpf;
        return substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9, 2);
    }

    private function formatPhone(string $phone): string
    {
        $d = preg_replace('/\D/', '', $phone);
        return match (strlen($d)) {
            11 => '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 5) . '-' . substr($d, 7),
            10 => '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 4) . '-' . substr($d, 6),
            default => $phone,
        };
    }

    private function formatSalary(float $salary): string
    {
        return 'R$ ' . number_format($salary, 2, ',', '.');
    }

    private function formatDate(string $date): string
    {
        if (empty($date) || $date === '0000-00-00') return '—';
        try {
            return date('d/m/Y', strtotime($date));
        } catch (\Throwable) {
            return $date;
        }
    }

    private function buildInitials(string $name): string
    {
        $parts = array_filter(explode(' ', trim($name)));
        if (empty($parts)) return '?';
        $first = mb_substr(reset($parts), 0, 1);
        $last  = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';
        return mb_strtoupper($first . $last);
    }

    private function buildAddress(object $e): string
    {
        $parts = array_filter([
            $e->address ?? '',
            $e->city    ?? '',
            $e->uf      ?? '',
        ]);
        return implode(', ', $parts) ?: '—';
    }

    private function roleBadgeColor(Role $role): string
    {
        return match($role) {
            Role::Admin       => '#dc3545',
            Role::Gestor      => '#0d6efd',
            Role::RH          => '#198754',
            Role::DPO         => '#6f42c1',
            Role::Funcionario => '#6c757d',
        };
    }

    private function roleBadgeBg(Role $role): string
    {
        return match($role) {
            Role::Admin       => '#f8d7da',
            Role::Gestor      => '#cfe2ff',
            Role::RH          => '#d1e7dd',
            Role::DPO         => '#e2d9f3',
            Role::Funcionario => '#e2e3e5',
        };
    }
}
