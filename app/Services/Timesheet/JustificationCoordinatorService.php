<?php

namespace App\Services\Timesheet;

class JustificationCoordinatorService
{
    public function __construct(
        private readonly JustificationQueryService $queryService = new JustificationQueryService(),
        private readonly JustificationWorkflowService $workflowService = new JustificationWorkflowService(),
    ) {
    }

    public static function createDefault(): self
    {
        return new self();
    }

    public function authenticatedEmployee(): ?array
    {
        return $this->queryService->authenticatedEmployee();
    }

    public function indexData(array $actor, array $filters, int $perPage = 20): array
    {
        return $this->queryService->indexData($actor, $filters, $perPage);
    }

    public function showData(int $id, array $actor): ?array
    {
        return $this->queryService->showData($id, $actor);
    }

    public function canReview(array $actor, int $justificationId): bool
    {
        return $this->queryService->canReview($actor, $justificationId);
    }

    public function create(array $actor, array $payload, array $attachmentFiles): array
    {
        return $this->workflowService->create($actor, $payload, $attachmentFiles);
    }

    public function approve(int $id, array $actor, ?string $notes): array
    {
        return $this->workflowService->approve($id, $actor, $notes);
    }

    public function reject(int $id, array $actor, ?string $notes): array
    {
        return $this->workflowService->reject($id, $actor, $notes);
    }

    public function delete(int $id, array $actor): array
    {
        return $this->workflowService->delete($id, $actor);
    }
}
