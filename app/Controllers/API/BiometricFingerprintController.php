<?php

namespace App\Controllers\API;

use App\Services\Biometric\ApiFingerprintBiometricService;
use CodeIgniter\HTTP\ResponseInterface;
use Exception;

/**
 * Fingerprint Biometric API Controller
 */
class BiometricFingerprintController extends BaseApiController
{
    protected ApiFingerprintBiometricService $apiFingerprintBiometricService;

    public function __construct()
    {
        parent::__construct();
        $this->apiFingerprintBiometricService = new ApiFingerprintBiometricService();
    }

    public function enroll(): ResponseInterface
    {
        try {
            $currentEmployee = $this->getAuthenticatedEmployee();
            if (!$currentEmployee) {
                return $this->failStandard('unauthorized', 'Não autenticado.', 401);
            }

            $rules = [
                'employee_id' => 'required|integer',
                'fingerprint_data' => 'required',
                'finger_position' => 'required|in_list[thumb_right,thumb_left,index_right,index_left,middle_right,middle_left,ring_right,ring_left,pinky_right,pinky_left]',
                'capture_method' => 'required|in_list[scanner,image,mobile]',
            ];

            if (!$this->validate($rules)) {
                return $this->failValidationErrors($this->validator->getErrors(), 'validation_error', 'Dados biométricos inválidos.');
            }

            $result = $this->apiFingerprintBiometricService->enroll(
                $currentEmployee,
                (int) $this->requestValue('employee_id'),
                (string) $this->requestValue('fingerprint_data'),
                (string) $this->requestValue('finger_position'),
                (string) $this->requestValue('capture_method'),
                $this->requestValue('device') !== null ? (string) $this->requestValue('device') : null
            );

            if (!$result['success']) {
                $this->logSecurityEvent('warning', 'Biometric enroll rejected', ['result' => $result]);
                return $this->failStandard($result['code'] ?? 'biometric_enroll_failed', $result['message'], (int) ($result['status'] ?? 400));
            }

            $this->logSecurityEvent('info', 'Biometric enroll succeeded', ['employee_id' => (int) $this->requestValue('employee_id')]);
            return $this->respondStandard($result['data'] ?? [], $result['message'] ?? 'Biometria cadastrada com sucesso.', (int) ($result['status'] ?? 201), 'biometric_enroll_success');
        } catch (Exception $e) {
            $this->logSecurityEvent('error', 'Biometric enroll exception', ['exception' => $e->getMessage()]);
            return $this->failStandard('internal_error', 'Erro interno ao processar requisição.', 500);
        }
    }

    public function verify(): ResponseInterface
    {
        try {
            $currentEmployee = $this->getAuthenticatedEmployee();
            if (!$currentEmployee) {
                return $this->failStandard('unauthorized', 'Não autenticado.', 401);
            }

            $rules = [
                'employee_id' => 'required|integer',
                'fingerprint_data' => 'required',
            ];

            if (!$this->validate($rules)) {
                return $this->failValidationErrors($this->validator->getErrors(), 'validation_error', 'Dados biométricos inválidos.');
            }

            $result = $this->apiFingerprintBiometricService->verify(
                $currentEmployee,
                (int) $this->requestValue('employee_id'),
                (string) $this->requestValue('fingerprint_data')
            );

            if (!$result['success']) {
                $this->logSecurityEvent('warning', 'Biometric verify failed', ['result' => $result]);
                return $this->failStandard($result['code'] ?? 'biometric_verify_failed', $result['message'], (int) ($result['status'] ?? 400));
            }

            return $this->respondStandard($result['data'] ?? [], $result['message'] ?? 'Biometria validada com sucesso.', 200, 'biometric_verify_success');
        } catch (Exception $e) {
            $this->logSecurityEvent('error', 'Biometric verify exception', ['exception' => $e->getMessage()]);
            return $this->failStandard('internal_error', 'Erro interno ao processar requisição.', 500);
        }
    }

    public function identify(): ResponseInterface
    {
        try {
            $rules = ['fingerprint_data' => 'required'];
            if (!$this->validate($rules)) {
                return $this->failValidationErrors($this->validator->getErrors(), 'validation_error', 'Dados biométricos inválidos.');
            }

            $department = $this->requestValue('department');
            $result = $this->apiFingerprintBiometricService->identify(
                (string) $this->requestValue('fingerprint_data'),
                $department !== null ? (string) $department : null
            );

            if (!$result['success']) {
                $this->logSecurityEvent('warning', 'Biometric identify failed', ['result' => $result]);
                return $this->failStandard($result['code'] ?? 'biometric_identify_failed', $result['message'], (int) ($result['status'] ?? 400));
            }

            return $this->respondStandard($result['data'] ?? [], $result['message'] ?? 'Biometria processada com sucesso.', 200, 'biometric_identify_success');
        } catch (Exception $e) {
            $this->logSecurityEvent('error', 'Biometric identify exception', ['exception' => $e->getMessage()]);
            return $this->failStandard('internal_error', 'Erro interno ao processar requisição.', 500);
        }
    }

    public function listTemplates($employeeId = null): ResponseInterface
    {
        try {
            $currentEmployee = $this->getAuthenticatedEmployee();
            if (!$currentEmployee) {
                return $this->failStandard('unauthorized', 'Não autenticado', 401);
            }

            $result = $this->apiFingerprintBiometricService->listTemplates($currentEmployee, $employeeId !== null ? (int) $employeeId : null);
            if (!$result['success']) {
                return $this->failStandard($result['code'] ?? 'templates_list_failed', $result['message'], (int) ($result['status'] ?? 400));
            }

            return $this->respondStandard($result['data'] ?? [], 'Templates listados com sucesso.', 200, 'templates_list_success');
        } catch (Exception $e) {
            log_message('error', 'BiometricController::listTemplates error: ' . $e->getMessage());
            return $this->failStandard('internal_error', 'Erro interno ao processar requisição', 500);
        }
    }

    public function deleteTemplate($templateId = null): ResponseInterface
    {
        try {
            $currentEmployee = $this->getAuthenticatedEmployee();
            if (!$currentEmployee) {
                return $this->failStandard('unauthorized', 'Não autenticado', 401);
            }

            if ($templateId === null) {
                return $this->failStandard('validation_error', 'ID do template é obrigatório', 400);
            }

            $result = $this->apiFingerprintBiometricService->deleteTemplate($currentEmployee, (int) $templateId);
            if (!$result['success']) {
                return $this->failStandard($result['code'] ?? 'template_delete_failed', $result['message'], (int) ($result['status'] ?? 400));
            }

            return $this->respondStandard([], $result['message'] ?? 'Template excluído com sucesso.', 200, 'template_delete_success');
        } catch (Exception $e) {
            log_message('error', 'BiometricController::deleteTemplate error: ' . $e->getMessage());
            return $this->failStandard('internal_error', 'Erro interno ao processar requisição', 500);
        }
    }

    public function consent(): ResponseInterface
    {
        try {
            $currentEmployee = $this->getAuthenticatedEmployee();
            if (!$currentEmployee) {
                return $this->failStandard('unauthorized', 'Não autenticado', 401);
            }

            $result = $this->apiFingerprintBiometricService->grantConsent((int) $currentEmployee->id);
            if (!$result['success']) {
                return $this->failStandard($result['code'] ?? 'consent_grant_failed', $result['message'], (int) ($result['status'] ?? 400));
            }

            return $this->respondStandard([], $result['message'] ?? 'Consentimento registrado com sucesso', (int) ($result['status'] ?? 201), 'consent_grant_success');
        } catch (Exception $e) {
            log_message('error', 'BiometricController::consent error: ' . $e->getMessage());
            return $this->failStandard('internal_error', 'Erro interno ao processar requisição', 500);
        }
    }

    public function revokeConsent(): ResponseInterface
    {
        try {
            $currentEmployee = $this->getAuthenticatedEmployee();
            if (!$currentEmployee) {
                return $this->failStandard('unauthorized', 'Não autenticado', 401);
            }

            $result = $this->apiFingerprintBiometricService->revokeConsent((int) $currentEmployee->id);
            if (!$result['success']) {
                return $this->failStandard($result['code'] ?? 'consent_revoke_failed', $result['message'], (int) ($result['status'] ?? 400));
            }

            return $this->respondStandard([], $result['message'] ?? 'Consentimento revogado.', 200, 'consent_revoke_success');
        } catch (Exception $e) {
            log_message('error', 'BiometricController::revokeConsent error: ' . $e->getMessage());
            return $this->failStandard('internal_error', 'Erro interno ao processar requisição', 500);
        }
    }

    public function consentStatus(): ResponseInterface
    {
        try {
            $currentEmployee = $this->getAuthenticatedEmployee();
            if (!$currentEmployee) {
                return $this->failStandard('unauthorized', 'Não autenticado', 401);
            }

            $result = $this->apiFingerprintBiometricService->consentStatus((int) $currentEmployee->id);
            return $this->respondStandard($result['data'] ?? [], 'Status de consentimento carregado.', 200, 'consent_status_success');
        } catch (Exception $e) {
            log_message('error', 'BiometricController::consentStatus error: ' . $e->getMessage());
            return $this->failStandard('internal_error', 'Erro interno ao processar requisição', 500);
        }
    }
}
