<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

use App\Models\Organization;
use App\Models\User;

interface UserRepositoryInterface
{
    public function findById(int $id): ?User;

    public function updateCurrentOrganization(User $user, Organization $organizationId): void;
}
