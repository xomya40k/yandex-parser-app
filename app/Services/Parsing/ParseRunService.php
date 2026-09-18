<?php

declare(strict_types=1);

namespace App\Services\Parsing;

use App\DTOs\Parsing\ParseRunProgressDTO;
use App\Repositories\Interfaces\ParseRunRepositoryInterface;

class ParseRunService
{
    public function __construct(
        private readonly ParseRunRepositoryInterface $parseRunRepository,
    ) {}

    public function recordProgress(ParseRunProgressDTO $dto): void
    {
        $this->parseRunRepository->updateById($dto->parseRunId, [
            'processed_reviews' => $dto->processedReviews,
            'processed_pages' => $dto->processedPages,
            'total_reviews' => $dto->totalReviews,
        ]);
    }
}
