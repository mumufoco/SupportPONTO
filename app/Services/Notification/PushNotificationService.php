<?php

namespace App\Services\Notification;

use App\Services\Notification\Push\FcmHttpClient;
use App\Services\Notification\Push\PushFailureProcessor;
use App\Services\Notification\Push\PushPayloadBuilder;
use App\Services\Notification\Push\PushTemplateCatalog;
use App\Services\Notification\Push\PushTokenRepository;
use App\Support\BootstrapEnv;
use CodeIgniter\Config\Services;
use CodeIgniter\Database\ConnectionInterface;

class PushNotificationService
{
    protected ConnectionInterface $db;
    protected string $fcmServerKey;

    protected PushTemplateCatalog $templates;
    protected PushPayloadBuilder $payloadBuilder;
    protected PushTokenRepository $tokens;
    protected PushFailureProcessor $failureProcessor;
    protected ?FcmHttpClient $client = null;

    public function __construct()
    {
        $dbService = Services::database();
        $this->db = $dbService ? $dbService->connect() : \Config\Database::connect();
        $this->fcmServerKey = BootstrapEnv::get('FCM_SERVER_KEY', '') ?? '';

        $this->templates = new PushTemplateCatalog();
        $this->payloadBuilder = new PushPayloadBuilder();
        $this->tokens = new PushTokenRepository($this->db);
        $this->failureProcessor = new PushFailureProcessor($this->tokens);

        if (! empty($this->fcmServerKey)) {
            $this->client = new FcmHttpClient($this->fcmServerKey);
        } else {
            log_message('warning', 'FCM_SERVER_KEY not configured. Push notifications will not work.');
        }
    }

    public function sendToDevice(
        string $deviceToken,
        string $title,
        string $body,
        array $data = [],
        array $options = []
    ): array {
        return $this->sendToDevices([$deviceToken], $title, $body, $data, $options);
    }

    public function sendToDevices(
        array $deviceTokens,
        string $title,
        string $body,
        array $data = [],
        array $options = []
    ): array {
        if ($this->client === null) {
            return ['success' => false, 'error' => 'FCM not configured'];
        }

        if (empty($deviceTokens)) {
            return ['success' => false, 'error' => 'No device tokens provided'];
        }

        $message = $this->payloadBuilder->forDevices($deviceTokens, $title, $body, $data, $options);
        $response = $this->client->send($message);

        if (($response['success'] ?? false) && isset($response['results']) && is_array($response['results'])) {
            $this->failureProcessor->processMulticastResults($deviceTokens, $response['results']);
        }

        return $response;
    }

    public function sendUsingTemplate(
        string $templateName,
        array $deviceTokens,
        array $variables = [],
        array $data = []
    ): array {
        $template = $this->templates->resolve($templateName, $variables);
        if (! $template) {
            return ['success' => false, 'error' => "Template '{$templateName}' not found"];
        }

        $data['template'] = $templateName;

        return $this->sendToDevices(
            $deviceTokens,
            $template['title'],
            $template['body'],
            $data,
            $template['options']
        );
    }

    public function sendToEmployee(
        int $employeeId,
        string $title,
        string $body,
        array $data = [],
        array $options = []
    ): array {
        $tokens = $this->tokens->employeeTokens($employeeId);

        if (empty($tokens)) {
            return [
                'success' => false,
                'error' => 'No device tokens found for employee',
            ];
        }

        return $this->sendToDevices($tokens, $title, $body, $data, $options);
    }

    public function sendToEmployeeUsingTemplate(
        int $employeeId,
        string $templateName,
        array $variables = [],
        array $data = []
    ): array {
        $tokens = $this->tokens->employeeTokens($employeeId);

        if (empty($tokens)) {
            return [
                'success' => false,
                'error' => 'No device tokens found for employee',
            ];
        }

        return $this->sendUsingTemplate($templateName, $tokens, $variables, $data);
    }

    public function sendToTopic(
        string $topic,
        string $title,
        string $body,
        array $data = [],
        array $options = []
    ): array {
        if ($this->client === null) {
            return ['success' => false, 'error' => 'FCM not configured'];
        }

        return $this->client->send($this->payloadBuilder->forTopic($topic, $title, $body, $data, $options));
    }

    public function registerDeviceToken(
        int $employeeId,
        string $deviceToken,
        string $platform = 'android',
        string $deviceName = 'Unknown Device'
    ): bool {
        return $this->tokens->register($employeeId, $deviceToken, $platform, $deviceName);
    }

    public function unregisterDeviceToken(string $deviceToken): bool
    {
        return $this->tokens->unregister($deviceToken);
    }

    public function getTemplates(): array
    {
        return $this->templates->all();
    }

    public function addTemplate(string $name, array $template): void
    {
        $this->templates->add($name, $template);
    }

    public function cleanupInvalidTokens(): int
    {
        $count = $this->tokens->cleanupInvalid();

        if ($count > 0) {
            log_message('info', "Cleaned up {$count} invalid push notification tokens");
        }

        return $count;
    }
}
