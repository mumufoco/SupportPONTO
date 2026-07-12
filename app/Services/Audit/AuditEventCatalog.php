<?php

declare(strict_types=1);

namespace App\Services\Audit;

/**
 * Catálogo canônico de ações auditáveis críticas.
 *
 * Mantém uma taxonomia única para evitar variações soltas como LOGIN_OK,
 * SIGNIN, USER_LOGIN etc. Os gates de release passam a validar a presença
 * desses eventos em produtores críticos.
 */
final class AuditEventCatalog
{
    public const LOGIN_SUCCEEDED = 'LOGIN';
    public const LOGIN_FAILED = 'LOGIN_FAILED';
    public const LOGIN_BLOCKED = 'LOGIN_BLOCKED';
    public const LOGIN_REMEMBER_ME = 'LOGIN_REMEMBER_ME';
    public const LOGOUT = 'LOGOUT';
    public const PASSWORD_CHANGED = 'PASSWORD_CHANGED';
    public const FIRST_ACCESS_PASSWORD_CHANGED = 'FIRST_ACCESS_PASSWORD_CHANGED';
    public const EMPLOYEE_CREATED = 'EMPLOYEE_CREATED';
    public const EMPLOYEE_UPDATED = 'EMPLOYEE_UPDATED';
    public const EMPLOYEE_DELETED = 'EMPLOYEE_DELETED';
    public const EMPLOYEE_ACTIVATED = 'EMPLOYEE_ACTIVATED';
    public const EMPLOYEE_DEACTIVATED = 'EMPLOYEE_DEACTIVATED';
    public const EMPLOYEE_DATA_EXPORTED = 'EMPLOYEE_DATA_EXPORTED';
    public const PUNCH_REGISTERED = 'PUNCH_REGISTERED';
    public const PUNCH_ADJUSTED = 'PUNCH_ADJUSTED';
    public const JUSTIFICATION_CREATED = 'JUSTIFICATION_CREATED';
    public const JUSTIFICATION_APPROVED = 'JUSTIFICATION_APPROVED';
    public const JUSTIFICATION_REJECTED = 'JUSTIFICATION_REJECTED';
    public const REPORT_EXPORTED = 'REPORT_EXPORTED';
    public const REPORT_QUEUED = 'REPORT_QUEUED';
    public const REPORT_GENERATED = 'REPORT_GENERATED';
    public const AUDIT_EXPORTED = 'AUDIT_EXPORTED';
    public const AUDIT_MAINTENANCE = 'AUDIT_MAINTENANCE';
    public const AUDIT_LOGS_CLEARED = 'AUDIT_LOGS_CLEARED';
    public const INSTALLER_STARTED = 'INSTALLER_STARTED';
    public const INSTALLER_COMPLETED = 'INSTALLER_COMPLETED';
    public const INSTALLER_FAILED = 'INSTALLER_FAILED';

    /** @return list<string> */
    public static function criticalEvents(): array
    {
        return [
            self::LOGIN_SUCCEEDED,
            self::LOGIN_FAILED,
            self::LOGIN_BLOCKED,
            self::LOGOUT,
            self::PASSWORD_CHANGED,
            self::FIRST_ACCESS_PASSWORD_CHANGED,
            self::EMPLOYEE_CREATED,
            self::EMPLOYEE_UPDATED,
            self::EMPLOYEE_DELETED,
            self::EMPLOYEE_ACTIVATED,
            self::EMPLOYEE_DEACTIVATED,
            self::PUNCH_REGISTERED,
            self::JUSTIFICATION_CREATED,
            self::JUSTIFICATION_APPROVED,
            self::JUSTIFICATION_REJECTED,
            self::REPORT_EXPORTED,
            self::REPORT_QUEUED,
            self::REPORT_GENERATED,
            self::AUDIT_EXPORTED,
            self::AUDIT_MAINTENANCE,
            self::AUDIT_LOGS_CLEARED,
            self::INSTALLER_STARTED,
            self::INSTALLER_COMPLETED,
            self::INSTALLER_FAILED,
        ];
    }

    public static function normalize(string $action): string
    {
        return strtoupper(trim($action));
    }
}
