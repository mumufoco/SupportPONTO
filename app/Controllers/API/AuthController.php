<?php

namespace App\Controllers\API;

use App\Services\Auth\ApiAuthService;
use App\Services\Auth\OAuth2Service;
use App\Services\Auth\PasswordLifecycleService;

/**
 * API Auth Controller
 *
 * Handles authentication for mobile/external applications using persisted OAuth2 tokens.
 */
class AuthController extends BaseApiController
{
    protected $modelName = 'App\Models\EmployeeModel';
    protected $format = 'json';

    protected ApiAuthService $apiAuthService;
    protected PasswordLifecycleService $passwordLifecycleService;

    public function __construct()
    {
        parent::__construct();
        $this->employeeModel = service('employeeModel');
        $this->apiAuthService = service('apiAuthService');
        $this->passwordLifecycleService = service('passwordLifecycleService');
        helper(['security', 'format']);
    }

    public function login()
    {
        $rules = [
            'email' => 'required|valid_email',
            'password' => 'required|min_length[6]',
        ];

        if (! $this->validate($rules)) {
            return $this->failStandard('validation_error', 'Dados inválidos.', 400, $this->validator->getErrors());
        }

        $email = (string) $this->requestValue('email', '');
        $password = (string) $this->requestValue('password', '');
        $scope = $this->requestValue('scope');
        $scopes = $this->apiAuthService->resolveScopes(is_string($scope) ? $scope : null);

        $result = $this->apiAuthService->login($email, $password, $scopes);

        if (! ($result['success'] ?? false)) {
            return $this->failStandard($result['code'] ?? 'invalid_credentials', $result['message'] ?? 'Credenciais inválidas.', (int) ($result['status'] ?? 401));
        }

        if ($result['requires_2fa'] ?? false) {
            return $this->respondStandard([
                    'requires_2fa' => true,
                    'two_factor_token' => $result['two_factor_token'],
                    'two_factor_expires_in' => $result['two_factor_expires_in'],
                ],
                'Verificação de dois fatores necessária.',
                200,
                'auth_login_2fa_required'
            );
        }

        $employee = $result['employee'];
        $tokens = $result['tokens'];

        return $this->respondStandard(array_merge($tokens, [
                'employee' => [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'email' => $employee->email,
                    'role' => $employee->role,
                    'department' => $employee->department,
                    'position' => $employee->position,
                    'unique_code' => $employee->unique_code,
                    'has_face_biometric' => $employee->has_face_biometric,
                    'has_fingerprint_biometric' => $employee->has_fingerprint_biometric,
                ],
            ]),
            'Login realizado com sucesso.',
            200,
            'auth_login_success'
        );
    }

    /**
     * Segunda etapa do login quando a conta tem 2FA habilitado (ver CRIT-05 na auditoria).
     * Recebe o two_factor_token emitido por login() + o código TOTP (ou backup code) e,
     * se válido, emite os tokens de acesso definitivos.
     */
    public function verifyTwoFactor()
    {
        $rules = [
            'two_factor_token' => 'required|string',
            'code' => 'required|string',
        ];

        if (! $this->validate($rules)) {
            return $this->failStandard('validation_error', 'Dados inválidos.', 400, $this->validator->getErrors());
        }

        $token = (string) $this->requestValue('two_factor_token', '');
        $code = (string) $this->requestValue('code', '');
        $useBackupCode = in_array((string) $this->requestValue('use_backup_code', ''), ['1', 'true', 'on', 'yes'], true);

        $result = $this->apiAuthService->verifyTwoFactor($token, $code, $useBackupCode);

        if (! ($result['success'] ?? false)) {
            return $this->failStandard($result['code'] ?? 'invalid_two_factor_code', $result['message'] ?? 'Código de verificação inválido.', (int) ($result['status'] ?? 401));
        }

        $employee = $result['employee'];
        $tokens = $result['tokens'];

        return $this->respondStandard(array_merge($tokens, [
                'employee' => [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'email' => $employee->email,
                    'role' => $employee->role,
                    'department' => $employee->department,
                    'position' => $employee->position,
                    'unique_code' => $employee->unique_code,
                    'has_face_biometric' => $employee->has_face_biometric,
                    'has_fingerprint_biometric' => $employee->has_fingerprint_biometric,
                ],
            ]),
            'Login realizado com sucesso.',
            200,
            'auth_login_success'
        );
    }

    public function logout()
    {
        $employee = $this->getAuthenticatedEmployee();
        $accessTokenId = $this->getAccessTokenId();

        if (! $employee || ! $accessTokenId) {
            return $this->failStandard('unauthorized', 'Não autenticado.', 401);
        }

        $this->apiAuthService->logout((int) $employee->id, (int) $accessTokenId, (string) $employee->name);

        return $this->respondStandard([], 'Logout realizado com sucesso.', 200, 'auth_logout_success');
    }

    public function refresh()
    {
        $refreshToken = $this->requestValue('refresh_token');

        if (! $refreshToken) {
            return $this->failStandard('validation_error', 'refresh_token é obrigatório.', 400);
        }

        $tokens = $this->apiAuthService->refresh(
            $refreshToken,
            OAuth2Service::generateDeviceFingerprint()
        );

        if (! $tokens) {
            return $this->failStandard('invalid_refresh_token', 'Refresh token inválido ou expirado.', 401);
        }

        return $this->respondStandard($tokens, 'Token renovado com sucesso.', 200, 'auth_refresh_success');
    }

    public function me()
    {
        $employee = $this->getAuthenticatedEmployee();

        if (! $employee) {
            return $this->failStandard('unauthorized', 'Não autenticado.', 401);
        }

        return $this->respondStandard([
            'id' => $employee->id,
            'name' => $employee->name,
            'email' => $employee->email,
            'cpf' => format_cpf($employee->cpf),
            'role' => $employee->role,
            'department' => $employee->department,
            'position' => $employee->position,
            'unique_code' => $employee->unique_code,
            'phone' => $employee->phone,
            'admission_date' => $employee->admission_date,
            'daily_hours' => $employee->daily_hours,
            'weekly_hours' => $employee->weekly_hours,
            'work_start_time' => $employee->work_start_time,
            'work_end_time' => $employee->work_end_time,
            'hours_balance' => $employee->hours_balance,
            'has_face_biometric' => $employee->has_face_biometric,
            'has_fingerprint_biometric' => $employee->has_fingerprint_biometric,
            'active' => $employee->active,
        ], 'Dados do usuário carregados com sucesso.', 200, 'auth_me_success');
    }

    public function changePassword()
    {
        $employee = $this->getAuthenticatedEmployee();

        if (! $employee) {
            return $this->failStandard('unauthorized', 'Não autenticado.', 401);
        }

        $rules = [
            'current_password' => 'required',
            'new_password' => 'required|strong_password',
            'new_password_confirm' => 'required|matches[new_password]',
        ];

        if (! $this->validate($rules)) {
            return $this->failStandard('validation_error', 'Dados inválidos.', 400, $this->validator->getErrors());
        }

        $currentPassword = $this->requestValue('current_password');
        $newPassword = $this->requestValue('new_password');

        if (! password_verify($currentPassword, $employee->password)) {
            return $this->failStandard('invalid_current_password', 'Senha atual incorreta.', 400);
        }

        $this->passwordLifecycleService->updatePassword((int) $employee->id, $newPassword, [
            'audit_action' => 'PASSWORD_CHANGED',
            'audit_description' => 'Senha alterada via API',
            'audit_level' => 'info',
        ]);

        return $this->respondStandard([], 'Senha alterada com sucesso.', 200, 'password_changed');
    }

}
