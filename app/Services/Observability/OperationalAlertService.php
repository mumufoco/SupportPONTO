<?php

declare(strict_types=1);

namespace App\Services\Observability;

use App\Models\EmployeeModel;
use App\Models\HealthAlertThrottleModel;
use App\Models\NotificationModel;
use App\Services\EmailService;
use App\Support\SensitiveDataSanitizer;

/**
 * Persistência local de alertas críticos para operação e suporte.
 *
 * `raise()` é a gravação INCONDICIONAL (NDJSON local + telemetria) — serve como
 * trilha de auditoria completa, sempre executada, independente de qualquer
 * decisão de "avisar alguém agora".
 *
 * `raiseWithDelivery()` é a camada adicional de ENTREGA REAL (e-mail aos admins
 * + notificação in-app), com THROTTLE — usada por detectores periódicos (ex.:
 * HealthCheckCommand) para evitar "alert storm" quando um problema persiste por
 * várias execuções seguidas. Ver HealthAlertThrottleModel para o racional do
 * throttle e a migração 2026-06-07-000492_CreateHealthAlertThrottleTable.
 */
class OperationalAlertService
{
    private OperationalTelemetryService $telemetry;
    private string $directory;
    private ?HealthAlertThrottleModel $throttleModel = null;
    private ?EmailService $emailService = null;
    private ?NotificationModel $notificationModel = null;
    private ?EmployeeModel $employeeModel = null;

    public function __construct(?OperationalTelemetryService $telemetry = null, ?string $directory = null)
    {
        $this->telemetry = $telemetry ?: new OperationalTelemetryService();
        $this->directory = rtrim($directory ?: (WRITEPATH . 'observability'), DIRECTORY_SEPARATOR);
    }

    /** @param array<string,mixed> $context */
    public function raise(string $severity, string $module, string $message, array $context = []): void
    {
        $severity = strtolower(trim($severity));
        if (! in_array($severity, ['warning', 'critical', 'emergency'], true)) {
            $severity = 'warning';
        }

        $payload = [
            'timestamp' => gmdate(DATE_ATOM),
            'severity' => $severity,
            'module' => preg_replace('/[^a-z0-9_.-]+/i', '_', $module) ?: 'system',
            'message' => substr($message, 0, 500),
            'request_id' => $_SERVER['SUPPORTPONTO_REQUEST_ID'] ?? null,
            'correlation_id' => function_exists('correlation_id') ? correlation_id() : null,
            'context' => SensitiveDataSanitizer::sanitizeForLogs($context),
        ];

        if (! is_dir($this->directory)) {
            @mkdir($this->directory, 0775, true);
        }

        @file_put_contents(
            $this->directory . DIRECTORY_SEPARATOR . 'operational-alerts-' . gmdate('Y-m-d') . '.ndjson',
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );

        $this->telemetry->record($severity === 'critical' || $severity === 'emergency' ? 'critical' : 'warning', 'alerts', (string) $payload['module'], $payload);
    }

    /**
     * Igual a `raise()` (sempre grava localmente para auditoria/telemetria), mas
     * adicionalmente tenta ENTREGAR o alerta de verdade — e-mail aos admins ativos
     * + notificação in-app — respeitando um intervalo mínimo de "silêncio" entre
     * envios repetidos do mesmo (module, severity), para não gerar um e-mail a
     * cada execução de um detector periódico (ex.: health-check a cada 5 minutos
     * com o banco fora do ar por horas geraria dezenas de e-mails idênticos).
     *
     * Nunca lança exceção — falha de entrega (e-mail/notificação) é logada e
     * silenciosamente ignorada, pois o alerta operacional NUNCA pode quebrar o
     * fluxo que o disparou (mesmo racional defensivo de EmployeeAfdEventRecorderService).
     *
     * @param array<string,mixed> $context
     * @return bool true se um envio (e-mail e/ou notificação) foi efetivamente disparado agora
     */
    public function raiseWithDelivery(
        string $severity,
        string $module,
        string $message,
        array $context = [],
        int $throttleMinutes = 30
    ): bool {
        // 1) Trilha de auditoria incondicional — sempre acontece.
        $this->raise($severity, $module, $message, $context);

        $normalizedSeverity = strtolower(trim($severity));
        if (! in_array($normalizedSeverity, ['warning', 'critical', 'emergency'], true)) {
            $normalizedSeverity = 'warning';
        }
        $normalizedModule = preg_replace('/[^a-z0-9_.-]+/i', '_', $module) ?: 'system';

        try {
            $throttle = $this->throttleModel ?? new HealthAlertThrottleModel();

            // Módulo voltou ao normal: limpa o throttle para que uma futura
            // recorrência alerte imediatamente, sem herdar o "silêncio" antigo.
            if ($normalizedSeverity === 'ok' || $normalizedSeverity === 'recovered') {
                $throttle->clearThrottle($normalizedModule, $normalizedSeverity);

                return false;
            }

            if (! $throttle->shouldAlert($normalizedModule, $normalizedSeverity, max(1, $throttleMinutes))) {
                return false;
            }

            $delivered = $this->deliver($normalizedSeverity, $normalizedModule, $message, $context);

            if ($delivered) {
                $throttle->markAlerted($normalizedModule, $normalizedSeverity);
            }

            return $delivered;
        } catch (\Throwable $e) {
            log_message('error', '[OperationalAlertService] Falha ao processar entrega de alerta (' . $normalizedModule . '/' . $normalizedSeverity . '): ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Envia o alerta de verdade — e-mail aos admins ativos + notificação in-app
     * para usuários admin/rh. Retorna true se PELO MENOS UM canal teve sucesso.
     *
     * @param array<string,mixed> $context
     */
    private function deliver(string $severity, string $module, string $message, array $context): bool
    {
        $severityLabel = match ($severity) {
            'emergency' => 'EMERGÊNCIA',
            'critical'  => 'CRÍTICO',
            default     => 'ATENÇÃO',
        };
        $moduleLabel = ucfirst(str_replace(['_', '-'], ' ', $module));
        $subject     = "[SupportPONTO] Alerta operacional ({$severityLabel}): {$moduleLabel}";

        $emailSent  = $this->deliverEmail($subject, $severityLabel, $moduleLabel, $message, $context);
        $notifySent = $this->deliverInAppNotification($severityLabel, $moduleLabel, $message);

        return $emailSent || $notifySent;
    }

    /**
     * @param array<string,mixed> $context
     */
    private function deliverEmail(string $subject, string $severityLabel, string $moduleLabel, string $message, array $context): bool
    {
        try {
            $email = $this->emailService ?? new EmailService();

            $contextHtml = '';
            $sanitizedContext = SensitiveDataSanitizer::sanitizeForLogs($context);
            if (! empty($sanitizedContext)) {
                $contextHtml = '<pre style="background:#f5f5f5;border:1px solid #ddd;border-radius:4px;padding:12px;font-size:12px;overflow:auto;">'
                    . esc(json_encode($sanitizedContext, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))
                    . '</pre>';
            }

            $body = '<h2 style="color:#b91c1c;">⚠ Alerta operacional — ' . esc($severityLabel) . '</h2>'
                . '<p><strong>Módulo:</strong> ' . esc($moduleLabel) . '</p>'
                . '<p><strong>Mensagem:</strong> ' . esc($message) . '</p>'
                . '<p><strong>Detectado em:</strong> ' . esc(gmdate('d/m/Y H:i:s')) . ' UTC</p>'
                . $contextHtml
                . '<hr><p style="color:#666;font-size:12px;">Este alerta foi disparado automaticamente pelo monitoramento de saúde do '
                . 'SupportPONTO (SystemHealthCheckService → HealthCheckCommand → OperationalAlertService). '
                . 'Para reduzir ruído, alertas repetidos do mesmo módulo são silenciados por um período mínimo entre envios.</p>';

            $sentCount = $email->sendToAdmins($subject, $body);

            return $sentCount > 0;
        } catch (\Throwable $e) {
            log_message('error', '[OperationalAlertService] Falha ao enviar e-mail de alerta: ' . $e->getMessage());

            return false;
        }
    }

    private function deliverInAppNotification(string $severityLabel, string $moduleLabel, string $message): bool
    {
        try {
            $employeeModel    = $this->employeeModel ?? new EmployeeModel();
            $notificationModel = $this->notificationModel ?? new NotificationModel();

            $admins = $employeeModel->whereIn('role', ['admin', 'rh'])->findAll();
            if (empty($admins)) {
                return false;
            }

            $title    = "Alerta operacional ({$severityLabel}): {$moduleLabel}";
            $isSevere = $severityLabel === 'EMERGÊNCIA' || $severityLabel === 'CRÍTICO';
            $icon     = $isSevere ? 'bi-exclamation-octagon-fill' : 'bi-exclamation-triangle-fill';
            $priority = $isSevere ? 'urgent' : 'high';

            $sent = false;
            foreach ($admins as $admin) {
                $adminId = is_object($admin) ? $admin->id : $admin['id'];
                $result  = $notificationModel->notify(
                    (int) $adminId,
                    'system',
                    $title,
                    $message,
                    site_url('admin/health'),
                    $icon,
                    $priority,
                    'operational_alert',
                    null
                );

                if ($result !== false) {
                    $sent = true;
                }
            }

            return $sent;
        } catch (\Throwable $e) {
            log_message('error', '[OperationalAlertService] Falha ao gerar notificação in-app de alerta: ' . $e->getMessage());

            return false;
        }
    }
}
