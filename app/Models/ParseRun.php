<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ParseRunStatus;
use Database\Factories\ParseRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property ParseRunStatus $status
 * @property int $processed_reviews
 * @property int|null $total_reviews
 * @property int $processed_pages
 * @property int $attempt
 * @property int $max_attempts
 * @property string|null $error_code
 * @property string|null $error_message
 * @property Carbon|null $queued_at
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 */
#[Fillable([
    'organization_id',
    'status',
    'processed_reviews',
    'total_reviews',
    'processed_pages',
    'attempt',
    'max_attempts',
    'error_code',
    'error_message',
    'queued_at',
    'started_at',
    'finished_at',
])]
class ParseRun extends Model
{
    /** @use HasFactory<ParseRunFactory> */
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
            'status' => ParseRunStatus::class,
            'processed_reviews' => 'integer',
            'total_reviews' => 'integer',
            'processed_pages' => 'integer',
            'attempt' => 'integer',
            'max_attempts' => 'integer',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
