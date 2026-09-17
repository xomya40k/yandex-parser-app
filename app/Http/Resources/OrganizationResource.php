<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Organization
 *
 * @property Organization $resource
 */
class OrganizationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'yandex_maps_url' => $this->resource->yandex_maps_url,
            'rating' => $this->resource->rating,
            'total_ratings' => $this->resource->total_ratings,
            'total_reviews' => $this->resource->total_reviews,
            'status' => $this->resource->status->value,
            'last_parsed_at' => $this->resource->last_parsed_at?->toIso8601String(),
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
