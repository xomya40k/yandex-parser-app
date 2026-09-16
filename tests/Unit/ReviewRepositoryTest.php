<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Review;
use App\Repositories\ReviewRepository;
use Database\Factories\OrganizationFactory;
use Database\Factories\ReviewFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ReviewRepositoryTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private ReviewRepository $ReviewRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ReviewRepository = app(ReviewRepository::class);
    }

    public function test_update_or_create_review()
    {
        $reviewData = ReviewFactory::new()->make();

        $review = $this->ReviewRepository->updateOrCreate($reviewData->toArray());

        $this->assertInstanceOf(Review::class, $review);
        $this->assertEquals($reviewData->author_name, $review->author_name);
        $this->assertEquals($reviewData->text, $review->text);

        $text = $this->faker->text;

        $review = $this->ReviewRepository->updateOrCreate(array_merge($reviewData->toArray(), ['text' => $text]));

        $this->assertInstanceOf(Review::class, $review);
        $this->assertEquals($reviewData->author_name, $review->author_name);
        $this->assertEquals($text, $review->text);
    }

    public function test_paginate_by_organization()
    {
        $organizationId = OrganizationFactory::new()->create()->id;
        ReviewFactory::new()->count(10)->create(['organization_id' => $organizationId]);

        $reviewsPaginator = $this->ReviewRepository->paginateByOrganization($organizationId, 10, 1);

        $this->assertTrue($reviewsPaginator->isNotEmpty());
        $this->assertEquals(10, $reviewsPaginator->total());
        $this->assertEquals(1, $reviewsPaginator->currentPage());
    }

    public function test_get_сount_by_organization()
    {
        $organizationId = OrganizationFactory::new()->create()->id;
        ReviewFactory::new()->count(10)->create(['organization_id' => $organizationId]);

        $count = $this->ReviewRepository->getCountByOrganization($organizationId);

        $this->assertEquals(10, $count);
    }
}
