<?php

namespace App\Services;

use App\Models\AuditModel;
use App\Models\EmployeeModel;
use App\Models\GeofenceModel;

class GeofenceManagementService
{
    private EmployeeModel $employeeModel;
    private GeofenceModel $geofenceModel;
    private AuditModel $auditModel;
    private GeolocationService $geolocationService;

    public function __construct()
    {
        $this->employeeModel = new EmployeeModel();
        $this->geofenceModel = new GeofenceModel();
        $this->auditModel = new AuditModel();
        $this->geolocationService = new GeolocationService();
    }

    public function authenticatedEmployeeBySession(): ?array
    {
        if (!session()->has('user_id')) {
            return null;
        }

        $employee = $this->employeeModel->find((int) session()->get('user_id'));
        if (!$employee) {
            return null;
        }

        return [
            'id' => $employee->id,
            'name' => $employee->name,
            'email' => $employee->email,
            'role' => $employee->role,
            'department' => $employee->department,
        ];
    }

    public function allGeofences(): array
    {
        return $this->geofenceModel->orderBy('created_at', 'DESC')->findAll();
    }

    public function findGeofence(int $id): mixed
    {
        return $this->geofenceModel->find($id);
    }

    public function createGeofence(array $employee, array $data): array
    {
        $data['created_by'] = $employee['id'];
        $geofenceId = $this->geofenceModel->insert($data);
        if (!$geofenceId) {
            return ['success' => false, 'message' => 'Erro ao criar geofence.'];
        }

        $this->auditModel->log(
            $employee['id'],
            'GEOFENCE_CREATED',
            'geofences',
            $geofenceId,
            null,
            $data,
            "Geofence '{$data['name']}' criado",
            'info'
        );

        return ['success' => true, 'id' => $geofenceId];
    }

    public function updateGeofence(array $employee, int $id, array $newData): array
    {
        $geofence = $this->geofenceModel->find($id);
        if (!$geofence) {
            return ['success' => false, 'message' => 'Geofence não encontrado.'];
        }

        $oldData = (array) $geofence;
        $this->geofenceModel->update($id, $newData);

        $this->auditModel->log(
            $employee['id'],
            'GEOFENCE_UPDATED',
            'geofences',
            $id,
            $oldData,
            $newData,
            "Geofence '{$newData['name']}' atualizado",
            'info'
        );

        return ['success' => true];
    }

    public function deleteGeofence(array $employee, int $id): array
    {
        $geofence = $this->geofenceModel->find($id);
        if (!$geofence) {
            return ['success' => false, 'message' => 'Geofence não encontrado.'];
        }

        $this->auditModel->log(
            $employee['id'],
            'GEOFENCE_DELETED',
            'geofences',
            $id,
            (array) $geofence,
            null,
            "Geofence '{$geofence->name}' excluído",
            'warning'
        );

        $this->geofenceModel->delete($id);

        return ['success' => true, 'message' => 'Geofence excluído com sucesso.'];
    }

    public function toggleGeofence(array $employee, int $id): array
    {
        $geofence = $this->geofenceModel->find($id);
        if (!$geofence) {
            return ['success' => false, 'message' => 'Geofence não encontrado.'];
        }

        $newStatus = !$geofence->active;
        $this->geofenceModel->update($id, ['active' => $newStatus]);

        $this->auditModel->log(
            $employee['id'],
            'GEOFENCE_TOGGLED',
            'geofences',
            $id,
            ['active' => $geofence->active],
            ['active' => $newStatus],
            "Geofence '{$geofence->name}' " . ($newStatus ? 'ativado' : 'desativado'),
            'info'
        );

        return [
            'success' => true,
            'active' => $newStatus,
            'message' => $newStatus ? 'Geofence ativado com sucesso.' : 'Geofence desativado com sucesso.',
        ];
    }

    public function validatePoint(float $latitude, float $longitude): array
    {
        return $this->geolocationService->validateGeofence($latitude, $longitude);
    }

    public function activeGeofencesForMap(): array
    {
        $geofences = $this->geofenceModel->where('active', true)->findAll();

        return array_map(static function ($geofence) {
            return [
                'id' => $geofence->id,
                'name' => $geofence->name,
                'description' => $geofence->description,
                'latitude' => (float) $geofence->latitude,
                'longitude' => (float) $geofence->longitude,
                'radius' => (int) $geofence->radius_meters,
            ];
        }, $geofences);
    }
}
