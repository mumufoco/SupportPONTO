<?php

namespace App\Services\Employees;

class EmployeeIdentityService
{
    public function toArray(mixed $employee): array
    {
        if (is_array($employee)) {
            return [
                'name' => (string) ($employee['name'] ?? ''),
                'email' => (string) ($employee['email'] ?? ''),
                'unique_code' => (string) ($employee['unique_code'] ?? ''),
            ];
        }

        return [
            'name' => (string) ($employee->name ?? ''),
            'email' => (string) ($employee->email ?? ''),
            'unique_code' => (string) ($employee->unique_code ?? ''),
        ];
    }
}
