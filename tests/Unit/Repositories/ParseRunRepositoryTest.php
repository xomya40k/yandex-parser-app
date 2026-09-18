<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\ParseRunStatus;
use App\Models\Organization;
use App\Models\ParseRun;
use App\Repositories\ParseRunRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParseRunRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private ParseRunRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(ParseRunRepository::class);
    }

    public function test_find_active_for_organization_returns_pending_or_processing(): void
    {
        $organization = Organization::factory()->create();
        $other = Organization::factory()->create();

        ParseRun::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);
        $active = ParseRun::factory()->processing()->create([
            'organization_id' => $organization->id,
        ]);
        ParseRun::factory()->pending()->create([
            'organization_id' => $other->id,
        ]);

        $found = $this->repository->findActiveForOrganization($organization->id);

        $this->assertNotNull($found);
        $this->assertSame($active->id, $found->id);
        $this->assertSame(ParseRunStatus::Processing, $found->status);
    }

    public function test_find_active_for_organization_returns_null_when_only_finished(): void
    {
        $organization = Organization::factory()->create();

        ParseRun::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);
        ParseRun::factory()->failed()->create([
            'organization_id' => $organization->id,
        ]);

        $this->assertNull($this->repository->findActiveForOrganization($organization->id));
    }

    public function test_find_latest_for_organization_orders_by_id_desc(): void
    {
        $organization = Organization::factory()->create();

        ParseRun::factory()->pending()->create([
            'organization_id' => $organization->id,
        ]);
        $latest = ParseRun::factory()->processing()->create([
            'organization_id' => $organization->id,
        ]);

        $found = $this->repository->findLatestForOrganization($organization->id);

        $this->assertNotNull($found);
        $this->assertSame($latest->id, $found->id);
    }

    public function test_mark_stale_as_failed_updates_only_old_processing_runs(): void
    {
        $stale = ParseRun::factory()->processing()->create([
            'started_at' => now()->subHours(2),
        ]);
        $fresh = ParseRun::factory()->processing()->create([
            'started_at' => now()->subMinutes(5),
        ]);
        $pending = ParseRun::factory()->pending()->create([
            'started_at' => null,
        ]);

        $count = $this->repository->markStaleAsFailed(now()->subHour(), 'stale_run');

        $this->assertSame(1, $count);

        $stale->refresh();
        $this->assertSame(ParseRunStatus::Failed, $stale->status);
        $this->assertSame('stale_run', $stale->error_code);
        $this->assertNotNull($stale->finished_at);

        $this->assertSame(ParseRunStatus::Processing, $fresh->fresh()->status);
        $this->assertSame(ParseRunStatus::Pending, $pending->fresh()->status);
    }
}
