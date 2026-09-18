<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\DTOs\Parsing\ParseRunTargetDTO;
use App\Http\Controllers\Controller;
use App\Http\Resources\ParseRunResource;
use App\Models\User;
use App\Services\Parsing\ParseRunService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParseRunController extends Controller
{
    public function __construct(
        private readonly ParseRunService $parseRunService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $organization = $user->currentOrganization;

        if (is_null($organization)) {
            abort(404);
        }

        $dto = $this->parseRunService->queue(new ParseRunTargetDTO($organization->id));

        return (new ParseRunResource($dto->parseRun))
            ->response()
            ->setStatusCode(202);
    }

    public function show(Request $request): ParseRunResource
    {
        /** @var User $user */
        $user = $request->user();
        $organization = $user->currentOrganization;

        if (is_null($organization)) {
            abort(404);
        }

        $dto = $this->parseRunService->latest(new ParseRunTargetDTO($organization->id));

        return new ParseRunResource($dto?->parseRun);
    }
}
