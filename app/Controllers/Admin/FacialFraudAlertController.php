<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\EmployeeModel;
use App\Models\FacialFraudAlertModel;

/**
 * Painel de revisão dos alertas de possível fraude gerados pela segunda
 * camada de verificação facial (ver TimesheetPunchRegistrationService::
 * validateFaceSecondFactor()): quando a foto ao vivo não corresponde ao
 * cadastro biométrico do funcionário já identificado por código/CPF/QR/
 * digital, o ponto é registrado normalmente e um alerta fica pendente aqui
 * para gestor/RH decidir se é um caso a investigar (histórico útil para
 * eventual processo disciplinar).
 */
class FacialFraudAlertController extends BaseController
{
    protected FacialFraudAlertModel $facialFraudAlertModel;
    protected EmployeeModel $employeeModel;
    private const PER_PAGE = 30;

    public function initController(
        \CodeIgniter\HTTP\RequestInterface $request,
        \CodeIgniter\HTTP\ResponseInterface $response,
        \Psr\Log\LoggerInterface $logger
    ): void {
        parent::initController($request, $response, $logger);
        $this->facialFraudAlertModel = new FacialFraudAlertModel();
        $this->employeeModel = new EmployeeModel();
    }

    // GET /admin/facial-fraud-alerts
    public function index(): mixed
    {
        $this->requireAnyRole(['admin', 'rh', 'gestor', 'dpo']);

        $status = (string) ($this->request->getGet('status') ?? 'pending');
        $status = in_array($status, ['pending', 'reviewed', 'dismissed'], true) ? $status : '';

        $department = $this->resolveDepartmentScope();
        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $offset = (self::PER_PAGE) * ($page - 1);

        $total = $this->facialFraudAlertModel->countScoped($status ?: null, null, $department);

        return view('admin/facial_fraud_alerts', [
            'alerts'          => $this->facialFraudAlertModel->listWithEmployee($status ?: null, null, $department, self::PER_PAGE, $offset),
            'status'          => $status,
            'page'            => $page,
            'perPage'         => self::PER_PAGE,
            'total'           => $total,
            'pendingCount'    => $this->facialFraudAlertModel->countByStatus(FacialFraudAlertModel::STATUS_PENDING, $department),
            'reviewedCount'   => $this->facialFraudAlertModel->countByStatus(FacialFraudAlertModel::STATUS_REVIEWED, $department),
            'dismissedCount'  => $this->facialFraudAlertModel->countByStatus(FacialFraudAlertModel::STATUS_DISMISSED, $department),
            'departmentScope' => $department,
            'currentUser'     => $this->currentUser,
        ]);
    }

    // POST /admin/facial-fraud-alerts/{id}/review
    public function review(int $id): mixed
    {
        $this->requireAnyRole(['admin', 'rh', 'gestor', 'dpo']);

        $alert = $this->facialFraudAlertModel->find($id);
        if (! $alert) {
            return redirect()->back()->with('error', 'Alerta não encontrado.');
        }

        // Gestor so pode decidir sobre alertas da propria equipe - sem isso, a
        // restricao da listagem seria so cosmetica (bastaria enviar o POST direto
        // para revisar/descartar um alerta de outro departamento).
        $department = $this->resolveDepartmentScope();
        if ($department !== null) {
            $employee = $this->employeeModel->find((int) $alert->employee_id);
            if (! $employee || ($employee->department ?? null) !== $department) {
                return redirect()->to(site_url('admin/facial-fraud-alerts'))
                    ->with('error', 'Você não tem permissão para revisar este alerta.');
            }
        }

        $decision = (string) $this->request->getPost('decision');
        if (! in_array($decision, [FacialFraudAlertModel::STATUS_REVIEWED, FacialFraudAlertModel::STATUS_DISMISSED], true)) {
            return redirect()->back()->with('error', 'Decisão inválida.');
        }

        $notes = trim((string) $this->request->getPost('review_notes'));

        $this->facialFraudAlertModel->markReviewed($id, (int) ($this->currentUser->id ?? 0), $decision, $notes);

        return redirect()->to(site_url('admin/facial-fraud-alerts'))
            ->with('success', $decision === FacialFraudAlertModel::STATUS_REVIEWED ? 'Alerta marcado como revisado.' : 'Alerta descartado.');
    }

    /** null = sem restrição (admin/rh/dpo); string = departamento do gestor logado. */
    private function resolveDepartmentScope(): ?int
    {
        $role = (string) ($this->currentUser->role ?? '');
        if ($role !== 'gestor') {
            return null;
        }

        // Sentinel 0 (nunca um id real) em vez de null quando o gestor não tem
        // department_id configurado -- sem isto, o filtro era pulado inteiramente
        // e um gestor mal configurado via os alertas de TODOS os departamentos.
        $departmentId = $this->currentUser->department_id ?? null;

        return ! empty($departmentId) ? (int) $departmentId : 0;
    }
}
