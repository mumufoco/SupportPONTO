<?php

namespace Config\Concerns;

use App\Services\Admin\AppearanceSettingsService;
use App\Services\Admin\ControlsSettingsService;
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
    public static function controlsSettingsService(bool $getShared = true): ControlsSettingsService
    {
        if ($getShared) {
            return static::getSharedInstance('controlsSettingsService');
        }

        return new ControlsSettingsService(
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
