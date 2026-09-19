<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Review;
use App\Models\User;
use App\Repositories\Interfaces\ReviewRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
use Tests\TestCase;

class ReviewApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/organization/reviews')->assertUnauthorized();
    }

    public function test_index_returns_404_when_user_has_no_current_organization(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/organization/reviews')
            ->assertNotFound();
    }

    public function test_index_returns_paginated_reviews(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->ready()->create();
        $user->forceFill(['current_organization_id' => $organization->id])->save();

        Review::factory()->count(55)->create([
            'organization_id' => $organization->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/organization/reviews?page=1&per_page=50');

        $response->assertOk()
            ->assertJsonCount(50, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 50)
            ->assertJsonPath('meta.total', 55)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'author_name', 'rating', 'text', 'review_date'],
                ],
                'links',
                'meta',
            ]);

        $this->actingAs($user)
            ->getJson('/api/organization/reviews?page=2&per_page=50')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.current_page', 2);
    }

    public function test_index_validates_pagination_params(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->ready()->create();
        $user->forceFill(['current_organization_id' => $organization->id])->save();

        $this->actingAs($user)
            ->getJson('/api/organization/reviews?page=0&per_page=100')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['page', 'per_page']);
    }

    public function test_index_serves_second_request_from_cache_without_extra_queries(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->ready()->create();
        $user->forceFill(['current_organization_id' => $organization->id])->save();

        Review::factory()->count(3)->create([
            'organization_id' => $organization->id,
        ]);

        $this->actingAs($user)
            ->getJson('/api/organization/reviews?page=1&per_page=50')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($user)
            ->getJson('/api/organization/reviews?page=1&per_page=50')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $reviewTableQueries = collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_contains($query['query'], 'from "reviews"')
                || str_contains($query['query'], 'from `reviews`'));

        $this->assertTrue(
            $reviewTableQueries->isEmpty(),
            'Expected cached reviews page to avoid querying the reviews table.',
        );
    }

    public function test_index_uses_repository_only_on_cache_miss(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->ready()->create();
        $user->forceFill(['current_organization_id' => $organization->id])->save();

        $reviews = Review::factory()->count(2)->create([
            'organization_id' => $organization->id,
        ]);

        $calls = 0;

        $this->mock(ReviewRepositoryInterface::class, function (MockInterface $mock) use (&$calls, $organization, $reviews): void {
            $mock->shouldReceive('paginateByOrganization')
                ->andReturnUsing(function (int $organizationId, int $perPage, int $page) use (&$calls, $organization, $reviews) {
                    $calls++;
                    $this->assertSame($organization->id, $organizationId);
                    $this->assertSame(50, $perPage);
                    $this->assertSame(1, $page);

                    return new LengthAwarePaginator(
                        $reviews,
                        $reviews->count(),
                        $perPage,
                        $page,
                    );
                });
        });

        $this->actingAs($user)
            ->getJson('/api/organization/reviews')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($user)
            ->getJson('/api/organization/reviews')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertSame(1, $calls);
    }
}
