<?php

namespace App\Services\Audit;

use App\Enums\Role;
use App\Models\AuditModel;
use App\Models\EmployeeModel;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;

class AuditQueryService
{
    public function __construct(
        private readonly BaseConnection $db,
        private readonly EmployeeModel $employeeModel = new EmployeeModel(),
        private readonly AuditModel $auditModel = new AuditModel(),
    ) {
    }

    public static function createDefault(): self
    {
        return new self(db_connect(), new EmployeeModel(), new AuditModel());
    }

    public function indexOptions(?array $actor = null): array
    {
        return [
            'actions' => $this->distinctActions($actor),
            'entities' => $this->distinctEntities($actor),
            'levels' => ['info', 'warning', 'error', 'critical'],
            'stats' => $this->statistics($actor),
            'users' => $this->scopedUsers($actor),
        ];
    }

    public function datatablePayload(array $requestData, ?array $actor = null): array
    {
        $draw = (int) ($requestData['draw'] ?? 0);
        $start = (int) ($requestData['start'] ?? 0);
        $length = (int) ($requestData['length'] ?? 25);
        $searchValue = trim((string) (($requestData['search']['value'] ?? '') ?: ''));

        $orderColumnIndex = (int) (($requestData['order'][0]['column'] ?? 0));
        $orderDir = strtolower((string) ($requestData['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

        $filters = [
            'user_id' => $requestData['filter_user_id'] ?? null,
            'action' => $requestData['filter_action'] ?? null,
            'entity' => $requestData['filter_entity'] ?? null,
            'level' => $requestData['filter_level'] ?? null,
            'start_date' => $requestData['filter_start_date'] ?? null,
            'end_date' => $requestData['filter_end_date'] ?? null,
        ];

        $columns = ['id', 'user_id', 'action', 'entity_type', 'description', 'level', 'created_at'];
        $orderColumn = $columns[$orderColumnIndex] ?? 'created_at';
        if ($orderColumn === 'entity_type') {
            $orderColumn = 'COALESCE(audit_logs.entity_type, audit_logs.table_name)';
        } else {
            $orderColumn = 'audit_logs.' . $orderColumn;
        }

        $builder = $this->baseScopeBuilder($actor);
        $this->applyDatatableFilters($builder, $filters, $searchValue);

        $recordsFiltered = $this->countDistinctAuditIds(clone $builder);
        $logs = $builder
            ->select('audit_logs.id, audit_logs.user_id, audit_logs.action, audit_logs.description, audit_logs.level, audit_logs.ip_address, audit_logs.created_at, COALESCE(audit_logs.entity_type, audit_logs.table_name) AS entity_type, COALESCE(audit_logs.entity_id, audit_logs.record_id) AS entity_id')
            ->distinct()
            ->orderBy($orderColumn, $orderDir)
            ->limit($length > 0 ? $length : 25, $start)
            ->get()
            ->getResult();

        $employeeIds = array_values(array_unique(array_filter(array_map(static fn(object $log): ?int => $log->user_id ? (int) $log->user_id : null, $logs))));
        $employees = $this->employeeModel->getNamesByIds($employeeIds);

        $data = array_map(function (object $log) use ($employees): array {
            $userName = $employees[$log->user_id] ?? ($log->user_id ? "ID: {$log->user_id}" : 'Sistema');

            return [
                'id' => (int) $log->id,
                'user' => $userName,
                'action' => $this->formatAction((string) $log->action),
                'entity' => $this->formatEntity((string) ($log->entity_type ?? ''), $log->entity_id ? (int) $log->entity_id : null),
                'description' => $log->description ?? '-',
                'level' => $this->formatLevel((string) ($log->level ?? 'info')),
                'ip_address' => $log->ip_address ?? '-',
                'created_at' => date('d/m/Y H:i:s', strtotime((string) $log->created_at)),
                'details' => (int) $log->id,
            ];
        }, $logs);

        return [
            'draw' => $draw,
            'recordsTotal' => $this->countDistinctAuditIds($this->baseScopeBuilder($actor)),
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ];
    }

    public function showData(int $id, ?array $actor = null): ?array
    {
        $log = $this->baseScopeBuilder($actor)
            ->select('audit_logs.*')
            ->where('audit_logs.id', $id)
            ->get()
            ->getFirstRow();

        if (!$log) {
            return null;
        }

        $user = null;
        if (!empty($log->user_id)) {
            $user = $this->employeeModel->find((int) $log->user_id);
        }

        return [
            'log' => $log,
            'user' => $user,
            'oldData' => $this->decodeJsonField($log->old_values ?? null),
            'newData' => $this->decodeJsonField($log->new_values ?? null),
        ];
    }

    public function detailsData(int $id, ?array $actor = null): ?array
    {
        $data = $this->showData($id, $actor);
        if ($data === null) {
            return null;
        }

        /** @var object $log */
        $log = $data['log'];
        $employee = $data['user'];

        return [
            'id' => $log->id,
            'action' => $log->action,
            'entity_type' => $log->entity_type ?? $log->table_name ?? null,
            'entity_id' => $log->entity_id ?? $log->record_id ?? null,
            'description' => $log->description,
            'level' => $log->level,
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'url' => $log->url ?? null,
            'method' => $log->method ?? null,
            'old_values' => $data['oldData'],
            'new_values' => $data['newData'],
            'created_at' => $log->created_at,
            'employee' => $employee ? [
                'id' => $employee->id,
                'name' => $employee->name,
                'email' => $employee->email,
            ] : null,
        ];
    }

    private function statistics(?array $actor = null): array
    {
        $todayStart = date('Y-m-d') . ' 00:00:00';
        $weekStart = date('Y-m-d', strtotime('-7 days')) . ' 00:00:00';
        $recentStart = date('Y-m-d', strtotime('-30 days')) . ' 00:00:00';

        $summaryRow = $this->baseScopeBuilder($actor)
            ->select("COUNT(DISTINCT audit_logs.id) AS total", false)
            ->select("COUNT(DISTINCT CASE WHEN audit_logs.created_at >= " . $this->db->escape($todayStart) . " THEN audit_logs.id END) AS today", false)
            ->select("COUNT(DISTINCT CASE WHEN audit_logs.created_at >= " . $this->db->escape($weekStart) . " THEN audit_logs.id END) AS this_week", false)
            ->select("COUNT(DISTINCT CASE WHEN audit_logs.created_at >= " . $this->db->escape($recentStart) . " AND audit_logs.level IN ('error', 'critical') THEN audit_logs.id END) AS critical", false)
            ->get()
            ->getFirstRow();

        $activeUsers = $this->baseScopeBuilder($actor)
            ->select('audit_logs.user_id, COUNT(DISTINCT audit_logs.id) AS count', false)
            ->where('audit_logs.created_at >=', $recentStart)
            ->where('audit_logs.user_id IS NOT NULL', null, false)
            ->groupBy('audit_logs.user_id')
            ->orderBy('count', 'DESC')
            ->limit(5)
            ->get()
            ->getResult();

        $userIds = array_values(array_unique(array_map(static fn(object $user): int => (int) $user->user_id, $activeUsers)));
        $userNames = $this->employeeModel->getNamesByIds($userIds);
        $userStats = array_map(static fn(object $user) => [
            'name' => $userNames[$user->user_id] ?? "ID: {$user->user_id}",
            'count' => $user->count,
        ], $activeUsers);

        $commonActions = $this->baseScopeBuilder($actor)
            ->select('audit_logs.action, COUNT(DISTINCT audit_logs.id) AS count', false)
            ->where('audit_logs.created_at >=', $recentStart)
            ->groupBy('audit_logs.action')
            ->orderBy('count', 'DESC')
            ->limit(5)
            ->get()
            ->getResult();

        return [
            'total' => (int) ($summaryRow->total ?? 0),
            'today' => (int) ($summaryRow->today ?? 0),
            'this_week' => (int) ($summaryRow->this_week ?? 0),
            'critical' => (int) ($summaryRow->critical ?? 0),
            'active_users' => $userStats,
            'common_actions' => $commonActions,
        ];
    }

    private function distinctActions(?array $actor = null): array
    {
        return $this->baseScopeBuilder($actor)
            ->select('audit_logs.action')
            ->distinct()
            ->orderBy('audit_logs.action')
            ->get()
            ->getResultArray();
    }

    private function distinctEntities(?array $actor = null): array
    {
        return $this->baseScopeBuilder($actor)
            ->select('COALESCE(audit_logs.entity_type, audit_logs.table_name) AS entity_type')
            ->distinct()
            ->orderBy('entity_type')
            ->get()
            ->getResultArray();
    }

    private function scopedUsers(?array $actor = null): array
    {
        $department = $this->limitedDepartment($actor);
        $builder = $this->employeeModel->builder();
        $builder->orderBy('name', 'ASC');

        if ($department !== null) {
            $builder->where('department', $department);
        }

        return $builder->get()->getResult();
    }

    private function baseScopeBuilder(?array $actor = null): BaseBuilder
    {
        $builder = $this->db->table($this->auditTable());
        $department = $this->limitedDepartment($actor);

        if ($department !== null) {
            $builder->join('employees audit_scope_employee', 'audit_scope_employee.id = audit_logs.user_id', 'left');
            $builder->join(
                'employees audit_scope_subject_employee',
                "audit_scope_subject_employee.id = COALESCE(audit_logs.entity_id, audit_logs.record_id) AND (COALESCE(audit_logs.entity_type, audit_logs.table_name) = 'employees')",
                'left'
            );
            $builder->groupStart()
                ->where('audit_scope_employee.department', $department)
                ->orWhere('audit_scope_subject_employee.department', $department)
                ->groupEnd();
        }

        return $builder;
    }

    private function countDistinctAuditIds(BaseBuilder $builder): int
    {
        $row = $builder
            ->select('COUNT(DISTINCT audit_logs.id) AS aggregate_count', false)
            ->get()
            ->getFirstRow();

        return (int) ($row->aggregate_count ?? 0);
    }

    private function limitedDepartment(?array $actor = null): ?string
    {
        if ($actor === null) {
            return null;
        }

        $role = $this->normalizeRole((string) ($actor['role'] ?? ''));
        if ($role !== Role::Gestor->value) {
            return null;
        }

        $department = trim((string) ($actor['department'] ?? ''));

        return $department !== '' ? $department : null;
    }

    private function normalizeRole(string $role): string
    {
        try {
            return Role::normalize($role)->value;
        } catch (\ValueError) {
            return Role::Funcionario->value;
        }
    }

    private function auditTable(): string
    {
        return $this->auditModel->getTable();
    }

    private function applyDatatableFilters(BaseBuilder $builder, array $filters, string $searchValue): void
    {
        if (!empty($filters['user_id'])) {
            $builder->where('audit_logs.user_id', $filters['user_id']);
        }

        if (!empty($filters['action'])) {
            $builder->where('audit_logs.action', $filters['action']);
        }

        if (!empty($filters['entity'])) {
            $builder->groupStart()
                ->where('audit_logs.entity_type', $filters['entity'])
                ->orWhere('audit_logs.table_name', $filters['entity'])
                ->groupEnd();
        }

        if (!empty($filters['level'])) {
            $builder->where('audit_logs.level', $filters['level']);
        }

        if (!empty($filters['start_date'])) {
            $builder->where('audit_logs.created_at >=', $filters['start_date'] . ' 00:00:00');
        }

        if (!empty($filters['end_date'])) {
            $builder->where('audit_logs.created_at <=', $filters['end_date'] . ' 23:59:59');
        }

        if ($searchValue !== '') {
            $builder->groupStart()
                ->like('audit_logs.action', $searchValue)
                ->orLike('audit_logs.description', $searchValue)
                ->orLike('audit_logs.ip_address', $searchValue)
                ->orLike('audit_logs.entity_type', $searchValue)
                ->orLike('audit_logs.table_name', $searchValue)
                ->groupEnd();
        }
    }

    private function decodeJsonField(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value) || is_object($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    private function formatAction(string $action): string
    {
        return trim((string) preg_replace('/\s+/', ' ', str_replace(['_', '-'], ' ', mb_strtolower($action))));
    }

    private function formatEntity(string $entityType, ?int $entityId): string
    {
        $label = $entityType !== '' ? $entityType : 'registro';

        return $entityId !== null ? sprintf('%s #%d', $label, $entityId) : $label;
    }

    private function formatLevel(string $level): string
    {
        return trim((string) preg_replace('/\s+/', ' ', mb_strtolower($level)));
    }
}
