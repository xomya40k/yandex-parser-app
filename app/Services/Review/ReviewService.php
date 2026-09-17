<?php

declare(strict_types=1);

namespace App\Services\Review;

use App\DTOs\Review\InvalidateReviewsCacheDTO;
use App\DTOs\Review\PaginateReviewsDTO;
use App\DTOs\Review\ReviewsPageDTO;
use App\Repositories\Interfaces\ReviewRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class ReviewService
{
    private const CACHE_VERSION_KEY = 'reviews:org:%d:version';

    private const CACHE_PAGE_KEY = 'reviews:org:%d:v%d:page:%d:per:%d';

    public function __construct(
        private readonly ReviewRepositoryInterface $reviewRepository,
    ) {}

    public function paginate(PaginateReviewsDTO $dto): ReviewsPageDTO
    {
        $version = $this->cacheVersion($dto->organizationId);
        $key = sprintf(
            self::CACHE_PAGE_KEY,
            $dto->organizationId,
            $version,
            $dto->page,
            $dto->perPage,
        );

        $ttl = (int) config('yandex.reviews_cache_ttl', 3600);

        $paginator = Cache::remember(
            $key,
            $ttl,
            fn () => $this->reviewRepository->paginateByOrganization(
                $dto->organizationId,
                $dto->perPage,
                $dto->page,
            ),
        );

        return new ReviewsPageDTO($paginator);
    }

    /**
     * Bump the per-organization reviews cache version so cached pages miss.
     */
    public function invalidateCache(InvalidateReviewsCacheDTO $dto): void
    {
        $key = sprintf(self::CACHE_VERSION_KEY, $dto->organizationId);
        $current = (int) Cache::get($key, 0);

        Cache::forever($key, $current + 1);
    }

    private function cacheVersion(int $organizationId): int
    {
        return (int) Cache::get(
            sprintf(self::CACHE_VERSION_KEY, $organizationId),
            0,
        );
    }
}
