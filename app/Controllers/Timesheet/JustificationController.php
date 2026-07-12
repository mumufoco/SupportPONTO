<?php

namespace App\Controllers\Timesheet;

use App\Controllers\BaseController;
use App\Services\Timesheet\JustificationCoordinatorService;
use App\Support\Navigation\AdminFlowContextResolver;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class JustificationController extends BaseController
{
    private JustificationCoordinatorService $justificationCoordinator;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        $this->justificationCoordinator = JustificationCoordinatorService::createDefault();
        helper(['form', 'datetime', 'format']);
    }

    public function index()
    {
        $employee = $this->justificationCoordinator->authenticatedEmployee();
        if ($employee === null) {
            return redirect()->to(sp_login_url())->with('error', 'Você precisa estar autenticado.');
        }

        $filters = [
            'status' => (string) ($this->request->getGet('status') ?? ''),
            'type'   => (string) ($this->request->getGet('type') ?? ''),
            'search' => (string) ($this->request->getGet('search') ?? ''),
        ];

        $data = $this->justificationCoordinator->indexData($employee, $filters, 20);

        $counts = $data['counts'];
        $summary = [
            'total'    => $counts['all']      ?? 0,
            'pending'  => $counts['pending']  ?? 0,
            'approved' => $counts['approved'] ?? 0,
            'rejected' => $counts['rejected'] ?? 0,
        ];

        return view('justifications/index', [
            'employee'         => $employee,
            'justifications'   => $data['justifications'],
            'pager'            => $data['pager'],
            'filters'          => $filters,
            'summary'          => $summary,
            'navigationContext' => AdminFlowContextResolver::fromRequest($this->request, 'justifications'),
        ]);
    }

    public function create()
    {
        $employee = $this->justificationCoordinator->authenticatedEmployee();
        if ($employee === null) {
            return redirect()->to(sp_login_url())->with('error', 'Você precisa estar autenticado.');
        }

        // Sanitize date param: accept only Y-m-d strings to prevent XSS via JS injection
        $rawDate = (string) ($this->request->getGet('date') ?? '');
        $date    = preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate) ? $rawDate : '';

        return view('justifications/create', [
            'employee' => $employee,
            'date'     => $date,
        ]);
    }

    public function store()
    {
        $employee = $this->justificationCoordinator->authenticatedEmployee();
        if ($employee === null) {
            return redirect()->to(sp_login_url())->with('error', 'Você precisa estar autenticado.');
        }

        $rules = [
            'justification_date' => 'required|valid_date',
            'justification_type' => 'required|in_list[falta,atraso,saida-antecipada]',
            'category' => 'required|in_list[doenca,compromisso-pessoal,emergencia-familiar,outro]',
            'reason' => 'required|min_length[50]|max_length[500]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $files = $this->request->getFiles();
        $attachmentFiles = isset($files['attachments']) && is_array($files['attachments']) ? $files['attachments'] : [];

        $result = $this->justificationCoordinator->create($employee, security_sanitize($this->request->getPost() ?? []), $attachmentFiles);
        if (($result['success'] ?? false) !== true) {
            return redirect()->back()->withInput()->with('error', $result['error'] ?? 'Erro ao criar justificativa.');
        }

        return redirect()->to(sp_justifications_index_url())->with('success', $result['message'] ?? 'Justificativa enviada com sucesso!');
    }

    public function show($id = null)
    {
        $employee = $this->justificationCoordinator->authenticatedEmployee();
        if ($employee === null) {
            return redirect()->to(sp_login_url())->with('error', 'Você precisa estar autenticado.');
        }

        $data = $this->justificationCoordinator->showData((int) $id, $employee);
        if ($data === null) {
            return redirect()->to(sp_justifications_index_url())->with('error', 'Justificativa não encontrada.');
        }

        if (!empty($data['access_denied'])) {
            return redirect()->to(sp_justifications_index_url())->with('error', 'Acesso negado.');
        }

        $justification = $data['justification'];
        $attachmentsRaw = $justification->attachments ?? null;
        $attachments = [];
        if (is_string($attachmentsRaw)) {
            $decoded = json_decode($attachmentsRaw, true);
            if (is_array($decoded)) {
                $attachments = $decoded;
            }
        } elseif (is_array($attachmentsRaw)) {
            $attachments = $attachmentsRaw;
        }

        return view('justifications/show', [
            'employee'              => $employee,
            'justification'         => $justification,
            'justificationEmployee' => $data['justificationEmployee'],
            'reviewer'              => $data['reviewer'],
            'attachments'           => $attachments,
        ]);
    }

    public function approve($id = null)
    {
        $employee = $this->justificationCoordinator->authenticatedEmployee();
        if ($employee === null || !$this->justificationCoordinator->canReview($employee, (int) $id)) {
            return redirect()->to(sp_justifications_index_url())->with('error', 'Acesso negado.');
        }

        $result = $this->justificationCoordinator->approve((int) $id, $employee, $this->request->getPost('notes'));
        if (($result['success'] ?? false) !== true) {
            return redirect()->to(sp_justifications_index_url())->with('error', $result['error'] ?? 'Erro ao aprovar justificativa.');
        }

        return redirect()->to(sp_justifications_show_url((int) $id))->with('success', $result['message']);
    }

    public function reject($id = null)
    {
        $employee = $this->justificationCoordinator->authenticatedEmployee();
        if ($employee === null || !$this->justificationCoordinator->canReview($employee, (int) $id)) {
            return redirect()->to(sp_justifications_index_url())->with('error', 'Acesso negado.');
        }

        $result = $this->justificationCoordinator->reject((int) $id, $employee, $this->request->getPost('notes'));
        if (($result['success'] ?? false) !== true) {
            if (!empty($result['validation_error'])) {
                return redirect()->back()->with('error', $result['error']);
            }

            return redirect()->to(sp_justifications_index_url())->with('error', $result['error'] ?? 'Erro ao rejeitar justificativa.');
        }

        return redirect()->to(sp_justifications_show_url((int) $id))->with('success', $result['message']);
    }

    public function delete($id = null)
    {
        $employee = $this->justificationCoordinator->authenticatedEmployee();
        if ($employee === null) {
            return redirect()->to(sp_login_url())->with('error', 'Você precisa estar autenticado.');
        }

        $result = $this->justificationCoordinator->delete((int) $id, $employee);
        if (($result['success'] ?? false) !== true) {
            return redirect()->to(sp_justifications_index_url())->with('error', $result['error'] ?? 'Erro ao excluir justificativa.');
        }

        return redirect()->to(sp_justifications_index_url())->with('success', $result['message']);
    }
}
