<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ParseRunStatus;
use App\Models\Organization;
use App\Models\ParseRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ParseRun>
 */
class ParseRunFactory extends Factory
{
    protected $model = ParseRun::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'status' => ParseRunStatus::Pending,
            'processed_reviews' => 0,
            'total_reviews' => null,
            'processed_pages' => 0,
            'attempt' => 0,
            'max_attempts' => 3,
            'queued_at' => now(),
        ];
    }

    public function pending(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => ParseRunStatus::Pending,
                'queued_at' => now(),
            ];
        });
    }

    public function processing(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => ParseRunStatus::Processing,
                'attempt' => 1,
                'processed_reviews' => $this->faker->numberBetween(1, 200),
                'processed_pages' => $this->faker->numberBetween(1, 4),
                'total_reviews' => $this->faker->numberBetween(200, 600),
                'queued_at' => now(),
                'started_at' => now(),
            ];
        });
    }

    public function completed(): self
    {
        return $this->state(function (array $attributes) {
            $totalReviews = $this->faker->numberBetween(1, 600);

            return [
                'status' => ParseRunStatus::Completed,
                'attempt' => 1,
                'processed_reviews' => $totalReviews,
                'processed_pages' => (int) ceil($totalReviews / 50),
                'total_reviews' => $totalReviews,
                'queued_at' => now(),
                'started_at' => now(),
                'finished_at' => now(),
            ];
        });
    }

    public function failed(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => ParseRunStatus::Failed,
                'attempt' => $attributes['max_attempts'] ?? 3,
                'error_code' => $this->faker->randomElement([
                    'captcha_required',
                    'invalid_layout',
                    'organization_unavailable',
                ]),
                'error_message' => $this->faker->sentence(),
                'queued_at' => now(),
                'started_at' => now(),
                'finished_at' => now(),
            ];
        });
    }
}
