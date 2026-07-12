<?php

namespace App\Services\Shift;

class ScheduleCoordinatorService
{
    public function __construct(
        private readonly ScheduleQueryService $queryService = new ScheduleQueryService(),
        private readonly ScheduleWorkflowService $workflowService = new ScheduleWorkflowService(),
        private readonly ScheduleExportService $exportService = new ScheduleExportService(),
    ) {
    }

    public static function createDefault(): self
    {
        return new self();
    }

    public function calendarData(?string $year, ?string $month, ?string $view): array
    {
        return $this->queryService->calendarData($year, $month, $view);
    }

    public function createFormData(): array
    {
        return $this->queryService->createFormData();
    }

    public function editData(int $id): ?array
    {
        return $this->queryService->editData($id);
    }

    public function mySchedulesData(int $employeeId, ?string $startDate, ?string $endDate): array
    {
        return $this->queryService->mySchedulesData($employeeId, $startDate, $endDate);
    }

    public function create(array $payload, int $actorId): array
    {
        return $this->workflowService->create($payload, $actorId);
    }

    public function update(int $id, array $payload): array
    {
        return $this->workflowService->update($id, $payload);
    }

    public function delete(int $id): array
    {
        return $this->workflowService->delete($id);
    }

    public function bulkAssign(array $payload): array
    {
        return $this->workflowService->bulkAssign($payload);
    }

    public function csvData(?string $startDate, ?string $endDate): array
    {
        return $this->exportService->csvData($startDate, $endDate);
    }

    public function translateStatus(string $status): string
    {
        return $this->exportService->translateStatus($status);
    }
}
