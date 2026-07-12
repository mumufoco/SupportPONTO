<?php

namespace Config\Concerns;

use App\Models\EmployeeModel;
use App\Services\Employees\EmployeeAccountService;
use App\Services\Employees\EmployeeControllerActionService;
use App\Services\Employees\EmployeeCoordinatorService;
use App\Services\Employees\EmployeeIdentityService;
use App\Services\Employees\EmployeeInsightsService;
use App\Services\Employees\EmployeeManagementService;

trait EmployeeServicesTrait
{
    public static function employeeModel(bool $getShared = true): EmployeeModel
    {
        if ($getShared) {
            return static::getSharedInstance('employeeModel');
        }

        return new EmployeeModel();
    }
    public static function employeeIdentityService(bool $getShared = true): EmployeeIdentityService
    {
        if ($getShared) {
            return static::getSharedInstance('employeeIdentityService');
        }

        return new EmployeeIdentityService();
    }
    public static function employeeManagementService(bool $getShared = true): EmployeeManagementService
    {
        if ($getShared) {
            return static::getSharedInstance('employeeManagementService');
        }

        return new EmployeeManagementService(
            static::employeeModel()
        );
    }
    public static function employeeInsightsService(bool $getShared = true): EmployeeInsightsService
    {
        if ($getShared) {
            return static::getSharedInstance('employeeInsightsService');
        }

        return new EmployeeInsightsService(
            static::employeeModel(),
            static::timePunchModel(),
        );
    }
    public static function employeeAccountService(bool $getShared = true): EmployeeAccountService
    {
        if ($getShared) {
            return static::getSharedInstance('employeeAccountService');
        }

        return new EmployeeAccountService(
            static::employeeModel(),
            static::biometricTemplateModel(),
            static::userConsentModel(),
            static::auditModel(),
            static::passwordLifecycleService()
        );
    }
    public static function employeeCoordinatorService(bool $getShared = true): EmployeeCoordinatorService
    {
        if ($getShared) {
            return static::getSharedInstance('employeeCoordinatorService');
        }

        return new EmployeeCoordinatorService(
            static::employeeManagementService(),
            static::employeeInsightsService(),
            static::employeeAccountService()
        );
    }
    public static function employeeControllerActionService(bool $getShared = true): EmployeeControllerActionService
    {
        if ($getShared) {
            return static::getSharedInstance('employeeControllerActionService');
        }

        return new EmployeeControllerActionService(
            static::employeeCoordinatorService(),
            static::employeeIdentityService()
        );
    }

}
