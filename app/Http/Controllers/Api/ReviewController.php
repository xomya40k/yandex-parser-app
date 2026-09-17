<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\DTOs\Review\PaginateReviewsDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Review\ListReviewsRequest;
use App\Http\Resources\ReviewResource;
use App\Models\User;
use App\Services\Review\ReviewService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReviewController extends Controller
{
    public function __construct(
        private readonly ReviewService $reviewService,
    ) {}

    public function index(ListReviewsRequest $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();
        $organization = $user->currentOrganization;

        if (is_null($organization)) {
            abort(404);
        }

        $page = $this->reviewService->paginate(new PaginateReviewsDTO(
            organizationId: $organization->id,
            page: (int) $request->validated('page'),
            perPage: (int) $request->validated('per_page'),
        ));

        return ReviewResource::collection($page->paginator);
    }
}
