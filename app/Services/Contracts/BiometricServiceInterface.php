<?php

namespace App\Services\Contracts;

/**
 * MELHORIA 1: Interface contrato para reconhecimento biométrico.
 *
 * Abstrai o motor de biometria (DeepFace, SourceAFIS ou outro).
 * Trocar de motor não exige alterar controllers ou services de ponto.
 */
interface BiometricServiceInterface
{
    /**
     * Cadastra a face de um funcionário no sistema biométrico.
     *
     * @param  int    $employeeId
     * @param  string $imageBase64 Foto em base64
     * @return array{success:bool, message?:string, data?:array}
     */
    public function enrollFace(int $employeeId, string $imageBase64): array;

    /**
     * Reconhece um funcionário a partir de uma foto.
     *
     * @param  string $imageBase64 Foto em base64
     * @return array{success:bool, recognized:bool, employee_id?:int, similarity?:float, message?:string}
     */
    public function recognizeFace(string $imageBase64): array;

    /**
     * Verifica se uma foto pertence ao funcionário esperado (verificação 1:1).
     *
     * @return array{success:bool, match:bool, similarity?:float}
     */
    public function verifyFace(int $employeeId, string $imageBase64): array;

    /**
     * Remove todos os templates biométricos de um funcionário.
     */
    public function deleteTemplates(int $employeeId): bool;

    /**
     * Verifica se o serviço biométrico está operacional.
     */
    public function isAvailable(): bool;
}
