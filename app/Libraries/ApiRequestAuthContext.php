<?php

namespace App\Libraries;

/**
 * Mantém o contexto autenticado da API no ciclo de vida da requisição atual,
 * sem depender de sessão PHP para endpoints stateless.
 */
class ApiRequestAuthContext
{
    private ?int $employeeId = null;
    private ?array $tokenData = null;
    private ?string $deviceFingerprint = null;
    private ?string $accessToken = null;

    public function setContext(array $tokenData, ?string $deviceFingerprint = null, ?string $accessToken = null): void
    {
        $this->tokenData = $tokenData;
        $this->employeeId = isset($tokenData['employee_id']) ? (int) $tokenData['employee_id'] : null;
        $this->deviceFingerprint = $deviceFingerprint;
        $this->accessToken = $accessToken;
    }

    public function clear(): void
    {
        $this->employeeId = null;
        $this->tokenData = null;
        $this->deviceFingerprint = null;
        $this->accessToken = null;
    }

    public function hasContext(): bool
    {
        return $this->tokenData !== null;
    }

    public function getEmployeeId(): ?int
    {
        return $this->employeeId;
    }

    public function getTokenData(): ?array
    {
        return $this->tokenData;
    }

    public function getDeviceFingerprint(): ?string
    {
        return $this->deviceFingerprint;
    }

    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }
}
