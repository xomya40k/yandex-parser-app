<?php

declare(strict_types=1);

namespace App\DTOs\Parsing;

final readonly class ParseRunProgressDTO
{
    public function __construct(
        public int $parseRunId,
        public int $processedReviews,
        public int $processedPages,
        public ?int $totalReviews,
    ) {}
}
