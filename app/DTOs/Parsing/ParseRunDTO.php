<?php

declare(strict_types=1);

namespace App\DTOs\Parsing;

use App\Models\ParseRun;

final readonly class ParseRunDTO
{
    public function __construct(
        public ParseRun $parseRun,
    ) {}
}
