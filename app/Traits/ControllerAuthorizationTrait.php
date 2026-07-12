<?php

namespace App\Traits;

use CodeIgniter\HTTP\Exceptions\RedirectException;

trait ControllerAuthorizationTrait
{
    protected function requireAuth(): void
    {
        if (!$this->isAuthenticated()) {
            if ($this->session) {
                $this->session->setFlashdata('error', 'Você precisa estar logado para acessar esta página.');
            }

            throw new RedirectException(sp_login_url());
        }
    }

    protected function hasRole(string $role): bool
    {
        if (!$this->currentUser) {
            $this->loadCurrentUser();
        }

        return $this->authorizationService->hasRole($this->currentUser, $role);
    }

    protected function hasAnyRole(array $roles): bool
    {
        if (!$this->currentUser) {
            $this->loadCurrentUser();
        }

        return $this->authorizationService->hasRole($this->currentUser, $roles);
    }

    protected function getCurrentUserRole(): string
    {
        if (!$this->currentUser) {
            $this->loadCurrentUser();
        }

        return $this->authorizationService->getRole($this->currentUser);
    }

    protected function can(string $permission): bool
    {
        if (!$this->currentUser) {
            $this->loadCurrentUser();
        }

        return $this->authorizationService->can($this->currentUser, $permission);
    }

    protected function requireRole(string $role): void
    {
        $this->requireAuth();

        if (!$this->hasRole($role)) {
            $this->session->setFlashdata('error', 'Você não tem permissão para acessar esta área.');
            throw new RedirectException(site_url('dashboard'));
        }
    }

    protected function requireAnyRole(array $roles, string $message = 'Você não tem permissão para acessar esta área.'): void
    {
        $this->requireAuth();

        if (!$this->hasAnyRole($roles)) {
            $this->session->setFlashdata('error', $message);
            throw new RedirectException(site_url('dashboard'));
        }
    }

    protected function requirePermission(string $permission, string $message = 'Você não tem permissão para executar esta ação.'): void
    {
        $this->requireAuth();

        if (!$this->can($permission)) {
            $this->session->setFlashdata('error', $message);
            throw new RedirectException(site_url('dashboard'));
        }
    }


    protected function canAccessManagerArea(): bool
    {
        if (!$this->currentUser) {
            $this->loadCurrentUser();
        }

        return $this->authorizationService->canAccessManagerArea($this->currentUser);
    }

    protected function canAccessBiometricArea(): bool
    {
        if (!$this->currentUser) {
            $this->loadCurrentUser();
        }

        return $this->authorizationService->canAccessBiometricArea($this->currentUser);
    }

    protected function requireBiometricArea(string $message = 'Você não tem permissão para acessar a área biométrica.'): void
    {
        $this->requireAuth();

        if (!$this->canAccessBiometricArea()) {
            $this->session->setFlashdata('error', $message);
            throw new RedirectException(site_url('dashboard'));
        }
    }

    protected function isManager(): bool
    {
        return $this->canAccessManagerArea();
    }

    protected function requireManager(): void
    {
        $this->requireAuth();

        if (!$this->isManager()) {
            $this->session->setFlashdata('error', 'Você não tem permissão para acessar esta área.');
            throw new RedirectException(site_url('dashboard'));
        }
    }

    protected function canAccessEmployeeRecord(mixed $targetEmployee, bool $allowSelf = true): bool
    {
        if (!$this->currentUser) {
            $this->loadCurrentUser();
        }

        return $this->authorizationService->canAccessEmployee($this->currentUser, $targetEmployee, $allowSelf);
    }

    protected function canManageEmployeeRecord(mixed $targetEmployee): bool
    {
        if (!$this->currentUser) {
            $this->loadCurrentUser();
        }

        return $this->authorizationService->canManageEmployee($this->currentUser, $targetEmployee);
    }
}
