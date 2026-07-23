<?php

namespace App\Controllers;

use App\Services\LGPD\ConsentService;
use App\Services\LGPD\LGPDControllerActionService;
use App\Services\Queue\AsyncJobService;
use App\Models\UserConsentModel;
use App\Models\ConsentTermModel;
use App\Models\EmployeeModel;
use App\Services\LGPD\PersonalDataInventoryService;
use App\Services\LGPD\DataRetentionPolicyService;
use App\Services\LGPD\DataSubjectRightsService;
use App\Services\LGPD\BiometricPrivacyGuardService;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * LGPDController
 *
 * Controller for LGPD compliance features
 * - Consent management
 * - Data portability (Art. 19)
 * - Data export requests
 */
class LGPDController extends BaseController
{
    protected ConsentService $consentService;
    protected LGPDControllerActionService $lgpdControllerActionService;
    protected AsyncJobService $asyncJobService;
    protected UserConsentModel $consentModel;
    protected EmployeeModel $employeeModel;
    protected PersonalDataInventoryService $inventoryService;
    protected DataRetentionPolicyService $retentionPolicyService;
    protected DataSubjectRightsService $dataSubjectRightsService;
    protected BiometricPrivacyGuardService $biometricPrivacyGuardService;
    protected ConsentTermModel $consentTermModel;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        
        $this->consentService = new ConsentService();
        $this->lgpdControllerActionService = new LGPDControllerActionService();
        $this->asyncJobService = new AsyncJobService();
        $this->consentModel = new UserConsentModel();
        $this->employeeModel = new EmployeeModel();
        $this->inventoryService = new PersonalDataInventoryService();
        $this->retentionPolicyService = new DataRetentionPolicyService();
        $this->dataSubjectRightsService = new DataSubjectRightsService();
        $this->biometricPrivacyGuardService = new BiometricPrivacyGuardService();
        $this->consentTermModel = new ConsentTermModel();
    }

    /**
     * Consent portal - Main page
     */
    public function consents(): ResponseInterface|string
    {
        $employeeId = session()->get('user_id');

        if (!$employeeId) {
            return redirect()->to(route_to('login'))->with('error', 'Você precisa estar autenticado');
        }

        $employee = $this->employeeModel->find($employeeId);
        $consents = $this->consentService->getEmployeeConsents($employeeId);

        $consentTypes = $this->lgpdControllerActionService->consentTypes();

        // Load canonical consent terms from DB to show full legal text in grant modal
        $consentTerms = [];
        foreach (array_keys($consentTypes) as $termType) {
            $term = $this->consentTermModel->getActiveTerm($termType);
            if ($term) {
                $consentTerms[$termType] = [
                    'title'       => $term->title,
                    'body'        => $term->body,
                    'legal_basis' => $term->legal_basis,
                    'version'     => $term->version,
                ];
            }
        }

        $subjectRequests = [];
        if (\Config\Database::connect()->tableExists('lgpd_subject_requests')) {
            $subjectRequests = \Config\Database::connect()->table('lgpd_subject_requests')
                ->where('employee_id', $employeeId)
                ->orderBy('created_at', 'DESC')
                ->get()->getResult();
        }

        return view('lgpd/consents', [
            'employee' => $employee,
            'consents' => $consents,
            'consentTypes' => $consentTypes,
            'consentTerms' => $consentTerms,
            'dataInventory' => $this->inventoryService->summary(),
            'retentionPolicies' => $this->retentionPolicyService->policies(),
            'subjectRequests' => $subjectRequests,
            'title' => 'Gestão de Consentimentos LGPD',
        ]);
    }

    /**
     * List export requests for current user
     */
    public function exportData(): ResponseInterface
    {
        $employeeId = session()->get('user_id');

        if (!$employeeId) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Não autenticado',
            ])->setStatusCode(401);
        }

        $exports = $this->lgpdControllerActionService->listExportsForEmployee((int) $employeeId);

        return $this->response->setJSON([
            'success' => true,
            'exports' => $exports,
        ]);
    }

    /**
     * Grant consent
     */
    public function grantConsent(): ResponseInterface
    {
        $employeeId = session()->get('user_id');

        if (!$employeeId) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Não autenticado',
            ])->setStatusCode(401);
        }

        $consentType = $this->request->getPost('consent_type');
        $purpose = $this->request->getPost('purpose');
        $legalBasis = $this->request->getPost('legal_basis');

        if (!$consentType || !$purpose) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Dados incompletos',
            ])->setStatusCode(400);
        }

        // Always use the canonical term from DB (never trust client-sent text)
        $term = $this->consentTermModel->getActiveTerm($consentType);
        if (!$term) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Termo de consentimento n\u00e3o encontrado para este tipo.',
            ])->setStatusCode(422);
        }
        $consentText = $term->body;
        $version = $term->version;
        if ($term->legal_basis) {
            $legalBasis = $term->legal_basis;
        }

        $result = $this->consentService->grant(
            $employeeId,
            $consentType,
            $purpose,
            $consentText,
            $legalBasis,
            $version
        );

        $result['csrf_hash'] = csrf_hash();
        return $this->response->setJSON($result);
    }

    /**
     * Revoke consent
     */
    public function revokeConsent(): ResponseInterface
    {
        $employeeId = session()->get('user_id');

        if (!$employeeId) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Não autenticado',
            ])->setStatusCode(401);
        }

        $consentType = $this->request->getPost('consent_type');
        $reason = $this->request->getPost('reason');

        if (!$consentType) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Tipo de consentimento não informado',
            ])->setStatusCode(400);
        }

        $result = $this->consentService->revoke($employeeId, $consentType, $reason);

        $result['csrf_hash'] = csrf_hash();
        return $this->response->setJSON($result);
    }

    /**
     * Request data export (LGPD Art. 19)
     */
    public function requestExport(): ResponseInterface
    {
        $employeeId = session()->get('user_id');

        if (!$employeeId) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Não autenticado',
            ])->setStatusCode(401);
        }

        $employee = $this->employeeModel->find($employeeId);

        if (!$employee) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Colaborador não encontrado',
            ])->setStatusCode(404);
        }

        // Check rate limiting (1 export per 24 hours)
        $recentExport = $this->lgpdControllerActionService->recentExportForEmployee((int) $employeeId);

        if ($recentExport) {
            $nextAvailable = date('d/m/Y H:i', strtotime($recentExport->created_at . ' +24 hours'));

            return $this->response->setJSON([
                'success' => false,
                'message' => "Você já solicitou uma exportação recentemente. Próxima disponível em: {$nextAvailable}",
            ])->setStatusCode(429);
        }

        $job = $this->asyncJobService->enqueue(AsyncJobService::TYPE_LGPD_EXPORT, [
            'employee_id' => (int) $employeeId,
            'requested_by' => $employee->email,
        ], [
            'employee_id' => (int) $employeeId,
            'queue' => 'compliance',
            'priority' => 55,
            'max_attempts' => 3,
        ]);

        return $this->response->setJSON([
            'success' => true,
            'queued' => true,
            'message' => 'Sua exportação LGPD foi enfileirada e será processada em background.',
            'job_id' => $job['job_id'],
            'job_status_url' => sp_async_job_status_url((string) $job['job_id']),
        ])->setStatusCode(202);
    }

    /**
     * Download export file
     */
    public function downloadExport(string $exportId): ResponseInterface
    {
        $employeeId = session()->get('user_id');

        if (!$employeeId) {
            return redirect()->to(route_to('login'))->with('error', 'Você precisa estar autenticado');
        }

        // Validate export belongs to user
        $export = $this->lgpdControllerActionService->completedExportForDownload($exportId, (int) $employeeId);

        if (!$export) {
            return redirect()->to(site_url('lgpd/consents'))->with('error', 'Exportação não encontrada');
        }

        // Check expiration
        if (strtotime($export->expires_at) < time()) {
            return redirect()->to(site_url('lgpd/consents'))->with('error', 'Exportação expirada. Solicite uma nova.');
        }

        $filePath = WRITEPATH . 'exports/lgpd/' . basename($exportId) . '.zip';

        if (!file_exists($filePath)) {
            return redirect()->to(site_url('lgpd/consents'))->with('error', 'Arquivo não encontrado');
        }

        // Update download count
        $this->lgpdControllerActionService->registerDownload($export);

        // Return file for download
        return $this->response->download($filePath, null)->setFileName('meus_dados_lgpd.zip');
    }


    public function inventory(): ResponseInterface
    {
        $employeeId = session()->get('user_id');
        if (!$employeeId) {
            return $this->response->setJSON(['success' => false, 'message' => 'Não autenticado'])->setStatusCode(401);
        }

        return $this->response->setJSON([
            'success' => true,
            'inventory' => $this->inventoryService->summary(),
        ]);
    }

    public function retentionPolicies(): ResponseInterface
    {
        $employeeId = session()->get('user_id');
        if (!$employeeId) {
            return $this->response->setJSON(['success' => false, 'message' => 'Não autenticado'])->setStatusCode(401);
        }

        return $this->response->setJSON([
            'success' => true,
            'policies' => $this->retentionPolicyService->policies(),
        ]);
    }

    public function requestPrivacyAction(): ResponseInterface
    {
        $employeeId = (int) session()->get('user_id');
        if (!$employeeId) {
            return $this->response->setJSON(['success' => false, 'message' => 'Não autenticado'])->setStatusCode(401);
        }

        $type = (string) ($this->request->getPost('request_type') ?: 'deactivation');
        $reason = trim((string) ($this->request->getPost('reason') ?: 'Solicitação do titular via portal LGPD.'));

        $result = $this->dataSubjectRightsService->registerSubjectRequest($employeeId, $type, $reason, $employeeId);

        return $this->response->setJSON($result)->setStatusCode(($result['success'] ?? false) ? 202 : 400);
    }

    public function adminDeactivateEmployee(int $employeeId): ResponseInterface
    {
        if (!$this->hasAnyRole(['admin', 'dpo'])) {
            return $this->response->setJSON(['success' => false, 'message' => 'Acesso negado'])->setStatusCode(403);
        }

        $reason = trim((string) ($this->request->getPost('reason') ?: 'Desativação solicitada pelo DPO/Admin.'));
        $actorId = (int) (session()->get('user_id') ?: 0) ?: null;
        $result = $this->dataSubjectRightsService->deactivateEmployeeForPrivacy($employeeId, $actorId, $reason);

        return $this->response->setJSON($result)->setStatusCode(($result['success'] ?? false) ? 200 : 400);
    }

    public function adminAnonymizeEmployee(int $employeeId): ResponseInterface
    {
        if (!$this->hasAnyRole(['admin', 'dpo'])) {
            return $this->response->setJSON(['success' => false, 'message' => 'Acesso negado'])->setStatusCode(403);
        }

        $confirmation = trim((string) $this->request->getPost('confirmation'));
        $expected = 'ANONIMIZAR TITULAR ' . $employeeId;
        if ($confirmation !== $expected) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Confirmação inválida. Use exatamente: ' . $expected,
            ])->setStatusCode(422);
        }

        $reason = trim((string) ($this->request->getPost('reason') ?: 'Anonimização confirmada pelo DPO/Admin.'));
        $actorId = (int) (session()->get('user_id') ?: 0) ?: null;
        $result = $this->dataSubjectRightsService->anonymizeEmployeeWhenAllowed($employeeId, $actorId, $reason);

        return $this->response->setJSON($result)->setStatusCode(($result['success'] ?? false) ? 200 : 400);
    }

    public function purgeBiometrics(int $employeeId): ResponseInterface
    {
        if (!$this->hasAnyRole(['admin', 'dpo'])) {
            return $this->response->setJSON(['success' => false, 'message' => 'Acesso negado'])->setStatusCode(403);
        }

        $types = $this->request->getPost('types') ?: ['face', 'fingerprint'];
        if (is_string($types)) {
            $types = array_filter(array_map('trim', explode(',', $types)));
        }

        $reason = trim((string) ($this->request->getPost('reason') ?: 'Expurgo biométrico por solicitação LGPD.'));
        $actorId = (int) (session()->get('user_id') ?: 0) ?: null;
        $result = $this->biometricPrivacyGuardService->purgeEmployeeBiometrics($employeeId, (array) $types, $reason, $actorId);

        return $this->response->setJSON($result)->setStatusCode(($result['success'] ?? false) ? 200 : 400);
    }

    /**
     * Admin: ANPD Report
     * Requires admin/DPO role
     */
    public function anpdReport(): string
    {
        $this->requireAnyRole(['admin', 'dpo']);

        $startDate = $this->request->getGet('start_date') ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $this->request->getGet('end_date') ?? date('Y-m-d');

        $report = $this->consentService->generateANPDReport($startDate, $endDate);

        return view('lgpd/anpd_report', [
            'report' => $report,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'title' => 'Relatório ANPD',
        ]);
    }

    /**
     * Admin: Export ANPD report to PDF
     */
    public function exportANPDReport(): ResponseInterface
    {
        if (!$this->hasAnyRole(['admin', 'dpo'])) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Acesso negado',
            ])->setStatusCode(403);
        }

        $startDate = $this->request->getGet('start_date') ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $this->request->getGet('end_date') ?? date('Y-m-d');

        $report = $this->consentService->generateANPDReport($startDate, $endDate);

        // Geração inline via Gotenberg (motor de PDF do sistema — ver
        // App\Services\Pdf\GotenbergPdfDocument) para manter entrega imediata
        // do relatório ANPD sem depender de fila assíncrona.
        $pdf = new \App\Services\Pdf\GotenbergPdfDocument('P', 'mm', 'A4', true, 'UTF-8', false);

        $pdf->SetCreator('Sistema de Ponto Eletrônico');
        $pdf->SetAuthor(env('COMPANY_NAME', 'Empresa'));
        $pdf->SetTitle('Relatório ANPD - Atividades de Tratamento de Dados');

        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);

        $pdf->AddPage();

        $html = view('lgpd/anpd_report_pdf', ['report' => $report]);
        $pdf->writeHTML($html, true, false, true, false, '');

        $fileName = 'relatorio_anpd_' . $startDate . '_' . $endDate . '.pdf';

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setBody($pdf->Output($fileName, 'S'));
    }

    public function resolveSubjectRequest(int $employeeId): ResponseInterface
    {
        if (!$this->hasAnyRole(['admin', 'dpo'])) {
            return $this->response->setJSON(['success' => false, 'message' => 'Acesso negado'])->setStatusCode(403);
        }

        $requestId = trim((string) ($this->request->getPost('request_id') ?: ''));
        $notes     = trim((string) ($this->request->getPost('resolution_notes') ?: $this->request->getPost('reason') ?: ''));
        $status    = in_array($this->request->getPost('status'), ['rejected','resolved'], true)
            ? $this->request->getPost('status')
            : 'resolved';

        if (!$requestId || !$notes) {
            return $this->response->setJSON(['success' => false, 'message' => 'request_id e notas de resolução são obrigatórios.'])->setStatusCode(400);
        }

        $db = \Config\Database::connect();
        $req = $db->table('lgpd_subject_requests')
            ->where('request_id', $requestId)
            ->where('employee_id', $employeeId)
            ->get()->getRowObject();

        if (!$req) {
            return $this->response->setJSON(['success' => false, 'message' => 'Solicitação não encontrada.'])->setStatusCode(404);
        }

        $db->table('lgpd_subject_requests')
            ->where('id', $req->id)
            ->update([
                'status'           => $status,
                'resolution_notes' => $notes,
                'resolved_at'      => date('Y-m-d H:i:s'),
                'updated_at'       => date('Y-m-d H:i:s'),
            ]);

        $actorId = (int) (session()->get('user_id') ?: 0);
        $this->dataSubjectRightsService->registerSubjectRequest(
            $employeeId,
            'request_' . $status,
            "Solicitação {$requestId} {$status} por admin: {$notes}",
            $actorId
        );

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Solicitação ' . ($status === 'resolved' ? 'resolvida' : 'rejeitada') . ' com sucesso.',
        ]);
    }

}
