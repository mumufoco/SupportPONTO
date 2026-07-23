<?php

namespace App\Services\Warning;

use App\Models\EmployeeModel;
use App\Models\WarningModel;
use App\Models\WarningWitnessModel;
use App\Services\Warning\WarningPdfStorageService;

class WarningQueryService
{
    public function __construct(
        private readonly WarningModel $warningModel,
        private readonly EmployeeModel $employeeModel,
        private readonly WarningAccessService $accessService,
        private readonly WarningPdfStorageService $pdfStorageService,
        private readonly ?WarningWitnessModel $warningWitnessModel = null
    ) {
    }

    public function listWarnings(array $actor, string $warningType = 'all', string $status = 'all', int $perPage = 20): array
    {
        if (!$this->warningModel->tableExists()) {
            return [
                'warnings' => [],
                'pager' => null,
                'counts' => $this->emptyCounts(),
                'warning' => 'Tabela de advertências não está disponível. Execute as migrações do banco de dados.',
            ];
        }

        $managedIds = $this->accessService->managedEmployeeIds($actor);

        // JOIN employee and issuer in a single query — eliminates N+1 (was 2 queries per warning)
        $query = $this->warningModel
            ->select("warnings.*, COALESCE(emp.name, 'Desconhecido') AS employee_name, COALESCE(iss.name, 'Desconhecido') AS issuer_name", false)
            ->join('employees AS emp', 'emp.id = warnings.employee_id', 'left')
            ->join('employees AS iss', 'iss.id = warnings.issued_by', 'left');

        if ($managedIds !== null) {
            // $managedIds pode vir vazio (gestor sem colaboradores ativos no departamento) -
            // whereIn() com array vazio gera "IN ()", erro de sintaxe no Postgres. [0] garante
            // que a condicao simplesmente nao bate com nenhum registro, sem quebrar a query.
            $query->whereIn('warnings.employee_id', $managedIds ?: [0]);
        }

        if ($warningType !== 'all') {
            $query->where('warnings.warning_type', $warningType);
        }

        if ($status !== 'all') {
            $query->where('warnings.status', $status);
        }

        try {
            $warnings = $query
                ->orderBy($this->warningModel->getSafeOrderByColumn(), 'DESC')
                ->paginate($perPage);
        } catch (\Throwable $e) {
            log_message('error', 'Erro listando advertências: ' . $e->getMessage());
            $warnings = [];
        }

        // employee_name and issuer_name now come from the JOIN above; no per-row queries needed

        return [
            'warnings' => $warnings,
            'pager' => $this->warningModel->pager,
            'counts' => $this->countWarnings($managedIds),
        ];
    }

    public function createFormData(array $actor): array
    {
        // Administradores do sistema não são colaboradores — não podem ser alvo
        // de advertência disciplinar (CLT), então ficam fora do seletor.
        $query = $this->employeeModel->where('active IS TRUE', null, false)->where('role !=', 'admin');

        if ($actor['role'] === 'gestor') {
            $query->where('department_id', ! empty($actor['department_id']) ? (int) $actor['department_id'] : 0);
        }

        return [
            'employees' => $query->orderBy('name', 'ASC')->findAll(),
        ];
    }

    public function warningDetails(int $warningId): ?array
    {
        $warning = $this->warningModel->find($warningId);
        if (!$warning) {
            return null;
        }

        $hoursElapsed = 0;
        if ($warning->status === 'pendente-assinatura') {
            $hoursElapsed = (time() - strtotime((string) $warning->created_at)) / 3600;
        }

        // Decode evidence_files (stored as JSON in PostgreSQL)
        $rawFiles = $warning->evidence_files ?? null;
        if (is_string($rawFiles)) {
            $rawFiles = json_decode($rawFiles, true) ?? [];
        }
        $rawFiles = is_array($rawFiles) ? $rawFiles : [];

        // Evidencias ficam em WRITEPATH/uploads (fora do webroot), nao em public/uploads -
        // base_url() apontava para um caminho publico que nunca existiu. O link correto
        // passa pela rota de download controlada (WarningController::downloadEvidence()).
        $attachments = [];
        foreach ($rawFiles as $index => $file) {
            $file = trim((string) $file);
            if ($file === '') {
                continue;
            }
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $attachments[] = [
                'url'  => route_to('warnings.evidence.download', $warningId, (int) $index),
                'name' => basename($file),
                'ext'  => $ext,
                'type' => in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) ? 'image' : 'file',
            ];
        }

        $warningEmployee = $this->employeeModel->find($warning->employee_id);

        // canAddWitness so libera enquanto ainda pendente ha 48h+ sem resposta - a propria
        // acao de adicionar testemunha JA marca a advertencia como recusada (ver
        // WarningWorkflowService::refuseWithWitness()), entao uma vez 'recusado' as
        // testemunhas ja foram registradas e nao ha nada a adicionar de novo.
        $witnessModel = $this->warningWitnessModel ?? new WarningWitnessModel();

        return [
            'warning'         => $warning,
            'warningEmployee' => $warningEmployee,
            'issuer'          => $this->employeeModel->find($warning->issued_by),
            'hoursElapsed'    => $hoursElapsed,
            'canAddWitness'   => $warning->status === 'pendente-assinatura' && $hoursElapsed >= 48,
            'attachments'     => $attachments,
            'witnesses'       => $warning->status === 'recusado' ? $witnessModel->forWarning($warningId) : [],
            'signerName'      => $warningEmployee ? ($warningEmployee->name ?? '') : '',
        ];
    }

    public function dashboardData(int $employeeId): ?array
    {
        $targetEmployee = $this->employeeModel->find($employeeId);
        if (!$targetEmployee) {
            return null;
        }

        // Batch-fetch all warning counts in one GROUP BY query —
        // eliminates 4 redundant countAllResults() calls (getCountByType×3 + getTotalWarnings×2)
        $stats = $this->warningModel->getStatsByEmployee($employeeId);

        return [
            'targetEmployee'  => $targetEmployee,
            'timeline'        => $this->warningModel->getTimeline($employeeId),
            'totalWarnings'   => $stats['total'],
            'warningsByType'  => [
                'verbal'    => $stats['verbal'],
                'escrita'   => $stats['escrita'],
                'suspensao' => $stats['suspensao'],
            ],
            'atLimit' => $stats['total'] >= 3,
        ];
    }

    public function downloadData(int $warningId): ?array
    {
        $warning = $this->warningModel->find($warningId);
        if (!$warning || !$warning->pdf_path) {
            return null;
        }

        $filepath = $this->pdfStorageService->resolveAbsolutePath((string) $warning->pdf_path);
        if ($filepath === null) {
            return [
                'warning' => $warning,
                'filepath' => null,
            ];
        }

        return [
            'warning' => $warning,
            'filepath' => $filepath,
        ];
    }

    private function countWarnings(?array $managedIds): array
    {
        // Avoid (clone $builder) — CI4 QueryBuilder does not support reliable deep-cloning.
        // Use a helper closure to create a fresh scoped builder each time.
        $scoped = function () use ($managedIds) {
            $b = $this->warningModel->builder();
            if ($managedIds !== null) {
                $b->whereIn('employee_id', $managedIds ?: [0]);
            }
            return $b;
        };

        return [
            'all'      => $scoped()->countAllResults(false),
            'verbal'   => $scoped()->where('warning_type', 'verbal')->countAllResults(false),
            'escrita'  => $scoped()->where('warning_type', 'escrita')->countAllResults(false),
            'suspensao'=> $scoped()->where('warning_type', 'suspensao')->countAllResults(false),
            'pendente' => $scoped()->where('status', 'pendente-assinatura')->countAllResults(false),
            'assinado' => $scoped()->where('status', 'assinado')->countAllResults(false),
            'recusado' => $scoped()->where('status', 'recusado')->countAllResults(false),
        ];
    }

    private function emptyCounts(): array
    {
        return [
            'all' => 0,
            'verbal' => 0,
            'escrita' => 0,
            'suspensao' => 0,
            'pendente' => 0,
            'assinado' => 0,
            'recusado' => 0,
        ];
    }
}
