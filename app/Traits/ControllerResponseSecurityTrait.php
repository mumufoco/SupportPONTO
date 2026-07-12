<?php

namespace App\Traits;

trait ControllerResponseSecurityTrait
{
    protected function setSuccess(string $message): void
    {
        if ($this->session) {
            $this->session->setFlashdata('success', $message);
        }
    }

    protected function setError(string $message): void
    {
        if ($this->session) {
            $this->session->setFlashdata('error', $message);
        }
    }

    protected function setWarning(string $message): void
    {
        if ($this->session) {
            $this->session->setFlashdata('warning', $message);
        }
    }

    protected function setInfo(string $message): void
    {
        if ($this->session) {
            $this->session->setFlashdata('info', $message);
        }
    }

    protected function respondWithJson(mixed $data, int $statusCode = 200): \CodeIgniter\HTTP\ResponseInterface
    {
        if (is_array($data) && !isset($data['csrf_hash'])) {
            $data['csrf_hash'] = csrf_hash();
        }
        return $this->response->setStatusCode($statusCode)->setJSON($data);
    }

    protected function respondSuccess(mixed $data = [], string $message = 'Success', int $statusCode = 200): \CodeIgniter\HTTP\ResponseInterface
    {
        return $this->respondWithJson([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    protected function respondError(string $message = 'Error', mixed $errors = null, int $statusCode = 400): \CodeIgniter\HTTP\ResponseInterface
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $this->sanitizeForLog($errors);
        }

        return $this->attachResponseContext($this->respondWithJson($response, $statusCode), $statusCode >= 400);
    }

    protected function logAudit(
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null,
        string $level = 'info'
    ): void {
        try {
            $auditModel = new \App\Models\AuditModel();
            $auditModel->log(
                $this->currentUser->id ?? null,
                $action,
                $entityType,
                $entityId,
                $oldValues,
                $newValues,
                $description,
                $level
            );
        } catch (\Throwable $e) {
            $this->logSecurityEvent('error', 'BaseController: audit logging failed', [
                'exception' => $e->getMessage(),
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ]);
        }
    }

    protected function getClientIp(): string
    {
        return $this->request->getIPAddress();
    }

    protected function getUserAgent(): string
    {
        return $this->request->getUserAgent()->getAgentString();
    }

    protected function validateCsrf(): bool
    {
        $token = $this->request->getHeaderLine('X-CSRF-TOKEN');
        if ($token === '') {
            $token = (string) $this->request->getPost(csrf_token());
        }

        return csrf_hash() === $token;
    }

    protected function requireHttps(string $message = 'Esta operação requer uma conexão HTTPS segura.'): mixed
    {
        if (!$this->request->isSecure()) {
            log_message('warning', 'HTTPS_REQUIRED: Insecure connection attempt from IP ' . $this->getClientIp());

            $this->logAudit(
                'HTTPS_VIOLATION',
                'security',
                null,
                null,
                ['ip' => $this->getClientIp(), 'url' => current_url()],
                'Tentativa de acesso sem HTTPS em operação sensível',
                'warning'
            );

            if ($this->request->isAJAX() || $this->request->getHeaderLine('Accept') === 'application/json') {
                return $this->respondError($message, null, 403);
            }

            $this->setError($message);
            return redirect()->back();
        }

        return null;
    }

    protected function isHttps(): bool
    {
        return $this->request->isSecure();
    }
}
