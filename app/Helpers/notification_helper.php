<?php

/**
 * Notification Helper
 *
 * Helper functions for sending push notifications
 */

use App\Services\Notification\PushNotificationService;

if (!function_exists('send_push_notification')) {
    /**
     * Send push notification to employee
     *
     * @param int $employeeId Employee ID
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Additional data
     * @param array $options Notification options
     * @return array
     */
    function send_push_notification(
        int $employeeId,
        string $title,
        string $body,
        array $data = [],
        array $options = []
    ): array {
        $service = new PushNotificationService();
        return $service->sendToEmployee($employeeId, $title, $body, $data, $options);
    }
}

if (!function_exists('send_notification_template')) {
    /**
     * Send push notification using template
     *
     * @param int $employeeId Employee ID
     * @param string $templateName Template name
     * @param array $variables Template variables
     * @param array $data Additional data
     * @return array
     */
    function send_notification_template(
        int $employeeId,
        string $templateName,
        array $variables = [],
        array $data = []
    ): array {
        $service = new PushNotificationService();
        return $service->sendToEmployeeUsingTemplate($employeeId, $templateName, $variables, $data);
    }
}

if (!function_exists('notify_punch_in')) {
    /**
     * Send punch in notification
     *
     * @param int $employeeId Employee ID
     * @param string $time Punch time
     * @return array
     */
    function notify_punch_in(int $employeeId, string $time): array
    {
        return send_notification_template(
            $employeeId,
            'punch_in',
            ['time' => $time],
            [
                'type' => 'punch',
                'action' => 'in',
                'timestamp' => date('Y-m-d H:i:s'),
            ]
        );
    }
}

if (!function_exists('notify_punch_out')) {
    /**
     * Send punch out notification
     *
     * @param int $employeeId Employee ID
     * @param string $time Punch time
     * @return array
     */
    function notify_punch_out(int $employeeId, string $time): array
    {
        return send_notification_template(
            $employeeId,
            'punch_out',
            ['time' => $time],
            [
                'type' => 'punch',
                'action' => 'out',
                'timestamp' => date('Y-m-d H:i:s'),
            ]
        );
    }
}

if (!function_exists('notify_timesheet_approved')) {
    /**
     * Send timesheet approved notification
     *
     * @param int $employeeId Employee ID
     * @param string $date Timesheet date
     * @param int $timesheetId Timesheet ID
     * @return array
     */
    function notify_timesheet_approved(int $employeeId, string $date, int $timesheetId): array
    {
        return send_notification_template(
            $employeeId,
            'timesheet_approved',
            ['date' => $date],
            [
                'type' => 'timesheet',
                'action' => 'approved',
                'timesheet_id' => $timesheetId,
            ]
        );
    }
}

if (!function_exists('notify_timesheet_rejected')) {
    /**
     * Send timesheet rejected notification
     *
     * @param int $employeeId Employee ID
     * @param string $date Timesheet date
     * @param string $reason Rejection reason
     * @param int $timesheetId Timesheet ID
     * @return array
     */
    function notify_timesheet_rejected(
        int $employeeId,
        string $date,
        string $reason,
        int $timesheetId
    ): array {
        return send_notification_template(
            $employeeId,
            'timesheet_rejected',
            [
                'date' => $date,
                'reason' => $reason,
            ],
            [
                'type' => 'timesheet',
                'action' => 'rejected',
                'timesheet_id' => $timesheetId,
                'reason' => $reason,
            ]
        );
    }
}

if (!function_exists('notify_warning_issued')) {
    /**
     * Send warning issued notification
     *
     * @param int $employeeId Employee ID
     * @param string $reason Warning reason
     * @param int $warningId Warning ID
     * @return array
     */
    function notify_warning_issued(int $employeeId, string $reason, int $warningId): array
    {
        return send_notification_template(
            $employeeId,
            'warning_issued',
            ['reason' => $reason],
            [
                'type' => 'warning',
                'warning_id' => $warningId,
                'reason' => $reason,
            ]
        );
    }
}

if (!function_exists('notify_schedule_updated')) {
    /**
     * Send schedule updated notification
     *
     * @param int $employeeId Employee ID
     * @return array
     */
    function notify_schedule_updated(int $employeeId): array
    {
        return send_notification_template(
            $employeeId,
            'schedule_updated',
            [],
            [
                'type' => 'schedule',
                'action' => 'updated',
            ]
        );
    }
}

if (!function_exists('notify_announcement')) {
    /**
     * Send announcement notification
     *
     * @param int|array $employeeIds Employee ID(s)
     * @param string $message Announcement message
     * @param array $data Additional data
     * @return array
     */
    function notify_announcement($employeeIds, string $message, array $data = []): array
    {
        $service = new PushNotificationService();

        // Convert single ID to array
        if (!is_array($employeeIds)) {
            $employeeIds = [$employeeIds];
        }

        $results = [];

        foreach ($employeeIds as $employeeId) {
            $results[$employeeId] = $service->sendToEmployeeUsingTemplate(
                $employeeId,
                'announcement',
                ['message' => $message],
                array_merge($data, [
                    'type' => 'announcement',
                ])
            );
        }

        return [
            'success' => true,
            'results' => $results,
            'total' => count($employeeIds),
        ];
    }
}
