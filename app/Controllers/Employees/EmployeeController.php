<?php

namespace App\Controllers\Employees;

use App\Controllers\Employees\Concerns\EmployeeControllerHelperTrait;

use App\Controllers\BaseController;
use App\Services\Auth\SessionSecurityService;
use App\Services\Employees\EmployeeAfdEventRecorderService;
use App\Services\Employees\EmployeeControllerActionService;
use App\Services\Employees\EmployeeCoordinatorService;
use Config\Services;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class EmployeeController extends BaseController
{
    use EmployeeControllerHelperTrait;
    protected EmployeeCoordinatorService $employeeCoordinatorService;
    protected EmployeeControllerActionService $employeeControllerActionService;
    protected SessionSecurityService $sessionSecurityService;
    protected EmployeeAfdEventRecorderService $employeeAfdEventRecorder;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        $this->employeeCoordinatorService = Services::employeeCoordinatorService();
        $this->employeeControllerActionService = Services::employeeControllerActionService();
        $this->sessionSecurityService = Services::sessionSecurityService();
        $this->employeeAfdEventRecorder = new EmployeeAfdEventRecorderService();
    }

    public function index()
    {
        $this->requireManager();

        return view('employees/index', [
            'employees'    => $this->employeeCoordinatorService->listEmployees($this->currentUser),
            'title'        => 'Colaboradores',
            'formOptions'  => $this->employeeCoordinatorService->createFormData(),
            'activeInvites' => (new \App\Models\EmployeeInviteModel())->listActive(),
        ]);
    }

    public function create()
    {
        $this->requireManager();

        return view('employees/create', [
            'formOptions' => $this->employeeCoordinatorService->createFormData(),
        ]);
    }

    public function show(int $id)
    {
        $this->requireManager();

        // Administradores do sistema não são colaboradores — acesso negado via rota de employees
        $targetEmployee = (new \App\Models\EmployeeModel())->asObject()->find($id);
        if ($targetEmployee && strtolower((string) ($targetEmployee->role ?? '')) === 'admin') {
            $this->setError('O perfil do administrador não é acessível como colaborador.');
            return redirect()->to(sp_employees_index_url());
        }

        $access = $this->resolveManagerEmployeeAccess($id);
        if (!($access['success'] ?? false)) {
            return $access['redirect'];
        }

        $showPayload = $this->employeeControllerActionService->showPayload($id);
        if (!($showPayload['success'] ?? false)) {
            $this->setError($showPayload['message']);
            return redirect()->to(sp_employees_index_url());
        }

        return view('employees/show', array_merge($showPayload['viewData'], ['currentUser' => $this->currentUser]));
    }

    public function getPositionsByDepartment()
    {
        $this->requireManager();

        $departmentId = (int) ($this->request->getGet('department_id') ?? 0);
        if ($departmentId <= 0) {
            return $this->response->setStatusCode(400)->setJSON(['positions' => [], 'message' => 'Departamento inválido.']);
        }

        try {
            $positions = $this->employeeCoordinatorService->getPositionsByDepartment($departmentId);
            $positions = array_map(static function ($item): array {
                if (is_array($item)) {
                    return [
                        'id' => (int) ($item['id'] ?? 0),
                        'name' => (string) ($item['name'] ?? $item['title'] ?? ''),
                    ];
                }

                return [
                    'id' => (int) ($item->id ?? 0),
                    'name' => (string) ($item->name ?? $item->title ?? ''),
                ];
            }, is_array($positions) ? $positions : []);

            return $this->response->setJSON(['positions' => $positions]);
        } catch (\Throwable) {
            return $this->response->setStatusCode(500)->setJSON(['positions' => [], 'message' => 'Erro ao carregar cargos.']);
        }
    }

    public function store()
    {
        $this->requireManager();

        $postData = $this->capRoleForActor(security_sanitize($this->request->getPost() ?? []));
        $result = $this->employeeCoordinatorService->createEmployee($postData);
        if (!($result['success'] ?? false)) {
            return $this->redirectWithServiceError($result, 'Erro ao criar empregado.');
        }

        $employeeId = (int) $result['employee_id'];
        $data = $result['data'];

        $this->logAudit('EMPLOYEE_CREATED', 'employees', $employeeId, null, $data, "Empregado criado: {$data['name']} ({$data['email']})");

        // Registro tipo "5" do AFD ("I" — inclusão de empregado no REP). Nunca bloqueia
        // o fluxo principal — ver EmployeeAfdEventRecorderService.
        $this->employeeAfdEventRecorder->recordInclusion($data, $this->currentUser);

        $this->setSuccess('Empregado criado com sucesso! Agora envie a foto e os documentos do colaborador.');
        return redirect()->to(route_to('employees.documents.step', $employeeId));
    }

    public function edit(int $id)
    {
        $this->requireManager();

        $access = $this->resolveManagerEmployeeAccess($id);
        if (!($access['success'] ?? false)) {
            return $access['redirect'];
        }

        if (!$this->employeeCoordinatorService->employeeIsActive($access['employee'])) {
            $this->setError('Colaboradores desligados não podem ser editados.');
            return redirect()->to(sp_employees_index_url());
        }

        return view('employees/edit', array_merge(
            $this->employeeCoordinatorService->createFormData(),
            ['employee' => $access['employee']]
        ));
    }

    public function update(int $id)
    {
        $this->requireManager();

        $access = $this->resolveManagerEmployeeAccess($id);
        if (!($access['success'] ?? false)) {
            return $access['redirect'];
        }

        $postData = $this->capRoleForActor(security_sanitize($this->request->getPost() ?? []));
        $result = $this->employeeCoordinatorService->updateEmployee($id, $postData);
        if (!($result['success'] ?? false)) {
            return $this->redirectWithServiceError($result, 'Erro ao atualizar empregado.');
        }

        $this->logAudit('EMPLOYEE_UPDATED', 'employees', $id, $result['old_values'], $result['data'], "Empregado atualizado: {$result['data']['name']}");

        // Registro tipo "5" do AFD ("A" — alteração de cadastro de empregado no REP).
        // Nunca bloqueia o fluxo principal — ver EmployeeAfdEventRecorderService.
        $this->employeeAfdEventRecorder->recordAlteration($result['data'], $this->currentUser);

        $this->setSuccess('Empregado atualizado com sucesso!');
        return redirect()->to(route_to('employees.show', $id));
    }

    public function delete(int $id)
    {
        $this->requireRole('admin');

        return $this->respondActionWithAudit(
            $this->employeeControllerActionService->deleteEmployee($id, $this->currentUser),
            $id,
            'Erro ao excluir colaborador.'
        );
    }

    public function activate(int $id)
    {
        return $this->setEmployeeActiveState($id, true);
    }

    public function deactivate(int $id)
    {
        return $this->setEmployeeActiveState($id, false);
    }

    public function pendingRegistrations()
    {
        $this->requireRole('admin');

        $inviteModel   = new \App\Models\EmployeeInviteModel();
        $inviteGroups  = $inviteModel->listAllWithStatus();

        return view('employees/pending', [
            'employees'           => $this->employeeCoordinatorService->pendingRegistrations(),
            'terminatedEmployees' => $this->employeeCoordinatorService->terminatedEmployees(),
            'activeInvites'       => $inviteGroups['active'],
            'expiredInvites'      => $inviteGroups['expired'],
            'usedInvites'         => $inviteGroups['used'],
            'title'               => 'Cadastros Pendentes',
        ]);
    }

    public function approveRegistration(int $id)
    {
        $this->requireRole('admin');

        return $this->redirectActionWithAudit(
            $this->employeeControllerActionService->approveRegistration($id),
            $id,
            '/employees/pending',
            'Colaborador não encontrado.'
        );
    }

    public function rejectRegistration(int $id)
    {
        $this->requireRole('admin');

        return $this->redirectActionWithAudit(
            $this->employeeControllerActionService->rejectRegistration($id),
            $id,
            '/employees/pending',
            'Colaborador não encontrado.'
        );
    }

    public function profile()
    {
        $this->requireAuth();

        $employeeId = (int) $this->currentUser->id;

        $showPayload = $this->employeeControllerActionService->showPayload($employeeId);
        if (!($showPayload['success'] ?? false)) {
            $this->setError($showPayload['message'] ?? 'Não foi possível carregar seu perfil.');
            return redirect()->to(sp_dashboard_url());
        }

        return view('employees/profile', array_merge($showPayload['viewData'], ['currentUser' => $this->currentUser]));
    }

    public function updateProfile()
    {
        $this->requireAuth();

        $rules = $this->employeeControllerActionService->profileValidationRules();

        if (!$this->validate($rules)) {
            return $this->redirectBackWithValidationErrors();
        }

        $result = $this->employeeCoordinatorService->updateProfile($this->currentUser->id, [
            'phone' => $this->request->getPost('phone'),
            'password' => $this->request->getPost('password'),
        ]);

        if (!($result['success'] ?? false)) {
            $this->setError($result['message'] ?? 'Erro ao atualizar perfil.');
            return redirect()->back();
        }

        $this->setSuccess($result['message']);
        return redirect()->to(route_to('profile'));
    }

    public function exportData(int $id)
    {
        $this->requireAuth();

        $action = $this->employeeControllerActionService->exportEmployeeData($id, $this->currentUser, $this->hasRole('admin'));
        if (!($action['success'] ?? false)) {
            return $this->respondError($action['message'] ?? 'Erro ao exportar dados.', null, (int) ($action['status'] ?? 400));
        }

        $this->logActionAudit($action, $id);
        return $this->respondSuccess($action['data'], $action['message']);
    }

    public function qrcode(int $id)
    {
        $this->requireManager();

        $employee = $this->resolveManagedEmployeeForView($id);
        if (!($employee['success'] ?? false)) {
            return $employee['redirect'];
        }

        return view('employees/qrcode', ['employee' => $employee['employee']]);
    }

    public function qrcodeDownload(int $id)
    {
        $this->requireManager();

        $employeeResult = $this->resolveManagedEmployeeForJson($id);
        if (!($employeeResult['success'] ?? false)) {
            return $employeeResult['response'];
        }

        try {
            return $this->response->setJSON(
                $this->employeeControllerActionService->qrcodeDownloadPayload($employeeResult['employee'])
            );
        } catch (\Throwable $e) {
            log_message('error', 'QR Code download error: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON(['error' => 'Erro interno do servidor']);
        }
    }

    public function qrcodePrint(int $id)
    {
        $this->requireManager();

        $employeeResult = $this->resolveManagedEmployeeForView($id);
        if (!($employeeResult['success'] ?? false)) {
            return $employeeResult['redirect'];
        }

        return view('employees/qrcode_print', ['employee' => $employeeResult['employee']]);
    }

    public function biometric()
    {
        if ($redirect = $this->redirectIfGuest()) {
            return $redirect;
        }

        $data = $this->employeeCoordinatorService->biometricPageData($this->currentUser->id);
        if ($data === null) {
            return redirect()->to(route_to('dashboard'))->with('error', 'Colaborador não encontrado.');
        }

        return view('profile/biometric', $data);
    }

    public function biometricConsent()
    {
        if ($redirect = $this->redirectIfGuest()) {
            return $redirect;
        }

        $result = $this->employeeControllerActionService->grantBiometricConsent($this->currentUser->id, $this->request->getIPAddress());

        return redirect()->to(route_to('profile.biometric'))->with($result['type'] ?? (($result['success'] ?? false) ? 'success' : 'error'), $result['message']);
    }

    public function biometricRevoke()
    {
        if ($redirect = $this->redirectIfGuest()) {
            return $redirect;
        }

        $guard = $this->sessionSecurityService->ensureCriticalActionAllowed($this->currentUser, $this->request, $this->session);
        if (! ($guard['success'] ?? false)) {
            return redirect()->to(route_to('profile.security'))->with('error', $guard['message'] ?? 'Confirme sua senha antes de revogar a biometria.');
        }

        $result = $this->employeeControllerActionService->revokeBiometricConsent($this->currentUser->id);

        return redirect()->to(route_to('profile.biometric'))->with(($result['success'] ?? false) ? 'success' : 'error', $result['message']);
    }

    public function changePassword()
    {
        $this->requireAuth();

        return view('employees/change_password', [
            'title' => 'Alterar Senha',
            'currentUser' => $this->currentUser,
        ]);
    }

    public function updatePassword()
    {
        $this->requireAuth();

        $rules = $this->employeeControllerActionService->passwordValidationRules();

        if (!$this->validate($rules)) {
            return $this->redirectBackWithValidationErrors();
        }

        $result = $this->employeeControllerActionService->changePassword(
            $this->currentUser,
            (string) $this->request->getPost('current_password'),
            (string) $this->request->getPost('new_password')
        );

        return ($result['success'] ?? false)
            ? redirect()->to(sp_dashboard_url())->with('success', $result['message'])
            : redirect()->back()->with('error', $result['message']);
    }

    private function redirectWithServiceError(array $result, string $defaultMessage)
    {
        if (isset($result['errors'])) {
            return redirect()->back()->withInput()->with('errors', $result['errors']);
        }

        return redirect()->back()->withInput()->with('error', $result['error'] ?? $defaultMessage);
    }

    /**
     * Non-admin managers (gestor, rh) must not assign privileged roles (admin, dpo, auditor).
     * If a non-admin submits a privileged role, silently downgrade it to 'funcionario'.
     */
    private function capRoleForActor(array $postData): array
    {
        $privilegedRoles = ['admin', 'dpo', 'auditor'];
        $actorRole = (string) ($this->currentUser->role ?? '');

        if ($actorRole === 'admin') {
            return $postData;
        }

        // Resolve role name from string or numeric role_id
        $submittedRole = strtolower(trim((string) ($postData['role'] ?? '')));
        if ($submittedRole === '' && isset($postData['role_id']) && is_numeric($postData['role_id'])) {
            try {
                $row = (new \App\Models\RoleModel())->find((int) $postData['role_id']);
                if ($row) {
                    $submittedRole = strtolower(trim((string) (is_array($row) ? ($row['name'] ?? '') : ($row->name ?? ''))));
                }
            } catch (\Throwable) {}
        }

        if (in_array($submittedRole, $privilegedRoles, true)) {
            $postData['role'] = 'funcionario';
            unset($postData['role_id']);
        }

        return $postData;
    }














    /**
     * AJAX endpoint: toggle employee active/inactive.
     * POST employees/{id}/toggle-active
     * Returns JSON: {"success": bool, "message": string, "active": bool}
     */
    public function toggleActive(int $id)
    {
        $this->requireRole('admin');

        if (! $this->request->is('post')) {
            return $this->response->setStatusCode(405)->setJSON([
                'success' => false,
                'message' => 'Método não permitido.',
            ]);
        }

        // Não pode alterar a si mesmo
        if ($this->currentUser && (int) $this->currentUser->id === $id) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Você não pode alterar o status da sua própria conta.',
            ]);
        }

        $employee = \Config\Services::employeeModel()->find($id);
        if (! $employee) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Colaborador não encontrado.',
            ]);
        }

        $currentActive = (bool) ($employee->active ?? false);
        $newActive     = ! $currentActive;

        try {
            // Query direta: bypass model callbacks/validation para update simples de status
            $db = \Config\Database::connect();
            $updateData = ['active' => $newActive, 'updated_at' => date('Y-m-d H:i:s')];
            // When deactivating: always record demission date (distinguishes from pending registrations)
            if (!$newActive) {
                $updateData['demission_date'] = date('Y-m-d');
            }
            // When reactivating: clear demission date
            if ($newActive) {
                $updateData['demission_date'] = null;
            }
            $updated = $db->table('employees')
                ->where('id', $id)
                ->update($updateData);
            if (! $updated) {
                log_message('error', 'toggleActive DB update returned false for id=' . $id);
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Erro ao atualizar o banco de dados.',
                ]);
            }
        } catch (\Throwable $e) {
            log_message('error', 'toggleActive failed for id=' . $id . ': ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Erro interno ao alterar status.',
            ]);
        }

        $action = $newActive ? 'EMPLOYEE_ACTIVATED' : 'EMPLOYEE_DEACTIVATED';
        $desc   = $newActive
            ? 'Colaborador ativado via toggle: ' . ($employee->name ?? $id)
            : 'Colaborador desativado via toggle: ' . ($employee->name ?? $id);

        $this->logAudit($action, 'employees', $id,
            ['active' => $currentActive],
            ['active' => $newActive],
            $desc
        );

        return $this->response->setJSON([
            'success'   => true,
            'active'    => $newActive,
            'csrf_hash' => csrf_hash(),
            'message'   => $newActive
                ? ($employee->name ?? 'Colaborador') . ' ativado com sucesso.'
                : ($employee->name ?? 'Colaborador') . ' desativado com sucesso.',
        ]);
    }

    private function setEmployeeActiveState(int $id, bool $active)
    {
        $this->requireRole('admin');

        $action = $this->employeeControllerActionService->setEmployeeActiveState($id, $active, $this->currentUser);
        if (!($action['success'] ?? false)) {
            if ($action['self_action'] ?? false) {
                $this->setError($action['message']);
                return redirect()->back();
            }

            return $this->respondError($action['message'] ?? 'Colaborador não encontrado.', null, (int) ($action['status'] ?? 404));
        }

        $this->logActionAudit($action, $id);
        return $this->respondSuccess(null, $action['message']);
    }

    private function respondActionWithAudit(array $action, int $employeeId, string $defaultError)
    {
        if (!($action['success'] ?? false)) {
            if ($action['self_action'] ?? false) {
                $this->setError($action['message']);
                return redirect()->back();
            }

            return $this->respondError($action['message'] ?? $defaultError, null, (int) ($action['status'] ?? 500));
        }

        $this->logActionAudit($action, $employeeId);
        return $this->respondSuccess(null, $action['message'] ?? 'Operação concluída.');
    }

    private function redirectActionWithAudit(array $action, int $employeeId, string $redirectPath, string $defaultError)
    {
        if (!($action['success'] ?? false)) {
            return $this->respondError($action['message'] ?? $defaultError, null, (int) ($action['status'] ?? 404));
        }

        $this->logActionAudit($action, $employeeId);
        return redirect()->to($redirectPath)->with('success', $action['message'] ?? 'Operação concluída.');
    }

    private function logActionAudit(array $action, int $employeeId): void
    {
        if (!isset($action['audit'])) {
            return;
        }

        $this->logAudit(
            $action['audit']['action'],
            'employees',
            $employeeId,
            $action['audit']['old_values'],
            $action['audit']['new_values'],
            $action['audit']['description']
        );
    }

    public function adminOverview()
    {
        $employeeModel = new \App\Models\EmployeeModel();
        $employees = $employeeModel
            ->select('id, name, email, role, department, unique_code, has_face_biometric, has_fingerprint_biometric, active')
            ->whereNotIn('role', ['admin', 'dpo', 'auditor'])
            ->orderBy('name', 'ASC')
            ->findAll();

        $db = \Config\Database::connect();

        // LGPD subject requests
        $lgpdRequests = $db->table('lgpd_subject_requests sr')
            ->select('sr.*, e.name AS employee_name, e.email AS employee_email, e.department')
            ->join('employees e', 'e.id = sr.employee_id', 'left')
            ->orderBy('sr.created_at', 'DESC')
            ->get()->getResultObject();

        $lgpdStats = [
            'pending'   => 0,
            'resolved'  => 0,
            'overdue'   => 0,
            'by_type'   => [],
        ];
        foreach ($lgpdRequests as $r) {
            if ($r->status === 'pending') {
                $lgpdStats['pending']++;
                if ($r->due_at && strtotime($r->due_at) < time()) {
                    $lgpdStats['overdue']++;
                }
            } elseif (in_array($r->status, ['resolved','completed'], true)) {
                $lgpdStats['resolved']++;
            }
            $lgpdStats['by_type'][$r->request_type] = ($lgpdStats['by_type'][$r->request_type] ?? 0) + 1;
        }

        $stats = [
            'total'            => count($employees),
            'active'           => count(array_filter($employees, fn($e) => $e->active)),
            'with_face'        => count(array_filter($employees, fn($e) => $e->has_face_biometric)),
            'with_fingerprint' => count(array_filter($employees, fn($e) => $e->has_fingerprint_biometric)),
        ];

        return view('admin/employees_overview', [
            'employees'    => $employees,
            'stats'        => $stats,
            'lgpdRequests' => $lgpdRequests,
            'lgpdStats'    => $lgpdStats,
        ]);
    }

    public function uploadPhoto(int $id)
    {
        $this->requireAuth();

        // Only self-upload or manager -- requireManager() sozinho e so checagem
        // de papel (admin/gestor/rh de qualquer departamento); um gestor de
        // outro departamento conseguia sobrescrever a foto de colaborador que
        // nao gerencia. resolveManagerAccess() aplica o mesmo escopo por
        // departamento ja usado em show()/edit()/update() deste controller.
        $isSelf = isset($this->currentUser) && (int)($this->currentUser->id ?? 0) === $id;
        if (!$isSelf) {
            $this->requireManager();

            $access = $this->employeeControllerActionService->resolveManagerAccess($this->currentUser, $id);
            if (!($access['success'] ?? false)) {
                return $this->respondError($access['message'] ?? 'Você não tem permissão para gerenciar este colaborador.', null, 403);
            }
        }

        $employeeModel = new \App\Models\EmployeeModel();
        $employee = $employeeModel->find($id);
        if (!$employee) {
            return $this->respondError('Colaborador não encontrado.', null, 404);
        }

        // MED-09 (auditoria): antes ficava em FCPATH (webroot público), com nome
        // previsível (emp_{id}_{timestamp}.jpg) e sem nenhum controle de acesso na
        // leitura — dado pessoal (LGPD) acessível a qualquer um com a URL. Agora fica em
        // WRITEPATH (fora do webroot), só acessível via photo() abaixo, que exige
        // autenticação e checa posse (self/admin/rh/gestor do mesmo departamento).
        $uploadDir = WRITEPATH . 'uploads/employees/photos/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0750, true);
        }

        $imgData = null;
        $file = $this->request->getFile('photo');

        if ($file && $file->isValid() && !$file->hasMoved()) {
            // File upload
            if (!$file->isImage()) {
                return $this->respondError('Apenas imagens são aceitas (JPG, PNG, WEBP).', null, 422);
            }
            if ($file->getSizeByUnit('mb') > 5) {
                return $this->respondError('Tamanho máximo permitido: 5 MB.', null, 422);
            }
            $realMime = (new \App\Services\Upload\SafeUploadService())->detectRealMime((string) $file->getTempName());
            if (! in_array($realMime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                return $this->respondError('Apenas imagens são aceitas (JPG, PNG, WEBP).', null, 422);
            }
            $imgData = file_get_contents($file->getTempName());
        } elseif ($b64 = $this->request->getPost('photo_base64')) {
            // Camera capture (base64)
            $raw = base64_decode(preg_replace('/^data:image\/\w+;base64,/', '', $b64));
            if (!$raw || strlen($raw) < 1000) {
                return $this->respondError('Imagem inválida. Tente novamente.', null, 422);
            }
            $imgData = $raw;
        }

        if (!$imgData) {
            return $this->respondError('Nenhuma imagem recebida.', null, 400);
        }

        // Delete old photo if any (localização legada em FCPATH ou nova em WRITEPATH)
        $oldPath = is_array($employee) ? ($employee['photo_path'] ?? null) : ($employee->photo_path ?? null);
        if ($oldPath) {
            $oldFull = str_starts_with($oldPath, 'uploads/')
                ? WRITEPATH . $oldPath
                : FCPATH . ltrim($oldPath, '/');
            if (file_exists($oldFull)) {
                @unlink($oldFull);
            }
        }

        // Save new photo com nome não previsível
        $filename = 'emp_' . $id . '_' . bin2hex(random_bytes(16)) . '.jpg';
        $filepath = $uploadDir . $filename;
        file_put_contents($filepath, $imgData);
        @chmod($filepath, 0640);

        $relativePath = 'uploads/employees/photos/' . $filename;
        $employeeModel->update($id, ['photo_path' => $relativePath]);

        return $this->respondSuccess(['photo_url' => site_url('employees/' . $id . '/photo')], 'Foto atualizada com sucesso.');
    }

    /**
     * Serve a foto do colaborador sob autenticação + checagem de posse — ver MED-09
     * na auditoria. Nunca serve arquivo estático diretamente do webroot público.
     */
    public function photo(int $id)
    {
        $this->requireAuth();

        $employeeModel = new \App\Models\EmployeeModel();
        $employee = $employeeModel->find($id);
        if (!$employee) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        if (!$this->canAccessEmployeeRecord($employee)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $photoPath = is_array($employee) ? ($employee['photo_path'] ?? null) : ($employee->photo_path ?? null);
        if (!$photoPath) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $candidate = str_starts_with($photoPath, 'uploads/')
            ? WRITEPATH . $photoPath
            : FCPATH . ltrim($photoPath, '/');

        $safeUpload = new \App\Services\Upload\SafeUploadService();
        $resolved = $safeUpload->safeDownloadPath($candidate, [
            realpath(WRITEPATH . 'uploads/employees/photos') ?: WRITEPATH . 'uploads/employees/photos',
            realpath(FCPATH . 'store/employees/photos') ?: FCPATH . 'store/employees/photos',
        ]);

        if ($resolved === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $mime = $safeUpload->detectRealMime($resolved) ?? 'application/octet-stream';

        return $this->response
            ->setHeader('Cache-Control', 'private, max-age=300')
            ->setContentType($mime)
            ->setBody(file_get_contents($resolved));
    }

}

