<?php

namespace App\Controllers\API;

use App\Libraries\SupportCheck\SupportCheckApiClient;
use App\Models\UserConsentModel;
use App\Services\SupportCheck\SupportCheckAttendanceReportService;
use App\Services\SupportCheck\SupportCheckTermService;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourceController;

/**
 * Recebe solicitações de reenvio de relatório vindas do SupportCHECK.
 * Autenticado pelo SupportCheckCallbackFilter (Bearer = SUPPORTCHECK_CALLBACK_SECRET).
 */
class SupportCheckCallbackController extends ResourceController
{
    public function requestReport(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? [];

        $year  = isset($json['year'])  ? (int) $json['year']  : 0;
        $month = isset($json['month']) ? (int) $json['month'] : 0;
        $externalEmployeeId = $json['external_employee_id'] ?? null;

        if ($year < 2020 || $year > 2099 || $month < 1 || $month > 12) {
            return $this->respond(['message' => 'Ano ou mês inválido.'], 422);
        }

        $client = new SupportCheckApiClient();
        if (! $client->isEnabled()) {
            return $this->respond(['message' => 'Integração SupportCHECK desabilitada neste servidor.'], 503);
        }

        $service = new SupportCheckAttendanceReportService($client);

        try {
            if ($externalEmployeeId !== null && $externalEmployeeId !== '') {
                $result  = $service->sendMonthForEmployee((int) $externalEmployeeId, $year, $month);
                $message = 'Relatório enviado para o funcionário.';
                $data    = ['sent' => 1, 'failed' => 0, 'detail' => $result];
            } else {
                $result  = $service->sendMonthForAll($year, $month);
                $message = "Enviados: {$result['sent']} | Falhas: {$result['failed']}";
                $data    = $result;
            }

            return $this->respond(['message' => $message, 'data' => $data], 200);
        } catch (\Throwable $e) {
            return $this->respond(['message' => 'Erro: '.$e->getMessage()], 500);
        }
    }

    public function requestTerms(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? [];
        $externalEmployeeId = $json['external_employee_id'] ?? null;

        $client = new SupportCheckApiClient();
        if (! $client->isEnabled()) {
            return $this->respond(['message' => 'Integração SupportCHECK desabilitada neste servidor.'], 503);
        }

        $service = new SupportCheckTermService();

        try {
            $employeeId = ($externalEmployeeId !== null && $externalEmployeeId !== '') ? (int) $externalEmployeeId : null;
            $result = $service->sendPending($employeeId);
            $message = "Enviados: {$result['sent']} | Falhas: {$result['failed']}";

            return $this->respond(['message' => $message, 'data' => $result], 200);
        } catch (\Throwable $e) {
            return $this->respond(['message' => 'Erro: '.$e->getMessage()], 500);
        }
    }

    /**
     * Recebe do SupportCHECK o status de assinatura de um termo enviado
     * anteriormente por SupportCheckTermService::sendOne(). Fecha o loop
     * descrito na integracao: o PONTO manda o termo para assinar e este
     * endpoint e como ele fica sabendo se/quando foi assinado.
     */
    public function signatureStatus(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? [];

        $termId = trim((string) ($json['supportponto_term_id'] ?? ''));
        $status = trim((string) ($json['signature_status'] ?? ''));

        if ($termId === '') {
            return $this->respond(['message' => 'supportponto_term_id e obrigatorio.'], 422);
        }

        if (! in_array($status, UserConsentModel::SIGNATURE_STATUSES, true)) {
            return $this->respond([
                'message' => 'signature_status invalido. Valores aceitos: ' . implode(', ', UserConsentModel::SIGNATURE_STATUSES),
            ], 422);
        }

        $consentModel = new UserConsentModel();
        $consent = $consentModel->findByExternalTermId($termId);

        if ($consent === null) {
            return $this->respond(['message' => 'Termo nao encontrado para o supportponto_term_id informado.'], 404);
        }

        $updated = $consentModel->updateSignatureStatus($consent->id, [
            'supportcheck_document_id'   => $json['supportcheck_document_id'] ?? null,
            'supportcheck_internal_code' => $json['supportcheck_internal_code'] ?? null,
            'signature_status'           => $status,
            'signature_provider'         => $json['provider'] ?? null,
            'provider_document_id'       => $json['provider_document_id'] ?? null,
            'provider_signature_id'      => $json['provider_signature_id'] ?? null,
            'signed_at'                  => $json['signed_at'] ?? null,
            'signed_file_reference'      => $json['signed_file_reference'] ?? null,
            'signed_file_hash'           => $json['signed_file_hash'] ?? null,
            'audit_status'               => $json['audit_status'] ?? null,
            'signature_sync_message'     => $json['sync_message'] ?? null,
            'signature_updated_at'       => date('Y-m-d H:i:s'),
        ]);

        if (! $updated) {
            return $this->respond(['message' => 'Falha ao gravar status de assinatura.'], 500);
        }

        log_message('info', "[SupportCHECK] Status de assinatura recebido: consent_id={$consent->id} status={$status}");

        return $this->respond([
            'message' => 'Status de assinatura atualizado.',
            'data' => ['consent_id' => $consent->id, 'signature_status' => $status],
        ], 200);
    }
}
