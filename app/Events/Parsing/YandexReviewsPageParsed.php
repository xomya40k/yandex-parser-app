<?php

declare(strict_types=1);

namespace App\Events\Parsing;

final readonly class YandexReviewsPageParsed
{
    public function __construct(
        public ?int $parseRunId,
        public int $page,
        public int $reviewsOnPage,
        public int $totalCollected,
        public int $totalReviews,
    ) {}
}
