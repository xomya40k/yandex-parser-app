<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrganizationStatus;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company,
            'yandex_maps_url' => $this->faker->url,
            'status' => $this->faker->randomElement(OrganizationStatus::cases()),
        ];
    }

    public function pending(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => OrganizationStatus::Pending,
            ];
        });
    }

    public function ready(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => OrganizationStatus::Ready,
            ];
        });
    }

    public function parsing(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => OrganizationStatus::Parsing,
            ];
        });
    }

    public function failed(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => OrganizationStatus::Failed,
            ];
        });
    }
}
