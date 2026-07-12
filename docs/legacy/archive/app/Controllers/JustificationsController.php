<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\JustificationModel;
use App\Models\AuditModel;
use App\Support\Navigation\AdminFlowContextResolver;
use App\Enums\Role;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class JustificationsController extends BaseController
{
    protected $justificationModel;
    protected $auditModel;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        
        $this->justificationModel = new JustificationModel();
        $this->auditModel = new AuditModel();
    }

    /**
     * Get authenticated employee
     */
    protected function getAuthenticatedEmployee(): ?array
    {
        $session = session();
        $userId = $session->get('user_id');

        if (!$userId) {
            return null;
        }

        // Assume EmployeeModel existe; ajuste se necessário
        $employeeModel = new \App\Models\EmployeeModel();
        $employee = $employeeModel->find($userId);

        if (!$employee) {
            return null;
        }

        return [
            'id' => $employee->id,
            'name' => $employee->name,
            'role' => Role::normalize((string) $employee->role)->value,
            'department' => $employee->department,
        ];
    }

    /**
     * Listar justificativas do usuário
     */
    public function index()
    {
        $employee = $this->getAuthenticatedEmployee();
        if (!$employee) {
            return redirect()->to(sp_login_url())->with('error', 'Você precisa estar autenticado.');
        }

        $targetEmployeeId = $this->request->getGet('employee_id') ?? $employee['id'];

        if ($targetEmployeeId != $employee['id'] && !$this->canManageJustifications($employee)) {
            return redirect()->back()->with('error', 'Sem permissão.');
        }

        // Obter e validar filtros com segurança
        $status = $this->request->getGet('status') ?? 'all';
        $dateFrom = $this->request->getGet('date_from');
        $dateTo = $this->request->getGet('date_to');
        $perPage = (int) ($this->request->getGet('per_page') ?? 10);

        // Validação defensiva de status
        if (!in_array($status, ['all', 'pending', 'approved', 'rejected'])) {
            $status = 'all';
        }

        // Validação defensiva de datas (formato YYYY-MM-DD)
        if ($dateFrom && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $dateFrom = null;
        }
        if ($dateTo && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $dateTo = null;
        }

        // Counts globais para admin/gestor se não especificar employee_id
        $isGlobalCounts = $this->canManageJustifications($employee) && !$this->request->getGet('employee_id');
        $countsEmployeeId = $isGlobalCounts ? null : $targetEmployeeId;
        $counts = $this->justificationModel->getCounts($countsEmployeeId) ?? [
            'all' => 0,
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
        ];

        // Filtrar justificativas com validação
        $query = $this->justificationModel->where('employee_id', $targetEmployeeId);
        
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        if ($dateFrom) {
            $query->where('created_at >=', $dateFrom . ' 00:00:00');
        }
        if ($dateTo) {
            $query->where('created_at <=', $dateTo . ' 23:59:59');
        }

        // Paginação para performance
        $justifications = $query->orderBy('created_at', 'DESC')->paginate($perPage);
        $pager = $this->justificationModel->pager;

        return view('justifications/index', [
            'employee' => $employee,
            'justifications' => $justifications,
            'counts' => $counts,
            'status' => $status,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'pager' => $pager,
            'perPage' => $perPage,
            'navigationContext' => AdminFlowContextResolver::fromRequest($this->request, 'justifications'),
        ]);
    }

    /**
     * Mostrar formulário de criação
     */
    public function create()
    {
        $employee = $this->getAuthenticatedEmployee();
        if (!$employee) {
            return redirect()->to(sp_login_url())->with('error', 'Você precisa estar autenticado.');
        }

        // Data padrão: hoje
        $date = date('Y-m-d');

        return view('justifications/create', [
            'employee' => $employee,
            'date' => $date,
        ]);
    }

    /**
     * Processar e salvar justificativa
     */
    public function store()
    {
        $employee = $this->getAuthenticatedEmployee();
        if (!$employee) {
            return redirect()->to(sp_login_url())->with('error', 'Você precisa estar autenticado.');
        }

        $rules = [
            'justification_date' => 'required|valid_date[Y-m-d]',
            'justification_type' => 'required|in_list[falta,atraso,saida-antecipada]',
            'category' => 'required|in_list[doenca,compromisso-pessoal,emergencia-familiar,outro]',
            'reason' => 'required|trim|min_length[50]|max_length[500]',
            'attachments.*' => 'max_size[attachments,5120]|mime_in[attachments,application/pdf,image/jpg,image/jpeg,image/png]|max_dims[attachments,2048,2048]',
        ];

        $messages = [
            'justification_date' => [
                'required' => 'A data da justificativa é obrigatória.',
                'valid_date' => 'Formato de data inválido.',
            ],
            'justification_type' => [
                'required' => 'O tipo de justificativa é obrigatório.',
                'in_list' => 'Tipo de justificativa inválido.',
            ],
            'category' => [
                'required' => 'A categoria é obrigatória.',
                'in_list' => 'Categoria inválida.',
            ],
            'reason' => [
                'required' => 'O motivo é obrigatório.',
                'min_length' => 'O motivo deve ter pelo menos 50 caracteres.',
                'max_length' => 'O motivo não pode exceder 500 caracteres.',
            ],
            'attachments.*' => [
                'max_size' => 'Cada anexo deve ter no máximo 5MB.',
                'mime_in' => 'Apenas arquivos PDF, JPG ou PNG são permitidos.',
                'max_dims' => 'Imagem muito grande (máx 2048x2048).',
            ],
        ];

        if (!$this->validate($rules, $messages)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        // Processar uploads com validação defensiva
        $attachments = [];
        $attachmentErrors = [];
        if ($this->request->getFiles()['attachments'] ?? false) {
            $files = $this->request->getFiles()['attachments'];
            $processed = 0;

            foreach ($files as $file) {
                if (!$file->isValid() || $file->hasMoved()) {
                    continue;
                }

                $processed++;
                if ($processed > 3) {
                    $attachmentErrors[] = 'Máximo de 3 arquivos permitidos.';
                    break;
                }

                $upload = upload_justification_attachment($file, (int) $employee['id']);
                if (($upload['success'] ?? false) !== true) {
                    $attachmentErrors[] = $upload['message'] ?? "Erro ao processar '{$file->getClientName()}'.";
                    continue;
                }

                $attachments[] = $upload['file_path'];
            }
        }

        if (!empty($attachmentErrors)) {
            foreach ($attachments as $path) {
                if (is_string($path) && file_exists(WRITEPATH . $path)) {
                    @unlink(WRITEPATH . $path);
                }
            }

            supportponto_log_event('warning', 'justification', 'legacy_attachment_validation_failed', [
                'employee_id' => $employee['id'] ?? null,
                'error_count' => count($attachmentErrors),
            ]);

            return redirect()->back()->withInput()->with('error', implode(' ', $attachmentErrors));
        }

        // Status: auto-aprovado para admin/gestor
        $status = $this->canAutoApproveJustification($employee) ? 'approved' : 'pending';

        $data = [
            'employee_id' => $employee['id'],
            'date' => $this->request->getPost('justification_date'),
            'type' => $this->request->getPost('justification_type'),
            'category' => $this->request->getPost('category'),
            'reason' => trim($this->request->getPost('reason')),
            'attachment_path' => !empty($attachments) ? json_encode($attachments) : null,
            'status' => $status,
            'submitted_by' => $employee['id'],
        ];

        supportponto_log_event('info', 'justification', 'legacy_store_attempt', [
            'employee_id' => $employee['id'] ?? null,
            'status' => $status,
            'attachment_count' => count($attachments),
        ]);

        try {
            if ($this->justificationModel->insert($data)) {
                $id = $this->justificationModel->getInsertID();
                $this->auditModel->log($employee['id'], 'JUSTIFICATION_CREATED', 'justifications', $id, null, ['status' => $status, 'attachment_count' => count($attachments)], 'Justificativa criada', 'info');
                supportponto_log_event('info', 'justification', 'legacy_store_success', [
                    'employee_id' => $employee['id'] ?? null,
                    'justification_id' => $id,
                    'status' => $status,
                ]);
                return redirect()->to('/justifications')->with('success', 'Justificativa enviada com sucesso.');
            } else {
                $modelErrors = $this->justificationModel->errors();
                supportponto_log_event('error', 'justification', 'legacy_store_model_error', [
                    'employee_id' => $employee['id'] ?? null,
                    'error_count' => is_array($modelErrors) ? count($modelErrors) : 0,
                ]);
                return redirect()->back()->withInput()->with('error', 'Erro ao salvar: ' . implode(', ', $modelErrors));
            }
        } catch (\Throwable $e) {
            foreach ($attachments as $path) {
                if (is_string($path) && file_exists(WRITEPATH . $path)) {
                    @unlink(WRITEPATH . $path);
                }
            }

            supportponto_log_exception('justification', 'legacy_store_exception', $e, [
                'employee_id' => $employee['id'] ?? null,
                'status' => $status,
            ]);
            return redirect()->back()->withInput()->with('error', 'Erro interno ao salvar justificativa.');
        }
    }

    /**
     * Mostrar detalhes de uma justificativa
     */
    public function show($id)
    {
        $employee = $this->getAuthenticatedEmployee();
        if (!$employee) {
            return redirect()->to(sp_login_url())->with('error', 'Você precisa estar autenticado.');
        }

        $justification = $this->justificationModel->find($id);
        if (!$justification || ($justification->employee_id != $employee['id'] && !$this->canManageJustifications($employee))) {
            return redirect()->to('/justifications')->with('error', 'Justificativa não encontrada ou sem permissão.');
        }

        $reviewer = null;
        if ($justification->approved_by) {
            $reviewer = $this->employeeModel->find($justification->approved_by);
        }

        return view('justifications/show', [
            'justificationEmployee' => $employee,
            'justification' => $justification,
            'reviewer' => $reviewer,
        ]);
    }

    /**
     * Aprovar justificativa (apenas admin/gestor)
     */
    public function approve($id)
    {
        $employee = $this->getAuthenticatedEmployee();
        if (!$employee || !$this->canManageJustifications($employee)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Sem permissão.'])->setStatusCode(403);
        }

        $justification = $this->justificationModel->find($id);
        if (!$justification || $justification->status !== 'pending') {
            return $this->response->setJSON(['success' => false, 'message' => 'Justificativa inválida.'])->setStatusCode(400);
        }

        try {
            $this->justificationModel->update($id, ['status' => 'approved']);
            $this->auditModel->log($employee['id'], 'JUSTIFICATION_APPROVED', 'justifications', $id, ['status' => 'pending'], ['status' => 'approved'], 'Justificativa aprovada', 'info');
            supportponto_log_event('info', 'justification', 'legacy_approved', [
                'employee_id' => $employee['id'] ?? null,
                'justification_id' => (int) $id,
            ]);

            return $this->response->setJSON(['success' => true, 'message' => 'Justificativa aprovada.']);
        } catch (\Throwable $e) {
            supportponto_log_exception('justification', 'legacy_approve_exception', $e, [
                'employee_id' => $employee['id'] ?? null,
                'justification_id' => (int) $id,
            ]);

            return $this->response->setJSON(['success' => false, 'message' => 'Erro interno ao aprovar justificativa.'])->setStatusCode(500);
        }
    }

    /**
     * Rejeitar justificativa (apenas admin/gestor)
     */
    public function reject($id)
    {
        $employee = $this->getAuthenticatedEmployee();
        if (!$employee || !$this->canManageJustifications($employee)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Sem permissão.'])->setStatusCode(403);
        }

        $reason = $this->request->getPost('reason');
        if (!$reason || trim($reason) === '') {
            return $this->response->setJSON(['success' => false, 'message' => 'Motivo da rejeição obrigatório.'])->setStatusCode(400);
        }

        $justification = $this->justificationModel->find($id);
        if (!$justification || $justification->status !== 'pending') {
            return $this->response->setJSON(['success' => false, 'message' => 'Justificativa inválida.'])->setStatusCode(400);
        }

        try {
            $this->justificationModel->update($id, ['status' => 'rejected', 'rejection_reason' => trim($reason)]);
            $this->auditModel->log($employee['id'], 'JUSTIFICATION_REJECTED', 'justifications', $id, ['status' => 'pending'], ['status' => 'rejected'], 'Justificativa rejeitada', 'warning');
            supportponto_log_event('warning', 'justification', 'legacy_rejected', [
                'employee_id' => $employee['id'] ?? null,
                'justification_id' => (int) $id,
            ]);

            return $this->response->setJSON(['success' => true, 'message' => 'Justificativa rejeitada.']);
        } catch (\Throwable $e) {
            supportponto_log_exception('justification', 'legacy_reject_exception', $e, [
                'employee_id' => $employee['id'] ?? null,
                'justification_id' => (int) $id,
            ]);

            return $this->response->setJSON(['success' => false, 'message' => 'Erro interno ao rejeitar justificativa.'])->setStatusCode(500);
        }
    }

    private function canManageJustifications(array $employee): bool
    {
        try {
            return Role::normalize((string) ($employee['role'] ?? Role::Funcionario->value))->canManageEmployees();
        } catch (\Throwable) {
            return false;
        }
    }

    private function canAutoApproveJustification(array $employee): bool
    {
        return $this->canManageJustifications($employee);
    }
}
