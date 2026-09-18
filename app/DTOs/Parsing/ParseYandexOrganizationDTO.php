<?php

declare(strict_types=1);

namespace App\DTOs\Parsing;

final readonly class ParseYandexOrganizationDTO
{
    public function __construct(
        public string $yandexMapsUrl,
        public ?int $parseRunId = null,
    ) {}
}
