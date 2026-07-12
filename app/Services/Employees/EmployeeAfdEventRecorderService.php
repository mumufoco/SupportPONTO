<?php

namespace App\Services\Employees;

use App\Models\EmployeeRecordEventModel;

/**
 * Ponte entre as operações de ciclo de vida do empregado (criação, alteração,
 * exclusão, ativação/desativação, aprovação de cadastro, aprovação de solicitação
 * de alteração) e o registro tipo "5" do AFD ("Inclusão/Alteração/Exclusão de
 * empregados no REP" — Portaria MTE 671/2021).
 *
 * REGRA DE OURO — esta classe NUNCA pode quebrar a operação principal de
 * empregados: cada método é inteiramente protegido por try/catch e jamais lança
 * exceções. Se faltar algum dado essencial (CPF do empregado, nome, CPF do
 * responsável) ou ocorrer qualquer erro de gravação, registra um aviso no log e
 * simplesmente NÃO grava o evento — a funcionalidade de RH é sempre prioritária
 * sobre o registro de conformidade do AFD (mesmo espírito defensivo de
 * `fetch*ForAfd` em TXTService, mas aplicado no momento da ESCRITA, não da leitura).
 *
 * Aceita o "empregado" tanto como array (ex.: `$payload`/`$result['data']` em
 * EmployeeRegistrationService) quanto como objeto (ex.: snapshot retornado por
 * EmployeeStatusService::find antes da mutação) — normaliza internamente.
 *
 * O "responsável" pode ser informado como objeto `$currentUser` (sessão), array,
 * ou simplesmente omitido — neste último caso tenta-se `sp_session_user_id()`
 * como fallback (ver EmployeeChangeRequestController::approve /
 * EmployeeControllerActionService::approveRegistration, que não recebem
 * `$currentUser` diretamente).
 */
class EmployeeAfdEventRecorderService
{
    public function __construct(private ?EmployeeRecordEventModel $eventModel = null)
    {
        $this->eventModel = $eventModel ?? new EmployeeRecordEventModel();
    }

    /**
     * Registra inclusão ("I") de empregado no REP.
     *
     * @param array|object $employee       Dados do empregado recém-criado (precisa expor cpf/name)
     * @param mixed        $responsible    Usuário responsável pela operação (objeto/array/null)
     */
    public function recordInclusion(array|object $employee, mixed $responsible = null): void
    {
        $this->record(EmployeeRecordEventModel::OPERATION_INCLUSION, $employee, $responsible);
    }

    /**
     * Registra alteração ("A") de cadastro de empregado no REP.
     *
     * @param array|object $employee       Dados do empregado após a alteração (precisa expor cpf/name)
     * @param mixed        $responsible    Usuário responsável pela operação (objeto/array/null)
     */
    public function recordAlteration(array|object $employee, mixed $responsible = null): void
    {
        $this->record(EmployeeRecordEventModel::OPERATION_ALTERATION, $employee, $responsible);
    }

    /**
     * Registra exclusão ("E") de empregado no REP.
     *
     * @param array|object $employee       Snapshot do empregado ANTES da exclusão (precisa expor cpf/name)
     * @param mixed        $responsible    Usuário responsável pela operação (objeto/array/null)
     */
    public function recordExclusion(array|object $employee, mixed $responsible = null): void
    {
        $this->record(EmployeeRecordEventModel::OPERATION_EXCLUSION, $employee, $responsible);
    }

    /**
     * Núcleo comum — normaliza dados, valida presença de CPF/nome/responsável,
     * grava o evento e NUNCA propaga exceções.
     */
    private function record(string $operationType, array|object $employee, mixed $responsible): void
    {
        try {
            $employeeCpf  = trim((string) $this->extract($employee, 'cpf'));
            $employeeName = trim((string) $this->extract($employee, 'name'));
            $responsibleCpf = trim((string) $this->resolveResponsibleCpf($responsible));

            if ($employeeCpf === '' || $employeeName === '') {
                log_message(
                    'warning',
                    "EmployeeAfdEventRecorder: operação '{$operationType}' ignorada — "
                    . 'CPF ou nome do empregado ausente. Registro tipo "5" do AFD NÃO foi gravado.'
                );

                return;
            }

            if ($responsibleCpf === '') {
                log_message(
                    'warning',
                    "EmployeeAfdEventRecorder: operação '{$operationType}' (empregado CPF={$employeeCpf}) ignorada — "
                    . 'CPF do responsável pela operação não pôde ser determinado. Registro tipo "5" do AFD NÃO foi gravado.'
                );

                return;
            }

            $this->eventModel->recordEvent(
                operationType: $operationType,
                employeeCpf: $employeeCpf,
                employeeName: $employeeName,
                responsibleCpf: $responsibleCpf,
                recordedAt: date('Y-m-d H:i:s')
            );
        } catch (\Throwable $e) {
            // Jamais deixar uma falha de registro de conformidade derrubar a
            // operação principal de RH — apenas loga e segue em frente.
            log_message(
                'error',
                "EmployeeAfdEventRecorder: falha ao gravar evento '{$operationType}' do AFD (tipo 5): " . $e->getMessage()
            );
        }
    }

    /**
     * Extrai um campo de um empregado representado como array ou objeto.
     */
    private function extract(array|object $employee, string $field): ?string
    {
        if (is_array($employee)) {
            return $employee[$field] ?? null;
        }

        return $employee->{$field} ?? null;
    }

    /**
     * Resolve o CPF do responsável pela operação a partir de diferentes formatos
     * possíveis: objeto de sessão (`$currentUser`), array, ou — na ausência de
     * ambos — fallback para `sp_session_user_id()` + busca do empregado logado.
     */
    private function resolveResponsibleCpf(mixed $responsible): ?string
    {
        if ($responsible !== null) {
            if (is_array($responsible) && isset($responsible['cpf'])) {
                return (string) $responsible['cpf'];
            }

            if (is_object($responsible) && isset($responsible->cpf)) {
                return (string) $responsible->cpf;
            }
        }

        // Fallback: deriva o CPF do responsável a partir do empregado atualmente
        // logado na sessão (usado nos pontos de injeção que não recebem
        // `$currentUser` diretamente — ex.: aprovação de cadastro/solicitação).
        try {
            helper('session_context');

            if (function_exists('sp_session_user_id')) {
                $sessionUserId = sp_session_user_id();

                if ($sessionUserId) {
                    $employeeModel = new \App\Models\EmployeeModel();
                    $sessionUser   = $employeeModel->find((int) $sessionUserId);

                    if ($sessionUser !== null && isset($sessionUser->cpf)) {
                        return (string) $sessionUser->cpf;
                    }
                }
            }
        } catch (\Throwable $e) {
            log_message('warning', 'EmployeeAfdEventRecorder: falha ao resolver CPF do responsável via sessão: ' . $e->getMessage());
        }

        return null;
    }
}
