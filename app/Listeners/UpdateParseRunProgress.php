<?php

declare(strict_types=1);

namespace App\Listeners;

use App\DTOs\Parsing\ParseRunProgressDTO;
use App\Events\Parsing\YandexReviewsPageParsed;
use App\Services\Parsing\ParseRunService;

final class UpdateParseRunProgress
{
    public function __construct(
        private readonly ParseRunService $parseRunService,
    ) {}

    public function handle(YandexReviewsPageParsed $event): void
    {
        if (is_null($event->parseRunId)) {
            return;
        }

        $this->parseRunService->recordProgress(new ParseRunProgressDTO(
            parseRunId: $event->parseRunId,
            processedReviews: $event->totalCollected,
            processedPages: $event->page,
            totalReviews: $event->totalReviews,
        ));
    }
}
