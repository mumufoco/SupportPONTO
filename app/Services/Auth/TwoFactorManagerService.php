<?php

namespace App\Services\Auth;

use App\Models\EmployeeModel;
use App\Services\Security\EncryptionService;
use App\Services\Security\TwoFactorAuthService;
use Config\Services;

class TwoFactorManagerService
{
    protected TwoFactorAuthService $twoFactorService;
    protected EncryptionService $encryptionService;
    protected EmployeeModel $employeeModel;

    public function __construct(
        ?TwoFactorAuthService $twoFactorService = null,
        ?EncryptionService $encryptionService = null,
        ?EmployeeModel $employeeModel = null
    ) {
        $this->twoFactorService = $twoFactorService ?? Services::twoFactorAuthService();
        $this->encryptionService = $encryptionService ?? Services::encryptionService();
        $this->employeeModel = $employeeModel ?? Services::employeeModel();
    }

    public function buildSetupPayload(object $employee, ?string $existingSecret = null): array
    {
        $secret = ($existingSecret !== null && $existingSecret !== '')
            ? $existingSecret
            : $this->twoFactorService->generateSecret();

        return [
            'secret' => $secret,
            'qr_code_data' => $this->twoFactorService->getQRCodeDataUri($secret, (string) $employee->email),
            'otpauth_url' => $this->twoFactorService->getOTPAuthURL($secret, (string) $employee->email),
        ];
    }

    public function enableForEmployee(int $employeeId, string $secret): array
    {
        $backupCodes = $this->twoFactorService->generateBackupCodes(10);
        $hashedBackupCodes = array_map(
            fn(string $code): string => $this->twoFactorService->hashBackupCode($code),
            $backupCodes
        );

        $this->employeeModel->update($employeeId, [
            'two_factor_enabled' => true,
            'two_factor_secret' => $this->encryptionService->encrypt($secret),
            'two_factor_backup_codes' => $this->encryptionService->encryptJson($hashedBackupCodes),
            'two_factor_verified_at' => date('Y-m-d H:i:s'),
        ]);

        return $backupCodes;
    }

    public function disableForEmployee(int $employeeId): bool
    {
        return $this->employeeModel->update($employeeId, [
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_backup_codes' => null,
            'two_factor_verified_at' => null,
        ]);
    }

    public function regenerateBackupCodes(int $employeeId): array
    {
        $backupCodes = $this->twoFactorService->generateBackupCodes(10);
        $hashedBackupCodes = array_map(
            fn(string $code): string => $this->twoFactorService->hashBackupCode($code),
            $backupCodes
        );

        $this->employeeModel->update($employeeId, [
            'two_factor_backup_codes' => $this->encryptionService->encryptJson($hashedBackupCodes),
        ]);

        return $backupCodes;
    }

    public function verifyTotpCode(object $employee, string $code): bool
    {
        if (empty($employee->two_factor_secret)) {
            return false;
        }

        try {
            $decryptedSecret = $this->encryptionService->decrypt((string) $employee->two_factor_secret);
        } catch (\Throwable $e) {
            log_message('error', '2FA: falha ao descriptografar segredo: ' . $e->getMessage());
            return false;
        }

        return $this->twoFactorService->verifyCode($decryptedSecret, $code);
    }

    public function countRemainingBackupCodes(object $employee): int
    {
        if (! (bool) ($employee->two_factor_enabled ?? false) || empty($employee->two_factor_backup_codes)) {
            return 0;
        }

        $backupCodes = $this->encryptionService->decryptJson((string) $employee->two_factor_backup_codes);

        return is_array($backupCodes) ? count($backupCodes) : 0;
    }

    public function verifyAndConsumeBackupCode(object $employee, string $code): bool
    {
        if (empty($employee->two_factor_backup_codes)) {
            return false;
        }

        try {
            $hashedBackupCodes = $this->encryptionService->decryptJson((string) $employee->two_factor_backup_codes);
        } catch (\Throwable $e) {
            log_message('error', '2FA: falha ao descriptografar códigos de backup: ' . $e->getMessage());
            return false;
        }

        if (! is_array($hashedBackupCodes) || $hashedBackupCodes === []) {
            return false;
        }

        foreach ($hashedBackupCodes as $index => $hashedCode) {
            if ($this->twoFactorService->verifyBackupCode($code, (string) $hashedCode)) {
                unset($hashedBackupCodes[$index]);

                $this->employeeModel->update((int) $employee->id, [
                    'two_factor_backup_codes' => $this->encryptionService->encryptJson(array_values($hashedBackupCodes)),
                ]);

                return true;
            }
        }

        return false;
    }
}
