<?php

declare(strict_types=1);

namespace App\DTOs\Parsing;

final readonly class FailParseRunDTO
{
    public function __construct(
        public int $parseRunId,
        public string $errorCode,
        public ?string $errorMessage,
        public bool $terminal,
    ) {}
}
