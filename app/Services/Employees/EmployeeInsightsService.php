<?php

namespace App\Services\Employees;

use App\Models\BiometricTemplateModel;
use App\Models\EmployeeModel;
use App\Models\JustificationModel;
use App\Models\TimePunchModel;
use App\Models\UserConsentModel;
use App\Models\WarningModel;
use App\Services\TimesheetService;

class EmployeeInsightsService
{
    protected EmployeeModel $employeeModel;
    protected TimePunchModel $timePunchModel;
    protected BiometricTemplateModel $biometricModel;
    protected JustificationModel $justificationModel;
    protected WarningModel $warningModel;
    protected TimesheetService $timesheetService;

    public function __construct(
        ?EmployeeModel $employeeModel = null,
        ?TimePunchModel $timePunchModel = null,
        ?BiometricTemplateModel $biometricModel = null,
        ?JustificationModel $justificationModel = null,
        ?WarningModel $warningModel = null,
        ?TimesheetService $timesheetService = null
    ) {
        $this->employeeModel = $employeeModel ?? new EmployeeModel();
        $this->timePunchModel = $timePunchModel ?? new TimePunchModel();
        $this->biometricModel = $biometricModel ?? new BiometricTemplateModel();
        $this->justificationModel = $justificationModel ?? new JustificationModel();
        $this->warningModel = $warningModel ?? new WarningModel();
        $this->timesheetService = $timesheetService ?? new TimesheetService();
    }

    public function getEmployeeOverview(int $employeeId): ?array
    {
        $employee = $this->employeeModel->find($employeeId);
        if (! $employee) {
            return null;
        }

        $today = date('Y-m-d');
        $todayPunches = $this->timePunchModel->getPunchesByDate($employeeId, $today);
        $lastPunchToday = ! empty($todayPunches) ? end($todayPunches) : null;

        $balanceData = $this->timesheetService->calculateHoursWorked($employeeId, date('Y-m-01'), $today);

        $hasFace        = $this->biometricModel->hasEnrolled($employeeId, 'face');
        $hasFingerprint = $this->biometricModel->hasEnrolled($employeeId, 'fingerprint');
        $faceTemplate   = $this->biometricModel->where('employee_id', $employeeId)->where('biometric_type', 'face')->where('active', true)->orderBy('created_at', 'DESC')->first();
        $fpTemplate     = $this->biometricModel->where('employee_id', $employeeId)->where('biometric_type', 'fingerprint')->where('active', true)->orderBy('created_at', 'DESC')->first();

        return [
            'employee' => $employee,
            'punchedToday' => ! empty($todayPunches),
            'lastPunchToday' => $lastPunchToday ?: null,
            'hourBalance' => $balanceData['hours_balance'] ?? '00:00',
            'totalJustifications' => $this->justificationModel->where('employee_id', $employeeId)->countAllResults(),
            'totalWarnings' => $this->warningModel->where('employee_id', $employeeId)->countAllResults(),
            'recentPunches' => $this->timePunchModel->where('employee_id', $employeeId)->orderBy('punch_time', 'DESC')->findAll(10),
            'recentJustifications' => $this->justificationModel->where('employee_id', $employeeId)->orderBy('created_at', 'DESC')->findAll(5),
            'hasFaceBiometric'        => $hasFace,
            'hasFingerprintBiometric' => $hasFingerprint,
            'faceTemplate'            => $faceTemplate,
            'fingerprintTemplate'     => $fpTemplate,
        ];
    }

    public function getOwnProfileData(int $employeeId): array
    {
        $employee = $this->employeeModel->find($employeeId);

        return [
            'employee' => $employee,
            'title' => 'Meu Perfil',
        ];
    }

    public function exportEmployeeData(int $employeeId): ?array
    {
        $employee = $this->employeeModel->find($employeeId);
        if (! $employee) {
            return null;
        }

        $consentModel = new UserConsentModel();

        return [
            'employee' => $employee,
            'punches' => $this->timePunchModel
                ->where('employee_id', $employeeId)
                ->orderBy('punch_time', 'DESC')
                ->findAll(),
            'justifications' => $this->justificationModel
                ->where('employee_id', $employeeId)
                ->orderBy('created_at', 'DESC')
                ->findAll(),
            'warnings' => $this->warningModel
                ->where('employee_id', $employeeId)
                ->orderBy('created_at', 'DESC')
                ->findAll(),
            'biometric_templates' => $this->biometricModel
                ->where('employee_id', $employeeId)
                ->findAll(),
            'consents' => $consentModel
                ->where('user_id', $employeeId)
                ->findAll(),
        ];
    }

    public function getBiometricPageData(int $employeeId): ?array
    {
        $employee = $this->employeeModel->find($employeeId);
        if (! $employee) {
            return null;
        }

        $consentModel = new UserConsentModel();
        $hasConsent = $consentModel->hasConsent($employeeId, 'biometric_processing');

        return [
            'title' => 'Dados Biométricos',
            'currentUser' => $employee,
            'hasConsent' => $hasConsent,
            'faceTemplateCount' => $this->biometricModel->where('employee_id', $employeeId)->where('type', 'facial')->where('is_active', 1)->countAllResults(),
            'fingerprintTemplateCount' => $this->biometricModel->where('employee_id', $employeeId)->where('type', 'biometria')->where('is_active', 1)->countAllResults(),
        ];
    }
}
