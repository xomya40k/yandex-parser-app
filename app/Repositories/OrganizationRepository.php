<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Organization;
use App\Repositories\Interfaces\OrganizationRepositoryInterface;

class OrganizationRepository implements OrganizationRepositoryInterface
{
    public function __construct(
        private readonly Organization $model,
    ) {}

    public function create(array $data): Organization
    {
        return $this->model->query()->create($data);
    }

    public function findByYandexUrl(string $url): ?Organization
    {
        return $this->model->query()
            ->where('yandex_maps_url', $url)
            ->first();
    }
}
