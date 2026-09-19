<?php

declare(strict_types=1);

namespace App\Services\Review;

use App\DTOs\Review\InvalidateReviewsCacheDTO;
use App\DTOs\Review\PaginateReviewsDTO;
use App\DTOs\Review\ReviewsPageDTO;
use App\Models\Review;
use App\Repositories\Interfaces\ReviewRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class ReviewService
{
    private const CACHE_VERSION_KEY = 'reviews:org:%d:version';

    private const CACHE_PAGE_KEY = 'reviews:org:%d:v%d:page:%d:per:%d:payload';

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

        /** @var array{items: list<array<string, mixed>>, total: int, per_page: int, current_page: int, path: string} $payload */
        $payload = Cache::remember(
            $key,
            $ttl,
            function () use ($dto): array {
                $paginator = $this->reviewRepository->paginateByOrganization(
                    $dto->organizationId,
                    $dto->perPage,
                    $dto->page,
                );

                return [
                    'items' => $paginator->getCollection()
                        ->map(static fn (Review $review): array => $review->getAttributes())
                        ->values()
                        ->all(),
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'path' => $paginator->path(),
                ];
            },
        );

        return new ReviewsPageDTO($this->paginatorFromPayload($payload));
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

    /**
     * @param  array{items: list<array<string, mixed>>, total: int, per_page: int, current_page: int, path: string}  $payload
     * @return LengthAwarePaginator<int, Review>
     */
    private function paginatorFromPayload(array $payload): LengthAwarePaginator
    {
        $items = Review::hydrate($payload['items']);

        return (new LengthAwarePaginator(
            $items,
            $payload['total'],
            $payload['per_page'],
            $payload['current_page'],
            [
                'path' => $payload['path'],
                'pageName' => 'page',
            ],
        ))->withQueryString();
    }
}
