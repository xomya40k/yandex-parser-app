<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

use App\Models\OrganizationSnapshot;

interface OrganizationSnapshotRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): OrganizationSnapshot;

    public function findLatestForOrganization(int $organizationId): ?OrganizationSnapshot;
}
