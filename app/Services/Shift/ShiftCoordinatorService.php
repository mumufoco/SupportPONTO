<?php

namespace App\Services\Shift;

class ShiftCoordinatorService
{
    public function __construct(
        private readonly ShiftQueryService $queryService = new ShiftQueryService(),
        private readonly ShiftWorkflowService $workflowService = new ShiftWorkflowService(),
    ) {
    }

    public static function createDefault(): self
    {
        return new self();
    }

    public function indexData(array $filters): array
    {
        return $this->queryService->indexData($filters);
    }

    public function showData(int $id): ?array
    {
        return $this->queryService->showData($id);
    }

    public function createFormData(): array
    {
        return $this->queryService->createFormData();
    }

    public function editData(int $id): ?array
    {
        return $this->queryService->editData($id);
    }

    public function statisticsData(): array
    {
        return $this->queryService->statisticsData();
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

    public function clone(int $id): array
    {
        return $this->workflowService->clone($id);
    }

    public function toggleActive(int $id): array
    {
        return $this->workflowService->toggleActive($id);
    }
}
