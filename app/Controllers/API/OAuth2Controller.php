<?php

namespace App\Controllers\API;

use App\Services\Auth\ApiAuthService;
use App\Services\Auth\OAuth2Service;
use App\Services\Security\RateLimitService;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

/**
 * OAuth 2.0 Controller
 *
 * Compatibility OAuth 2.0 endpoints.
 *
 * Canonical API auth flow lives in API\AuthController + ApiAuthService.
 * This controller is kept for backward-compatible /api/oauth/* clients.
 */
class OAuth2Controller extends BaseApiController
{
    protected $modelName = 'App\Models\EmployeeModel';
    protected $format = 'json';

    protected OAuth2Service $oauth2Service;
    protected ApiAuthService $apiAuthService;
    protected RateLimitService $rateLimitService;

    public function __construct()
    {
        parent::__construct();
        $this->oauth2Service = Services::oauth2Service();
        $this->apiAuthService = Services::apiAuthService();
        $this->rateLimitService = Services::rateLimitService();
    }

    public function token()
    {
        $rateLimitFailure = $this->applyTokenRateLimit();
        if ($rateLimitFailure !== null) {
            return $rateLimitFailure;
        }

        return match ((string) $this->requestValue('grant_type', '')) {
            'password' => $this->passwordGrant(),
            'refresh_token' => $this->refreshTokenGrant(),
            default => $this->failValidationError('Unsupported grant_type'),
        };
    }

    protected function passwordGrant()
    {
        $credentials = $this->readPasswordGrantPayload();
        if ($credentials === null) {
            return $this->failValidationError('Missing username or password');
        }

        $result = $this->apiAuthService->login(
            $credentials['username'],
            $credentials['password'],
            $this->apiAuthService->resolveScopes($credentials['scope'])
        );

        if (! ($result['success'] ?? false)) {
            return $this->failStandard($result['code'] ?? 'authentication_failed', $result['message'] ?? 'Authentication failed', (int) ($result['status'] ?? ResponseInterface::HTTP_UNAUTHORIZED));
        }

        return $this->respondStandard($this->formatTokenResponse($result['tokens']), 'Token emitido com sucesso.', 200, 'oauth_token_issued');
    }

    protected function refreshTokenGrant()
    {
        $refreshToken = (string) $this->requestValue('refresh_token', '');
        if ($refreshToken === '') {
            return $this->failValidationError('Missing refresh_token');
        }

        $tokens = $this->apiAuthService->refresh($refreshToken, OAuth2Service::generateDeviceFingerprint());
        if ($tokens === null) {
            return $this->failUnauthorized('Invalid or expired refresh token');
        }

        return $this->respondStandard($this->formatTokenResponse($tokens), 'Token renovado com sucesso.', 200, 'oauth_token_refreshed');
    }

    public function refresh()
    {
        return $this->refreshTokenGrant();
    }

    public function revoke()
    {
        $accessToken = $this->extractBearerToken();
        if ($accessToken === null) {
            return $this->failUnauthorized('Missing bearer token');
        }

        $tokenData = $this->oauth2Service->validateAccessToken($accessToken);
        if (! $tokenData) {
            return $this->failUnauthorized('Invalid token');
        }

        $revoked = $this->oauth2Service->revokeAccessToken($tokenData['id']);
        if (! $revoked) {
            return $this->failServerError('Failed to revoke token');
        }

        log_message('info', "OAuth access token revoked: {$tokenData['id']}");

        return $this->respond([
            'success' => true,
            'message' => 'Token revoked successfully',
        ]);
    }

    public function listTokens()
    {
        $employeeId = $this->resolveAuthenticatedEmployeeId();
        if ($employeeId === null) {
            return $this->failUnauthorized('Authentication required');
        }

        return $this->respond([
            'tokens' => $this->oauth2Service->getActiveTokens($employeeId),
        ]);
    }

    public function revokeAll()
    {
        $employeeId = $this->resolveAuthenticatedEmployeeId();
        if ($employeeId === null) {
            return $this->failUnauthorized('Authentication required');
        }

        $revoked = $this->oauth2Service->revokeAllTokens($employeeId);
        if (! $revoked) {
            return $this->failServerError('Failed to revoke active tokens');
        }

        log_message('info', "OAuth access tokens revoked for employee: {$employeeId}");

        return $this->respond([
            'success' => true,
            'message' => 'All active tokens revoked successfully',
        ]);
    }


    protected function applyTokenRateLimit()
    {
        $ip = $this->request->getIPAddress();
        $limitInfo = $this->rateLimitService->attempt('oauth_token:' . $ip, 'oauth_token', $ip);

        if ($limitInfo['allowed']) {
            return null;
        }

        return $this->failTooManyRequests($this->rateLimitService->getErrorMessage($limitInfo));
    }

    protected function readPasswordGrantPayload(): ?array
    {
        $username = trim((string) $this->requestValue('username', ''));
        $password = (string) $this->requestValue('password', '');
        $scope = $this->requestValue('scope');

        if ($username === '' || $password === '') {
            return null;
        }

        return [
            'username' => $username,
            'password' => $password,
            'scope' => is_string($scope) ? $scope : null,
        ];
    }

    protected function formatTokenResponse(array $tokens): array
    {
        return [
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'token_type' => 'Bearer',
            'expires_in' => $tokens['expires_in'] ?? 3600,
            'scope' => implode(' ', $tokens['scopes'] ?? ['api.read', 'api.write']),
        ];
    }

    protected function extractBearerToken(): ?string
    {
        $header = trim((string) $this->request->getHeaderLine('Authorization'));
        if ($header === '' || stripos($header, 'Bearer ') !== 0) {
            return null;
        }

        $token = trim(substr($header, 7));

        return $token !== '' ? $token : null;
    }

    protected function resolveAuthenticatedEmployeeId(): ?int
    {
        $contextEmployeeId = service('apiRequestAuthContext')->getEmployeeId();
        if ($contextEmployeeId !== null) {
            return $contextEmployeeId;
        }

        $accessToken = $this->extractBearerToken();
        if ($accessToken === null) {
            return null;
        }

        $tokenData = $this->oauth2Service->validateAccessToken($accessToken, OAuth2Service::generateDeviceFingerprint());

        return isset($tokenData['employee_id']) ? (int) $tokenData['employee_id'] : null;
    }
}
