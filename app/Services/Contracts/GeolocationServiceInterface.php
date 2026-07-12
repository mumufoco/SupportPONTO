<?php

namespace App\Services\Contracts;

/**
 * MELHORIA 1: Interface contrato para validação de geolocalização.
 */
interface GeolocationServiceInterface
{
    /**
     * Valida se as coordenadas estão dentro de algum geofence ativo.
     *
     * @return array{valid:bool, error?:string, geofence_id?:int, geofence_name?:string, nearest_geofence?:array}
     */
    public function validateGeofence(float $latitude, float $longitude, ?float $accuracyMeters = null): array;

    /**
     * Valida se as coordenadas são geográficamente plausíveis.
     *
     * @return array{valid:bool, error?:string}
     */
    public function validateCoordinates(float $latitude, float $longitude): array;

    /**
     * Calcula distância em metros entre dois pontos geográficos (Haversine).
     */
    public function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float;

    /**
     * Retorna o geofence mais próximo das coordenadas fornecidas.
     *
     * @return array|null null se não houver geofences cadastrados
     */
    public function getNearestGeofence(float $latitude, float $longitude): ?array;
}
