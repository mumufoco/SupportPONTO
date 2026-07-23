<?php

namespace App\Services\Timesheet;

use App\DTO\Timesheet\PunchRegistrationCommand;
use App\Enums\PunchMethod;
use App\Enums\PunchType;
use App\Events\TimePunchRegistered;
use App\Models\AuditModel;
use App\Models\EmployeeModel;
use App\Models\FacialFraudAlertModel;
use App\Models\HolidayModel;
use App\Models\SettingModel;
use App\Models\TimePunchModel;
use App\Services\Biometric\DeepFaceService;
use App\Services\GeolocationService;
use App\Services\Security\RateLimitService;
use CodeIgniter\Events\Events;

/**
 * Serviço canônico de marcação de ponto.
 *
 * Fase 9: web, API, QR Code, kiosk, facial e aprovações devem convergir para
 * este fluxo para evitar divergência de sequência, geofence, cooldown, NSR,
 * integridade, auditoria e payload de retorno.
 */
class TimesheetPunchRegistrationService
{
    public function __construct(
        private readonly TimePunchModel $timePunchModel = new TimePunchModel(),
        private readonly EmployeeModel $employeeModel = new EmployeeModel(),
        private readonly HolidayModel $holidayModel = new HolidayModel(),
        private readonly SettingModel $settingModel = new SettingModel(),
        private readonly AuditModel $auditModel = new AuditModel(),
        private readonly GeolocationService $geolocationService = new GeolocationService(),
        private readonly DeepFaceService $deepFaceService = new DeepFaceService(),
        private readonly RateLimitService $rateLimitService = new RateLimitService(),
        private readonly FacialFraudAlertModel $facialFraudAlertModel = new FacialFraudAlertModel(),
    ) {
    }

    public function register(PunchRegistrationCommand $command): array
    {
        try {
            if ($command->clientUuid !== null && $command->clientUuid !== '') {
                $idempotentResult = $this->idempotentResultFor($command->clientUuid);
                if ($idempotentResult !== null) {
                    return $idempotentResult;
                }
            }

            $employee = $this->employeeModel->find($command->employeeId);
            if (! $employee || ! ($employee->active ?? false)) {
                return $this->failure('Funcionário não encontrado ou inativo.', 404, ['employee' => 'inactive_or_missing']);
            }

            $resolvedPunchType = PunchType::tryFrom($this->timePunchModel->normalizePunchType($command->punchType));
            if (! $resolvedPunchType) {
                return $this->failure('Tipo de marcação inválido.', 400, ['punch_type' => 'invalid']);
            }

            $resolvedMethod = PunchMethod::tryFrom($command->method);
            if (! $resolvedMethod) {
                return $this->failure('Método de marcação inválido.', 400, ['method' => 'invalid']);
            }

            if (! $command->skipCooldownValidation && ! $this->timePunchModel->canPunch($command->employeeId)) {
                return $this->failure('Já existe uma marcação muito recente para este colaborador. Aguarde 1 minuto antes de tentar novamente.', 429, ['cooldown' => true]);
            }

            $punchDate = substr($command->punchTime ?: date('Y-m-d H:i:s'), 0, 10);
            $blockingHoliday = $this->holidayModel->getBlockingHolidayForDate($punchDate);
            if ($blockingHoliday !== null && ! $command->holidayOverride) {
                $typeName = \App\Models\HolidayModel::typeLabel($blockingHoliday->type ?? '');
                return $this->failure(
                    "Registro de ponto bloqueado: {$blockingHoliday->name} ({$typeName}). Contate o administrador para autorizar o ponto neste dia.",
                    403,
                    ['holiday_block' => true, 'holiday_name' => $blockingHoliday->name, 'holiday_type' => $blockingHoliday->type]
                );
            }

            if (! $command->skipSequenceValidation) {
                $referenceDate = substr($command->punchTime ?: date('Y-m-d H:i:s'), 0, 10);
                $expectedType = $this->timePunchModel->getNextPunchType($command->employeeId, $referenceDate);
                if ($resolvedPunchType !== $expectedType) {
                    return $this->failure(
                        sprintf('Sequência de ponto inválida. O próximo tipo esperado é %s.', mb_strtolower($expectedType->label())),
                        422,
                        [
                            'punch_type' => 'invalid_sequence',
                            'expected_punch_type' => $expectedType->value,
                            'expected_punch_type_label' => $expectedType->label(),
                            'received_punch_type' => $resolvedPunchType->value,
                        ]
                    );
                }
            }

            $locationValidation = $this->validateLocation($command);
            if (! ($locationValidation['success'] ?? false)) {
                return $locationValidation;
            }

            $additionalData = $command->additionalData;
            $fraudAlert = null;
            if ($resolvedMethod === PunchMethod::Facial) {
                $faceResult = $this->validateFacialPunch($command);
                if (! ($faceResult['success'] ?? false)) {
                    return $faceResult;
                }

                if (isset($faceResult['similarity'])) {
                    $additionalData['face_similarity'] = $faceResult['similarity'];
                }
            } elseif ($this->settingModel->get('punch_require_face_second_factor', true)) {
                // Segunda camada de segurança contra fraude (empréstimo de código/CPF/QR,
                // ou leitor de digital comprometido): além do método principal já ter
                // identificado o funcionário, exige uma foto ao vivo comparada 1:1 com o
                // cadastro biométrico facial DESSE funcionário (verificação, não
                // identificação aberta — não é possível "cadastrar por cima" do rosto de
                // outra pessoa por esse caminho, ver validateFaceSecondFactor()).
                $secondFactor = $this->validateFaceSecondFactor($command);
                if (! ($secondFactor['success'] ?? false)) {
                    return $secondFactor;
                }

                if (isset($secondFactor['similarity'])) {
                    $additionalData['face_second_factor_similarity'] = $secondFactor['similarity'];
                }

                if ($secondFactor['fraud_suspected'] ?? false) {
                    // Não entra em $additionalData: é um sinal de controle interno, não
                    // uma coluna de time_punches. Registrado à parte em facial_fraud_alerts
                    // depois que o ponto for gravado com sucesso (precisa do punch_id).
                    $fraudAlert = [
                        'similarity_score' => $secondFactor['similarity'] ?? null,
                        'threshold_used' => $secondFactor['threshold'] ?? null,
                        'reason' => $secondFactor['fraud_reason'] ?? 'mismatch',
                    ];
                }
            }

            $punchData = array_merge([
                'employee_id' => $command->employeeId,
                'punch_time' => $command->punchTime ?: date('Y-m-d H:i:s'),
                'punch_type' => $resolvedPunchType->value,
                'method' => $resolvedMethod->value,
                'location_lat' => $this->normalizeNullableNumber($command->latitude),
                'location_lng' => $this->normalizeNullableNumber($command->longitude),
                'latitude' => $this->normalizeNullableNumber($command->latitude),
                'longitude' => $this->normalizeNullableNumber($command->longitude),
                'ip_address' => $command->ipAddress ?: (function_exists('get_client_ip') ? get_client_ip() : null),
                'user_agent' => $command->userAgent ?: (function_exists('get_user_agent') ? get_user_agent() : null),
                'client_uuid' => ($command->clientUuid !== '' ? $command->clientUuid : null),
            ], $additionalData);

            if ($command->accuracy !== null && $command->accuracy !== '') {
                $punchData['location_accuracy'] = $command->accuracy;
            }

            // CI4 4.6+ requer que todas as chaves do array sejam strings.
            // Garante que nenhuma chave numérica (ex: de array_merge com arrays
            // indexados ou callbacks que retornam listas) chegue ao query builder.
            $punchData = array_filter($punchData, static fn($k) => is_string($k), ARRAY_FILTER_USE_KEY);

            $punchId = $this->timePunchModel->insertWithIntegrityLock($punchData);
            $punch = $this->timePunchModel->find($punchId);
            if (! $punch) {
                return $this->failure('Registro criado, mas não foi possível carregar o comprovante.', 500);
            }

            $this->auditModel->log(
                $command->employeeId,
                $this->auditActionFor($command->source),
                'time_punches',
                $punchId,
                null,
                array_merge($command->toAuditContext(), [
                    'nsr' => $punch->nsr ?? null,
                    'contingency_mode' => $resolvedMethod->isContingencyMethod(),
                ]),
                "Ponto registrado via {$command->source}: {$resolvedPunchType->value} via {$resolvedMethod->value}",
                'info'
            );

            if ($fraudAlert !== null) {
                $this->recordFraudAlert($command, $resolvedMethod, $punchId, $fraudAlert);
            }

            $this->triggerPunchRegisteredEvent($employee, $punch);

            return [
                'success' => true,
                'status' => 201,
                'message' => 'Ponto registrado com sucesso!',
                'punch' => $punch,
                'employee' => $employee,
                'data' => [
                    'id' => $punch->id,
                    'punch_id' => $punch->id,
                    'nsr' => $punch->nsr ?? null,
                    'punch_time' => function_exists('format_datetime_br') ? format_datetime_br($punch->punch_time) : (string) $punch->punch_time,
                    'punch_type' => $punch->punch_type,
                    'punch_type_label' => $resolvedPunchType->label(),
                    'method' => $punch->method,
                    'hash' => $punch->hash ?? null,
                    'chain_hash' => $punch->chain_hash ?? null,
                    'contingency_mode' => $resolvedMethod->isContingencyMethod(),
                ],
            ];
        } catch (\Throwable $e) {
            log_message('error', 'TimesheetPunchRegistrationService::register failed: ' . $e->getMessage(), $command->toAuditContext());
            return $this->failure('Erro interno ao registrar ponto.', 500);
        }
    }

    /**
     * Sincronização offline (PWA): se este client_uuid já foi processado
     * (marcação gravada ou pendência criada), devolve o mesmo resultado em vez
     * de reprocessar — protege contra duplicidade quando o dispositivo reenvia
     * por timeout/queda de conexão sem ter recebido a resposta anterior.
     */
    private function idempotentResultFor(string $clientUuid): ?array
    {
        $existingPunch = $this->timePunchModel->findByClientUuid($clientUuid);
        if ($existingPunch !== null) {
            return [
                'success' => true,
                'status' => 200,
                'message' => 'Ponto já havia sido registrado anteriormente.',
                'punch' => $existingPunch,
                'data' => [
                    'id' => $existingPunch->id,
                    'punch_id' => $existingPunch->id,
                    'nsr' => $existingPunch->nsr ?? null,
                    'punch_time' => function_exists('format_datetime_br') ? format_datetime_br($existingPunch->punch_time) : (string) $existingPunch->punch_time,
                    'punch_type' => $existingPunch->punch_type,
                    'method' => $existingPunch->method ?? null,
                    'hash' => $existingPunch->hash ?? null,
                    'chain_hash' => $existingPunch->chain_hash ?? null,
                    'already_processed' => true,
                ],
            ];
        }

        $pendingModel = new \App\Models\PendingPunchModel();
        $existingPending = $pendingModel->findByClientUuid($clientUuid);
        if ($existingPending !== null) {
            return [
                'success' => true,
                'status' => 202,
                'message' => 'Esta marcação já está pendente de aprovação do gestor.',
                'data' => [
                    'pending_id' => $existingPending->id,
                    'pending_review' => true,
                    'already_processed' => true,
                ],
            ];
        }

        return null;
    }

    private function validateLocation(PunchRegistrationCommand $command): array
    {
        $hasLatitude = $command->latitude !== null && $command->latitude !== '';
        $hasLongitude = $command->longitude !== null && $command->longitude !== '';

        if ($hasLatitude xor $hasLongitude) {
            return $this->failure('Latitude e longitude devem ser informadas juntas.', 400, ['location' => 'partial']);
        }

        if ($this->settingModel->get('require_geolocation', false) && (! $hasLatitude || ! $hasLongitude)) {
            return $this->failure('Para registrar o ponto neste fluxo, permita o acesso à sua localização e tente novamente.', 400, ['location' => 'required']);
        }

        if ($hasLatitude && $hasLongitude) {
            $accuracyMeters = ($command->accuracy !== null && $command->accuracy !== '') ? (float) $command->accuracy : null;
            $geofenceResult = $this->geolocationService->validateGeofence((float) $command->latitude, (float) $command->longitude, $accuracyMeters);
            $isValid = $geofenceResult['valid'] ?? ($geofenceResult['geofence_matched'] ?? true);

            if (! $isValid) {
                $distance = $geofenceResult['nearest_geofence']['distance_meters'] ?? null;
                $this->auditModel->log(
                    $command->employeeId,
                    'PUNCH_OUTSIDE_GEOFENCE',
                    'time_punches',
                    null,
                    null,
                    ['location' => ['lat' => $command->latitude, 'lng' => $command->longitude], 'distance' => $distance, 'source' => $command->source],
                    "Tentativa de registro fora da cerca virtual (distância: {$distance}m)",
                    'warning'
                );

                if ($this->settingModel->get('require_geofence', false) && $command->requireGeofenceConfirmation && ! $command->confirmedOutsideGeofence) {
                    return $this->failure(
                        $geofenceResult['error'] ?? 'Sua localização está fora da área configurada para esta marcação.',
                        403,
                        [
                            'outside_geofence' => true,
                            'distance' => $distance,
                            'nearest_geofence' => $geofenceResult['nearest_geofence']['name'] ?? 'Desconhecida',
                            'require_confirmation' => true,
                        ]
                    );
                }
            }
        }

        return ['success' => true];
    }

    private function validateFacialPunch(PunchRegistrationCommand $command): array
    {
        if (! $command->photo) {
            return $this->failure('Foto é obrigatória para registro facial.', 400, ['photo' => 'required']);
        }

        // ALTO-06 (auditoria): api/v1/time-punch atende todos os métodos de ponto (código,
        // QR, facial), então gatear a rota inteira pelo bucket 'biometric' (mais restrito)
        // penalizaria funcionários usando código/QR no mesmo IP/NAT. Em vez disso,
        // aplicamos aqui, especificamente no caminho facial, o mesmo bucket 'biometric'
        // (10 tentativas/min) já existente em RateLimitPolicyService — chaveado por
        // funcionário, não só por IP, para conter tentativas repetidas de burlar o
        // reconhecimento com fotos/telas contra um único colaborador.
        $limitInfo = $this->rateLimitService->attempt(
            'facial_punch_' . $command->employeeId,
            'biometric',
            $this->rateLimitService->getClientIp()
        );
        if (! ($limitInfo['allowed'] ?? true)) {
            return $this->failure('Muitas tentativas de reconhecimento facial. Aguarde antes de tentar novamente.', 429, ['biometric_rate_limited' => true]);
        }

        $recognition = $this->deepFaceService->recognizeFace((string) $command->photo);
        if (! ($recognition['success'] ?? false) || ! ($recognition['recognized'] ?? false)) {
            return $this->failure('Rosto não reconhecido.', 400, ['face' => 'not_recognized']);
        }

        if ((int) ($recognition['employee_id'] ?? 0) !== $command->employeeId) {
            return $this->failure('A foto não corresponde ao funcionário autenticado.', 403, ['face' => 'employee_mismatch']);
        }

        return ['success' => true, 'similarity' => $recognition['similarity'] ?? null];
    }

    /**
     * Segunda camada de verificação facial para os métodos não-biométricos
     * (código, CPF, QR) e para a biometria digital: confirma 1:1 que a pessoa
     * na foto é o próprio funcionário já identificado pelo método principal,
     * usando o cadastro biométrico facial dele (FaceRecognitionService::
     * verifyFace — comparação 1:1 contra UM cadastro específico, diferente do
     * método "facial" puro, que faz reconhecimento aberto 1:N para descobrir
     * quem é a pessoa).
     *
     * Importante para segurança: não existe caminho aqui para "cadastrar" ou
     * sobrescrever o rosto de outro funcionário — o link de autocadastro
     * devolvido no caso "sem cadastro" só permite que a PRÓPRIA pessoa logada
     * cadastre o PRÓPRIO rosto (ver FaceRecognitionController::enroll()), então
     * uma tentativa de fraude com CPF/código de outra pessoa não consegue usar
     * esse fluxo para validar-se como a vítima.
     */
    private function validateFaceSecondFactor(PunchRegistrationCommand $command): array
    {
        // Falha técnica de captura (sem câmera, permissão negada, app antigo em
        // cache, etc.): não há como distinguir isso, no backend, de uma tentativa
        // deliberada de pular a verificação — então, em vez de travar o
        // colaborador (o que impedia QUALQUER registro de ponto quando a foto
        // não chegava), libera o ponto e gera um alerta para revisão de
        // gestor/RH, mesmo tratamento dado ao caso de "foto não bate".
        if (! $command->photo) {
            return [
                'success' => true,
                'fraud_suspected' => true,
                'fraud_reason' => 'no_photo',
            ];
        }

        $limitInfo = $this->rateLimitService->attempt(
            'facial_punch_' . $command->employeeId,
            'biometric',
            $this->rateLimitService->getClientIp()
        );
        if (! ($limitInfo['allowed'] ?? true)) {
            return $this->failure('Muitas tentativas de verificação facial. Aguarde antes de tentar novamente.', 429, ['biometric_rate_limited' => true]);
        }

        try {
            $result = $this->deepFaceService->verifyFace($command->employeeId, (string) $command->photo);
        } catch (\Throwable $e) {
            // Indisponibilidade do serviço DeepFace (rede, timeout, fora do ar):
            // é uma falha de infraestrutura, não um sinal de fraude — não pode
            // derrubar o registro de ponto da empresa inteira enquanto o
            // serviço estiver instável. Libera e sinaliza para revisão.
            log_message('error', 'Face second-factor verification failed: ' . $e->getMessage());
            return [
                'success' => true,
                'fraud_suspected' => true,
                'fraud_reason' => 'service_error',
            ];
        }

        if (! ($result['success'] ?? false)) {
            $error = (string) ($result['error'] ?? '');
            if (str_contains($error, 'não possui cadastro facial')) {
                return $this->failure(
                    'Você ainda não possui cadastro de biometria facial. Cadastre para continuar usando este método com segurança.',
                    403,
                    ['face_second_factor' => 'no_enrollment', 'enroll_url' => site_url('minha-biometria')]
                );
            }

            // Erro retornado pelo próprio DeepFace (não uma exceção de rede) —
            // mesma lógica: infraestrutura, não fraude, não pode travar o ponto.
            return [
                'success' => true,
                'fraud_suspected' => true,
                'fraud_reason' => 'service_error',
            ];
        }

        if (! ($result['verified'] ?? false)) {
            // Mudança de comportamento a pedido: NÃO bloqueia o registro. A foto não
            // corresponder ao cadastro biométrico não impede a marcação (o funcionário
            // já foi identificado pelo método principal) — em vez disso, o ponto é
            // registrado normalmente e um alerta de possível fraude fica registrado
            // para revisão de gestor/RH (ver facial_fraud_alerts / register()).
            return [
                'success' => true,
                'similarity' => $result['similarity'] ?? null,
                'threshold' => $result['threshold'] ?? null,
                'fraud_suspected' => true,
                'fraud_reason' => 'mismatch',
            ];
        }

        return ['success' => true, 'similarity' => $result['similarity'] ?? null, 'threshold' => $result['threshold'] ?? null];
    }

    /**
     * Registra o alerta de possível fraude sem interromper o registro do ponto
     * (o ponto já foi gravado com sucesso a esta altura). Falha ao gravar o
     * alerta é apenas logada — não pode reverter ou impedir a marcação já
     * concluída.
     */
    private function recordFraudAlert(PunchRegistrationCommand $command, PunchMethod $resolvedMethod, int $punchId, array $fraudAlert): void
    {
        $reason = $fraudAlert['reason'] ?? 'mismatch';

        try {
            $this->facialFraudAlertModel->record([
                'employee_id' => $command->employeeId,
                'time_punch_id' => $punchId,
                'method' => $resolvedMethod->value,
                'reason' => $reason,
                'similarity_score' => $fraudAlert['similarity_score'],
                'threshold_used' => $fraudAlert['threshold_used'],
                'ip_address' => $command->ipAddress ?: (function_exists('get_client_ip') ? get_client_ip() : null),
                'user_agent' => $command->userAgent ?: (function_exists('get_user_agent') ? get_user_agent() : null),
            ]);

            $description = match ($reason) {
                'no_photo' => 'Verificação facial não recebeu foto (possível falha técnica de câmera) — ponto registrado normalmente, alerta gerado para revisão.',
                'service_error' => 'Serviço de verificação facial indisponível no momento do registro — ponto registrado normalmente, alerta gerado para revisão.',
                default => 'Foto de verificação facial não corresponde ao cadastro biométrico — ponto registrado normalmente, alerta gerado para revisão.',
            };

            $this->auditModel->log(
                $command->employeeId,
                'FACIAL_FRAUD_SUSPECTED',
                'time_punches',
                $punchId,
                null,
                [
                    'method' => $resolvedMethod->value,
                    'reason' => $reason,
                    'similarity' => $fraudAlert['similarity_score'],
                    'threshold' => $fraudAlert['threshold_used'],
                ],
                $description,
                'warning'
            );
        } catch (\Throwable $e) {
            log_message('error', 'Falha ao registrar alerta de fraude facial: ' . $e->getMessage());
        }
    }

    private function failure(string $message, int $status, ?array $errors = null): array
    {
        return [
            'success' => false,
            'status' => $status,
            'message' => $message,
            'errors' => $errors,
        ];
    }

    private function normalizeNullableNumber(mixed $value): mixed
    {
        return ($value === '' || $value === null) ? null : $value;
    }

    private function auditActionFor(string $source): string
    {
        return match ($source) {
            'api' => 'PUNCH_REGISTERED_API',
            'qrcode' => 'PUNCH_REGISTERED_QRCODE',
            'pending_approval' => 'PUNCH_REGISTERED_PENDING_APPROVAL',
            default => 'PUNCH_REGISTERED',
        };
    }

    private function triggerPunchRegisteredEvent(object $employee, object $punch): void
    {
        try {
            Events::trigger('timePunchRegistered', TimePunchRegistered::fromPunchData($employee, $punch));
        } catch (\Throwable $e) {
            log_message('warning', '[TimesheetPunchRegistrationService] Falha ao disparar evento timePunchRegistered: ' . $e->getMessage());
        }
    }
}
