<?php

namespace App\Services\Biometric;

use App\Models\SettingModel;

class FingerprintSettingsService
{
    public function __construct(private readonly SettingModel $settingsModel = new SettingModel())
    {
    }

    public function threshold(): int
    {
        return (int) ($this->settingsModel->get('biometric_fingerprint_threshold') ?? 30);
    }

    public function minQuality(): int
    {
        return (int) ($this->settingsModel->get('biometric_min_quality_score') ?? 30);
    }

    public function maxTemplatesPerUser(): int
    {
        return (int) ($this->settingsModel->get('biometric_max_templates_per_user') ?? 5);
    }

    public function allowDuplicateCheck(): bool
    {
        return (bool) ($this->settingsModel->get('biometric_allow_duplicate_check') ?? true);
    }

    public function requireConsent(): bool
    {
        return (bool) ($this->settingsModel->get('biometric_require_consent') ?? true);
    }

    public function algorithmVersion(): string
    {
        return $this->settingsModel->get('sourceafis_version') ?? '3.17.0';
    }
}
