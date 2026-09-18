<?php

declare(strict_types=1);

namespace App\DTOs\Parsing;

final readonly class ParseRunTargetDTO
{
    public function __construct(
        public int $organizationId,
        public int $delaySeconds = 0,
    ) {}
}
