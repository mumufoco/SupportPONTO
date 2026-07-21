<?php

namespace Config;

require_once __DIR__ . '/Concerns/AdminServicesTrait.php';
require_once __DIR__ . '/Concerns/EmployeeServicesTrait.php';
require_once __DIR__ . '/Concerns/ReportServicesTrait.php';

use Config\Concerns\ReportServicesTrait;
use Config\Concerns\EmployeeServicesTrait;
use Config\Concerns\AdminServicesTrait;
use App\Models\AuditModel;
use App\Services\Audit\AuditTrailService;
use App\Models\BiometricTemplateModel;
use App\Models\EmployeeModel;
use App\Models\SettingModel;
use App\Models\TimePunchModel;
use App\Models\UserConsentModel;
use App\Models\BiometricRecordModel;
use App\Services\Auth\ApiAuthService;
use App\Services\API\ApiPayloadSanitizer;
use App\Services\Auth\OAuth2Service;
use App\Services\Auth\PasswordLifecycleService;
use App\Services\Auth\PasswordResetService;
use App\Services\Auth\RememberMeService;
use App\Services\Auth\WebAuthService;
use App\Services\Auth\TwoFactorManagerService;
use App\Services\Biometric\FingerprintSettingsService;
use App\Services\Biometric\FaceRecognitionService;
use App\Services\Settings\Catalog\CatalogSettingsActionService;
use App\Services\Settings\Catalog\ContractTypeCatalogService;
use App\Services\Settings\Catalog\DepartmentCatalogService;
use App\Services\Settings\Catalog\PositionCatalogService;
use App\Services\Settings\Catalog\RoleCatalogService;
use App\Services\Settings\Catalog\WorkUnitCatalogService;
use App\Services\Employees\EmployeeAccountService;
use App\Services\Employees\EmployeeControllerActionService;
use App\Services\Employees\EmployeeCoordinatorService;
use App\Services\Employees\EmployeeIdentityService;
use App\Services\Employees\EmployeeInsightsService;
use App\Services\Database\QueryPlanAnalyzerService;
use App\Services\Employees\EmployeeManagementService;
use App\Services\Security\EncryptionService;
use App\Services\Security\RateLimitService;
use App\Services\Security\TwoFactorAuthService;
use App\Models\JustificationModel;
use App\Models\WarningModel;
use App\Models\WarningWitnessModel;
use App\Libraries\ApiRequestAuthContext;
use App\Models\TimesheetConsolidatedModel;
use App\Models\WorkShiftModel;
use App\Models\HolidayModel;
use App\Services\CSVService;
use App\Services\ExcelService;
use App\Services\NotificationService;
use App\Services\PDFService;
use App\Services\Reports\ReportExecutionService;
use App\Services\Queue\ReportQueueService;
use App\Services\ReportService;
use App\Services\SMSService;
use App\Services\TXTService;
use App\Services\TimesheetService;
use App\Services\Warning\WarningAccessService;
use App\Services\Warning\WarningControllerActionService;
use App\Services\Warning\WarningQueryService;
use App\Services\Warning\WarningWorkflowService;
use App\Services\Warning\Workflow\WarningDocumentService;
use App\Services\Warning\Workflow\WarningEvidenceService;
use App\Services\Warning\Workflow\WarningNotificationService;
use App\Services\Warning\Workflow\WarningSignatureService;
use App\Services\LGPD\ConsentService;
use App\Services\WarningPDFService;
use App\Services\XMLService;
use CodeIgniter\Config\BaseService;

/**
 * Application Services Configuration.
 *
 * Centraliza a criação de dependências para reduzir acoplamento por `new`
 * espalhado em controllers e services críticos.
 */
class Services extends BaseService
{
    use ReportServicesTrait;
    use EmployeeServicesTrait;
    use AdminServicesTrait;



    public static function faceRecognition(bool $getShared = true): FaceRecognitionService
    {
        if ($getShared) {
            return static::getSharedInstance('faceRecognition');
        }

        return new FaceRecognitionService();
    }

    public static function timePunchModel(bool $getShared = true): TimePunchModel
    {
        if ($getShared) {
            return static::getSharedInstance('timePunchModel');
        }

        return new TimePunchModel();
    }

    public static function auditModel(bool $getShared = true): AuditModel
    {
        if ($getShared) {
            return static::getSharedInstance('auditModel');
        }

        return new AuditModel();
    }

    public static function auditTrail(bool $getShared = true): AuditTrailService
    {
        if ($getShared) {
            return static::getSharedInstance('auditTrail');
        }

        return new AuditTrailService(static::auditModel());
    }

    public static function userConsentModel(bool $getShared = true): UserConsentModel
    {
        if ($getShared) {
            return static::getSharedInstance('userConsentModel');
        }

        return new UserConsentModel();
    }

    public static function biometricRecordModel(bool $getShared = true): BiometricRecordModel
    {
        if ($getShared) {
            return static::getSharedInstance('biometricRecordModel');
        }

        return new BiometricRecordModel();
    }


    public static function biometricTemplateModel(bool $getShared = true): BiometricTemplateModel
    {
        if ($getShared) {
            return static::getSharedInstance('biometricTemplateModel');
        }

        return new BiometricTemplateModel();
    }

    public static function settingModel(bool $getShared = true): SettingModel
    {
        if ($getShared) {
            return static::getSharedInstance('settingModel');
        }

        return new SettingModel();
    }


    public static function settings(bool $getShared = true): SettingModel
    {
        if ($getShared) {
            return static::getSharedInstance('settings');
        }

        return static::settingModel(false);
    }
















    public static function sessionSecurityService(bool $getShared = true): \App\Services\Auth\SessionSecurityService
    {
        if ($getShared) {
            return static::getSharedInstance('sessionSecurityService');
        }

        return new \App\Services\Auth\SessionSecurityService(
            static::employeeModel(),
            static::auditModel()
        );
    }


    public static function apiPayloadSanitizer(bool $getShared = true): ApiPayloadSanitizer
    {
        if ($getShared) {
            return static::getSharedInstance('apiPayloadSanitizer');
        }

        return new ApiPayloadSanitizer();
    }

    public static function apiRequestAuthContext(bool $getShared = true): ApiRequestAuthContext
    {
        if ($getShared) {
            return static::getSharedInstance('apiRequestAuthContext');
        }

        return new ApiRequestAuthContext();
    }

    public static function rateLimitService(bool $getShared = true): RateLimitService
    {
        if ($getShared) {
            return static::getSharedInstance('rateLimitService');
        }

        return new RateLimitService();
    }

    public static function rememberMeService(bool $getShared = true): RememberMeService
    {
        if ($getShared) {
            return static::getSharedInstance('rememberMeService');
        }

        return new RememberMeService();
    }


    public static function passwordLifecycleService(bool $getShared = true): PasswordLifecycleService
    {
        if ($getShared) {
            return static::getSharedInstance('passwordLifecycleService');
        }

        return new PasswordLifecycleService();
    }


    public static function passwordResetService(bool $getShared = true): PasswordResetService
    {
        if ($getShared) {
            return static::getSharedInstance('passwordResetService');
        }

        return new PasswordResetService(
            static::employeeModel(),
            static::rateLimitService(),
            static::passwordLifecycleService()
        );
    }

    public static function webAuthService(bool $getShared = true): WebAuthService
    {
        if ($getShared) {
            return static::getSharedInstance('webAuthService');
        }

        return new WebAuthService(
            static::employeeModel(),
            static::auditModel(),
            static::rateLimitService(),
            static::rememberMeService()
        );
    }

    public static function oauth2Service(bool $getShared = true): OAuth2Service
    {
        if ($getShared) {
            return static::getSharedInstance('oauth2Service');
        }

        return new OAuth2Service();
    }

    public static function apiAuthService(bool $getShared = true): ApiAuthService
    {
        if ($getShared) {
            return static::getSharedInstance('apiAuthService');
        }

        return new ApiAuthService(
            static::employeeModel(),
            static::auditModel(),
            static::rateLimitService(),
            static::oauth2Service(),
            static::twoFactorManagerService()
        );
    }

    public static function twoFactorAuthService(bool $getShared = true): TwoFactorAuthService
    {
        if ($getShared) {
            return static::getSharedInstance('twoFactorAuthService');
        }

        return new TwoFactorAuthService();
    }

    public static function twoFactorManagerService(bool $getShared = true): TwoFactorManagerService
    {
        if ($getShared) {
            return static::getSharedInstance('twoFactorManagerService');
        }

        return new TwoFactorManagerService(
            static::twoFactorAuthService(),
            static::encryptionService(),
            static::employeeModel()
        );
    }

    public static function encryptionService(bool $getShared = true): EncryptionService
    {
        if ($getShared) {
            return static::getSharedInstance('encryptionService');
        }

        return new EncryptionService();
    }
    public static function warningModel(bool $getShared = true): WarningModel
    {
        if ($getShared) {
            return static::getSharedInstance('warningModel');
        }

        return new WarningModel();
    }

    public static function justificationModel(bool $getShared = true): JustificationModel
    {
        if ($getShared) {
            return static::getSharedInstance('justificationModel');
        }

        return new JustificationModel();
    }

    public static function timesheetConsolidatedModel(bool $getShared = true): TimesheetConsolidatedModel
    {
        if ($getShared) {
            return static::getSharedInstance('timesheetConsolidatedModel');
        }

        return new TimesheetConsolidatedModel();
    }

    public static function notificationService(bool $getShared = true): NotificationService
    {
        if ($getShared) {
            return static::getSharedInstance('notificationService');
        }

        return new NotificationService();
    }

    public static function smsService(bool $getShared = true): SMSService
    {
        if ($getShared) {
            return static::getSharedInstance('smsService');
        }

        return new SMSService();
    }

    public static function reportQueueService(bool $getShared = true): ReportQueueService
    {
        if ($getShared) {
            return static::getSharedInstance('reportQueueService');
        }

        return new ReportQueueService(
            null,
            static::reportService(false),
            new \App\Services\EmailService(),
            static::employeeModel(false)
        );
    }


    public static function warningPdfStorageService(bool $getShared = true): \App\Services\Warning\WarningPdfStorageService
    {
        if ($getShared) {
            return static::getSharedInstance('warningPdfStorageService');
        }

        return new \App\Services\Warning\WarningPdfStorageService();
    }

    public static function warningPdfService(bool $getShared = true): WarningPDFService
    {
        if ($getShared) {
            return static::getSharedInstance('warningPdfService');
        }

        return new WarningPDFService(static::warningPdfStorageService(false));
    }

    public static function warningAccessService(bool $getShared = true): WarningAccessService
    {
        if ($getShared) {
            return static::getSharedInstance('warningAccessService');
        }

        return new WarningAccessService(static::employeeModel());
    }

    public static function warningQueryService(bool $getShared = true): WarningQueryService
    {
        if ($getShared) {
            return static::getSharedInstance('warningQueryService');
        }

        return new WarningQueryService(
            static::warningModel(),
            static::employeeModel(),
            static::warningAccessService(),
            static::warningPdfStorageService(),
            new WarningWitnessModel()
        );
    }

    public static function warningEvidenceService(bool $getShared = true): WarningEvidenceService
    {
        if ($getShared) {
            return static::getSharedInstance('warningEvidenceService');
        }

        return new WarningEvidenceService(static::warningPdfStorageService(false));
    }

    public static function warningSignatureService(bool $getShared = true): WarningSignatureService
    {
        if ($getShared) {
            return static::getSharedInstance('warningSignatureService');
        }

        return new WarningSignatureService(static::warningPdfService(), static::smsService());
    }

    public static function warningDocumentService(bool $getShared = true): WarningDocumentService
    {
        if ($getShared) {
            return static::getSharedInstance('warningDocumentService');
        }

        return new WarningDocumentService(static::warningModel(), static::warningPdfService(), new WarningWitnessModel());
    }

    public static function warningNotificationWorkflowService(bool $getShared = true): WarningNotificationService
    {
        if ($getShared) {
            return static::getSharedInstance('warningNotificationWorkflowService');
        }

        return new WarningNotificationService(static::notificationService());
    }

    public static function consentService(bool $getShared = true): ConsentService
    {
        if ($getShared) {
            return static::getSharedInstance('consentService');
        }

        return new ConsentService(
            static::userConsentModel(),
            static::auditModel(),
            static::employeeModel(),
            static::biometricRecordModel()
        );
    }

    public static function warningWorkflowService(bool $getShared = true): WarningWorkflowService
    {
        if ($getShared) {
            return static::getSharedInstance('warningWorkflowService');
        }

        return new WarningWorkflowService(
            static::warningModel(),
            static::employeeModel(),
            static::auditModel(),
            static::notificationService(),
            static::warningPdfService(),
            static::smsService(),
            static::warningEvidenceService(),
            static::warningSignatureService(),
            static::warningDocumentService(),
            static::warningNotificationWorkflowService(),
            new WarningWitnessModel()
        );
    }

    public static function warningControllerActionService(bool $getShared = true): WarningControllerActionService
    {
        if ($getShared) {
            return static::getSharedInstance('warningControllerActionService');
        }

        return new WarningControllerActionService();
    }




























    public static function workShiftModel(bool $getShared = true): WorkShiftModel
    {
        if ($getShared) {
            return static::getSharedInstance('workShiftModel');
        }

        return new WorkShiftModel();
    }


    public static function holidayModel(bool $getShared = true): HolidayModel
    {
        if ($getShared) {
            return static::getSharedInstance('holidayModel');
        }

        return new HolidayModel();
    }


    public static function workUnitCatalogService(bool $getShared = true): WorkUnitCatalogService
    {
        if ($getShared) {
            return static::getSharedInstance('workUnitCatalogService');
        }

        return new WorkUnitCatalogService();
    }

    public static function departmentCatalogService(bool $getShared = true): DepartmentCatalogService
    {
        if ($getShared) {
            return static::getSharedInstance('departmentCatalogService');
        }

        return new DepartmentCatalogService();
    }

    public static function contractTypeCatalogService(bool $getShared = true): ContractTypeCatalogService
    {
        if ($getShared) {
            return static::getSharedInstance('contractTypeCatalogService');
        }

        return new ContractTypeCatalogService();
    }

    public static function positionCatalogService(bool $getShared = true): PositionCatalogService
    {
        if ($getShared) {
            return static::getSharedInstance('positionCatalogService');
        }

        return new PositionCatalogService();
    }

    public static function roleCatalogService(bool $getShared = true): RoleCatalogService
    {
        if ($getShared) {
            return static::getSharedInstance('roleCatalogService');
        }

        return new RoleCatalogService();
    }

    public static function catalogSettingsActionService(bool $getShared = true): CatalogSettingsActionService
    {
        if ($getShared) {
            return static::getSharedInstance('catalogSettingsActionService');
        }

        return new CatalogSettingsActionService();
    }

    public static function queryPlanAnalyzerService(bool $getShared = true): QueryPlanAnalyzerService
    {
        if ($getShared) {
            return static::getSharedInstance('queryPlanAnalyzerService');
        }

        return new QueryPlanAnalyzerService();
    }

}
