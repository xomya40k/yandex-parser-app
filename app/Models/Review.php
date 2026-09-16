<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $external_id
 * @property string $author_name
 * @property int $rating
 * @property string|null $text
 * @property Carbon $review_date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 */
#[Fillable([
    'organization_id',
    'external_id',
    'author_name',
    'rating',
    'text',
    'review_date',
])]
class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

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
            'rating' => 'integer',
            'review_date' => 'datetime',
        ];
    }
}
