<?php

namespace App\Controllers\Geolocation;

use App\Controllers\BaseController;
use App\Services\GeofenceManagementService;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Geofence Controller
 *
 * Thin HTTP layer for geofence management.
 */
class GeofenceController extends BaseController
{
    protected GeofenceManagementService $geofenceManagementService;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        $this->geofenceManagementService = new GeofenceManagementService();
        helper(['form']);
    }

    public function index()
    {
        $employee = $this->requireAdmin();
        if (!$employee) {
            return $this->redirectDenied('Apenas administradores podem gerenciar geofences.');
        }

        return view('geofences/index', [
            'employee' => $employee,
            'geofences' => $this->geofenceManagementService->allGeofences(),
        ]);
    }

    public function map()
    {
        $employee = $this->requireAdmin();
        if (!$employee) {
            return $this->redirectDenied('Apenas administradores podem visualizar o mapa de geofences.');
        }

        $search = trim((string) ($this->request->getGet('search') ?? ''));
        $status = (string) ($this->request->getGet('status') ?? '');

        $geofences = $this->geofenceManagementService->allGeofences();

        if ($search !== '') {
            $geofences = array_values(array_filter($geofences, function ($g) use ($search) {
                return stripos((string) ($g->name ?? ''), $search) !== false
                    || stripos((string) ($g->description ?? ''), $search) !== false;
            }));
        }

        if ($status === 'active') {
            $geofences = array_values(array_filter($geofences, fn($g) => !empty($g->active)));
        } elseif ($status === 'inactive') {
            $geofences = array_values(array_filter($geofences, fn($g) => empty($g->active)));
        }

        return view('geofences/map', [
            'employee'  => $employee,
            'geofences' => $geofences,
            'filters'   => ['search' => $search, 'status' => $status],
        ]);
    }

    public function create()
    {
        $employee = $this->requireAdmin();
        if (!$employee) {
            return $this->redirectDenied('Acesso negado.');
        }

        return view('geofences/create', ['employee' => $employee]);
    }

    public function store()
    {
        $employee = $this->requireAdmin();
        if (!$employee) {
            return $this->redirectDenied('Acesso negado.');
        }

        if (!$this->validate($this->rules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $result = $this->geofenceManagementService->createGeofence($employee, $this->payloadFromRequest());
        if (!$result['success']) {
            return redirect()->back()->withInput()->with('error', $result['message']);
        }

        return redirect()->to(route_to('geofences'))->with('success', 'Geofence criado com sucesso!');
    }

    public function show($id = null)
    {
        $employee = $this->requireAdmin();
        if (!$employee) {
            return $this->redirectDenied('Acesso negado.');
        }

        $geofence = $id !== null ? $this->geofenceManagementService->findGeofence((int) $id) : null;
        if (!$geofence) {
            return redirect()->to(route_to('geofences'))->with('error', 'Geofence não encontrado.');
        }

        return view('geofences/show', ['employee' => $employee, 'geofence' => $geofence]);
    }

    public function edit($id = null)
    {
        $employee = $this->requireAdmin();
        if (!$employee) {
            return $this->redirectDenied('Acesso negado.');
        }

        $geofence = $id !== null ? $this->geofenceManagementService->findGeofence((int) $id) : null;
        if (!$geofence) {
            return redirect()->to(route_to('geofences'))->with('error', 'Geofence não encontrado.');
        }

        return view('geofences/edit', ['employee' => $employee, 'geofence' => $geofence]);
    }

    public function update($id = null)
    {
        $employee = $this->requireAdmin();
        if (!$employee) {
            return $this->redirectDenied('Acesso negado.');
        }

        if ($id === null || !$this->geofenceManagementService->findGeofence((int) $id)) {
            return redirect()->to(route_to('geofences'))->with('error', 'Geofence não encontrado.');
        }

        if (!$this->validate($this->rules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $result = $this->geofenceManagementService->updateGeofence($employee, (int) $id, $this->payloadFromRequest());
        if (!$result['success']) {
            return redirect()->to(route_to('geofences'))->with('error', $result['message']);
        }

        return redirect()->to(route_to('geofences'))->with('success', 'Geofence atualizado com sucesso!');
    }

    public function delete($id = null)
    {
        $employee = $this->requireAdmin();
        if (!$employee) {
            return $this->redirectDenied('Acesso negado.');
        }

        if ($id === null) {
            return redirect()->to(route_to('geofences'))->with('error', 'Geofence não encontrado.');
        }

        $result = $this->geofenceManagementService->deleteGeofence($employee, (int) $id);
        if (!$result['success']) {
            return redirect()->to(route_to('geofences'))->with('error', $result['message']);
        }

        return redirect()->to(route_to('geofences'))->with('success', $result['message']);
    }

    public function toggle($id = null)
    {
        $employee = $this->requireAdmin();
        if (!$employee) {
            return $this->redirectDenied('Acesso negado.');
        }

        if ($id === null) {
            return redirect()->to(route_to('geofences'))->with('error', 'Geofence não encontrado.');
        }

        $result = $this->geofenceManagementService->toggleGeofence($employee, (int) $id);
        if (!$result['success']) {
            return redirect()->to(route_to('geofences'))->with('error', $result['message']);
        }

        return redirect()->to(route_to('geofences'))->with('success', $result['message']);
    }

    public function test()
    {
        $employee = $this->requireAdmin();
        if (!$employee) {
            return $this->response->setJSON(['success' => false, 'message' => 'Acesso negado.'])->setStatusCode(403);
        }

        $latitude = $this->request->getPost('latitude');
        $longitude = $this->request->getPost('longitude');

        if ($latitude === null || $longitude === null) {
            return $this->response->setJSON(['success' => false, 'message' => 'Latitude e longitude são obrigatórios.'])->setStatusCode(400);
        }

        return $this->response->setJSON(
            $this->geofenceManagementService->validatePoint((float) $latitude, (float) $longitude)
        );
    }

    public function json()
    {
        $employee = $this->geofenceManagementService->authenticatedEmployeeBySession();
        if (!$employee) {
            return $this->response->setJSON(['success' => false, 'message' => 'Não autenticado.'])->setStatusCode(401);
        }

        return $this->response->setJSON([
            'success' => true,
            'data' => $this->geofenceManagementService->activeGeofencesForMap(),
        ]);
    }

    private function requireAdmin(): ?array
    {
        $employee = $this->geofenceManagementService->authenticatedEmployeeBySession();
        if (!$employee || $employee['role'] !== 'admin') {
            return null;
        }

        return $employee;
    }

    private function redirectDenied(string $reason)
    {
        return redirect()->to(route_to('dashboard'))->with('error', "Acesso negado. {$reason}");
    }

    private function payloadFromRequest(): array
    {
        return [
            'name'          => $this->request->getPost('name'),
            'description'   => $this->request->getPost('description'),
            'center_lat'    => $this->request->getPost('latitude'),
            'center_lng'    => $this->request->getPost('longitude'),
            'radius_meters' => $this->request->getPost('radius_meters'),
            'active'        => $this->request->getPost('active') ? true : false,
        ];
    }

    private function rules(): array
    {
        return [
            'name' => 'required|min_length[3]|max_length[255]',
            'description' => 'permit_empty|max_length[500]',
            'latitude' => 'required|valid_latitude',
            'longitude' => 'required|valid_longitude',
            'radius_meters' => 'required|numeric|greater_than[0]',
            'active' => 'permit_empty',
        ];
    }
}
