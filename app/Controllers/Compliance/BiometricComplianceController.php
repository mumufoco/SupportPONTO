<?php

namespace App\Controllers\Compliance;

use App\Controllers\BaseController;
use App\Services\Compliance\BiometricComplianceService;

class BiometricComplianceController extends BaseController
{
    public function index()
    {
        $service = new BiometricComplianceService();

        supportponto_log_event('info', 'compliance', 'biometric_dashboard_opened', [
            'user_id' => $this->session?->get('user_id'),
            'path' => $this->request->getUri()->getPath(),
        ]);

        return view('compliance/biometric', [
            'summary' => $service->getConsentSummary(),
            'cards' => $service->getBiometricProfileCards(),
            'guidelines' => $service->getBiometricGuidelines(),
        ]);
    }
}
