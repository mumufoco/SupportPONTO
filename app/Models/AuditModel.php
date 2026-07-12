<?php

namespace App\Models;

use App\Support\SensitiveDataSanitizer;
use CodeIgniter\Model;

/**
 * AuditModel — Registro imutável de auditoria
 *
 * MELHORIA 6: Implementa cadeia de integridade via checksum SHA-256 encadeado.
 * Cada registro contém o hash do registro anterior, formando uma blockchain
 * simples que detecta adulteração mesmo fora do banco de dados.
 *
 * Conformidade: Portaria MTE 671/2021, CLT Art. 74, LGPD Art. 37.
 */
class AuditModel extends Model
{
    protected $table      = 'audit_logs';
    protected $primaryKey = 'id';
    protected $returnType = 'object';

    protected $allowedFields = [
        'user_id',
        'action',
        'table_name',
        'record_id',
        'entity_type',
        'entity_id',
        'old_values',
        'new_values',
        'description',
        'ip_address',
        'user_agent',
        'url',
        'method',
        'level',
        'row_checksum',
        'actor_type',
        'request_id',
        'source',
        'context_data',
        'created_at',
    ];

    protected $beforeInsert = ['normalizeAuditContractBeforeInsert'];
    protected $afterFind = ['hydrateAuditContractAfterFind'];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = false; // MELHORIA 6: Registros nunca são atualizados

    /**
     * Registra um evento de auditoria com checksum encadeado.
     *
     * O checksum garante que qualquer adulteração posterior seja detectável
     * ao verificar a cadeia de hashes sem precisar de acesso ao banco original.
     */
    public function log(
        ?int    $userId,
        string  $action,
        ?string $tableName   = null,
        ?int    $recordId    = null,
        ?array  $oldValues   = null,
        ?array  $newValues   = null,
        ?string $description = null,
        string  $level       = 'info',
        array   $context     = [],
        ?string $source      = null,
        ?string $actorType   = null
    ): bool {
        $requestContext = $this->getRequestContext();

        $data = [
            'user_id'      => $userId,
            'action'       => strtoupper($action),
            'table_name'   => $tableName,
            'record_id'    => $recordId,
            'entity_type'  => $tableName,
            'entity_id'    => $recordId,
            'old_values'   => $oldValues  ? json_encode(SensitiveDataSanitizer::sanitizeForLogs($oldValues),  JSON_UNESCAPED_UNICODE) : null,
            'new_values'   => $newValues  ? json_encode(SensitiveDataSanitizer::sanitizeForLogs($newValues),  JSON_UNESCAPED_UNICODE) : null,
            'description'  => $description !== null ? (string) SensitiveDataSanitizer::sanitizeForLogs($description, 'description') : null,
            'ip_address'   => (string) SensitiveDataSanitizer::sanitizeForLogs($this->getClientIp(), 'ip_address'),
            'user_agent'   => (string) SensitiveDataSanitizer::sanitizeForLogs($this->getUserAgent(), 'user_agent'),
            'url'          => $requestContext['url'],
            'method'       => $requestContext['method'],
            'level'        => $this->normalizeLevel($level),
            'actor_type'   => $actorType ?: ($userId === null ? 'system' : 'user'),
            'request_id'   => $requestContext['request_id'],
            'source'       => $source ?: $requestContext['source'],
            'context_data' => $context !== [] ? json_encode(SensitiveDataSanitizer::sanitizeForLogs($context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'created_at'   => date('Y-m-d H:i:s'),
        ];

        try {
            return $this->insertWithIntegrityLock($data);
        } catch (\Throwable $e) {
            log_message('error', '[Audit] Falha ao gravar log de auditoria: ' . $e->getMessage());
            return false;
        }
    }


    /**
     * Registra auditoria usando o contrato canônico entity_type/entity_id.
     *
     * @param array<string, mixed> $data
     */
    public function insertCanonical(array $data): bool
    {
        $oldValues = $data['old_values'] ?? null;
        $newValues = $data['new_values'] ?? null;

        return $this->log(
            isset($data['user_id']) ? (int) $data['user_id'] : null,
            (string) ($data['action'] ?? 'UNKNOWN'),
            $data['entity_type'] ?? $data['table_name'] ?? null,
            isset($data['entity_id']) ? (int) $data['entity_id'] : (isset($data['record_id']) ? (int) $data['record_id'] : null),
            is_array($oldValues) ? $oldValues : null,
            is_array($newValues) ? $newValues : null,
            isset($data['description']) ? (string) $data['description'] : null,
            isset($data['level']) ? (string) $data['level'] : 'info',
            is_array($data['context_data'] ?? null) ? $data['context_data'] : (is_array($data['context'] ?? null) ? $data['context'] : []),
            isset($data['source']) ? (string) $data['source'] : null,
            isset($data['actor_type']) ? (string) $data['actor_type'] : null,
        );
    }

    /**
     * Verifica a integridade da cadeia de checksums.
     *
     * Detecta adulteração em qualquer ponto da cadeia.
     * Retorna array com ['valid' => bool, 'tampered_ids' => int[]].
     */
    public function verifyIntegrity(?int $limit = 1000): array
    {
        $records = $this->orderBy('id', 'ASC')
                        ->limit($limit ?? 1000)
                        ->findAll();

        $tampered = [];
        $oldestRemainingDate = null;
        if ($records !== []) {
            $oldestRemainingDate = (string) ($this->recordValue($records[0], 'created_at') ?? '');
        }

        $anchor = $this->resolveIntegrityAnchor($oldestRemainingDate ?: null);
        $previousChecksum = $anchor['checksum'] ?? 'genesis';

        $storedContinuationAdvances = 0;
        $storedContinuationIds = [];

        foreach ($records as $record) {
            $data = [
                'user_id'     => $this->recordValue($record, 'user_id'),
                'action'      => $this->recordValue($record, 'action'),
                'table_name'  => $this->recordValue($record, 'table_name'),
                'record_id'   => $this->recordValue($record, 'record_id'),
                'old_values'  => $this->recordValue($record, 'old_values'),
                'new_values'  => $this->recordValue($record, 'new_values'),
                'description' => $this->recordValue($record, 'description'),
                'ip_address'  => $this->recordValue($record, 'ip_address'),
                'user_agent'  => $this->recordValue($record, 'user_agent'),
                'url'         => $this->recordValue($record, 'url'),
                'method'      => $this->recordValue($record, 'method'),
                'level'       => $this->recordValue($record, 'level'),
                'actor_type'  => $this->recordValue($record, 'actor_type'),
                'request_id'  => $this->recordValue($record, 'request_id'),
                'source'      => $this->recordValue($record, 'source'),
                'context_data'=> $this->recordValue($record, 'context_data'),
                'created_at'  => $this->recordValue($record, 'created_at'),
            ];

            $expected = $this->computeChecksum($data, $previousChecksum);
            $current  = (string) ($this->recordValue($record, 'row_checksum') ?? '');

            if ($current === '' || !hash_equals($expected, $current)) {
                $recordId = (int) ($this->recordValue($record, 'id') ?? 0);
                $tampered[] = $recordId;

                // Estratégia tolerante: avançar pela cadeia armazenada evita uma cascata artificial
                // de falsos positivos após retenções legítimas e permite continuar a inspeção do restante
                // da cadeia. Para uso forense, os relatórios passam a explicitar quando isso ocorreu.
                if ($current !== '') {
                    $storedContinuationAdvances++;
                    $storedContinuationIds[] = $recordId;
                    $previousChecksum = $current;
                } else {
                    $previousChecksum = $expected;
                }

                continue;
            }

            $previousChecksum = $current;
        }

        return [
            'valid'          => empty($tampered),
            'checked'        => count($records),
            'tampered_ids'   => $tampered,
            'last_checksum'  => $previousChecksum,
            'anchor_used'    => $anchor['checksum'] ?? null,
            'anchor_cutoff'  => $anchor['cutoff_at'] ?? null,
            'continuation_mode' => 'stored_checksum_tolerant',
            'stored_checksum_advances' => $storedContinuationAdvances,
            'stored_checksum_advance_ids' => $storedContinuationIds,
            'forensic_review_required' => ! empty($tampered) || $storedContinuationAdvances > 0,
        ];
    }

    // ── Consultas ──────────────────────────────────────────────────────────────

    public function getFilteredLogs(array $filters = [], ?int $limit = null): array
    {
        $builder = $this->builder();
        $builder->select('audit_logs.*, employees.name as user_name')
                ->join('employees', 'employees.id = audit_logs.user_id', 'left');

        if (!empty($filters['user_id'])) {
            $builder->where('audit_logs.user_id', $filters['user_id']);
        }
        if (!empty($filters['action'])) {
            $builder->where('audit_logs.action', $filters['action']);
        }
        $entityFilter = (string) ($filters['entity_type'] ?? $filters['table_name'] ?? '');
        if ($entityFilter !== '') {
            $builder->groupStart()
                ->where('audit_logs.entity_type', $entityFilter)
                ->orWhere('audit_logs.table_name', $entityFilter)
                ->groupEnd();
        }
        if (!empty($filters['date_from'])) {
            $builder->where('audit_logs.created_at >=', $filters['date_from'] . ' 00:00:00');
        }
        if (!empty($filters['date_to'])) {
            $builder->where('audit_logs.created_at <=', $filters['date_to'] . ' 23:59:59');
        }
        if (!empty($filters['level'])) {
            $builder->where('audit_logs.level', $filters['level']);
        }
        if (!empty($filters['ip_address'])) {
            $builder->where('audit_logs.ip_address', $filters['ip_address']);
        }

        $builder->orderBy('audit_logs.created_at', 'DESC');

        return $limit ? $builder->paginate($limit) : $builder->get()->getResult();
    }

    public function getUniqueUsers(): array
    {
        return $this->select('audit_logs.user_id, employees.name')
                    ->join('employees', 'employees.id = audit_logs.user_id')
                    ->groupBy('audit_logs.user_id, employees.name')
                    ->orderBy('employees.name')
                    ->findAll();
    }

    public function getUniqueActions(): array
    {
        return $this->select('action')
                    ->groupBy('action')
                    ->orderBy('action')
                    ->findAll();
    }

    public function getUniqueTables(): array
    {
        return $this->select('COALESCE(entity_type, table_name) AS entity_type')
                    ->where('(entity_type IS NOT NULL OR table_name IS NOT NULL)')
                    ->groupBy('COALESCE(entity_type, table_name)')
                    ->orderBy('COALESCE(entity_type, table_name)')
                    ->findAll();
    }

    // ── Helpers internos ───────────────────────────────────────────────────────


    /**
     * Resolve a âncora aplicável para a verificação de integridade.
     *
     * Quando registros antigos são descartados por política de retenção,
     * o primeiro registro remanescente deixa de ter 'genesis' como predecessor.
     * A âncora persiste o checksum do último elo removido para manter a cadeia verificável.
     *
     * @return array{checksum: string, cutoff_at: string}|null
     */
    protected function resolveIntegrityAnchor(?string $oldestRemainingDate): ?array
    {
        if ($oldestRemainingDate === null || $oldestRemainingDate === '' || ! $this->db->tableExists('audit_chain_anchors')) {
            return null;
        }

        $row = $this->db->table('audit_chain_anchors')
            ->select('cutoff_at, anchor_checksum')
            ->where('cutoff_at <=', $oldestRemainingDate)
            ->orderBy('cutoff_at', 'DESC')
            ->limit(1)
            ->get()
            ->getRowArray();

        if (! is_array($row) || ($row['anchor_checksum'] ?? '') === '') {
            return null;
        }

        return [
            'checksum' => (string) $row['anchor_checksum'],
            'cutoff_at' => (string) ($row['cutoff_at'] ?? ''),
        ];
    }


    /**
     * Normaliza o contrato semântico da auditoria antes do INSERT.
     *
     * entity_type/entity_id são o contrato canônico atual.
     * table_name/record_id permanecem como espelho compatível para legado.
     * Isso impede que produtores diferentes falem linguagens diferentes.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    protected function normalizeAuditContractBeforeInsert(array $payload): array
    {
        if (! isset($payload['data']) || ! is_array($payload['data'])) {
            return $payload;
        }

        $payload['data'] = $this->normalizeAuditContractArray($payload['data']);

        return $payload;
    }

    /**
     * Hidrata aliases do contrato de auditoria após leitura.
     *
     * Consumidores antigos podem continuar lendo table_name/record_id,
     * enquanto os novos passam a preferir entity_type/entity_id.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    protected function hydrateAuditContractAfterFind(array $payload): array
    {
        if (! array_key_exists('data', $payload)) {
            return $payload;
        }

        $payload['data'] = $this->hydrateAuditContractValue($payload['data']);

        return $payload;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeAuditContractArray(array $data): array
    {
        $entityType = $data['entity_type'] ?? $data['table_name'] ?? null;
        $entityId   = $data['entity_id'] ?? $data['record_id'] ?? null;

        $data['entity_type'] = $entityType;
        $data['table_name']  = $entityType;
        $data['entity_id']   = $entityId;
        $data['record_id']   = $entityId;

        return $data;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function hydrateAuditContractValue(mixed $value): mixed
    {
        if (is_array($value)) {
            if ($this->isAuditRow($value)) {
                return $this->normalizeAuditContractArray($value);
            }

            return array_map(fn (mixed $row) => $this->hydrateAuditContractValue($row), $value);
        }

        if (is_object($value)) {
            $entityType = $value->entity_type ?? $value->table_name ?? null;
            $entityId   = $value->entity_id ?? $value->record_id ?? null;

            $value->entity_type = $entityType;
            $value->table_name  = $entityType;
            $value->entity_id   = $entityId;
            $value->record_id   = $entityId;

            return $value;
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function isAuditRow(array $row): bool
    {
        return array_key_exists('action', $row)
            || array_key_exists('table_name', $row)
            || array_key_exists('record_id', $row)
            || array_key_exists('entity_type', $row)
            || array_key_exists('entity_id', $row);
    }

    /**
     * Calcula o checksum encadeado do registro.
     *
     * O elo anterior é recebido explicitamente para evitar que a verificação da
     * cadeia consulte sempre o último registro da tabela. A atomicidade da leitura
     * do elo anterior no INSERT é tratada separadamente em pacote específico.
     */

    /**
     * @param object|array<string, mixed> $record
     */
    private function recordValue(object|array $record, string $field): mixed
    {
        if (is_array($record)) {
            return $record[$field] ?? null;
        }

        return $record->{$field} ?? null;
    }

    private function computeChecksum(array $data, string $previousChecksum): string
    {
        $payload = implode('|', [
            $data['user_id']    ?? '',
            $data['action']     ?? '',
            $data['table_name'] ?? '',
            $data['record_id']  ?? '',
            $data['old_values'] ?? '',
            $data['new_values'] ?? '',
            $data['description']?? '',
            $data['ip_address'] ?? '',
            $data['user_agent'] ?? '',
            $data['url'] ?? '',
            $data['method'] ?? '',
            $data['level']      ?? '',
            $data['actor_type'] ?? '',
            $data['request_id'] ?? '',
            $data['source'] ?? '',
            $data['context_data'] ?? '',
            $data['created_at'] ?? '',
            $previousChecksum,
        ]);

        return hash('sha256', $payload);
    }

    /**
     * Retorna o checksum mais recente persistido na tabela.
     *
     * Mantido isolado para que a geração do novo elo e a futura correção de
     * concorrência (transação/lock) possam evoluir sem afetar a verificação.
     */

    private function getLatestChecksum(): string
    {
        $latest = $this->select('row_checksum')
            ->orderBy('id', 'DESC')
            ->first();

        return is_object($latest)
            ? (string) ($latest->row_checksum ?? 'genesis')
            : (string) (($latest['row_checksum'] ?? 'genesis'));
    }

    /**
     * Insere um novo registro de auditoria com serialização explícita da cadeia.
     *
     * A leitura do checksum anterior, o cálculo do novo elo e o INSERT ocorrem
     * dentro da mesma transação e sob lock do banco para evitar fork da cadeia
     * em gravações concorrentes.
     */
    private function insertWithIntegrityLock(array $data): bool
    {
        $db = $this->db;
        $driver = strtolower((string) $db->DBDriver);
        $acquiredNamedLock = false;

        $db->transBegin();

        try {
            if ($driver === 'postgre') {
                $db->query("SELECT pg_advisory_xact_lock(hashtext('audit_logs_integrity_chain'))");
            } elseif ($driver === 'mysqli') {
                $result = $db->query("SELECT GET_LOCK('audit_logs_integrity_chain', 30) AS lock_acquired");
                $row = $result ? $result->getRowArray() : null;
                if ((int) ($row['lock_acquired'] ?? 0) !== 1) {
                    throw new \RuntimeException('Não foi possível adquirir lock da cadeia de auditoria.');
                }
                $acquiredNamedLock = true;
            }

            $previousChecksum = $this->getLatestChecksumForWrite($driver);
            $data['row_checksum'] = $this->computeChecksum($data, $previousChecksum);

            $inserted = $this->builder()->insert($data);
            if ($inserted === false) {
                throw new \RuntimeException('Falha ao inserir registro de auditoria com checksum encadeado.');
            }

            if ($acquiredNamedLock) {
                $db->query("SELECT RELEASE_LOCK('audit_logs_integrity_chain')");
            }

            $db->transCommit();
            return true;
        } catch (\Throwable $e) {
            if ($acquiredNamedLock) {
                try {
                    $db->query("SELECT RELEASE_LOCK('audit_logs_integrity_chain')");
                } catch (\Throwable $ignored) {
                }
            }

            if ($db->transStatus() !== false) {
                $db->transRollback();
            }

            throw $e;
        }
    }

    /**
     * Lê o último elo persistido da cadeia sob lock da conexão/transação atual.
     */
    private function getLatestChecksumForWrite(string $driver): string
    {
        if ($driver === 'postgre') {
            $query = $this->db->query(
                sprintf('SELECT row_checksum FROM %s ORDER BY id DESC LIMIT 1 FOR UPDATE', $this->table)
            );
            $row = $query->getRowArray();

            return (string) ($row['row_checksum'] ?? 'genesis');
        }

        return $this->getLatestChecksum();
    }

    /**
     * Captura contexto HTTP/CLI sem tornar a auditoria dependente da camada web.
     *
     * @return array{url:?string,method:?string,request_id:string,source:string}
     */
    protected function getRequestContext(): array
    {
        $source = is_cli() ? 'cli' : 'web';
        $url = null;
        $method = is_cli() ? 'CLI' : ($_SERVER['REQUEST_METHOD'] ?? null);
        $requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? $_SERVER['HTTP_X_CORRELATION_ID'] ?? null;

        try {
            $request = \Config\Services::request();
            if (method_exists($request, 'getUri')) {
                $url = substr((string) $request->getUri(), 0, 500);
            }
            if (method_exists($request, 'getMethod')) {
                $method = strtoupper((string) $request->getMethod());
            }
            if (method_exists($request, 'getHeaderLine')) {
                $headerRequestId = trim((string) $request->getHeaderLine('X-Request-ID'));
                $headerCorrelationId = trim((string) $request->getHeaderLine('X-Correlation-ID'));
                $requestId = $headerRequestId !== '' ? $headerRequestId : ($headerCorrelationId !== '' ? $headerCorrelationId : $requestId);
            }
        } catch (\Throwable $ignored) {
            // A auditoria também precisa funcionar em CLI, seeders, jobs e instalador.
        }

        if (! is_string($requestId) || trim($requestId) === '') {
            $requestId = 'sp-' . date('YmdHis') . '-' . bin2hex(random_bytes(6));
        }

        return [
            'url' => $url,
            'method' => $method !== null ? substr(strtoupper((string) $method), 0, 10) : null,
            'request_id' => substr(preg_replace('/[^a-zA-Z0-9_.:-]/', '', $requestId) ?: $requestId, 0, 80),
            'source' => $source,
        ];
    }

    protected function normalizeLevel(string $level): string
    {
        $level = strtolower(trim($level));
        return in_array($level, ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'], true)
            ? $level
            : 'info';
    }

    /**
     * SEC-12 FIX: IP via CI4 request (respeita App::$proxyIPs).
     */
    protected function getClientIp(): string
    {
        try {
            return \Config\Services::request()->getIPAddress();
        } catch (\Throwable $e) {
            return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        }
    }

    protected function getUserAgent(): string
    {
        return substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
    }

    public function search(array $filters = []): array
    {
        $builder = $this->db->table($this->table);
        if (!empty($filters['action'])) { $builder->like('action', $filters['action']); }
        if (!empty($filters['user_id'])) { $builder->where('user_id', (int) $filters['user_id']); }
        if (!empty($filters['start_date'])) { $builder->where('created_at >=', $filters['start_date']); }
        if (!empty($filters['end_date'])) { $builder->where('created_at <=', $filters['end_date']); }
        if (!empty($filters['level'])) { $builder->where('level', $filters['level']); }
        $limit = isset($filters['limit']) ? (int) $filters['limit'] : 500;
        $builder->orderBy('created_at', 'DESC')->limit($limit);
        return $builder->get()->getResult();
    }

}
