<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
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

    public function initController(
        \CodeIgniter\HTTP\RequestInterface $request,
        \CodeIgniter\HTTP\ResponseInterface $response,
        \Psr\Log\LoggerInterface $logger
    ): void {
        parent::initController($request, $response, $logger);
        $this->facialFraudAlertModel = new FacialFraudAlertModel();
    }

    // GET /admin/facial-fraud-alerts
    public function index(): mixed
    {
        $this->requireAnyRole(['admin', 'rh', 'gestor', 'dpo']);

        $status = (string) ($this->request->getGet('status') ?? 'pending');
        $status = in_array($status, ['pending', 'reviewed', 'dismissed'], true) ? $status : '';

        return view('admin/facial_fraud_alerts', [
            'alerts'        => $this->facialFraudAlertModel->listWithEmployee($status ?: null),
            'status'        => $status,
            'pendingCount'  => $this->facialFraudAlertModel->countByStatus(FacialFraudAlertModel::STATUS_PENDING),
            'currentUser'   => $this->currentUser,
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

        $decision = (string) $this->request->getPost('decision');
        if (! in_array($decision, [FacialFraudAlertModel::STATUS_REVIEWED, FacialFraudAlertModel::STATUS_DISMISSED], true)) {
            return redirect()->back()->with('error', 'Decisão inválida.');
        }

        $notes = trim((string) $this->request->getPost('review_notes'));

        $this->facialFraudAlertModel->markReviewed($id, (int) ($this->currentUser->id ?? 0), $decision, $notes);

        return redirect()->to(site_url('admin/facial-fraud-alerts'))
            ->with('success', $decision === FacialFraudAlertModel::STATUS_REVIEWED ? 'Alerta marcado como revisado.' : 'Alerta descartado.');
    }
}
