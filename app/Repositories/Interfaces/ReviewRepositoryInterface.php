<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

use App\Models\Review;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ReviewRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function updateOrCreate(array $data): Review;

    /**
     * @return LengthAwarePaginator<int, Review>
     */
    public function paginateByOrganization(int $organizationId, int $perPage, int $page): LengthAwarePaginator;

    public function getCountByOrganization(int $organizationId): int;
}
