<?php

namespace App\Controllers\API;

use App\Services\Notification\PushNotificationService;
use App\Services\Queue\AsyncJobService;
use Throwable;

class PushNotificationController extends BaseApiController
{
    protected $format = 'json';

    protected PushNotificationService $notificationService;
    protected AsyncJobService $asyncJobService;

    public function __construct()
    {
        parent::__construct();
        $this->notificationService = new PushNotificationService();
        $this->asyncJobService = new AsyncJobService();
    }

    public function register()
    {
        $employeeId = $this->currentEmployeeId();
        if (! $employeeId) {
            return $this->attachResponseContext($this->failUnauthorized('Authentication required'), true);
        }

        $deviceToken = (string) $this->requestValue('device_token', '');
        $platform = (string) $this->requestValue('platform', 'android');
        $deviceName = (string) $this->requestValue('device_name', 'Unknown Device');

        if ($deviceToken === '') {
            return $this->errorResponse('validation_error', 'Missing device_token', 400);
        }

        if (! in_array($platform, ['android', 'ios', 'web'], true)) {
            return $this->errorResponse('validation_error', 'Invalid platform.', 400);
        }

        $success = $this->notificationService->registerDeviceToken(
            $employeeId,
            $deviceToken,
            $platform,
            $deviceName
        );

        if ($success) {
            $this->logSecurityEvent('info', 'Push device token registered', [
                'employee_id' => $employeeId,
                'platform' => $platform,
                'device_name' => $deviceName,
                'device_token' => $deviceToken,
            ]);

            return $this->successResponse([
                'success' => true,
                'message' => 'Device token registered successfully',
            ]);
        }

        return $this->errorResponse('push_registration_failed', 'Failed to register device token', 500);
    }

    public function unregister()
    {
        $employeeId = $this->currentEmployeeId();
        if (! $employeeId) {
            return $this->attachResponseContext($this->failUnauthorized('Authentication required'), true);
        }

        $deviceToken = (string) $this->requestValue('device_token', '');

        if ($deviceToken === '') {
            return $this->errorResponse('validation_error', 'Missing device_token', 400);
        }

        $success = $this->notificationService->unregisterDeviceToken($employeeId, $deviceToken);

        if ($success) {
            $this->logSecurityEvent('info', 'Push device token unregistered', [
                'employee_id' => $employeeId,
                'device_token' => $deviceToken,
            ]);

            return $this->successResponse([
                'success' => true,
                'message' => 'Device token unregistered successfully',
            ]);
        }

        return $this->errorResponse('push_unregistration_failed', 'Failed to unregister device token', 500);
    }

    public function sendTest()
    {
        $employeeId = $this->currentEmployeeId();
        if (! $employeeId) {
            return $this->attachResponseContext($this->failUnauthorized('Authentication required'), true);
        }

        try {
            $job = $this->asyncJobService->dispatchPushNotification([
                'employee_id' => (int) $employeeId,
                'type' => 'test',
                'payload' => [
                    'title' => 'SupportPONTO',
                    'body' => 'Test notification from API',
                ],
            ]);

            $this->logSecurityEvent('info', 'Push notification test enqueued', [
                'employee_id' => $employeeId,
                'job_id' => $job['job_id'] ?? null,
                'job_type' => $job['job_type'] ?? AsyncJobService::TYPE_PUSH_NOTIFICATION,
            ]);

            return $this->successResponse([
                'success' => true,
                'message' => 'Test notification queued successfully',
                'job' => $job,
            ]);
        } catch (Throwable $exception) {
            $this->logSecurityEvent('warning', 'Push notification test enqueue failed', [
                'employee_id' => $employeeId,
                'error' => $exception->getMessage(),
            ]);

            return $this->errorResponse('push_test_failed', 'Failed to queue test notification', 500);
        }
    }

    public function templates()
    {
        return $this->successResponse([
            'templates' => $this->notificationService->getTemplates(),
        ]);
    }

    private function currentEmployeeId(): ?int
    {
        return $this->getAuthenticatedEmployeeId();
    }

    private function successResponse(array $payload, int $status = 200)
    {
        return $this->attachResponseContext($this->respond(array_merge($payload, [
            'meta' => ['request_id' => $this->getRequestId()],
        ]), $status), true);
    }

    private function errorResponse(string $code, string $message, int $status)
    {
        return $this->attachResponseContext($this->respond([
            'success' => false,
            'code' => $code,
            'message' => $message,
            'meta' => ['request_id' => $this->getRequestId()],
        ], $status), true);
    }
}
