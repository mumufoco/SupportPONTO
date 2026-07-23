<?php

namespace App\Controllers\API;

use App\Controllers\BaseController;
use App\Models\EmployeeModel;
use App\Services\Biometric\DeepFaceService;
use App\Services\Monitoring\SystemObservabilityService;
use App\Services\Queue\AsyncJobService;
use App\Traits\ObservabilityTrait;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Endpoints públicos mínimos e proxies DeepFace protegidos por rota.
 */
class ApiController extends BaseController
{
    use ObservabilityTrait;

    protected EmployeeModel $employeeModel;
    protected DeepFaceService $deepFaceService;
    protected SystemObservabilityService $observabilityService;
    protected AsyncJobService $asyncJobService;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        $this->employeeModel = new EmployeeModel();
        $this->deepFaceService = new DeepFaceService();
        $this->observabilityService = new SystemObservabilityService();
        $this->asyncJobService = new AsyncJobService();
    }

    /**
     * POST /api/validate-code
     *
     * Endpoint legado para terminais. Mantém compatibilidade, mas reduz exposição:
     * não retorna nome, departamento ou cargo do colaborador. Use endpoints OAuth2
     * autenticados para obter dados cadastrais completos.
     */
    public function validateCode(): ResponseInterface
    {
        $code = $this->requestValue('code');
        if (! is_string($code) || trim($code) === '') {
            return $this->apiError('validation_error', 'Código não fornecido.', 400);
        }

        $code = strtoupper(preg_replace('/[^A-Z0-9_-]/i', '', trim($code)) ?? '');
        if ($code === '' || strlen($code) > 64) {
            return $this->apiError('validation_error', 'Código inválido.', 400);
        }

        $employee = $this->employeeModel
            ->select('id, unique_code, active')
            ->where('unique_code', $code)
            ->where('active', true)
            ->first();

        if (! $employee) {
            return $this->apiError('invalid_employee_code', 'Código inválido ou colaborador inativo.', 404);
        }

        return $this->apiSuccess([
            'employee_id' => (int) $employee->id,
            'validated' => true,
        ], 'Código validado.', 200, 'employee_code_valid');
    }

    /**
     * GET /api/health
     *
     * Health público sem versão, ambiente, stack, DB name, hostname ou dependências sensíveis.
     */
    public function health(): ResponseInterface
    {
        $health = $this->observabilityService->readiness();
        $statusCode = ($health['status'] ?? 'not_ready') === 'ready' ? 200 : 503;

        return $this->attachResponseContext($this->response, true)
            ->setHeader('Cache-Control', 'no-store, max-age=0')
            ->setJSON([
                'success' => $statusCode === 200,
                'code' => $statusCode === 200 ? 'ready' : 'not_ready',
                'status' => $health['status'] ?? 'not_ready',
                'timestamp' => $health['timestamp'] ?? gmdate(DATE_ATOM),
                'services' => [
                    'database' => $health['checks']['database']['status'] ?? 'error',
                    // Nome público 'filesystem' mantido por compatibilidade; a checagem
                    // real vive na chave 'writable' de SystemHealthCheckService::readiness().
                    'filesystem' => $health['checks']['writable']['status'] ?? 'error',
                    'storage' => $health['checks']['storage']['status'] ?? 'error',
                    'encryption' => $health['checks']['encryption']['status'] ?? 'error',
                ],
                'alerts_count' => $health['alerts_count'] ?? 0,
                'meta' => [
                    'request_id' => $this->getRequestId(),
                    'timestamp' => gmdate(DATE_ATOM),
                ],
            ])
            ->setStatusCode($statusCode);
    }

    /**
     * POST /api/v1/deepface/enroll
     */
    public function deepfaceEnroll(): ResponseInterface
    {
        try {
            $employeeId = (int) $this->requestValue('employee_id', 0);
            $image = $this->requestValue('image', '');

            if ($employeeId <= 0 || ! is_string($image) || trim($image) === '') {
                return $this->apiError('validation_error', 'Parâmetros insuficientes: employee_id e image são obrigatórios.', 400);
            }

            $employee = $this->employeeModel->find($employeeId);
            if (! $employee || ! (bool) ($employee->active ?? false)) {
                return $this->apiError('employee_not_found', 'Colaborador não encontrado ou inativo.', 404);
            }

            $job = $this->asyncJobService->enqueue(AsyncJobService::TYPE_FACE_ENROLL, [
                'employee_id' => $employeeId,
                'photo' => $image,
                'force' => false,
                'actor_id' => $employeeId,
                'actor_role' => 'api_deepface_proxy',
                'request_ip' => $this->request->getIPAddress(),
                'user_agent' => $this->request->getUserAgent()->getAgentString(),
            ], [
                'employee_id' => $employeeId,
                'queue' => 'biometric',
                'priority' => 80,
                'max_attempts' => 3,
            ]);

            helper('operational_link');

            return $this->apiSuccess([
                'queued' => true,
                'job_id' => $job['job_id'] ?? null,
                'status_url' => sp_api_async_job_status_url((string) ($job['job_id'] ?? '')),
            ], 'Cadastro facial enfileirado para processamento.', 202, 'deepface_enroll_queued');
        } catch (Throwable $e) {
            log_message('error', '[API] DeepFace enroll failure: ' . $e->getMessage());

            return $this->apiError('internal_error', 'Erro interno ao processar cadastro facial.', 500);
        }
    }

    /**
     * POST /api/v1/deepface/recognize
     */
    public function deepfaceRecognize(): ResponseInterface
    {
        try {
            $image = $this->requestValue('image', '');
            if (! is_string($image) || trim($image) === '') {
                return $this->apiError('validation_error', 'Imagem não fornecida.', 400);
            }

            $result = $this->deepFaceService->recognizeFace($image);
            if (($result['success'] ?? false) === true) {
                return $this->apiSuccess([
                    'employee_id' => isset($result['employee_id']) ? (int) $result['employee_id'] : null,
                    'confidence' => $result['confidence'] ?? null,
                ], 'Face reconhecida.', 200, 'deepface_recognized');
            }

            return $this->apiError('deepface_not_recognized', $result['message'] ?? 'Face não reconhecida.', 404);
        } catch (Throwable $e) {
            log_message('error', '[API] DeepFace recognize failure: ' . $e->getMessage());

            return $this->apiError('internal_error', 'Erro interno ao processar reconhecimento facial.', 500);
        }
    }

    private function requestValue(string $key, mixed $default = null): mixed
    {
        $json = $this->request->getJSON(true);
        if (is_array($json) && array_key_exists($key, $json)) {
            return Services::apiPayloadSanitizer()->sanitizeValue($json[$key], $key);
        }

        $post = $this->request->getPost($key);
        if ($post !== null) {
            return Services::apiPayloadSanitizer()->sanitizeValue($post, $key);
        }

        return $default;
    }

    private function apiSuccess(array $data, string $message, int $statusCode = 200, string $code = 'ok'): ResponseInterface
    {
        return $this->attachResponseContext(
            $this->response->setStatusCode($statusCode)->setJSON($this->apiSuccessPayload($data, $message, $code)),
            $statusCode >= 400
        );
    }

    private function apiError(string $code, string $message, int $statusCode = 400, ?array $errors = null): ResponseInterface
    {
        return $this->attachResponseContext(
            $this->response->setStatusCode($statusCode)->setJSON($this->apiErrorPayload($code, $message, $errors)),
            true
        );
    }
}
