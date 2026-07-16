<?php

namespace App\Services\Backup;

use App\Models\BackupCheckModel;

/**
 * Governanca de backup, reproduzindo o padrao do SupportCHECK
 * (BackupCheckService/BackupCheck): nao basta o backup existir no disco --
 * persistimos um HISTORICO de checagens (nao so o estado ao vivo), avaliando
 * prontidao (DatabaseBackupService::verifyReadiness()), existencia/tamanho
 * do backup mais recente (DatabaseBackupService::listBackups()) e se ja
 * houve um teste de restauracao registrado (RestoreTestRegistryService).
 */
class BackupCheckService
{
    public function __construct(
        private readonly ?DatabaseBackupService $backupService = null,
        private readonly ?BackupCheckModel $model = null,
        private readonly ?RestoreTestRegistryService $restoreTests = null,
    ) {
    }

    private function backup(): DatabaseBackupService
    {
        return $this->backupService ?? new DatabaseBackupService();
    }

    private function checks(): BackupCheckModel
    {
        return $this->model ?? new BackupCheckModel();
    }

    private function restore(): RestoreTestRegistryService
    {
        return $this->restoreTests ?? new RestoreTestRegistryService();
    }

    public function run(): object
    {
        $readiness = $this->backup()->verifyReadiness();
        $backups = $this->backup()->listBackups();
        $latest = $backups[0] ?? null;

        $risks = [];

        // Os checks 'runtime_policy'/'pg_dump'/'psql'/'proc_open' de proposito
        // reportam bloqueado quando chamados a partir de uma requisicao web
        // (ProcessSafety::canRunDatabaseBackupInCurrentRuntime() so libera
        // execucao de shell via CLI) -- isso e o comportamento de seguranca
        // CORRETO (o backup de verdade so roda via cron/worker CLI, nunca a
        // partir do processo web), nao um problema real. So tratamos como
        // risco de fato quando o check e chamado em contexto CLI (onde o
        // resultado reflete se o cron consegue rodar) ou quando a causa e
        // algo independente de runtime (config do banco, caminho do backup).
        $runtimeGatedLabels = ['runtime_policy', 'pg_dump', 'psql', 'proc_open'];
        $realFailures = [];
        foreach (($readiness['checks'] ?? []) as $label => $check) {
            if (($check['status'] ?? 'error') === 'ok') {
                continue;
            }
            if (!is_cli() && in_array($label, $runtimeGatedLabels, true)) {
                continue; // esperado a partir da web, nao e risco
            }
            $realFailures[] = $label;
        }
        if ($realFailures !== []) {
            $risks[] = 'Ambiente não está pronto para gerar backup (' . implode(', ', $realFailures) . ').';
        }
        if (!$latest) {
            $risks[] = 'Nenhum backup encontrado no destino configurado.';
        }
        if ($latest && (int) ($latest['size'] ?? 0) <= 0) {
            $risks[] = 'Backup mais recente encontrado com tamanho zero.';
        }
        if ($latest && (int) ($latest['age_days'] ?? 0) > 2) {
            $risks[] = 'Backup mais recente tem mais de 2 dias.';
        }
        if (!$this->restore()->latest()) {
            $risks[] = 'Nenhum teste de restauração registrado.';
        }

        $data = [
            'status' => $risks === [] ? 'ok' : 'warning',
            'last_backup_at' => $latest ? $latest['date'] : null,
            'backup_size_bytes' => $latest ? (int) $latest['size'] : 0,
            'destination' => $readiness['backup_path'] ?? null,
            'integrity_ok' => $latest !== null && (int) ($latest['size'] ?? 0) > 0,
            'critical_files' => json_encode([
                '.env' => is_file(ROOTPATH . '.env'),
                'writable' => is_dir(WRITEPATH),
            ]),
            'risks' => json_encode($risks),
            'checked_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $this->checks()->insert($data);

        return $this->checks()->latest();
    }

    public function latest(): ?object
    {
        return $this->checks()->latest();
    }
}
