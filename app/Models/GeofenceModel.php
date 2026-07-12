<?php

namespace App\Models;

use CodeIgniter\Model;

class GeofenceModel extends Model
{
    protected $table            = 'geofences';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'name',
        'description',
        'center_lat',
        'center_lng',
        'radius_meters',
        'address',
        'active',
        'color',
        'created_by',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules = [
        'name'          => 'required|min_length[3]|max_length[100]',
        'center_lat'    => 'required|decimal',
        'center_lng'    => 'required|decimal',
        'radius_meters' => 'required|integer|greater_than[0]',
    ];

    // Callbacks
    protected $afterFind = ['castBooleans'];

    protected $validationMessages = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    /**
     * Normalize PostgreSQL boolean strings ('t'/'f') to PHP booleans after every find.
     */
    protected function castBooleans(array $data): array
    {
        if (empty($data['data'])) {
            return $data;
        }

        $rows = $data['singleton'] ? [$data['data']] : $data['data'];

        foreach ($rows as $row) {
            if (is_object($row)) {
                $row->active = in_array($row->active, [true, 't', '1', 1, 'true'], true);
            }
        }

        return $data;
    }

    /**
     * Get active geofences
     */
    public function getActive(): array
    {
        return $this->where('active', true)->findAll();
    }

    /**
     * Check if coordinates are within any active geofence
     */
    public function checkPoint(float $lat, float $lng): ?object
    {
        $geofences = $this->getActive();

        foreach ($geofences as $geofence) {
            if ($this->isWithinGeofence($lat, $lng, $geofence)) {
                return $geofence;
            }
        }

        return null;
    }

    /**
     * Check if point is within specific geofence using Haversine formula
     */
    public function isWithinGeofence(float $lat, float $lng, object $geofence): bool
    {
        $earthRadius = 6371000; // Earth radius in meters

        $latFrom = deg2rad($geofence->center_lat);
        $lngFrom = deg2rad($geofence->center_lng);
        $latTo = deg2rad($lat);
        $lngTo = deg2rad($lng);

        $latDelta = $latTo - $latFrom;
        $lngDelta = $lngTo - $lngFrom;

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos($latFrom) * cos($latTo) *
             sin($lngDelta / 2) * sin($lngDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        $distance = $earthRadius * $c;

        return $distance <= $geofence->radius_meters;
    }

    /**
     * Calculate distance between two points
     */
    public function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000; // in meters

        $latFrom = deg2rad($lat1);
        $lngFrom = deg2rad($lng1);
        $latTo = deg2rad($lat2);
        $lngTo = deg2rad($lng2);

        $latDelta = $latTo - $latFrom;
        $lngDelta = $lngTo - $lngFrom;

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos($latFrom) * cos($latTo) *
             sin($lngDelta / 2) * sin($lngDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
