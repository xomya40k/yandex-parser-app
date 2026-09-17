<?php

declare(strict_types=1);

namespace App\DTOs\Organization;

use App\Models\User;

final readonly class StoreOrganizationDTO
{
    public function __construct(
        public User $user,
        public string $yandexMapsUrl,
    ) {}
}
