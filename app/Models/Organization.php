<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrganizationStatus;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $name
 * @property string $yandex_maps_url
 * @property string|null $rating
 * @property int $total_ratings
 * @property int $total_reviews
 * @property Carbon|null $last_parsed_at
 * @property OrganizationStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name',
    'yandex_maps_url',
    'rating',
    'total_ratings',
    'total_reviews',
    'last_parsed_at',
    'status',
])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    /**
     * @return HasMany<Review, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * @return HasMany<OrganizationSnapshot, $this>
     */
    public function snapshots(): HasMany
    {
        return $this->hasMany(OrganizationSnapshot::class);
    }

    /**
     * @return HasMany<ParseRun, $this>
     */
    public function parseRuns(): HasMany
    {
        return $this->hasMany(ParseRun::class);
    }

    /**
     * @return HasOne<ParseRun, $this>
     */
    public function latestParseRun(): HasOne
    {
        return $this->hasOne(ParseRun::class)->latestOfMany();
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
            'last_parsed_at' => 'datetime',
            'status' => OrganizationStatus::class,
        ];
    }
}
