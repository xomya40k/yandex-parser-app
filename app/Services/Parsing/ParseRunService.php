<?php

declare(strict_types=1);

namespace App\Services\Parsing;

use App\DTOs\Parsing\FailParseRunDTO;
use App\DTOs\Parsing\ParseRunAttemptDTO;
use App\DTOs\Parsing\ParseRunDTO;
use App\DTOs\Parsing\ParseRunProgressDTO;
use App\DTOs\Parsing\ParseRunTargetDTO;
use App\DTOs\Parsing\ReapedParseRunsDTO;
use App\Enums\OrganizationStatus;
use App\Enums\ParseRunStatus;
use App\Jobs\ParseOrganizationJob;
use App\Repositories\Interfaces\OrganizationRepositoryInterface;
use App\Repositories\Interfaces\ParseRunRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class ParseRunService
{
    public function __construct(
        private readonly ParseRunRepositoryInterface $parseRunRepository,
        private readonly OrganizationRepositoryInterface $organizationRepository,
    ) {}

    public function queue(ParseRunTargetDTO $dto): ParseRunDTO
    {
        $lock = Cache::lock("parse-run-queue:{$dto->organizationId}", 10);

        return $lock->block(5, function () use ($dto): ParseRunDTO {
            $active = $this->parseRunRepository->findActiveForOrganization($dto->organizationId);

            if ($active !== null) {
                return new ParseRunDTO($active);
            }

            $run = $this->parseRunRepository->create([
                'organization_id' => $dto->organizationId,
                'status' => ParseRunStatus::Pending,
                'queued_at' => now(),
                'max_attempts' => (int) config('yandex.queue.tries', 3),
            ]);

            ParseOrganizationJob::dispatch($dto->organizationId, $run->id);

            return new ParseRunDTO($run);
        });
    }

    public function latest(ParseRunTargetDTO $dto): ?ParseRunDTO
    {
        $parseRun = $this->parseRunRepository->findLatestForOrganization($dto->organizationId);

        return $parseRun === null ? null : new ParseRunDTO($parseRun);
    }

    /**
     * Ensure an active parse run exists without dispatching a job.
     * Used when sync() is invoked outside the queue (legacy / direct calls).
     */
    public function ensure(ParseRunTargetDTO $dto): ParseRunDTO
    {
        $active = $this->parseRunRepository->findActiveForOrganization($dto->organizationId);

        if (!is_null($active)) {
            return new ParseRunDTO($active);
        }

        $run = $this->parseRunRepository->create([
            'organization_id' => $dto->organizationId,
            'status' => ParseRunStatus::Pending,
            'queued_at' => now(),
            'max_attempts' => (int) config('yandex.queue.tries', 3),
        ]);

        return new ParseRunDTO($run);
    }

    public function markProcessing(ParseRunAttemptDTO $dto): void
    {
        $data = [
            'status' => ParseRunStatus::Processing,
            'attempt' => $dto->attempt,
            'error_code' => null,
            'error_message' => null,
        ];

        if ($dto->attempt === 1) {
            $data['started_at'] = now();
        }

        $this->parseRunRepository->updateById($dto->parseRunId, $data);
    }

    public function recordProgress(ParseRunProgressDTO $dto): void
    {
        $this->parseRunRepository->updateById($dto->parseRunId, [
            'processed_reviews' => $dto->processedReviews,
            'processed_pages' => $dto->processedPages,
            'total_reviews' => $dto->totalReviews,
        ]);
    }

    public function markCompleted(ParseRunProgressDTO $dto): void
    {
        $this->parseRunRepository->updateById($dto->parseRunId, [
            'status' => ParseRunStatus::Completed,
            'processed_reviews' => $dto->processedReviews,
            'processed_pages' => $dto->processedPages,
            'total_reviews' => $dto->totalReviews,
            'error_code' => null,
            'error_message' => null,
            'finished_at' => now(),
        ]);
    }

    public function recordFailure(FailParseRunDTO $dto): void
    {
        $parseRun = $this->parseRunRepository->findById($dto->parseRunId);

        if ($parseRun === null) {
            return;
        }

        if ($dto->terminal) {
            $this->parseRunRepository->updateById($dto->parseRunId, [
                'status' => ParseRunStatus::Failed,
                'error_code' => $dto->errorCode,
                'error_message' => $dto->errorMessage,
                'finished_at' => now(),
            ]);

            $organization = $this->organizationRepository->findById($parseRun->organization_id);

            if ($organization !== null) {
                $this->organizationRepository->update($organization, [
                    'status' => OrganizationStatus::Failed,
                ]);
            }

            return;
        }

        $this->parseRunRepository->updateById($dto->parseRunId, [
            'status' => ParseRunStatus::Pending,
            'error_code' => $dto->errorCode,
            'error_message' => $dto->errorMessage,
        ]);
    }

    public function reapStale(): ReapedParseRunsDTO
    {
        $staleMinutes = (int) config('yandex.queue.stale_run_minutes', 30);

        $count = $this->parseRunRepository->markStaleAsFailed(
            now()->subMinutes($staleMinutes),
            'stale_run',
        );

        return new ReapedParseRunsDTO($count);
    }
}
