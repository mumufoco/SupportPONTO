<?php

namespace App\Services\Timesheet;

/**
 * Detecta falhas técnicas objetivas nos métodos de ponto e determina
 * se a condição para abertura de justificativa foi atingida.
 *
 * Distinção fundamental:
 * - FALHA TÉCNICA: serviço inacessível, timeout, câmera inacessível → justificativa permitida
 * - FALHA DE AUTENTICAÇÃO: rosto não reconhece, digital não bate → NÃO permite justificativa
 */
class PunchFailureDetector
{
    /** Tipos de erro que caracterizam falha TÉCNICA (sistema falhou) */
    private const TECHNICAL_ERROR_CODES = [
        'service_unavailable',
        'deepface_timeout',
        'deepface_unavailable',
        'sourceafis_timeout',
        'camera_inaccessible',
        'camera_permission_denied',
        'network_timeout',
        'database_error',
        'circuit_breaker_open',
        'qr_camera_unavailable',
    ];

    /** Tipos de erro que caracterizam falha de AUTENTICAÇÃO (sistema funcionou, pessoa não reconhecida) */
    private const AUTH_ERROR_CODES = [
        'face_not_recognized',
        'similarity_below_threshold',
        'fingerprint_not_matched',
        'employee_not_found',
        'employee_inactive',
        'qr_expired',
        'cpf_invalid',
        'code_invalid',
    ];

    /**
     * Avalia se a condição para abrir justificativa foi atingida.
     *
     * @param array $attemptLog Array de tentativas: [['method'=>'facial','error_code'=>'deepface_timeout','timestamp'=>'...'], ...]
     * @param array $enabledMethods Métodos habilitados na unidade
     * @param int $existingPendingCount Quantidade de pendências abertas pelo colaborador neste mês
     * @param int $maxPendingPerMonth Limite configurado
     */
    public function evaluate(
        array $attemptLog,
        array $enabledMethods,
        int $existingPendingCount = 0,
        int $maxPendingPerMonth = 3
    ): FailureEvaluationResult {

        if ($existingPendingCount >= $maxPendingPerMonth) {
            return FailureEvaluationResult::blocked(
                "Limite de {$maxPendingPerMonth} justificativas por mês atingido. Contate o RH.",
                $attemptLog
            );
        }

        $technicalFailures = [];
        $authFailures      = [];
        $methodsAttempted  = [];

        foreach ($attemptLog as $attempt) {
            $method    = (string) ($attempt['method'] ?? 'unknown');
            $errorCode = (string) ($attempt['error_code'] ?? '');

            $methodsAttempted[$method] = $errorCode;

            if (in_array($errorCode, self::TECHNICAL_ERROR_CODES, true)) {
                $technicalFailures[$method] = $attempt;
            } elseif (in_array($errorCode, self::AUTH_ERROR_CODES, true)) {
                $authFailures[$method] = $attempt;
            }
        }

        $availableCount        = count($enabledMethods);
        $techFailedMethods     = array_keys($technicalFailures);
        $notTechnicallyFailed  = array_diff($enabledMethods, $techFailedMethods);

        // Cenário 1: TODOS os métodos disponíveis falharam tecnicamente
        $allTechnicallyFailed = count($notTechnicallyFailed) === 0 && $availableCount > 0 && count($technicalFailures) > 0;

        // Cenário 2: Biometria falhou por auth + método alternativo com falha técnica
        // Ex: facial não reconheceu (auth) + QR timeout (técnico)
        $biometricAuthPlusAlternativeTechnical =
            !empty($authFailures) &&
            !empty($technicalFailures) &&
            count($techFailedMethods) >= $availableCount - 1;

        $justified = $allTechnicallyFailed || $biometricAuthPlusAlternativeTechnical;

        if (!$justified) {
            // Verificar se há métodos disponíveis que nem foram tentados
            $notAttempted = array_diff($enabledMethods, array_keys($methodsAttempted));
            if (!empty($notAttempted)) {
                return FailureEvaluationResult::blocked(
                    'Há métodos disponíveis que não foram tentados: ' . implode(', ', $notAttempted) . '. Tente todos os métodos disponíveis antes de solicitar justificativa.',
                    $attemptLog
                );
            }

            return FailureEvaluationResult::blocked(
                'As falhas identificadas não caracterizam falha técnica do sistema. Contate o suporte ou o RH.',
                $attemptLog
            );
        }

        return FailureEvaluationResult::justified(
            $technicalFailures,
            $authFailures,
            $methodsAttempted,
            $this->buildFailureSummary($technicalFailures)
        );
    }

    public function isTechnicalError(string $errorCode): bool
    {
        return in_array($errorCode, self::TECHNICAL_ERROR_CODES, true);
    }

    public function isAuthError(string $errorCode): bool
    {
        return in_array($errorCode, self::AUTH_ERROR_CODES, true);
    }

    private function buildFailureSummary(array $technicalFailures): array
    {
        $summary = [];
        $labels  = [
            'deepface_timeout'        => 'Reconhecimento facial — serviço sem resposta',
            'deepface_unavailable'    => 'Reconhecimento facial — serviço indisponível',
            'sourceafis_timeout'      => 'Biometria digital — serviço sem resposta',
            'camera_inaccessible'     => 'Câmera — hardware inacessível',
            'camera_permission_denied'=> 'Câmera — permissão negada',
            'qr_camera_unavailable'   => 'QR Code — câmera inacessível',
            'network_timeout'         => 'Erro de rede — timeout',
            'service_unavailable'     => 'Serviço indisponível',
            'circuit_breaker_open'    => 'Serviço temporariamente suspenso (circuit breaker ativo)',
        ];

        foreach ($technicalFailures as $method => $attempt) {
            $errorCode = (string) ($attempt['error_code'] ?? '');
            $summary[] = $labels[$errorCode] ?? ucfirst($method) . ' — ' . $errorCode;
        }

        return $summary;
    }
}

/**
 * Resultado da avaliação de falha, com semântica clara.
 */
class FailureEvaluationResult
{
    private function __construct(
        public readonly bool  $justified,
        public readonly bool  $blocked,
        public readonly string $blockReason,
        public readonly array  $technicalFailures,
        public readonly array  $authFailures,
        public readonly array  $methodsAttempted,
        public readonly array  $failureSummary,
        public readonly array  $attemptLog,
    ) {}

    public static function justified(
        array $technicalFailures,
        array $authFailures,
        array $methodsAttempted,
        array $failureSummary
    ): self {
        return new self(true, false, '', $technicalFailures, $authFailures, $methodsAttempted, $failureSummary, []);
    }

    public static function blocked(string $reason, array $attemptLog): self
    {
        return new self(false, true, $reason, [], [], [], [], $attemptLog);
    }
}
