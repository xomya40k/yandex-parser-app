<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Review
 *
 * @property Review $resource
 */
class ReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'author_name' => $this->resource->author_name,
            'rating' => $this->resource->rating,
            'text' => $this->resource->text,
            'review_date' => $this->resource->review_date->toIso8601String(),
        ];
    }
}
