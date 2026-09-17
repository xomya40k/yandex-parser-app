<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

use App\Models\Organization;
use Illuminate\Support\Collection;

interface OrganizationRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Organization;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Organization $organization, array $data): Organization;

    public function findById(int $id): ?Organization;

    public function findByYandexUrl(string $url): ?Organization;

    /**
     * Organizations due for (re)parse: Pending always; Ready/Failed past the reparse interval.
     *
     * @return Collection<int, Organization>
     */
    public function findDueForSync(int $reparseIntervalHours): Collection;
}
