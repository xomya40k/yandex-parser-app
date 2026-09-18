<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\ParseRunStatus;
use App\Models\ParseRun;
use App\Repositories\Interfaces\ParseRunRepositoryInterface;
use Carbon\CarbonInterface;

class ParseRunRepository implements ParseRunRepositoryInterface
{
    public function __construct(
        private readonly ParseRun $model,
    ) {}

    public function create(array $data): ParseRun
    {
        return $this->model->query()->create($data);
    }

    public function updateById(int $id, array $data): ?ParseRun
    {
        $parseRun = $this->findById($id);

        if ($parseRun === null) {
            return null;
        }

        $parseRun->update($data);

        return $parseRun->refresh();
    }

    public function findById(int $id): ?ParseRun
    {
        return $this->model->query()->find($id);
    }

    public function findLatestForOrganization(int $organizationId): ?ParseRun
    {
        return $this->model->query()
            ->where('organization_id', $organizationId)
            ->orderByDesc('id')
            ->first();
    }

    public function findActiveForOrganization(int $organizationId): ?ParseRun
    {
        return $this->model->query()
            ->where('organization_id', $organizationId)
            ->whereIn('status', [
                ParseRunStatus::Pending,
                ParseRunStatus::Processing,
            ])
            ->orderByDesc('id')
            ->first();
    }

    public function markStaleAsFailed(CarbonInterface $cutoff, string $errorCode): int
    {
        return $this->model->query()
            ->where('status', ParseRunStatus::Processing)
            ->where('started_at', '<=', $cutoff)
            ->update([
                'status' => ParseRunStatus::Failed,
                'error_code' => $errorCode,
                'error_message' => 'Parse run stuck in processing past the stale cutoff.',
                'finished_at' => now(),
            ]);
    }
}
