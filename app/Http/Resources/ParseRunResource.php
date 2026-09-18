<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ParseRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ParseRun
 *
 * @property ParseRun|null $resource
 */
class ParseRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if (is_null($this->resource)) {
            return [];
        }

        $progressPercent = null;
        if (!is_null($this->total_reviews) && $this->total_reviews > 0) {
            $progressPercent = min(
                100,
                (int) round($this->processed_reviews / $this->total_reviews * 100),
            );
        }

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'processed_reviews' => $this->processed_reviews,
            'total_reviews' => $this->total_reviews,
            'processed_pages' => $this->processed_pages,
            'progress_percent' => $progressPercent,
            'attempt' => $this->attempt,
            'max_attempts' => $this->max_attempts,
            'error_code' => $this->error_code,
            'error_message' => $this->error_message,
            'queued_at' => $this->queued_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
        ];
    }
}
