<?php

namespace Tests\Support;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Caso de teste base para o subsistema de auditoria.
 *
 * Centraliza a configuração comum (banco de testes via DatabaseTestTrait,
 * truncamento por teste, migrações aplicadas sob demanda) usada pelos testes
 * de integridade/retenção da trilha de auditoria, evitando duplicação entre
 * as suítes Integration e Feature relacionadas a `audit_logs`.
 *
 * @see \App\Models\AuditModel
 * @see \App\Services\Audit\AuditMaintenanceService
 * @see \App\Services\Audit\AuditMutationService
 */
abstract class AuditTestCase extends CIUnitTestCase
{
    use DatabaseTestTrait;

    /**
     * Migra o schema antes de cada teste e restaura o estado entre execuções,
     * para que a cadeia de checksums comece sempre de um estado conhecido (genesis).
     */
    protected $migrate = true;

    /**
     * Trunca as tabelas (em vez de envolver em transação) — necessário porque
     * `AuditModel::insertWithIntegrityLock()` usa locks/âncoras sensíveis a
     * estado persistido entre conexões.
     */
    protected $refresh = true;

    protected $namespace = 'App';
}
