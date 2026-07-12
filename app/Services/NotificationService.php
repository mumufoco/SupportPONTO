<?php
// ARQ-03 FIX: Este arquivo é o núcleo real do serviço.
// A nomenclatura "LegacyCore" é um artefato histórico — não indica código obsoleto.
// Roadmap: renomear para *ServiceCore e a subclasse pública para *Service
// na próxima refatoração maior. (Ver SECURITY_AUDIT_IMPLEMENTATION.md)


namespace App\Services;

use App\Models\EmployeeModel;
use App\Models\NotificationModel;
use App\Models\SettingModel;
use App\Services\Notification\LegacyCore\NotificationDeliveryService;
use App\Services\Notification\LegacyCore\NotificationEventService;
use App\Services\Notification\LegacyCore\NotificationRoutingService;
use App\Services\Notification\LegacyCore\NotificationStateService;

/**
 * Notification Service (Legacy Core)
 *
 * Mantém API pública legada, delegando para componentes coesos.
 */
class NotificationService
{
    protected NotificationModel $notificationModel;
    protected EmployeeModel $employeeModel;
    protected SettingModel $settingModel;

    private NotificationDeliveryService $deliveryService;
    private NotificationRoutingService $routingService;
    private NotificationStateService $stateService;
    private NotificationEventService $eventService;

    public function __construct()
    {
        $this->notificationModel = new NotificationModel();
        $this->employeeModel = new EmployeeModel();
        $this->settingModel = new SettingModel();

        $this->deliveryService = new NotificationDeliveryService($this->notificationModel);
        $this->routingService = new NotificationRoutingService($this->employeeModel);
        $this->stateService = new NotificationStateService($this->notificationModel);
        $this->eventService = new NotificationEventService(
            $this->employeeModel,
            $this->deliveryService,
            $this->routingService
        );
    }

    public function notify(int $employeeId, string $title, string $message, string $type = 'info', ?string $link = null)
    {
        return $this->deliveryService->notify($employeeId, $title, $message, $type, $link);
    }

    /**
     * Alias para compatibilidade com consumers mais novos.
     */
    public function create(int $employeeId, string $title, string $message, string $type = 'info', ?string $link = null)
    {
        return $this->notify($employeeId, $title, $message, $type, $link);
    }

    public function notifyMultiple(array $employeeIds, string $title, string $message, string $type = 'info', ?string $link = null): int
    {
        return $this->deliveryService->notifyMultiple($employeeIds, $title, $message, $type, $link);
    }

    public function notifyAdmins(string $title, string $message, string $type = 'info', ?string $link = null): int
    {
        return $this->deliveryService->notifyMultiple($this->routingService->adminIds(), $title, $message, $type, $link);
    }

    public function notifyManagers(string $title, string $message, string $type = 'info', ?string $link = null): int
    {
        return $this->deliveryService->notifyMultiple($this->routingService->managerIds(), $title, $message, $type, $link);
    }

    public function notifyDepartment(string $department, string $title, string $message, string $type = 'info', ?string $link = null): int
    {
        return $this->deliveryService->notifyMultiple($this->routingService->departmentIds($department), $title, $message, $type, $link);
    }

    public function markAsRead(int $notificationId, int $employeeId): bool
    {
        return $this->stateService->markAsRead($notificationId, $employeeId);
    }

    public function markAllAsRead(int $employeeId): int
    {
        return $this->stateService->markAllAsRead($employeeId);
    }

    public function getUnread(int $employeeId, int $limit = 10): array
    {
        return $this->stateService->getUnread($employeeId, $limit);
    }

    public function getAll(int $employeeId, int $limit = 20, int $offset = 0): array
    {
        return $this->stateService->getAll($employeeId, $limit, $offset);
    }

    public function countUnread(int $employeeId): int
    {
        return $this->stateService->countUnread($employeeId);
    }

    public function delete(int $notificationId, int $employeeId): bool
    {
        return $this->stateService->delete($notificationId, $employeeId);
    }

    public function deleteAllRead(int $employeeId): int
    {
        return $this->stateService->deleteAllRead($employeeId);
    }

    public function deleteOld(int $daysOld = 30): int
    {
        return $this->stateService->deleteOld($daysOld);
    }

    public function notifyNewEmployeeRegistration(int $employeeId): int
    {
        return $this->eventService->notifyNewEmployeeRegistration($employeeId);
    }

    public function notifyJustificationSubmitted(int $justificationId, int $employeeId): int
    {
        return $this->eventService->notifyJustificationSubmitted($justificationId, $employeeId);
    }

    public function notifyJustificationStatus(int $employeeId, bool $approved, ?string $reason = null)
    {
        return $this->eventService->notifyJustificationStatus($employeeId, $approved, $reason);
    }

    public function notifyMissingPunch(int $employeeId, string $date)
    {
        return $this->eventService->notifyMissingPunch($employeeId, $date);
    }

    public function notifyLateArrival(int $employeeId, string $date, int $minutesLate)
    {
        return $this->eventService->notifyLateArrival($employeeId, $date, $minutesLate);
    }

    public function notifyTimesheetReady(int $employeeId, string $month)
    {
        return $this->eventService->notifyTimesheetReady($employeeId, $month);
    }

    public function notifyWarning(int $employeeId, string $warningType)
    {
        return $this->eventService->notifyWarning($employeeId, $warningType);
    }

    public function getStatistics(): array
    {
        return $this->stateService->getStatistics();
    }
}
