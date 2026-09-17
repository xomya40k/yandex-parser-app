<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Organization;
use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;

class UserRepository implements UserRepositoryInterface
{
    public function __construct(
        private readonly User $model,
    ) {}

    public function findById(int $id): ?User
    {
        return $this->model->query()->find($id);
    }

    public function updateCurrentOrganization(User $user, Organization $organization): void
    {
        $user->currentOrganization()->associate($organization);
        $user->save();
    }
}
