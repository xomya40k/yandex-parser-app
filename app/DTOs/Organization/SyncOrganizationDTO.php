<?php

declare(strict_types=1);

namespace App\DTOs\Organization;

final readonly class SyncOrganizationDTO
{
    public function __construct(
        public int $organizationId,
    ) {}
}
