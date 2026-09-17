<?php

declare(strict_types=1);

namespace App\DTOs\Review;

use App\Models\Review;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class ReviewsPageDTO
{
    /**
     * @param  LengthAwarePaginator<int, Review>  $paginator
     */
    public function __construct(
        public LengthAwarePaginator $paginator,
    ) {}
}
