<?php

declare(strict_types=1);

namespace App\DTOs\Parsing;

final readonly class ReapedParseRunsDTO
{
    public function __construct(
        public int $count,
    ) {}
}
