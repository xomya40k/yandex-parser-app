<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Organization;
use App\Models\Review;
use App\Repositories\ReviewRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private ReviewRepository $reviewRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reviewRepository = app(ReviewRepository::class);
    }

    public function test_update_or_create_review(): void
    {
        $reviewData = Review::factory()->make();

        $review = $this->reviewRepository->updateOrCreate($reviewData->toArray());

        $this->assertInstanceOf(Review::class, $review);
        $this->assertEquals($reviewData->author_name, $review->author_name);
        $this->assertEquals($reviewData->text, $review->text);

        $text = fake()->paragraph();

        $review = $this->reviewRepository->updateOrCreate(array_merge($reviewData->toArray(), ['text' => $text]));

        $this->assertInstanceOf(Review::class, $review);
        $this->assertEquals($reviewData->author_name, $review->author_name);
        $this->assertEquals($text, $review->text);
    }

    public function test_paginate_by_organization(): void
    {
        $organization = Organization::factory()->create();
        Review::factory()->count(10)->create(['organization_id' => $organization->id]);

        $reviewsPaginator = $this->reviewRepository->paginateByOrganization($organization->id, 10, 1);

        $this->assertTrue($reviewsPaginator->isNotEmpty());
        $this->assertEquals(10, $reviewsPaginator->total());
        $this->assertEquals(1, $reviewsPaginator->currentPage());
    }

    public function test_get_count_by_organization(): void
    {
        $organization = Organization::factory()->create();
        Review::factory()->count(10)->create(['organization_id' => $organization->id]);

        $count = $this->reviewRepository->getCountByOrganization($organization->id);

        $this->assertEquals(10, $count);
    }
}
