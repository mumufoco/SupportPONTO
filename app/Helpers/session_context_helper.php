<?php

declare(strict_types=1);

use App\Services\AuthorizationService;

if (!function_exists('sp_session_user')) {
    /**
     * Retorna o contexto autenticado canônico da sessão.
     * Mantém fallback legado employee_* apenas para compatibilidade transitória.
     *
     * @return array{id:?int,name:string,email:?string,role:string,department:mixed,active:bool}
     */
    function sp_session_user(): array
    {
        $session = session();
        $employee = $session->get('employee');

        $userId = $session->get('user_id');

        $name = $session->get('user_name');
        if (($name === null || $name === '') && is_array($employee)) {
            $name = $employee['name'] ?? null;
        }

        $email = $session->get('user_email');
        if (($email === null || $email === '') && is_array($employee)) {
            $email = $employee['email'] ?? null;
        }

        $role = $session->get('user_role');
        if (($role === null || $role === '') && is_array($employee)) {
            $role = $employee['role'] ?? null;
        }

        $department = null;
        if (is_array($employee)) {
            $department = $employee['department'] ?? null;
        }
        if ($department === null || $department === '') {
            $department = $session->get('user_department');
        }

        $active = $session->get('user_active');
        if ($active === null && is_array($employee) && array_key_exists('active', $employee)) {
            $active = $employee['active'];
        }

        $normalizedRole = 'funcionario';
        try {
            $normalizedRole = (new AuthorizationService())->normalizeRole((string) ($role ?? 'funcionario'));
        } catch (Throwable) {
            $normalizedRole = 'funcionario';
        }

        return [
            'id' => $userId !== null && $userId !== '' ? (int) $userId : null,
            'name' => is_string($name) && trim($name) !== '' ? trim($name) : 'Usuário',
            'email' => is_string($email) && trim($email) !== '' ? trim($email) : null,
            'role' => $normalizedRole,
            'department' => $department,
            'active' => !($active === false || $active === '0' || $active === 0),
        ];
    }
}

if (!function_exists('sp_session_user_id')) {
    function sp_session_user_id(): ?int
    {
        return sp_session_user()['id'];
    }
}

if (!function_exists('sp_session_is_authenticated')) {
    function sp_session_is_authenticated(): bool
    {
        return sp_session_user_id() !== null;
    }
}
