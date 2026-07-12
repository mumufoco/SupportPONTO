<?php

namespace App\Events;

use App\Enums\PunchMethod;
use App\Enums\PunchType;

/**
 * MELHORIA 3: Domain Event — Ponto registrado.
 *
 * Disparado pelo PunchService sempre que um ponto é registrado com sucesso.
 * Listeners desacoplados reagem a este evento sem que o PunchService precise
 * conhecer nenhum deles.
 *
 * Benefício: adicionar integração futura (folha de pagamento, BI, ERP) requer
 * apenas criar um novo listener — sem tocar no PunchService.
 */
class TimePunchRegistered
{
    public function __construct(
        public readonly int         $employeeId,
        public readonly string      $employeeName,
        public readonly int         $punchId,
        public readonly int         $nsr,
        public readonly string      $punchTime,
        public readonly PunchType   $punchType,
        public readonly PunchMethod $method,
        public readonly ?float      $latitude,
        public readonly ?float      $longitude,
        public readonly bool        $withinGeofence,
        public readonly ?string     $geofenceName,
        public readonly ?float      $faceSimilarity,
        public readonly string      $ipAddress,
        public readonly string      $triggeredAt,
    ) {}

    /** Factory a partir do array de dados do punch registrado */
    public static function fromPunchData(object $employee, object $punch): self
    {
        return new self(
            employeeId:    (int)    $employee->id,
            employeeName:           (string) $employee->name,
            punchId:       (int)    $punch->id,
            nsr:           (int)    $punch->nsr,
            punchTime:              (string) $punch->punch_time,
            punchType:     PunchType::from((string) $punch->punch_type),
            method:        PunchMethod::from((string) $punch->method),
            latitude:      isset($punch->location_lat)  ? (float) $punch->location_lat  : null,
            longitude:     isset($punch->location_lng)  ? (float) $punch->location_lng  : null,
            withinGeofence:(bool)   ($punch->within_geofence ?? false),
            geofenceName:           isset($punch->geofence_name) ? (string) $punch->geofence_name : null,
            faceSimilarity:isset($punch->face_similarity) ? (float) $punch->face_similarity : null,
            ipAddress:              (string) ($punch->ip_address ?? ''),
            triggeredAt:            date('Y-m-d H:i:s'),
        );
    }
}
