<?php

namespace App\Services\Contracts;

/**
 * MELHORIA 1: Interface contrato para autenticação.
 */
interface AuthServiceInterface
{
    /**
     * Autentica um funcionário com email e senha.
     *
     * @return array{success:bool, user?:object, tokens?:array, message?:string, status?:int}
     */
    public function login(string $email, string $password, array $scopes = []): array;

    /**
     * Encerra a sessão de um funcionário.
     */
    public function logout(int $employeeId, int $accessTokenId, string $employeeName): void;

    /**
     * Renova o access token usando um refresh token válido.
     *
     * @return array|null null se o refresh token for inválido
     */
    public function refresh(string $refreshToken, string $deviceFingerprint): ?array;

    /**
     * Valida um access token Bearer e retorna os dados do token.
     *
     * @return array|null null se inválido ou expirado
     */
    public function validateToken(string $accessToken, ?string $deviceFingerprint = null): ?array;
}
