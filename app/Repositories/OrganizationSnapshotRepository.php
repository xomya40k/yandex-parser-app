<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\OrganizationSnapshot;
use App\Repositories\Interfaces\OrganizationSnapshotRepositoryInterface;

class OrganizationSnapshotRepository implements OrganizationSnapshotRepositoryInterface
{
    public function __construct(
        private readonly OrganizationSnapshot $model,
    ) {}

    public function create(array $data): OrganizationSnapshot
    {
        return $this->model->query()->create($data);
    }

    public function findLatestForOrganization(int $organizationId): ?OrganizationSnapshot
    {
        return $this->model->query()
            ->where('organization_id', $organizationId)
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->first();
    }
}
