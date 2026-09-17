<?php

declare(strict_types=1);

namespace App\Services\Review;

use App\DTOs\Review\InvalidateReviewsCacheDTO;
use Illuminate\Support\Facades\Cache;

class ReviewService
{
    private const CACHE_VERSION_KEY = 'reviews:org:%d:version';

    /**
     * Bump the per-organization reviews cache version so cached pages miss.
     */
    public function invalidateCache(InvalidateReviewsCacheDTO $dto): void
    {
        $key = sprintf(self::CACHE_VERSION_KEY, $dto->organizationId);
        $current = (int) Cache::get($key, 0);

        Cache::forever($key, $current + 1);
    }
}
