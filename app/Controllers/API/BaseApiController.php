<?php

namespace App\Controllers\API;

use App\Libraries\ApiRequestAuthContext;
use App\Models\EmployeeModel;
use App\Services\Auth\OAuth2Service;
use App\Services\AuthorizationService;
use App\Services\API\ApiPayloadSanitizer;
use App\Traits\ObservabilityTrait;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourceController;
use Config\Services;

/**
 * Base API Controller
 *
 * Provides common functionality for OAuth2-protected API controllers.
 */
class BaseApiController extends ResourceController
{
    use ResponseTrait;
    use ObservabilityTrait;

    protected $authenticatedEmployee = null;

    protected EmployeeModel $employeeModel;
    protected OAuth2Service $oauth2Service;
    protected AuthorizationService $authorizationService;
    protected ApiRequestAuthContext $apiAuthContext;
    protected ApiPayloadSanitizer $apiPayloadSanitizer;

    public function __construct()
    {
        $this->employeeModel = new EmployeeModel();
        $this->oauth2Service = new OAuth2Service();
        $this->authorizationService = new AuthorizationService();
        $this->apiAuthContext = Services::apiRequestAuthContext();
        $this->apiPayloadSanitizer = Services::apiPayloadSanitizer();
    }

    protected function getTokenData(): ?array
    {
        if ($this->apiAuthContext->hasContext()) {
            return $this->apiAuthContext->getTokenData();
        }

        $authHeader = $this->request->getHeaderLine('Authorization');
        if ($authHeader === '' || ! preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return null;
        }

        $accessToken = trim($matches[1]);
        if ($accessToken === '') {
            return null;
        }

        $deviceFingerprint = $this->apiAuthContext->getDeviceFingerprint() ?? OAuth2Service::generateDeviceFingerprint();
        $tokenData = $this->oauth2Service->validateAccessToken($accessToken, $deviceFingerprint);

        if (is_array($tokenData)) {
            $this->apiAuthContext->setContext($tokenData, $deviceFingerprint, $accessToken);
        }

        return $tokenData ?: null;
    }

    protected function getAuthenticatedEmployee(): ?object
    {
        if ($this->authenticatedEmployee !== null) {
            return $this->authenticatedEmployee;
        }

        $tokenData = $this->getTokenData();
        if (! $tokenData || ! isset($tokenData['employee_id'])) {
            return null;
        }

        $employee = $this->employeeModel->find($tokenData['employee_id']);
        if (! $employee || ! $employee->active) {
            return null;
        }

        return $this->authenticatedEmployee = $employee;
    }

    protected function getAuthenticatedEmployeeId(): ?int
    {
        $contextEmployeeId = $this->apiAuthContext->getEmployeeId();
        if ($contextEmployeeId !== null) {
            return $contextEmployeeId;
        }

        $tokenData = $this->getTokenData();
        return isset($tokenData['employee_id']) ? (int) $tokenData['employee_id'] : null;
    }

    protected function getAccessTokenId(): ?int
    {
        $tokenData = $this->getTokenData();
        return isset($tokenData['id']) ? (int) $tokenData['id'] : null;
    }

    protected function getDeviceFingerprint(): ?string
    {
        return $this->apiAuthContext->getDeviceFingerprint();
    }

    protected function requestValue(string $key, mixed $default = null): mixed
    {
        $value = $this->request->getJsonVar($key);
        if ($value !== null) {
            return $this->apiPayloadSanitizer->sanitizeValue($value, $key);
        }

        $value = $this->request->getPost($key);
        if ($value !== null) {
            return $this->apiPayloadSanitizer->sanitizeValue($value, $key);
        }

        $rawInput = $this->request->getRawInput();
        if (array_key_exists($key, $rawInput)) {
            return $this->apiPayloadSanitizer->sanitizeValue($rawInput[$key], $key);
        }

        if ($this->request->is('get')) {
            $value = $this->request->getGet($key);
            if ($value !== null) {
                return $this->apiPayloadSanitizer->sanitizeQueryValue($value, $key);
            }
        }

        return $default;
    }

    protected function requestPayload(): array
    {
        $payload = [];

        $json = $this->request->getJSON(true);
        if (is_array($json)) {
            $payload = $json;
        }

        $post = $this->request->getPost();
        if (is_array($post) && $post !== []) {
            $payload = array_merge($payload, $post);
        }

        $raw = $this->request->getRawInput();
        if (is_array($raw) && $raw !== []) {
            $payload = array_merge($payload, $raw);
        }

        return $this->apiPayloadSanitizer->sanitizePayload($payload);
    }

    protected function respondStandard(array $data = [], string $message = 'OK', int $statusCode = 200, string $code = 'ok'): ResponseInterface
    {
        return $this->attachResponseContext(
            $this->respond($this->apiSuccessPayload($data, $message, $code), $statusCode),
            $statusCode >= 400
        );
    }

    protected function failStandard(string $code, string $message, int $statusCode = 400, array|string|null $errors = null): ResponseInterface
    {
        if (is_string($errors)) {
            $decoded = json_decode($errors, true);
            $errors = is_array($decoded) ? $decoded : ['details' => $errors];
        }

        return $this->attachResponseContext(
            $this->respond($this->apiErrorPayload($code, $message, $errors), $statusCode),
            true
        );
    }

    public function failValidationErrors($errors, ?string $code = null, string $message = ''): ResponseInterface
    {
        return $this->failStandard(
            $code ?? 'validation_error',
            $message !== '' ? $message : 'Dados inválidos.',
            400,
            $errors
        );
    }

    protected function unauthorizedResponse(): ResponseInterface
    {
        return $this->failUnauthorized('Não autenticado. Token inválido ou expirado.');
    }

    protected function forbidResponse(string $message = 'Acesso negado. Permissão insuficiente.'): ResponseInterface
    {
        return $this->failForbidden($message);
    }

    /**
     * @return object|ResponseInterface
     */
    protected function requireAuth()
    {
        $employee = $this->getAuthenticatedEmployee();

        if (! $employee) {
            return $this->unauthorizedResponse();
        }

        return $employee;
    }

    /**
     * @return object|ResponseInterface
     */
    protected function requireRole(string $role)
    {
        $employee = $this->requireAuth();

        if ($employee instanceof ResponseInterface) {
            return $employee;
        }

        if (! $this->authorizationService->hasRole($employee, $role)) {
            return $this->forbidResponse();
        }

        return $employee;
    }

    protected function hasAnyRole(object $employee, array $roles): bool
    {
        return $this->authorizationService->hasRole($employee, $roles);
    }
}
