<?php

namespace Config\Concerns;

use App\Services\Admin\AppearanceSettingsService;
use App\Services\Admin\AuthenticationSettingsService;
use App\Services\Admin\SecuritySettingsService;
use App\Services\Admin\SystemSettingsService;
use App\Services\Biometric\FingerprintSettingsService;

trait AdminServicesTrait
{
    public static function appearanceSettingsService(bool $getShared = true): AppearanceSettingsService
    {
        if ($getShared) {
            return static::getSharedInstance('appearanceSettingsService');
        }

        return new AppearanceSettingsService(
            static::settings(false)
        );
    }
    public static function authenticationSettingsService(bool $getShared = true): AuthenticationSettingsService
    {
        if ($getShared) {
            return static::getSharedInstance('authenticationSettingsService');
        }

        return new AuthenticationSettingsService(
            static::settings(false)
        );
    }
    public static function securitySettingsService(bool $getShared = true): SecuritySettingsService
    {
        if ($getShared) {
            return static::getSharedInstance('securitySettingsService');
        }

        return new SecuritySettingsService(
            static::settings(false)
        );
    }
    public static function systemSettingsService(bool $getShared = true): SystemSettingsService
    {
        if ($getShared) {
            return static::getSharedInstance('systemSettingsService');
        }

        return new SystemSettingsService(
            static::settings(false)
        );
    }
    public static function fingerprintSettingsService(bool $getShared = true): FingerprintSettingsService
    {
        if ($getShared) {
            return static::getSharedInstance('fingerprintSettingsService');
        }

        return new FingerprintSettingsService(
            static::settings(false)
        );
    }

}
