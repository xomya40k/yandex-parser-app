<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Enums\ParseRunStatus;
use App\Jobs\ParseOrganizationJob;
use App\Models\Organization;
use App\Models\ParseRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QueueOrganizationParsingCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_reports_when_nothing_is_due(): void
    {
        Organization::factory()->ready()->create([
            'last_parsed_at' => now(),
        ]);

        $this->artisan('yandex:queue-parsing')
            ->expectsOutputToContain('No organizations due for sync.')
            ->assertSuccessful();
    }

    public function test_due_organizations_get_jobs_queued(): void
    {
        Queue::fake();

        $first = Organization::factory()->pending()->create();
        $second = Organization::factory()->pending()->create();

        $this->artisan('yandex:queue-parsing')
            ->expectsOutputToContain('Queued: 2.')
            ->assertSuccessful();

        Queue::assertPushed(ParseOrganizationJob::class, 2);
        Queue::assertPushed(ParseOrganizationJob::class, function (ParseOrganizationJob $job) use ($first): bool {
            return $job->organizationId === $first->id;
        });
        Queue::assertPushed(ParseOrganizationJob::class, function (ParseOrganizationJob $job) use ($second): bool {
            return $job->organizationId === $second->id;
        });

        $this->assertDatabaseHas('parse_runs', [
            'organization_id' => $first->id,
            'status' => ParseRunStatus::Pending->value,
        ]);
        $this->assertDatabaseHas('parse_runs', [
            'organization_id' => $second->id,
            'status' => ParseRunStatus::Pending->value,
        ]);
    }

    public function test_stale_processing_runs_are_reaped(): void
    {
        Queue::fake();

        $organization = Organization::factory()->pending()->create();
        $stale = ParseRun::factory()->processing()->create([
            'organization_id' => $organization->id,
            'started_at' => now()->subHours(2),
            'updated_at' => now()->subHours(2),
        ]);

        $this->artisan('yandex:queue-parsing')
            ->expectsOutputToContain('Reaped 1 stale parse run(s).')
            ->assertSuccessful();

        $this->assertSame(ParseRunStatus::Failed, $stale->fresh()->status);
        $this->assertSame('stale_run', $stale->fresh()->error_code);

        Queue::assertPushed(ParseOrganizationJob::class, 1);
    }
}
