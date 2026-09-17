<?php

declare(strict_types=1);

namespace App\DTOs\Review;

final readonly class PaginateReviewsDTO
{
    public function __construct(
        public int $organizationId,
        public int $page,
        public int $perPage,
    ) {}
}
