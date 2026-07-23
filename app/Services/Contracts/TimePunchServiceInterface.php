<?php

namespace App\Services\Contracts;

/**
 * MELHORIA 1: Interface contrato para o serviço de registro de ponto.
 *
 * Define o contrato público do serviço núcleo de ponto eletrônico.
 * Qualquer implementação (real, mock, stub) deve satisfazer este contrato.
 *
 * Benefícios:
 * - Testes unitários usam mocks reais (não reflexão de classe)
 * - Troca de implementação (ex: novo motor de geolocalização) sem alterar controllers
 * - PHPStan verifica todos os callers contra o contrato
 */
interface TimePunchServiceInterface
{
    /**
     * Registra um ponto eletrônico para o colaborador.
     *
     * @param  object      $employee  Objeto do colaborador autenticado
     * @param  string      $punchType Tipo: entrada|saida|intervalo_inicio|intervalo_fim
     * @param  string      $method    Método: codigo|cpf|qrcode|facial|biometria
     * @param  float|null  $latitude  Coordenada GPS (opcional)
     * @param  float|null  $longitude Coordenada GPS (opcional)
     * @param  string|null $photo     Foto base64 para reconhecimento facial (opcional)
     * @return array{success:bool, status:int, message:string, data?:array}
     */
    public function registerPunch(
        object  $employee,
        string  $punchType,
        string  $method,
        ?float  $latitude  = null,
        ?float  $longitude = null,
        ?string $photo     = null
    ): array;

    /**
     * Retorna os registros de ponto de um colaborador em uma data específica.
     *
     * @param  int    $employeeId
     * @param  string $date       Formato Y-m-d
     * @return array<object>
     */
    public function getPunchesByDate(int $employeeId, string $date): array;

    /**
     * Valida se o colaborador pode registrar ponto agora (anti-duplicate).
     *
     * @param  int $employeeId
     * @return bool true se pode registrar
     */
    public function canPunchNow(int $employeeId): bool;

    /**
     * Retorna o próximo tipo de ponto esperado para o colaborador.
     *
     * @param  int         $employeeId
     * @param  string|null $date
     * @return string      entrada|saida|intervalo_inicio|intervalo_fim
     */
    public function getNextPunchType(int $employeeId, ?string $date = null): string;
}
