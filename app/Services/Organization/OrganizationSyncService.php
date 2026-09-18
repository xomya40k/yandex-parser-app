<?php

declare(strict_types=1);

namespace App\Services\Organization;

use App\DTOs\Organization\OrganizationDTO;
use App\DTOs\Organization\SyncOrganizationDTO;
use App\DTOs\Parsing\FailParseRunDTO;
use App\DTOs\Parsing\ParseRunAttemptDTO;
use App\DTOs\Parsing\ParseRunProgressDTO;
use App\DTOs\Parsing\ParseRunTargetDTO;
use App\DTOs\Parsing\ParseYandexOrganizationDTO;
use App\DTOs\Parsing\ParsedReviewDTO;
use App\DTOs\Parsing\YandexParseResultDTO;
use App\DTOs\Review\InvalidateReviewsCacheDTO;
use App\Enums\OrganizationStatus;
use App\Exceptions\Parsing\YandexParsingException;
use App\Models\Organization;
use App\Models\OrganizationSnapshot;
use App\Repositories\Interfaces\OrganizationRepositoryInterface;
use App\Repositories\Interfaces\OrganizationSnapshotRepositoryInterface;
use App\Repositories\Interfaces\ReviewRepositoryInterface;
use App\Services\Parsers\Contracts\YandexParserInterface;
use App\Services\Parsing\ParseRunService;
use App\Services\Review\ReviewService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrganizationSyncService
{
    public function __construct(
        private readonly OrganizationRepositoryInterface $organizationRepository,
        private readonly OrganizationSnapshotRepositoryInterface $snapshotRepository,
        private readonly ReviewRepositoryInterface $reviewRepository,
        private readonly YandexParserInterface $parser,
        private readonly ReviewService $reviewService,
        private readonly ParseRunService $parseRunService,
    ) {}

    public function sync(SyncOrganizationDTO $dto): OrganizationDTO
    {
        $organization = $this->organizationRepository->findById($dto->organizationId);

        if (is_null($organization)) {
            throw (new ModelNotFoundException)->setModel(Organization::class, [$dto->organizationId]);
        }

        $parseRunId = $dto->parseRunId
            ?? $this->parseRunService->ensure(new ParseRunTargetDTO($organization->id))->parseRun->id;

        $this->parseRunService->markProcessing(new ParseRunAttemptDTO(
            parseRunId: $parseRunId,
            attempt: $dto->attempt,
        ));

        $organization = $this->organizationRepository->update($organization, [
            'status' => OrganizationStatus::Parsing,
        ]);

        try {
            $result = $this->parser->parse(
                new ParseYandexOrganizationDTO($organization->yandex_maps_url, $parseRunId),
            );

            $organization = DB::transaction(function () use ($organization, $result): Organization {
                foreach ($result->reviews as $review) {
                    $this->persistReview($organization->id, $review);
                }

                $previous = $this->snapshotRepository->findLatestForOrganization($organization->id);

                $this->snapshotRepository->create([
                    'organization_id' => $organization->id,
                    'rating' => $result->rating,
                    'total_ratings' => $result->totalRatings,
                    'total_reviews' => $result->totalReviews,
                    'reviews_count' => count($result->reviews),
                    'payload' => $this->buildSnapshotPayload($previous, $result),
                    'captured_at' => $result->capturedAt,
                ]);

                return $this->organizationRepository->update($organization, [
                    'name' => $result->name,
                    'rating' => $result->rating,
                    'total_ratings' => $result->totalRatings,
                    'total_reviews' => $result->totalReviews,
                    'status' => OrganizationStatus::Ready,
                    'last_parsed_at' => now(),
                ]);
            });

            $this->reviewService->invalidateCache(
                new InvalidateReviewsCacheDTO($organization->id),
            );

            $progress = $this->parseRunService->latest(new ParseRunTargetDTO($organization->id));

            $this->parseRunService->markCompleted(new ParseRunProgressDTO(
                parseRunId: $parseRunId,
                processedReviews: count($result->reviews),
                processedPages: $progress?->parseRun->processed_pages ?? 0,
                totalReviews: $result->totalReviews,
            ));

            return new OrganizationDTO($organization);
        } catch (YandexParsingException $exception) {
            $this->parseRunService->recordFailure(new FailParseRunDTO(
                parseRunId: $parseRunId,
                errorCode: $exception->errorCode(),
                errorMessage: $exception->getMessage(),
                terminal: false,
            ));

            Log::error('Yandex organization sync failed', [
                'organization_id' => $organization->id,
                'parse_run_id' => $parseRunId,
                'attempt' => $dto->attempt,
                'yandex_maps_url' => $organization->yandex_maps_url,
                'error_code' => $exception->errorCode(),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function persistReview(int $organizationId, ParsedReviewDTO $review): void
    {
        $this->reviewRepository->updateOrCreate([
            'organization_id' => $organizationId,
            'external_id' => $review->externalId,
            'author_name' => $review->authorName,
            'rating' => $review->rating,
            'text' => $review->text,
            'review_date' => $review->reviewDate,
        ]);
    }

    /**
     * @return array{before: array<string, mixed>|null, after: array<string, mixed>}
     */
    private function buildSnapshotPayload(?OrganizationSnapshot $previous, YandexParseResultDTO $result): array
    {
        return [
            'before' => is_null($previous) ? null : [
                'rating' => $previous->rating,
                'total_ratings' => $previous->total_ratings,
                'total_reviews' => $previous->total_reviews,
                'reviews_count' => $previous->reviews_count,
            ],
            'after' => [
                'rating' => $result->rating,
                'total_ratings' => $result->totalRatings,
                'total_reviews' => $result->totalReviews,
                'reviews_count' => count($result->reviews),
            ],
        ];
    }
}
