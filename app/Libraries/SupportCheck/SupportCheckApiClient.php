<?php

namespace App\Libraries\SupportCheck;

use Config\SupportCheck as SupportCheckConfig;
use CodeIgniter\HTTP\CURLRequest;
use CodeIgniter\HTTP\Exceptions\HTTPException;

/**
 * Cliente HTTP para a API do SupportCHECK.
 *
 * Todos os métodos lançam \RuntimeException em caso de falha HTTP ou de rede.
 */
class SupportCheckApiClient
{
    private SupportCheckConfig $config;
    private CURLRequest $client;

    public function __construct(?SupportCheckConfig $config = null)
    {
        $this->config = $config ?? config('SupportCheck');
        $this->client = \Config\Services::curlrequest([
            'baseURI' => rtrim($this->config->baseUrl, '/') . '/',
            'timeout' => $this->config->timeout,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->config->apiToken,
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ],
        ]);
    }

    public function isEnabled(): bool
    {
        return $this->config->enabled
            && ! empty($this->config->baseUrl)
            && ! empty($this->config->apiToken);
    }

    /**
     * POST /api/v1/supportponto/company/sync
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function syncCompany(array $payload): array
    {
        return $this->post('api/v1/supportponto/company/sync', $payload);
    }

    /**
     * POST /api/v1/supportponto/positions/sync
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function syncPositions(array $payload): array
    {
        return $this->post('api/v1/supportponto/positions/sync', $payload);
    }

    /**
     * POST /api/v1/supportponto/departments/sync
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function syncDepartments(array $payload): array
    {
        return $this->post('api/v1/supportponto/departments/sync', $payload);
    }

    /**
     * POST /api/v1/supportponto/work-units/sync
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function syncWorkUnits(array $payload): array
    {
        return $this->post('api/v1/supportponto/work-units/sync', $payload);
    }

    /**
     * POST /api/v1/supportponto/employees/sync
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function syncEmployee(array $payload): array
    {
        return $this->post('api/v1/supportponto/employees/sync', $payload);
    }

    /**
     * POST /api/v1/supportponto/attendance-report
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */

    /**
     * Verifica se o SupportCHECK está acessível. Retorna true se responder HTTP < 500.
     */
    public function ping(): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }
        try {
            $response = $this->client->get('api/v1/ping', ['http_errors' => false, 'timeout' => 5]);
            return $response->getStatusCode() < 500;
        } catch (\Throwable $e) {
            return false;
        }
    }


    
    public function sendAttendanceReport(array $payload): array
    {
        return $this->post('api/v1/supportponto/attendance-report', $payload);
    }

    /**
     * POST /api/v1/supportponto/terms/created
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function sendTermCreated(array $payload): array
    {
        return $this->post('api/v1/supportponto/terms/created', $payload);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload): array
    {
        try {
            $response = $this->client->post($path, ['json' => $payload]);
        } catch (HTTPException $e) {
            throw new \RuntimeException(
                'SupportCheckApiClient: erro de rede em POST ' . $path . ' — ' . $e->getMessage(),
                0,
                $e
            );
        }

        $status = $response->getStatusCode();
        $body   = (string) $response->getBody();
        $json   = json_decode($body, true);

        if ($status < 200 || $status >= 300) {
            $message = is_array($json) ? ($json['message'] ?? $body) : $body;
            throw new \RuntimeException(
                'SupportCheckApiClient: HTTP ' . $status . ' em POST ' . $path . ' — ' . $message
            );
        }

        return is_array($json) ? $json : ['raw' => $body];
    }
}
