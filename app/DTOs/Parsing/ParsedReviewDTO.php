<?php

declare(strict_types=1);

namespace App\DTOs\Parsing;

use DateTimeInterface;

final readonly class ParsedReviewDTO
{
    public function __construct(
        public string $externalId,
        public string $authorName,
        public int $rating,
        public ?string $text,
        public DateTimeInterface $reviewDate,
    ) {}
}
