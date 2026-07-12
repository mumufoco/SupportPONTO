<?php

namespace App\Traits;

use App\Models\EmployeeModel;

trait ControllerSessionContextTrait
{
    protected function initializeSession(): void
    {
        try {
            $sessionSavePath = defined('WRITEPATH')
                ? WRITEPATH . 'session'
                : dirname(FCPATH) . '/writable/session';

            if (!$this->ensureSessionDirectoryExists($sessionSavePath)) {
                throw new \RuntimeException('Session directory is not writable: ' . $sessionSavePath);
            }

            $this->session = \Config\Services::session();

            if (method_exists($this->session, 'isStarted') && !$this->session->isStarted()) {
                if (!$this->session->start()) {
                    throw new \RuntimeException('Failed to start session');
                }
            }

            log_message('debug', 'Session initialized successfully');
        } catch (\Throwable $e) {
            log_message('critical', 'FATAL: Session initialization failed - ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'ip' => $this->request->getIPAddress(),
            ]);

            throw new \RuntimeException(
                'Application cannot start without session support. Please check writable/session directory permissions.',
                500,
                $e
            );
        }
    }

    protected function loadCurrentUser(): void
    {
        try {
            if (!$this->session) {
                log_message('debug', 'BaseController: Session not available in loadCurrentUser');
                $this->currentUser = null;
                return;
            }

            helper('session_context');
            $userId = sp_session_user_id();
            if (!$userId) {
                log_message('debug', 'BaseController: No user_id in session');
                $this->currentUser = null;
                return;
            }

            $employeeModel = new EmployeeModel();
            $user = $employeeModel->find($userId);

            if ($user === null) {
                $this->currentUser = null;
                return;
            }

            $role = is_object($user) ? ($user->role ?? null) : ($user['role'] ?? null);
            if (!$role) {
                if (is_object($user)) {
                    $user->role = 'funcionario';
                } else {
                    $user['role'] = 'funcionario';
                }
            } else {
                $normalizedRole = $this->authorizationService->normalizeRole((string) $role);
                if (is_object($user)) {
                    $user->role = $normalizedRole;
                } else {
                    $user['role'] = $normalizedRole;
                }
            }

            $this->currentUser = $user;
        } catch (\Throwable $e) {
            log_message('error', 'BaseController: Error loading currentUser: ' . $e->getMessage());
            $this->currentUser = null;
        }
    }

    protected function getCurrentUser(): mixed
    {
        return $this->currentUser;
    }

    protected function getAuthenticatedEmployee(): ?array
    {
        try {
            helper('session_context');
            $employee = sp_session_user();

            return $employee['id'] !== null ? $employee : null;
        } catch (\Throwable $e) {
            log_message('error', 'Error getting authenticated employee: ' . $e->getMessage());
            return null;
        }
    }

    protected function isAuthenticated(): bool
    {
        if (! $this->session) {
            return false;
        }

        helper('session_context');

        return sp_session_is_authenticated();
    }

    protected function ensureSessionDirectoryExists(?string $sessionPath = null): bool
    {
        try {
            if ($sessionPath === null) {
                $sessionPath = defined('WRITEPATH')
                    ? WRITEPATH . DIRECTORY_SEPARATOR . 'session'
                    : dirname(FCPATH) . DIRECTORY_SEPARATOR . 'writable' . DIRECTORY_SEPARATOR . 'session';
            }

            if (!is_dir($sessionPath)) {
                if (!@mkdir($sessionPath, 0750, true)) {
                    log_message('error', 'Failed to create session directory: ' . $sessionPath);
                    return false;
                }

                @chmod($sessionPath, 0750);
            }

            return is_writable($sessionPath);
        } catch (\Throwable $e) {
            log_message('error', 'Error ensuring session directory: ' . $e->getMessage());
            return false;
        }
    }
}
