<?php

namespace App\Enums;

/**
 * MELHORIA 2: Enum PHP 8.1 para perfis de acesso (roles).
 *
 * Fonte única da verdade para os roles do sistema.
 * Substitui strings mágicas em EmployeeModel, AuthorizationService,
 * filtros e views.
 */
enum Role: string
{
    case Admin      = 'admin';
    case Gestor     = 'gestor';
    case Funcionario = 'funcionario';
    case RH         = 'rh';
    case DPO        = 'dpo';
    case Auditor    = 'auditor';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function validationList(): string
    {
        return implode(',', self::values());
    }

    /** Verifica se o role tem permissões administrativas */
    public function isAdminLevel(): bool
    {
        return $this === self::Admin;
    }

    /** Verifica se pode gerenciar funcionários */
    public function canManageEmployees(): bool
    {
        return match($this) {
            self::Admin, self::Gestor, self::RH => true,
            default => false,
        };
    }

    /** Verifica se pode ver relatórios operacionais */
    public function canViewOperationalReports(): bool
    {
        return match($this) {
            self::Admin, self::Gestor, self::RH => true,
            default => false,
        };
    }

    /** Verifica se pode ver relatórios de auditoria/compliance */
    public function canViewAuditReports(): bool
    {
        return match($this) {
            self::Admin, self::Gestor, self::DPO, self::Auditor => true,
            default => false,
        };
    }

    /** Compatibilidade: relatórios agora significam relatórios operacionais */
    public function canViewReports(): bool
    {
        return $this->canViewOperationalReports();
    }

    /** Normaliza aliases históricos para o valor canônico */
    public static function normalize(string $value): self
    {
        return match(strtolower(trim($value))) {
            'administrador', 'administrator', 'root', 'superadmin', 'super_admin' => self::Admin,
            'manager', 'gerente'          => self::Gestor,
            'employee', 'funcionário'     => self::Funcionario,
            'hr', 'rh'                    => self::RH,
            'data_protection', 'dpo'      => self::DPO,
            'auditoria', 'audit', 'auditor' => self::Auditor,
            default                       => self::from(strtolower(trim($value))),
        };
    }

    public function label(): string
    {
        return match($this) {
            self::Admin       => 'Administrador',
            self::Gestor      => 'Gestor',
            self::Funcionario => 'Funcionário',
            self::RH          => 'Recursos Humanos',
            self::DPO         => 'Encarregado de Dados (DPO)',
            self::Auditor     => 'Auditor',
        };
    }
}
