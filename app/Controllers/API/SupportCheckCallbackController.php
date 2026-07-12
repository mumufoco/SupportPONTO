<?php

namespace App\Controllers\API;

use App\Libraries\SupportCheck\SupportCheckApiClient;
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
}
