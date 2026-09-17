<?php

declare(strict_types=1);

namespace App\ValueObjects\Yandex;

/**
 * Normalized payload extracted from one Yandex Maps reviews page.
 *
 * Reviews remain raw source arrays until the parser maps them into ParsedReviewDTO.
 */
final readonly class YandexReviewsPageState
{
    /**
     * @param  list<array<string, mixed>>  $reviews
     */
    public function __construct(
        public ?string $name,
        public ?string $rating,
        public int $totalRatings,
        public int $totalReviews,
        public array $reviews,
    ) {}
}
