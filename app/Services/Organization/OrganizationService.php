<?php

declare(strict_types=1);

namespace App\Services\Organization;

use App\DTOs\Organization\OrganizationDTO;
use App\DTOs\Organization\StoreOrganizationDTO;
use App\DTOs\Parsing\ParseRunTargetDTO;
use App\Enums\OrganizationStatus;
use App\Repositories\Interfaces\OrganizationRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Services\Parsing\ParseRunService;

class OrganizationService
{
    public function __construct(
        private readonly OrganizationRepositoryInterface $organizationRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly ParseRunService $parseRunService,
    ) {}

    public function store(StoreOrganizationDTO $dto): OrganizationDTO
    {
        $organization = $this->organizationRepository->findByYandexUrl($dto->yandexMapsUrl);

        if (is_null($organization)) {
            $organization = $this->organizationRepository->create([
                'yandex_maps_url' => $dto->yandexMapsUrl,
                'status' => OrganizationStatus::Pending,
            ]);
        }

        $this->userRepository->updateCurrentOrganization($dto->user, $organization);
        $this->parseRunService->queue(new ParseRunTargetDTO($organization->id));

        return new OrganizationDTO($organization);
    }
}
