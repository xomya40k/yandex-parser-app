<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

use App\Enums\OrganizationStatus;
use App\Models\Organization;

interface OrganizationRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Organization;

    public function findByYandexUrl(string $url): ?Organization;

    public function updateStatus(Organization $organization, OrganizationStatus $status): Organization;
}
