<?php

namespace Tests\Integration;

use App\Models\AuditModel;
use Tests\Support\AuditTestCase;

/**
 * AuditIntegrityIntegrationTest
 *
 * Cobertura de integração mínima e real do subsistema de integridade da
 * auditoria (cadeia de checksums encadeados estilo "blockchain simples"):
 *
 *  - Inserção encadeada via AuditModel::log() / insertWithIntegrityLock()
 *    produz uma cadeia válida (`row_checksum` derivado do checksum anterior).
 *  - AuditModel::verifyIntegrity() reconstrói a cadeia elo a elo e detecta
 *    adulteração de qualquer registro intermediário.
 *  - AuditModel::resolveIntegrityAnchor() permite que a verificação retome a
 *    cadeia a partir de uma âncora (`audit_chain_anchors`) após uma retenção
 *    legítima, em vez de iniciar sempre do "genesis" — preservando a
 *    continuidade da prova de integridade mesmo quando registros antigos são
 *    removidos dentro da janela de manutenção controlada.
 *
 * Conformidade de referência: Portaria MTE 671/2021, LGPD Art. 37,
 * `docs/operations/QA_RELEASE_GATE_CURRENT.md` (rodadas 250-254 / 270-273).
 *
 * @internal
 */
final class AuditIntegrityIntegrationTest extends AuditTestCase
{
    private AuditModel $auditModel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->auditModel = new AuditModel();
    }

    public function testChainedInsertsProduceAValidIntegrityChain(): void
    {
        $this->logSampleEvent('CREATE', 1);
        $this->logSampleEvent('UPDATE', 1);
        $this->logSampleEvent('DELETE', 1);

        $result = $this->auditModel->verifyIntegrity();

        $this->assertTrue($result['valid'], 'A cadeia recém-criada deve ser íntegra.');
        $this->assertSame(3, $result['checked']);
        $this->assertSame([], $result['tampered_ids']);
        $this->assertSame('genesis', $result['anchor_used'] ?? 'genesis', 'Sem âncora salva, a cadeia deve partir do genesis.');
    }

    public function testVerifyIntegrityDetectsTamperedRowChecksum(): void
    {
        $this->logSampleEvent('CREATE', 10);
        $this->logSampleEvent('UPDATE', 10);
        $thirdId = $this->logSampleEventAndGetId('DELETE', 10);

        // Adulteração direta no banco — simula alteração fora do caminho canônico
        // (ex.: acesso direto ao SGBD), que a cadeia de checksums deve detectar.
        $this->db->table('audit_logs')
            ->where('id', $thirdId)
            ->update(['row_checksum' => str_repeat('0', 64)]);

        $result = $this->auditModel->verifyIntegrity();

        $this->assertFalse($result['valid'], 'A adulteração de um row_checksum deve invalidar a cadeia.');
        $this->assertContains($thirdId, $result['tampered_ids']);
        $this->assertTrue($result['forensic_review_required']);
    }

    public function testVerifyIntegrityResumesFromChainAnchorAfterRetention(): void
    {
        $this->logSampleEvent('CREATE', 20);
        $secondId = $this->logSampleEventAndGetId('UPDATE', 20);
        $this->logSampleEvent('DELETE', 20);

        $secondRecord = $this->db->table('audit_logs')->where('id', $secondId)->get()->getRowArray();
        $this->assertIsArray($secondRecord);

        // Simula o resultado de uma retenção legítima: o registro mais antigo foi removido
        // e uma âncora foi persistida com o checksum do último registro removido —
        // exatamente o que AuditMaintenanceService::saveChainAnchor() faz em produção.
        $cutoffAt = $secondRecord['created_at'];
        $anchorChecksum = (string) $secondRecord['row_checksum'];

        $this->db->table('audit_logs')->where('id', $secondId)->orWhere('id <', $secondId)->delete();

        $this->db->table('audit_chain_anchors')->insert([
            'cutoff_at'       => $cutoffAt,
            'anchor_checksum' => $anchorChecksum,
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        $result = $this->auditModel->verifyIntegrity();

        $this->assertTrue($result['valid'], 'A cadeia retomada a partir da âncora deve permanecer íntegra.');
        $this->assertSame($anchorChecksum, $result['anchor_used'], 'verifyIntegrity() deve reconstruir a cadeia a partir da âncora salva, não do genesis.');
    }

    private function logSampleEvent(string $action, int $recordId): void
    {
        $this->logSampleEventAndGetId($action, $recordId);
    }

    private function logSampleEventAndGetId(string $action, int $recordId): int
    {
        $logged = $this->auditModel->log(
            null,
            $action,
            'employees',
            $recordId,
            null,
            ['sample' => $action],
            "Evento de teste de integridade ({$action})",
            'info',
            [],
            'integration-test',
            'system'
        );

        $this->assertTrue($logged, 'AuditModel::log() deve persistir o evento de auditoria.');

        $row = $this->db->table('audit_logs')->orderBy('id', 'DESC')->limit(1)->get()->getRowArray();
        $this->assertIsArray($row);

        return (int) $row['id'];
    }
}
