<?php

declare(strict_types=1);

namespace App\DTOs\Parsing;

final readonly class ParseRunAttemptDTO
{
    public function __construct(
        public int $parseRunId,
        public int $attempt,
    ) {}
}
