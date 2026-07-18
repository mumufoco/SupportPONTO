<?php

namespace App\Controllers;

use App\Services\Audit\AuditCoordinatorService;
use App\Services\Audit\AuditMaintenanceService;
use App\Support\Navigation\AdminFlowContextResolver;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Audit Controller
 *
 * HTTP adapter for audit use cases:
 * - dashboard/listing
 * - data filtering/search
 * - event details
 * - CSV export
 * - AFD export + compliance
 * - maintenance (clear logs)
 */
class AuditController extends BaseController
{
    protected AuditCoordinatorService $auditCoordinatorService;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        $this->auditCoordinatorService = new AuditCoordinatorService();
        helper(['datetime', 'format']);
    }

    public function index(): ResponseInterface|string
    {
        $employee = $this->auditCoordinatorService->authenticatedEmployee();

        if (!$this->canAccessAuditArea($employee)) {
            return redirect()->to(route_to('dashboard'))
                ->with('error', 'Acesso negado. Apenas perfis autorizados podem acessar logs de auditoria.');
        }

        $viewData = $this->auditCoordinatorService->indexData($employee);

        return view('audit/index', [
            'employee' => $employee,
            'actions' => $viewData['actions'],
            'entities' => $viewData['entities'],
            'levels' => $viewData['levels'],
            'stats' => $viewData['stats'],
            'users' => $viewData['users'],
            'title' => 'Auditoria e Logs',
            'navigationContext' => AdminFlowContextResolver::fromRequest($this->request, 'audit'),
            'filters' => [],
            'events' => [],
            'auditScopeLimited' => $this->isLimitedAuditScope($employee),
        ]);
    }

    public function getData(): ResponseInterface
    {
        $employee = $this->auditCoordinatorService->authenticatedEmployee();

        if (!$this->canAccessAuditArea($employee)) {
            return $this->response->setJSON(['error' => 'Acesso negado'])->setStatusCode(403);
        }

        $payload = $this->auditCoordinatorService->datatableData($this->request->getPost() ?? [], $employee);
        $payload['csrf_hash'] = csrf_hash();

        return $this->response->setJSON($payload);
    }

    public function show(?int $id = null): ResponseInterface|string
    {
        $employee = $this->auditCoordinatorService->authenticatedEmployee();

        if (!$this->canAccessAuditDetails($employee)) {
            return redirect()->to(route_to('audit'))->with('error', 'Acesso negado.');
        }

        $logData = $this->auditCoordinatorService->showData((int) $id, $employee);
        if ($logData === null) {
            return redirect()->to(route_to('audit'))->with('error', 'Log de auditoria não encontrado ou fora do seu escopo.');
        }

        return view('audit/show', [
            'employee' => $employee,
            'log' => $logData['log'],
            'user' => $logData['user'],
            'oldData' => $logData['oldData'],
            'newData' => $logData['newData'],
        ]);
    }

    public function details(int $id): ResponseInterface
    {
        $employee = $this->auditCoordinatorService->authenticatedEmployee();

        if (!$this->canAccessAuditDetails($employee)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Acesso negado',
            ])->setStatusCode(403);
        }

        $details = $this->auditCoordinatorService->detailsData($id, $employee);
        if ($details === null) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Log não encontrado ou fora do escopo permitido',
            ])->setStatusCode(404);
        }

        return $this->response->setJSON([
            'success' => true,
            'log' => [
                'id' => $details['id'],
                'action' => $details['action'],
                'entity_type' => $details['entity_type'],
                'entity_id' => $details['entity_id'],
                'description' => $details['description'],
                'level' => $details['level'],
                'ip_address' => $details['ip_address'],
                'user_agent' => $details['user_agent'],
                'url' => $details['url'],
                'method' => $details['method'],
                'old_values' => $details['old_values'],
                'new_values' => $details['new_values'],
                'created_at' => $details['created_at'],
            ],
            'employee' => $details['employee'],
        ]);
    }

    private function canAccessAuditArea(?array $employee): bool
    {
        return $employee !== null && $this->authorizationService->canViewAuditLimited($employee);
    }

    private function canAccessAuditDetails(?array $employee): bool
    {
        return $this->canAccessAuditArea($employee) && $this->can('audit.view.details');
    }

    private function isLimitedAuditScope(array $employee): bool
    {
        return $this->authorizationService->canViewAuditLimited($employee) && ! $this->authorizationService->canViewAudit($employee);
    }

    public function export(): ResponseInterface
    {
        if (!$this->can('audit.export') && !$this->hasRole('admin')) {
            return redirect()->to(route_to('audit'))->with('error', 'Acesso negado.');
        }

        $dateFrom = (string) ($this->request->getGet('date_from') ?: date('Y-m-01'));
        $dateTo = (string) ($this->request->getGet('date_to') ?: date('Y-m-d'));
        $employee = $this->auditCoordinatorService->authenticatedEmployee();
        $export = $this->auditCoordinatorService->exportCsv($dateFrom, $dateTo, $employee !== null ? (int) $employee['id'] : null);

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=utf-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $export['filename'] . '"')
            ->setBody((string) $export['content']);
    }

    public function exportPdf(): ResponseInterface
    {
        if (!$this->can('audit.export') && !$this->hasRole('admin')) {
            return redirect()->to(route_to('audit'))->with('error', 'Acesso negado.');
        }

        $dateFrom = (string) ($this->request->getGet('date_from') ?: date('Y-m-01'));
        $dateTo = (string) ($this->request->getGet('date_to') ?: date('Y-m-d'));
        $employee = $this->auditCoordinatorService->authenticatedEmployee();
        $export = $this->auditCoordinatorService->exportPdf($dateFrom, $dateTo, $employee !== null ? (int) $employee['id'] : null);

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $export['filename'] . '"')
            ->setBody((string) $export['content']);
    }

    public function exportExcel(): ResponseInterface
    {
        if (!$this->can('audit.export') && !$this->hasRole('admin')) {
            return redirect()->to(route_to('audit'))->with('error', 'Acesso negado.');
        }

        $dateFrom = (string) ($this->request->getGet('date_from') ?: date('Y-m-01'));
        $dateTo = (string) ($this->request->getGet('date_to') ?: date('Y-m-d'));
        $employee = $this->auditCoordinatorService->authenticatedEmployee();
        $export = $this->auditCoordinatorService->exportExcel($dateFrom, $dateTo, $employee !== null ? (int) $employee['id'] : null);

        return $this->response
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $export['filename'] . '"')
            ->setBody((string) $export['content']);
    }

    public function clear(): ResponseInterface
    {
        $employee = $this->auditCoordinatorService->authenticatedEmployee();

        if (!$employee || !$this->hasRole('admin')) {
            return redirect()->to(route_to('audit'))->with('error', 'Acesso negado.');
        }

        $days = (int) ($this->request->getPost('days') ?: AuditMaintenanceService::LEGAL_MINIMUM_RETENTION_DAYS);

        try {
            $deletedCount = $this->auditCoordinatorService->clearLogs($days, (int) $employee['id']);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->to(route_to('audit'))
            ->with('success', "{$deletedCount} registro(s) de auditoria foram excluídos.");
    }

    public function generateAFD(): ResponseInterface
    {
        $employee = $this->auditCoordinatorService->authenticatedEmployee();

        if (!$employee || !$this->can('audit.export')) {
            return redirect()->to(route_to('audit'))->with('error', 'Acesso negado.');
        }

        $dateFrom = (string) ($this->request->getGet('date_from') ?: date('Y-m-01'));
        $dateTo = (string) ($this->request->getGet('date_to') ?: date('Y-m-d'));
        $export = $this->auditCoordinatorService->exportAfd((int) $employee['id'], $dateFrom, $dateTo);

        return $this->response
            ->setHeader('Content-Type', 'text/plain; charset=utf-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $export['filename'] . '"')
            ->setBody((string) $export['content']);
    }

    public function compliance(): ResponseInterface|string
    {
        $employee = $this->auditCoordinatorService->authenticatedEmployee();

        if (!$employee || !$this->can('audit.export')) {
            return redirect()->to(route_to('dashboard'))->with('error', 'Acesso negado.');
        }

        return view('audit/compliance', [
            'employee' => $employee,
            'compliance' => $this->auditCoordinatorService->complianceData(),
            'title' => 'Conformidade MTE 671/2021',
        ]);
    }
}
