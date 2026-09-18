<?php

declare(strict_types=1);

namespace App\Jobs;

use App\DTOs\Organization\SyncOrganizationDTO;
use App\DTOs\Parsing\FailParseRunDTO;
use App\Exceptions\Parsing\YandexParsingException;
use App\Services\Organization\OrganizationSyncService;
use App\Services\Parsing\ParseRunService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

final class ParseOrganizationJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public bool $failOnTimeout = true;

    public int $maxExceptions = 3;

    public function __construct(
        public readonly int $organizationId,
        public readonly int $parseRunId,
    ) {
        $this->onQueue((string) config('yandex.queue.name', 'parsing'));
        $this->timeout = (int) config('yandex.queue.timeout', 900);
    }

    public function tries(): int
    {
        return (int) config('yandex.queue.tries', 3);
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        /** @var list<int> $backoff */
        $backoff = config('yandex.queue.backoff', [60, 300, 900]);

        return $backoff;
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("parse-organization:{$this->organizationId}"))
                ->dontRelease()
                ->expireAfter($this->timeout),
        ];
    }

    public function handle(OrganizationSyncService $syncService): void
    {
        try {
            $syncService->sync(new SyncOrganizationDTO(
                organizationId: $this->organizationId,
                parseRunId: $this->parseRunId,
                attempt: $this->attempts(),
            ));
        } catch (YandexParsingException $exception) {
            if (!$exception->isRetryable()) {
                $this->fail($exception);

                return;
            }

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(ParseRunService::class)->recordFailure(new FailParseRunDTO(
            parseRunId: $this->parseRunId,
            errorCode: $exception instanceof YandexParsingException
                ? $exception->errorCode()
                : 'unexpected_error',
            errorMessage: $exception?->getMessage(),
            terminal: true,
        ));
    }
}
