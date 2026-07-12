<?php

namespace App\Controllers\Settings;

use App\Models\GeofenceModel;

class GeofenceSettingsController extends BaseSettingsController
{
    private const DEFAULT_PER_PAGE = 25;
    private const MAX_PER_PAGE = 100;

    protected GeofenceModel $geofenceModel;

    public function __construct()
    {
        parent::__construct();
        $this->geofenceModel = new GeofenceModel();
    }



    private function paginationParams(): array
    {
        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = (int) ($this->request->getGet('per_page') ?? self::DEFAULT_PER_PAGE);
        $perPage = max(1, min(self::MAX_PER_PAGE, $perPage));

        return [$page, $perPage];
    }

    private function paginationMeta($pager, int $page, int $perPage, int $count): array
    {
        return [
            'page' => $page,
            'per_page' => $perPage,
            'count' => $count,
            'total' => (int) ($pager?->getTotal() ?? $count),
            'page_count' => (int) ($pager?->getPageCount() ?? 1),
        ];
    }

    public function geofences()
    {
        $this->requireAdminAccess();
        [$page, $perPage] = $this->paginationParams();

        $records = $this->geofenceModel
            ->orderBy('active', 'DESC')
            ->orderBy('created_at', 'DESC')
            ->paginate($perPage, 'default', $page);

        return $this->response->setJSON([
            'success' => true,
            'data' => $records,
            'meta' => $this->paginationMeta($this->geofenceModel->pager, $page, $perPage, count($records)),
        ]);
    }

    public function storeGeofence()
    {
        $this->requireAdminAccess();

        // Aceitar tanto os nomes canônicos (center_lat/center_lng/radius_meters)
        // quanto os aliases curtos (latitude/longitude/radius) enviados pelo JS
        $lat    = $this->request->getPost('center_lat')    ?? $this->request->getPost('latitude');
        $lng    = $this->request->getPost('center_lng')    ?? $this->request->getPost('longitude');
        $radius = $this->request->getPost('radius_meters') ?? $this->request->getPost('radius');

        $rules = [
            'name' => 'required|min_length[2]|max_length[255]',
        ];

        if (! $this->validate($rules)) {
            return $this->response->setJSON(['success' => false, 'message' => implode(', ', $this->validator->getErrors())]);
        }

        if (empty($lat) || empty($lng) || empty($radius)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Latitude, longitude e raio são obrigatórios.']);
        }

        $data = [
            'name'          => $this->request->getPost('name'),
            'description'   => $this->request->getPost('description') ?? '',
            'center_lat'    => (float) $lat,
            'center_lng'    => (float) $lng,
            'radius_meters' => (int) $radius,
            'address'       => $this->request->getPost('address') ?? '',
            'color'         => $this->request->getPost('color') ?: '#4fa14f',
            'active'        => 1,
        ];

        if ($this->geofenceModel->insert($data)) {
            return $this->response->setJSON(['success' => true, 'message' => 'Cerca criada com sucesso']);
        }

        return $this->response->setJSON(['success' => false, 'message' => 'Erro ao criar cerca']);
    }

    public function updateGeofence($id)
    {
        $this->requireAdminAccess();

        $geofence = $this->geofenceModel->find($id);
        if (! $geofence) {
            return $this->response->setJSON(['success' => false, 'message' => 'Cerca não encontrada']);
        }

        $lat    = $this->request->getPost('center_lat')    ?? $this->request->getPost('latitude');
        $lng    = $this->request->getPost('center_lng')    ?? $this->request->getPost('longitude');
        $radius = $this->request->getPost('radius_meters') ?? $this->request->getPost('radius');

        $data = [
            'name'          => $this->request->getPost('name'),
            'description'   => $this->request->getPost('description') ?? '',
            'center_lat'    => (float) $lat,
            'center_lng'    => (float) $lng,
            'radius_meters' => (int) $radius,
            'address'       => $this->request->getPost('address') ?? '',
            'color'         => $this->request->getPost('color') ?: '#4fa14f',
        ];

        if ($this->geofenceModel->update($id, $data)) {
            return $this->response->setJSON(['success' => true, 'message' => 'Cerca atualizada com sucesso']);
        }

        return $this->response->setJSON(['success' => false, 'message' => 'Erro ao atualizar cerca']);
    }

    public function toggleGeofence($id)
    {
        $this->requireAdminAccess();

        $geofence = $this->geofenceModel->find($id);
        if (! $geofence) {
            return $this->response->setJSON(['success' => false, 'message' => 'Cerca não encontrada']);
        }

        $active = is_array($geofence) ? ($geofence['active'] ?? 0) : ($geofence->active ?? 0);
        $this->geofenceModel->update($id, ['active' => $active ? false : true]);

        return $this->response->setJSON(['success' => true, 'message' => 'Status alterado']);
    }

    public function deleteGeofence($id)
    {
        $this->requireAdminAccess();

        $geofence = $this->geofenceModel->find($id);
        if (! $geofence) {
            return $this->response->setJSON(['success' => false, 'message' => 'Cerca não encontrada']);
        }

        if ($this->geofenceModel->delete($id)) {
            return $this->response->setJSON(['success' => true, 'message' => 'Cerca excluída']);
        }

        return $this->response->setJSON(['success' => false, 'message' => 'Erro ao excluir']);
    }
}
