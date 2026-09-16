<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

    public function definition(): array
    {
        return [
            'organization_id' => OrganizationFactory::new(),
            'external_id' => $this->faker->uuid,
            'author_name' => $this->faker->name,
            'rating' => random_int(0, 5),
            'text' => $this->faker->text,
            'review_date' => $this->faker->date,
        ];
    }
}
