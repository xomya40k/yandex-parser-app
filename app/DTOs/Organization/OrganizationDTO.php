<?php

declare(strict_types=1);

namespace App\DTOs\Organization;

use App\Models\Organization;

final readonly class OrganizationDTO
{
    public function __construct(public Organization $organization) {}
}
