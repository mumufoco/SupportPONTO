<?php

namespace App\Services\Timesheet;

use App\DTO\Timesheet\PunchRegistrationCommand;
use App\Enums\PunchMethod;
use App\Enums\PunchType;
use App\Events\TimePunchRegistered;
use App\Models\AuditModel;
use App\Models\EmployeeModel;
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
    ) {
    }

    public function register(PunchRegistrationCommand $command): array
    {
        try {
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
            if ($resolvedMethod === PunchMethod::Facial) {
                $faceResult = $this->validateFacialPunch($command);
                if (! ($faceResult['success'] ?? false)) {
                    return $faceResult;
                }

                if (isset($faceResult['similarity'])) {
                    $additionalData['face_similarity'] = $faceResult['similarity'];
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
