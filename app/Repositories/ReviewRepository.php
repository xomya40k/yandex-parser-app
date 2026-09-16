<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Review;
use App\Repositories\Interfaces\ReviewRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ReviewRepository implements ReviewRepositoryInterface
{
    public function __construct(
        private readonly Review $model,
    ) {}

    public function updateOrCreate(array $data): Review
    {
        return $this->model->query()->updateOrCreate(
            [
                'organization_id' => $data['organization_id'],
                'external_id' => $data['external_id'],
            ],
            [
                'author_name' => $data['author_name'],
                'rating' => $data['rating'],
                'text' => $data['text'],
                'review_date' => $data['review_date'],
            ],
        );
    }

    public function paginateByOrganization(int $organizationId, int $perPage, int $page): LengthAwarePaginator
    {
        return $this->model->query()
            ->where('organization_id', $organizationId)
            ->orderByDesc('review_date')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function getCountByOrganization(int $organizationId): int
    {
        return $this->model->query()
            ->where('organization_id', $organizationId)
            ->count();
    }
}
