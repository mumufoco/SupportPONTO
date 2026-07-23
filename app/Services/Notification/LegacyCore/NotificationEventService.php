<?php

namespace App\Services\Notification\LegacyCore;

use App\Models\EmployeeModel;

class NotificationEventService
{
    public function __construct(
        private readonly EmployeeModel $employeeModel,
        private readonly NotificationDeliveryService $deliveryService,
        private readonly NotificationRoutingService $routingService
    ) {
    }

    public function notifyNewEmployeeRegistration(int $employeeId): int
    {
        $employee = $this->employeeModel->find($employeeId);

        if (!$employee) {
            return 0;
        }

        return $this->deliveryService->notifyMultiple(
            $this->routingService->adminIds(),
            'Novo cadastro pendente',
            "O colaborador {$employee->name} ({$employee->email}) solicitou cadastro no sistema.",
            'employee_registration',
            '/employees/pending'
        );
    }

    public function notifyJustificationSubmitted(int $justificationId, int $employeeId): int
    {
        $employee = $this->employeeModel->find($employeeId);

        if (!$employee) {
            return 0;
        }

        return $this->deliveryService->notifyMultiple(
            $this->routingService->managerIds(),
            'Nova justificativa pendente',
            "O colaborador {$employee->name} enviou uma justificativa para aprovação.",
            'justification',
            "/justifications/{$justificationId}"
        );
    }

    public function notifyJustificationStatus(int $employeeId, bool $approved, ?string $reason = null)
    {
        $status = $approved ? 'aprovada' : 'rejeitada';
        $type = $approved ? 'success' : 'warning';

        $message = "Sua justificativa foi {$status}.";
        if ($reason) {
            $message .= " Motivo: {$reason}";
        }

        return $this->deliveryService->notify(
            $employeeId,
            'Justificativa ' . ucfirst($status),
            $message,
            $type,
            '/justifications'
        );
    }

    public function notifyMissingPunch(int $employeeId, string $date)
    {
        $formattedDate = date('d/m/Y', strtotime($date));

        return $this->deliveryService->notify(
            $employeeId,
            'Registro de ponto ausente',
            "Você não registrou ponto no dia {$formattedDate}. Por favor, justifique a ausência.",
            'warning',
            '/justifications/create'
        );
    }

    public function notifyLateArrival(int $employeeId, string $date, int $minutesLate)
    {
        $formattedDate = date('d/m/Y', strtotime($date));

        helper('operational_link');

        return $this->deliveryService->notify(
            $employeeId,
            'Atraso registrado',
            "Você chegou {$minutesLate} minutos atrasado no dia {$formattedDate}.",
            'warning',
            sp_timesheet_history_path()
        );
    }

    public function notifyTimesheetReady(int $employeeId, string $month)
    {
        $formattedMonth = date('m/Y', strtotime($month . '-01'));

        helper('operational_link');

        return $this->deliveryService->notify(
            $employeeId,
            'Espelho de ponto disponível',
            "O espelho de ponto do mês {$formattedMonth} está disponível para visualização.",
            'info',
            sp_reports_timesheet_month_path($month)
        );
    }

    public function notifyWarning(int $employeeId, string $warningType)
    {
        return $this->deliveryService->notify(
            $employeeId,
            'Advertência recebida',
            "Você recebeu uma advertência: {$warningType}. Confira os detalhes.",
            'warning',
            sp_warning_index_path()
        );
    }
}
