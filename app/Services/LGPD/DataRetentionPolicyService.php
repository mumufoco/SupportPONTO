<?php

namespace App\Services\LGPD;

use App\Models\AuditModel;

class DataRetentionPolicyService
{
    /**
     * @return array<string,array<string,mixed>>
     */
    public function policies(): array
    {
        return [
            'employee_master_data' => [
                'label' => 'Cadastro do colaborador',
                'retention_days' => (int) env('LGPD_RETENTION_EMPLOYEE_DAYS', 3650),
                'trigger' => 'Após desligamento ou encerramento da finalidade trabalhista.',
                'action' => 'Anonimização seletiva após retenção legal mínima.',
                'legal_basis' => 'Obrigação legal trabalhista/contratual.',
            ],
            'labor_records' => [
                'label' => 'Registros de jornada e documentos trabalhistas',
                'retention_days' => (int) env('LGPD_RETENTION_LABOR_DAYS', 3650),
                'trigger' => 'Após encerramento do vínculo e fim de prazos legais.',
                'action' => 'Retenção segura e posterior anonimização quando juridicamente possível.',
                'legal_basis' => 'Obrigação legal trabalhista.',
            ],
            'biometric_templates' => [
                'label' => 'Templates e arquivos biométricos',
                'retention_days' => (int) env('LGPD_RETENTION_BIOMETRIC_DAYS', 180),
                'trigger' => 'Após revogação aplicável, desligamento ou substituição do método de autenticação.',
                'action' => 'Expurgo criptográfico/remoção segura e auditoria.',
                'legal_basis' => 'Dado sensível — LGPD Art. 11.',
            ],
            'geolocation_records' => [
                'label' => 'Geolocalização operacional',
                'retention_days' => (int) env('LGPD_RETENTION_GEOLOCATION_DAYS', 730),
                'trigger' => 'Após fechamento e auditoria do ciclo de ponto.',
                'action' => 'Minimização/anomização de coordenadas quando não houver obrigação legal ativa.',
                'legal_basis' => 'Execução contratual/consentimento quando aplicável.',
            ],
            'security_audit_logs' => [
                'label' => 'Logs de segurança e auditoria',
                'retention_days' => (int) env('LGPD_RETENTION_AUDIT_DAYS', 1825),
                'trigger' => 'Após expiração do interesse legítimo de segurança e rastreabilidade.',
                'action' => 'Retenção imutável e expurgo controlado por rotina administrativa.',
                'legal_basis' => 'Segurança, prevenção à fraude e obrigação legal.',
            ],
            'data_exports' => [
                'label' => 'Arquivos de exportação do titular',
                'retention_days' => (int) env('LGPD_RETENTION_EXPORT_DAYS', 2),
                'trigger' => 'Após geração da exportação ou expiração do link.',
                'action' => 'Remover ZIP expirado e metadados conforme política.',
                'legal_basis' => 'Atendimento de direito do titular.',
            ],
        ];
    }

    public function policy(string $key): ?array
    {
        $policies = $this->policies();

        return $policies[$key] ?? null;
    }

    public function calculateRetentionUntil(string $policyKey, ?string $baseDate = null): string
    {
        $policy = $this->policy($policyKey) ?? ['retention_days' => 3650];
        $base = strtotime($baseDate ?: date('Y-m-d H:i:s')) ?: time();

        return date('Y-m-d', strtotime('+' . (int) $policy['retention_days'] . ' days', $base));
    }

    /**
     * @return array<string,mixed>
     */
    public function purgeExpiredExports(): array
    {
        $db = \Config\Database::connect();
        if (!$db->tableExists('data_exports')) {
            return ['success' => true, 'deleted_files' => 0, 'marked_expired' => 0];
        }

        $expired = $db->table('data_exports')
            ->where('expires_at <', date('Y-m-d H:i:s'))
            ->whereIn('status', ['completed', 'expired'])
            ->get()
            ->getResult();

        $deleted = 0;
        foreach ($expired as $row) {
            $path = WRITEPATH . 'exports/lgpd/' . $row->export_id . '.zip';
            if (is_file($path) && @unlink($path)) {
                $deleted++;
            }
        }

        if ($expired !== []) {
            $ids = array_map(static fn($row) => $row->id, $expired);
            $db->table('data_exports')->whereIn('id', $ids)->update(['status' => 'expired', 'updated_at' => date('Y-m-d H:i:s')]);
            (new AuditModel())->log(null, 'LGPD_EXPORT_PURGE', 'data_exports', null, null, [
                'expired_exports' => count($expired),
                'deleted_files' => $deleted,
            ], 'Expurgo de exportações LGPD expiradas executado.', 'info');
        }

        return ['success' => true, 'deleted_files' => $deleted, 'marked_expired' => count($expired)];
    }
}
