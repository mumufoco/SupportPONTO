<?php

namespace App\Services\Auth;

use App\Models\EmployeeModel;
use App\Models\AuditModel;

class PasswordLifecycleService
{
    protected EmployeeModel $employeeModel;
    protected AuditModel $auditModel;

    public function __construct()
    {
        $this->employeeModel = new EmployeeModel();
        $this->auditModel = new AuditModel();
    }

    public function updatePassword(int $employeeId, string $plainPassword, array $options = []): void
    {
        $passwordErrors = \App\Support\InitialAdminPolicy::validateBootstrapPassword($plainPassword);
        if ($passwordErrors !== []) {
            throw new \InvalidArgumentException('A senha não atende à política mínima: ' . implode(' ', $passwordErrors));
        }
        $data = [
            'password' => password_hash($plainPassword, PASSWORD_ARGON2ID),
            'must_change_password' => false,
            'password_changed_at' => date('Y-m-d H:i:s'),
        ];

        if (! empty($options['clear_reset_tokens'])) {
            $data['password_reset_token'] = null;
            $data['password_reset_expires'] = null;
        }

        if (! empty($options['clear_remember_tokens'])) {
            $data['remember_token'] = null;
            $data['remember_token_expires'] = null;
        }

        $this->employeeModel->update($employeeId, $data);
        $this->removeLegacyBootstrapCredentialFiles();

        if (! empty($options['audit_action'])) {
            $this->auditModel->log(
                $employeeId,
                $options['audit_action'],
                'employees',
                $employeeId,
                null,
                $options['audit_new_values'] ?? null,
                $options['audit_description'] ?? null,
                $options['audit_level'] ?? 'info'
            );
        }
    }

    private function removeLegacyBootstrapCredentialFiles(): void
    {
        foreach ([
            WRITEPATH . 'secrets/admin_bootstrap_credentials.json',
            WRITEPATH . 'secrets/admin_bootstrap_credentials.txt',
        ] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
}
