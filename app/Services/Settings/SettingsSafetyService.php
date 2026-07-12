<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\AuditModel;
use CodeIgniter\I18n\Time;
use RuntimeException;

class SettingsSafetyService
{
    public function __construct(
        private readonly ?SettingsTransferService $settingsTransferService = null,
        private readonly ?AuditModel $auditModel = null,
    ) {
    }

    /**
     * @param list<string>|null $groups
     * @param array<string,mixed> $context
     * @return array{success:bool,file_path?:string,file_name?:string,relative_path?:string,groups:list<string>,message:string}
     */
    public function createPreDestructiveSnapshot(string $action, ?int $actorId, ?array $groups = null, array $context = []): array
    {
        helper('observability');

        $allowedGroups = $this->settingsTransfer()->allowedGroups();
        $targetGroups = $groups === null ? $allowedGroups : array_values(array_intersect($allowedGroups, $groups));

        if ($targetGroups === []) {
            return [
                'success' => false,
                'groups' => [],
                'message' => 'Nenhum grupo elegível para snapshot seguro.',
            ];
        }

        $payload = $this->settingsTransfer()->exportPayload($targetGroups);
        $settings = $payload['settings'] ?? [];
        if (! is_array($settings) || $settings === []) {
            return [
                'success' => false,
                'groups' => $targetGroups,
                'message' => 'Nenhuma configuração exportável encontrada para snapshot.',
            ];
        }

        $dir = rtrim(WRITEPATH, '/\\') . DIRECTORY_SEPARATOR . 'settings-snapshots';
        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException('Não foi possível preparar o diretório de snapshots de configuração.');
        }

        $safeAction = preg_replace('/[^a-z0-9_\-]+/i', '-', strtolower($action)) ?: 'snapshot';
        $timestamp = Time::now('UTC')->format('Ymd_His');
        $random = bin2hex(random_bytes(4));
        $fileName = sprintf('settings_%s_%s_%s.json', $safeAction, $timestamp, $random);
        $filePath = $dir . DIRECTORY_SEPARATOR . $fileName;
        $relativePath = 'settings-snapshots/' . $fileName;

        $document = [
            'meta' => array_merge($payload['meta'] ?? [], [
                'snapshot_reason' => $action,
                'created_by' => $actorId,
                'groups' => array_keys($settings),
                'context' => $this->sanitizeContext($context),
            ]),
            'settings' => $settings,
        ];

        $json = json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || file_put_contents($filePath, $json, LOCK_EX) === false) {
            throw new RuntimeException('Falha ao gravar snapshot preventivo das configurações.');
        }

        supportponto_log_event('warning', 'settings', 'destructive_snapshot_created', [
            'action' => $action,
            'actor_id' => $actorId,
            'snapshot' => $relativePath,
            'groups' => array_keys($settings),
        ]);

        $this->audit()->log(
            $actorId,
            'SETTINGS_SNAPSHOT_CREATED',
            'settings',
            null,
            null,
            [
                'action' => $action,
                'snapshot' => $relativePath,
                'groups' => array_keys($settings),
            ],
            'Snapshot preventivo criado antes de ação destrutiva em configurações.',
            'warning'
        );

        return [
            'success' => true,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'relative_path' => $relativePath,
            'groups' => array_values(array_keys($settings)),
            'message' => 'Snapshot preventivo criado com sucesso.',
        ];
    }

    /** @param list<string>|null $groups */
    public function assertWebResetAllowed(?array $groups): void
    {
        $allowedGroups = $this->settingsTransfer()->allowedGroups();
        $targetGroups = $groups === null ? [] : array_values(array_intersect($allowedGroups, $groups));

        if ($targetGroups === []) {
            throw new RuntimeException('Reset global ou de grupo não exportável via interface web está desabilitado. Utilize rotina controlada via CLI.');
        }
    }

    private function settingsTransfer(): SettingsTransferService
    {
        return $this->settingsTransferService ?? new SettingsTransferService();
    }

    private function audit(): AuditModel
    {
        return $this->auditModel ?? new AuditModel();
    }

    /** @param array<string,mixed> $context @return array<string,mixed> */
    private function sanitizeContext(array $context): array
    {
        unset($context['current_password'], $context['password'], $context['token'], $context['secret']);

        return $context;
    }
}
