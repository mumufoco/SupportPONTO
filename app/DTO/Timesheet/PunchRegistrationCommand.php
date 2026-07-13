<?php

namespace App\DTO\Timesheet;

use CodeIgniter\HTTP\RequestInterface;

final class PunchRegistrationCommand
{
    public function __construct(
        public readonly int $employeeId,
        public readonly string $punchType,
        public readonly string $method,
        public readonly ?string $punchTime = null,
        public readonly mixed $latitude = null,
        public readonly mixed $longitude = null,
        public readonly mixed $accuracy = null,
        public readonly ?string $ipAddress = null,
        public readonly ?string $userAgent = null,
        public readonly ?string $photo = null,
        public readonly array $additionalData = [],
        public readonly array $context = [],
        public readonly bool $skipSequenceValidation = false,
        public readonly bool $skipCooldownValidation = false,
        public readonly bool $requireGeofenceConfirmation = true,
        public readonly bool $confirmedOutsideGeofence = false,
        public readonly bool $holidayOverride = false,
        public readonly string $source = 'unknown',
    ) {
    }

    public static function fromRequest(
        int $employeeId,
        string $punchType,
        string $method,
        RequestInterface $request,
        array $additionalData = [],
        array $context = [],
        string $source = 'web'
    ): self {
        $jsonBody = $request->getJSON(true) ?? [];

        $latitude = $additionalData['location_lat']
            ?? $additionalData['latitude']
            ?? $request->getPost('location_lat')
            ?? $request->getPost('latitude')
            ?? $jsonBody['location_lat']
            ?? $jsonBody['latitude']
            ?? null;

        $longitude = $additionalData['location_lng']
            ?? $additionalData['longitude']
            ?? $request->getPost('location_lng')
            ?? $request->getPost('longitude')
            ?? $jsonBody['location_lng']
            ?? $jsonBody['longitude']
            ?? null;

        $accuracy = $additionalData['location_accuracy']
            ?? $additionalData['accuracy']
            ?? $request->getPost('location_accuracy')
            ?? $request->getPost('accuracy')
            ?? $jsonBody['location_accuracy']
            ?? $jsonBody['accuracy']
            ?? null;

        $confirmedOutsideGeofence = filter_var(
            $request->getPost('confirm_outside_geofence')
                ?? $jsonBody['confirm_outside_geofence']
                ?? false,
            FILTER_VALIDATE_BOOL
        );

        $holidayOverride = filter_var(
            $request->getPost('holiday_override')
                ?? $jsonBody['holiday_override']
                ?? false,
            FILTER_VALIDATE_BOOL
        );

        unset(
            $additionalData['latitude'],
            $additionalData['longitude'],
            $additionalData['location_lat'],
            $additionalData['location_lng'],
            $additionalData['accuracy'],
            $additionalData['location_accuracy']
        );

        return new self(
            employeeId: $employeeId,
            punchType: $punchType,
            method: $method,
            punchTime: $additionalData['punch_time'] ?? null,
            latitude: $latitude,
            longitude: $longitude,
            accuracy: $accuracy,
            ipAddress: $request->getIPAddress(),
            userAgent: (string) $request->getUserAgent()->getAgentString(),
            // Fallback para o corpo da requisição: o método facial já popula
            // additionalData['photo'] antes de chegar aqui, mas os demais métodos
            // (código/CPF/QR/biometria) enviam a foto da segunda camada de
            // verificação diretamente no payload da requisição.
            photo: $additionalData['photo'] ?? $request->getPost('photo') ?? $jsonBody['photo'] ?? null,
            additionalData: $additionalData,
            context: $context,
            confirmedOutsideGeofence: $confirmedOutsideGeofence,
            holidayOverride: $holidayOverride,
            source: $source,
        );
    }

    public function toAuditContext(): array
    {
        return [
            'employee_id' => $this->employeeId,
            'punch_type' => $this->punchType,
            'method' => $this->method,
            'source' => $this->source,
            'has_location' => $this->latitude !== null && $this->latitude !== '' && $this->longitude !== null && $this->longitude !== '',
            'skip_sequence_validation' => $this->skipSequenceValidation,
            'skip_cooldown_validation' => $this->skipCooldownValidation,
        ];
    }
}
