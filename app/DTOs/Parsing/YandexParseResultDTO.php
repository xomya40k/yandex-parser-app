<?php

declare(strict_types=1);

namespace App\DTOs\Parsing;

use DateTimeInterface;

final readonly class YandexParseResultDTO
{
    /**
     * @param  list<ParsedReviewDTO>  $reviews
     */
    public function __construct(
        public ?string $name,
        public ?string $rating,
        public int $totalRatings,
        public int $totalReviews,
        public array $reviews,
        public DateTimeInterface $capturedAt,
    ) {}
}
