<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\Organization;
use App\Models\OrganizationSnapshot;
use App\Repositories\OrganizationSnapshotRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationSnapshotRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationSnapshotRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(OrganizationSnapshotRepository::class);
    }

    public function test_create_persists_snapshot(): void
    {
        $organization = Organization::factory()->create();
        $capturedAt = now()->subMinute();

        $snapshot = $this->repository->create([
            'organization_id' => $organization->id,
            'rating' => '4.50',
            'total_ratings' => 100,
            'total_reviews' => 40,
            'reviews_count' => 40,
            'payload' => [
                'before' => null,
                'after' => ['rating' => '4.50'],
            ],
            'captured_at' => $capturedAt,
        ]);

        $this->assertInstanceOf(OrganizationSnapshot::class, $snapshot);
        $this->assertDatabaseHas('organization_snapshots', [
            'id' => $snapshot->id,
            'organization_id' => $organization->id,
            'total_ratings' => 100,
            'total_reviews' => 40,
            'reviews_count' => 40,
        ]);
        $this->assertSame(['before' => null, 'after' => ['rating' => '4.50']], $snapshot->payload);
    }

    public function test_find_latest_for_organization_orders_by_captured_at(): void
    {
        $organization = Organization::factory()->create();
        $other = Organization::factory()->create();

        $this->repository->create([
            'organization_id' => $organization->id,
            'rating' => '4.00',
            'total_ratings' => 10,
            'total_reviews' => 5,
            'reviews_count' => 5,
            'payload' => ['label' => 'older'],
            'captured_at' => now()->subHours(2),
        ]);

        $latest = $this->repository->create([
            'organization_id' => $organization->id,
            'rating' => '4.80',
            'total_ratings' => 20,
            'total_reviews' => 8,
            'reviews_count' => 8,
            'payload' => ['label' => 'newer'],
            'captured_at' => now()->subHour(),
        ]);

        $this->repository->create([
            'organization_id' => $other->id,
            'rating' => '5.00',
            'total_ratings' => 99,
            'total_reviews' => 99,
            'reviews_count' => 99,
            'payload' => ['label' => 'other'],
            'captured_at' => now(),
        ]);

        $found = $this->repository->findLatestForOrganization($organization->id);

        $this->assertNotNull($found);
        $this->assertSame($latest->id, $found->id);
        $this->assertSame(['label' => 'newer'], $found->payload);
    }

    public function test_find_latest_for_organization_returns_null_when_none(): void
    {
        $organization = Organization::factory()->create();

        $this->assertNull($this->repository->findLatestForOrganization($organization->id));
    }
}
