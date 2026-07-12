<?php

namespace App\Controllers\API;

use App\Services\Biometric\ApiFaceBiometricService;
use App\Services\Biometric\Face\FaceImageService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * API Biometric Controller (face + consent endpoints)
 */
class BiometricController extends BaseApiController
{
    protected string $format = 'json';

    protected ApiFaceBiometricService $apiFaceBiometricService;
    protected FaceImageService $faceImageService;

    public function __construct()
    {
        parent::__construct();
        $this->apiFaceBiometricService = new ApiFaceBiometricService();
        $this->faceImageService = new FaceImageService();
    }

    public function enrollFace(): ResponseInterface
    {
        $employee = $this->getAuthenticatedEmployee();
        if (!$employee) {
            return $this->failStandard('unauthorized', 'Não autenticado.', 401);
        }

        $photo = $this->resolvePhotoPayload();
        $validation = $photo !== null ? $this->faceImageService->validateImage($photo) : ['valid' => false, 'error' => 'Foto é obrigatória.'];
        if (($validation['valid'] ?? false) !== true) {
            return $this->failValidationErrors(['photo' => $validation['error'] ?? 'Imagem facial inválida.'], 'validation_error', 'Dados biométricos inválidos.');
        }

        $result = $this->apiFaceBiometricService->enrollFace((int) $employee->id, $photo);
        if (!$result['success']) {
            return $this->failStandard($result['code'] ?? 'face_enroll_failed', $result['message'], (int) ($result['status'] ?? 400), $result['errors'] ?? null);
        }

        return $this->respondStandard($result['data'] ?? [], $result['message'] ?? 'Biometria facial cadastrada com sucesso!', (int) ($result['status'] ?? 201), 'face_enroll_success');
    }

    public function testFace(): ResponseInterface
    {
        $employee = $this->getAuthenticatedEmployee();
        if (!$employee) {
            return $this->failStandard('unauthorized', 'Não autenticado.', 401);
        }

        $photo = $this->resolvePhotoPayload();
        $validation = $photo !== null ? $this->faceImageService->validateImage($photo) : ['valid' => false, 'error' => 'Foto é obrigatória.'];
        if (($validation['valid'] ?? false) !== true) {
            return $this->failValidationErrors(['photo' => $validation['error'] ?? 'Imagem facial inválida.'], 'validation_error', 'Dados biométricos inválidos.');
        }

        $result = $this->apiFaceBiometricService->testFace((int) $employee->id, $photo);
        if (!$result['success']) {
            return $this->failStandard($result['code'] ?? 'face_test_failed', $result['message'], (int) ($result['status'] ?? 400));
        }

        return $this->respondStandard($result['data'] ?? [], $result['message'] ?? 'Reconhecimento processado.', 200, 'face_test_success');
    }

    public function deleteFace($id = null): ResponseInterface
    {
        $employee = $this->getAuthenticatedEmployee();
        if (!$employee) {
            return $this->failStandard('unauthorized', 'Não autenticado.', 401);
        }

        if ($id === null) {
            return $this->failStandard('validation_error', 'ID do template é obrigatório.', 400);
        }

        $result = $this->apiFaceBiometricService->deleteFace((int) $employee->id, (int) $id);
        if (!$result['success']) {
            return $this->failStandard($result['code'] ?? 'face_delete_failed', $result['message'], (int) ($result['status'] ?? 400));
        }

        return $this->respondStandard([], $result['message'] ?? 'Template biométrico excluído com sucesso.', 200, 'face_delete_success');
    }

    public function templates(): ResponseInterface
    {
        $employee = $this->getAuthenticatedEmployee();
        if (!$employee) {
            return $this->failStandard('unauthorized', 'Não autenticado.', 401);
        }

        $result = $this->apiFaceBiometricService->templates((int) $employee->id);
        return $this->respondStandard($result['data'] ?? [], 'Templates biométricos carregados.', 200, 'biometric_templates_success');
    }

    public function grantConsent(): ResponseInterface
    {
        $employee = $this->getAuthenticatedEmployee();
        if (!$employee) {
            return $this->failStandard('unauthorized', 'Não autenticado.', 401);
        }

        $result = $this->apiFaceBiometricService->grantConsent((int) $employee->id);
        if (!$result['success']) {
            return $this->failStandard($result['code'] ?? 'consent_grant_failed', $result['message'], (int) ($result['status'] ?? 400));
        }

        return $this->respondStandard([], $result['message'] ?? 'Consentimento registrado com sucesso!', (int) ($result['status'] ?? 201), 'consent_grant_success');
    }

    public function revokeConsent(): ResponseInterface
    {
        $employee = $this->getAuthenticatedEmployee();
        if (!$employee) {
            return $this->failStandard('unauthorized', 'Não autenticado.', 401);
        }

        $result = $this->apiFaceBiometricService->revokeConsent((int) $employee->id);
        if (!$result['success']) {
            return $this->failStandard($result['code'] ?? 'consent_revoke_failed', $result['message'], (int) ($result['status'] ?? 400));
        }

        return $this->respondStandard([], $result['message'] ?? 'Consentimento revogado.', 200, 'consent_revoke_success');
    }

    public function consentStatus(): ResponseInterface
    {
        $employee = $this->getAuthenticatedEmployee();
        if (!$employee) {
            return $this->failStandard('unauthorized', 'Não autenticado.', 401);
        }

        $result = $this->apiFaceBiometricService->consentStatus((int) $employee->id);
        return $this->respondStandard($result['data'] ?? [], 'Status de consentimento carregado.', 200, 'consent_status_success');
    }

    private function resolvePhotoPayload(): ?string
    {
        $photo = $this->request->getPost('photo') ?: $this->request->getPost('image');
        if (is_string($photo) && $photo !== '') {
            return $photo;
        }

        $payload = $this->request->getJSON(true);
        if (is_array($payload)) {
            $candidate = $payload['photo'] ?? $payload['image'] ?? null;
            return is_string($candidate) && $candidate !== '' ? $candidate : null;
        }

        return null;
    }
}
