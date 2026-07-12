<?php

namespace App\Controllers;

use App\Controllers\Employees\EmployeeController;

/**
 * Legacy compatibility controller.
 *
 * Keeps aliases like conta/perfil and conta/biometria working while the
 * canonical profile flow lives in Employees\EmployeeController.
 */
/**
 * Canonical profile controller.
 * Do not replicate profile rules in wrappers.
 */
class ProfileController extends EmployeeController
{
    public function index()
    {
        return parent::profile();
    }

    public function biometric()
    {
        return parent::biometric();
    }

    public function update(int $id)
    {
        return parent::updateProfile();
    }

    public function biometricConsent()
    {
        return parent::biometricConsent();
    }

    public function biometricRevoke()
    {
        return parent::biometricRevoke();
    }

    public function changePassword()
    {
        return parent::changePassword();
    }

    public function updatePassword()
    {
        return parent::updatePassword();
    }
}
