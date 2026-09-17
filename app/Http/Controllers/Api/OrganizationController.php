<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\DTOs\Organization\StoreOrganizationDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\StoreOrganizationRequest;
use App\Http\Resources\OrganizationResource;
use App\Models\User;
use App\Services\Organization\OrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function __construct(
        private readonly OrganizationService $organizationService,
    ) {}

    public function show(Request $request): OrganizationResource
    {
        /** @var User $user */
        $user = $request->user();
        $organization = $user->currentOrganization;

        if (is_null($organization)) {
            abort(404);
        }

        return new OrganizationResource($organization);
    }

    public function store(StoreOrganizationRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $dto = new StoreOrganizationDTO(
            user: $user,
            yandexMapsUrl: (string) $request->validated('yandex_maps_url'),
        );

        $organization = $this->organizationService->store($dto)->organization;

        return new OrganizationResource($organization)
            ->response()
            ->setStatusCode($organization->wasRecentlyCreated ? 201 : 200);
    }
}
