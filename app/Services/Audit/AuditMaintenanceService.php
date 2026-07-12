<?php

namespace App\Services\Audit;

use App\Models\AuditModel;
use CodeIgniter\Database\BaseConnection;

class AuditMaintenanceService
{
    public const LEGAL_MINIMUM_RETENTION_DAYS = 1825;

    private readonly AuditMutationService $auditMutationService;

    public function __construct(
        private readonly BaseConnection $db,
        private readonly AuditModel $auditModel = new AuditModel(),
        ?AuditMutationService $auditMutationService = null,
    ) {
        $this->auditMutationService = $auditMutationService ?? AuditMutationService::createDefault();
    }

    public static function createDefault(): self
    {
        return new self(db_connect(), new AuditModel(), AuditMutationService::createDefault());
    }

    public function clearOlderThanDays(int $days, int $actorId): int
    {
        if ($days < self::LEGAL_MINIMUM_RETENTION_DAYS) {
            throw new \InvalidArgumentException(
                'Retenção mínima legal é de ' . self::LEGAL_MINIMUM_RETENTION_DAYS . ' dias (Portaria MTE 671/2021).'
            );
        }

        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $auditTable = $this->auditModel->getTable();
        $lastDeletedChecksum = $this->getChecksumJustBeforeCutoff($auditTable, $cutoffDate);

        $deletedCount = (int) $this->auditMutationService->runControlled(function () use ($auditTable, $cutoffDate, $lastDeletedChecksum): int {
            if ($lastDeletedChecksum !== null) {
                $this->saveChainAnchor($cutoffDate, $lastDeletedChecksum);
            }

            $this->db->table($auditTable)->where('created_at <', $cutoffDate)->delete();
            return (int) $this->db->affectedRows();
        }, 'retention');

        $this->auditModel->log(
            $actorId,
            'AUDIT_LOGS_CLEARED',
            'audit_logs',
            null,
            null,
            [
                'days' => $days,
                'deleted_count' => $deletedCount,
                'cutoff_at' => $cutoffDate,
                'chain_anchor_checksum' => $lastDeletedChecksum,
            ],
            "Logs de auditoria mais antigos que {$days} dias foram excluídos ({$deletedCount} registros)",
            'warning'
        );

        return $deletedCount;
    }

    private function getChecksumJustBeforeCutoff(string $auditTable, string $cutoffDate): ?string
    {
        $row = $this->db->table($auditTable)
            ->select('id, row_checksum, created_at')
            ->where('created_at <', $cutoffDate)
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->limit(1)
            ->get()
            ->getRowArray();

        $checksum = is_array($row) ? (string) ($row['row_checksum'] ?? '') : '';
        return $checksum !== '' ? $checksum : null;
    }

    private function saveChainAnchor(string $cutoffDate, string $checksum): void
    {
        if (! $this->db->tableExists('audit_chain_anchors')) {
            return;
        }

        $builder = $this->db->table('audit_chain_anchors');
        $existing = $builder->where('cutoff_at', $cutoffDate)->get()->getRowArray();
        if (is_array($existing) && $existing !== []) {
            return;
        }

        $builder->insert([
            'cutoff_at' => $cutoffDate,
            'anchor_checksum' => $checksum,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
