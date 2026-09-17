<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Repositories\Interfaces\OrganizationRepositoryInterface;
use Illuminate\Support\Collection;

class OrganizationRepository implements OrganizationRepositoryInterface
{
    public function __construct(
        private readonly Organization $model,
    ) {}

    public function create(array $data): Organization
    {
        return $this->model->query()->create($data);
    }

    public function update(Organization $organization, array $data): Organization
    {
        $organization->update($data);

        return $organization->refresh();
    }

    public function findById(int $id): ?Organization
    {
        return $this->model->query()->find($id);
    }

    public function findByYandexUrl(string $url): ?Organization
    {
        return $this->model->query()
            ->where('yandex_maps_url', $url)
            ->first();
    }

    public function findDueForSync(int $reparseIntervalHours): Collection
    {
        $cutoff = now()->subHours($reparseIntervalHours);

        return $this->model->query()
            ->where(function ($query) use ($cutoff): void {
                $query->where('status', OrganizationStatus::Pending)
                    ->orWhere(function ($query) use ($cutoff): void {
                        $query->where('status', OrganizationStatus::Ready)
                            ->where(function ($query) use ($cutoff): void {
                                $query->whereNull('last_parsed_at')
                                    ->orWhere('last_parsed_at', '<=', $cutoff);
                            });
                    })
                    ->orWhere(function ($query) use ($cutoff): void {
                        $query->where('status', OrganizationStatus::Failed)
                            ->where('updated_at', '<=', $cutoff);
                    });
            })
            ->orderBy('id')
            ->get();
    }
}
