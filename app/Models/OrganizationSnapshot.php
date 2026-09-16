<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property string|null $rating
 * @property int $total_ratings
 * @property int $total_reviews
 * @property int $reviews_count
 * @property array<string, mixed>|null $payload
 * @property Carbon $captured_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 */
#[Fillable([
    'organization_id',
    'rating',
    'total_ratings',
    'total_reviews',
    'reviews_count',
    'payload',
    'captured_at',
])]
class OrganizationSnapshot extends Model
{
    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'decimal:2',
            'total_ratings' => 'integer',
            'total_reviews' => 'integer',
            'reviews_count' => 'integer',
            'payload' => 'array',
            'captured_at' => 'datetime',
        ];
    }
}
