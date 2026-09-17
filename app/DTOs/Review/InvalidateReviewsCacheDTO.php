<?php

declare(strict_types=1);

namespace App\DTOs\Review;

final readonly class InvalidateReviewsCacheDTO
{
    public function __construct(
        public int $organizationId,
    ) {}
}
